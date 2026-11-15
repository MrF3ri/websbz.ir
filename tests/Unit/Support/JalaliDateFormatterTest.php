<?php

namespace Tests\Unit\Support;

use App\Support\Dates\JalaliDateFormatter;
use PHPUnit\Framework\TestCase;

class JalaliDateFormatterTest extends TestCase
{
    public function test_gregorian_2025_03_20_maps_to_jalali_1403_12_30(): void
    {
        $this->assertSame('1403/12/30', self::latinize(JalaliDateFormatter::formatDate('2025-03-20T00:00:00Z')));
    }

    public function test_gregorian_2025_03_21_maps_to_jalali_1404_01_01(): void
    {
        $this->assertSame('1404/01/01', self::latinize(JalaliDateFormatter::formatDate('2025-03-21T00:00:00Z')));
    }

    public function test_gregorian_2026_03_21_maps_to_jalali_1405_01_01(): void
    {
        $this->assertSame('1405/01/01', self::latinize(JalaliDateFormatter::formatDate('2026-03-21T00:00:00Z')));
    }

    private static function latinize(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
