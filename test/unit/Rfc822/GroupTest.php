<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Group::class)]
class GroupTest extends TestCase
{
    public function testConstruction(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
        );
        $group = new Group('Team', $list);
        $this->assertSame('Team', $group->groupname);
        $this->assertSame($list, $group->addresses);
    }

    public function testLabel(): void
    {
        $group = new Group('Team', new AddressList(groupsAllowed: false));
        $this->assertSame('Team', $group->label());
    }

    public function testIsValid(): void
    {
        $this->assertTrue((new Group('Team', new AddressList(groupsAllowed: false)))->isValid());
        $this->assertFalse((new Group('', new AddressList(groupsAllowed: false)))->isValid());
    }

    public function testCount(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $group = new Group('Team', $list);
        $this->assertSame(2, $group->count());
    }

    public function testWriteAddress(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $group = new Group('Team', $list);
        $this->assertSame('Team: a@example.com, b@example.com;', $group->writeAddress());
    }

    public function testWriteAddressEmpty(): void
    {
        $group = new Group('Team', new AddressList(groupsAllowed: false));
        $this->assertSame('Team:;', $group->writeAddress());
    }

    public function testToString(): void
    {
        $list = AddressList::addressesOnly(new Address('a', 'example.com'));
        $group = new Group('Team', $list);
        $this->assertSame('Team: a@example.com;', (string) $group);
    }

    public function testGroupnameEncoded(): void
    {
        $group = new Group('plain', new AddressList(groupsAllowed: false));
        $this->assertSame('plain', $group->groupnameEncoded());
    }

    public function testMatch(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
        );
        $group = new Group('Team', $list);
        $this->assertTrue($group->match(new Address('a', 'example.com')));
        $this->assertFalse($group->match(new Address('b', 'example.com')));
    }

    public function testMatchString(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
        );
        $group = new Group('Team', $list);
        $this->assertTrue($group->match('a@example.com'));
        $this->assertFalse($group->match('b@example.com'));
    }

    // ── Non-ASCII groupname encoding ────────────────────────────────

    public function testGroupnameEncodedNonAscii(): void
    {
        $group = new Group('Tëam', new AddressList(groupsAllowed: false));
        $this->assertSame('=?utf-8?b?VMOrYW0=?=', $group->groupnameEncoded());
    }

    public function testWriteAddressEncodedGroupname(): void
    {
        $list = AddressList::addressesOnly(
            new Address('a', 'example.com'),
        );
        $group = new Group('Tëam', $list);
        $this->assertSame(
            '=?utf-8?b?VMOrYW0=?=: a@example.com;',
            $group->writeAddress(encode: 'UTF-8'),
        );
    }

    // ── Bug #4834: encoding email lists with groups ─────────────────

    public function testMixedListWithGroupWriteAddress(): void
    {
        $groupList = AddressList::addressesOnly(
            new Address('peter', 'example.com'),
            new Address('jane', 'example.com'),
        );
        $list = new AddressList();
        $list->add(new Address('john', 'example.com', 'John Doe'));
        $list->add(new Group('Group', $groupList));
        $this->assertSame(
            'John Doe <john@example.com>, Group: peter@example.com, jane@example.com;',
            $list->writeAddress(),
        );
    }
}
