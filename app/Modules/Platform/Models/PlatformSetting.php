<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 */
class PlatformSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
