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
        'density' => 'comfortable',
        'card_style' => 'standard',
        'heading_size' => 'normal',
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

        $templates = $registry->availableFor($business, $entitlements);
        $theme = array_merge(self::DEFAULT_THEME, array_intersect_key($menu->theme ?? [], self::DEFAULT_THEME));
        $theme['density'] = in_array($theme['density'], ['compact', 'comfortable', 'spacious'], true) ? $theme['density'] : self::DEFAULT_THEME['density'];
        $theme['card_style'] = in_array($theme['card_style'], ['standard', 'flat', 'elevated'], true) ? $theme['card_style'] : self::DEFAULT_THEME['card_style'];
        $theme['heading_size'] = in_array($theme['heading_size'], ['normal', 'large', 'display'], true) ? $theme['heading_size'] : self::DEFAULT_THEME['heading_size'];

        if (! isset($templates[$theme['template']])) {
            $templates[$theme['template']] = [
                'name' => 'قالب فعلی',
                'description' => 'این قالب از قبل انتخاب شده و قابل انتخاب مجدد نیست.',
                'layout' => $theme['template'],
            ];
        }

        return Inertia::render('Menu/Theme', [
            'menu' => [
                'theme' => $theme,
                'advertising_banner' => $menu->advertising_banner,
            ],
            'templates' => $templates,
            'features' => [
                'custom_colors' => $entitlements->hasFeature('custom_colors'),
                'font_selection' => $entitlements->hasFeature('font_selection'),
                'dark_mode' => $entitlements->hasFeature('dark_mode'),
                'custom_design' => $entitlements->hasFeature('custom_design'),
                'advertising_banner' => $entitlements->hasFeature('advertising_banner'),
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
            'density' => ['sometimes', 'in:compact,comfortable,spacious'],
            'card_style' => ['sometimes', 'in:standard,flat,elevated'],
            'heading_size' => ['sometimes', 'in:normal,large,display'],
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
            $currentTemplate = $menu->theme['template'] ?? self::DEFAULT_THEME['template'];

            if ($validated['template'] !== $currentTemplate) {
                $availableTemplates = $registry->availableFor($lockedBusiness, $entitlements);
                abort_unless(isset($availableTemplates[$validated['template']]), 403);
            }

            $theme = array_merge(self::DEFAULT_THEME, $menu->theme ?? []);
            $theme['template'] = $validated['template'];

            $primaryColor = strtoupper($validated['primary_color']);
            $currentPrimaryColor = strtoupper($theme['primary_color']);

            if ($primaryColor !== $currentPrimaryColor && $primaryColor !== self::DEFAULT_THEME['primary_color']) {
                abort_unless($entitlements->hasFeature('custom_colors'), 403);
            }

            $theme['primary_color'] = $primaryColor;

            if ($validated['font'] !== $theme['font'] && $validated['font'] !== self::DEFAULT_THEME['font']) {
                abort_unless($entitlements->hasFeature('font_selection'), 403);
            }

            $theme['font'] = $validated['font'];

            if ($validated['mode'] !== $theme['mode'] && $validated['mode'] !== self::DEFAULT_THEME['mode']) {
                abort_unless($entitlements->hasFeature('dark_mode'), 403);
            }

            $theme['mode'] = $validated['mode'];

            foreach (['density', 'card_style', 'heading_size'] as $field) {
                $value = $validated[$field] ?? $theme[$field];

                if ($value !== $theme[$field] && $value !== self::DEFAULT_THEME[$field]) {
                    abort_unless($entitlements->hasFeature('custom_design'), 403);
                }

                $theme[$field] = $value;
            }

            $menu->update(['theme' => $theme]);
        });

        $menu = $business->menu()->firstOrFail();
        $publicMenuCache->forget($menu);

        return back()->with('success', 'قالب و ظاهر منو ذخیره شد.');
    }
}
