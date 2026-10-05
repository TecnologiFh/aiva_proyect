<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = 5;

        // 1. Obtener número de versión siguiente
        $lastVersion = DB::table('canonical_catalogs')->where('tenant_id', $tenantId)->max('version_number') ?? 0;
        $nextVersion = $lastVersion + 1;

        // Desactivar o marcar versiones previas como SUPERSEDED
        DB::table('canonical_catalogs')
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'SUPERSEDED']);

        // 2. Construir payload canónico con la carta exacta de "El Antojito"
        $payload = [
            'version' => $nextVersion,
            'tenant_id' => $tenantId,
            'status' => 'PUBLISHED',
            'menu_groups' => [
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Menú Común',
                    'normalized_name' => 'menu comun',
                    'price' => 12.00,
                    'currency' => 'PEN',
                    'description' => 'Menú económico del día (Entradas: Sopa de casa, Ensalada antojito, Plátano con queso. Fondos: Pollo frito, Churrasco, Carapulcra, Picante de carne, Pescado a la chorrillana)',
                    'status' => 'PUBLISHED',
                    'items' => [
                        ['name' => 'Sopa de casa', 'normalized_name' => 'sopa de casa', 'base_price' => 12.00],
                        ['name' => 'Ensalada antojito', 'normalized_name' => 'ensalada antojito', 'base_price' => 12.00],
                        ['name' => 'Plátano con queso', 'normalized_name' => 'platano con queso', 'base_price' => 12.00],
                        ['name' => 'Pollo frito con puré de espinacas', 'normalized_name' => 'pollo frito con pure de espinacas', 'base_price' => 12.00],
                        ['name' => 'Churrasco con puré de espinacas', 'normalized_name' => 'churrasco con pure de espinacas', 'base_price' => 12.00],
                        ['name' => 'Carapulcra criolla', 'normalized_name' => 'carapulcra criolla', 'base_price' => 12.00],
                        ['name' => 'Picante de carne', 'normalized_name' => 'picante de carne', 'base_price' => 12.00],
                        ['name' => 'Pescado a la chorrillana', 'normalized_name' => 'pescado a la chorrillana', 'base_price' => 12.00],
                    ]
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Menú Especial',
                    'normalized_name' => 'menu especial',
                    'price' => 15.00,
                    'currency' => 'PEN',
                    'description' => 'Menú ejecutivo especial',
                    'status' => 'PUBLISHED',
                    'items' => [
                        ['name' => 'Pollo Broaster (Entrada + Pollo Broaster con papas fritas, arroz y ensalada + Bebida)', 'normalized_name' => 'pollo broaster', 'base_price' => 15.00],
                        ['name' => 'Cevichito (Entrada de Cevichito + Plato de Fondo + Bebida)', 'normalized_name' => 'cevichito', 'base_price' => 15.00]
                    ]
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Menú Súper Especial',
                    'normalized_name' => 'menu super especial',
                    'price' => 17.00,
                    'currency' => 'PEN',
                    'description' => 'Entrada de cevichito + pollo broaster (papas fritas, arroz y ensalada) + bebida',
                    'status' => 'PUBLISHED',
                    'items' => [
                        ['name' => 'Bisteck a la Pobre', 'normalized_name' => 'bisteck a la pobre', 'base_price' => 17.00]
                    ]
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Menú Marino',
                    'normalized_name' => 'menu marino',
                    'price' => 17.00,
                    'currency' => 'PEN',
                    'description' => 'Cevichito + arroz con mariscos + bebida',
                    'status' => 'PUBLISHED',
                    'items' => [
                        ['name' => 'Ceviche Mixto + Arroz con Mariscos', 'normalized_name' => 'ceviche mixto arroz con mariscos', 'base_price' => 17.00]
                    ]
                ]
            ],
            'unassociated_products' => [],
            'generated_at' => now()->toIso8601String()
        ];

        // 3. Insertar Catálogo Canónico Publicado
        DB::table('canonical_catalogs')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'version_number' => $nextVersion,
            'status' => 'PUBLISHED',
            'source_document_id' => 'doc_antojito_official',
            'published_at' => now(),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Asegurar productos registrados en tabla `products` para Tenant 5
        $productsToSeed = [
            ['name' => 'Menú Común', 'normalized_name' => 'menu comun', 'base_price' => 12.00],
            ['name' => 'Menú Especial', 'normalized_name' => 'menu especial', 'base_price' => 15.00],
            ['name' => 'Pollo Broaster (Menú Especial)', 'normalized_name' => 'pollo broaster', 'base_price' => 15.00],
            ['name' => 'Cevichito (Menú Especial)', 'normalized_name' => 'cevichito', 'base_price' => 15.00],
            ['name' => 'Menú Súper Especial', 'normalized_name' => 'menu super especial', 'base_price' => 17.00],
            ['name' => 'Menú Marino', 'normalized_name' => 'menu marino', 'base_price' => 17.00],
        ];

        // Obtener o crear categoría por defecto para Tenant 5
        $categoryId = DB::table('categories')->where('tenant_id', $tenantId)->value('id');
        if (!$categoryId) {
            $categoryId = DB::table('categories')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'Menús',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($productsToSeed as $p) {
            DB::table('products')->updateOrInsert(
                ['tenant_id' => $tenantId, 'normalized_name' => $p['normalized_name']],
                [
                    'uuid' => (string) Str::uuid(),
                    'slug' => Str::slug($p['name']) . '-' . rand(100, 999),
                    'category_id' => $categoryId,
                    'name' => $p['name'],
                    'base_price' => $p['base_price'],
                    'status' => 'ACTIVE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void {}
};
