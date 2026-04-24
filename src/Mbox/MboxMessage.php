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
