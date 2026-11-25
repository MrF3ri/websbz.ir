<?php

namespace Tests\Unit\Branch;

use App\Modules\Branch\Models\BranchProductSetting;
use App\Modules\Branch\Services\BranchProductResolver;
use App\Modules\Product\Models\Product;
use PHPUnit\Framework\TestCase;

class BranchProductResolverTest extends TestCase
{
    public function test_missing_branch_setting_inherits_canonical_product_values(): void
    {
        $product = $this->product();
        $resolved = (new BranchProductResolver)->resolve($product, null);

        $this->assertSame([
            'name' => 'Canonical espresso',
            'description' => 'Canonical description',
            'base_price' => 120000,
            'discount_price' => 100000,
            'is_visible' => true,
            'is_available' => true,
            'sort_order' => 4,
        ], $resolved);
    }

    public function test_branch_overrides_replace_content_price_discount_and_order(): void
    {
        $setting = new BranchProductSetting([
            'name_override' => 'Branch espresso',
            'description_override' => 'Branch description',
            'description_override_enabled' => true,
            'base_price_override' => 150000,
            'discount_price_override' => 130000,
            'discount_override_mode' => 'set',
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertSame('Branch espresso', $resolved['name']);
        $this->assertSame('Branch description', $resolved['description']);
        $this->assertSame(150000, $resolved['base_price']);
        $this->assertSame(130000, $resolved['discount_price']);
        $this->assertSame(1, $resolved['sort_order']);
    }

    public function test_enabled_null_description_override_intentionally_clears_description(): void
    {
        $setting = new BranchProductSetting([
            'description_override' => null,
            'description_override_enabled' => true,
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertNull($resolved['description']);
    }

    public function test_discount_clear_mode_removes_canonical_discount(): void
    {
        $setting = new BranchProductSetting([
            'discount_override_mode' => 'clear',
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertNull($resolved['discount_price']);
    }

    public function test_zero_tracked_stock_makes_a_visible_branch_product_unavailable(): void
    {
        $setting = new BranchProductSetting([
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertTrue($resolved['is_visible']);
        $this->assertFalse($resolved['is_available']);
    }

    public function test_untracked_stock_does_not_block_branch_product_availability(): void
    {
        $setting = new BranchProductSetting([
            'is_visible' => true,
            'is_available' => true,
            'track_inventory' => false,
            'stock_quantity' => null,
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertTrue($resolved['is_available']);
    }

    public function test_branch_visibility_and_availability_are_independent(): void
    {
        $setting = new BranchProductSetting([
            'is_visible' => false,
            'is_available' => false,
            'track_inventory' => false,
        ]);

        $resolved = (new BranchProductResolver)->resolve($this->product(), $setting);

        $this->assertFalse($resolved['is_visible']);
        $this->assertFalse($resolved['is_available']);
    }

    private function product(): Product
    {
        return new Product([
            'name' => 'Canonical espresso',
            'description' => 'Canonical description',
            'base_price' => 120000,
            'discount_price' => 100000,
            'is_available' => true,
            'sort_order' => 4,
        ]);
    }
}
