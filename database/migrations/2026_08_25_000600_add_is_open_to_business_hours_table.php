<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_hours')) {
            Schema::table('business_hours', function (Blueprint $table) {
                if (!Schema::hasColumn('business_hours', 'is_open')) {
                    $table->boolean('is_open')->default(true)->after('day_of_week');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_hours')) {
            Schema::table('business_hours', function (Blueprint $table) {
                if (Schema::hasColumn('business_hours', 'is_open')) {
                    $table->dropColumn('is_open');
                }
            });
        }
    }
};
