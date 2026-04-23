<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Transport\SmtpTransport;
use Horde\Mail\Transport\Transport;
use Horde\Mail\Transport\TransportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpTransport::class)]
class SmtpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Horde\Smtp\SmtpConfig::class)) {
            $this->markTestSkipped('horde/smtp not installed.');
        }
    }

    public function testImplementsTransport(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 25);
        $this->assertInstanceOf(Transport::class, new SmtpTransport($config));
    }

    public function testSupportsEaiBeforeConnection(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 25);
        $transport = new SmtpTransport($config);
        $this->assertFalse($transport->supportsEai());
    }

    public function testLastSendResultNullBeforeSend(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 25);
        $transport = new SmtpTransport($config);
        $this->assertNull($transport->lastSendResult());
    }

    public function testConnectionFailureThrowsTransportException(): void
    {
        $this->expectException(TransportException::class);

        $config = new \Horde\Smtp\SmtpConfig(host: '192.0.2.1', port: 1, timeout: 1);
        $transport = new SmtpTransport($config);
        $transport->client();
    }

    public function testDefaultProtocolIsSmtp(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 25);
        $transport = new SmtpTransport($config);
        $this->assertInstanceOf(SmtpTransport::class, $transport);
    }

    public function testLmtpProtocol(): void
    {
        $config = new \Horde\Smtp\SmtpConfig(host: 'localhost', port: 24);
        $transport = new SmtpTransport($config, \Horde\Smtp\Protocol::Lmtp);
        $this->assertInstanceOf(SmtpTransport::class, $transport);
    }
}
