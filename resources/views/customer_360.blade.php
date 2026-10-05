@extends('layouts.app')

@section('title', 'AIVA — Customer 360 & Ficha Unificada de Clientes')

@section('content')
<div class="space-y-6">

    <!-- FLOATING TOAST NOTIFICATION MODAL -->
    <div id="toast-modal" class="fixed top-6 right-6 z-50 transition-all duration-300 transform translate-y-[-20px] opacity-0 pointer-events-none">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xl flex items-center space-x-3.5 max-w-md">
            <div id="toast-icon-container" class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 pr-2">
                <h4 id="toast-title" class="text-xs font-bold text-slate-900 dark:text-white">Notificación CRM</h4>
                <p id="toast-message" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium"></p>
            </div>
            <button onclick="closeToastModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-base font-bold p-1">&times;</button>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Customer 360 & Ficha de Clientes</h1>
                <span class="px-2.5 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 rounded-full text-xs font-bold">
                    Modulo M10 CRM
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Historial unificado de compras, valor de vida del cliente (LTV), canal preferido y etiquetas de fidelidad.</p>
        </div>

        <div class="flex items-center space-x-3">
            <a href="/dashboard" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition">
                ← Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- CRM STATS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Clientes</span>
                <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 43a8 8 0 100-16 8 8 0 000 16zM12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-3">{{ count($customers) }}</div>
            <p class="text-[11px] text-slate-400 mt-1">Registrados en el Tenant #{{ $tenantId }}</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Clientes VIP / Frecuentes</span>
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">
                {{ $customers->filter(fn($c) => in_array($c->tag, ['VIP', 'FRECUENTE']))->count() }}
            </div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400/80 mt-1 font-semibold">Alta frecuencia y valor de compra</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ventas Acumuladas (LTV)</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">
                S/ {{ number_format((float)$customers->sum('total_spent_amount'), 2) }}
            </div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400/80 mt-1 font-semibold">Ingreso histórico de clientes</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Canal Dominante</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">WhatsApp</div>
            <p class="text-[11px] text-purple-400 mt-1">Interacción principal inteligente</p>
        </div>
    </div>

    <!-- CUSTOMERS TABLE -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-slate-100 dark:border-slate-800 pb-3">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Directorio Unificado de Clientes</h2>
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <input type="text" id="crm-search-input" onkeyup="filterCustomers()" placeholder="Buscar por nombre o teléfono..." class="bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64">
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-slate-400 font-semibold">Mostrar:</span>
                    <select id="crm-page-size" onchange="changeCrmPageSize(this.value)" class="text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-slate-700 dark:text-slate-300 font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="5">5 por página</option>
                        <option value="10" selected>10 por página</option>
                        <option value="20">20 por página</option>
                        <option value="50">50 por página</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[480px] overflow-y-auto rounded-xl border border-slate-100 dark:border-slate-800/80">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="sticky top-0 z-10 bg-slate-100 dark:bg-slate-900 text-xs font-bold uppercase text-slate-400 border-b border-slate-200 dark:border-slate-800 shadow-sm">
                    <tr>
                        <th class="py-3 px-4">Cliente</th>
                        <th class="py-3 px-4">Teléfono / WhatsApp</th>
                        <th class="py-3 px-4">Canal Preferido</th>
                        <th class="py-3 px-4">Pedidos Totales</th>
                        <th class="py-3 px-4">Total Gastado (LTV)</th>
                        <th class="py-3 px-4">Fidelidad / Tag</th>
                        <th class="py-3 px-4 text-right">Ficha 360</th>
                    </tr>
                </thead>
                <tbody id="customers-table-body" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <tr>
                        <td colspan="7" class="py-8 text-center text-xs text-slate-400">Cargando directorio de clientes...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- CONTROLES DE PAGINACIÓN CRM -->
        <div class="flex flex-col sm:flex-row justify-between items-center pt-4 border-t border-slate-100 dark:border-slate-800 gap-3 text-xs">
            <div class="text-slate-400 font-medium" id="crm-pagination-info">
                Mostrando 0 - 0 de 0 clientes
            </div>
            <div class="flex items-center space-x-2">
                <button id="crm-btn-prev" onclick="changeCrmPage(-1)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    ◀ Anterior
                </button>
                <span id="crm-page-num" class="font-extrabold text-slate-800 dark:text-slate-200 px-2.5 py-1 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                    Página 1 de 1
                </span>
                <button id="crm-btn-next" onclick="changeCrmPage(1)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Siguiente ▶
                </button>
            </div>
        </div>
    </div>

</div>

<!-- DRAWER MODAL CUSTOMER 360 -->
<div id="modal-customer-360" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 w-full max-w-2xl rounded-2xl p-6 space-y-5 shadow-2xl">
        <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-blue-600 text-white font-extrabold flex items-center justify-center text-sm">
                    👤
                </div>
                <div>
                    <h3 id="modal-customer-name" class="text-base font-bold text-slate-900 dark:text-white">Cargando Cliente...</h3>
                    <p id="modal-customer-phone" class="text-xs text-slate-400 font-mono"></p>
                </div>
            </div>
            <button onclick="document.getElementById('modal-customer-360').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold">&times;</button>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Pedidos Totales</span>
                <p id="modal-customer-orders" class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5">0</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">LTV Acumulado</span>
                <p id="modal-customer-spent" class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">S/ 0.00</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Etiqueta Fidelidad</span>
                <p id="modal-customer-tag" class="text-xs font-bold text-amber-500 mt-1 uppercase">NUEVO</p>
            </div>
        </div>

        <div class="space-y-2">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Historial de Pedidos & Interacciones</h4>
            <div id="modal-order-history-list" class="max-h-48 overflow-y-auto space-y-2 pr-1 text-xs">
                <p class="text-slate-400 text-center py-4">Cargando compras...</p>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button onclick="document.getElementById('modal-customer-360').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold">Cerrar</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const activeTenantId = Number('{{ $tenantId ?? "" }}') || window.activeTenantId || 4;
    let toastTimeout = null;
    const rawCustomers = @json($customers);
    let filteredCustomers = [...rawCustomers];
    let crmCurrentPage = 1;
    let crmPageSize = 10;

    document.addEventListener('DOMContentLoaded', () => {
        renderCustomersTable();
    });

    function changeCrmPageSize(val) {
        crmPageSize = parseInt(val) || 10;
        crmCurrentPage = 1;
        renderCustomersTable();
    }

    function changeCrmPage(direction) {
        crmCurrentPage += direction;
        renderCustomersTable();
    }

    function filterCustomers() {
        const input = document.getElementById('crm-search-input');
        const filter = input ? input.value.toLowerCase().trim() : '';

        if (!filter) {
            filteredCustomers = [...rawCustomers];
        } else {
            filteredCustomers = rawCustomers.filter(c => {
                const nameMatch = (c.name || '').toLowerCase().includes(filter);
                const phoneMatch = (c.phone || '').toLowerCase().includes(filter);
                const tagMatch = (c.tag || '').toLowerCase().includes(filter);
                return nameMatch || phoneMatch || tagMatch;
            });
        }
        crmCurrentPage = 1;
        renderCustomersTable();
    }

    function renderCustomersTable() {
        const tbody = document.getElementById('customers-table-body');
        if (!tbody) return;

        if (filteredCustomers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-xs text-slate-400">No hay clientes registrados o que coincidan con la búsqueda.</td></tr>`;
            document.getElementById('crm-pagination-info').innerText = 'Mostrando 0 - 0 de 0 clientes';
            document.getElementById('crm-btn-prev').disabled = true;
            document.getElementById('crm-btn-next').disabled = true;
            document.getElementById('crm-page-num').innerText = 'Página 1 de 1';
            return;
        }

        const totalItems = filteredCustomers.length;
        const totalPages = Math.ceil(totalItems / crmPageSize) || 1;

        if (crmCurrentPage > totalPages) crmCurrentPage = totalPages;
        if (crmCurrentPage < 1) crmCurrentPage = 1;

        const startIndex = (crmCurrentPage - 1) * crmPageSize;
        const endIndex = Math.min(startIndex + crmPageSize, totalItems);
        const paginatedData = filteredCustomers.slice(startIndex, endIndex);

        tbody.innerHTML = paginatedData.map(c => `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">${c.name || 'Cliente'}</td>
                <td class="py-3.5 px-4 font-mono text-xs text-slate-500 dark:text-slate-400">${c.phone || ''}</td>
                <td class="py-3.5 px-4">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                        ${c.preferred_channel || 'WHATSAPP'}
                    </span>
                </td>
                <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-slate-200">${c.total_orders_count || 0} pedidos</td>
                <td class="py-3.5 px-4 font-bold text-emerald-600 dark:text-emerald-400">S/ ${parseFloat(c.total_spent_amount || 0).toFixed(2)}</td>
                <td class="py-3.5 px-4">
                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold ${c.tag === 'VIP' ? 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : (c.tag === 'FRECUENTE' ? 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-500')}">
                        ${c.tag || 'NUEVO'}
                    </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                    <button onclick="openCustomer360Modal(${c.id})" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                        Ver Ficha 360
                    </button>
                </td>
            </tr>
        `).join('');

        document.getElementById('crm-pagination-info').innerText = `Mostrando ${startIndex + 1} - ${endIndex} de ${totalItems} clientes`;
        document.getElementById('crm-btn-prev').disabled = (crmCurrentPage <= 1);
        document.getElementById('crm-btn-next').disabled = (crmCurrentPage >= totalPages);
        document.getElementById('crm-page-num').innerText = `Página ${crmCurrentPage} de ${totalPages}`;
    }

    function showToastModal(title, message, type = 'success') {
        const modal = document.getElementById('toast-modal');
        document.getElementById('toast-title').innerText = title;
        document.getElementById('toast-message').innerText = message;
        modal.classList.remove('translate-y-[-20px]', 'opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-0', 'opacity-100');
        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(closeToastModal, 4000);
    }

    function closeToastModal() {
        const modal = document.getElementById('toast-modal');
        modal.classList.remove('translate-y-0', 'opacity-100');
        modal.classList.add('translate-y-[-20px]', 'opacity-0', 'pointer-events-none');
    }

    async function openCustomer360Modal(customerId) {
        document.getElementById('modal-customer-360').classList.remove('hidden');
        try {
            const res = await fetch(`/api/v1/crm/customers/${customerId}?tenant_id=${activeTenantId}`);
            const json = await res.json();
            const data = json.data;

            document.getElementById('modal-customer-name').innerText = data.customer.name;
            document.getElementById('modal-customer-phone').innerText = data.customer.phone;
            document.getElementById('modal-customer-orders').innerText = data.ltv_metrics.total_orders + ' pedidos';
            document.getElementById('modal-customer-spent').innerText = 'S/ ' + data.ltv_metrics.total_spent.toFixed(2);
            document.getElementById('modal-customer-tag').innerText = data.ltv_metrics.loyalty_tag;

            const list = document.getElementById('modal-order-history-list');
            if (data.order_history.length === 0) {
                list.innerHTML = `<p class="text-slate-400 text-center py-4">Sin pedidos registrados aún.</p>`;
                return;
            }

            list.innerHTML = data.order_history.map(o => `
                <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <div>
                        <span class="font-bold text-slate-800 dark:text-slate-200">${o.order_number}</span>
                        <p class="text-[10px] text-slate-400">${o.delivery_zone || 'Delivery'} • ${o.channel}</p>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">S/ ${parseFloat(o.total_amount).toFixed(2)}</span>
                        <p class="text-[10px] font-semibold text-slate-500">${o.status}</p>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            showToastModal('Error', 'No se pudo cargar la Ficha Customer 360.', 'error');
        }
    }
</script>
@endsection
