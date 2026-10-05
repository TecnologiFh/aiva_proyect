<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_channels')) {
            Schema::create('whatsapp_channels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('phone_number_id')->index();
                $table->string('waba_id')->nullable();
                $table->string('display_phone_number')->nullable();
                $table->string('status')->default('ACTIVE');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // Vincular el WHATSAPP_PHONE_NUMBER_ID del env al Tenant 5 ("Antojito")
        $envPhoneId = env('WHATSAPP_PHONE_NUMBER_ID', '1238823855985383');
        if ($envPhoneId) {
            DB::table('whatsapp_channels')->updateOrInsert(
                ['phone_number_id' => $envPhoneId],
                [
                    'tenant_id' => 5, // Tenant 5: Antojito
                    'display_phone_number' => '900434897',
                    'status' => 'ACTIVE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Limpiar registros de tenant 10 y re-asociar a Tenant 5 (Antojito)
        try {
            DB::table('messages')->where('tenant_id', 10)->update(['tenant_id' => 5]);
            DB::table('conversations')->where('tenant_id', 10)->update(['tenant_id' => 5]);
            DB::table('tenants')->where('id', 10)->delete();
            DB::table('tenant_settings')->updateOrInsert(
                ['setting_key' => 'latest_active_tenant_id'],
                ['setting_value' => '5', 'tenant_id' => 5, 'updated_at' => now()]
            );
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_channels');
    }
};
