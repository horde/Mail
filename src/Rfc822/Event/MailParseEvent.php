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

namespace Horde\Mail\Rfc822\Event;

abstract class MailParseEvent
{
    public function __construct(
        private readonly string $message = '',
        private readonly array $context = [],
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
