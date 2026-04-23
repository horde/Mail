<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

final readonly class SendmailConfig
{
    public function __construct(
        public string $sendmailPath = '/usr/sbin/sendmail',
        public string $sendmailArgs = '-i',
    ) {}
}
