<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Group;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressList::class)]
class AddressListTest extends TestCase
{
    public function testFrom(): void
    {
        $a = new Address('a', 'example.com');
        $b = new Address('b', 'example.com');
        $list = AddressList::from($a, $b);
        $this->assertCount(2, $list);
    }

    public function testAdd(): void
    {
        $list = new AddressList();
        $list->add(new Address('a', 'example.com'));
        $this->assertCount(1, $list);
    }

    public function testAddGroup(): void
    {
        $list = new AddressList();
        $group = new Group('Team', AddressList::addressesOnly(
            new Address('a', 'example.com'),
        ));
        $list->add($group);
        $this->assertCount(1, $list);
    }

    public function testGroupsNotAllowed(): void
    {
        $list = new AddressList(groupsAllowed: false);
        $this->expectException(InvalidArgumentException::class);
        $list->add(new Group('Team', new AddressList(groupsAllowed: false)));
    }

    public function testRemove(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $list->remove('a@example.com');
        $this->assertCount(1, $list);
        $this->assertSame('b@example.com', $list->first()->bareAddress());
    }

    public function testUnique(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com'),
            new Address('a', 'example.com', 'Alice'),
            new Address('b', 'example.com'),
        );
        $unique = $list->unique();
        $this->assertCount(2, $unique);
        $addresses = $unique->addresses();
        $found = false;
        foreach ($addresses as $addr) {
            if ($addr->bareAddress() === 'a@example.com') {
                $this->assertSame('Alice', $addr->personal);
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testFirst(): void
    {
        $list = AddressList::from(new Address('a', 'example.com'));
        $this->assertSame('a@example.com', $list->first()->bareAddress());
    }

    public function testFirstEmpty(): void
    {
        $list = new AddressList();
        $this->assertNull($list->first());
    }

    public function testContains(): void
    {
        $list = AddressList::from(new Address('a', 'example.com'));
        $this->assertTrue($list->contains('a@example.com'));
        $this->assertFalse($list->contains('b@example.com'));
    }

    public function testContainsInGroup(): void
    {
        $group = new Group('Team', AddressList::addressesOnly(
            new Address('a', 'example.com'),
        ));
        $list = AddressList::from($group);
        $this->assertTrue($list->contains('a@example.com'));
    }

    public function testMatchLists(): void
    {
        $a = AddressList::from(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $b = AddressList::from(
            new Address('b', 'example.com'),
            new Address('a', 'example.com'),
        );
        $this->assertTrue($a->match($b));
    }

    public function testAddresses(): void
    {
        $group = new Group('Team', AddressList::addressesOnly(
            new Address('b', 'example.com'),
        ));
        $list = AddressList::from(
            new Address('a', 'example.com'),
            $group,
        );
        $addresses = $list->addresses();
        $this->assertCount(2, $addresses);
    }

    public function testBareAddresses(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $this->assertSame(['a@example.com', 'b@example.com'], $list->bareAddresses());
    }

    public function testGroups(): void
    {
        $group = new Group('Team', new AddressList(groupsAllowed: false));
        $list = AddressList::from(
            new Address('a', 'example.com'),
            $group,
        );
        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame('Team', $groups[0]->groupname);
    }

    public function testBaseElements(): void
    {
        $group = new Group('Team', new AddressList(groupsAllowed: false));
        $addr = new Address('a', 'example.com');
        $list = AddressList::from($addr, $group);
        $this->assertCount(2, $list->baseElements());
    }

    public function testWriteAddress(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com', 'Alice'),
            new Address('b', 'example.com'),
        );
        $this->assertSame('Alice <a@example.com>, b@example.com', $list->writeAddress());
    }

    public function testCountFlattensGroups(): void
    {
        $group = new Group('Team', AddressList::addressesOnly(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        ));
        $list = AddressList::from(
            new Address('c', 'example.com'),
            $group,
        );
        $this->assertSame(3, $list->count());
    }

    public function testGroupCount(): void
    {
        $group = new Group('Team', new AddressList(groupsAllowed: false));
        $list = AddressList::from(
            new Address('a', 'example.com'),
            $group,
        );
        $this->assertSame(1, $list->groupCount());
    }

    public function testIterator(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com'),
            new Address('b', 'example.com'),
        );
        $items = iterator_to_array($list);
        $this->assertCount(2, $items);
    }

    public function testSerializeRoundTrip(): void
    {
        $list = AddressList::from(
            new Address('a', 'example.com', 'Alice'),
            new Address('b', 'example.com'),
        );

        $serialized = serialize($list);
        $restored = unserialize($serialized);

        $this->assertCount(2, $restored);
        $this->assertSame('a@example.com', $restored->first()->bareAddress());
    }

    public function testToString(): void
    {
        $list = AddressList::from(new Address('a', 'example.com'));
        $this->assertSame('a@example.com', (string) $list);
    }
}
