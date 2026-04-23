<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Transport\LmtpTransport;
use Horde\Mail\Transport\Transport;
use Horde\Mail\Transport\TransportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LmtpTransport::class)]
class LmtpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Horde\Smtp\SmtpConfig::class)) {
            $this->markTestSkipped('horde/smtp not installed.');
        }
    }

    public function testImplementsTransport(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 24);
        $this->assertInstanceOf(Transport::class, new LmtpTransport($config));
    }

    public function testSupportsEaiBeforeConnection(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 24);
        $transport = new LmtpTransport($config);
        $this->assertFalse($transport->supportsEai());
    }

    public function testLastSendResultNullBeforeSend(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 24);
        $transport = new LmtpTransport($config);
        $this->assertNull($transport->lastSendResult());
    }

    public function testConnectionFailureThrowsTransportException(): void
    {
        $this->expectException(TransportException::class);

        $config = new \Horde\Smtp\SmtpConfig(host: '192.0.2.1', port: 1, timeout: 1);
        $transport = new LmtpTransport($config);
        $transport->client();
    }
}
