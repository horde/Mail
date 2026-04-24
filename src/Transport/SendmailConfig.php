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

final readonly class SendmailConfig
{
    public function __construct(
        public string $sendmailPath = '/usr/sbin/sendmail',
        public string $sendmailArgs = '-i',
    ) {}
}
