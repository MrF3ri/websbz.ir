<?php

namespace App\Modules\Product\Enums;

enum ProductLabel: string
{
    case New = 'new';
    case Popular = 'popular';
    case Recommended = 'recommended';
    case ChefSpecial = 'chef_special';

    public function title(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Popular => 'پرفروش',
            self::Recommended => 'پیشنهاد ویژه',
            self::ChefSpecial => 'پیشنهاد سرآشپز',
        };
    }
}
