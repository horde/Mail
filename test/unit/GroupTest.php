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
use Horde_Mail_Rfc822_Group;

#[CoversNothing]
class GroupTest extends TestCase
{
    #[DataProvider('writeAddressProvider')]
    public function testWriteAddress(
        $addresses,
        $groupname,
        $encode,
        $expected
    ) {
        $group_ob = new Horde_Mail_Rfc822_Group($groupname, $addresses);

        $this->assertEquals(
            $expected,
            $group_ob->writeAddress(['encode' => $encode])
        );
    }

    public static function writeAddressProvider()
    {
        return [
            [
                [
                    'Test <test@example.com>',
                    'foo@example.com',
                ],
                'Testing',
                false,
                'Testing: Test <test@example.com>, foo@example.com;',
            ],
            [
                [
                    'Fooã <test@example.com>',
                    'foo@example.com',
                ],
                'Group "Foo"',
                true,
                '"Group \"Foo\"": =?utf-8?b?Rm9vw6M=?= <test@example.com>, foo@example.com;',
            ],
        ];
    }

    public function testValid()
    {
        $group_ob = new Horde_Mail_Rfc822_Group();

        $this->assertTrue($group_ob->valid);

        $group_ob->groupname = '';

        $this->assertFalse($group_ob->valid);
    }

    public function testEmptyGroupCount()
    {
        $group_ob = new Horde_Mail_Rfc822_Group('Group');
        $this->assertEquals(
            0,
            count($group_ob)
        );
    }

    #[DataProvider('encodingGroupnameProvider')]
    public function testEncodingGroupname($in, $expected)
    {
        $group_ob = new Horde_Mail_Rfc822_Group($in);

        $this->assertEquals(
            $expected,
            $group_ob->groupname_encoded
        );
    }

    public static function encodingGroupnameProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Aäb', '=?utf-8?b?QcOkYg==?='],
        ];
    }

    #[DataProvider('matchProvider')]
    public function testMatch($compare, $result)
    {
        $ob = new Horde_Mail_Rfc822_Group(
            'Testing',
            [
                'foo@example.com',
                'bar@example.com',
            ]
        );

        if ($result) {
            $this->assertTrue($ob->match($compare));
        } else {
            $this->assertFalse($ob->match($compare));
        }
    }

    public static function matchProvider()
    {
        return [
            [['foo@example.com'], false],
            [['bar@example.com'], false],
            [['foo@example.com', 'bar@example.com'], true],
            [['bar@example.com', 'foo@example.com'], true],
        ];
    }

}
