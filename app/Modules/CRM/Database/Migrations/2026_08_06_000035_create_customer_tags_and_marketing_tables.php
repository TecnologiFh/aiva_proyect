<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('tag_type', 30)->default('USER'); // SYSTEM, USER
            $table->string('color', 10)->default('#6c757d');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('customer_tag_map', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('customer_tags')->cascadeOnDelete();

            $table->primary(['customer_id', 'tag_id']);
        });

        Schema::create('customer_marketing_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->boolean('accepts_promotions')->default(true);
            $table->string('preferred_channel', 30)->default('WHATSAPP');
            $table->string('last_campaign_id', 100)->nullable();
            $table->string('last_coupon_code', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_marketing_preferences');
        Schema::dropIfExists('customer_tag_map');
        Schema::dropIfExists('customer_tags');
    }
};
