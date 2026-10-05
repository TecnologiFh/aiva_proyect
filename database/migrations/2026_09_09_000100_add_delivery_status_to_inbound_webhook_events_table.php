<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inbound_webhook_events')) {
            Schema::table('inbound_webhook_events', function (Blueprint $table) {
                if (!Schema::hasColumn('inbound_webhook_events', 'delivery_status')) {
                    $table->string('delivery_status', 32)->nullable()->after('status');
                }
                if (!Schema::hasColumn('inbound_webhook_events', 'delivery_weight')) {
                    $table->integer('delivery_weight')->default(0)->after('delivery_status');
                }
                if (!Schema::hasColumn('inbound_webhook_events', 'delivery_updated_at')) {
                    $table->timestamp('delivery_updated_at')->nullable()->after('delivery_weight');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inbound_webhook_events')) {
            Schema::table('inbound_webhook_events', function (Blueprint $table) {
                if (Schema::hasColumn('inbound_webhook_events', 'delivery_status')) {
                    $table->dropColumn('delivery_status');
                }
                if (Schema::hasColumn('inbound_webhook_events', 'delivery_weight')) {
                    $table->dropColumn('delivery_weight');
                }
                if (Schema::hasColumn('inbound_webhook_events', 'delivery_updated_at')) {
                    $table->dropColumn('delivery_updated_at');
                }
            });
        }
    }
};
