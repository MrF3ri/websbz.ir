<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->unique(['id', 'business_id'], 'menus_id_business_unique');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['id', 'menu_id'], 'products_id_menu_unique');
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path', 1024);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->unique(['id', 'business_id'], 'media_id_business_unique');
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign(['menu_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('menus')
                ->cascadeOnDelete();
            $table->foreign(['product_id', 'menu_id'])
                ->references(['id', 'menu_id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['media_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('media')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'media_id'], 'product_images_product_media_unique');
            $table->index(['product_id', 'sort_order', 'id'], 'product_images_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('media');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_id_menu_unique');
        });

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropUnique('menus_id_business_unique');
        });
    }
};
