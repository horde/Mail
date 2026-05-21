<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\Group;
use Horde\Mail\Rfc822\Rfc822Parser;
use Horde\Mail\Rfc2047;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests using canonical examples from RFC 2822 Appendix A and RFC 2047 §8.
 */
#[CoversClass(Rfc822Parser::class)]
#[CoversClass(Rfc2047::class)]
class RfcCanonicalTest extends TestCase
{
    private Rfc822Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Rfc822Parser();
    }

    /**
     * RFC 2822 Appendix A.1.1: simple address with display name.
     * "John Doe <jdoe@machine.example>"
     */
    public function testRfc2822AppendixA11SimpleAddress(): void
    {
        $list = $this->parser->parseAddressList('John Doe <jdoe@machine.example>');

        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertInstanceOf(Address::class, $addr);
        $this->assertSame('John Doe', $addr->personal);
        $this->assertSame('jdoe', $addr->mailbox);
        $this->assertSame('machine.example', $addr->host);
    }

    /**
     * RFC 2822 Appendix A.1.1: bare address without display name.
     * "jdoe@machine.example"
     */
    public function testRfc2822AppendixA11NoDisplayName(): void
    {
        $list = $this->parser->parseAddressList('jdoe@machine.example');

        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertInstanceOf(Address::class, $addr);
        $this->assertNull($addr->personal);
        $this->assertSame('jdoe', $addr->mailbox);
        $this->assertSame('machine.example', $addr->host);
    }

    /**
     * RFC 2822 Appendix A.1.2: multiple mailboxes in a single list.
     * "Joe Q. Public" <john.q.public@example.com>, Mary Smith <mary@x.test>, jdoe@example.org
     */
    public function testRfc2822AppendixA12MultipleMailboxes(): void
    {
        $list = $this->parser->parseAddressList(
            '"Joe Q. Public" <john.q.public@example.com>, Mary Smith <mary@x.test>, jdoe@example.org'
        );

        $this->assertCount(3, $list);
        $addresses = $list->addresses();

        $this->assertSame('Joe Q. Public', $addresses[0]->personal);
        $this->assertSame('john.q.public', $addresses[0]->mailbox);
        $this->assertSame('example.com', $addresses[0]->host);

        $this->assertSame('Mary Smith', $addresses[1]->personal);
        $this->assertSame('mary', $addresses[1]->mailbox);
        $this->assertSame('x.test', $addresses[1]->host);

        $this->assertNull($addresses[2]->personal);
        $this->assertSame('jdoe', $addresses[2]->mailbox);
        $this->assertSame('example.org', $addresses[2]->host);
    }

    /**
     * RFC 2822 Appendix A.1.3: group address.
     * "A Group:Ed Jones <c@a.test>,joe@where.test,John <jdoe@one.test>;"
     */
    public function testRfc2822AppendixA13GroupAddress(): void
    {
        $list = $this->parser->parseAddressList(
            'A Group:Ed Jones <c@a.test>,joe@where.test,John <jdoe@one.test>;'
        );

        $groups = $list->groups();
        $this->assertCount(1, $groups);
        $this->assertSame('A Group', $groups[0]->groupname);

        $members = $groups[0]->addresses->addresses();
        $this->assertCount(3, $members);

        $this->assertSame('Ed Jones', $members[0]->personal);
        $this->assertSame('c', $members[0]->mailbox);
        $this->assertSame('a.test', $members[0]->host);

        $this->assertNull($members[1]->personal);
        $this->assertSame('joe', $members[1]->mailbox);
        $this->assertSame('where.test', $members[1]->host);

        $this->assertSame('John', $members[2]->personal);
        $this->assertSame('jdoe', $members[2]->mailbox);
        $this->assertSame('one.test', $members[2]->host);
    }

    /**
     * RFC 2822 Appendix A.1.4: empty group (no addresses).
     * "Undisclosed recipients:;"
     */
    public function testRfc2822AppendixA14EmptyGroup(): void
    {
        $list = $this->parser->parseAddressList('Undisclosed recipients:;');

        $this->assertCount(0, $list);
    }

    /**
     * RFC 2822 §3.4: address with comment (parenthetical remark).
     * "jdoe@machine.example (John Doe)" — comment after addr-spec.
     */
    public function testRfc2822AddressWithComment(): void
    {
        $list = $this->parser->parseAddressList('jdoe@machine.example (John Doe)');

        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertInstanceOf(Address::class, $addr);
        $this->assertSame('jdoe', $addr->mailbox);
        $this->assertSame('machine.example', $addr->host);
        $this->assertSame(['John Doe'], $addr->comments);
    }

    /**
     * RFC 2822: quoted personal name containing special characters (colon).
     * "Mary Smith: Personal Account" <smith@home.example>
     */
    public function testRfc2822QuotedPersonalWithSpecials(): void
    {
        $list = $this->parser->parseAddressList(
            '"Mary Smith: Personal Account" <smith@home.example>'
        );

        $this->assertCount(1, $list);
        $addr = $list->first();
        $this->assertSame('Mary Smith: Personal Account', $addr->personal);
        $this->assertSame('smith', $addr->mailbox);
        $this->assertSame('home.example', $addr->host);
    }

    /**
     * RFC 2047 §8: Q-encoding with underscores representing spaces.
     * "=?US-ASCII?Q?Keith_Moore?=" → "Keith Moore"
     */
    public function testRfc2047Section8KeithMoore(): void
    {
        $decoded = Rfc2047::decode('=?US-ASCII?Q?Keith_Moore?=');

        $this->assertSame('Keith Moore', $decoded);
    }

    /**
     * RFC 2047 §8: Q-encoding with ISO-8859-1 high byte (ø = 0xF8).
     * "=?ISO-8859-1?Q?Keld_J=F8rn_Simonsen?=" → "Keld Jørn Simonsen"
     */
    public function testRfc2047Section8KeldSimonsen(): void
    {
        $decoded = Rfc2047::decode('=?ISO-8859-1?Q?Keld_J=F8rn_Simonsen?=');

        $this->assertSame('Keld Jørn Simonsen', $decoded);
    }

    /**
     * RFC 2047 §8: Q-encoding with ISO-8859-1 (ä = 0xE4).
     * "=?ISO-8859-1?Q?Olle_J=E4rnefors?=" → "Olle Järnefors"
     */
    public function testRfc2047Section8OlleJarnefors(): void
    {
        $decoded = Rfc2047::decode('=?ISO-8859-1?Q?Olle_J=E4rnefors?=');

        $this->assertSame('Olle Järnefors', $decoded);
    }

    /**
     * RFC 2047 §8: Q-encoding with multiple high bytes (ä=0xE4, ö=0xF6).
     * "=?ISO-8859-1?Q?Patrik_F=E4ltstr=F6m?=" → "Patrik Fältström"
     */
    public function testRfc2047Section8PatrikFaltstrom(): void
    {
        $decoded = Rfc2047::decode('=?ISO-8859-1?Q?Patrik_F=E4ltstr=F6m?=');

        $this->assertSame('Patrik Fältström', $decoded);
    }

    /**
     * RFC 2047 §6.2: adjacent encoded-words separated only by linear
     * whitespace have the whitespace ignored when decoding.
     */
    public function testRfc2047AdjacentEncodedWordsJoined(): void
    {
        $input = "=?ISO-8859-1?Q?a?=\r\n =?ISO-8859-1?Q?b?=";

        $decoded = Rfc2047::decode($input);

        $this->assertSame('ab', $decoded);
    }

    /**
     * RFC 2047 §8: Base64 (B) encoding of ISO-8859-1 text.
     */
    public function testRfc2047Base64Decoding(): void
    {
        $text = 'If you can read this yo';
        $encoded = '=?ISO-8859-1?B?' . base64_encode($text) . '?=';

        $decoded = Rfc2047::decode($encoded);

        $this->assertSame($text, $decoded);
    }

    /**
     * RFC 2047: mixed Q and B encoded-words in a single header field.
     */
    public function testRfc2047MixedEncodingsInField(): void
    {
        $input = '=?US-ASCII?Q?Hello?= =?ISO-8859-1?B?V/ZybGQ=?=';

        $decoded = Rfc2047::decode($input);

        $this->assertSame('HelloWörld', $decoded);
    }
}
