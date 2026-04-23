<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\ParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ParseException::class)]
class ParseExceptionTest extends TestCase
{
    public function testBasicException(): void
    {
        $e = new ParseException('test error');
        $this->assertSame('test error', $e->getMessage());
        $this->assertNull($e->position);
        $this->assertNull($e->input);
    }

    public function testWithPositionAndInput(): void
    {
        $e = new ParseException('bad char', position: 5, input: 'foo@bar');
        $this->assertSame(5, $e->position);
        $this->assertSame('foo@bar', $e->input);
    }

    public function testExtendsRuntimeException(): void
    {
        $e = new ParseException('test');
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testWithPrevious(): void
    {
        $prev = new RuntimeException('cause');
        $e = new ParseException('wrapped', previous: $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
