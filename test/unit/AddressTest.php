<?php

/**
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mail
 * @subpackage UnitTests
 */

namespace Horde\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Mail_Rfc822_Address;

/**
 * @coversNothing
 */
class AddressTest extends TestCase
{
    #[DataProvider('domainMatchProvider')]
    public function testDomainMatch($addr, $tests)
    {
        $address = new Horde_Mail_Rfc822_Address($addr);

        foreach ($tests as $val) {
            $match = $address->matchDomain($val[0]);
            if ($val[1]) {
                $this->assertTrue($match);
            } else {
                $this->assertFalse($match);
            }
        }
    }

    public static function domainMatchProvider()
    {
        return [
            [
                'Test <test@example.com>',
                [
                    ['example.com', true],
                    ['foo.example.com', false],
                ],
            ],
            [
                'Test <test@foo.example.com>',
                [
                    ['example.com', true],
                    ['foo.example.com', true],
                ],
            ],
            [
                'Test <test@example.co.uk>',
                [
                    ['example.co.uk', true],
                    ['foo.example.co.uk', false],
                    ['co.uk', true],
                ],
            ],
            [
                'Test <test@foo.example.co.uk>',
                [
                    ['example.co.uk', true],
                    ['foo.example.co.uk', true],
                    ['co.uk', true],
                ],
            ],
        ];
    }

    #[DataProvider('personalIsSameAsEmailProvider')]
    public function testPersonalIsSameAsEmail($addr, $expected)
    {
        $address = new Horde_Mail_Rfc822_Address($addr);

        $this->assertEquals(
            $expected,
            strval($address)
        );
    }

    public static function personalIsSameAsEmailProvider()
    {
        return [
            [
                '"test@example.com" <test@example.com>',
                'test@example.com',
            ],
            [
                '"TEST@EXAMPLE.COM" <test@example.com>',
                'test@example.com',
            ],
        ];
    }

    #[DataProvider('labelProvider')]
    public function testLabel($in, $expected)
    {
        $address = new Horde_Mail_Rfc822_Address($in);

        $this->assertEquals(
            $expected,
            $address->label
        );
    }

    public static function labelProvider()
    {
        return [
            ['foo@example.com', 'foo@example.com'],
            ['Foo <foo@example.com>', 'Foo'],
        ];
    }

    #[DataProvider('personalEncodedProvider')]
    public function testPersonalEncoded($in, $expected)
    {
        $address = new Horde_Mail_Rfc822_Address($in);

        $this->assertEquals(
            $expected,
            $address->personal_encoded
        );

        $this->assertFalse($address->eai);
    }

    public static function personalEncodedProvider()
    {
        return [
            ['Foo <foo@example.com>', 'Foo'],
            ['Aäb <bar@example.com>', '=?utf-8?b?QcOkYg==?='],
        ];
    }

    #[DataProvider('eaiAddressesProvider')]
    public function testEaiAddresses($in, $personal, $email)
    {
        $address = new Horde_Mail_Rfc822_Address($in);

        $this->assertEquals(
            $personal,
            $address->personal
        );
        $this->assertEquals(
            $email,
            $address->bare_address
        );
        $this->assertTrue($address->eai);
    }

    public static function eaiAddressesProvider()
    {
        return [
            /* Example from https://github.com/arnt/eai-test-messages */
            [
                'Jøran Øygårdvær <jøran@example.com>',
                'Jøran Øygårdvær',
                'jøran@example.com',
            ],
        ];
    }

}
