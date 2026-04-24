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
use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Mail_Rfc822_Address;

#[CoversNothing]
class MatchTest extends TestCase
{
    #[DataProvider('matchProvider')]
    public function testMatch($in, $match, $expected)
    {
        $address = new Horde_Mail_Rfc822_Address($in);

        $this->assertEquals(
            $expected,
            $address->match($match)
        );
    }

    public static function matchProvider()
    {
        $test1 = 'Test <test@example.com>';
        $test2 = 'Test <täst@example.com>';

        return [
            [
                $test1,
                'Foo <test@example.com>',
                true,
            ],
            [
                $test1,
                'Foo <test@EXAMPLE.COM>',
                true,
            ],
            [
                $test1,
                'Foo <Test@example.com>',
                false,
            ],
            [
                $test2,
                'Foo <test@example.com>',
                false,
            ],
            [
                $test2,
                'täst@example.com',
                true,
            ],
        ];
    }

    #[DataProvider('insensitiveMatchProvider')]
    public function testInsensitiveMatch($in, $match, $expected)
    {
        $address = new Horde_Mail_Rfc822_Address($in);

        $this->assertEquals(
            $expected,
            $address->matchInsensitive($match)
        );
    }

    public static function insensitiveMatchProvider()
    {
        $test1 = 'Test <test@example.com>';
        $test2 = 'Test <täst@example.com>';

        return [
            [
                $test1,
                'Foo <test@example.com>',
                true,
            ],
            [
                $test1,
                'Foo <test@EXAMPLE.COM>',
                true,
            ],
            [
                $test1,
                'Foo <Test@example.com>',
                true,
            ],
            [
                $test1,
                'test1@example.com',
                false,
            ],
            [
                $test2,
                'TäST@EXAMPLE.cOm',
                true,
            ],
        ];
    }

}
