<?php

declare(strict_types=1);

namespace CronBeats\PingSdk\Tests;

use CronBeats\PingSdk\Exception\ValidationException;
use CronBeats\PingSdk\PingClient;
use PHPUnit\Framework\TestCase;

class PingClientValidationTest extends TestCase
{
    public function testRejectsInvalidJobKey(): void
    {
        $this->expectException(ValidationException::class);
        new PingClient('invalid-key');
    }

    public function testAcceptsValidJobKey(): void
    {
        $client = new PingClient('abc123de');
        $this->assertInstanceOf(PingClient::class, $client);
    }
}
