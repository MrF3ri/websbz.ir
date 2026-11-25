<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->timestamps();

            $table->unique(['menu_id', 'locale']);
            $table->index(['locale', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_translations');
    }
};
