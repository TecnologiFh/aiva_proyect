<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_sources')) {
            return;
        }

        // Carta 1: Pollos a la Brasa, Mostritos y Promociones
        DB::table('business_sources')
            ->where('tenant_id', 11)
            ->where(function($q) {
                $q->where('file_path', 'LIKE', '%carta1%')
                  ->orWhere('title', 'LIKE', '%carta1%');
            })
            ->update([
                'title' => 'Carta 1 - Pollos a la Brasa, Mostritos y Promociones',
                'raw_content' => 'WALLPA a la brasa o al cilindro: 1 pollo a la brasa de la casa acompañado de papas andinas familiar y ensalada fresca S/ 60.00, 1/2 Pollo de la casa con papas andinas y ensalada fresca S/ 35.00, 1/4 de pollo con papas andinas y ensalada fresca S/ 19.00. Mostrito (1/8 pollo con chaufa de la casa y papas andinas) S/ 16.00, Mostro (1/4 pollo con chaufa de la casa y papas andinas) S/ 23.00, Wallpa aguja (1/4 pollo con chaufa, papas andinas, plátano y huevo frito) S/ 28.00, Wallpa criollo (1/4 pollo con 1 palito de anticucho, papas andinas y ensalada) S/ 25.00, Wallpa parrillero (1/4 pollo con 1 chorizo, papas andinas y ensalada) S/ 25.00. Promociones Wallpa: Promo 1 (1 pollo jugoso con papas andinas familiar, ensalada fresca y gaseosa Pepsi o Seven Up 1.5L) S/ 65.00, Promo 2 (1 pollo con papas andinas, ensalada, chaufa de la casa y gaseosa 1.5L) S/ 80.00, Promo 3 (1 pollo + 1/4 pollo con papas andinas, ensalada y gaseosa 1.5L) S/ 75.00, Promo 4 (1 pollo + 1/2 pollo con papas andinas, ensalada y gaseosa 1.5L) S/ 85.00, Promo 5 (2 pollos jugosos con 2 papas andinas familiar, ensalada y gaseosa 1.5L) S/ 120.00.',
                'updated_at' => now(),
            ]);

        // Carta 2: Chancho al Cilindro, Dúos y Chaufas
        DB::table('business_sources')
            ->where('tenant_id', 11)
            ->where(function($q) {
                $q->where('file_path', 'LIKE', '%carta2%')
                  ->orWhere('title', 'LIKE', '%carta2%');
            })
            ->update([
                'title' => 'Carta 2 - Chancho al Cilindro, Dúos y Chaufas',
                'raw_content' => 'Chancho al cilindro: 250 gr de chancho al cilindro acompañado de camote frito, papas andinas y ensalada fresca S/ 25.00, 500 gr de chancho al cilindro S/ 45.00, 1 kg de chancho al cilindro S/ 85.00, Chanchi Mostro (180 gr chancho al cilindro con chaufa de la casa y papas andinas) S/ 29.00. Dúos perfectos Wallpa: Dúo personal (1/4 pollo + 180 gr chancho al cilindro con papas andinas, camote frito y ensalada) S/ 35.00, Dúo Bravazo (1/2 pollo + 350 gr chancho al cilindro con papas andinas, camote frito y ensalada) S/ 60.00, Dúo familiar (1 pollo + 500 gr chancho al cilindro con papas andinas, camote frito y ensalada) S/ 95.00. Chaufas al estilo Wallpa (Personal / Familiar): Chaufa de pollo Personal S/ 16.00 Familiar S/ 31.00, Chaufa Charcutero con frankfurt y chorizo Personal S/ 17.00 Familiar S/ 32.00, Chaufa Brasa con pollo brasa Personal S/ 18.50 Familiar S/ 37.00, Chaufa de chancho al cilindro Personal S/ 21.00 Familiar S/ 40.00, Chaufa Especial Wallpa con frankfurt, chorizo, pollo y chancho Personal S/ 25.00 Familiar S/ 45.00, Chaufa Amazónico con cecina, chorizo regional y plátano frito S/ 26.00.',
                'updated_at' => now(),
            ]);

        // Carta 3: Alitas, Salchipapas, Piqueos y Criollos
        DB::table('business_sources')
            ->where('tenant_id', 11)
            ->where(function($q) {
                $q->where('file_path', 'LIKE', '%carta3%')
                  ->orWhere('title', 'LIKE', '%carta3%');
            })
            ->update([
                'title' => 'Carta 3 - Alitas, Salchipapas, Piqueos y Criollos',
                'raw_content' => 'Alitas (8 piezas acompañadas de papas andinas): Alitas BBQ S/ 23.00, Alitas BBQ Búfalo picante S/ 23.00, Alitas Crispy crocantes S/ 23.00, Alitas en maracuyá S/ 23.00, Alitas en maracumango S/ 23.00, Alitas en salsa de mango S/ 23.00, Alitas Acevichada S/ 23.00, Solo Alitas (8 alitas hasta 2 sabores) S/ 18.00. Salchipapas: Salchipapa (hot dog frankfurt con papas andinas) S/ 18.00, Salchi Mix (frankfurt con chorizo y papas andinas) S/ 20.00, Salchi Brasa 1/8 pollo con hot dog y papas andinas S/ 20.00, Salchi Brasa 1/4 pollo con hot dog y papas andinas S/ 24.00. BOX BBQ: Box Piqueos (8 alitas + salchipapas + 6 tequeños) S/ 45.00, Box Familiar (16 alitas + salchimix + 8 tequeños + papas andinas) S/ 64.00. Criollo: Pollo Saltado S/ 21.00, Lomo Saltado S/ 23.00, Saltado especial Wallpa con champiñones y 2 huevos montados S/ 28.00, Tallarín Saltado de Pollo S/ 21.00, Tallarín Saltado de Carne S/ 23.00.',
                'updated_at' => now(),
            ]);

        // Carta 4: Parrillas, Pastas y Guarniciones
        DB::table('business_sources')
            ->where('tenant_id', 11)
            ->where(function($q) {
                $q->where('file_path', 'LIKE', '%carta4%')
                  ->orWhere('title', 'LIKE', '%carta4%');
            })
            ->update([
                'title' => 'Carta 4 - Parrillas, Pastas y Guarniciones',
                'raw_content' => 'Parrillas: Pechuga a la parrilla S/ 21.00, Chuleta a la parrilla S/ 22.00, Anticuchos S/ 20.00, Bistec a la parrilla S/ 25.00, Churrasco a la parrilla S/ 25.00, Parrilla para dos (1/4 pollo + churrasco 250g + pechuga 250g + 1 anticucho + 1 chorizo + 1 frankfurt + papas andinas y ensalada) S/ 80.00, Parrilla familiar (1/2 pollo + 2 chuletas 250g + 2 churrasco 250g + 2 pechugas 250g + 2 anticuchos + 2 chorizos + 2 frankfurt + papas andinas familiar y ensalada familiar) S/ 150.00, Parrilla Wallpa (incluye además 350g chancho al cilindro) S/ 180.00. Pastas (Fetuccini al dente al pesto o a la huancaína): con 1/4 pollo S/ 22.00, con Pechuga a la parrilla S/ 25.00, con Churrasco a la parrilla S/ 25.00, con Chuleta a la parrilla S/ 25.00, con Bistec a la parrilla S/ 25.00, con Pollo saltado S/ 23.00, con Lomo Saltado S/ 26.00. Guarniciones: Papas de la casa regular S/ 12.00 Familiar S/ 20.00, Camote frito S/ 6.00, Frankfurt S/ 4.00, Chorizo Artesanal S/ 6.00, Plátano frito S/ 4.00, Huevo frito S/ 2.00, Porción de arroz blanco S/ 5.00, Porción de chaufa S/ 12.00, Ensalada personal S/ 4.00 Familiar S/ 8.00.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
