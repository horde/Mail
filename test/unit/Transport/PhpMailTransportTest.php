<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Transport\PhpMailConfig;
use Horde\Mail\Transport\PhpMailTransport;
use Horde\Mail\Transport\Transport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpMailTransport::class)]
#[CoversClass(PhpMailConfig::class)]
class PhpMailTransportTest extends TestCase
{
    public function testImplementsTransport(): void
    {
        $this->assertInstanceOf(Transport::class, new PhpMailTransport());
    }

    public function testSupportsEaiReturnsFalse(): void
    {
        $this->assertFalse((new PhpMailTransport())->supportsEai());
    }

    public function testConfigDefaults(): void
    {
        $config = new PhpMailConfig();
        $this->assertSame('', $config->additionalArgs);
    }

    public function testConfigCustomArgs(): void
    {
        $config = new PhpMailConfig(additionalArgs: '-f bounce@example.com');
        $this->assertSame('-f bounce@example.com', $config->additionalArgs);
    }
}
