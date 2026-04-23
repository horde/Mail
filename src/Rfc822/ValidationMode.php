<?php

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

enum ValidationMode
{
    case Lenient;
    case Strict;
    case Eai;
}
