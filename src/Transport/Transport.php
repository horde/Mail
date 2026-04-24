<?php

/**
 * Copyright 1997-2026 Horde LLC (http://www.horde.org/)
 * Copyright (c) 2002-2007, Richard Heyes
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Richard Heyes <richard@phpguru.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

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
