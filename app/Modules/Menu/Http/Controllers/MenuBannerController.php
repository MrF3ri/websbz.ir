<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MenuBannerController
{
    /** @var list<string> */
    private const FIELDS = ['title', 'message', 'cta_label', 'cta_url'];

    public function update(
        Request $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        Gate::authorize('viewAny', Category::class);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:300'],
            'cta_label' => ['nullable', 'string', 'max:40', 'required_with:cta_url'],
            'cta_url' => ['nullable', 'url', 'max:2048', 'starts_with:https://', 'required_with:cta_label'],
        ]);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        DB::transaction(function () use ($business, $ensurePrimaryMenu, $validated): void {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ensurePrimaryMenu->execute($lockedBusiness);

            $menu = Menu::query()
                ->where('business_id', $lockedBusiness->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $banner = [];

            foreach (self::FIELDS as $field) {
                $value = $validated[$field] ?? null;
                $banner[$field] = is_string($value) && trim($value) !== ''
                    ? trim($value)
                    : null;
            }

            $current = [];

            foreach (self::FIELDS as $field) {
                $value = $menu->advertising_banner[$field] ?? null;
                $current[$field] = is_string($value) && trim($value) !== ''
                    ? trim($value)
                    : null;
            }

            $hasContent = count(array_filter($banner, fn ($value): bool => $value !== null)) > 0;

            if ($hasContent && $banner !== $current) {
                $entitlements = new EntitlementService($lockedBusiness);
                (new EnforcementService($entitlements))->requireFeature($lockedBusiness, 'advertising_banner');
            }

            $menu->update([
                'advertising_banner' => $hasContent ? $banner : null,
            ]);
        });

        $menu = $business->menu()->firstOrFail();
        $publicMenuCache->forget($menu);

        return back()->with('success', 'بنر تبلیغاتی منو ذخیره شد.');
    }
}
