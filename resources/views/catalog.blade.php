@extends('layouts.app')

@section('title', 'AIVA — Catálogo & Inventario')

@section('content')
<div class="space-y-6">

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Catálogo & Inventario</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Gestión de productos oficiales, precios y control de stock transaccional en Kardex.</p>
        </div>

        <div class="flex items-center space-x-3">
            <button onclick="openProductModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-md shadow-blue-500/20 transition flex items-center space-x-1.5">
                <span>+ Nuevo Producto</span>
            </button>
        </div>
    </div>

    <!-- INVENTORY STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total de Productos</span>
                <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-3">{{ number_format($totalProducts ?? $products->total()) }}</div>
            <p class="text-[11px] text-slate-400 mt-1">Disponibles para venta oficial</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Stock Total Disponible</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">
                {{ number_format($totalStock ?? 0) }} un.
            </div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400/80 mt-1 font-semibold">Unidades físicas verificadas en Kardex</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Empresa Activa</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-3">Tenant #{{ $tenantId }}</div>
            <p class="text-[11px] text-slate-400 mt-1 truncate" title="{{ $businessName ?? 'Mi Negocio' }}">{{ $businessName ?? 'Mi Negocio' }}</p>
        </div>
    </div>

    <!-- PRODUCTS TABLE -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Productos Registrados en Menú</h2>
                <span class="text-xs font-bold text-slate-400">Haga clic en Editar para configurar Reglas de IA</span>
            </div>

            <!-- SEARCH & PER-PAGE TOOLBAR -->
            <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-2">
                @if(request('tenant_id'))
                    <input type="hidden" name="tenant_id" value="{{ request('tenant_id') }}">
                @endif
                <div class="relative w-full sm:w-64">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar plato o SKU..." 
                           class="w-full pl-9 pr-8 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @if(!empty($search))
                        <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => 1]) }}" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold" title="Limpiar búsqueda">✕</a>
                    @endif
                </div>

                <select name="per_page" onchange="this.form.submit()" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="15" {{ ($perPage ?? 15) == 15 ? 'selected' : '' }}>15 / pág</option>
                    <option value="25" {{ ($perPage ?? 15) == 25 ? 'selected' : '' }}>25 / pág</option>
                    <option value="50" {{ ($perPage ?? 15) == 50 ? 'selected' : '' }}>50 / pág</option>
                    <option value="100" {{ ($perPage ?? 15) == 100 ? 'selected' : '' }}>100 / pág</option>
                </select>

                <button type="submit" class="px-3 py-1.5 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition">
                    Buscar
                </button>
            </form>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-900/80 text-xs font-bold uppercase text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Producto</th>
                        <th class="py-3 px-4">SKU / Código</th>
                        <th class="py-3 px-4">Precio (S/)</th>
                        <th class="py-3 px-4">Stock</th>
                        <th class="py-3 px-4">Anticipación</th>
                        <th class="py-3 px-4">Adelanto (%)</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
                    @forelse($products as $product)
                        @php
                            $price = $product->price ?? $product->base_price ?? $product->unit_price ?? 0;
                            $stock = $product->calculated_stock ?? 0;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                            <td class="py-3.5 px-4 font-sans font-bold text-slate-900 dark:text-white">{{ $product->name ?? 'Producto' }}</td>
                            <td class="py-3.5 px-4 text-slate-400 text-xs">{{ $product->sku ?? 'SKU-'.$product->id }}</td>
                            <td class="py-3.5 px-4 text-blue-600 dark:text-blue-400 font-bold">S/ {{ number_format((float)$price, 2) }}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-slate-200">{{ $stock }} un.</td>
                            <td class="py-3.5 px-4 font-sans">
                                <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 font-bold text-xs rounded-md">
                                    {{ $product->advance_days ?? 0 }} días
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-sans">
                                <span class="px-2 py-0.5 bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 font-bold text-xs rounded-md">
                                    {{ $product->advance_percentage ?? 0 }}%
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-sans">
                                <button onclick="openProductModal('{{ $product->id }}', '{{ addslashes($product->name ?? '') }}', '{{ $price }}', '{{ $product->sku ?? '' }}', '{{ $stock }}', '{{ $product->advance_days ?? 0 }}', '{{ $product->advance_percentage ?? 0 }}', '{{ addslashes($product->ai_instructions ?? '') }}')" class="px-3.5 py-1.5 bg-slate-900 dark:bg-slate-800 text-white font-bold rounded-lg text-xs hover:bg-slate-800 transition">
                                    ✏️ Editar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 font-sans">No hay productos registrados en el catálogo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION CONTROLS -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="text-slate-500 dark:text-slate-400 font-medium">
                @if($products->total() > 0)
                    Mostrando <span class="font-bold text-slate-800 dark:text-slate-200">{{ $products->firstItem() }}</span> al <span class="font-bold text-slate-800 dark:text-slate-200">{{ $products->lastItem() }}</span> de <span class="font-bold text-slate-800 dark:text-slate-200">{{ $products->total() }}</span> productos
                    @if(!empty($search))
                        <span class="text-blue-600 dark:text-blue-400 font-semibold">(filtrados por "{{ $search }}")</span>
                    @endif
                @else
                    0 productos encontrados
                @endif
            </div>

            @if($products->hasPages())
            <div class="flex items-center space-x-1.5">
                {{-- Botón Anterior --}}
                @if ($products->onFirstPage())
                    <span class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/40 text-slate-400 cursor-not-allowed font-semibold text-xs select-none">
                        ← Anterior
                    </span>
                @else
                    <a href="{{ $products->previousPageUrl() }}" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition">
                        ← Anterior
                    </a>
                @endif

                {{-- Números de Página --}}
                @php
                    $currentPage = $products->currentPage();
                    $lastPage = $products->lastPage();
                    $start = max(1, $currentPage - 2);
                    $end = min($lastPage, $currentPage + 2);
                @endphp

                @if($start > 1)
                    <a href="{{ $products->url(1) }}" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition">1</a>
                    @if($start > 2)
                        <span class="px-1 text-slate-400">...</span>
                    @endif
                @endif

                @for ($page = $start; $page <= $end; $page++)
                    @if ($page == $currentPage)
                        <span class="w-8 h-8 flex items-center justify-center rounded-xl bg-blue-600 text-white font-extrabold text-xs shadow-md shadow-blue-500/20">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $products->url($page) }}" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition">
                            {{ $page }}
                        </a>
                    @endif
                @endfor

                @if($end < $lastPage)
                    @if($end < $lastPage - 1)
                        <span class="px-1 text-slate-400">...</span>
                    @endif
                    <a href="{{ $products->url($lastPage) }}" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition">{{ $lastPage }}</a>
                @endif

                {{-- Botón Siguiente --}}
                @if ($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition">
                        Siguiente →
                    </a>
                @else
                    <span class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/40 text-slate-400 cursor-not-allowed font-semibold text-xs select-none">
                        Siguiente →
                    </span>
                @endif
            </div>
            @endif
        </div>
    </div>

</div>

<!-- MODAL FORMULARIO DE PRODUCTO CON REGLAS DE IA -->
<div id="productModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden animate-in fade-in duration-200">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 id="productModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">📦 Agregar Nuevo Producto & Reglas IA</h3>
            <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>
        <form onsubmit="handleSaveProduct(event)" class="space-y-3.5 text-xs">
            <input type="hidden" id="pm-id">
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Nombre del Producto *</label>
                <input type="text" id="pm-name" required placeholder="Ej: Torta Personalizada de Cumpleaños" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none text-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Precio (S/) *</label>
                    <input type="number" step="0.50" id="pm-price" required placeholder="Ej: 45.00" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Código SKU</label>
                    <input type="text" id="pm-sku" placeholder="Ej: TOR-001" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Stock Inicial</label>
                    <input type="number" id="pm-stock" value="20" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- ESPECIFICACIONES DE IA AVANZADAS -->
            <div class="p-3.5 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 rounded-2xl space-y-3">
                <div class="flex items-center space-x-2">
                    <span class="text-base">🤖</span>
                    <h4 class="font-extrabold text-blue-600 dark:text-blue-400">Reglas para la Agente IA (Groq LPU Engine)</h4>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Días de Anticipación Requeridos</label>
                        <input type="number" id="pm-adv-days" value="0" placeholder="Ej: 2 días antes" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white">
                        <span class="text-[10px] text-slate-400">0 = Entrega el mismo día</span>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Adelanto Requerido (%)</label>
                        <input type="number" step="5" id="pm-adv-pct" value="0" placeholder="Ej: 50%" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-emerald-600">
                        <span class="text-[10px] text-slate-400">0% = Pago contra entrega</span>
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Instrucciones Específicas para la IA</label>
                    <textarea id="pm-ai-notes" rows="2" placeholder="Ej: Solo disponible en sabores Chocolate y Vainilla. Incluye dedicatoria personalizada gratis." class="w-full px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none text-slate-900 dark:text-white"></textarea>
                </div>
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 hidden animate-in slide-in-from-bottom duration-300">
    <div class="bg-slate-900 text-white border border-slate-700 px-5 py-3.5 rounded-2xl shadow-2xl flex items-center space-x-3">
        <span id="toastIcon" class="text-emerald-400 text-lg">✓</span>
        <span id="toastText" class="text-xs font-bold">Producto guardado exitosamente.</span>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function showToast(text, isError = false) {
        const toast = document.getElementById('toastNotification');
        const icon = document.getElementById('toastIcon');
        const msg = document.getElementById('toastText');
        icon.innerText = isError ? '✕' : '✓';
        icon.className = isError ? 'text-rose-400 text-lg' : 'text-emerald-400 text-lg';
        msg.innerText = text;
        toast.classList.remove('hidden');
        setTimeout(() => { toast.classList.add('hidden'); }, 3500);
    }

    function openProductModal(id = null, name = '', price = 0, sku = '', stock = 20, advDays = 0, advPct = 0, notes = '') {
        document.getElementById('pm-id').value = id || '';
        document.getElementById('pm-name').value = name || '';
        document.getElementById('pm-price').value = price || 0;
        document.getElementById('pm-sku').value = sku || '';
        document.getElementById('pm-stock').value = stock || 20;
        document.getElementById('pm-adv-days').value = advDays || 0;
        document.getElementById('pm-adv-pct').value = advPct || 0;
        document.getElementById('pm-ai-notes').value = notes || '';

        document.getElementById('productModalTitle').innerText = id ? '✏️ Editar Producto & Reglas IA' : '📦 Agregar Nuevo Producto & Reglas IA';
        document.getElementById('productModal').classList.remove('hidden');
    }

    function closeProductModal() {
        document.getElementById('productModal').classList.add('hidden');
    }

    async function handleSaveProduct(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/v1/settings/products/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: document.getElementById('pm-id').value,
                    name: document.getElementById('pm-name').value,
                    price: document.getElementById('pm-price').value,
                    sku: document.getElementById('pm-sku').value,
                    stock: document.getElementById('pm-stock').value,
                    advance_days: document.getElementById('pm-adv-days').value,
                    advance_percentage: document.getElementById('pm-adv-pct').value,
                    ai_instructions: document.getElementById('pm-ai-notes').value,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                closeProductModal();
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al conectar con el servidor.', true);
        }
    }
</script>
@endsection
