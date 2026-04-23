<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\Rfc822Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
class AddressTest extends TestCase
{
    public function testConstruction(): void
    {
        $addr = new Address('user', 'example.com', 'John Doe', ['a comment']);
        $this->assertSame('user', $addr->mailbox);
        $this->assertSame('example.com', $addr->host);
        $this->assertSame('John Doe', $addr->personal);
        $this->assertSame(['a comment'], $addr->comments);
    }

    public function testMinimalConstruction(): void
    {
        $addr = new Address('user');
        $this->assertSame('user', $addr->mailbox);
        $this->assertNull($addr->host);
        $this->assertNull($addr->personal);
        $this->assertSame([], $addr->comments);
    }

    public function testBareAddress(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertSame('user@example.com', $addr->bareAddress());
    }

    public function testBareAddressWithoutHost(): void
    {
        $addr = new Address('user');
        $this->assertSame('user', $addr->bareAddress());
    }

    public function testIsEai(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertFalse($addr->isEai());
    }

    public function testLabel(): void
    {
        $addr = new Address('user', 'example.com', 'John Doe');
        $this->assertSame('John Doe', $addr->label());
    }

    public function testLabelFallsToBareAddress(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertSame('user@example.com', $addr->label());
    }

    public function testIsValid(): void
    {
        $this->assertTrue((new Address('user', 'example.com'))->isValid());
        $this->assertFalse((new Address(''))->isValid());
    }

    public function testWriteAddressPlain(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertSame('user@example.com', $addr->writeAddress());
    }

    public function testWriteAddressWithPersonal(): void
    {
        $addr = new Address('user', 'example.com', 'John Doe');
        $this->assertSame('John Doe <user@example.com>', $addr->writeAddress());
    }

    public function testWriteAddressWithComments(): void
    {
        $addr = new Address('user', 'example.com', null, ['test comment']);
        $this->assertSame(
            'user@example.com (test comment)',
            $addr->writeAddress(includeComment: true),
        );
    }

    public function testToString(): void
    {
        $addr = new Address('user', 'example.com', 'John');
        $this->assertSame('John <user@example.com>', (string) $addr);
    }

    public function testMatch(): void
    {
        $a = new Address('user', 'example.com');
        $b = new Address('user', 'example.com', 'John');
        $this->assertTrue($a->match($b));
    }

    public function testMatchString(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertTrue($addr->match('user@example.com'));
        $this->assertFalse($addr->match('other@example.com'));
    }

    public function testMatchInsensitive(): void
    {
        $a = new Address('User', 'Example.COM');
        $this->assertTrue($a->matchInsensitive('user@example.com'));
    }

    public function testMatchDomain(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertTrue($addr->matchDomain('example.com'));
        $this->assertTrue($addr->matchDomain('Example.COM'));
        $this->assertFalse($addr->matchDomain('other.com'));
    }

    public function testMatchDomainWithoutHost(): void
    {
        $addr = new Address('user');
        $this->assertFalse($addr->matchDomain('example.com'));
    }

    public function testPersonalEncoded(): void
    {
        $addr = new Address('user', 'example.com', 'plain');
        $this->assertSame('plain', $addr->personalEncoded());
    }

    public function testPersonalEncodedNull(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertNull($addr->personalEncoded());
    }

    // ── Non-ASCII personal encoding ─────────────────────────────────

    public function testPersonalEncodedNonAscii(): void
    {
        $addr = new Address('test', 'example.com', 'Fooã');
        $this->assertSame('=?utf-8?b?Rm9vw6M=?=', $addr->personalEncoded());
    }

    public function testPersonalEncodedUmlaut(): void
    {
        $addr = new Address('test', 'example.com', 'Aäb');
        $this->assertSame('=?utf-8?b?QcOkYg==?=', $addr->personalEncoded());
    }

    // ── writeAddress with encode param ──────────────────────────────

    public function testWriteAddressEncodeNonAsciiPersonal(): void
    {
        $addr = new Address('test', 'example.com', 'Fooã');
        $this->assertSame(
            '=?utf-8?b?Rm9vw6M=?= <test@example.com>',
            $addr->writeAddress(encode: 'UTF-8'),
        );
    }

    public function testWriteAddressEncodeEszett(): void
    {
        $addr = new Address('test', 'example.com', 'ß');
        $this->assertSame(
            '=?utf-8?b?w58=?= <test@example.com>',
            $addr->writeAddress(encode: 'UTF-8'),
        );
    }

    public function testWriteAddressEncodeEszettWithSpace(): void
    {
        $addr = new Address('test', 'example.com', 'ß X');
        $this->assertSame(
            '=?utf-8?b?w58=?= X <test@example.com>',
            $addr->writeAddress(encode: 'UTF-8'),
        );
    }

    public function testWriteAddressNoEncodePreservesUtf8(): void
    {
        $addr = new Address('test', 'example.com', 'Fooã');
        $this->assertSame('Fooã <test@example.com>', $addr->writeAddress());
    }

    // ── IDN host ────────────────────────────────────────────────────

    public function testHostIdn(): void
    {
        $addr = new Address('test', 'üexample.com');
        $this->assertSame('xn--example-m2a.com', $addr->hostIdn());
    }

    public function testHostIdnNull(): void
    {
        $addr = new Address('test');
        $this->assertNull($addr->hostIdn());
    }

    public function testBareAddressIdn(): void
    {
        $addr = new Address('test', 'üexample.com');
        $this->assertSame('test@xn--example-m2a.com', $addr->bareAddressIdn());
    }

    public function testWriteAddressWithIdnFlag(): void
    {
        $addr = new Address('test', 'üexample.com', 'Aäb');
        $this->assertSame(
            '=?utf-8?b?QcOkYg==?= <test@xn--example-m2a.com>',
            $addr->writeAddress(encode: 'UTF-8', idn: true),
        );
    }

    // ── EAI (8-bit mailbox) ─────────────────────────────────────────

    public function testIsEaiWithAsciiMailbox(): void
    {
        $addr = new Address('user', 'example.com');
        $this->assertFalse($addr->isEai());
    }

    public function testIsEaiWithUtf8Mailbox(): void
    {
        $addr = new Address('fooççç', 'example.com');
        $this->assertTrue($addr->isEai());
    }

    // ── Comment output (ported from legacy ObjectTest) ──────────────

    #[DataProvider('commentOutputProvider')]
    public function testCommentOutput(string $expected, Address $addr): void
    {
        $this->assertSame($expected, $addr->writeAddress(includeComment: true));
    }

    public static function commentOutputProvider(): array
    {
        return [
            'single comment' => [
                'Foo <foo@example.com> (Test Comment)',
                new Address('foo', 'example.com', 'Foo', ['Test Comment']),
            ],
            'two comments' => [
                'Foo <foo@example.com> (Test Comment) (2nd Comment)',
                new Address('foo', 'example.com', 'Foo', ['Test Comment', '2nd Comment']),
            ],
            'comment without personal' => [
                'foo@example.com (Test Comment)',
                new Address('foo', 'example.com', null, ['Test Comment']),
            ],
        ];
    }

    // ── writeAddress with noQuote ───────────────────────────────────

    public function testWriteAddressNoQuote(): void
    {
        $addr = new Address('user', 'example.com', 'Has, Comma');
        $result = $addr->writeAddress(noQuote: true);
        $this->assertSame('Has, Comma <user@example.com>', $result);
    }

    // ── matchString with case sensitivity ───────────────────────────

    public function testMatchIsCaseSensitiveForMailbox(): void
    {
        $addr = new Address('User', 'example.com');
        $this->assertFalse($addr->match('user@example.com'));
    }

    public function testMatchInsensitiveWithAddress(): void
    {
        $a = new Address('User', 'Example.COM');
        $b = new Address('user', 'example.com');
        $this->assertTrue($a->matchInsensitive($b));
    }
}
