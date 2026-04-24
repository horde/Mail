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

use Horde\Mail\Rfc2047;
use Horde\Util\HordeString;
use Horde_Idna;
use Exception;

final readonly class Address
{
    public function __construct(
        public string $mailbox,
        public ?string $host = null,
        public ?string $personal = null,
        public array $comments = [],
    ) {}

    public function bareAddress(): string
    {
        return $this->host !== null
            ? $this->mailbox . '@' . $this->host
            : $this->mailbox;
    }

    public function bareAddressIdn(): string
    {
        $idnHost = $this->hostIdn();
        return $idnHost !== null
            ? $this->mailbox . '@' . $idnHost
            : $this->mailbox;
    }

    public function isEai(): bool
    {
        return Rfc2047::is8bit($this->mailbox);
    }

    public function personalEncoded(string $charset = 'UTF-8'): ?string
    {
        if ($this->personal === null) {
            return null;
        }
        return Rfc2047::encode($this->personal, $charset);
    }

    public function hostIdn(): ?string
    {
        if ($this->host === null) {
            return null;
        }
        try {
            return Horde_Idna::encode($this->host);
        } catch (Exception $e) {
            return $this->host;
        }
    }

    public function label(): string
    {
        return $this->personal ?? $this->bareAddress();
    }

    public function isValid(): bool
    {
        return $this->mailbox !== '';
    }

    public function writeAddress(
        ?string $encode = null,
        bool $idn = false,
        bool $includeComment = false,
        bool $noQuote = false,
    ): string {
        $host = $idn ? $this->hostIdn() : $this->host;
        $address = $host !== null
            ? $this->mailbox . '@' . $host
            : $this->mailbox;

        $personal = null;
        if ($this->personal !== null && $this->personal !== '') {
            if ($encode !== null) {
                $personal = Rfc2047::encode($this->personal, $encode);
            } else {
                $personal = $this->personal;
            }

            if (!$noQuote) {
                $personal = Rfc822Parser::quotePersonal($personal);
            }
        }

        $result = '';
        if ($personal !== null) {
            $result = $personal . ' <' . $address . '>';
        } else {
            $result = $address;
        }

        if ($includeComment && $this->comments !== []) {
            foreach ($this->comments as $comment) {
                $result .= ' (' . $comment . ')';
            }
        }

        return $result;
    }

    public function match(self|string $other): bool
    {
        $otherAddress = $other instanceof self ? $other->bareAddress() : $other;
        return $this->bareAddress() === $otherAddress;
    }

    public function matchInsensitive(self|string $other): bool
    {
        $otherAddress = $other instanceof self ? $other->bareAddress() : $other;
        return HordeString::lower($this->bareAddress()) === HordeString::lower($otherAddress);
    }

    public function matchDomain(string $domain): bool
    {
        return $this->host !== null
            && strcasecmp($this->host, $domain) === 0;
    }

    public function __toString(): string
    {
        return $this->writeAddress();
    }
}
