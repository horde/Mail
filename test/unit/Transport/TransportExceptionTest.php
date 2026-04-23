<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\MailException;
use Horde\Mail\Transport\TransportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TransportException::class)]
class TransportExceptionTest extends TestCase
{
    public function testExtendsMailException(): void
    {
        $e = new TransportException('transport failed');
        $this->assertInstanceOf(MailException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testMessageAndCode(): void
    {
        $e = new TransportException('connection refused', 111);
        $this->assertSame('connection refused', $e->getMessage());
        $this->assertSame(111, $e->getCode());
    }

    public function testPreviousException(): void
    {
        $prev = new RuntimeException('inner');
        $e = new TransportException('outer', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
