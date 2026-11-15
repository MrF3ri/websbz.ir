<?php

namespace App\Modules\Business\Http\Controllers;

use App\Actions\Business\CreateBusinessAction;
use App\Models\User;
use App\Modules\Business\Http\Requests\StoreBusinessRequest;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOnboardingController
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->businesses()->exists()) {
            return to_route('dashboard');
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get(['code', 'name', 'monthly_price'])
            ->map(fn (Plan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
            ])
            ->values();

        return Inertia::render('Business/Create', [
            'plans' => $plans,
        ]);
    }

    public function store(
        StoreBusinessRequest $request,
        CreateBusinessAction $action
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->businesses()->exists()) {
            return to_route('dashboard');
        }

        /** @var array{name: string, plan_code: string} $data */
        $data = $request->validated();

        $action->execute($user, $data);

        return to_route('dashboard')->with(
            'success',
            'Business created successfully.'
        );
    }
}
