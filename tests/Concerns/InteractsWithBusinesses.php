<?php

namespace Tests\Concerns;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Models\BusinessMember;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use Database\Factories\BusinessMemberFactory;
use Database\Seeders\PlanSeeder;

trait InteractsWithBusinesses
{
    protected function createBusinessFor(
        User $user,
        BusinessRole $role = BusinessRole::Owner,
        array $attributes = [],
    ): Business {
        $business = Business::factory()->create($attributes);

        $this->attachUserWithBusinessRole($business, $user, $role);

        return $business;
    }

    protected function attachUserWithBusinessRole(
        Business $business,
        User $user,
        BusinessRole $role,
    ): BusinessMember {
        return BusinessMemberFactory::new()->create([
            'business_id' => $business->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);
    }

    protected function giveBusinessTrialSubscription(
        Business $business,
        string $planCode,
    ): Subscription {
        $this->seed(PlanSeeder::class);

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        return app(StartTrialAction::class)->execute($business, $plan);
    }
}
