<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Stream\StreamInterface;

final class SendmailTransport implements Transport
{
    use TransportHelper;

    public function __construct(
        private readonly SendmailConfig $config = new SendmailConfig(),
    ) {}

    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {
        $recipientsBare = $this->recipientsToBareIdn($recipients);
        $recipientsStr = implode(' ', array_map('escapeshellarg', $recipientsBare));

        $headers = $this->sanitizeHeaders($headers);
        [$from, $textHeaders] = $this->prepareHeaders($headers);
        $from = $this->extractFrom($from, $headers);

        $cmd = $this->config->sendmailPath
            . ($this->config->sendmailArgs !== '' ? ' ' . $this->config->sendmailArgs : '')
            . ' -f ' . escapeshellarg($from)
            . ' -- ' . $recipientsStr;

        $mail = @popen($cmd, 'w');
        if (!$mail) {
            throw new TransportException(
                'Failed to open sendmail [' . $this->config->sendmailPath . '] for execution.',
            );
        }

        fputs($mail, $textHeaders . $this->eol . $this->eol);

        if ($body instanceof StreamInterface) {
            $resource = $body->getResource();
            if (is_resource($resource)) {
                rewind($resource);
                while (!feof($resource)) {
                    fputs($mail, $this->normalizeEol(fread($resource, 8192)));
                }
            }
        } else {
            fputs($mail, $this->normalizeEol($body));
        }

        $result = pclose($mail);

        if ($result === 0) {
            return;
        }

        $msg = match ($result) {
            64 => 'command line usage error',
            65 => 'data format error',
            66 => 'cannot open input',
            67 => 'addressee unknown',
            68 => 'host name unknown',
            69 => 'service unavailable',
            70 => 'internal software error',
            71 => 'system error',
            72 => 'critical system file missing',
            73 => 'cannot create output file',
            74 => 'input/output error',
            75 => 'temporary failure',
            76 => 'remote error in protocol',
            77 => 'permission denied',
            78 => 'configuration error',
            79 => 'entry not found',
            default => 'unknown error',
        };

        throw new TransportException('sendmail: ' . $msg . ' (' . $result . ')', $result);
    }

    public function supportsEai(): bool
    {
        return false;
    }
}
