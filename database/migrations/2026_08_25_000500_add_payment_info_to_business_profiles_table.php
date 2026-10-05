<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('business_profiles', 'yape_info')) {
                    $table->string('yape_info', 255)->nullable();
                }
                if (!Schema::hasColumn('business_profiles', 'plin_info')) {
                    $table->string('plin_info', 255)->nullable();
                }
                if (!Schema::hasColumn('business_profiles', 'bank_account_info')) {
                    $table->string('bank_account_info', 255)->nullable();
                }
            });

            // Actualizar datos de pago oficiales para Tenant 4
            DB::table('business_profiles')->where('tenant_id', 4)->update([
                'yape_info' => '900434897 - Pastelería Donas Pepa SAC',
                'plin_info' => '900434897 - Pastelería Donas Pepa SAC',
                'bank_account_info' => '002-191-002938481023-54',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_profiles')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('business_profiles', 'yape_info')) {
                    $table->dropColumn(['yape_info', 'plin_info', 'bank_account_info']);
                }
            });
        }
    }
};
