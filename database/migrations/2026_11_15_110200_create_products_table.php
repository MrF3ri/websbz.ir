<?php

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price');
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('status', 20)->default(ProductStatus::Draft->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign(['category_id', 'menu_id'])
                ->references(['id', 'menu_id'])
                ->on('categories')
                ->cascadeOnDelete();
            $table->index(
                ['menu_id', 'category_id', 'status', 'sort_order', 'id'],
                'products_catalog_order_idx',
            );
            $table->index(['menu_id', 'name'], 'products_menu_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
