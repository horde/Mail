<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit;

use Horde\Mail\MailException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MailException::class)]
class MailExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $e = new MailException();
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testDefaultValues(): void
    {
        $e = new MailException();
        $this->assertSame('', $e->getMessage());
        $this->assertSame(0, $e->getCode());
        $this->assertNull($e->getPrevious());
    }

    public function testCustomMessage(): void
    {
        $e = new MailException('test error', 42);
        $this->assertSame('test error', $e->getMessage());
        $this->assertSame(42, $e->getCode());
    }

    public function testPreviousException(): void
    {
        $prev = new RuntimeException('cause');
        $e = new MailException('wrapped', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
