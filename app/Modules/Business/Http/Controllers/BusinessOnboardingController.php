<?php

namespace App\Modules\Business\Http\Controllers;

use App\Actions\Business\CreateBusinessAction;
use App\Models\User;
use App\Modules\Business\Http\Requests\StoreBusinessRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOnboardingController
{
    public function create(): Response
    {
        return Inertia::render('Business/Create');
    }

    public function store(
        StoreBusinessRequest $request,
        CreateBusinessAction $action
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $action->execute($user, $request->validated());

        return to_route('dashboard')->with(
            'success',
            'Business created successfully.'
        );
    }
}
