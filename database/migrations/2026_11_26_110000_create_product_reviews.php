<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedTinyInteger('rating');
            $table->string('display_name', 80)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('visitor_key_hash', 64);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->foreign(['menu_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('menus')
                ->cascadeOnDelete();
            $table->foreign(['product_id', 'menu_id'])
                ->references(['id', 'menu_id'])
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'visitor_key_hash'], 'product_reviews_product_visitor_unique');
            $table->index(['business_id', 'status', 'created_at'], 'product_reviews_moderation_idx');
            $table->index(['product_id', 'status', 'created_at'], 'product_reviews_public_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
