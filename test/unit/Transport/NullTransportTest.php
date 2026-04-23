<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Transport\NullTransport;
use Horde\Mail\Transport\Transport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullTransport::class)]
class NullTransportTest extends TestCase
{
    public function testImplementsTransport(): void
    {
        $this->assertInstanceOf(Transport::class, new NullTransport());
    }

    public function testSendDoesNothing(): void
    {
        $transport = new NullTransport();
        $recipients = AddressList::from(new Address('test', 'example.com'));

        $transport->send(
            $recipients,
            ['From' => 'sender@example.com', 'Subject' => 'Test'],
            'Hello',
        );

        $this->assertTrue(true);
    }

    public function testSupportsEaiReturnsFalse(): void
    {
        $this->assertFalse((new NullTransport())->supportsEai());
    }
}
