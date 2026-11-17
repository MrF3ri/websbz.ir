<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use Illuminate\Support\Facades\DB;

class UpdateBranchAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($business, $branch, $data): Branch {
            Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBranch = $business->branches()
                ->whereKey($branch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $attributes = [
                'name' => $data['name'],
                'slug' => $data['slug'],
            ];

            foreach (['phone', 'email', 'address', 'latitude', 'longitude', 'timezone', 'opening_hours'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            $lockedBranch->update($attributes);

            return $lockedBranch->refresh();
        });
    }
}
