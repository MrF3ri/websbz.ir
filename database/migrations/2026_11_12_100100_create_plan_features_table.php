<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->json('value');
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
