<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 1999-2026 Horde LLC
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

namespace Horde\Mail;

use Horde\Util\HordeString;

/**
 * RFC 2047 MIME header encoding and decoding.
 *
 * Extracted from Horde_Mime to break the Mail ↔ Mime circular dependency.
 */
final class Rfc2047
{
    private const EOL = "\r\n";

    /**
     * Use windows-1252 charset when decoding ISO-8859-1 data?
     * HTML 5 requires this behavior, so it is the default.
     */
    public static bool $decodeWindows1252 = true;

    public static function is8bit(string $string): bool
    {
        for ($i = 0, $len = strlen($string); $i < $len; ++$i) {
            if (ord($string[$i]) > 127) {
                return true;
            }
        }

        return false;
    }

    public static function encode(string $text, string $charset = 'UTF-8'): string
    {
        $charset = HordeString::lower($charset);
        $text = HordeString::convertCharset($text, 'UTF-8', $charset);

        $encoded = $is_encoded = false;
        $lwsp = $word = null;
        $out = '';

        /* 0 = word unencoded
         * 1 = word encoded
         * 2 = spaces */
        $parts = [];

        for ($i = 0, $len = strlen($text); $i < $len; ++$i) {
            switch ($text[$i]) {
                case "\t":
                case "\r":
                case "\n":
                    if (!is_null($word)) {
                        $parts[] = [intval($encoded), $word, $i - $word];
                        $word = null;
                    } elseif (!is_null($lwsp)) {
                        $parts[] = [2, $lwsp, $i - $lwsp];
                        $lwsp = null;
                    }

                    $parts[] = [0, $i, 1];
                    break;

                case ' ':
                    if (!is_null($word)) {
                        $parts[] = [intval($encoded), $word, $i - $word];
                        $word = null;
                    }
                    if (is_null($lwsp)) {
                        $lwsp = $i;
                    }
                    break;

                default:
                    if (is_null($word)) {
                        $encoded = false;
                        $word = $i;
                        if (!is_null($lwsp)) {
                            $parts[] = [2, $lwsp, $i - $lwsp];
                            $lwsp = null;
                        }

                        if (($text[$i] === '=')
                            && (($i + 1) < $len)
                            && ($text[$i + 1] === '?')) {
                            ++$i;
                            $encoded = $is_encoded = true;
                        }
                    }

                    if (!$encoded) {
                        $c = ord($text[$i]);
                        if ($encoded = (($c & 0x80) || ($c < 32))) {
                            $is_encoded = true;
                        }
                    }
                    break;
            }
        }

        if (!$is_encoded) {
            return $text;
        }

        if (is_null($lwsp)) {
            $parts[] = [intval($encoded), $word, $len];
        } else {
            $parts[] = [2, $lwsp, $len];
        }

        for ($i = 0, $cnt = count($parts); $i < $cnt; ++$i) {
            $val = $parts[$i];

            switch ($val[0]) {
                case 0:
                case 2:
                    $out .= substr($text, $val[1], $val[2]);
                    break;

                case 1:
                    $j = $i;
                    for ($k = $i + 1; $k < $cnt; ++$k) {
                        switch ($parts[$k][0]) {
                            case 0:
                                break 2;

                            case 1:
                                $i = $k;
                                break;
                        }
                    }

                    $encode = '';
                    for (; $j <= $i; ++$j) {
                        $encode .= substr($text, $parts[$j][1], $parts[$j][2]);
                    }

                    $delim = '=?' . $charset . '?b?';
                    $e_parts = explode(
                        self::EOL,
                        rtrim(
                            chunk_split(
                                base64_encode($encode),
                                intval((75 - strlen($delim) + 2) / 4) * 4
                            )
                        )
                    );

                    $tmp = [];
                    foreach ($e_parts as $val) {
                        $tmp[] = $delim . $val . '?=';
                    }

                    $out .= implode(' ', $tmp);
                    break;
            }
        }

        return rtrim($out);
    }

    public static function decode(string $string): string
    {
        $old_pos = 0;
        $out = '';

        while (($pos = strpos($string, '=?', $old_pos)) !== false) {
            $pre = substr($string, $old_pos, $pos - $old_pos);
            if (!$old_pos
                || (strspn($pre, " \t\n\r") != strlen($pre))) {
                $out .= $pre;
            }

            if (($d1 = strpos($string, '?', $pos + 2)) === false) {
                break;
            }

            $orig_charset = substr($string, $pos + 2, $d1 - $pos - 2);
            if (self::$decodeWindows1252
                && (HordeString::lower($orig_charset) == 'iso-8859-1')) {
                $orig_charset = 'windows-1252';
            }

            if (($d2 = strpos($string, '?', $d1 + 1)) === false) {
                break;
            }

            $encoding = substr($string, $d1 + 1, $d2 - $d1 - 1);

            if (($end = strpos($string, '?=', $d2 + 1)) === false) {
                break;
            }

            $encoded_text = substr($string, $d2 + 1, $end - $d2 - 1);

            switch ($encoding) {
                case 'Q':
                case 'q':
                    $out .= HordeString::convertCharset(
                        quoted_printable_decode(
                            str_replace('_', ' ', $encoded_text)
                        ),
                        $orig_charset,
                        'UTF-8'
                    );
                    break;

                case 'B':
                case 'b':
                    $out .= HordeString::convertCharset(
                        base64_decode($encoded_text),
                        $orig_charset,
                        'UTF-8'
                    );
                    break;

                default:
                    break;
            }

            $old_pos = $end + 2;
        }

        return $out . substr($string, $old_pos);
    }
}
