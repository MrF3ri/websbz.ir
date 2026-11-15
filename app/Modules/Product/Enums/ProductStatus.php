<?php

namespace App\Modules\Product\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
