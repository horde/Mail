<?php

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
