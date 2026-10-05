<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_vouchers')) {
            Schema::create('payment_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('order_id');
                $table->string('voucher_hash', 64);
                $table->string('operation_fingerprint', 64);
                $table->decimal('extracted_amount', 10, 2)->nullable();
                $table->timestamp('extracted_date')->nullable();
                $table->string('extracted_operation_number', 100)->nullable();
                $table->string('extracted_destination_phone', 50)->nullable();
                $table->string('extracted_destination_holder', 150)->nullable();
                $table->string('status', 30)->default('RECEIVED'); // RECEIVED, ANALYZING, MATCHED, MISMATCH, REUSED, MANUAL_REVIEW, VERIFIED, REJECTED
                $table->timestamps();

                $table->unique(['tenant_id', 'voucher_hash'], 'uk_tenant_voucher_hash');
                $table->index(['tenant_id', 'operation_fingerprint'], 'idx_tenant_op_fingerprint');
                $table->index(['tenant_id', 'status'], 'idx_tenant_vouch_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};
