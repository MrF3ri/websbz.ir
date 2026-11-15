<?php

use App\Modules\Menu\Enums\MenuStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('slug', 80);
            $table->string('status', 20)->default(MenuStatus::Draft->value);
            $table->string('default_locale', 5)->default('fa');
            $table->timestamps();
            $table->unique('business_id');
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
