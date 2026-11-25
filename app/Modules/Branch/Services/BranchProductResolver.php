<?php

namespace App\Modules\Branch\Services;

use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Product\Models\Product;

class BranchProductResolver
{
    /**
     * Resolve branch-specific values over the canonical product without mutating either model.
     *
     * @return array{name: string, description: string|null, base_price: int, discount_price: int|null, is_visible: bool, is_available: bool, sort_order: int}
     */
    public function resolve(Product $product, ?BranchProductSetting $setting): array
    {
        $basePrice = $setting?->base_price_override ?? $product->base_price;
        $discountPrice = match ($setting?->discount_override_mode ?? 'inherit') {
            'set' => $setting->discount_price_override,
            'clear' => null,
            default => $product->discount_price,
        };
        $tracksInventory = $setting?->track_inventory ?? false;
        $stockAvailable = ! $tracksInventory || ($setting?->stock_quantity ?? 0) > 0;

        return [
            'name' => $setting?->name_override ?? $product->name,
            'description' => $setting?->description_override_enabled
                ? $setting->description_override
                : $product->description,
            'base_price' => $basePrice,
            'discount_price' => $discountPrice,
            'is_visible' => $setting?->is_visible ?? true,
            'is_available' => $product->is_available
                && ($setting?->is_available ?? true)
                && $stockAvailable,
            'sort_order' => $setting?->sort_order ?? $product->sort_order,
        ];
    }
}
