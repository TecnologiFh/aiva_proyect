<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            if (!Schema::hasColumn('tenants', 'lifecycle_state')) {
                Schema::table('tenants', function (Blueprint $table) {
                    $table->string('lifecycle_state', 50)->default('PRODUCTION')->after('status');
                });
            }
            // Asegurar que Tenant 4 (Pastelería Donas Pepa) esté en PRODUCTION
            DB::table('tenants')->update(['lifecycle_state' => 'PRODUCTION']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'lifecycle_state')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('lifecycle_state');
            });
        }
    }
};
