<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['id', 'menu_id']);
            $table->index(['menu_id', 'sort_order', 'id'], 'categories_menu_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
