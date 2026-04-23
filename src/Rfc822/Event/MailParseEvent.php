<?php

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
