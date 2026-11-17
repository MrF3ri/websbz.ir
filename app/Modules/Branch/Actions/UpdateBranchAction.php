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

            $lockedBranch->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Tehran',
            ]);

            return $lockedBranch->refresh();
        });
    }
}
