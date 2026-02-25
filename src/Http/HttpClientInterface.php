<?php

declare(strict_types=1);

namespace CronBeats\PingSdk\Http;

interface HttpClientInterface
{
    /**
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutMs = 5000
    ): array;
}
