<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inbound_webhook_emergency_logs')) {
            Schema::create('inbound_webhook_emergency_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 100);
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('payload_hash', 64);
                $table->longText('payload');
                $table->timestamp('received_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->string('status', 20)->default('PENDING'); // PENDING, PROCESSING, PROCESSED, FAILED
                $table->text('last_error')->nullable();
                $table->string('correlation_id', 64);
                $table->timestamps();

                $table->unique(['tenant_id', 'event_id'], 'uk_tenant_event_emergency');
                $table->index(['status', 'received_at'], 'idx_status_received');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_emergency_logs');
    }
};
