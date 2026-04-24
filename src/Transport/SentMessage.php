<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Transport;

final readonly class SentMessage
{
    /**
     * @param string[] $recipients Bare IDN-encoded addresses
     */
    public function __construct(
        public string $body,
        public string $from,
        public array $headers,
        public string $headerText,
        public array $recipients,
    ) {}
}
