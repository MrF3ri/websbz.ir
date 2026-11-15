<?php

namespace App\\Console\\Commands;

use App\\Modules\\Subscription\\Actions\\ExpireSubscriptionsAction;
use Illuminate\\Console\\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire trials and paid subscriptions whose current period has ended.';

    public function handle(ExpireSubscriptionsAction $action): int
    {
        $expiredCount = $action->execute();

        $this->info("Expired {$expiredCount} subscription(s).");

        return self::SUCCESS;
    }
}
