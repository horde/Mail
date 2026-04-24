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

use RuntimeException;
use Throwable;

class ParseException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?int $position = null,
        public readonly ?string $input = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
