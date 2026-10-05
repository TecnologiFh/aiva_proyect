<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Lunes, 7 = Domingo
            $table->unsignedTinyInteger('shift_number')->default(1); // Turno 1, Turno 2
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'day_of_week', 'shift_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
