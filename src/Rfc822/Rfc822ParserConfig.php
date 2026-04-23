<?php

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class Rfc822ParserConfig
{
    public function __construct(
        public ?string $defaultDomain = null,
        public ValidationMode $validation = ValidationMode::Lenient,
        public int $limit = 0,
        public ?EventDispatcherInterface $eventDispatcher = null,
    ) {}
}
