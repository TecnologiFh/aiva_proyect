<?php

namespace App\Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MultiTenantTestSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant 4: Pastelería Donuts Fs
        DB::table('tenants')->updateOrInsert(
            ['id' => 4],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Pastelería Donuts Fs',
                'slug' => 'donuts-fs',
                'business_type' => 'Gastronomía / Pastelería Artesanal',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Tenant 5: Boutique Moda Elegante
        DB::table('tenants')->updateOrInsert(
            ['id' => 5],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Boutique Moda Elegante',
                'slug' => 'boutique-moda',
                'business_type' => 'Venta de Ropa y Calzado de Lujo',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Tenant 6: Clínica Dental OdontoSmile
        DB::table('tenants')->updateOrInsert(
            ['id' => 6],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Clínica Dental OdontoSmile',
                'slug' => 'odontosmile',
                'business_type' => 'Salud Dental / Estética Odontológica',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Productos de Tenant 5 (Boutique)
        DB::table('products')->updateOrInsert(
            ['tenant_id' => 5, 'name' => 'Vestido de Noche Glamour'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Vestido largo de seda fina para eventos formales.',
                'base_price' => 180.00,
                'price' => 180.00,
                'calculated_stock' => 5,
                'stock' => 5,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('products')->updateOrInsert(
            ['tenant_id' => 5, 'name' => 'Casaca de Cuero Italiana'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Casaca 100% cuero genuino de alta costura.',
                'base_price' => 250.00,
                'price' => 250.00,
                'calculated_stock' => 3,
                'stock' => 3,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Productos de Tenant 6 (Clínica Dental)
        DB::table('products')->updateOrInsert(
            ['tenant_id' => 6, 'name' => 'Limpieza Dental Profunda Ultrasonido'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Profilaxis completa con destartraje por ultrasonido.',
                'base_price' => 80.00,
                'price' => 80.00,
                'calculated_stock' => 20,
                'stock' => 20,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('products')->updateOrInsert(
            ['tenant_id' => 6, 'name' => 'Blanqueamiento Dental LED Pro'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Tratamiento estético aclarante en 1 sola sesión.',
                'base_price' => 250.00,
                'price' => 250.00,
                'calculated_stock' => 10,
                'stock' => 10,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Asignar GATEWAY_SECRET específicos por Tenant en tenant_settings
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => 5, 'setting_key' => 'gateway_secret'],
            ['setting_value' => 'secret_boutique_tenant_5', 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => 6, 'setting_key' => 'gateway_secret'],
            ['setting_value' => 'secret_odontosmile_tenant_6', 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
