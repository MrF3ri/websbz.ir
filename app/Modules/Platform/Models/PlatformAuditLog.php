<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property array<string, mixed>|null $before_data
 * @property array<string, mixed>|null $after_data
 */
class PlatformAuditLog extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Platform audit records are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Platform audit records are immutable.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
