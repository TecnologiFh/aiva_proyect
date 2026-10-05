<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'customer_phone_canonical')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('customer_phone_canonical', 50)->nullable()->index()->after('customer_phone');
            });
        }

        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'customer_phone_canonical')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('customer_phone_canonical', 50)->nullable()->index()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customer_phone_canonical')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('customer_phone_canonical');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'customer_phone_canonical')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('customer_phone_canonical');
            });
        }
    }
};
