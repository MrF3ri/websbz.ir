<?php

namespace Tests\\Unit\\Media;

use App\\Modules\\Media\\Services\\Mp4DurationReader;
use PHPUnit\\Framework\\TestCase;
use RuntimeException;

class Mp4DurationReaderTest extends TestCase
{
    public function test_reads_version_zero_movie_header_duration(): void
    {
        $mvhdPayload = pack('N', 0)
            .pack('N', 0)
            .pack('N', 0)
            .pack('N', 1000)
            .pack('N', 4750)
            .str_repeat("\\0", 80);
        $mvhd = pack('N', strlen($mvhdPayload) + 8).'mvhd'.$mvhdPayload;
        $moov = pack('N', strlen($mvhd) + 8).'moov'.$mvhd;
        $ftypPayload = 'isom'.pack('N', 0x200).'isomiso2';
        $ftyp = pack('N', strlen($ftypPayload) + 8).'ftyp'.$ftypPayload;
        $path = tempnam(sys_get_temp_dir(), 'mp4-duration-');

        self::assertNotFalse($path);

        try {
            file_put_contents($path, $ftyp.$moov);
            self::assertSame(4750, (new Mp4DurationReader)->milliseconds($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_mp4_without_movie_duration_metadata(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4-duration-');

        self::assertNotFalse($path);

        try {
            file_put_contents($path, pack('N', 16).'ftyp'.'isom'.pack('N', 0));
            $this->expectException(RuntimeException::class);
            (new Mp4DurationReader)->milliseconds($path);
        } finally {
            @unlink($path);
        }
    }
}
