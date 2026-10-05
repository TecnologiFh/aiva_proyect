<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 30)->default('PENDING')->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'channel')) {
                $table->string('channel', 30)->default('WHATSAPP')->after('order_type');
            }
            if (!Schema::hasColumn('orders', 'delivery_zone')) {
                $table->string('delivery_zone', 100)->nullable()->after('delivery_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_status', 'channel', 'delivery_zone']);
        });
    }
};
