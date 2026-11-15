<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('bank_tracking_number')->nullable()->unique();
            $table->string('receipt_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropUnique(['bank_tracking_number']);
            $table->dropColumn([
                'bank_tracking_number',
                'receipt_path',
                'submitted_at',
                'reviewed_at',
                'reviewed_by',
                'rejection_reason',
            ]);
        });
    }
};
