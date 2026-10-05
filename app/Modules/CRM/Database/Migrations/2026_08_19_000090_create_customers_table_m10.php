<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('phone')->index();
                $table->string('email')->nullable();
                $table->string('preferred_channel')->default('WHATSAPP');
                $table->integer('total_orders_count')->default(0);
                $table->decimal('total_spent_amount', 12, 2)->default(0.00);
                $table->string('favorite_product')->nullable();
                $table->string('tag')->default('NUEVO'); // VIP, FRECUENTE, NUEVO
                $table->text('notes')->nullable();
                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'phone']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
