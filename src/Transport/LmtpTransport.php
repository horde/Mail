<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Smtp\Protocol;
use Horde\Smtp\SendResult;
use Horde\Smtp\SmtpClient;
use Horde\Smtp\SmtpConfig;
use Horde\Stream\StreamInterface;

/**
 * Requires horde/smtp (optional dependency).
 *
 * @todo Relocate to horde/smtp — this class belongs with the protocol implementation.
 */
final class LmtpTransport implements Transport
{
    private readonly SmtpTransport $inner;

    public function __construct(SmtpConfig $smtpConfig)
    {
        $this->inner = new SmtpTransport($smtpConfig, Protocol::Lmtp);
    }

    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {
        $this->inner->send($recipients, $headers, $body);
    }

    public function supportsEai(): bool
    {
        return $this->inner->supportsEai();
    }

    public function client(): SmtpClient
    {
        return $this->inner->client();
    }

    public function lastSendResult(): ?SendResult
    {
        return $this->inner->lastSendResult();
    }
}
