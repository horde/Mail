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
        return array(
            array(
                'Test <test@example.com>',
                array(
                    array('example.com', true),
                    array('foo.example.com', false)
                )
            ),
            array(
                'Test <test@foo.example.com>',
                array(
                    array('example.com', true),
                    array('foo.example.com', true)
                )
            ),
            array(
                'Test <test@example.co.uk>',
                array(
                    array('example.co.uk', true),
                    array('foo.example.co.uk', false),
                    array('co.uk', true)
                )
            ),
            array(
                'Test <test@foo.example.co.uk>',
                array(
                    array('example.co.uk', true),
                    array('foo.example.co.uk', true),
                    array('co.uk', true)
                )
            )
        );
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
        return array(
            array(
                '"test@example.com" <test@example.com>',
                'test@example.com'
            ),
            array(
                '"TEST@EXAMPLE.COM" <test@example.com>',
                'test@example.com'
            )
        );
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
        return array(
            array('foo@example.com', 'foo@example.com'),
            array('Foo <foo@example.com>', 'Foo')
        );
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
        return array(
            array('Foo <foo@example.com>', 'Foo'),
            array('Aäb <bar@example.com>', '=?utf-8?b?QcOkYg==?=')
        );
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
        return array(
            /* Example from https://github.com/arnt/eai-test-messages */
            array(
                'Jøran Øygårdvær <jøran@example.com>',
                'Jøran Øygårdvær',
                'jøran@example.com'
            )
        );
    }

}
