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
        if (Schema::hasTable('payment_evidences') && !Schema::hasColumn('payment_evidences', 'payment_revision')) {
            Schema::table('payment_evidences', function (Blueprint $table) {
                $table->unsignedInteger('payment_revision')->default(1)->after('order_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payment_evidences') && Schema::hasColumn('payment_evidences', 'payment_revision')) {
            Schema::table('payment_evidences', function (Blueprint $table) {
                $table->dropColumn('payment_revision');
            });
        }
    }
};
