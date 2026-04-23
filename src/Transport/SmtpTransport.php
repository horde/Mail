<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Smtp\Protocol;
use Horde\Smtp\SendResult;
use Horde\Smtp\SmtpClient;
use Horde\Smtp\SmtpConfig;
use Horde\Smtp\SmtpException;
use Horde\Stream\StreamInterface;
use Horde\Stream\Temp;

/**
 * Requires horde/smtp (optional dependency).
 *
 * @todo Relocate to horde/smtp — this class belongs with the protocol implementation.
 */
final class SmtpTransport implements Transport
{
    use TransportHelper;

    private ?SmtpClient $client = null;
    private ?SendResult $lastResult = null;

    public function __construct(
        private readonly SmtpConfig $smtpConfig,
        private readonly Protocol $protocol = Protocol::Smtp,
    ) {
        $this->eol = "\r\n";
    }

    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {
        $client = $this->client();

        $headers = $this->sanitizeHeaders($headers);
        [$from, $textHeaders] = $this->prepareHeaders($headers);
        $from = $this->extractFrom($from, $headers);
        $recipientsBare = $this->recipientsToBareIdn($recipients);

        $combined = new Temp();
        $combined->add(rtrim($textHeaders, $this->eol));
        $combined->add($this->eol . $this->eol);

        if ($body instanceof StreamInterface) {
            $combined->add((string) $body);
        } else {
            $combined->add($body);
        }

        $combined->rewind();

        try {
            $this->lastResult = $client->send(
                $from,
                $recipientsBare,
                $combined->getResource(),
            );
        } catch (SmtpException $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function supportsEai(): bool
    {
        if ($this->client === null) {
            return false;
        }

        $capabilities = $this->client->capabilities();
        return $capabilities !== null && $capabilities->supportsInternationalized();
    }

    public function client(): SmtpClient
    {
        if ($this->client === null) {
            $this->client = new SmtpClient($this->smtpConfig, $this->protocol);
            try {
                $this->client->connect();
            } catch (SmtpException $e) {
                $this->client = null;
                throw new TransportException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->client;
    }

    public function lastSendResult(): ?SendResult
    {
        return $this->lastResult;
    }
}
