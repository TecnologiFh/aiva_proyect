<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 50);
            $table->string('payment_method', 30);
            $table->string('gateway_transaction_id', 150)->nullable();
            $table->string('gateway_reference', 150)->nullable();
            $table->string('merchant_reference', 150)->nullable();
            $table->string('authorization_code', 50)->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 10)->default('PEN');
            $table->string('status', 30)->default('PENDING'); // PENDING, APPROVED, REJECTED, EXPIRED, REFUNDED
            $table->string('qr_code_url', 255)->nullable();
            $table->string('payment_link', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
