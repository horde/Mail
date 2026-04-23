<?php

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
