<?php

namespace App\Modules\Menu\Enums;

enum MenuStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
