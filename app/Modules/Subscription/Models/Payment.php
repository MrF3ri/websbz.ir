<?php

namespace App\Modules\Subscription\Models;

use App\Models\User;
use App\Modules\Subscription\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $target_plan_id
 * @property int $amount
 * @property PaymentStatus $status
 * @property string|null $gateway_reference
 * @property string|null $bank_tracking_number
 * @property string|null $receipt_path
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $rejection_reason
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'target_plan_id',
        'amount',
        'status',
        'gateway_reference',
        'bank_tracking_number',
        'receipt_path',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'paid_at',
    ];

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Plan, $this> */
    public function targetPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
