<?php

namespace App\Modules\Branch\Http\Controllers;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\SetBranchStatusAction;
use App\Modules\Branch\Actions\UpdateBranchAction;
use App\Modules\Branch\Http\Requests\SaveBranchRequest;
use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController
{
    public function manage(CurrentBusiness $currentBusiness): Response
    {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('view', $business);

        $menu = $business->menu()->first();
        $menuIsPublished = $menu?->status === MenuStatus::Published;

        $branches = $business->branches()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get([
                'public_id',
                'name',
                'slug',
                'status',
                'is_primary',
                'phone',
                'email',
                'address',
                'latitude',
                'longitude',
                'timezone',
                'opening_hours',
            ])
            ->map(function (Branch $branch) use ($business, $menu, $menuIsPublished): Branch {
                $branch->setAttribute(
                    'public_url',
                    $menuIsPublished && $branch->status === 'active' && $menu !== null
                        ? route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug])
                        : null,
                );

                return $branch;
            });

        return Inertia::render('Branches/Index', [
            'canManage' => Gate::allows('update', $business),
            'branches' => $branches,
        ]);
    }

    public function index(CurrentBusiness $currentBusiness): JsonResponse
    {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('view', $business);

        return response()->json([
            'data' => $business->branches()
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get([
                    'public_id',
                    'name',
                    'slug',
                    'status',
                    'is_primary',
                    'phone',
                    'email',
                    'address',
                    'latitude',
                    'longitude',
                    'timezone',
                    'opening_hours',
                ]),
        ]);
    }

    public function store(
        SaveBranchRequest $request,
        CurrentBusiness $currentBusiness,
        CreateBranchAction $createBranch,
    ): RedirectResponse {
        $business = $this->resolveBusiness($currentBusiness);
        $createBranch->execute($business, $request->validated());

        return to_route('business.branches.manage')->with('success', 'شعبه جدید با موفقیت ایجاد شد.');
    }

    public function update(
        SaveBranchRequest $request,
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        UpdateBranchAction $updateBranch,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->resolveBusiness($currentBusiness);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $updatedBranch = $updateBranch->execute($business, $branch, $request->validated());
        $menu = $business->menu()->first();

        if ($menu instanceof Menu) {
            $publicMenuCache->forget($menu, $updatedBranch);
        }

        return to_route('business.branches.manage')->with('success', 'اطلاعات شعبه ذخیره شد.');
    }

    public function activate(
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        SetBranchStatusAction $setBranchStatus,
    ): RedirectResponse {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('update', $business);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $setBranchStatus->execute($business, $branch, true);

        return to_route('business.branches.manage')->with('success', 'شعبه فعال شد.');
    }

    public function deactivate(
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        SetBranchStatusAction $setBranchStatus,
    ): RedirectResponse {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('update', $business);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $setBranchStatus->execute($business, $branch, false);

        return to_route('business.branches.manage')->with('success', 'شعبه غیرفعال شد.');
    }

    private function resolveBusiness(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }

    private function resolveBranch(Business $business, string $publicId): Branch
    {
        return $business->branches()->where('public_id', $publicId)->firstOrFail();
    }
}
