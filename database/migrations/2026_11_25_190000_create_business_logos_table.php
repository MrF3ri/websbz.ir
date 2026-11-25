<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_logos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('media_id')->unique();
            $table->timestamps();

            $table->foreign(['media_id', 'business_id'], 'business_logos_media_tenant_fk')
                ->references(['id', 'business_id'])
                ->on('media')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_logos');
    }
};
