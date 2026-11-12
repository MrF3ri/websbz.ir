<?php

namespace Database\Seeders;

use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Seed the three subscription plans and their exact feature definitions.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'economic',
                'name' => 'Economic',
                'monthly_price' => 350_000,
                'features' => [
                    'max_products' => 20,
                    'max_categories' => 2,
                    'max_product_images' => 0,
                    'max_branches' => 1,
                    'max_languages' => 1,
                ],
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'monthly_price' => 750_000,
                'features' => [
                    'max_products' => -1,
                    'max_categories' => 5,
                    'max_product_images' => 1,
                    'max_branches' => 1,
                    'max_languages' => 1,
                    'product_discount' => true,
                    'availability_control' => true,
                ],
            ],
            [
                'code' => 'top',
                'name' => 'Top',
                'monthly_price' => 1_500_000,
                'features' => [
                    'max_products' => -1,
                    'max_categories' => -1,
                    'max_product_images' => 5,
                    'max_branches' => 2,
                    'max_languages' => 3,
                    'custom_domain' => true,
                    'product_video' => true,
                ],
            ],
        ];

        DB::transaction(function () use ($plans): void {
            foreach ($plans as $planData) {
                $features = $planData['features'];
                unset($planData['features']);

                $plan = Plan::query()->updateOrCreate(
                    ['code' => $planData['code']],
                    [
                        'name' => $planData['name'],
                        'monthly_price' => $planData['monthly_price'],
                        'is_active' => true,
                    ],
                );

                // Keep the seeded feature set exact and repeatable.
                $plan->features()->delete();

                foreach ($features as $featureKey => $value) {
                    $plan->features()->create([
                        'feature_key' => $featureKey,
                        'value' => $value,
                    ]);
                }
            }
        });
    }
}
