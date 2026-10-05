<?php

namespace App\Http\Controllers;

use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    protected UserTenantAccessService $tenantAccessService;

    public function __construct()
    {
        $this->tenantAccessService = new UserTenantAccessService();

        // Garantizar que la columna stock exista físicamente en MySQL
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'stock')) {
            try {
                Schema::table('products', function ($table) {
                    $table->integer('stock')->default(0)->after('price');
                });
            } catch (\Throwable $e) {
                // Columna ya existente o manejada por el motor
            }
        }
    }

    public function index(Request $request)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);

        $search = trim((string)$request->input('search', ''));
        $perPage = (int)$request->input('per_page', 15);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 15;
        }

        // Métricas globales de inventario independientes de la paginación activa
        $totalProducts = DB::table('products')->where('tenant_id', $tenantId)->count();
        $totalStock = (int) DB::table('products')->where('tenant_id', $tenantId)->sum('stock');

        // Nombre comercial del negocio
        $businessName = DB::table('tenants')
            ->leftJoin('business_profiles', 'tenants.id', '=', 'business_profiles.tenant_id')
            ->where('tenants.id', $tenantId)
            ->value(DB::raw('COALESCE(business_profiles.commercial_name, tenants.name)')) ?? "Tenant #{$tenantId}";

        // Consulta de productos con filtro y paginación
        $query = DB::table('products')->where('tenant_id', $tenantId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        foreach ($products as $p) {
            $p->calculated_stock = (int)($p->stock ?? $p->current_stock ?? $p->quantity ?? 0);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'products' => $products
            ]);
        }

        return view('catalog', [
            'products' => $products,
            'tenantId' => $tenantId,
            'totalProducts' => $totalProducts,
            'totalStock' => $totalStock,
            'businessName' => $businessName,
            'search' => $search,
            'perPage' => $perPage
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $cols = Schema::getColumnListing('products');
        $data = [
            'tenant_id' => $tenantId,
            'stock' => (int) $validated['stock'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Categoría
        if (in_array('category_id', $cols)) {
            $catId = DB::table('categories')->where('tenant_id', $tenantId)->value('id');
            if (!$catId && Schema::hasTable('categories')) {
                $catData = [
                    'tenant_id' => $tenantId,
                    'name' => 'General',
                    'slug' => 'general',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('categories', 'uuid')) {
                    $catData['uuid'] = (string) Str::uuid();
                }
                $catId = DB::table('categories')->insertGetId($catData);
            }
            $data['category_id'] = $catId ?? 1;
        }

        if (in_array('name', $cols)) $data['name'] = $validated['name'];
        if (in_array('slug', $cols)) $data['slug'] = Str::slug($validated['name']);
        if (in_array('uuid', $cols)) $data['uuid'] = (string) Str::uuid();
        
        if (in_array('price', $cols)) $data['price'] = $validated['price'];
        if (in_array('base_price', $cols)) $data['base_price'] = $validated['price'];
        if (in_array('unit_price', $cols)) $data['unit_price'] = $validated['price'];
        
        if (in_array('current_stock', $cols)) $data['current_stock'] = (int) $validated['stock'];
        if (in_array('quantity', $cols)) $data['quantity'] = (int) $validated['stock'];
        
        if (in_array('sku', $cols)) $data['sku'] = 'SKU-' . strtoupper(Str::random(6));

        $productId = DB::table('products')->insertGetId($data);

        return redirect()->route('catalog.index')->with('success', 'Producto guardado exitosamente con ' . $validated['stock'] . ' unidades.');
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();
        $stock = (int) $request->input('stock', 0);
        $name = trim($request->input('name', ''));
        $price = (float) $request->input('price', 0);

        $cols = Schema::getColumnListing('products');
        $update = ['updated_at' => now()];

        if (!empty($name) && in_array('name', $cols)) $update['name'] = $name;
        if ($price > 0) {
            if (in_array('price', $cols)) $update['price'] = $price;
            if (in_array('base_price', $cols)) $update['base_price'] = $price;
        }

        if (in_array('stock', $cols)) $update['stock'] = $stock;
        if (in_array('current_stock', $cols)) $update['current_stock'] = $stock;
        if (in_array('quantity', $cols)) $update['quantity'] = $stock;

        DB::table('products')->where('id', $id)->where('tenant_id', $tenantId)->update($update);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Producto actualizado correctamente.']);
        }

        return redirect()->route('catalog.index')->with('success', 'Producto actualizado.');
    }

    public function archive(Request $request, $id)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();
        DB::table('products')->where('id', $id)->where('tenant_id', $tenantId)->update([
            'status' => 'ARCHIVED',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'SUCCESS', 'message' => 'Producto archivado. Excluido del catálogo comercial e IA.']);
    }

    public function restore(Request $request, $id)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();
        DB::table('products')->where('id', $id)->where('tenant_id', $tenantId)->update([
            'status' => 'ACTIVE',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'SUCCESS', 'message' => 'Producto restaurado a estado comercial ACTIVO.']);
    }
}
