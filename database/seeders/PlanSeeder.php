<?php

namespace Database\Seeders;

use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Seed all approved plan features as a complete feature matrix.
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
                    'menu_template_count' => 3,
                    'custom_domain' => false,
                    'custom_design' => false,
                    'product_discount' => false,
                    'product_availability_toggle' => false,
                    'product_video' => false,
                    'max_product_videos' => 0,
                    'product_reviews' => false,
                    'product_likes' => false,
                    'instagram_integration' => false,
                    'excel_export' => false,
                    'backup' => false,
                    'notifications' => false,
                    'initial_setup' => false,
                    'map_location' => false,
                    'social_links' => false,
                    'whatsapp_link' => false,
                    'business_logo' => false,
                    'custom_colors' => false,
                    'font_selection' => false,
                    'dark_mode' => false,
                    'product_labels' => false,
                    'image_slider' => false,
                    'advertising_banner' => false,
                    'business_introduction' => false,
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
                    'menu_template_count' => 6,
                    'custom_domain' => false,
                    'custom_design' => false,
                    'product_discount' => true,
                    'product_availability_toggle' => true,
                    'product_video' => false,
                    'product_reviews' => false,
                    'product_likes' => false,
                    'instagram_integration' => false,
                    'excel_export' => false,
                    'backup' => false,
                    'notifications' => false,
                    'initial_setup' => false,
                    'map_location' => true,
                    'social_links' => true,
                    'whatsapp_link' => true,
                    'business_logo' => true,
                    'custom_colors' => true,
                    'font_selection' => true,
                    'dark_mode' => true,
                    'product_labels' => true,
                    'image_slider' => true,
                    'advertising_banner' => true,
                    'business_introduction' => true,
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
                    'menu_template_count' => -1,
                    'custom_domain' => true,
                    'custom_design' => true,
                    'product_discount' => true,
                    'product_availability_toggle' => true,
                    'product_video' => true,
                    'max_product_videos' => 1,
                    'product_reviews' => true,
                    'product_likes' => true,
                    'instagram_integration' => true,
                    'excel_export' => true,
                    'backup' => true,
                    'notifications' => true,
                    'initial_setup' => true,
                    'map_location' => true,
                    'social_links' => true,
                    'whatsapp_link' => true,
                    'business_logo' => true,
                    'custom_colors' => true,
                    'font_selection' => true,
                    'dark_mode' => true,
                    'product_labels' => true,
                    'image_slider' => true,
                    'advertising_banner' => true,
                    'business_introduction' => true,
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
