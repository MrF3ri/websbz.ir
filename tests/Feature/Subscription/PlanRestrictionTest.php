<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\EnforcementService;
use App\Modules\Subscription\Services\EntitlementService;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class PlanRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_economic_cannot_create_product_twenty_one(): void
    {
        $business = $this->createBusinessWithPlan('economic');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_products', 20));
    }

    public function test_economic_cannot_create_category_three(): void
    {
        $business = $this->createBusinessWithPlan('economic');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_categories', 2));
    }

    public function test_economic_cannot_upload_product_images(): void
    {
        $business = $this->createBusinessWithPlan('economic');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_product_images', 0));
    }

    public function test_professional_cannot_upload_a_second_image_for_one_product(): void
    {
        $business = $this->createBusinessWithPlan('professional');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_product_images', 1));
    }

    public function test_top_cannot_upload_a_sixth_image_for_one_product(): void
    {
        $business = $this->createBusinessWithPlan('top');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_product_images', 5));
    }

    public function test_professional_cannot_create_a_second_branch(): void
    {
        $business = $this->createBusinessWithPlan('professional');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_branches', 1));
    }

    public function test_top_cannot_create_a_third_branch(): void
    {
        $business = $this->createBusinessWithPlan('top');

        $this->assertFalse($this->enforcementFor($business)->canCreate($business, 'max_branches', 2));
    }

    public function test_require_feature_throws_when_feature_is_not_enabled(): void
    {
        $business = $this->createBusinessWithPlan('economic');

        $this->expectException(AccessDeniedHttpException::class);

        $this->enforcementFor($business)->requireFeature($business, 'custom_domain');
    }

    public function test_require_limit_throws_when_limit_is_reached(): void
    {
        $business = $this->createBusinessWithPlan('economic');

        $this->expectException(AccessDeniedHttpException::class);

        $this->enforcementFor($business)->requireLimit($business, 'max_products', 20);
    }

    public function test_enforcement_service_throws_403_in_http_context(): void
    {
        $business = $this->createBusinessWithPlan('economic');
        $enforcement = $this->enforcementFor($business);

        // This temporary route verifies Laravel's HTTP exception rendering only.
        // Consumer-module integration must be tested against real application routes.
        Route::post('/dummy-enforcement', static function () use ($business, $enforcement) {
            // The economic plan's product limit is 20; the current count is at capacity.
            $enforcement->requireLimit($business, 'max_products', 20);

            return response()->json(['message' => 'Product Created']);
        });

        $this->postJson('/dummy-enforcement')->assertForbidden();
    }

    private function createBusinessWithPlan(string $planCode): Business
    {
        $business = Business::query()->create([
            'name' => 'Plan Restriction Test Business',
            'slug' => 'plan-restriction-'.Str::lower(Str::random(10)),
        ]);

        $plan = Plan::query()
            ->where('code', $planCode)
            ->firstOrFail();

        Subscription::query()->create([
            'business_id' => $business->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'current_period_starts_at' => CarbonImmutable::now()->subDay(),
            'current_period_ends_at' => CarbonImmutable::now()->addMonth(),
        ]);

        return $business;
    }

    private function enforcementFor(Business $business): EnforcementService
    {
        return new EnforcementService(new EntitlementService($business));
    }
}
