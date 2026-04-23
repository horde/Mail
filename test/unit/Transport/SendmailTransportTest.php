<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Transport\SendmailConfig;
use Horde\Mail\Transport\SendmailTransport;
use Horde\Mail\Transport\Transport;
use Horde\Mail\Transport\TransportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendmailTransport::class)]
#[CoversClass(SendmailConfig::class)]
class SendmailTransportTest extends TestCase
{
    public function testImplementsTransport(): void
    {
        $this->assertInstanceOf(Transport::class, new SendmailTransport());
    }

    public function testSupportsEaiReturnsFalse(): void
    {
        $this->assertFalse((new SendmailTransport())->supportsEai());
    }

    public function testConfigDefaults(): void
    {
        $config = new SendmailConfig();
        $this->assertSame('/usr/sbin/sendmail', $config->sendmailPath);
        $this->assertSame('-i', $config->sendmailArgs);
    }

    public function testConfigCustomValues(): void
    {
        $config = new SendmailConfig(
            sendmailPath: '/usr/local/bin/sendmail',
            sendmailArgs: '-oi -t',
        );
        $this->assertSame('/usr/local/bin/sendmail', $config->sendmailPath);
        $this->assertSame('-oi -t', $config->sendmailArgs);
    }

    public function testMissingFromThrows(): void
    {
        $this->expectException(TransportException::class);

        $recipients = AddressList::from(new Address('test', 'example.com'));
        $transport = new SendmailTransport();
        $transport->send($recipients, [], 'Body');
    }

    public function testNonExistentSendmailThrows(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('sendmail:');

        $config = new SendmailConfig(sendmailPath: '/nonexistent/sendmail');
        $transport = new SendmailTransport($config);
        $recipients = AddressList::from(new Address('test', 'example.com'));

        $transport->send(
            $recipients,
            ['From' => 'sender@example.com'],
            'Test body',
        );
    }
}
