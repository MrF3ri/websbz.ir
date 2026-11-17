<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

class CreateBranchAction
{
    public function __construct(
        private readonly SyncBranchCatalogAssignmentsAction $catalogAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, array $data): Branch
    {
        return DB::transaction(function () use ($business, $data): Branch {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentCount = $lockedBusiness->branches()->count();

            (new EnforcementService(new EntitlementService($lockedBusiness)))
                ->requireLimit($lockedBusiness, 'max_branches', $currentCount);

            $branch = $lockedBusiness->branches()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => 'active',
                'is_primary' => false,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Tehran',
            ]);

            $this->catalogAssignments->initialize($branch);

            return $branch;
        });
    }
}
