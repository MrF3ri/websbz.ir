<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Subscription\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property SubscriptionEventType $event_type
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class SubscriptionEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => SubscriptionEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
