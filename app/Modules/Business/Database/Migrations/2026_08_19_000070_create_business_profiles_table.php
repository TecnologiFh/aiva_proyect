<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            // Identidad Institucional
            $table->string('commercial_name', 150);
            $table->string('legal_name', 150)->nullable();
            $table->string('tax_id', 20)->nullable(); // RUC / NIT / RFC
            $table->string('business_category', 100)->nullable(); // Pastelería, Clínica, Boutique, etc.
            $table->text('description')->nullable();
            
            // Branding Assets
            $table->string('logo_url', 500)->nullable();
            $table->string('favicon_url', 500)->nullable();
            $table->string('primary_color', 10)->default('#4F46E5');
            $table->string('secondary_color', 10)->default('#10B981');
            
            // Datos de Contacto
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('website_url', 500)->nullable();
            
            // Redes Sociales (JSON)
            $table->json('social_links')->nullable();
            
            // Reglas y Horarios de Operación (JSON)
            $table->json('opening_hours')->nullable();
            $table->json('non_working_days')->nullable();
            $table->unsignedInteger('avg_preparation_mins')->default(30);
            $table->unsignedInteger('avg_delivery_mins')->default(45);
            $table->decimal('min_order_amount', 10, 2)->default(0.00);
            
            // Políticas Comerciales (JSON)
            $table->json('business_policies')->nullable(); // Cambios, Devoluciones, Reservas, Cancelaciones
            
            $table->timestamps();
            
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
