<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

final class MessageIdParser
{
    use Rfc822CharacterRules;

    private array $activeComments = [];

    /**
     * @return string[]
     */
    public function parse(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $this->data = $value;
        $this->dataLen = strlen($value);
        $this->ptr = 0;
        $this->activeComments = [];

        $ids = [];

        $this->skipLwsp();

        while ($this->curr() !== false) {
            try {
                $ids[] = $this->parseMessageId();
            } catch (ParseException $e) {
                break;
            }

            if ($this->curr() === ',') {
                $this->skipLwsp(true);
            }
        }

        return $ids;
    }

    private function validationMode(): ValidationMode
    {
        return ValidationMode::Strict;
    }

    private function parseMessageId(): string
    {
        $bracket = ($this->curr(true) === '<');
        $str = '<';

        while (($chr = $this->curr(true)) !== false) {
            if ($bracket) {
                $str .= $chr;
                if ($chr === '>') {
                    $this->skipLwsp();
                    return $str;
                }
            } else {
                if (!strcspn($chr, " \n\r\t,")) {
                    $this->skipLwsp();
                    return $str;
                }
                $str .= $chr;
            }
        }

        if (!$bracket) {
            return $str;
        }

        throw new ParseException('Invalid Message-ID.');
    }
}
