<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'refund_status')) {
                    $table->string('refund_status', 40)->default('NONE')->after('payment_status');
                }
                if (!Schema::hasColumn('orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('updated_at');
                }
                if (!Schema::hasColumn('orders', 'cancelled_by')) {
                    $table->string('cancelled_by', 50)->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('orders', 'cancellation_channel')) {
                    $table->string('cancellation_channel', 50)->nullable()->after('cancelled_by');
                }
                if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 255)->nullable()->after('cancellation_channel');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = ['refund_status', 'cancelled_at', 'cancelled_by', 'cancellation_channel', 'cancellation_reason'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
