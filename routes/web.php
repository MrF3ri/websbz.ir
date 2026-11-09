<?php

use App\Modules\Business\Http\Controllers\BusinessOnboardingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('/onboarding', [BusinessOnboardingController::class, 'create'])
        ->name('onboarding');

    Route::post('/onboarding', [BusinessOnboardingController::class, 'store'])
        ->name('onboarding.store');
});

require __DIR__.'/settings.php';
