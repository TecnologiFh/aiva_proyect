<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            // Resetear todas las conversaciones activas a modo IA (AI)
            DB::table('conversations')->update(['mode' => 'AI', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
