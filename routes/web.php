<?php

use App\Modules\Business\Http\Controllers\BusinessOnboardingController;
use App\Modules\Business\Http\Controllers\BusinessSettingsController;
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
    });
});

require __DIR__.'/settings.php';
