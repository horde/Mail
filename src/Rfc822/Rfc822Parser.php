<?php

/**
 * Copyright (c) 2001-2010, Richard Heyes
 * Copyright (c) 2002-2011, Timo Sirainen
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Richard Heyes <richard@phpguru.org>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Timo Sirainen <tss@iki.fi>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

use Horde\EventDispatcher\NullEventDispatcher;
use Horde\Mail\Rfc2047;
use Horde\Mail\Rfc822\Event\AddressParsed;
use Horde\Mail\Rfc822\Event\GroupParsed;
use Horde\Mail\Rfc822\Event\ParseError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Exception;
use Horde_Idna;

final class Rfc822Parser
{
    use Rfc822CharacterRules;

    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly Rfc822ParserConfig $config;

    private array $activeComments = [];
    private ValidationMode $activeValidation;
    private AddressList $listob;
    private int $activeLimit;

    private const ENCODE_FILTER = "\0\1\2\3\4\5\6\7\10\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\"(),:;<>@[\\]\177";

    public function __construct(?Rfc822ParserConfig $config = null)
    {
        $this->config = $config ?? new Rfc822ParserConfig();
        $this->eventDispatcher = $this->config->eventDispatcher ?? new NullEventDispatcher();
    }

    public function parseAddressList(
        string $input,
        ?Rfc822ParserConfig $config = null,
    ): AddressList {
        $cfg = $config ?? $this->config;

        $this->activeValidation = $cfg->validation;
        $this->activeLimit = $cfg->limit > 0 ? $cfg->limit : -1;
        $this->listob = new AddressList();

        $input = rtrim(trim($input), ',');
        if ($input === '') {
            $result = $this->listob;
            unset($this->listob);
            return $result;
        }

        $this->data = $input;
        $this->dataLen = strlen($input);
        $this->ptr = 0;

        $this->parseAddressListInternal($cfg);

        $result = $this->listob;
        unset($this->listob);
        return $result;
    }

    public static function quoteAddress(string $str): string
    {
        $filter = self::ENCODE_FILTER . "\11\40";
        return self::quoteString($str, $filter);
    }

    public static function quotePersonal(string $str): string
    {
        $filter = self::ENCODE_FILTER . '.';
        return self::quoteString($str, $filter);
    }

    public static function quoteComment(string $str): string
    {
        $filter = "\0\1\2\3\4\5\6\7\10\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\50\51\134\177";
        return self::quoteString($str, $filter);
    }

    public static function trimAddress(string $address): string
    {
        $address = trim($address);
        return (isset($address[0]) && $address[0] === '<' && str_ends_with($address, '>'))
            ? substr($address, 1, -1)
            : $address;
    }

    public static function approximateCount(string $data): int
    {
        return count(preg_split('/(?<!\\\\),/', $data));
    }

    /**
     * @return array{0: string, 1: string}|false
     */
    public static function isValidInetAddress(string $data, bool $strict = false): array|false
    {
        $regex = $strict
            ? '/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i'
            : '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i';

        return preg_match($regex, trim($data), $matches)
            ? [$matches[1], $matches[2]]
            : false;
    }

    private function validationMode(): ValidationMode
    {
        return $this->activeValidation;
    }

    private static function quoteString(string $str, string $filter): string
    {
        $str = trim($str);
        if ($str !== '' && $str[0] === '"' && str_ends_with($str, '"')) {
            $str = stripslashes(substr($str, 1, -1));
        }

        return (strcspn($str, $filter) !== strlen($str))
            ? '"' . addcslashes($str, '\\"') . '"'
            : $str;
    }

    private function parseAddressListInternal(Rfc822ParserConfig $cfg): void
    {
        while (($this->curr() !== false) && ($this->activeLimit-- !== 0)) {
            try {
                $this->parseAddress($cfg);
            } catch (ParseException $e) {
                if ($this->activeValidation !== ValidationMode::Lenient) {
                    throw $e;
                }
                $this->eventDispatcher->dispatch(new ParseError(
                    $e->getMessage(),
                    ['position' => $this->ptr, 'input' => $this->data, 'error' => $e->getMessage()],
                ));
                ++$this->ptr;
            }

            switch ($this->curr()) {
                case ',':
                    $this->skipLwsp(true);
                    break;

                case false:
                    break;

                default:
                    if ($this->activeValidation !== ValidationMode::Lenient) {
                        throw new ParseException('Error when parsing address list.');
                    }
                    break;
            }
        }
    }

    private function parseAddress(Rfc822ParserConfig $cfg): void
    {
        $start = $this->ptr;
        try {
            if ($this->parseGroup($cfg)) {
                return;
            }
        } catch (ParseException $e) {
            /* In lenient mode, if an unterminated "group" is actually a
             * name-addr with an unencoded ':' in the display-name (e.g.
             * "ACME : The professional network <addr>"), recover by
             * rewinding and falling back to parseMailbox below. The
             * fallback only triggers if a '<' is present in the current
             * segment (before the next ',' or ';'). */
            if ($this->activeValidation !== ValidationMode::Lenient
                || !$this->hasAngleAddrBeforeSeparator($start)) {
                throw $e;
            }
        }
        $this->ptr = $start;
        $mbox = $this->parseMailbox($cfg);
        if ($mbox !== null) {
            $this->listob->add($mbox);
        }
    }

    private function hasAngleAddrBeforeSeparator(int $from): bool
    {
        $segment = substr($this->data, $from);
        $stop = strcspn($segment, '<,;');
        return $stop < strlen($segment) && $segment[$stop] === '<';
    }

    private function parseGroup(Rfc822ParserConfig $cfg): bool
    {
        $groupname = '';
        $this->parsePhrase($groupname);

        if ($this->curr(true) !== ':') {
            return false;
        }

        $addresses = AddressList::addressesOnly();

        $this->skipLwsp();

        while (($chr = $this->curr()) !== false) {
            if ($chr === ';') {
                ++$this->ptr;

                if ($addresses->count() > 0) {
                    $group = new Group($groupname, $addresses);
                    $this->listob->add($group);

                    $this->eventDispatcher->dispatch(new GroupParsed(
                        'Group parsed: ' . $groupname,
                        ['groupname' => $groupname, 'count' => $group->count()],
                    ));
                }

                return true;
            }

            $mbox = $this->parseMailbox($cfg);
            if ($mbox !== null) {
                $addresses->add($mbox);
            }

            switch ($this->curr()) {
                case ',':
                    $this->skipLwsp(true);
                    break;

                case ';':
                    break;

                default:
                    break 2;
            }
        }

        throw new ParseException('Error when parsing group.');
    }

    private function parseMailbox(Rfc822ParserConfig $cfg): ?Address
    {
        $this->activeComments = [];
        $start = $this->ptr;

        $ob = $this->parseNameAddr($cfg);
        if ($ob === null) {
            $this->activeComments = [];
            $this->ptr = $start;
            $ob = $this->parseAddrSpec($cfg);
        }

        if ($ob !== null && $this->activeComments !== []) {
            $ob = new Address(
                mailbox: $ob->mailbox,
                host: $ob->host,
                personal: $ob->personal,
                comments: $this->activeComments,
            );
        }

        if ($ob !== null) {
            $this->eventDispatcher->dispatch(new AddressParsed(
                'Address parsed: ' . $ob->bareAddress(),
                [
                    'mailbox' => $ob->mailbox,
                    'host' => $ob->host,
                    'personal' => $ob->personal,
                ],
            ));
        }

        return $ob;
    }

    private function parseNameAddr(Rfc822ParserConfig $cfg): ?Address
    {
        $personal = '';
        $this->parsePhrase($personal);

        $ob = $this->parseAngleAddr($cfg);

        /* In lenient mode, tolerate disallowed characters (e.g. ':') in the
         * display-name by consuming everything up to the next angle-addr
         * within the current segment (before the next ',' or ';'). */
        if ($ob === null
            && $this->activeValidation === ValidationMode::Lenient
            && $this->curr() !== false) {
            $segment = substr($this->data, $this->ptr);
            $stop = strcspn($segment, '<,;');
            if ($stop < strlen($segment) && $segment[$stop] === '<') {
                $extra = rtrim(substr($segment, 0, $stop));
                $this->ptr += $stop;
                $ob = $this->parseAngleAddr($cfg);
                if ($ob !== null) {
                    $personal = rtrim($personal . ' ' . $extra);
                }
            }
        }

        if ($ob === null) {
            return null;
        }

        if ($personal !== '') {
            $decoded = $this->decodePersonal($personal);
            $ob = new Address(
                mailbox: $ob->mailbox,
                host: $ob->host,
                personal: $decoded,
                comments: $ob->comments,
            );
        }

        return $ob;
    }

    private function parseAddrSpec(Rfc822ParserConfig $cfg): Address
    {
        $localPart = $this->parseLocalPart();

        $host = null;
        if ($this->curr() === '@') {
            try {
                $domain = '';
                $this->traitParseDomain($domain);
                if ($domain !== '') {
                    $host = $this->normalizeHost($domain);
                }
            } catch (ParseException $e) {
                if ($this->activeValidation !== ValidationMode::Lenient) {
                    throw $e;
                }
            }
        }

        if ($host === null) {
            if ($cfg->defaultDomain !== null) {
                $host = $cfg->defaultDomain;
            } elseif ($this->activeValidation !== ValidationMode::Lenient) {
                throw new ParseException('Address is missing domain.');
            }
        }

        return new Address(mailbox: $localPart, host: $host);
    }

    private function parseLocalPart(): string
    {
        $curr = $this->curr();
        if ($curr === false) {
            throw new ParseException('Error when parsing local part.');
        }

        $str = '';
        if ($curr === '"') {
            $this->traitParseQuotedString($str);
        } else {
            $this->traitParseDotAtom($str, ',;@');
        }

        return $str;
    }

    private function parseAngleAddr(Rfc822ParserConfig $cfg): ?Address
    {
        if ($this->curr() !== '<') {
            return null;
        }

        $this->skipLwsp(true);

        if ($this->curr() === '@') {
            $this->parseDomainListRoute();
            if ($this->curr() !== ':') {
                throw new ParseException('Invalid route.');
            }
            $this->skipLwsp(true);
        }

        $ob = $this->parseAddrSpec($cfg);

        if ($this->curr() !== '>') {
            throw new ParseException('Error when parsing angle address.');
        }

        $this->skipLwsp(true);

        return $ob;
    }

    private function parseDomainListRoute(): array
    {
        $route = [];

        while ($this->curr() !== false) {
            $str = '';
            $this->traitParseDomain($str);
            $route[] = '@' . $str;

            $this->skipLwsp();
            if ($this->curr() !== ',') {
                return $route;
            }
            ++$this->ptr;
        }

        throw new ParseException('Invalid domain list.');
    }

    private function parsePhrase(string &$phrase): void
    {
        $curr = $this->curr();
        if ($curr === false || $curr === '.') {
            return;
        }

        do {
            if ($curr === '"') {
                $this->traitParseQuotedString($phrase);
            } else {
                $this->traitParseAtomOrDot($phrase);
            }

            $curr = $this->curr();
            if ($curr === false
                || ($curr !== '"'
                    && $curr !== '.'
                    && !$this->isAtext($curr))) {
                break;
            }

            $phrase .= ' ';
        } while ($this->ptr < $this->dataLen);

        $this->skipLwsp();
    }

    private function decodePersonal(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        try {
            return Rfc2047::decode($value);
        } catch (Throwable $e) {
            $this->eventDispatcher->dispatch(new ParseError(
                'Failed to decode personal part: ' . $e->getMessage(),
                ['position' => $this->ptr, 'input' => $value, 'error' => $e->getMessage()],
            ));
            return $value;
        }
    }

    private function normalizeHost(string $host): string
    {
        try {
            return Horde_Idna::decode($host);
        } catch (Exception $e) {
            return $host;
        }
    }
}
