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

namespace Horde\Mail\Transport;

use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Rfc822Parser;
use Horde\Mail\Rfc822\Rfc822ParserConfig;
use Horde\Mail\Rfc822\ValidationMode;
use Horde\Stream\StreamInterface;

/** @phpstan-require-implements Transport */
trait TransportHelper
{
    private string $eol = PHP_EOL;

    /**
     * @param array<string, string|string[]> $headers
     * @return array{0: ?string, 1: string} [from address, header text]
     */
    private function prepareHeaders(array $headers): array
    {
        $from = null;
        $lines = [];
        $raw = $headers['_raw'] ?? null;

        $validation = $this->supportsEai()
            ? ValidationMode::Eai
            : ValidationMode::Strict;
        $config = new Rfc822ParserConfig(validation: $validation);
        $parser = new Rfc822Parser($config);

        foreach ($headers as $key => $value) {
            if ($key === '_raw') {
                continue;
            }

            if (strcasecmp($key, 'From') === 0) {
                $valueStr = is_array($value) ? implode(', ', $value) : $value;
                $addresses = $parser->parseAddressList($valueStr);
                $first = $addresses->first();
                if ($first !== null) {
                    $from = $first->bareAddress();
                }

                if ($from !== null && str_contains($from, ' ')) {
                    throw new TransportException('From address contains spaces.');
                }

                $lines[] = $key . ': ' . $this->normalizeEol($valueStr);
            } elseif (!$raw && strcasecmp($key, 'Received') === 0) {
                $received = [];
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $line) {
                    $received[] = $key . ': ' . $this->normalizeEol($line);
                }

                $lines = array_merge($received, $lines);
            } elseif (!$raw) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $lines[] = $key . ': ' . $this->normalizeEol($value);
            }
        }

        return [$from, $raw ?? implode($this->eol, $lines)];
    }

    /**
     * @return string[] Bare IDN-encoded addresses
     */
    private function recipientsToBareIdn(AddressList $recipients): array
    {
        return $recipients->bareAddressesIdn();
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach (array_diff(array_keys($headers), ['_raw']) as $key) {
            if (is_array($headers[$key])) {
                $headers[$key] = array_map(
                    fn(string $v) => preg_replace('=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\\\n|\\\\r)\S).*=i', '', $v),
                    $headers[$key],
                );
            } else {
                $headers[$key] = preg_replace(
                    '=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\\\n|\\\\r)\S).*=i',
                    '',
                    $headers[$key],
                );
            }
        }

        return $headers;
    }

    private function normalizeEol(string $data): string
    {
        return strtr($data, [
            "\r\n" => $this->eol,
            "\r" => $this->eol,
            "\n" => $this->eol,
        ]);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function extractFrom(?string $fromPrepared, array $headers): string
    {
        foreach (array_keys($headers) as $hdr) {
            if (strcasecmp($hdr, 'Return-Path') === 0) {
                $fromPrepared = is_array($headers[$hdr])
                    ? implode(', ', $headers[$hdr])
                    : $headers[$hdr];
                break;
            }
        }

        if ($fromPrepared === null || $fromPrepared === '') {
            throw new TransportException('No from address provided.');
        }

        $parser = new Rfc822Parser();
        $list = $parser->parseAddressList(Rfc822Parser::trimAddress($fromPrepared));
        $first = $list->first();
        if ($first === null) {
            throw new TransportException('No from address provided.');
        }

        return $first->bareAddressIdn();
    }

    private function bodyToString(string|StreamInterface $body): string
    {
        $str = ($body instanceof StreamInterface)
            ? (string) $body
            : $body;

        return $this->normalizeEol($str);
    }
}
