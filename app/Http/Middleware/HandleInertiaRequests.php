<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'entitlements' => fn (): ?array => $this->resolveEntitlements($request),
        ];
    }

    /**
     * Resolve only the safe entitlement contract for the current authenticated tenant.
     *
     * @return array{plan_code: string, status: string, trial_ends_at: string|null, features: array<string, bool|int|float>}|null
     */
    private function resolveEntitlements(Request $request): ?array
    {
        if (! $request->user() instanceof User) {
            return null;
        }

        $business = app(CurrentBusiness::class)->get();

        if (! $business instanceof Business) {
            return null;
        }

        $subscription = $business->subscriptions()
            ->with('plan')
            ->latest('id')
            ->first();

        if (! $subscription instanceof Subscription || $subscription->plan === null) {
            return null;
        }

        $features = [];

        foreach ($subscription->features_snapshot ?? [] as $key => $value) {
            $featureKey = (string) $key;

            if (preg_match('/(?:^|_)(?:id|hash)(?:$|_)/i', $featureKey) === 1) {
                continue;
            }

            if (is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))) {
                $features[$featureKey] = $value;
            }
        }

        return [
            'plan_code' => $subscription->plan->code,
            'status' => $subscription->status->value,
            'trial_ends_at' => $subscription->trial_ends_at?->utc()->toISOString(),
            'features' => $features,
        ];
    }
}
