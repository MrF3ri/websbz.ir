<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->timestamps();

            $table->unique(['category_id', 'locale']);
            $table->index(['locale', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_translations');
    }
};
