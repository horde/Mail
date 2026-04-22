<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit;

use Horde\Mail\Rfc2047;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rfc2047::class)]
class Rfc2047Test extends TestCase
{
    #[DataProvider('is8bitProvider')]
    public function testIs8bit(string $data, bool $expected): void
    {
        $this->assertSame($expected, Rfc2047::is8bit($data));
    }

    public static function is8bitProvider(): array
    {
        return [
            ['', false],
            ['A', false],
            ['a', false],
            ['1', false],
            ['!', false],
            ["\0", false],
            ["\10", false],
            ["\127", false],
            ["\x80", true],
            ['ä', true],
            ['A©B', true],
            [' ® ', true],
            [base64_decode('UnVubmVyc5IgQWxlcnQh='), true],
        ];
    }

    #[DataProvider('encodeProvider')]
    public function testEncode(string $data, string $charset, string $expected): void
    {
        $this->assertEquals($expected, Rfc2047::encode($data, $charset));
    }

    public static function encodeProvider(): array
    {
        return [
            ['a b', 'utf-8', 'a b'],
            ['a bcäde f', 'utf-8', 'a =?utf-8?b?YmPDpGRl?= f'],
            ['a ää ä b', 'utf-8', 'a =?utf-8?b?w6TDpCDDpA==?= b'],
            ['ä a ä', 'utf-8', '=?utf-8?b?w6Q=?= a =?utf-8?b?w6Q=?='],
            ['ää a ä', 'utf-8', '=?utf-8?b?w6TDpA==?= a =?utf-8?b?w6Q=?='],
            ['=', 'utf-8', '='],
            ['?', 'utf-8', '?'],
            ['a=?', 'utf-8', 'a=?'],
            ['=?', 'utf-8', '=?utf-8?b?PT8=?='],
            ['=?x', 'utf-8', '=?utf-8?b?PT94?='],
            ["a\n=?", 'utf-8', "a\n=?utf-8?b?PT8=?="],
            ["a\t=?", 'utf-8', "a\t=?utf-8?b?PT8=?="],
            ['a =?', 'utf-8', 'a =?utf-8?b?PT8=?='],
            ["foo\001bar", 'utf-8', '=?utf-8?b?Zm9vAWJhcg==?='],
            ["\x01\x02\x03\x04\x05\x06\x07\x08", 'utf-8', '=?utf-8?b?AQIDBAUGBwg=?='],
            ["\x00", 'UTF-16LE', '=?utf-16le?b?AAA=?='],
        ];
    }

    #[DataProvider('decodeProvider')]
    public function testDecode(string $data, string $expected): void
    {
        $this->assertEquals($expected, Rfc2047::decode($data));
    }

    public static function decodeProvider(): array
    {
        return [
            [
                '=?utf-8?Q?_Fran=C3=A7ois_Xavier=2E_XXXXXX_?= <foo@example.com>',
                ' François Xavier. XXXXXX  <foo@example.com>',
            ],
            [
                " \t=?utf-8?q?=c3=a4?=  =?utf-8?q?=c3=a4?=  b  \t\r\n ",
                " \tää  b  \t\r\n ",
            ],
            [
                'a =?utf-8?q?=c3=a4?= b',
                'a ä b',
            ],
            [
                "a =?utf-8?q?=c3=a4?=\t\t\r\n =?utf-8?q?=c3=a4?= b",
                'a ää b',
            ],
            [
                'a =?utf-8?q?=c3=a4?=  x  =?utf-8?q?=c3=a4?= b',
                'a ä  x  ä b',
            ],
            [
                'a =?utf-8?b?w6TDpCDDpA==?= b',
                'a ää ä b',
            ],
            [
                '=?utf-8?b?w6Qgw6Q=?=',
                'ä ä',
            ],
            [
                '=? required=?',
                '=? required=?',
            ],
        ];
    }

    public function testDecodeWindows1252Fallback(): void
    {
        $original = Rfc2047::$decodeWindows1252;

        try {
            Rfc2047::$decodeWindows1252 = true;
            $this->assertTrue(Rfc2047::$decodeWindows1252);

            Rfc2047::$decodeWindows1252 = false;
            $this->assertFalse(Rfc2047::$decodeWindows1252);
        } finally {
            Rfc2047::$decodeWindows1252 = $original;
        }
    }

    public function testDecodeWindows1252DefaultIsTrue(): void
    {
        $this->assertTrue(Rfc2047::$decodeWindows1252);
    }
}
