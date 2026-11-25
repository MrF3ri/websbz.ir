<?php

namespace App\Modules\Media\Models;

use App\Modules\Business\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**\n * @property int $id\n * @property int $business_id\n * @property int $media_id\n */\nclass BusinessLogo extends Model
{
    protected $fillable = [
        'business_id',
        'media_id',
    ];

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
