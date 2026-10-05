<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 150)->unique();
            $table->string('gateway', 50);
            $table->string('signature', 255)->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->useCurrent();

            $table->index(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
