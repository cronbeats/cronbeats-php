# CronBeats PHP SDK (Ping)

[![Latest Version](https://img.shields.io/packagist/v/cronbeats/cronbeats-php)](https://packagist.org/packages/cronbeats/cronbeats-php)
[![Total Downloads](https://img.shields.io/packagist/dt/cronbeats/cronbeats-php)](https://packagist.org/packages/cronbeats/cronbeats-php)
[![License](https://img.shields.io/github/license/cronbeats/cronbeats-php)](LICENSE)

Official PHP SDK for CronBeats ping telemetry.

## Install

```bash
composer require cronbeats/cronbeats-php
```

## Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CronBeats\PingSdk\PingClient;

$client = new PingClient('abc123de', [
    'baseUrl' => 'https://cronbeats.io',
    'timeoutMs' => 5000,
    'maxRetries' => 2,
]);

// Simple heartbeat
$client->ping();

// Start/end signals
$client->start();
// ... do work ...
$client->success();
```

## Real-World Cron Job Example

```php
<?php

$client = new PingClient('abc123de');
$client->start();

try {
    // your actual cron work
    processEmails();
    $client->success();
} catch (Exception $e) {
    $client->fail();
}
```

## Progress Updates

```php
<?php

// Path seq form
$client->progress(50, 'Processing batch 50/100');

// Options form
$client->progress([
    'seq' => 75,
    'message' => 'Almost done',
]);
```

## Error Handling

```php
<?php

use CronBeats\PingSdk\Exception\ApiException;
use CronBeats\PingSdk\Exception\ValidationException;

try {
    $client->ping();
} catch (ValidationException $e) {
    // Invalid local inputs like malformed job key
} catch (ApiException $e) {
    // API/network issue with normalized metadata
    $code = $e->getErrorCode();   // e.g. RATE_LIMITED
    $status = $e->getHttpStatus(); // e.g. 429
}
```

## API Surface

- `ping()`
- `start()`
- `end('success'|'fail')`
- `success()`
- `fail()`
- `progress(int|array|null $seqOrOptions, ?string $message = null)`

## Notes

- SDK uses `POST` for telemetry requests.
- `jobKey` must be exactly 8 Base62 characters.
- Retries happen only for network errors, HTTP `429`, and HTTP `5xx`.
- Default 5s timeout ensures the SDK never blocks your cron job if CronBeats is unreachable.
