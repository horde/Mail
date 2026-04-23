<?php

declare(strict_types=1);

namespace Horde\Mail\Transport;

final readonly class PhpMailConfig
{
    public function __construct(
        public string $additionalArgs = '',
    ) {}
}
