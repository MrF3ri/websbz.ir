<?php

namespace App\Modules\Business\Http\Controllers;

use App\Modules\Business\Http\Requests\UpdateBusinessSettingsRequest;
use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController
{
    public function __construct(
        private readonly CurrentBusiness $currentBusiness
    ) {}

    public function edit(): Response
    {
        $business = $this->resolveCurrentBusiness();

        Gate::authorize('view', $business);

        return Inertia::render('Business/Settings', [
            'business' => $business->only([
                'name',
                'phone',
                'email',
                'address',
                'city',
                'postal_code',
            ]),
        ]);
    }

    public function update(
        UpdateBusinessSettingsRequest $request
    ): RedirectResponse {
        $business = $this->resolveCurrentBusiness();

        Gate::authorize('update', $business);

        $business->update($request->validated());

        return to_route('business.settings.edit')->with(
            'success',
            'Business settings updated successfully.'
        );
    }

    private function resolveCurrentBusiness(): Business
    {
        $business = $this->currentBusiness->get();

        abort_unless($business instanceof Business, 404);

        return $business;
    }
}
