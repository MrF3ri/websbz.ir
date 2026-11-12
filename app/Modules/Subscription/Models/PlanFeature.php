<?php

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property bool|int|float|string|null $value
 */
class PlanFeature extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'plan_id',
        'feature_key',
        'value',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
