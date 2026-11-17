<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['id', 'menu_id'], 'products_id_menu_unique');
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 80);
            $table->string('status', 20)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_business_id')->nullable()->unique();
            $table->string('phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->json('opening_hours')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'slug'], 'branches_business_slug_unique');
            $table->unique(['id', 'business_id'], 'branches_id_business_unique');
            $table->index(['business_id', 'status'], 'branches_business_status_idx');
        });

        $this->enforcePrimaryBranchMarker();

        Schema::create('branch_category_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('category_id');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'category_id'], 'branch_category_settings_branch_category_unique');
            $table->foreign(['branch_id', 'business_id'], 'branch_category_settings_branch_business_fk')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
            $table->foreign(['menu_id', 'business_id'], 'branch_category_settings_menu_business_fk')
                ->references(['id', 'business_id'])
                ->on('menus')
                ->cascadeOnDelete();
            $table->foreign(['category_id', 'menu_id'], 'branch_category_settings_category_menu_fk')
                ->references(['id', 'menu_id'])
                ->on('categories')
                ->cascadeOnDelete();
            $table->index(['branch_id', 'is_visible', 'sort_order'], 'branch_category_settings_order_idx');
        });

        Schema::create('branch_product_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name_override', 180)->nullable();
            $table->text('description_override')->nullable();
            $table->boolean('description_override_enabled')->default(false);
            $table->unsignedBigInteger('base_price_override')->nullable();
            $table->unsignedBigInteger('discount_price_override')->nullable();
            $table->string('discount_override_mode', 10)->default('inherit');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('track_inventory')->default(false);
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id'], 'branch_product_settings_branch_product_unique');
            $table->foreign(['branch_id', 'business_id'], 'branch_product_settings_branch_business_fk')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
            $table->foreign(['menu_id', 'business_id'], 'branch_product_settings_menu_business_fk')
                ->references(['id', 'business_id'])
                ->on('menus')
                ->cascadeOnDelete();
            $table->foreign(['product_id', 'menu_id'], 'branch_product_settings_product_menu_fk')
                ->references(['id', 'menu_id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->index(['branch_id', 'is_visible', 'is_available', 'sort_order'], 'branch_product_settings_public_idx');
        });

        $this->backfillPrimaryBranches();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_settings');
        Schema::dropIfExists('branch_category_settings');
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS branches_primary_marker_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS branches_primary_marker_update');
        }

        Schema::dropIfExists('branches');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_id_menu_unique');
        });
    }

    private function enforcePrimaryBranchMarker(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE branches
                ADD CONSTRAINT branches_primary_marker_check
                CHECK (
                    (is_primary = TRUE AND primary_business_id = business_id)
                    OR (is_primary = FALSE AND primary_business_id IS NULL)
                )
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER branches_primary_marker_insert
                BEFORE INSERT ON branches
                FOR EACH ROW
                WHEN NOT (
                    (NEW.is_primary = 1 AND NEW.primary_business_id = NEW.business_id)
                    OR (NEW.is_primary = 0 AND NEW.primary_business_id IS NULL)
                )
                BEGIN
                    SELECT RAISE(ABORT, 'branches primary marker must match business');
                END
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER branches_primary_marker_update
                BEFORE UPDATE OF business_id, is_primary, primary_business_id ON branches
                FOR EACH ROW
                WHEN NOT (
                    (NEW.is_primary = 1 AND NEW.primary_business_id = NEW.business_id)
                    OR (NEW.is_primary = 0 AND NEW.primary_business_id IS NULL)
                )
                BEGIN
                    SELECT RAISE(ABORT, 'branches primary marker must match business');
                END
                SQL);
        }
    }

    private function backfillPrimaryBranches(): void
    {
        DB::table('businesses')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses): void {
                foreach ($businesses as $business) {
                    DB::transaction(function () use ($business): void {
                        $branch = DB::table('branches')
                            ->where('business_id', $business->id)
                            ->where('is_primary', true)
                            ->first();

                        if ($branch === null) {
                            $branchId = DB::table('branches')->insertGetId([
                                'public_id' => (string) Str::ulid(),
                                'business_id' => $business->id,
                                'name' => $business->name,
                                'slug' => 'main',
                                'status' => 'active',
                                'is_primary' => true,
                                'primary_business_id' => $business->id,
                                'timezone' => 'Asia/Tehran',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            $branchId = $branch->id;
                        }

                        $menu = DB::table('menus')
                            ->where('business_id', $business->id)
                            ->first(['id']);

                        if ($menu === null) {
                            return;
                        }

                        $now = now();

                        $categories = DB::table('categories')
                            ->where('menu_id', $menu->id)
                            ->get(['id', 'sort_order']);

                        foreach ($categories as $category) {
                            DB::table('branch_category_settings')->insertOrIgnore([
                                'branch_id' => $branchId,
                                'business_id' => $business->id,
                                'menu_id' => $menu->id,
                                'category_id' => $category->id,
                                'is_visible' => true,
                                'sort_order' => $category->sort_order,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        $products = DB::table('products')
                            ->where('menu_id', $menu->id)
                            ->get(['id', 'is_available', 'sort_order']);

                        foreach ($products as $product) {
                            DB::table('branch_product_settings')->insertOrIgnore([
                                'branch_id' => $branchId,
                                'business_id' => $business->id,
                                'menu_id' => $menu->id,
                                'product_id' => $product->id,
                                'description_override_enabled' => false,
                                'discount_override_mode' => 'inherit',
                                'is_visible' => true,
                                'is_available' => $product->is_available,
                                'track_inventory' => false,
                                'stock_quantity' => null,
                                'sort_order' => $product->sort_order,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    });
                }
            });
    }
};
