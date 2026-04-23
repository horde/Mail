<?php

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

use Horde\Mail\Rfc2047;

final readonly class Group
{
    public function __construct(
        public string $groupname,
        public AddressList $addresses,
    ) {}

    public function groupnameEncoded(string $charset = 'UTF-8'): string
    {
        return Rfc2047::encode($this->groupname, $charset);
    }

    public function label(): string
    {
        return $this->groupname;
    }

    public function isValid(): bool
    {
        return $this->groupname !== '';
    }

    public function count(): int
    {
        return $this->addresses->count();
    }

    public function writeAddress(
        ?string $encode = null,
        bool $idn = false,
    ): string {
        $groupname = $encode !== null
            ? Rfc2047::encode($this->groupname, $encode)
            : $this->groupname;

        $addrString = $this->addresses->writeAddress($encode, $idn);

        return $groupname . ':' . ($addrString !== '' ? ' ' . $addrString : '') . ';';
    }

    public function match(Address|string $other): bool
    {
        return $this->addresses->match($other);
    }

    public function __toString(): string
    {
        return $this->writeAddress();
    }
}
