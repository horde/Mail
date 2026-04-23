<?php

declare(strict_types=1);

namespace Horde\Mail\Mbox;

use ArrayIterator;
use Countable;
use DateTimeImmutable;
use Horde\Mail\MailException;
use Horde\Stream\Temp;
use IteratorAggregate;
use Traversable;
use Exception;

final class MboxParser implements Countable, IteratorAggregate
{
    /** @var resource */
    private mixed $stream;

    /** @var array<int, array{date: ?DateTimeImmutable|false, start: int}> */
    private array $parsed = [];

    /**
     * @param string|resource $data Filename or open stream resource
     * @throws MailException
     */
    public function __construct(mixed $data, ?int $limit = null)
    {
        $this->stream = is_resource($data)
            ? $data
            : @fopen($data, 'r');

        if ($this->stream === false) {
            throw new MailException('Could not parse mailbox data.');
        }

        rewind($this->stream);

        $lastLine = null;
        $mbox = false;
        $count = 0;
        $start = 0;

        while (!feof($this->stream)) {
            if ($lastLine === null) {
                $start = ftell($this->stream);
            }

            $line = fgets($this->stream);

            if ($line === false) {
                break;
            }

            if ($lastLine === null) {
                ltrim($line);
            }

            if (str_starts_with($line, 'From ')) {
                if ($lastLine === null) {
                    $mbox = true;
                } elseif (!$mbox || trim($lastLine) !== '') {
                    $lastLine = $line;
                    continue;
                }

                if ($limit !== null && ++$count > $limit) {
                    throw new MailException(
                        sprintf('Imported mailbox contains more than enforced limit of %d messages.', $limit),
                    );
                }

                $fromParts = explode(' ', $line, 3);
                try {
                    $date = new DateTimeImmutable($fromParts[2] ?? '');
                } catch (Exception) {
                    $date = null;
                }

                $this->parsed[] = [
                    'date' => $date,
                    'start' => ftell($this->stream),
                ];
            }

            if ($lastLine !== null || trim($line) !== '') {
                $lastLine = $line;
            }
        }

        if ($this->parsed === []) {
            $this->parsed[] = [
                'date' => false,
                'start' => $start,
            ];
        }
    }

    /**
     * @throws MailException
     */
    public function get(int $index): MboxMessage
    {
        if (!isset($this->parsed[$index])) {
            throw new MailException(sprintf('Message index %d out of range.', $index));
        }

        $p = $this->parsed[$index];
        $end = isset($this->parsed[$index + 1])
            ? $this->parsed[$index + 1]['start']
            : null;

        $temp = new Temp();
        $resource = $temp->getResource();

        fseek($this->stream, $p['start']);
        while (!feof($this->stream)) {
            $line = fgets($this->stream);
            if ($line === false) {
                break;
            }
            if ($end !== null && ftell($this->stream) >= $end) {
                break;
            }

            $output = ($p['date'] !== false && str_starts_with($line, '>From '))
                ? substr($line, 1)
                : $line;

            fwrite($resource, $output);
        }

        $size = (int) ftell($resource);
        $temp->rewind();

        $date = ($p['date'] === false) ? null : $p['date'];

        return new MboxMessage(
            data: $temp,
            date: $date,
            size: $size,
        );
    }

    public function count(): int
    {
        return count($this->parsed);
    }

    public function getIterator(): Traversable
    {
        for ($i = 0, $count = count($this->parsed); $i < $count; $i++) {
            yield $i => $this->get($i);
        }
    }

    public function __toString(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }
}
