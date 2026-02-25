<?php

declare(strict_types=1);

namespace CronBeats\PingSdk;

use CronBeats\PingSdk\Exception\ApiException;
use CronBeats\PingSdk\Exception\SdkException;
use CronBeats\PingSdk\Exception\ValidationException;
use CronBeats\PingSdk\Http\CurlHttpClient;
use CronBeats\PingSdk\Http\HttpClientInterface;

class PingClient
{
    private string $baseUrl;
    private string $jobKey;
    private int $timeoutMs;
    private int $maxRetries;
    private int $retryBackoffMs;
    private int $retryJitterMs;
    private string $userAgent;
    private HttpClientInterface $httpClient;

    /**
     * @param array{
     *   baseUrl?:string,
     *   timeoutMs?:int,
     *   maxRetries?:int,
     *   retryBackoffMs?:int,
     *   retryJitterMs?:int,
     *   userAgent?:string,
     *   httpClient?:HttpClientInterface
     * } $options
     */
    public function __construct(string $jobKey, array $options = [])
    {
        $this->assertJobKey($jobKey);

        $this->jobKey = $jobKey;
        $this->baseUrl = rtrim((string) ($options['baseUrl'] ?? 'https://cronbeats.io'), '/');
        $this->timeoutMs = (int) ($options['timeoutMs'] ?? 5000);
        $this->maxRetries = (int) ($options['maxRetries'] ?? 2);
        $this->retryBackoffMs = (int) ($options['retryBackoffMs'] ?? 250);
        $this->retryJitterMs = (int) ($options['retryJitterMs'] ?? 100);
        $this->userAgent = (string) ($options['userAgent'] ?? 'cronbeats-php-sdk/0.1.0');
        $this->httpClient = $options['httpClient'] ?? new CurlHttpClient();
    }

    public function ping(): array
    {
        return $this->request('ping', '/ping/' . $this->jobKey);
    }

    public function start(): array
    {
        return $this->request('start', '/ping/' . $this->jobKey . '/start');
    }

    public function end(string $status = 'success'): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['success', 'fail'], true)) {
            throw new ValidationException('Status must be "success" or "fail".');
        }

        return $this->request('end', '/ping/' . $this->jobKey . '/end/' . $status);
    }

    public function success(): array
    {
        return $this->end('success');
    }

    public function fail(): array
    {
        return $this->end('fail');
    }

    /**
     * @param int|array{seq?:int,message?:string}|null $seqOrOptions
     */
    public function progress(int|array|null $seqOrOptions = null, ?string $message = null): array
    {
        $seq = null;
        $msg = $message;

        if (is_int($seqOrOptions)) {
            $seq = $seqOrOptions;
        } elseif (is_array($seqOrOptions)) {
            $seq = isset($seqOrOptions['seq']) ? (int) $seqOrOptions['seq'] : null;
            $msg = (string) ($seqOrOptions['message'] ?? $msg ?? '');
        }

        if ($seq !== null && $seq < 0) {
            throw new ValidationException('Progress seq must be a non-negative integer.');
        }

        $msg = (string) ($msg ?? '');
        if (strlen($msg) > 255) {
            $msg = substr($msg, 0, 255);
        }

        if ($seq !== null) {
            return $this->request(
                'progress',
                '/ping/' . $this->jobKey . '/progress/' . $seq,
                ['message' => $msg]
            );
        }

        $body = ['message' => $msg];
        if (is_int($seqOrOptions)) {
            $body['progress'] = $seqOrOptions;
        }

        return $this->request('progress', '/ping/' . $this->jobKey . '/progress', $body);
    }

    private function request(string $action, string $path, array $body = []): array
    {
        $attempt = 0;
        $url = $this->baseUrl . $path;
        $payload = empty($body) ? null : json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new SdkException('Failed to encode request payload.');
        }

        while (true) {
            try {
                $response = $this->httpClient->request(
                    'POST',
                    $url,
                    [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'User-Agent' => $this->userAgent,
                    ],
                    $payload,
                    $this->timeoutMs
                );
            } catch (SdkException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw new ApiException(
                        'NETWORK_ERROR',
                        null,
                        true,
                        $e->getMessage(),
                        $e
                    );
                }
                $attempt++;
                $this->sleepWithBackoff($attempt);
                continue;
            }

            $status = $response['status'];
            $decoded = json_decode($response['body'], true);
            $decoded = is_array($decoded) ? $decoded : ['message' => 'Invalid JSON response'];

            if ($status >= 200 && $status < 300) {
                return $this->normalizeSuccess($action, $decoded);
            }

            $error = $this->mapError($status, $decoded);
            if ($error['retryable'] && $attempt < $this->maxRetries) {
                $attempt++;
                $this->sleepWithBackoff($attempt);
                continue;
            }

            throw new ApiException(
                $error['code'],
                $status,
                $error['retryable'],
                (string) ($decoded['message'] ?? 'Request failed'),
                $decoded
            );
        }
    }

    private function normalizeSuccess(string $action, array $payload): array
    {
        return [
            'ok' => true,
            'action' => (string) ($payload['action'] ?? $action),
            'jobKey' => (string) ($payload['job_key'] ?? $this->jobKey),
            'timestamp' => (string) ($payload['timestamp'] ?? ''),
            'processingTimeMs' => isset($payload['processing_time_ms']) ? (float) $payload['processing_time_ms'] : 0.0,
            'nextExpected' => isset($payload['next_expected']) ? (string) $payload['next_expected'] : null,
            'raw' => $payload,
        ];
    }

    /**
     * @return array{code:string,retryable:bool}
     */
    private function mapError(int $status, array $payload): array
    {
        if ($status === 400) {
            return ['code' => 'VALIDATION_ERROR', 'retryable' => false];
        }
        if ($status === 404) {
            return ['code' => 'NOT_FOUND', 'retryable' => false];
        }
        if ($status === 429) {
            return ['code' => 'RATE_LIMITED', 'retryable' => true];
        }
        if ($status >= 500) {
            return ['code' => 'SERVER_ERROR', 'retryable' => true];
        }

        return ['code' => 'UNKNOWN_ERROR', 'retryable' => false];
    }

    private function assertJobKey(string $jobKey): void
    {
        if (!preg_match('/^[a-zA-Z0-9]{8}$/', $jobKey)) {
            throw new ValidationException('jobKey must be exactly 8 Base62 characters.');
        }
    }

    private function sleepWithBackoff(int $attempt): void
    {
        $base = $this->retryBackoffMs * (2 ** max(0, $attempt - 1));
        $jitter = random_int(0, max(0, $this->retryJitterMs));
        usleep((int) (($base + $jitter) * 1000));
    }
}
