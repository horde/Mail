<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Mbox;

use DateTimeImmutable;
use Horde\Mail\Mbox\MboxMessage;
use Horde\Stream\Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MboxMessage::class)]
class MboxMessageTest extends TestCase
{
    public function testConstruction(): void
    {
        $stream = new Temp();
        $stream->add('test data');
        $date = new DateTimeImmutable('2024-01-15 10:30:00');

        $msg = new MboxMessage(data: $stream, date: $date, size: 9);

        $this->assertSame($stream, $msg->data);
        $this->assertSame($date, $msg->date);
        $this->assertSame(9, $msg->size);
    }

    public function testNullDate(): void
    {
        $stream = new Temp();
        $msg = new MboxMessage(data: $stream, date: null, size: 0);
        $this->assertNull($msg->date);
    }

    public function testReadonly(): void
    {
        $reflection = new ReflectionClass(MboxMessage::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
