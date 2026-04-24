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

use Horde\Mail\Rfc822\AddressList;
use Horde\Stream\StreamInterface;

final class PhpMailTransport implements Transport
{
    use TransportHelper;

    public function __construct(
        private readonly PhpMailConfig $config = new PhpMailConfig(),
    ) {}

    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {
        $headers = $this->sanitizeHeaders($headers);
        $recipientsBare = $this->recipientsToBareIdn($recipients);
        $recipientsStr = implode(',', $recipientsBare);

        $subject = '';
        foreach (array_keys($headers) as $hdr) {
            if (strcasecmp($hdr, 'Subject') === 0) {
                $subject = is_array($headers[$hdr])
                    ? implode(', ', $headers[$hdr])
                    : $headers[$hdr];
                unset($headers[$hdr]);
            } elseif (strcasecmp($hdr, 'To') === 0) {
                unset($headers[$hdr]);
            }
        }

        [, $textHeaders] = $this->prepareHeaders($headers);

        $bodyStr = $this->bodyToString($body);

        $result = ($this->config->additionalArgs !== '')
            ? mail($recipientsStr, $subject, $bodyStr, $textHeaders, $this->config->additionalArgs)
            : mail($recipientsStr, $subject, $bodyStr, $textHeaders);

        if ($result === false) {
            throw new TransportException('mail() returned failure.');
        }
    }

    public function supportsEai(): bool
    {
        return false;
    }
}
