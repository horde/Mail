<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Mbox;

use Horde\Mail\MailException;
use Horde\Mail\Mbox\MboxMessage;
use Horde\Mail\Mbox\MboxParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MboxParser::class)]
#[CoversClass(MboxMessage::class)]
class MboxParserTest extends TestCase
{
    private static function fixtureDir(): string
    {
        return __DIR__ . '/../../fixtures';
    }

    public function testMboxParse(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.mbox');

        $this->assertCount(2, $parser);

        $i = 0;
        foreach ($parser as $key => $msg) {
            $this->assertSame($i, $key);
            $this->assertInstanceOf(MboxMessage::class, $msg);

            $msg->data->rewind();
            $firstLine = fgets($msg->data->getResource());
            $this->assertSame("Return-Path: <bugs@horde.org>\r\n", $firstLine);

            $i++;
        }

        $this->assertSame(2, $i);
    }

    #[DataProvider('emlParseProvider')]
    public function testEmlParse(string $file, string $firstLine): void
    {
        $parser = new MboxParser($file);

        $this->assertCount(1, $parser);

        $msg = $parser->get(0);
        $this->assertInstanceOf(MboxMessage::class, $msg);

        $msg->data->rewind();
        $line = fgets($msg->data->getResource());
        $this->assertSame($firstLine . "\r\n", $line);
    }

    public static function emlParseProvider(): array
    {
        $dir = __DIR__ . '/../../fixtures';
        return [
            [$dir . '/test.eml', 'Return-Path: <bugs@horde.org>'],
            [$dir . '/test2.eml', 'Return-Path: <test@example.com>'],
        ];
    }

    public function testBadData(): void
    {
        $this->expectException(MailException::class);
        new MboxParser(__DIR__ . '/noexist');
    }

    public function testGetByIndex(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.mbox');

        $msg0 = $parser->get(0);
        $this->assertInstanceOf(MboxMessage::class, $msg0);
        $this->assertGreaterThan(0, $msg0->size);

        $msg1 = $parser->get(1);
        $this->assertInstanceOf(MboxMessage::class, $msg1);
        $this->assertGreaterThan(0, $msg1->size);
    }

    public function testGetOutOfRangeThrows(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('index 99 out of range');

        $parser = new MboxParser(self::fixtureDir() . '/test.mbox');
        $parser->get(99);
    }

    public function testMboxDates(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.mbox');
        $msg = $parser->get(0);
        $this->assertNotNull($msg->date);
    }

    public function testEmlHasNullDate(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.eml');
        $msg = $parser->get(0);
        $this->assertNull($msg->date);
    }

    public function testToString(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.mbox');
        $str = (string) $parser;
        $this->assertStringContainsString('From bugs@horde.org', $str);
        $this->assertStringContainsString('Return-Path: <bugs@horde.org>', $str);
    }

    public function testResourceInput(): void
    {
        $resource = fopen(self::fixtureDir() . '/test.mbox', 'r');
        $parser = new MboxParser($resource);
        $this->assertCount(2, $parser);
        fclose($resource);
    }

    public function testLimit(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('more than enforced limit of 1');

        new MboxParser(self::fixtureDir() . '/test.mbox', limit: 1);
    }

    public function testCountable(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.eml');
        $this->assertSame(1, count($parser));
    }

    public function testIteratorAggregate(): void
    {
        $parser = new MboxParser(self::fixtureDir() . '/test.eml');
        $messages = iterator_to_array($parser);
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(MboxMessage::class, $messages[0]);
    }
}
