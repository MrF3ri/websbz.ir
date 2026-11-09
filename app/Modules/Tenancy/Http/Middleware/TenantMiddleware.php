<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function __construct(
        private readonly CurrentBusiness $currentBusiness
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        $this->currentBusiness->clear();

        $activeBusinessId = $request->session()->get('active_business_id');
        $business = null;

        if ($activeBusinessId !== null) {
            // The relationship scopes this lookup to businesses the user belongs to.
            $business = $user->businesses()
                ->whereKey($activeBusinessId)
                ->first();

            if ($business === null) {
                $request->session()->forget('active_business_id');
            }
        }

        if ($business === null) {
            $business = $user->businesses()->first();
        }

        if ($business === null) {
            return redirect()->route('onboarding');
        }

        $this->currentBusiness->set($business);

        $request->session()->put('active_business_id', $business->getKey());

        return $next($request);
    }
}
