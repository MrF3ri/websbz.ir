<?php

namespace App\Modules\Media\Services;

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

        $size = $this->unsignedInt32($contents, $offset);

        if ($size === null) {
            return null;
        }
        $type = substr($contents, $offset + 4, 4);
        $headerSize = 8;

        if ($size === 1) {
            if ($offset + 16 > $limit) {
                return null;
            }

            $size = $this->unsignedInt64($contents, $offset + 8);

            if ($size === null) {
                return null;
            }
            $headerSize = 16;
        } elseif ($size === 0) {
            $size = $limit - $offset;
        }

        if ($size < $headerSize || $size > $limit - $offset) {
            return null;
        }

        return [
            'type' => $type,
            'payload_offset' => $offset + $headerSize,
            'end' => $offset + $size,
        ];
    }

    private function unsignedInt32(string $contents, int $offset): ?int
    {
        $unpacked = unpack('Nvalue', substr($contents, $offset, 4));

        if (! is_array($unpacked) || ! isset($unpacked['value'])) {
            return null;
        }

        return $unpacked['value'];
    }

    private function unsignedInt64(string $contents, int $offset): ?int
    {
        $unpacked = unpack('Jvalue', substr($contents, $offset, 8));

        if (! is_array($unpacked) || ! isset($unpacked['value'])) {
            return null;
        }

        return $unpacked['value'];
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
                    $timescale = $this->unsignedInt32($contents, $payload + 12);
                    $duration = $this->unsignedInt32($contents, $payload + 16);
                } elseif ($version === 1 && $payload + 32 <= $atom['end']) {
                    $timescale = $this->unsignedInt32($contents, $payload + 20);
                    $duration = $this->unsignedInt64($contents, $payload + 24);
                } else {
                    return null;
                }

                if ($timescale === null || $duration === null || $timescale < 1 || $duration < 1) {
                    return null;
                }

                return $duration / $timescale;
            }

            $offset = $atom['end'];
        }

        return null;
    }
}
