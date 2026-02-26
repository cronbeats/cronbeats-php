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

## Progress Tracking

Track your job's progress in real-time. CronBeats supports two distinct modes:

### Mode 1: With Percentage (0-100)
Shows a **progress bar** and your status message on the dashboard.

✓ **Use when**: You can calculate meaningful progress (e.g., processed 750 of 1000 records)

```php
<?php

// Percentage mode: 0-100 with message
$client->progress(50, 'Processing batch 500/1000');

// Or using options array
$client->progress([
    'seq' => 75,
    'message' => 'Almost done - 750/1000',
]);
```

### Mode 2: Message Only
Shows **only your status message** (no percentage bar) on the dashboard.

✓ **Use when**: Progress isn't measurable or you only want to send status updates

```php
<?php

// Message-only mode: null seq, just status updates
$client->progress(null, 'Connecting to database...');
$client->progress(null, 'Starting data sync...');
```

### What you see on the dashboard
- **Mode 1**: Progress bar (0-100%) + your message → "75% - Processing batch 750/1000"
- **Mode 2**: Only your status message → "Connecting to database..."

### Complete Example

```php
<?php

$client = new PingClient('abc123de');
$client->start();

try {
    // Message-only updates for non-measurable steps
    $client->progress(null, 'Connecting to database...');
    $db = connectToDatabase();
    
    $client->progress(null, 'Fetching records...');
    $total = $db->count();
    
    // Percentage updates for measurable progress
    for ($i = 0; $i < $total; $i++) {
        processRecord($i);
        
        if ($i % 100 === 0) {
            $percent = (int)($i * 100 / $total);
            $client->progress($percent, "Processed $i / $total records");
        }
    }
    
    $client->progress(100, 'All records processed');
    $client->success();
    
} catch (Exception $e) {
    $client->fail();
    throw $e;
}
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
