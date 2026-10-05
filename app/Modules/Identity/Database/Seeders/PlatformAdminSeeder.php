<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\PlatformUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin de la Plataforma (Control Plane)
        PlatformUser::firstOrCreate(
            ['email' => 'admin@aiva.pe'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Super Administrator',
                'password' => Hash::make('Admin123!@#'),
                'role' => 'SUPERADMIN',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Owner de Tenant #4 (Donuts FS Express)
        $ownerRole = Role::where('name', 'OWNER')->first();

        $tenantUser = User::firstOrCreate(
            ['tenant_id' => 4, 'email' => 'donuts@fs.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Owner Donuts',
                'password' => Hash::make('Donuts123!'),
                'status' => 'ACTIVE',
            ]
        );

        if ($ownerRole) {
            \Illuminate\Support\Facades\DB::table('role_user')->updateOrInsert(
                ['user_id' => $tenantUser->id, 'tenant_id' => 4],
                ['role_id' => $ownerRole->id, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
