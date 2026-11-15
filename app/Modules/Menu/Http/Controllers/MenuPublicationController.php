<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Category\Models\Category;
use App\Modules\Menu\Actions\EnsurePrimaryMenuAction;
use App\Modules\Menu\Actions\PublishMenuAction;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Product\Models\Product;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MenuPublicationController
{
    public function publish(
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
        PublishMenuAction $publishMenu,
    ): RedirectResponse {
        Gate::authorize('viewAny', Category::class);
        Gate::authorize('viewAny', Product::class);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        $menu = $ensurePrimaryMenu->execute($business);
        $publishMenu->execute($menu);

        return back()->with('success', 'منوی کسب‌وکار با موفقیت منتشر شد.');
    }

    public function unpublish(
        CurrentBusiness $currentBusiness,
        EnsurePrimaryMenuAction $ensurePrimaryMenu,
    ): RedirectResponse {
        Gate::authorize('viewAny', Category::class);

        $business = $currentBusiness->get();
        abort_unless($business !== null, 404);

        $menu = $ensurePrimaryMenu->execute($business);
        $menu->update(['status' => MenuStatus::Draft]);

        return back()->with('success', 'انتشار منو متوقف شد.');
    }
}
