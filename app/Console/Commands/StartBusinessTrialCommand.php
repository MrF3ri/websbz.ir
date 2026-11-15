<?php

namespace App\Console\Commands;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Actions\StartTrialAction;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StartBusinessTrialCommand extends Command
{
    protected $signature = 'subscription:start-trial {business-slug} {plan-code}';

    protected $description = 'Start a 72-hour trial for an existing business without a subscription.';

    public function handle(StartTrialAction $action): int
    {
        $businessSlug = (string) $this->argument('business-slug');
        $planCode = (string) $this->argument('plan-code');

        $business = Business::query()->where('slug', $businessSlug)->first();

        if ($business === null) {
            $this->error("Business [{$businessSlug}] was not found.");

            return self::FAILURE;
        }

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            $this->error("Active plan [{$planCode}] was not found.");

            return self::FAILURE;
        }

        $started = DB::transaction(function () use ($business, $plan, $action): bool {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBusiness->subscriptions()->exists()) {
                return false;
            }

            $action->execute($lockedBusiness, $plan);

            return true;
        }, attempts: 3);

        if (! $started) {
            $this->error('This business already has a subscription; no changes were made.');

            return self::FAILURE;
        }

        $this->info("A 72-hour [{$planCode}] trial was started for [{$businessSlug}].");

        return self::SUCCESS;
    }
}
