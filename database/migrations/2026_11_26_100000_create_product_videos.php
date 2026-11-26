<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_ms')->nullable()->after('height');
        });

        Schema::create('product_videos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('media_id');
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

            $table->unique('product_id', 'product_videos_one_per_product');
            $table->unique(['media_id', 'business_id'], 'product_videos_media_business_unique');
            $table->index(['business_id', 'menu_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');

        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('duration_ms');
        });
    }
};
