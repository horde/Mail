<?php

declare(strict_types=1);

namespace Horde\Mail\Mbox;

use DateTimeImmutable;
use Horde\Stream\StreamInterface;

final readonly class MboxMessage
{
    public function __construct(
        public StreamInterface $data,
        public ?DateTimeImmutable $date,
        public int $size,
    ) {}
}
