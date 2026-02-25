<?php

declare(strict_types=1);

namespace CronBeats\PingSdk\Exception;

class ApiException extends SdkException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly ?int $httpStatus,
        private readonly bool $retryable,
        string $message,
        private readonly mixed $raw = null
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getRaw(): mixed
    {
        return $this->raw;
    }
}
