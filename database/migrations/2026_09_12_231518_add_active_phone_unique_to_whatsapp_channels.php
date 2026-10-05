<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Limpieza de duplicados previos antes de fijar índice UNIQUE
        $channels = \Illuminate\Support\Facades\DB::table('whatsapp_channels')
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->get();

        $seenPhones = [];
        foreach ($channels as $c) {
            $rawPhone = $c->display_phone_number ?? $c->phone_number_id;
            if (!empty($rawPhone)) {
                $canonical = \App\Modules\Core\Identity\PhoneNormalizer::toCanonical($rawPhone);
                if (isset($seenPhones[$canonical])) {
                    // Duplicado previo: desactivar registro anterior
                    \Illuminate\Support\Facades\DB::table('whatsapp_channels')
                        ->where('id', $c->id)
                        ->update(['status' => 'INACTIVE', 'updated_at' => now()]);
                } else {
                    $seenPhones[$canonical] = $c->id;
                }
            }
        }

        if (!Schema::hasColumn('whatsapp_channels', 'active_phone_e164')) {
            Schema::table('whatsapp_channels', function (Blueprint $table) {
                $table->string('active_phone_e164', 30)->nullable()->unique()->after('display_phone_number');
            });
        }

        // 2. Poblar active_phone_e164 para los canales activos no duplicados
        foreach ($seenPhones as $canonical => $channelId) {
            \Illuminate\Support\Facades\DB::table('whatsapp_channels')
                ->where('id', $channelId)
                ->where('status', 'ACTIVE')
                ->update(['active_phone_e164' => $canonical]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_channels', 'active_phone_e164')) {
            Schema::table('whatsapp_channels', function (Blueprint $table) {
                $table->dropUnique(['active_phone_e164']);
                $table->dropColumn('active_phone_e164');
            });
        }
    }
};
