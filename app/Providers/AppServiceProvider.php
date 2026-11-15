<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Policies\BusinessPolicy;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Policies\CategoryPolicy;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Policies\ProductPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Gate::define('platform-admin', fn (User $user): bool => $user->isPlatformAdmin());
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
