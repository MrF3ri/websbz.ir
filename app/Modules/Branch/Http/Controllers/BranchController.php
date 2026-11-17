<?php

namespace App\Modules\Branch\Http\Controllers;

use App\Modules\Branch\Actions\CreateBranchAction;
use App\Modules\Branch\Actions\SetBranchStatusAction;
use App\Modules\Branch\Actions\UpdateBranchAction;
use App\Modules\Branch\Http\Requests\SaveBranchRequest;
use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BranchController
{
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
                ]),
        ]);
    }

    public function store(
        SaveBranchRequest $request,
        CurrentBusiness $currentBusiness,
        CreateBranchAction $createBranch,
    ): JsonResponse {
        $business = $this->resolveBusiness($currentBusiness);
        $branch = $createBranch->execute($business, $request->validated());

        return response()->json(['data' => $branch], 201);
    }

    public function update(
        SaveBranchRequest $request,
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        UpdateBranchAction $updateBranch,
    ): JsonResponse {
        $business = $this->resolveBusiness($currentBusiness);
        $branch = $this->resolveBranch($business, $branchPublicId);
        $updated = $updateBranch->execute($business, $branch, $request->validated());

        return response()->json(['data' => $updated]);
    }

    public function activate(
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        SetBranchStatusAction $setBranchStatus,
    ): JsonResponse {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('update', $business);
        $branch = $this->resolveBranch($business, $branchPublicId);

        return response()->json([
            'data' => $setBranchStatus->execute($business, $branch, true),
        ]);
    }

    public function deactivate(
        string $branchPublicId,
        CurrentBusiness $currentBusiness,
        SetBranchStatusAction $setBranchStatus,
    ): JsonResponse {
        $business = $this->resolveBusiness($currentBusiness);
        Gate::authorize('update', $business);
        $branch = $this->resolveBranch($business, $branchPublicId);

        return response()->json([
            'data' => $setBranchStatus->execute($business, $branch, false),
        ]);
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
