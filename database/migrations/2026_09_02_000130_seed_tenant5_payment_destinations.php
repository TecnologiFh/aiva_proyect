<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = 5;

        // Limpiar destinos previos de prueba para Tenant 5
        DB::table('tenant_payment_destinations')->where('tenant_id', $tenantId)->delete();

        // 1. Destinos Yape por nombre
        DB::table('tenant_payment_destinations')->insert([
            'tenant_id' => $tenantId,
            'provider' => 'YAPE',
            'destination_type' => 'NAME',
            'destination_value' => 'Antojito',
            'display_name' => 'El Antojito',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_payment_destinations')->insert([
            'tenant_id' => $tenantId,
            'provider' => 'YAPE',
            'destination_type' => 'NAME',
            'destination_value' => 'Luis Fiestas',
            'display_name' => 'Luis Fiestas',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Destinos Yape por teléfono
        DB::table('tenant_payment_destinations')->insert([
            'tenant_id' => $tenantId,
            'provider' => 'YAPE',
            'destination_type' => 'PHONE',
            'destination_value' => '977149110',
            'display_name' => '977149110',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_payment_destinations')->insert([
            'tenant_id' => $tenantId,
            'provider' => 'YAPE',
            'destination_type' => 'PHONE',
            'destination_value' => '900434897',
            'display_name' => '900434897',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
