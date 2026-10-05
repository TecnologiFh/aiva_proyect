<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'advance_days')) {
                    $table->integer('advance_days')->default(0)->after('base_price');
                }
                if (!Schema::hasColumn('products', 'advance_percentage')) {
                    $table->decimal('advance_percentage', 5, 2)->default(0.00)->after('advance_days');
                }
                if (!Schema::hasColumn('products', 'ai_instructions')) {
                    $table->text('ai_instructions')->nullable()->after('advance_percentage');
                }
                if (!Schema::hasColumn('products', 'stock')) {
                    $table->integer('stock')->default(20)->after('sku');
                }
            });
        }

        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('business_profiles', 'general_advance_days')) {
                    $table->integer('general_advance_days')->default(1)->after('business_category');
                }
                if (!Schema::hasColumn('business_profiles', 'general_advance_percentage')) {
                    $table->decimal('general_advance_percentage', 5, 2)->default(50.00)->after('general_advance_days');
                }
                if (!Schema::hasColumn('business_profiles', 'preparation_time_mins')) {
                    $table->integer('preparation_time_mins')->default(25)->after('general_advance_percentage');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['advance_days', 'advance_percentage', 'ai_instructions']);
            });
        }
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->dropColumn(['general_advance_days', 'general_advance_percentage', 'preparation_time_mins']);
            });
        }
    }
};
