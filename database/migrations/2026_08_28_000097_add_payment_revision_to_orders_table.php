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
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'payment_revision')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('payment_revision')->default(1)->after('total_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'payment_revision')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_revision');
            });
        }
    }
};
