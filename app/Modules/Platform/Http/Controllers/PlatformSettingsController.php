<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Models\User;
use App\Modules\Platform\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController
{
    public function edit(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'cardDetails' => $settings->cardDetails(),
        ]);
    }

    public function update(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'card_number' => ['required', 'regex:/^\d{16}$/'],
            'card_holder' => ['required', 'string', 'max:120'],
        ]);

        $settings->updateCardDetails($validated, $user);

        return back()->with('success', 'اطلاعات کارت بانکی ذخیره شد.');
    }
}
