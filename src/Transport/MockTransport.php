<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Closure;
use Horde\Mail\Rfc822\AddressList;
use Horde\Stream\StreamInterface;

final class MockTransport implements Transport
{
    use TransportHelper;

    /** @var SentMessage[] */
    private array $sentMessages = [];

    public function __construct(
        private readonly ?Closure $preSendCallback = null,
        private readonly ?Closure $postSendCallback = null,
        string $eol = PHP_EOL,
    ) {
        $this->eol = $eol;
    }

    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {
        if ($this->preSendCallback !== null) {
            ($this->preSendCallback)($this, $recipients, $headers, $body);
        }

        $headers = $this->sanitizeHeaders($headers);
        [$from, $textHeaders] = $this->prepareHeaders($headers);
        $from = $this->extractFrom($from, $headers);
        $recipientsBare = $this->recipientsToBareIdn($recipients);
        $bodyStr = $this->bodyToString($body);

        $this->sentMessages[] = new SentMessage(
            body: $bodyStr,
            from: $from,
            headers: $headers,
            headerText: $textHeaders,
            recipients: $recipientsBare,
        );

        if ($this->postSendCallback !== null) {
            ($this->postSendCallback)($this, $recipientsBare, $headers, $bodyStr);
        }
    }

    public function supportsEai(): bool
    {
        return false;
    }

    /**
     * @return SentMessage[]
     */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
    }
}
