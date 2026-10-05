<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_channels')) {
            DB::statement("ALTER TABLE whatsapp_channels MODIFY phone_number_id VARCHAR(255) NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_channels')) {
            DB::statement("ALTER TABLE whatsapp_channels MODIFY phone_number_id VARCHAR(255) NOT NULL");
        }
    }
};
