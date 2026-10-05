<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. payment_evidences
        if (!Schema::hasTable('payment_evidences')) {
            Schema::create('payment_evidences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('uuid', 64)->unique();
                $table->string('source', 50)->default('WHATSAPP');
                $table->string('provider', 50)->default('UNKNOWN');
                $table->string('sha256', 64)->index();
                $table->string('phash', 64)->nullable()->index();
                $table->string('status', 50)->default('RECEIVED')->index();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // 2. payment_evidence_files
        if (!Schema::hasTable('payment_evidence_files')) {
            Schema::create('payment_evidence_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_evidence_id')->index();
                $table->string('original_name', 255)->nullable();
                $table->string('storage_path', 500);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->timestamps();

                $table->foreign('payment_evidence_id')
                    ->references('id')
                    ->on('payment_evidences')
                    ->onDelete('cascade');
            });
        }

        // 3. payment_extractions
        if (!Schema::hasTable('payment_extractions')) {
            Schema::create('payment_extractions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_evidence_id')->index();
                $table->string('provider', 50);
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('currency', 10)->default('PEN');
                $table->string('operation_id', 100)->nullable()->index();
                $table->date('transaction_date')->nullable();
                $table->time('transaction_time')->nullable();
                $table->string('sender_name', 255)->nullable();
                $table->string('recipient_name', 255)->nullable();
                $table->string('recipient_phone', 50)->nullable();
                $table->decimal('overall_confidence', 5, 4)->default(0.0000);
                $table->longText('raw_ocr_json')->nullable();
                $table->json('confidence_json')->nullable();
                $table->json('bounding_boxes_json')->nullable();
                $table->timestamps();

                $table->foreign('payment_evidence_id')
                    ->references('id')
                    ->on('payment_evidences')
                    ->onDelete('cascade');
            });
        }

        // 4. payment_validations
        if (!Schema::hasTable('payment_validations')) {
            Schema::create('payment_validations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_evidence_id')->index();
                $table->string('rule_code', 50);
                $table->string('status', 20); // PASS, FAIL, SKIPPED
                $table->string('expected_value', 255)->nullable();
                $table->string('actual_value', 255)->nullable();
                $table->json('details_json')->nullable();
                $table->timestamps();

                $table->foreign('payment_evidence_id')
                    ->references('id')
                    ->on('payment_evidences')
                    ->onDelete('cascade');
            });
        }

        // 5. payment_decisions (INSERT ONLY - Inmutable)
        if (!Schema::hasTable('payment_decisions')) {
            Schema::create('payment_decisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_evidence_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('decision', 50); // AUTHORIZED, MANUAL_REVIEW, REJECTED, PARTIAL_PAYMENT
                $table->string('reason_code', 100);
                $table->integer('risk_score')->default(0);
                $table->string('actor_type', 50)->default('SYSTEM'); // SYSTEM, USER
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('previous_decision_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 6. payment_allocations
        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_evidence_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->decimal('allocated_amount', 12, 2);
                $table->decimal('remaining_order_amount', 12, 2);
                $table->string('allocation_type', 50)->default('EXACT_PAYMENT'); // EXACT_PAYMENT, PARTIAL_PAYMENT, OVERPAYMENT
                $table->string('status', 50)->default('APPLIED');
                $table->timestamps();

                $table->foreign('payment_evidence_id')
                    ->references('id')
                    ->on('payment_evidences')
                    ->onDelete('cascade');
            });
        }

        // 7. payment_audit_events
        if (!Schema::hasTable('payment_audit_events')) {
            Schema::create('payment_audit_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('payment_evidence_id')->nullable()->index();
                $table->string('event_type', 100);
                $table->string('actor_type', 50)->default('SYSTEM');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 8. tenant_payment_destinations
        if (!Schema::hasTable('tenant_payment_destinations')) {
            Schema::create('tenant_payment_destinations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 50); // YAPE, PLIN, BCP, BBVA, INTERBANK, SCOTIABANK
                $table->string('destination_type', 50); // PHONE, ACCOUNT_NUMBER, CCI
                $table->string('destination_value', 100);
                $table->string('display_name', 255);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'provider', 'destination_value'], 'unique_tenant_dest');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_destinations');
        Schema::dropIfExists('payment_audit_events');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_decisions');
        Schema::dropIfExists('payment_validations');
        Schema::dropIfExists('payment_extractions');
        Schema::dropIfExists('payment_evidence_files');
        Schema::dropIfExists('payment_evidences');
    }
};
