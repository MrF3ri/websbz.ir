<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'created_at']);
            $table->index(['subscription_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
