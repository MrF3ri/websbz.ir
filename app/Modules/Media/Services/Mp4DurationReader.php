<?php

namespace App\\Modules\\Media\\Services;

use RuntimeException;

class Mp4DurationReader
{
    public function milliseconds(string $path): int
    {
        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Unable to read the uploaded video.');
        }

        $length = strlen($contents);
        $offset = 0;

        while ($offset + 8 <= $length) {
            $atom = $this->atomAt($contents, $offset, $length);

            if ($atom === null) {
                break;
            }

            if ($atom['type'] === 'moov') {
                $duration = $this->movieDuration($contents, $atom['payload_offset'], $atom['end']);

                if ($duration !== null) {
                    return (int) ceil($duration * 1000);
                }
            }

            $offset = $atom['end'];
        }

        throw new RuntimeException('The MP4 duration metadata is missing or invalid.');
    }

    /**
     * @return array{type: string, payload_offset: int, end: int}|null
     */
    private function atomAt(string $contents, int $offset, int $limit): ?array
    {
        if ($offset + 8 > $limit) {
            return null;
        }

        $size = unpack('Nsize', substr($contents, $offset, 4))['size'];
        $type = substr($contents, $offset + 4, 4);
        $headerSize = 8;

        if ($size === 1) {
            if ($offset + 16 > $limit) {
                return null;
            }

            $size = unpack('Jsize', substr($contents, $offset + 8, 8))['size'];
            $headerSize = 16;
        } elseif ($size === 0) {
            $size = $limit - $offset;
        }

        if (! is_int($size) || $size < $headerSize || $size > $limit - $offset) {
            return null;
        }

        return [
            'type' => $type,
            'payload_offset' => $offset + $headerSize,
            'end' => $offset + $size,
        ];
    }

    private function movieDuration(string $contents, int $offset, int $end): ?float
    {
        while ($offset + 8 <= $end) {
            $atom = $this->atomAt($contents, $offset, $end);

            if ($atom === null) {
                return null;
            }

            if ($atom['type'] === 'mvhd') {
                $payload = $atom['payload_offset'];
                $version = ord($contents[$payload]);

                if ($version === 0 && $payload + 20 <= $atom['end']) {
                    $timescale = unpack('Nvalue', substr($contents, $payload + 12, 4))['value'];
                    $duration = unpack('Nvalue', substr($contents, $payload + 16, 4))['value'];
                } elseif ($version === 1 && $payload + 32 <= $atom['end']) {
                    $timescale = unpack('Nvalue', substr($contents, $payload + 20, 4))['value'];
                    $duration = unpack('Jvalue', substr($contents, $payload + 24, 8))['value'];
                } else {
                    return null;
                }

                if ($timescale < 1 || $duration < 1) {
                    return null;
                }

                return $duration / $timescale;
            }

            $offset = $atom['end'];
        }

        return null;
    }
}
