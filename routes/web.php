<?php

use App\Modules\Branch\Http\Controllers\BranchCatalogController;
use App\Modules\Branch\Http\Controllers\BranchController;
use App\Modules\Business\Http\Controllers\BusinessLogoController;
use App\Modules\Business\Http\Controllers\BusinessOnboardingController;
use App\Modules\Business\Http\Controllers\BusinessSettingsController;
use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Category\Http\Controllers\CategoryController;
use App\Modules\Media\Http\Controllers\MediaController;
use App\Modules\Menu\Http\Controllers\MenuBannerController;
use App\Modules\Menu\Http\Controllers\MenuPublicationController;
use App\Modules\Menu\Http\Controllers\MenuThemeController;
use App\Modules\Menu\Http\Controllers\MenuTranslationsController;
use App\Modules\Platform\Http\Controllers\AdminAuditLogController;
use App\Modules\Platform\Http\Controllers\AdminBusinessController;
use App\Modules\Platform\Http\Controllers\AdminDashboardController;
use App\Modules\Platform\Http\Controllers\AdminEventController;
use App\Modules\Platform\Http\Controllers\AdminPaymentController;
use App\Modules\Platform\Http\Controllers\AdminSubscriptionController;
use App\Modules\Platform\Http\Controllers\PlatformSettingsController;
use App\Modules\Product\Http\Controllers\ProductController;
use App\Modules\PublicMenu\Http\Controllers\PublicMenuController;
use App\Modules\Subscription\Http\Controllers\BillingController;
use App\Modules\Subscription\Http\Controllers\ManualPaymentController;
use App\Modules\Tenancy\Http\Middleware\TenantMiddleware;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/media/{mediaPublicId}', [MediaController::class, 'publicImage'])->name('media.public');
Route::get('/media/business-logo/{businessPublicId}/{mediaPublicId}', [BusinessLogoController::class, 'public'])->name('media.business-logo');

Route::get('/menu/{businessSlug}/{menuSlug}/{branchSlug}', [PublicMenuController::class, 'showBranch'])
    ->name('public.menu.branch.show');
Route::get('/menu/{businessSlug}/{menuSlug}', [PublicMenuController::class, 'showPrimary'])
    ->name('public.menu.show');

Route::middleware(['auth', 'verified', 'can:platform-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/businesses', [AdminBusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/{businessPublicId}', [AdminBusinessController::class, 'show'])
            ->name('businesses.show');
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [AdminPaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
        Route::get('/settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [BusinessOnboardingController::class, 'create'])->name('onboarding');
    Route::post('/onboarding', [BusinessOnboardingController::class, 'store'])->name('onboarding.store');

    Route::middleware([TenantMiddleware::class])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::get('/catalog/menu/theme', [MenuThemeController::class, 'edit'])->name('catalog.menu.theme.edit');
        Route::put('/catalog/menu/theme', [MenuThemeController::class, 'update'])->name('catalog.menu.theme.update');
        Route::get('/catalog/menu/translations/{locale}', [MenuTranslationsController::class, 'edit'])->name('catalog.menu.translations.edit');
        Route::put('/catalog/menu/translations/{locale}', [MenuTranslationsController::class, 'update'])->name('catalog.menu.translations.update');
        Route::put('/catalog/menu/banner', [MenuBannerController::class, 'update'])->name('catalog.menu.banner.update');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/payments', [ManualPaymentController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('billing.payments.store');
        Route::get('/billing/payments/{payment}/receipt', [ManualPaymentController::class, 'receipt'])
            ->name('billing.payments.receipt');

        Route::get('/business/settings', [BusinessSettingsController::class, 'edit'])->name('business.settings.edit');
        Route::put('/business/settings', [BusinessSettingsController::class, 'update'])->name('business.settings.update');
        Route::post('/business/logo', [BusinessLogoController::class, 'store'])->middleware('throttle:5,1')->name('business.logo.store');
        Route::delete('/business/logo', [BusinessLogoController::class, 'destroy'])->name('business.logo.destroy');

        Route::get('/business/branches', [BranchController::class, 'index'])->name('business.branches.index');
        Route::get('/business/branches/manage', [BranchController::class, 'manage'])->name('business.branches.manage');
        Route::get('/business/branches/{branchPublicId}/catalog', [BranchCatalogController::class, 'show'])->name('business.branches.catalog');
        Route::put('/business/branches/{branchPublicId}/catalog', [BranchCatalogController::class, 'update'])->name('business.branches.catalog.update');
        Route::post('/business/branches', [BranchController::class, 'store'])->name('business.branches.store');
        Route::put('/business/branches/{branchPublicId}', [BranchController::class, 'update'])->name('business.branches.update');
        Route::post('/business/branches/{branchPublicId}/activate', [BranchController::class, 'activate'])->name('business.branches.activate');
        Route::post('/business/branches/{branchPublicId}/deactivate', [BranchController::class, 'deactivate'])->name('business.branches.deactivate');

        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::put('/catalog/menu/publish', [MenuPublicationController::class, 'publish'])->name('catalog.menu.publish');
        Route::put('/catalog/menu/unpublish', [MenuPublicationController::class, 'unpublish'])->name('catalog.menu.unpublish');
        Route::post('/catalog/categories', [CategoryController::class, 'store'])->name('catalog.categories.store');
        Route::put('/catalog/categories/reorder', [CategoryController::class, 'reorder'])->name('catalog.categories.reorder');
        Route::put('/catalog/categories/{categoryPublicId}', [CategoryController::class, 'update'])->name('catalog.categories.update');
        Route::delete('/catalog/categories/{categoryPublicId}', [CategoryController::class, 'destroy'])->name('catalog.categories.destroy');
        Route::post('/catalog/products', [ProductController::class, 'store'])->name('catalog.products.store');
        Route::post('/catalog/products/{productPublicId}/images', [MediaController::class, 'store'])->name('catalog.products.images.store');
        Route::delete('/catalog/products/{productPublicId}/images/{mediaPublicId}', [MediaController::class, 'destroy'])->name('catalog.products.images.destroy');
        Route::put('/catalog/products/{productPublicId}/images/reorder', [MediaController::class, 'reorder'])->name('catalog.products.images.reorder');
        Route::put('/catalog/categories/{categoryPublicId}/products/reorder', [ProductController::class, 'reorder'])->name('catalog.products.reorder');
        Route::put('/catalog/products/{productPublicId}', [ProductController::class, 'update'])->name('catalog.products.update');
        Route::delete('/catalog/products/{productPublicId}', [ProductController::class, 'destroy'])->name('catalog.products.destroy');
    });
});

require __DIR__.'/settings.php';
