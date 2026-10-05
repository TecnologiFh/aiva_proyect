<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for P2.12 Business Control Plane.
     */
    public function up(): void
    {
        // 1. Refunds (Operational Source of Truth)
        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 36)->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('payment_allocation_id')->nullable()->index();
                $table->string('refund_number', 50);
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255);
                $table->string('refund_method', 50)->default('YAPE');
                $table->string('status', 50)->default('REQUESTED');
                $table->timestamp('processed_at')->nullable();
                $table->string('processed_by', 100)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'refund_number']);
            });
        }

        // 2. CQRS Read Model: Analytics Daily Metrics
        if (!Schema::hasTable('analytics_daily_metrics')) {
            Schema::create('analytics_daily_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->date('business_date');
                $table->decimal('gross_sales', 12, 2)->default(0.00);
                $table->decimal('discounts', 12, 2)->default(0.00);
                $table->decimal('net_sales', 12, 2)->default(0.00);
                $table->decimal('paid_sales', 12, 2)->default(0.00);
                $table->decimal('pending_sales', 12, 2)->default(0.00);
                $table->decimal('refunds_total', 12, 2)->default(0.00);
                $table->unsignedInteger('total_orders_count')->default(0);
                $table->unsignedInteger('paid_orders_count')->default(0);
                $table->unsignedInteger('pending_orders_count')->default(0);
                $table->unsignedInteger('cancelled_orders_count')->default(0);
                $table->unsignedInteger('refunded_orders_count')->default(0);
                $table->decimal('avg_ticket', 10, 2)->default(0.00);
                $table->longText('metrics_json')->nullable();
                $table->string('dataset_version', 50)->default('v1.0');
                $table->timestamps();

                $table->unique(['tenant_id', 'business_date']);
            });
        }

        // 3. CQRS Read Model: Analytics Product Daily
        if (!Schema::hasTable('analytics_product_daily')) {
            Schema::create('analytics_product_daily', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('product_id');
                $table->date('business_date');
                $table->string('product_name', 150);
                $table->unsignedInteger('units_sold')->default(0);
                $table->decimal('gross_revenue', 12, 2)->default(0.00);
                $table->decimal('net_revenue', 12, 2)->default(0.00);
                $table->decimal('cogs_unit', 10, 2)->default(0.00);
                $table->decimal('cogs_total', 12, 2)->default(0.00);
                $table->decimal('margin_total', 12, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['tenant_id', 'product_id', 'business_date'], 'idx_analytics_product_daily_unique');
            });
        }

        // 4. CQRS Read Model: Analytics Customer Metrics (RFM)
        if (!Schema::hasTable('analytics_customer_metrics')) {
            Schema::create('analytics_customer_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedInteger('recency_days')->default(0);
                $table->unsignedInteger('frequency_count')->default(0);
                $table->decimal('monetary_total', 12, 2)->default(0.00);
                $table->string('rfm_score', 10)->default('111');
                $table->string('rfm_segment', 50)->default('NEW');
                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'customer_id']);
            });
        }

        // 5. Daily Closings (Audit Closing Store)
        if (!Schema::hasTable('daily_closings')) {
            Schema::create('daily_closings', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 36)->unique();
                $table->unsignedBigInteger('tenant_id');
                $table->date('closing_date');
                $table->decimal('gross_sales', 12, 2)->default(0.00);
                $table->decimal('discounts', 12, 2)->default(0.00);
                $table->decimal('net_sales', 12, 2)->default(0.00);
                $table->decimal('paid_sales', 12, 2)->default(0.00);
                $table->decimal('pending_sales', 12, 2)->default(0.00);
                $table->decimal('refunds_total', 12, 2)->default(0.00);
                $table->decimal('expected_cash', 12, 2)->default(0.00);
                $table->decimal('declared_cash', 12, 2)->default(0.00);
                $table->decimal('cash_difference', 12, 2)->default(0.00);
                $table->unsignedInteger('orders_count')->default(0);
                $table->unsignedInteger('cancelled_orders_count')->default(0);
                $table->string('previous_closing_hash', 64)->nullable();
                $table->string('closing_hash', 64);
                $table->string('correlation_id', 100);
                $table->string('closed_by', 100);
                $table->timestamp('closed_at');
                $table->timestamps();

                $table->unique(['tenant_id', 'closing_date']);
            });
        }

        // 6. Daily Closing Adjustments
        if (!Schema::hasTable('daily_closing_adjustments')) {
            Schema::create('daily_closing_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_closing_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->decimal('adjustment_amount', 12, 2);
                $table->string('reason', 255);
                $table->string('adjusted_by', 100);
                $table->string('approved_by', 100)->nullable();
                $table->string('adjustment_hash', 64);
                $table->timestamps();
            });
        }

        // 7. Report Exports Ledger
        if (!Schema::hasTable('report_exports')) {
            Schema::create('report_exports', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 36)->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('report_type', 50);
                $table->string('format', 10)->default('PDF');
                $table->date('date_from');
                $table->date('date_to');
                $table->string('schema_version', 20)->default('1.0');
                $table->string('dataset_version', 50);
                $table->string('file_sha256', 64);
                $table->string('storage_path', 500);
                $table->string('download_token', 100)->unique();
                $table->timestamp('expires_at');
                $table->string('generated_by', 100);
                $table->timestamps();
            });
        }

        // 8. Canonical Domain Events Store (Timeline & Replay)
        if (!Schema::hasTable('domain_events')) {
            Schema::create('domain_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 100)->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('event_type', 100)->index();
                $table->string('aggregate_type', 50)->index();
                $table->string('aggregate_id', 100)->index();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->string('actor_type', 50)->default('SYSTEM');
                $table->string('actor_id', 100)->nullable();
                $table->string('correlation_id', 100)->nullable()->index();
                $table->string('causation_id', 100)->nullable();
                $table->string('schema_version', 20)->default('1.0');
                $table->longText('payload_json');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 9. Dead Letter Queue Table (P2.13-F3 Isolation)
        if (!Schema::hasTable('dead_letter_queue')) {
            Schema::create('dead_letter_queue', function (Blueprint $table) {
                $table->id();
                $table->string('message_uuid', 100)->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('event_type', 100)->index();
                $table->longText('payload_json');
                $table->string('payload_sha256', 64);
                $table->unsignedInteger('attempt_count')->default(1);
                $table->timestamp('first_failed_at')->useCurrent();
                $table->timestamp('last_failed_at')->useCurrent();
                $table->string('failure_reason', 255);
                $table->string('correlation_id', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('daily_closing_adjustments');
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('analytics_customer_metrics');
        Schema::dropIfExists('analytics_product_daily');
        Schema::dropIfExists('analytics_daily_metrics');
        Schema::dropIfExists('refunds');
    }
};
