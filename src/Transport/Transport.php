<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Stream\StreamInterface;

interface Transport
{
    /**
     * @param array<string, string|string[]> $headers
     * @throws TransportException
     */
    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void;

    public function supportsEai(): bool;
}
