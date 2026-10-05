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
            if (!Schema::hasColumn('whatsapp_channels', 'client_session_id')) {
                Schema::table('whatsapp_channels', function (Blueprint $table) {
                    $table->string('client_session_id')->nullable()->index()->after('tenant_id');
                });
            }

            // Eliminar cualquier binding legado con "default"
            DB::table('whatsapp_channels')
                ->where('client_session_id', 'default')
                ->orWhere('phone_number_id', 'default')
                ->delete();

            // Configurar binding determinístico oficial para Tenant 11 (Wallpa Brasa)
            DB::table('whatsapp_channels')->updateOrInsert(
                ['client_session_id' => 'wallpa-brasa'],
                [
                    'tenant_id' => 11,
                    'phone_number_id' => 'channel_wallpa_brasa_qr',
                    'display_phone_number' => '977149110',
                    'status' => 'ACTIVE',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Configurar binding determinístico oficial para Tenant 4 (Donuts Fs)
            DB::table('whatsapp_channels')->updateOrInsert(
                ['client_session_id' => 'donuts-fs'],
                [
                    'tenant_id' => 4,
                    'phone_number_id' => 'channel_donuts_fs_qr',
                    'display_phone_number' => '999111222',
                    'status' => 'ACTIVE',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_channels') && Schema::hasColumn('whatsapp_channels', 'client_session_id')) {
            Schema::table('whatsapp_channels', function (Blueprint $table) {
                $table->dropColumn('client_session_id');
            });
        }
    }
};
