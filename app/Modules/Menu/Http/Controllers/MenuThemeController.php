<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Models\Menu;
use App\Modules\Menu\Services\MenuThemeRegistry;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MenuThemeController
{
    private const DEFAULT_THEME = [
        'template' => 'classic',
        'primary_color' => '#365B4A',
        'font' => 'system',
        'mode' => 'light',
    ];

    public function edit(
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        MenuThemeRegistry $registry,
    ): Response {
        Gate::authorize('viewAny', Category::class);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        $menu = $ensurePrimaryMenu->execute($business);
        $entitlements = new EntitlementService($business);

        return Inertia::render('Menu/Theme', [
            'menu' => [
                'theme' => array_merge(self::DEFAULT_THEME, $menu->theme ?? []),
            ],
            'templates' => $registry->availableFor($business, $entitlements),
            'features' => [
                'custom_colors' => $entitlements->hasFeature('custom_colors'),
                'font_selection' => $entitlements->hasFeature('font_selection'),
                'dark_mode' => $entitlements->hasFeature('dark_mode'),
            ],
        ]);
    }

    public function update(
        Request $request,
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        MenuThemeRegistry $registry,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        Gate::authorize('viewAny', Category::class);

        $validated = $request->validate([
            'template' => ['required', 'string', 'max:40'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font' => ['required', 'in:system,serif,rounded'],
            'mode' => ['required', 'in:light,dark'],
        ]);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        DB::transaction(function () use ($business, $ensurePrimaryMenu, $registry, $validated): void {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ensurePrimaryMenu->execute($lockedBusiness);

            $menu = Menu::query()
                ->where('business_id', $lockedBusiness->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $entitlements = new EntitlementService($lockedBusiness);
            $registry->findAvailable($lockedBusiness, $entitlements, $validated['template']);

            $theme = self::DEFAULT_THEME;
            $theme['template'] = $validated['template'];

            if ($validated['primary_color'] !== self::DEFAULT_THEME['primary_color']) {
                abort_unless($entitlements->hasFeature('custom_colors'), 403);
                $theme['primary_color'] = strtoupper($validated['primary_color']);
            }

            if ($validated['font'] !== self::DEFAULT_THEME['font']) {
                abort_unless($entitlements->hasFeature('font_selection'), 403);
                $theme['font'] = $validated['font'];
            }

            if ($validated['mode'] !== self::DEFAULT_THEME['mode']) {
                abort_unless($entitlements->hasFeature('dark_mode'), 403);
                $theme['mode'] = $validated['mode'];
            }

            $menu->update(['theme' => $theme]);
        });

        $menu = $business->menu()->firstOrFail();
        $publicMenuCache->forget($menu);

        return back()->with('success', 'قالب و ظاهر منو ذخیره شد.');
    }
}
