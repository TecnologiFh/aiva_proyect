<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Services\Customer360Service;
use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class Customer360Controller extends Controller
{
    protected Customer360Service $crmService;
    protected UserTenantAccessService $tenantAccessService;

    public function __construct()
    {
        $this->crmService = new Customer360Service();
        $this->tenantAccessService = new UserTenantAccessService();
    }

    /**
     * Listar todos los clientes del Tenant activo con filtros.
     */
    public function index(Request $request)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $search = $request->input('search');
        $tag = $request->input('tag');

        // Autocuración: Asegurar que la tabla sea compatible con M10
        if (!DB::getSchemaBuilder()->hasColumn('customers', 'total_spent_amount')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('DROP TABLE IF EXISTS customers;');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::getSchemaBuilder()->create('customers', function (\Illuminate\Database\Schema\Blueprint $table) {
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
                $table->string('tag')->default('NUEVO');
                $table->text('notes')->nullable();
                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'phone']);
            });
        }

        // Sincronizar automáticamente todos los clientes desde conversaciones y pedidos reales
        $this->seedCustomersFromOrders($tenantId);

        $query = Customer::where('tenant_id', $tenantId);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($tag)) {
            $query->where('tag', strtoupper($tag));
        }

        $customers = $query->orderByDesc('total_spent_amount')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'tenant_id' => $tenantId,
                'customers' => $customers,
            ]);
        }

        return view('customer_360', [
            'tenantId' => $tenantId,
            'customers' => $customers,
        ]);
    }

    /**
     * Obtener Ficha Unificada Customer 360 por ID.
     */
    public function show(Request $request, int $id)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $profile = $this->crmService->getCustomer360Profile($tenantId, $id);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $profile,
        ]);
    }

    /**
     * Actualizar notas o etiqueta de fidelidad (VIP, FRECUENTE).
     */
    public function updateTag(Request $request, int $id)
    {
        $tenantId = (int) $request->input('tenant_id', 4);
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($id);

        if ($request->has('tag')) {
            $customer->tag = strtoupper($request->input('tag'));
        }
        if ($request->has('notes')) {
            $customer->notes = $request->input('notes');
        }

        $customer->save();

        return response()->json(['status' => 'UPDATED', 'customer' => $customer]);
    }

    /**
     * Generar y sincronizar registros de clientes a partir de conversaciones y pedidos en tiempo real.
     */
    protected function seedCustomersFromOrders(int $tenantId)
    {
        // 1. Sincronizar clientes desde Conversaciones
        $conversations = DB::table('conversations')->where('tenant_id', $tenantId)->get();
        foreach ($conversations as $c) {
            if ($c->customer_phone) {
                $customer = $this->crmService->getOrCreateCustomer($tenantId, $c->customer_phone, $c->customer_name ?? ('Cliente ' . substr($c->customer_phone, -4)));
                $this->crmService->recalculateCustomerMetrics($tenantId, $customer->id);
            }
        }

        // 2. Sincronizar clientes desde Pedidos
        $orders = DB::table('orders')->where('tenant_id', $tenantId)->get();
        $grouped = $orders->groupBy('customer_phone');

        foreach ($grouped as $phone => $items) {
            if ($phone) {
                $first = $items->first();
                $customer = $this->crmService->getOrCreateCustomer($tenantId, $phone, $first->customer_name ?? 'Cliente Registrado');
                $this->crmService->recalculateCustomerMetrics($tenantId, $customer->id);
            }
        }
    }
}
