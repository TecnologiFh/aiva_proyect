@extends('layouts.app')

@section('title', 'AIVA — Operations Center & Order Engine')

@section('content')
<div class="space-y-6">

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Operations Center</h1>
                <span class="px-2.5 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 rounded-full text-xs font-bold">
                    v21.0 4-Dimension Live SQL
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Central de mando operativa en vivo: Alertas de 4 Dimensiones, Pedidos Multicanal y Logística de Reparto.</p>
        </div>

        @php
            $userTenantService = new \App\Modules\Tenant\Services\UserTenantAccessService();
            $currentTenantId = $tenantId ?? $userTenantService->getActiveTenantId();
            $allowedTenants = $userTenantService->getAllowedTenants();
            $currentBizName = \Illuminate\Support\Facades\DB::table('business_profiles')->where('tenant_id', $currentTenantId)->value('commercial_name') 
                ?? \Illuminate\Support\Facades\DB::table('tenants')->where('id', $currentTenantId)->value('name') 
                ?? "Negocio #{$currentTenantId}";
        @endphp

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center space-x-2 bg-slate-100 dark:bg-slate-900 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                    {{ $currentBizName }} (Tenant {{ $currentTenantId }})
                </span>
            </div>
            <a href="/knowledge" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition">
                Knowledge Hub
            </a>
            <a href="/dashboard" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition shadow-sm">
                WhatsApp Inbox
            </a>
        </div>
    </div>

    <!-- OPERATIONAL ALERTS BAR (SQL REAL VIVO) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-[#111827] border-l-4 border-red-500 border-t border-r border-b border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Atención Pendiente</span>
                <div id="alert-attention-count" class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">0</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Conversaciones requieren operador humano</p>
            </div>
            <div class="p-3 bg-red-50 dark:bg-red-950 text-red-600 dark:text-red-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#111827] border-l-4 border-amber-500 border-t border-r border-b border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pagos Pendientes</span>
                <div id="alert-payments-count" class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">0</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Esperando confirmación Yape/Transferencia</p>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-[#111827] border-l-4 border-emerald-500 border-t border-r border-b border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Deliveries Listos</span>
                <div id="alert-deliveries-count" class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">0</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Listos para asignación de motorizado</p>
            </div>
            <div class="p-3 bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            </div>
        </div>
    </div>

    <!-- FILTRO DE FECHA SUPERIOR (HOY POR DEFECTO / HISTÓRICO) -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white dark:bg-[#111827] p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center space-x-1">
                <span>📅 Filtrar Ventas y Pedidos por Fecha:</span>
            </span>
            <button onclick="setDateFilter('TODAY')" id="filter-btn-today" class="px-3 py-1.5 bg-blue-600 text-white rounded-xl text-xs font-extrabold transition shadow-sm">
                Hoy ({{ date('d/m/Y') }})
            </button>
            <button onclick="setDateFilter('ALL')" id="filter-btn-all" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition">
                Todos (Histórico)
            </button>
        </div>
        <div class="flex items-center space-x-2 w-full sm:w-auto">
            <span class="text-xs text-slate-500 font-semibold">Seleccionar Fecha:</span>
            <input type="date" id="custom-date-picker" onchange="setDateFilter(this.value)" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1 text-xs text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <!-- SMART DELIVERY BOARD (DINÁMICO CON ZONAS REALES) -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Smart Delivery Board (Rutas Agrupadas por Zona)</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Consolidación automática de pedidos por zonas de entrega. Haz clic en una tarjeta para ver el detalle de la venta.</p>
            </div>
            <span id="active-zones-badge" class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-3 py-1 rounded-full font-bold border border-blue-200 dark:border-blue-800">
                0 Zonas Activas
            </span>
        </div>

        <div id="delivery-board-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <p class="text-xs text-slate-400 text-center py-6 col-span-3">Cargando mapa de zonas de delivery en tiempo real...</p>
        </div>
    </div>

    <!-- TABLA DE PEDIDOS MULTICANAL EN VIVO (4 DIMENSIONES) -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3 gap-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Monitoreo de Pedidos en Vivo (Matriz 4D)</h2>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400 font-semibold">Mostrar:</span>
                <select id="select-page-size" onchange="changePageSize(this.value)" class="text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1 text-slate-700 dark:text-slate-300 font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="5">5 por página</option>
                    <option value="10" selected>10 por página</option>
                    <option value="20">20 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[480px] overflow-y-auto rounded-xl border border-slate-100 dark:border-slate-800/80">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="sticky top-0 z-10 bg-slate-100 dark:bg-slate-900 text-xs font-bold uppercase text-slate-400 border-b border-slate-200 dark:border-slate-800 shadow-sm">
                    <tr>
                        <th class="py-3 px-4">Orden #</th>
                        <th class="py-3 px-4">Cliente / WhatsApp</th>
                        <th class="py-3 px-4">Canal</th>
                        <th class="py-3 px-4">Total (S/)</th>
                        <th class="py-3 px-4">Pago</th>
                        <th class="py-3 px-4">Delivery</th>
                        <th class="py-3 px-4">Atención</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <tr>
                        <td colspan="7" class="py-8 text-center text-xs text-slate-400">Cargando pedidos en vivo desde la base de datos...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN Y CONTROL DE VISTA DE LA TABLA -->
        <div class="flex flex-col sm:flex-row justify-between items-center pt-4 border-t border-slate-100 dark:border-slate-800 gap-3 text-xs">
            <div class="text-slate-400 font-medium" id="pagination-info">
                Mostrando 0 - 0 de 0 pedidos
            </div>
            <div class="flex items-center space-x-2">
                <button id="btn-prev-page" onclick="changePage(-1)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    ◀ Anterior
                </button>
                <span id="page-num-display" class="font-extrabold text-slate-800 dark:text-slate-200 px-2 py-1 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                    Página 1
                </span>
                <button id="btn-next-page" onclick="changePage(1)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Siguiente ▶
                </button>
            </div>
        </div>
    </div>

</div>

<!-- MODAL INTERACTIVO DE FICHA DETALLADA DEL PEDIDO -->
<div id="modal-order-details" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 w-full max-w-xl rounded-2xl p-6 space-y-5 shadow-2xl">
        <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 font-extrabold text-sm">
                    📦
                </div>
                <div>
                    <h3 id="modal-order-num" class="text-base font-extrabold text-slate-900 dark:text-white">Cargando Pedido...</h3>
                    <p id="modal-order-date" class="text-xs text-slate-400 font-medium"></p>
                </div>
            </div>
            <button onclick="closeOrderModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold">&times;</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">👤 Cliente & Contacto</span>
                <p id="modal-order-customer" class="text-xs font-extrabold text-slate-900 dark:text-white"></p>
                <p id="modal-order-phone" class="text-[11px] text-slate-500 font-mono"></p>
            </div>

            <div class="bg-slate-50 dark:bg-slate-900/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">📍 Dirección de Delivery</span>
                <p id="modal-order-address" class="text-xs font-bold text-slate-900 dark:text-white"></p>
                <p id="modal-order-zone" class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold"></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Monto Total</span>
                <p id="modal-order-total" class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">S/ 0.00</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Estado de Pago</span>
                <div id="modal-order-payment-status" class="mt-1"></div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Estado Delivery</span>
                <div id="modal-order-fulfillment-status" class="mt-1"></div>
            </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-3 space-y-2">
            <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-200">Acciones Rápidas de Estado:</h4>
            <div class="flex flex-wrap gap-2">
                <button onclick="updateSelectedOrderStatus('PAID')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-sm">✓ Marcar PAGADO</button>
                <button onclick="updateSelectedOrderStatus('OUT_FOR_DELIVERY')" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition shadow-sm">🚚 En Camino</button>
                <button onclick="updateSelectedOrderStatus('DELIVERED')" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl transition shadow-sm">✅ Entregado</button>
                <button onclick="updateSelectedOrderStatus('CANCELLED')" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl transition shadow-sm">❌ Cancelar Pedido</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let activeTenantId = Number('{{ $tenantId ?? "" }}') || (window.activeTenantId ? Number(window.activeTenantId) : null);
    let operationsRequestSequence = 0;
    let currentDateFilter = 'TODAY';
    let currentLoadedOrders = [];
    let selectedOrder = null;
    let currentPage = 1;
    let pageSize = 10;

    function changePageSize(newSize) {
        pageSize = parseInt(newSize) || 10;
        currentPage = 1;
        renderOrdersTable(currentLoadedOrders);
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadOperationsData();
        setInterval(loadOperationsData, 10000);
    });

    function clearOperationsUI() {
        currentLoadedOrders = [];
        const tbody = document.getElementById('orders-table-body');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-xs text-slate-400">Actualizando datos operativos...</td></tr>`;
        }
    }

    function setDateFilter(filterValue) {
        currentDateFilter = filterValue;
        const btnToday = document.getElementById('filter-btn-today');
        const btnAll = document.getElementById('filter-btn-all');

        if (filterValue === 'TODAY') {
            btnToday.className = "px-3 py-1.5 bg-blue-600 text-white rounded-xl text-xs font-extrabold transition shadow-sm";
            btnAll.className = "px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition";
        } else if (filterValue === 'ALL') {
            btnAll.className = "px-3 py-1.5 bg-blue-600 text-white rounded-xl text-xs font-extrabold transition shadow-sm";
            btnToday.className = "px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition";
        } else {
            btnToday.className = "px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition";
            btnAll.className = "px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition";
        }

        clearOperationsUI();
        loadOperationsData();
    }

    async function loadOperationsData() {
        if (!activeTenantId) return;
        const reqId = ++operationsRequestSequence;
        const correlationId = 'OP_REQ_' + Date.now() + '_' + reqId;

        try {
            const res = await fetch(`/api/v1/operations/data?tenant_id=${activeTenantId}&date_filter=${currentDateFilter}`, {
                headers: {
                    'X-Correlation-ID': correlationId
                }
            });

            if (!res.ok) {
                if (res.status === 403) {
                    console.error('P3.13-10: Acceso denegado al Tenant ID', activeTenantId);
                }
                return;
            }

            const data = await res.json();

            // P3.13-10: Anti-Stale check mediante request_sequence
            if (reqId !== operationsRequestSequence) {
                console.log('P3.13-10: Descartando respuesta stale de petición', reqId, 'actual:', operationsRequestSequence);
                return;
            }

            currentLoadedOrders = data.orders || [];

            document.getElementById('alert-attention-count').innerText = data.alerts.attention_required || 0;
            document.getElementById('alert-payments-count').innerText = data.alerts.pending_payments || 0;
            document.getElementById('alert-deliveries-count').innerText = data.alerts.ready_deliveries || 0;

            renderDeliveryBoard(data.delivery_board || {});
            renderOrdersTable(currentLoadedOrders);

        } catch (err) {
            console.error('Error cargando datos operativos:', err);
        }
    }

    function renderDeliveryBoard(boardData) {
        const grid = document.getElementById('delivery-board-grid');
        const badge = document.getElementById('active-zones-badge');
        const zones = Object.keys(boardData);

        badge.innerText = `${zones.length} Zonas Activas`;

        if (zones.length === 0) {
            grid.innerHTML = `
                <div class="col-span-3 text-center py-6 text-xs text-slate-400">
                    No hay pedidos de delivery registrados para esta fecha (${currentDateFilter === 'TODAY' ? 'Hoy' : currentDateFilter}).
                </div>
            `;
            return;
        }

        grid.innerHTML = zones.map(zoneName => {
            const items = boardData[zoneName];
            return `
                <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-800 pb-2">
                        <span class="font-extrabold text-xs text-slate-900 dark:text-white uppercase tracking-wider">${zoneName}</span>
                        <span class="text-[10px] bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded-full font-bold">${items.length} pedidos</span>
                    </div>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        ${items.map(o => `
                            <div onclick="openOrderModal(${o.id})" class="p-2.5 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 text-xs flex justify-between items-center cursor-pointer hover:border-blue-500 hover:shadow-md transition">
                                <div>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">${o.order_number}</span>
                                    <p class="text-[10px] text-slate-400 truncate max-w-[120px]">${o.customer_name || 'Cliente'}</p>
                                </div>
                                <span class="font-extrabold text-emerald-600 dark:text-emerald-400">S/ ${parseFloat(o.total_amount).toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderOrdersTable(orders) {
        const tbody = document.getElementById('orders-table-body');
        if (!orders || orders.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-xs text-slate-400">No hay pedidos registrados para la fecha seleccionada.</td></tr>`;
            document.getElementById('pagination-info').innerText = 'Mostrando 0 - 0 de 0 pedidos';
            document.getElementById('btn-prev-page').disabled = true;
            document.getElementById('btn-next-page').disabled = true;
            document.getElementById('page-num-display').innerText = 'Página 1 de 1';
            return;
        }

        const totalOrders = orders.length;
        const totalPages = Math.ceil(totalOrders / pageSize) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalOrders);
        const paginatedOrders = orders.slice(startIndex, endIndex);

        tbody.innerHTML = paginatedOrders.map(o => `
            <tr onclick="openOrderModal(${o.id})" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition cursor-pointer">
                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">${o.order_number}</td>
                <td class="py-3 px-4">
                    <p class="font-bold text-slate-800 dark:text-slate-200 text-xs">${o.customer_name || 'Cliente WhatsApp'}</p>
                    <p class="text-[10px] text-slate-400 font-mono">${o.customer_phone || ''}</p>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                        ${o.channel}
                    </span>
                </td>
                <td class="py-3 px-4 font-bold text-emerald-600 dark:text-emerald-400">S/ ${parseFloat(o.total_amount).toFixed(2)}</td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${o.payment_status === 'PAID' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800'}">
                        ${o.payment_status}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${(o.status === 'CANCELLED' || o.fulfillment_status === 'CANCELLED') ? 'bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800' : 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800'}">
                        ${(o.status === 'CANCELLED' || o.fulfillment_status === 'CANCELLED') ? 'CANCELLED' : o.fulfillment_status}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${o.attention_status === 'HUMAN_REQUIRED' ? 'bg-red-50 dark:bg-red-950 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800' : 'bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800'}">
                        ${o.attention_status}
                    </span>
                </td>
            </tr>
        `).join('');

        document.getElementById('pagination-info').innerText = `Mostrando ${startIndex + 1} - ${endIndex} de ${totalOrders} pedidos`;
        document.getElementById('btn-prev-page').disabled = (currentPage <= 1);
        document.getElementById('btn-next-page').disabled = (currentPage >= totalPages);
        document.getElementById('page-num-display').innerText = `Página ${currentPage} de ${totalPages}`;
    }

    function changePage(direction) {
        currentPage += direction;
        renderOrdersTable(currentLoadedOrders);
    }

    function openOrderModal(orderId) {
        selectedOrder = currentLoadedOrders.find(o => o.id == orderId);
        if (!selectedOrder) return;

        document.getElementById('modal-order-num').innerText = selectedOrder.order_number || ('Pedido #' + selectedOrder.id);
        document.getElementById('modal-order-date').innerText = 'Registrado: ' + (selectedOrder.created_at || 'Reciente');
        document.getElementById('modal-order-customer').innerText = selectedOrder.customer_name || 'Cliente WhatsApp';
        document.getElementById('modal-order-phone').innerText = 'WhatsApp: ' + (selectedOrder.customer_phone || 'S/N');
        document.getElementById('modal-order-address').innerText = selectedOrder.delivery_address || 'Dirección de Delivery Registrada';
        document.getElementById('modal-order-zone').innerText = 'Zona de Reparto: ' + (selectedOrder.delivery_zone || 'General');
        document.getElementById('modal-order-total').innerText = 'S/ ' + parseFloat(selectedOrder.total_amount || 0).toFixed(2);

        document.getElementById('modal-order-payment-status').innerHTML = `
            <span class="px-2 py-1 rounded-md text-xs font-extrabold ${selectedOrder.payment_status === 'PAID' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">
                ${selectedOrder.payment_status === 'PAID' ? '✓ PAGADO (VERIFICADO)' : '⏳ PENDIENTE DE PAGO'}
            </span>
        `;

        document.getElementById('modal-order-fulfillment-status').innerHTML = `
            <span class="px-2 py-1 rounded-md text-xs font-extrabold bg-blue-100 text-blue-700">
                ${selectedOrder.fulfillment_status || 'PENDIENTE'}
            </span>
        `;

        document.getElementById('modal-order-details').classList.remove('hidden');
    }

    function closeOrderModal() {
        document.getElementById('modal-order-details').classList.add('hidden');
    }

    async function updateSelectedOrderStatus(newStatus) {
        if (!selectedOrder) return;
        try {
            await fetch(`/api/v1/operations/orders/${selectedOrder.id}/status`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ status: newStatus })
            });
            closeOrderModal();
            loadOperationsData();
        } catch (e) {
            console.error('Error actualizando pedido:', e);
        }
    }
</script>
@endsection
