<?php

namespace App\Modules\Branch\Actions;

use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetBranchStatusAction
{
    public function __construct(
        private readonly SyncBranchCatalogAssignmentsAction $catalogAssignments,
    ) {}

    public function execute(Business $business, Branch $branch, bool $active): Branch
    {
        return DB::transaction(function () use ($business, $branch, $active): Branch {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBranch = $lockedBusiness->branches()
                ->whereKey($branch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $active && $lockedBranch->is_primary) {
                throw ValidationException::withMessages([
                    'branch' => 'شعبه اصلی قابل غیرفعال‌سازی نیست.',
                ]);
            }

            if (! $active && $lockedBusiness->branches()->where('status', 'active')->count() <= 1) {
                throw ValidationException::withMessages([
                    'branch' => 'حداقل یک شعبه فعال باید باقی بماند.',
                ]);
            }

            $lockedBranch->update(['status' => $active ? 'active' : 'inactive']);

            if ($active) {
                $this->catalogAssignments->initialize($lockedBranch);
            }

            return $lockedBranch->refresh();
        });
    }
}
