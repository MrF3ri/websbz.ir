<?php

namespace App\Support\Dates;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use RuntimeException;

class JalaliDateFormatter
{
    public static function formatDate(DateTimeInterface|string $value): string
    {
        return self::format($value, 'yyyy/MM/dd');
    }

    public static function formatDateTime(DateTimeInterface|string $value): string
    {
        return self::format($value, 'yyyy/MM/dd HH:mm');
    }

    private static function format(DateTimeInterface|string $value, string $pattern): string
    {
        $date = is_string($value)
            ? new DateTimeImmutable($value, new DateTimeZone('UTC'))
            : DateTimeImmutable::createFromInterface($value);

        $formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        $formatted = $formatter->format($date);

        if ($formatted === false) {
            throw new RuntimeException('Unable to format the date using the Persian calendar.');
        }

        return $formatted;
    }
}
