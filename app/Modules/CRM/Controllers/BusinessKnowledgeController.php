<?php

namespace App\Modules\CRM\Controllers;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Services\Customer360Service;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class Customer360Controller extends Controller
{
    protected Customer360Service $crmService;

    public function __construct()
    {
        $this->crmService = new Customer360Service();
    }

    /**
     * Listar todos los clientes del Tenant activo con filtros.
     */
    public function index(Request $request)
    {
        $tenantId = (int) $request->input('tenant_id', 4);
        $search = $request->input('search');
        $tag = $request->input('tag');

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

        // Si no existen clientes para este tenant, poblar datos iniciales basados en pedidos reales
        if ($customers->isEmpty()) {
            $this->seedCustomersFromOrders($tenantId);
            $customers = Customer::where('tenant_id', $tenantId)->orderByDesc('total_spent_amount')->get();
        }

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
        $tenantId = (int) $request->input('tenant_id', 4);
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
     * Generar registros iniciales de clientes a partir de pedidos existentes.
     */
    protected function seedCustomersFromOrders(int $tenantId)
    {
        $orders = DB::table('orders')->where('tenant_id', $tenantId)->get();
        $grouped = $orders->groupBy('customer_phone');

        foreach ($grouped as $phone => $items) {
            $first = $items->first();
            $customer = $this->crmService->getOrCreateCustomer($tenantId, $phone, $first->customer_name ?? 'Cliente Registrado');
            $this->crmService->recalculateCustomerMetrics($tenantId, $customer->id);
        }
    }
}
