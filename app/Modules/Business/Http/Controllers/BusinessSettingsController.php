<?php

namespace App\Modules\Business\Http\Controllers;

use App\Modules\Business\Http\Requests\UpdateBusinessSettingsRequest;
use App\Modules\Business\Models\Business;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController
{
    public function __construct(
        private readonly CurrentBusiness $currentBusiness,
        private readonly PublicMenuCache $publicMenuCache,
    ) {}

    public function edit(): Response
    {
        $business = $this->resolveCurrentBusiness();

        Gate::authorize('view', $business);

        $logo = $business->logo()->with('media')->first();

        return Inertia::render('Business/Settings', [
            'business' => array_merge($business->only([
                'name',
                'phone',
                'email',
                'address',
                'city',
                'postal_code',
            ]), [
                'logo_url' => $logo ? route('media.business-logo', [$business->public_id, $logo->media->public_id]) : null,
            ]),
            'features' => [
                'business_logo' => (new EntitlementService($business))->hasFeature('business_logo'),
                'can_manage_logo' => Gate::allows('update', $business),
            ],
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        // Update authorization is owned by the Form Request; do not duplicate the Policy check.
        $business = $this->resolveCurrentBusiness();
        $business->update($request->validated());
        $menu = $business->menu()->first();

        if ($menu !== null) {
            $this->publicMenuCache->forget($menu);
        }

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
