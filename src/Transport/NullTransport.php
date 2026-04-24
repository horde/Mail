<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 * Copyright (c) 2010 Phil Kernick
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Phil Kernick <philk@rotfl.com.au>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Stream\StreamInterface;

final class NullTransport implements Transport
{
    public function send(
        AddressList $recipients,
        array $headers,
        string|StreamInterface $body,
    ): void {}

    public function supportsEai(): bool
    {
        return false;
    }
}
