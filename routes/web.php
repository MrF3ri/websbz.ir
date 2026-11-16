<?php

use App\\Modules\\Business\\Http\\Controllers\\BusinessOnboardingController;
use App\\Modules\\Business\\Http\\Controllers\\BusinessSettingsController;
use App\\Modules\\Catalog\\Http\\Controllers\\CatalogController;
use App\\Modules\\Category\\Http\\Controllers\\CategoryController;
use App\\Modules\\Menu\\Http\\Controllers\\MenuPublicationController;
use App\\Modules\\Platform\\Http\\Controllers\\AdminPaymentController;
use App\\Modules\\Platform\\Http\\Controllers\\PlatformSettingsController;
use App\\Modules\\Product\\Http\\Controllers\\ProductController;
use App\\Modules\\PublicMenu\\Http\\Controllers\\PublicMenuController;
use App\\Modules\\Subscription\\Http\\Controllers\\BillingController;
use App\\Modules\\Subscription\\Http\\Controllers\\ManualPaymentController;
use App\\Modules\\Tenancy\\Http\\Middleware\\TenantMiddleware;
use Illuminate\\Support\\Facades\\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/menu/{businessSlug}/{menuSlug}', [PublicMenuController::class, 'show'])
    ->name('public.menu.show');

Route::middleware(['auth', 'verified', 'can:platform-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}/receipt', [AdminPaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
        Route::get('/settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    // Onboarding must remain outside TenantMiddleware to avoid redirect loops.
    Route::get('/onboarding', [BusinessOnboardingController::class, 'create'])
        ->name('onboarding');

    Route::post('/onboarding', [BusinessOnboardingController::class, 'store'])
        ->name('onboarding.store');

    Route::middleware([TenantMiddleware::class])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/payments', [ManualPaymentController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('billing.payments.store');
        Route::get('/billing/payments/{payment}/receipt', [ManualPaymentController::class, 'receipt'])
            ->name('billing.payments.receipt');

        Route::get('/business/settings', [BusinessSettingsController::class, 'edit'])
            ->name('business.settings.edit');

        Route::put('/business/settings', [BusinessSettingsController::class, 'update'])
            ->name('business.settings.update');

        Route::get('/catalog', [CatalogController::class, 'index'])
            ->name('catalog.index');

        Route::put('/catalog/menu/publish', [MenuPublicationController::class, 'publish'])
            ->name('catalog.menu.publish');
        Route::put('/catalog/menu/unpublish', [MenuPublicationController::class, 'unpublish'])
            ->name('catalog.menu.unpublish');

        Route::post('/catalog/categories', [CategoryController::class, 'store'])
            ->name('catalog.categories.store');
        Route::put('/catalog/categories/reorder', [CategoryController::class, 'reorder'])
            ->name('catalog.categories.reorder');
        Route::put('/catalog/categories/{categoryPublicId}', [CategoryController::class, 'update'])
            ->name('catalog.categories.update');
        Route::delete('/catalog/categories/{categoryPublicId}', [CategoryController::class, 'destroy'])
            ->name('catalog.categories.destroy');

        Route::post('/catalog/products', [ProductController::class, 'store'])
            ->name('catalog.products.store');
        Route::put('/catalog/categories/{categoryPublicId}/products/reorder', [ProductController::class, 'reorder'])
            ->name('catalog.products.reorder');
        Route::put('/catalog/products/{productPublicId}', [ProductController::class, 'update'])
            ->name('catalog.products.update');
        Route::delete('/catalog/products/{productPublicId}', [ProductController::class, 'destroy'])
            ->name('catalog.products.destroy');
    });
});

require __DIR__.'/settings.php';
