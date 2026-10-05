<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'stock_control_enabled')) {
                $table->boolean('stock_control_enabled')->default(true)->after('status');
            }
            if (!Schema::hasColumn('products', 'requires_advance')) {
                $table->boolean('requires_advance')->default(false)->after('stock_control_enabled');
            }
            if (!Schema::hasColumn('products', 'advance_hours')) {
                $table->unsignedInteger('advance_hours')->default(0)->after('requires_advance');
            }
            if (!Schema::hasColumn('products', 'requires_deposit')) {
                $table->boolean('requires_deposit')->default(false)->after('advance_hours');
            }
            if (!Schema::hasColumn('products', 'deposit_percentage')) {
                $table->decimal('deposit_percentage', 5, 2)->default(0.00)->after('requires_deposit');
            }
            if (!Schema::hasColumn('products', 'ai_instructions')) {
                $table->text('ai_instructions')->nullable()->after('deposit_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'stock_control_enabled',
                'requires_advance',
                'advance_hours',
                'requires_deposit',
                'deposit_percentage',
                'ai_instructions'
            ]);
        });
    }
};
