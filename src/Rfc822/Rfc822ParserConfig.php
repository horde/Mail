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
