<?php

use App\Modules\Business\Http\Controllers\BusinessOnboardingController;
use App\Modules\Business\Http\Controllers\BusinessSettingsController;
use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Category\Http\Controllers\CategoryController;
use App\Modules\Product\Http\Controllers\ProductController;
use App\Modules\Tenancy\Http\Middleware\TenantMiddleware;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Onboarding must remain outside TenantMiddleware to avoid redirect loops.
    Route::get('/onboarding', [BusinessOnboardingController::class, 'create'])
        ->name('onboarding');

    Route::post('/onboarding', [BusinessOnboardingController::class, 'store'])
        ->name('onboarding.store');

    Route::middleware([TenantMiddleware::class])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::get('/business/settings', [BusinessSettingsController::class, 'edit'])
            ->name('business.settings.edit');

        Route::put('/business/settings', [BusinessSettingsController::class, 'update'])
            ->name('business.settings.update');

        Route::get('/catalog', [CatalogController::class, 'index'])
            ->name('catalog.index');

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
