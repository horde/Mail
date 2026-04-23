<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use Horde\Mail\Rfc822\MessageIdParser;
use Horde\Mail\Rfc822\ParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageIdParser::class)]
class MessageIdParserTest extends TestCase
{
    private function parser(): MessageIdParser
    {
        return new MessageIdParser();
    }

    public function testSingleMessageId(): void
    {
        $ids = $this->parser()->parse('<msg-001@example.com>');
        $this->assertSame(['<msg-001@example.com>'], $ids);
    }

    public function testMultipleMessageIds(): void
    {
        $ids = $this->parser()->parse('<msg-001@example.com> <msg-002@example.com>');
        $this->assertSame(['<msg-001@example.com>', '<msg-002@example.com>'], $ids);
    }

    public function testReferencesHeader(): void
    {
        $ids = $this->parser()->parse(
            '<first@example.com> <second@example.com> <third@example.com>'
        );
        $this->assertCount(3, $ids);
    }

    public function testCommaSeparatedTolerance(): void
    {
        $ids = $this->parser()->parse('<msg1@example.com>, <msg2@example.com>');
        $this->assertCount(2, $ids);
    }

    public function testEmptyInput(): void
    {
        $ids = $this->parser()->parse('');
        $this->assertSame([], $ids);
    }

    public function testMessageIdWithMultipleAtSigns(): void
    {
        $ids = $this->parser()->parse('<bad@@example.com>');
        $this->assertSame(['<bad@@example.com>'], $ids);
    }

    public function testUnbracketedId(): void
    {
        $ids = $this->parser()->parse('<msg@example.com> unbracketedid');
        $this->assertCount(2, $ids);
    }

    public function testWhitespaceHandling(): void
    {
        $ids = $this->parser()->parse("  <msg@example.com>  \r\n  <msg2@example.com>  ");
        $this->assertCount(2, $ids);
    }

    // ── Legacy IdentificationTest data provider cases ───────────────

    #[DataProvider('legacyParsingProvider')]
    public function testLegacyParsingCases(string $input, int $expectedCount): void
    {
        $ids = $this->parser()->parse($input);
        $this->assertCount($expectedCount, $ids);
    }

    public static function legacyParsingProvider(): array
    {
        return [
            'space separated' => [
                '<foo@example.com> <foo2@example.com> <foo3@example.com>',
                3,
            ],
            'no space between IDs' => [
                '<foo@example.com><foo2@example.com><foo3@example.com>',
                3,
            ],
            'comma separated' => [
                '<foo@example.com>, <foo2@example.com>,<foo3@example.com>',
                3,
            ],
            'mixed comma and space (5 IDs)' => [
                '<foo@example.com>, <foo2@example.com>,<foo3@example.com> <foo4@example.com>     <foo5@example.com>  ',
                5,
            ],
            'Bug #11953: double @ sign' => [
                '<foo@example@example.com>',
                1,
            ],
            'non-compliant unbracketed ID' => [
                'foo@example.com',
                1,
            ],
            'unbracketed then bracketed' => [
                'foo@example.com  <foo2@example.com>',
                2,
            ],
            'unbracketed comma bracketed' => [
                'foo@example.com, <foo2@example.com>',
                2,
            ],
        ];
    }

    // ── Additional edge cases ───────────────────────────────────────

    public function testWhitespaceOnlyInput(): void
    {
        $ids = $this->parser()->parse('   ');
        $this->assertSame([], $ids);
    }

    public function testIdWithDottedLocalPart(): void
    {
        $ids = $this->parser()->parse('<user.name.tag@example.com>');
        $this->assertCount(1, $ids);
        $this->assertSame(['<user.name.tag@example.com>'], $ids);
    }

    public function testIdWithPlusInLocalPart(): void
    {
        $ids = $this->parser()->parse('<user+tag@example.com>');
        $this->assertCount(1, $ids);
    }

    public function testIdWithSubdomain(): void
    {
        $ids = $this->parser()->parse('<msg@sub.domain.example.com>');
        $this->assertCount(1, $ids);
    }
}
