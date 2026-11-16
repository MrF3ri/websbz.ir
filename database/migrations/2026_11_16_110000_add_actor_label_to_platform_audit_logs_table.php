<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->string('actor_label', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->dropColumn('actor_label');
        });
    }
};
