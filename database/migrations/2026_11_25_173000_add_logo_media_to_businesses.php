<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedBigInteger('logo_media_id')->nullable();

            $table->foreign(['logo_media_id', 'id'], 'business_logo_media_tenant_fk')
                ->references(['id', 'business_id'])
                ->on('media')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropForeign('business_logo_media_tenant_fk');
            $table->dropColumn('logo_media_id');
        });
    }
};
