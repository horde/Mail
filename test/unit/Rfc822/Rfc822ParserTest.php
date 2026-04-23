<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Group;
use Horde\Mail\Rfc822\ParseException;
use Horde\Mail\Rfc822\Rfc822Parser;
use Horde\Mail\Rfc822\Rfc822ParserConfig;
use Horde\Mail\Rfc822\ValidationMode;
use Horde\Mail\Rfc822\Event\AddressParsed;
use Horde\Mail\Rfc822\Event\GroupParsed;
use Horde\Mail\Rfc822\Event\ParseError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(Rfc822Parser::class)]
class Rfc822ParserTest extends TestCase
{
    private function parser(?Rfc822ParserConfig $config = null): Rfc822Parser
    {
        return new Rfc822Parser($config);
    }

    private function strictParser(): Rfc822Parser
    {
        return new Rfc822Parser(new Rfc822ParserConfig(validation: ValidationMode::Strict));
    }

    // ── Basic parsing ────────────────────────────────────────────────

    public function testSimpleAddress(): void
    {
        $list = $this->parser()->parseAddressList('user@example.com');
        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertInstanceOf(Address::class, $addr);
        $this->assertSame('user', $addr->mailbox);
        $this->assertSame('example.com', $addr->host);
    }

    public function testMultipleAddresses(): void
    {
        $list = $this->parser()->parseAddressList('a@example.com, b@example.com');
        $this->assertCount(2, $list);
        $this->assertSame(['a@example.com', 'b@example.com'], $list->bareAddresses());
    }

    public function testAddressWithPersonal(): void
    {
        $list = $this->parser()->parseAddressList('John Doe <user@example.com>');
        $addr = $list->first();
        $this->assertSame('John Doe', $addr->personal);
        $this->assertSame('user', $addr->mailbox);
        $this->assertSame('example.com', $addr->host);
    }

    public function testQuotedPersonal(): void
    {
        $list = $this->parser()->parseAddressList('"Doe, John" <user@example.com>');
        $addr = $list->first();
        $this->assertSame('Doe, John', $addr->personal);
    }

    public function testAngleBrackets(): void
    {
        $list = $this->parser()->parseAddressList('<user@example.com>');
        $addr = $list->first();
        $this->assertSame('user@example.com', $addr->bareAddress());
    }

    public function testSubdomains(): void
    {
        $list = $this->parser()->parseAddressList('user@sub.domain.example.com');
        $addr = $list->first();
        $this->assertSame('sub.domain.example.com', $addr->host);
    }

    public function testDottedLocalPart(): void
    {
        $list = $this->parser()->parseAddressList('first.last@example.com');
        $addr = $list->first();
        $this->assertSame('first.last', $addr->mailbox);
        $this->assertSame('example.com', $addr->host);
    }

    public function testEmptyInput(): void
    {
        $list = $this->parser()->parseAddressList('');
        $this->assertCount(0, $list);
    }

    public function testTrailingCommaHandled(): void
    {
        $list = $this->parser()->parseAddressList('user@example.com,');
        $this->assertCount(1, $list);
    }

    public function testWhitespaceOnlyInput(): void
    {
        $list = $this->parser()->parseAddressList('   ');
        $this->assertCount(0, $list);
    }

    // ── Quoted strings ───────────────────────────────────────────────

    public function testQuotedLocalPart(): void
    {
        $list = $this->parser()->parseAddressList('<"Jon Parise"@php.net>');
        $addr = $list->first();
        $this->assertSame('Jon Parise', $addr->mailbox);
        $this->assertSame('php.net', $addr->host);
    }

    public function testStrictRejectsUnquotedSpaceInLocalPart(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $this->expectException(ParseException::class);
        $this->parser($config)->parseAddressList('<Jon Parise@php.net>');
    }

    public function testQuotedPersonalWithEscapedQuotes(): void
    {
        $list = $this->parser()->parseAddressList('"Test \\"F-oo\\" Bar" <foo@example.com>');
        $addr = $list->first();
        $this->assertSame('Test "F-oo" Bar', $addr->personal);
    }

    public function testQuotedPersonalWithCommas(): void
    {
        $list = $this->parser()->parseAddressList('"Foo, Bar" <foo@example.com>');
        $this->assertCount(1, $list);
        $this->assertSame('Foo, Bar', $list->first()->personal);
    }

    public function testEmailInDisplayPart(): void
    {
        $list = $this->parser()->parseAddressList(
            'Foo Bar <foobar@example.com>, "bad_email@example.com, Baz" <baz@example.com>, "Qux" <qux@example.com>'
        );
        $this->assertCount(3, $list);
    }

    public function testValidateQuotedStringWithEscapedParens(): void
    {
        $config = new Rfc822ParserConfig(defaultDomain: 'example.com');
        $list = $this->parser($config)->parseAddressList(
            '"Joe Doe \(from Somewhere\)" <doe@example.com>, postmaster@example.com, root'
        );
        $this->assertCount(3, $list);
    }

    #[DataProvider('quotedStringEscapeProvider')]
    public function testQuotedStringEscapeSequences(string $input, bool $shouldFail): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        if ($shouldFail) {
            $this->expectException(ParseException::class);
        }
        $list = $this->parser($config)->parseAddressList($input);
        if (!$shouldFail) {
            $this->assertCount(1, $list);
        }
    }

    public static function quotedStringEscapeProvider(): array
    {
        return [
            ['"John Doe" <test@example.com>', false],
            ['"John Doe' . chr(92) . '" <test@example.com>', true],
            ['"John Doe' . chr(92) . chr(92) . '" <test@example.com>', false],
            ['"John Doe' . chr(92) . chr(92) . chr(92) . '" <test@example.com>', true],
            ['"John Doe' . chr(92) . chr(92) . chr(92) . chr(92) . '" <test@example.com>', false],
            ['"John Doe <test@example.com>', true],
        ];
    }

    // ── Groups ───────────────────────────────────────────────────────

    public function testGroup(): void
    {
        $list = $this->parser()->parseAddressList('Team: a@example.com, b@example.com;');
        $this->assertCount(2, $list);
        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame('Team', $groups[0]->groupname);
    }

    public function testGroupWithPersonalAndComments(): void
    {
        $list = $this->parser()->parseAddressList(
            'My Group: "Richard" <richard@example.com> (A comment), ted@example.com (Ted Bloggs), Barney;'
        );
        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame('My Group', $groups[0]->groupname);

        $addresses = $groups[0]->addresses->addresses();
        $this->assertCount(3, $addresses);

        $this->assertSame('Richard', $addresses[0]->personal);
        $this->assertSame('richard', $addresses[0]->mailbox);
        $this->assertSame(['A comment'], $addresses[0]->comments);

        $this->assertSame('ted', $addresses[1]->mailbox);
        $this->assertSame(['Ted Bloggs'], $addresses[1]->comments);

        $this->assertSame('Barney', $addresses[2]->mailbox);
    }

    public function testEmptyGroup(): void
    {
        $list = $this->parser()->parseAddressList('Team:;');
        $this->assertCount(0, $list);
        $this->assertSame(0, $list->groupCount());
    }

    public function testMixedAddressesAndGroups(): void
    {
        $list = $this->parser()->parseAddressList(
            'solo@example.com, Team: a@example.com, b@example.com;, end@example.com'
        );
        $this->assertCount(4, $list);
        $this->assertSame(1, $list->groupCount());
    }

    public function testGroupWithMissingAddressInLenientMode(): void
    {
        $list = $this->parser()->parseAddressList('Group: foo@example.com, A;');
        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame(2, $groups[0]->count());
    }

    public function testGroupParsingLenient(): void
    {
        $list = $this->parser()->parseAddressList('Group: foo@example.com, foo2@example.com;');
        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame(2, $groups[0]->count());
    }

    // ── Comments ─────────────────────────────────────────────────────

    public function testCommentsPreserved(): void
    {
        $list = $this->parser()->parseAddressList('user@example.com (a comment)');
        $addr = $list->first();
        $this->assertSame(['a comment'], $addr->comments);
    }

    public function testNestedComments(): void
    {
        $list = $this->parser()->parseAddressList('user@example.com (outer (inner))');
        $addr = $list->first();
        $this->assertNotEmpty($addr->comments);
    }

    public function testMultipleComments(): void
    {
        $list = $this->parser()->parseAddressList(
            '"Test Student" <test@mydomain.com> (test)'
        );
        $addr = $list->first();
        $this->assertSame('Test Student', $addr->personal);
        $this->assertSame('test', $addr->mailbox);
        $this->assertSame('mydomain.com', $addr->host);
        $this->assertSame(['test'], $addr->comments);
    }

    // ── Configuration ────────────────────────────────────────────────

    public function testDefaultDomain(): void
    {
        $config = new Rfc822ParserConfig(defaultDomain: 'fallback.com');
        $list = $this->parser($config)->parseAddressList('user');
        $addr = $list->first();
        $this->assertSame('fallback.com', $addr->host);
    }

    public function testDefaultDomainDoesNotOverrideExplicit(): void
    {
        $config = new Rfc822ParserConfig(defaultDomain: 'example.com');
        $list = $this->parser($config)->parseAddressList('foo@example2.com');
        $this->assertSame('example2.com', $list->first()->host);
    }

    public function testStrictValidationThrowsOnMissingDomain(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $this->expectException(ParseException::class);
        $this->parser($config)->parseAddressList('user');
    }

    public function testBareMailboxWithoutDefaultDomainLenient(): void
    {
        $list = $this->parser()->parseAddressList('foo');
        $this->assertSame('foo', $list->first()->mailbox);
        $this->assertNull($list->first()->host);
    }

    public function testBareMailboxWithoutDefaultDomainStrict(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $this->expectException(ParseException::class);
        $this->parser($config)->parseAddressList('foo');
    }

    public function testLenientSkipsInvalid(): void
    {
        $list = $this->parser()->parseAddressList('user@example.com, !!!invalid, b@example.com');
        $bare = $list->bareAddresses();
        $this->assertContains('user@example.com', $bare);
        $this->assertContains('b@example.com', $bare);
    }

    public function testLimit(): void
    {
        $config = new Rfc822ParserConfig(limit: 1);
        $list = $this->parser($config)->parseAddressList('a@example.com, b@example.com');
        $this->assertCount(1, $list);
        $this->assertSame('a@example.com', $list->first()->bareAddress());
    }

    public function testLimitLargeSet(): void
    {
        $email = implode(', ', array_fill(0, 10, 'A <foo@example.com>'));
        $config = new Rfc822ParserConfig(limit: 5);
        $list = $this->parser($config)->parseAddressList($email);
        $this->assertCount(5, $list);
    }

    public function testPerCallConfigOverride(): void
    {
        $parser = $this->parser();
        $override = new Rfc822ParserConfig(defaultDomain: 'override.com');
        $list = $parser->parseAddressList('user', $override);
        $this->assertSame('override.com', $list->first()->host);
    }

    // ── RFC 2047 decoding through parser ─────────────────────────────

    public function testRfc2047PersonalDecoded(): void
    {
        $list = $this->parser()->parseAddressList(
            '=?utf-8?Q?Fran=C3=A7ois?= <user@example.com>'
        );
        $addr = $list->first();
        $this->assertSame('François', $addr->personal);
    }

    public function testRfc2047QuotedWordDecoded(): void
    {
        $list = $this->parser()->parseAddressList(
            '=?utf-8?b?QcOkYg==?= <test@example.com>'
        );
        $this->assertSame('Aäb', $list->first()->personal);
    }

    // ── Unicode / EAI ────────────────────────────────────────────────

    public function testUnicodePersonalInLenientMode(): void
    {
        $list = $this->parser()->parseAddressList('"ß" <test@example.com>');
        $this->assertSame('ß', $list->first()->personal);
    }

    public function testUnicodePersonalUnquotedLenient(): void
    {
        $list = $this->parser()->parseAddressList('ß ß <test@example.com>');
        $this->assertSame('ß ß', $list->first()->personal);
    }

    public function testUnicodePersonalInStrictModeThrows(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $this->expectException(ParseException::class);
        $this->parser($config)->parseAddressList('ß <test@example.com>');
    }

    #[DataProvider('eaiAddressProvider')]
    public function testEaiAddresses(string $input, bool $validEai): void
    {
        $lenientList = $this->parser()->parseAddressList($input);
        $this->assertCount(1, $lenientList);

        $strictConfig = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        try {
            $this->parser($strictConfig)->parseAddressList($input);
            $this->fail('Strict mode should reject non-ASCII');
        } catch (ParseException $e) {
        }

        $eaiConfig = new Rfc822ParserConfig(validation: ValidationMode::Eai);
        if ($validEai) {
            $eaiList = $this->parser($eaiConfig)->parseAddressList($input);
            $this->assertCount(1, $eaiList);
        } else {
            $this->expectException(ParseException::class);
            $this->parser($eaiConfig)->parseAddressList($input);
        }
    }

    public static function eaiAddressProvider(): array
    {
        return [
            ['fooççç@example.com', true],
            ['Jøran Øygårdvær <jøran@example.com>', true],
            ['foo@üexample.com', true],
        ];
    }

    // ── IDN hosts ────────────────────────────────────────────────────

    public function testParsingIDNHostLenient(): void
    {
        $list = $this->parser()->parseAddressList('Aäb <test@üexample.com>');
        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertNotNull($addr->host);
    }

    public function testParsingIDNHostStrict(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $this->expectException(ParseException::class);
        $this->parser($config)->parseAddressList('Aäb <test@üexample.com>');
    }

    // ── Domain literal ───────────────────────────────────────────────

    public function testDomainLiteral(): void
    {
        $list = $this->parser()->parseAddressList('user@[192.168.1.1]');
        $addr = $list->first();
        $this->assertSame('user', $addr->mailbox);
        $this->assertSame('192.168.1.1', $addr->host);
    }

    public function testIPv6DomainLiteral(): void
    {
        $list = $this->strictParser()->parseAddressList('user@[IPv6:::1]');
        $addr = $list->first();
        $this->assertSame('user', $addr->mailbox);
        $this->assertNotNull($addr->host);
    }

    public function testIPv6DomainLiteralLenientSilentlyDropped(): void
    {
        $list = $this->parser()->parseAddressList('user@[IPv6:::1]');
        $this->assertSame(0, $list->count());
    }

    // ── Obsolete route syntax ────────────────────────────────────────

    public function testObsoleteRouteIgnored(): void
    {
        $list = $this->strictParser()->parseAddressList('<@route1:user@example.com>');
        $addr = $list->first();
        $this->assertSame('user', $addr->mailbox);
        $this->assertSame('example.com', $addr->host);
    }

    public function testObsoleteRouteLenientSilentlyDropped(): void
    {
        $list = $this->parser()->parseAddressList('<@route1:user@example.com>');
        $this->assertSame(0, $list->count());
    }

    // ── Simple string (bare mailbox) ─────────────────────────────────

    public function testParsingSimpleString(): void
    {
        $list = $this->parser()->parseAddressList('Test');
        $this->assertCount(1, $list);
        $this->assertSame('Test', $list->first()->mailbox);
    }

    // ── Bug regression: missing mailbox ──────────────────────────────

    public function testMissingMailboxInLenientMode(): void
    {
        $list = $this->parser()->parseAddressList('A <example.com>');
        $this->assertCount(0, $list);
    }

    // ── Bare address at front, name-addr after ───────────────────────

    public function testBareAddressFollowedByNameAddr(): void
    {
        $list = $this->parser()->parseAddressList('test@example.com, Foo <test2@example.com>');
        $this->assertCount(2, $list);
        $this->assertSame('example.com', $list->first()->host);
    }

    // ── Uncommon TLD ─────────────────────────────────────────────────

    public function testUncommonTldAccepted(): void
    {
        $config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
        $list = $this->parser($config)->parseAddressList('jon@host.longtld');
        $this->assertCount(1, $list);
    }

    // ── Validation with Unicode in personal ──────────────────────────

    public function testUnicodeInQuotedPersonalLenient(): void
    {
        $list = $this->parser()->parseAddressList('"Tek-Diária - Newsletter" <foo@example.com>');
        $this->assertCount(1, $list);
    }

    // ── Large parse ──────────────────────────────────────────────────

    public function testLargeParsePerformance(): void
    {
        $email = implode(', ', array_fill(
            0,
            100,
            'A <foo@example.com>, "A B" <foo@example.com>, foo@example.com, Group: A <foo@example.com>;, Group2: "A B" <foo@example.com>;'
        ));
        $list = $this->parser()->parseAddressList($email);
        $this->assertSame(500, $list->count());
    }

    // ── Static utility methods ───────────────────────────────────────

    #[DataProvider('quoteAddressProvider')]
    public function testQuoteAddress(string $input, string $expected): void
    {
        $this->assertSame($expected, Rfc822Parser::quoteAddress($input));
    }

    public static function quoteAddressProvider(): array
    {
        return [
            ['simple', 'simple'],
            ['has space', '"has space"'],
            ['"already quoted"', '"already quoted"'],
        ];
    }

    #[DataProvider('quotePersonalProvider')]
    public function testQuotePersonal(string $input, string $expected): void
    {
        $this->assertSame($expected, Rfc822Parser::quotePersonal($input));
    }

    public static function quotePersonalProvider(): array
    {
        return [
            ['John Doe', 'John Doe'],
            ['simple', 'simple'],
            ['has.dot', '"has.dot"'],
        ];
    }

    public function testTrimAddress(): void
    {
        $this->assertSame('user@example.com', Rfc822Parser::trimAddress('<user@example.com>'));
        $this->assertSame('user@example.com', Rfc822Parser::trimAddress('user@example.com'));
        $this->assertSame('user@example.com', Rfc822Parser::trimAddress('  user@example.com  '));
    }

    public function testApproximateCount(): void
    {
        $this->assertSame(1, Rfc822Parser::approximateCount('a@b.com'));
        $this->assertSame(3, Rfc822Parser::approximateCount('a@b.com,c@d.com,e@f.com'));
    }

    #[DataProvider('isValidInetAddressProvider')]
    public function testIsValidInetAddress(string $data, bool $strict, array|false $expected): void
    {
        $result = Rfc822Parser::isValidInetAddress($data, $strict);
        if ($expected === false) {
            $this->assertFalse($result);
        } else {
            $this->assertSame($expected, $result);
        }
    }

    public static function isValidInetAddressProvider(): array
    {
        return [
            ['user@example.com', false, ['user', 'example.com']],
            ['user@example.com', true, ['user', 'example.com']],
            ['invalid', false, false],
            ['user+tag@example.com', false, ['user+tag', 'example.com']],
            ['user@sub.domain.com', false, ['user', 'sub.domain.com']],
        ];
    }

    // ── PSR-14 events ────────────────────────────────────────────────

    public function testAddressParsedEventDispatched(): void
    {
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $config = new Rfc822ParserConfig(eventDispatcher: $dispatcher);
        $parser = new Rfc822Parser($config);
        $parser->parseAddressList('user@example.com');

        $addressEvents = array_filter($events, fn($e) => $e instanceof AddressParsed);
        $this->assertCount(1, $addressEvents);

        $evt = array_values($addressEvents)[0];
        $this->assertSame('user', $evt->getContext()['mailbox']);
        $this->assertSame('example.com', $evt->getContext()['host']);
    }

    public function testGroupParsedEventDispatched(): void
    {
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $config = new Rfc822ParserConfig(eventDispatcher: $dispatcher);
        $parser = new Rfc822Parser($config);
        $parser->parseAddressList('Team: a@example.com;');

        $groupEvents = array_filter($events, fn($e) => $e instanceof GroupParsed);
        $this->assertCount(1, $groupEvents);

        $evt = array_values($groupEvents)[0];
        $this->assertSame('Team', $evt->getContext()['groupname']);
    }

    public function testMultipleEventsForMultipleAddresses(): void
    {
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $config = new Rfc822ParserConfig(eventDispatcher: $dispatcher);
        $parser = new Rfc822Parser($config);
        $parser->parseAddressList('a@example.com, b@example.com, c@example.com');

        $addressEvents = array_filter($events, fn($e) => $e instanceof AddressParsed);
        $this->assertCount(3, $addressEvents);
    }

    public function testParseErrorEventOnInvalidInLenient(): void
    {
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$events) {
            $events[] = $event;
            return $event;
        });

        $config = new Rfc822ParserConfig(
            validation: ValidationMode::Lenient,
            eventDispatcher: $dispatcher,
        );
        $parser = new Rfc822Parser($config);
        $parser->parseAddressList('"unclosed');

        $errorEvents = array_filter($events, fn($e) => $e instanceof ParseError);
        $this->assertGreaterThanOrEqual(1, count($errorEvents));
    }

    public function testNoEventsWithoutDispatcher(): void
    {
        $parser = $this->parser();
        $list = $parser->parseAddressList('user@example.com');
        $this->assertCount(1, $list);
    }
}
