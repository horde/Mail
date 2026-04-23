<?php

declare(strict_types=1);

namespace Horde\Mail\Rfc822;

trait Rfc822CharacterRules
{
    private string $data = '';
    private int $dataLen = 0;
    private int $ptr = 0;

    abstract private function validationMode(): ValidationMode;

    private function curr(bool $advance = false): string|false
    {
        return ($this->ptr >= $this->dataLen)
            ? false
            : $this->data[$advance ? $this->ptr++ : $this->ptr];
    }

    private function isAtext(string $chr, ?string $nonAtomDelimiters = null): bool
    {
        if ($this->validationMode() === ValidationMode::Lenient && $nonAtomDelimiters !== null) {
            return (bool) strcspn($chr, $nonAtomDelimiters);
        }

        $ord = ($chr === '') ? 0 : ord($chr);

        if ($ord > 127) {
            return $this->validationMode() === ValidationMode::Eai;
        }

        if ($ord <= 32) {
            return false;
        }

        return match ($ord) {
            34, 40, 41, 44, 58, 59, 60, 62, 64, 91, 92, 93, 127 => false,
            default => true,
        };
    }

    private function skipLwsp(bool $advance = false): void
    {
        if ($advance) {
            ++$this->ptr;
        }

        while (($chr = $this->curr()) !== false) {
            switch ($chr) {
                case ' ':
                case "\n":
                case "\r":
                case "\t":
                    ++$this->ptr;
                    continue 2;

                case '(':
                    $this->skipComment($this->activeComments);
                    break;

                default:
                    return;
            }
        }
    }

    private function skipComment(array &$comments): void
    {
        if ($this->curr(true) !== '(') {
            throw new ParseException('Error when parsing a comment.');
        }

        $comment = '';
        $level = 1;

        while (($chr = $this->curr(true)) !== false) {
            switch ($chr) {
                case '(':
                    ++$level;
                    continue 2;

                case ')':
                    if (--$level === 0) {
                        $comments[] = $comment;
                        return;
                    }
                    break;

                case '\\':
                    if (($chr = $this->curr(true)) === false) {
                        break 2;
                    }
                    break;
            }

            $comment .= $chr;
        }

        throw new ParseException('Error when parsing a comment.');
    }

    private function traitParseQuotedString(string &$str): void
    {
        if ($this->curr(true) !== '"') {
            throw new ParseException('Error when parsing a quoted string.');
        }

        while (($chr = $this->curr(true)) !== false) {
            switch ($chr) {
                case '"':
                    $this->skipLwsp();
                    return;

                case "\n":
                    if (substr($str, -1) === "\r") {
                        $str = substr($str, 0, -1);
                    }
                    continue 2;

                case '\\':
                    if (($chr = $this->curr(true)) === false) {
                        break 2;
                    }
                    break;
            }

            $str .= $chr;
        }

        throw new ParseException('Error when parsing a quoted string.');
    }

    private function traitParseDotAtom(string &$str, ?string $validate = null): void
    {
        $valid = false;

        while ($this->ptr < $this->dataLen) {
            $chr = $this->data[$this->ptr];

            if ($this->isAtext($chr, $validate)) {
                $str .= $chr;
                ++$this->ptr;
            } elseif (!$valid) {
                throw new ParseException('Error when parsing dot-atom.');
            } else {
                $this->skipLwsp();

                if ($this->curr() !== '.') {
                    return;
                }
                $str .= $chr;

                $this->skipLwsp(true);
            }

            $valid = true;
        }
    }

    private function traitParseAtomOrDot(string &$str): void
    {
        while ($this->ptr < $this->dataLen) {
            $chr = $this->data[$this->ptr];
            if ($chr !== '.' && !$this->isAtext($chr, ',<:')) {
                $this->skipLwsp();
                if ($this->validationMode() === ValidationMode::Lenient && $str !== '') {
                    $str = trim($str);
                }
                return;
            }

            $str .= $chr;
            ++$this->ptr;
        }
    }

    private function traitParseDomain(string &$str): void
    {
        if ($this->curr(true) !== '@') {
            throw new ParseException('Error when parsing domain.');
        }

        $this->skipLwsp();

        if ($this->curr() === '[') {
            $this->traitParseDomainLiteral($str);
        } else {
            $this->traitParseDotAtom($str, ';,> ');
        }
    }

    private function traitParseDomainLiteral(string &$str): void
    {
        if ($this->curr(true) !== '[') {
            throw new ParseException('Error parsing domain literal.');
        }

        while (($chr = $this->curr(true)) !== false) {
            switch ($chr) {
                case '\\':
                    if (($chr = $this->curr(true)) === false) {
                        break 2;
                    }
                    break;

                case ']':
                    $this->skipLwsp();
                    return;
            }

            $str .= $chr;
        }

        throw new ParseException('Error parsing domain literal.');
    }
}
