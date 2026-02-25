<?php

declare(strict_types=1);

namespace CronBeats\PingSdk\Tests;

use CronBeats\PingSdk\Exception\ApiException;
use CronBeats\PingSdk\Http\HttpClientInterface;
use CronBeats\PingSdk\PingClient;
use PHPUnit\Framework\TestCase;

class PingClientBehaviorTest extends TestCase
{
    public function testNormalizesSuccessResponse(): void
    {
        $http = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutMs = 5000): array
            {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'status' => 'success',
                        'message' => 'OK',
                        'job_key' => 'abc123de',
                        'action' => 'ping',
                        'timestamp' => '2026-02-25 12:00:00',
                        'processing_time_ms' => 7.5,
                    ], JSON_UNESCAPED_SLASHES),
                    'headers' => [],
                ];
            }
        };

        $client = new PingClient('abc123de', ['httpClient' => $http]);
        $result = $client->ping();

        $this->assertTrue($result['ok']);
        $this->assertSame('ping', $result['action']);
        $this->assertSame('abc123de', $result['jobKey']);
    }

    public function testThrowsApiExceptionForNotFound(): void
    {
        $http = new class implements HttpClientInterface {
            public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutMs = 5000): array
            {
                return [
                    'status' => 404,
                    'body' => json_encode(['status' => 'error', 'message' => 'Job not found or disabled']),
                    'headers' => [],
                ];
            }
        };

        $client = new PingClient('abc123de', ['httpClient' => $http, 'maxRetries' => 0]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Job not found or disabled');
        $client->ping();
    }
}
