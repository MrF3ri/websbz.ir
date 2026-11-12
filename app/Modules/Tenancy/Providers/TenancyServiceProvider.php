<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            CurrentBusiness::class,
            fn (): CurrentBusiness => new CurrentBusiness
        );
    }
}
