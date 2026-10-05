<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulateSaleCommand extends Command
{
    protected $signature = 'simulate:sale {tenant_id=4}';
    protected $description = 'Simula un flujo de venta completo asistido por IA (Mensaje -> Pedido -> Pago)';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        $this->info("=================================================");
        $this->info("🤖 AIVA AI SALES FLOW SIMULATOR - Core v20.0");
        $this->info("=================================================");
        $this->line("Tenant ID Objetivo: [{$tenantId}]");
        $this->newLine();

        // 1. Verificar o crear producto en catálogo
        $this->info("1. Verificando producto en Catálogo...");
        $product = DB::table('products')->where('tenant_id', $tenantId)->first();
        if (!$product) {
            $cols = DB::getSchemaBuilder()->getColumnListing('products');
            $data = ['tenant_id' => $tenantId, 'created_at' => now(), 'updated_at' => now()];
            if (in_array('uuid', $cols)) $data['uuid'] = (string) Str::uuid();
            if (in_array('name', $cols)) $data['name'] = 'Dona Glaseada Especial';
            if (in_array('slug', $cols)) $data['slug'] = 'dona-glaseada-especial';
            if (in_array('price', $cols)) $data['price'] = 4.50;
            if (in_array('base_price', $cols)) $data['base_price'] = 4.50;
            if (in_array('unit_price', $cols)) $data['unit_price'] = 4.50;
            if (in_array('stock', $cols)) $data['stock'] = 50;

            $productId = DB::table('products')->insertGetId($data);
            $product = DB::table('products')->find($productId);
        }

        $price = (float)($product->price ?? $product->base_price ?? $product->unit_price ?? 0);
        if ($price <= 0) {
            $price = 4.50;
        }
        $name = $product->name ?? 'Dona Glaseada Especial';

        $this->line("   📦 Producto: <comment>{$name}</comment> - Precio: <comment>S/ " . number_format($price, 2) . "</comment>");
        $this->newLine();

        // 2. Simular Mensaje Entrante de WhatsApp
        $this->info("2. Simulando mensaje entrante de WhatsApp...");
        $this->line("   💬 Cliente (+51987654321): <info>\"Hola, quisiera 2 Donas Glaseadas Especiales para entregar en Surco.\"</info>");
        $this->line("   🤖 AIVA AI Response: <comment>\"¡Entendido! He registrado tu pedido de 2x {$name}. Total: S/ " . number_format($price * 2, 2) . ". ¿Confirmamos el pago por Yape/Niubiz?\"</comment>");
        $this->newLine();

        // 3. Generar Orden en BD
        $this->info("3. Creando Pedido y Reserva de Stock en BD...");
        $orderCols = DB::getSchemaBuilder()->getColumnListing('orders');
        $orderData = ['tenant_id' => $tenantId, 'status' => 'PAID', 'created_at' => now(), 'updated_at' => now()];
        if (in_array('total', $orderCols)) $orderData['total'] = $price * 2;
        if (in_array('total_amount', $orderCols)) $orderData['total_amount'] = $price * 2;
        if (in_array('uuid', $orderCols)) $orderData['uuid'] = (string) Str::uuid();
        if (in_array('order_number', $orderCols)) $orderData['order_number'] = 'ORD-' . strtoupper(Str::random(8));

        foreach ($orderCols as $col) {
            if (isset($orderData[$col]) || in_array($col, ['id', 'created_at', 'updated_at'])) continue;
            if ($col === 'customer_name') $orderData['customer_name'] = 'Cliente WhatsApp (+51987654321)';
            elseif ($col === 'customer_phone') $orderData['customer_phone'] = '+51987654321';
            elseif ($col === 'customer_email') $orderData['customer_email'] = 'cliente@whatsapp.pe';
            elseif ($col === 'delivery_address') $orderData['delivery_address'] = 'Av. Javier Prado 1234, Surco';
            elseif ($col === 'payment_method') $orderData['payment_method'] = 'Yape';
            elseif ($col === 'currency') $orderData['currency'] = 'PEN';
        }

        $orderId = DB::table('orders')->insertGetId($orderData);

        $this->line("   🛒 Order ID: <comment>#{$orderId}</comment> | Estado: <info>PAID</info> | Total: <comment>S/ " . number_format($price * 2, 2) . "</comment>");
        $this->newLine();

        // 4. Simular Evento de Pago Exitoso
        $this->info("4. Procesando Webhook de Pago (Niubiz/Yape)...");
        if (DB::getSchemaBuilder()->hasTable('payment_webhook_idempotencies')) {
            DB::table('payment_webhook_idempotencies')->insert([
                'tenant_id' => $tenantId,
                'provider' => 'niubiz',
                'provider_event_id' => 'evt_sim_' . time(),
                'payload_hash' => md5((string)time()),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->newLine();
        $this->info("🎉 ¡VENTA COMPLETADA CON ÉXITO!");
        $this->line("Comprobante de pago procesado y orden enviada a la cola de preparación.");
        $this->line("Actualiza tu Dashboard en: <comment>http://127.0.0.1:8000/dashboard</comment> para ver el nuevo balance.");
        $this->info("=================================================");

        return 0;
    }
}
