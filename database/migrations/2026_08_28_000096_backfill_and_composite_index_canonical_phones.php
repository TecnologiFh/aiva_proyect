<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Modules\Core\Identity\PhoneNormalizer;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill retroactivo de customer_phone_canonical en orders
        $orders = DB::table('orders')->whereNull('customer_phone_canonical')->orWhere('customer_phone_canonical', '')->get();
        foreach ($orders as $o) {
            if (!empty($o->customer_phone)) {
                $canonical = PhoneNormalizer::toCanonical($o->customer_phone);
                DB::table('orders')->where('id', $o->id)->update(['customer_phone_canonical' => $canonical]);
            }
        }

        // 2. Backfill retroactivo en customers
        $customers = DB::table('customers')->whereNull('customer_phone_canonical')->orWhere('customer_phone_canonical', '')->get();
        foreach ($customers as $c) {
            if (!empty($c->phone)) {
                $canonical = PhoneNormalizer::toCanonical($c->phone);
                DB::table('customers')->where('id', $c->id)->update(['customer_phone_canonical' => $canonical]);
            }
        }

        // 3. Crear Índices Compuestos (tenant_id, customer_phone_canonical)
        if (Schema::hasColumn('orders', 'customer_phone_canonical')) {
            try {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index(['tenant_id', 'customer_phone_canonical'], 'idx_orders_tenant_phone_canonical');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasColumn('customers', 'customer_phone_canonical')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->index(['tenant_id', 'customer_phone_canonical'], 'idx_customers_tenant_phone_canonical');
                });
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_tenant_phone_canonical');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_tenant_phone_canonical');
        });
    }
};
