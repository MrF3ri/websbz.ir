<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Subscription\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $amount
 * @property PaymentStatus $status
 * @property string|null $gateway_reference
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'amount',
        'status',
        'gateway_reference',
        'paid_at',
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
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
