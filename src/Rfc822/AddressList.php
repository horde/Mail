<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

final class AddressList implements Countable, IteratorAggregate
{
    /** @var array<Address|Group> */
    private array $items = [];

    public function __construct(
        private readonly bool $groupsAllowed = true,
    ) {}

    public static function from(Address|Group ...$items): self
    {
        $list = new self();
        foreach ($items as $item) {
            $list->items[] = $item;
        }
        return $list;
    }

    public static function addressesOnly(Address ...$items): self
    {
        $list = new self(groupsAllowed: false);
        foreach ($items as $item) {
            $list->items[] = $item;
        }
        return $list;
    }

    public function add(Address|Group ...$items): void
    {
        foreach ($items as $item) {
            if (!$this->groupsAllowed && $item instanceof Group) {
                throw new InvalidArgumentException('Groups are not allowed in this list.');
            }
            $this->items[] = $item;
        }
    }

    public function remove(Address|string $address): void
    {
        $target = $address instanceof Address ? $address->bareAddress() : $address;
        $this->items = array_values(array_filter(
            $this->items,
            static function (Address|Group $item) use ($target): bool {
                return !($item instanceof Address && $item->bareAddress() === $target);
            },
        ));
    }

    public function unique(): self
    {
        $seen = [];
        $result = new self($this->groupsAllowed);

        foreach ($this->items as $item) {
            if ($item instanceof Group) {
                $result->items[] = $item;
                continue;
            }

            $key = $item->bareAddress();
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result->items[] = $item;
            } elseif ($item->personal !== null) {
                foreach ($result->items as $i => $existing) {
                    if ($existing instanceof Address
                        && $existing->bareAddress() === $key
                        && $existing->personal === null) {
                        $result->items[$i] = $item;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    public function first(): Address|Group|null
    {
        return $this->items[0] ?? null;
    }

    public function contains(Address|string $address): bool
    {
        $target = $address instanceof Address ? $address->bareAddress() : $address;

        foreach ($this->items as $item) {
            if ($item instanceof Address && $item->bareAddress() === $target) {
                return true;
            }
            if ($item instanceof Group) {
                foreach ($item->addresses as $groupAddr) {
                    if ($groupAddr instanceof Address && $groupAddr->bareAddress() === $target) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function match(self|Address|string $other): bool
    {
        if ($other instanceof self) {
            $ours = $this->bareAddresses();
            $theirs = $other->bareAddresses();
            sort($ours);
            sort($theirs);
            return $ours === $theirs;
        }

        return $this->contains($other);
    }

    /**
     * @return Address[]
     */
    public function addresses(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            if ($item instanceof Address) {
                $result[] = $item;
            } elseif ($item instanceof Group) {
                foreach ($item->addresses as $addr) {
                    if ($addr instanceof Address) {
                        $result[] = $addr;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function bareAddresses(): array
    {
        return array_map(
            static fn(Address $a): string => $a->bareAddress(),
            $this->addresses(),
        );
    }

    /**
     * @return string[]
     */
    public function bareAddressesIdn(): array
    {
        return array_map(
            static fn(Address $a): string => $a->bareAddressIdn(),
            $this->addresses(),
        );
    }

    /**
     * @return Group[]
     */
    public function groups(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn(Address|Group $item): bool => $item instanceof Group,
        ));
    }

    /**
     * @return array<Address|Group>
     */
    public function baseElements(): array
    {
        return $this->items;
    }

    public function writeAddress(?string $encode = null, bool $idn = false): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            if ($item instanceof Address) {
                $parts[] = $item->writeAddress(encode: $encode, idn: $idn);
            } elseif ($item instanceof Group) {
                $parts[] = $item->writeAddress(encode: $encode, idn: $idn);
            }
        }
        return implode(', ', $parts);
    }

    public function __toString(): string
    {
        return $this->writeAddress();
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item instanceof Address) {
                ++$count;
            } elseif ($item instanceof Group) {
                $count += $item->count();
            }
        }
        return $count;
    }

    public function groupCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item instanceof Group) {
                ++$count;
            }
        }
        return $count;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<Address|Group> */
    public function __serialize(): array
    {
        return $this->items;
    }

    /** @param array<Address|Group> $data */
    public function __unserialize(array $data): void
    {
        $this->items = $data;
    }
}
