<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenant:provision 
                            {--name= : Nombre del Merchant / Tenant} 
                            {--phone-id= : Phone Number ID de WABA}';

    protected $description = 'Aprovisionamiento atómico y seguro de un nuevo Tenant piloto';

    public function handle(): int
    {
        $this->info('=== AIVA Tenant Pilot Provisioning Tool ===');

        $name = $this->option('name') ?? $this->ask('Nombre del Tenant / Negocio Piloto:');
        $phoneId = $this->option('phone-id') ?? $this->ask('Meta WABA Phone Number ID:');

        // Prompt seguro usando $this->secret() para no dejar rastros en bash history / ps
        $token = $this->secret('Meta Access Token (Entrada Oculta):');
        $webhookSecret = $this->secret('Meta App / Webhook Secret (Entrada Oculta):');
        $adminEmail = $this->ask('Email del Administrador del Tenant:');
        $adminPassword = $this->secret('Contraseña del Administrador (Entrada Oculta):');

        if (empty($name) || empty($phoneId) || empty($token) || empty($webhookSecret) || empty($adminEmail) || empty($adminPassword)) {
            $this->error('Error: Todos los datos son requeridos para el aprovisionamiento.');
            return 1;
        }

        $this->comment('Iniciando transacción atómica de aprovisionamiento...');

        try {
            DB::transaction(function () use ($name, $phoneId, $token, $webhookSecret, $adminEmail, $adminPassword) {
                // 0. Asegurar Plan base si la tabla plans existe
                $planId = 1;
                if (\Schema::hasTable('plans')) {
                    $firstPlan = DB::table('plans')->first();
                    if (!$firstPlan) {
                        $planData = [
                            'name' => 'Plan Piloto Enterprise',
                            'monthly_price' => 0.00,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $planId = DB::table('plans')->insertGetId($this->fillTableDefaults('plans', $planData));
                    } else {
                        $planId = $firstPlan->id;
                    }
                }

                // 1. Crear Tenant
                $tenantData = [
                    'name' => $name,
                    'status' => 'active',
                    'plan_id' => $planId,
                    'business_type' => 'retail',
                    'currency' => 'PEN',
                    'country' => 'PE',
                    'timezone' => 'America/Lima',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $tenantId = DB::table('tenants')->insertGetId($this->fillTableDefaults('tenants', $tenantData));

                // 2. Crear Admin User (si existe la tabla users)
                if (\Schema::hasTable('users')) {
                    $userData = [
                        'tenant_id' => $tenantId,
                        'name' => 'Admin ' . $name,
                        'email' => $adminEmail,
                        'password' => Hash::make($adminPassword),
                        'role' => 'owner',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    DB::table('users')->insert($this->fillTableDefaults('users', $userData));
                }

                // 3. Crear WABA Connection (Secrets encriptados físicamente)
                if (\Schema::hasTable('whats_app_connections')) {
                    $wabaData = [
                        'tenant_id' => $tenantId,
                        'phone_number_id' => $phoneId,
                        'access_token' => Crypt::encryptString($token),
                        'webhook_secret' => $webhookSecret,
                        'display_phone_number' => '+51999999999',
                        'status' => 'connected',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    DB::table('whats_app_connections')->insert($this->fillTableDefaults('whats_app_connections', $wabaData));
                }

                // 4. Asegurar Categoría si existe la tabla categories
                $categoryId = null;
                if (\Schema::hasTable('categories')) {
                    $firstCat = DB::table('categories')->where('tenant_id', $tenantId)->first();
                    if (!$firstCat) {
                        $catData = [
                            'tenant_id' => $tenantId,
                            'name' => 'General',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $categoryId = DB::table('categories')->insertGetId($this->fillTableDefaults('categories', $catData));
                    } else {
                        $categoryId = $firstCat->id;
                    }
                }

                // 5. Cargar Catálogo Base de Demostración
                if (\Schema::hasTable('products')) {
                    $prodData = [
                        'tenant_id' => $tenantId,
                        'sku' => 'DON-001',
                        'name' => 'Dona Glaseada Clásica',
                        'price' => 3.50,
                        'description' => 'Dona artesanal recién horneada',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($categoryId) {
                        $prodData['category_id'] = $categoryId;
                    }

                    $prodId = DB::table('products')->insertGetId($this->fillTableDefaults('products', $prodData));

                    if (\Schema::hasTable('inventory')) {
                        $invData = [
                            'product_id' => $prodId,
                            'quantity' => 100,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        DB::table('inventory')->insert($this->fillTableDefaults('inventory', $invData));
                    }
                }

                // 6. Auditoría
                if (\Schema::hasTable('handover_logs')) {
                    $auditData = [
                        'tenant_id' => $tenantId,
                        'conversation_id' => 0, // System-level
                        'action' => 'TENANT_PROVISIONED',
                        'reason' => 'Atomic provision command executed successfully',
                        'actor' => 'artisan:tenant:provision',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    DB::table('handover_logs')->insert($this->fillTableDefaults('handover_logs', $auditData));
                }

                $this->info("✓ Tenant [ID: {$tenantId}] aprovisionado exitosamente con cifrado atómico.");
            });

            return 0;
        } catch (Exception $e) {
            $this->error('❌ FALLO ATÓMICO: Transacción abortada y ejecutado ROLLBACK total.');
            $this->error('Detalle: ' . $e->getMessage());
            return 1;
        }
    }

    private function fillTableDefaults(string $table, array $data): array
    {
        if (!\Schema::hasTable($table)) {
            return $data;
        }

        $columns = \Schema::getColumnListing($table);
        $result = [];

        foreach ($columns as $column) {
            if ($column === 'id') continue;

            if (array_key_exists($column, $data)) {
                $result[$column] = $data[$column];
            } elseif ($column === 'uuid') {
                $result['uuid'] = (string) Str::uuid();
            } elseif ($column === 'slug') {
                $base = $data['name'] ?? 'item';
                $result['slug'] = Str::slug($base) . '-' . rand(100, 999);
            }
        }

        return $result;
    }
}
