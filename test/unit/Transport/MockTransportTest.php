<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Transport;

use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Transport\MockTransport;
use Horde\Mail\Transport\SentMessage;
use Horde\Mail\Transport\Transport;
use Horde\Mail\Transport\TransportException;
use Horde\Stream\Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockTransport::class)]
#[CoversClass(SentMessage::class)]
class MockTransportTest extends TestCase
{
    private function makeRecipients(string ...$emails): AddressList
    {
        $list = new AddressList();
        foreach ($emails as $email) {
            [$mailbox, $host] = explode('@', $email);
            $list->add(new Address($mailbox, $host));
        }
        return $list;
    }

    public function testImplementsTransport(): void
    {
        $this->assertInstanceOf(Transport::class, new MockTransport());
    }

    public function testSupportsEaiReturnsFalse(): void
    {
        $this->assertFalse((new MockTransport())->supportsEai());
    }

    public function testBasicSend(): void
    {
        $transport = new MockTransport();
        $recipients = $this->makeRecipients('test@example.com');

        $transport->send(
            $recipients,
            ['From' => 'sender@example.com', 'Subject' => 'Test'],
            'Hello World',
        );

        $messages = $transport->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(SentMessage::class, $messages[0]);
        $this->assertSame(['test@example.com'], $messages[0]->recipients);
        $this->assertSame('sender@example.com', $messages[0]->from);
        $this->assertSame('Hello World', $messages[0]->body);
    }

    public function testMultipleSends(): void
    {
        $transport = new MockTransport();

        $transport->send(
            $this->makeRecipients('a@example.com'),
            ['From' => 'sender@example.com'],
            'First',
        );
        $transport->send(
            $this->makeRecipients('b@example.com'),
            ['From' => 'sender@example.com'],
            'Second',
        );

        $this->assertCount(2, $transport->sentMessages());
        $this->assertSame('First', $transport->sentMessages()[0]->body);
        $this->assertSame('Second', $transport->sentMessages()[1]->body);
    }

    public function testReset(): void
    {
        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('a@example.com'),
            ['From' => 'sender@example.com'],
            'Test',
        );
        $this->assertCount(1, $transport->sentMessages());

        $transport->reset();
        $this->assertCount(0, $transport->sentMessages());
    }

    public function testMixedEolsLfMode(): void
    {
        $transport = new MockTransport(eol: "\n");
        $recipients = $this->makeRecipients('test@example.com');
        $body = "Foo\r\nBar\nBaz\rTest";
        $headers = [
            'To' => '<test2@example.com>',
            'From' => '<foo@example.com>',
            'Subject' => 'Test',
            'X-Test' => "Line 1\r\n\tLine 2\n\tLine 3\r\tLine 4",
            'X-Truncated-Header' => $body,
        ];

        $transport->send($recipients, $headers, $body);

        $msg = $transport->sentMessages()[0];
        $this->assertStringNotContainsString("\r", $msg->headerText);
        $this->assertStringNotContainsString("\r", $msg->body);
    }

    public function testMixedEolsCrlfMode(): void
    {
        $transport = new MockTransport(eol: "\r\n");
        $recipients = $this->makeRecipients('test@example.com');
        $body = "Foo\r\nBar\nBaz\rTest";
        $headers = [
            'To' => '<test2@example.com>',
            'From' => '<foo@example.com>',
            'Subject' => 'Test',
            'X-Test' => "Line 1\r\n\tLine 2\n\tLine 3\r\tLine 4",
        ];

        $transport->send($recipients, $headers, $body);

        $msg = $transport->sentMessages()[0];
        $this->assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $msg->headerText);
        $this->assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $msg->body);
    }

    public function testIdnEncoding(): void
    {
        $addr = new Address('test', 'üexample.com', 'Aäb');
        $recipients = AddressList::from($addr);

        $transport = new MockTransport();
        $transport->send(
            $recipients,
            ['Return-Path' => '<test@üexample.com>'],
            'Foo',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertSame(['test@xn--example-m2a.com'], $msg->recipients);
        $this->assertSame('test@xn--example-m2a.com', $msg->from);
    }

    public function testMissingFromThrows(): void
    {
        $this->expectException(TransportException::class);

        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('foo@example.com'),
            [],
            'Foo',
        );
    }

    public function testReturnPathOverridesFrom(): void
    {
        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('rcpt@example.com'),
            [
                'From' => 'visible@example.com',
                'Return-Path' => '<bounce@example.com>',
            ],
            'Test',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertSame('bounce@example.com', $msg->from);
    }

    public function testPreSendCallback(): void
    {
        $called = false;
        $transport = new MockTransport(
            preSendCallback: function () use (&$called): void {
                $called = true;
            },
        );

        $transport->send(
            $this->makeRecipients('a@example.com'),
            ['From' => 'b@example.com'],
            'Test',
        );

        $this->assertTrue($called);
    }

    public function testPostSendCallback(): void
    {
        $capturedRecipients = null;
        $transport = new MockTransport(
            postSendCallback: function ($transport, $recipients) use (&$capturedRecipients): void {
                $capturedRecipients = $recipients;
            },
        );

        $transport->send(
            $this->makeRecipients('a@example.com'),
            ['From' => 'b@example.com'],
            'Body',
        );

        $this->assertSame(['a@example.com'], $capturedRecipients);
    }

    public function testStreamBody(): void
    {
        $stream = new Temp();
        $stream->add('Stream body content');
        $stream->rewind();

        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('a@example.com'),
            ['From' => 'b@example.com'],
            $stream,
        );

        $msg = $transport->sentMessages()[0];
        $this->assertSame('Stream body content', $msg->body);
    }

    public function testHeaderSanitization(): void
    {
        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('a@example.com'),
            [
                'From' => 'b@example.com',
                'X-Injected' => 'value<CR>Evil-Header: injected',
            ],
            'Test',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertStringNotContainsString('Evil-Header', $msg->headerText);
    }

    public function testMultipleRecipients(): void
    {
        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('a@example.com', 'b@example.com', 'c@example.com'),
            ['From' => 'sender@example.com'],
            'Test',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertCount(3, $msg->recipients);
        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $msg->recipients);
    }

    public function testSentMessageDto(): void
    {
        $transport = new MockTransport();
        $transport->send(
            $this->makeRecipients('rcpt@example.com'),
            ['From' => 'from@example.com', 'Subject' => 'Hello'],
            'Body text',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertSame('Body text', $msg->body);
        $this->assertSame('from@example.com', $msg->from);
        $this->assertIsArray($msg->headers);
        $this->assertIsString($msg->headerText);
        $this->assertIsArray($msg->recipients);
    }

    public function testRawHeaders(): void
    {
        $raw = "From: raw@example.com\r\nSubject: Raw\r\n";
        $transport = new MockTransport(eol: "\r\n");
        $transport->send(
            $this->makeRecipients('rcpt@example.com'),
            ['From' => 'from@example.com', '_raw' => $raw],
            'Body',
        );

        $msg = $transport->sentMessages()[0];
        $this->assertSame($raw, $msg->headerText);
    }

    public function testReceivedHeadersPrepended(): void
    {
        $transport = new MockTransport(eol: "\n");
        $transport->send(
            $this->makeRecipients('rcpt@example.com'),
            [
                'From' => 'from@example.com',
                'Received' => ['from mx1.example.com', 'from mx2.example.com'],
                'Subject' => 'Test',
            ],
            'Body',
        );

        $msg = $transport->sentMessages()[0];
        $pos_received = strpos($msg->headerText, 'Received:');
        $pos_from = strpos($msg->headerText, 'From:');
        $this->assertNotFalse($pos_received);
        $this->assertLessThan($pos_from, $pos_received);
    }
}
