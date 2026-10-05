@extends('layouts.app')

@section('title', 'AIVA BOS — Panel Propietario del SaaS & Multi-Tenant')

@section('content')
<div class="space-y-6">

    <!-- PAGE TITLE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 text-xs font-extrabold rounded-full border border-purple-200 dark:border-purple-800">
                    PROPIETARIO SAAS
                </span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Panel Propietario del SaaS & Multi-Tenant</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Gestión global de empresas, licencias activas/suspendidas y métricas de consumo de la plataforma.</p>
        </div>
        <div>
            <button onclick="openCreateModal()" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-blue-500/20 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                + Nueva Empresa (Tenant)
            </button>
        </div>
    </div>

    <!-- METRICS KPI CARDS DE PLATAFORMA SAAS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Empresas Totales</span>
                <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <div id="m-total-tenants" class="text-3xl font-extrabold text-slate-900 dark:text-white mt-3">0</div>
            <p class="text-[11px] text-slate-400 mt-1">Tenants registrados en MySQL</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Empresas Activas</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div id="m-active-tenants" class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">0</div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400/80 mt-1 font-semibold">Licencias activas operando</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Suscripciones Suspendidas</span>
                <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
            </div>
            <div id="m-suspended-tenants" class="text-3xl font-extrabold text-rose-600 dark:text-rose-400 mt-3">0</div>
            <p class="text-[11px] text-rose-600 dark:text-rose-400/80 mt-1 font-semibold">IA pausada por falta de pago</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pedidos Totales</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
            </div>
            <div id="m-total-orders" class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">0</div>
            <p class="text-[11px] text-purple-600 dark:text-purple-300 mt-1">Órdenes procesadas en todo el SaaS</p>
        </div>
    </div>

    <!-- DIRECTORIO DE EMPRESAS REGISTRADAS EN TABLA -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 dark:border-slate-800/80">
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Directorio de Empresas Registradas en AIVA
            </h3>
            <span class="text-xs text-slate-400 font-medium">Aislamiento de datos garantizado por Tenant ID</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800/80 text-slate-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">Tenant ID</th>
                        <th class="py-3 px-4">Empresa Comercial</th>
                        <th class="py-3 px-4">Categoría</th>
                        <th class="py-3 px-4">Teléfono WhatsApp</th>
                        <th class="py-3 px-4">Pedidos</th>
                        <th class="py-3 px-4">Conversaciones</th>
                        <th class="py-3 px-4">Estado de Licencia</th>
                        <th class="py-3 px-4 text-right">Acción Comercial</th>
                    </tr>
                </thead>
                <tbody id="tenants-table-body" class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                    <tr><td colspan="8" class="text-center py-10 text-slate-400">Cargando directorio de empresas del SaaS...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- VENTANA EMERGENTE ELABORADA PARA EDITAR EMPRESA -->
<div id="editModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-7 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-extrabold">
                    ✏️
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white" id="editModalHeaderTitle">Editar Datos de Empresa</h3>
                    <p class="text-xs text-slate-400" id="editModalHeaderSubtitle">Actualización del perfil comercial en MySQL</p>
                </div>
            </div>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold">✕</button>
        </div>

        <form id="editTenantForm" onsubmit="handleUpdateTenant(event)" class="space-y-4 text-xs">
            <input type="hidden" id="e-tenant-id">

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nombre Comercial de la Empresa *</label>
                <input type="text" id="e-name" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Categoría de Negocio</label>
                <select id="e-category" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
                    <option value="RESTAURANT_BAKERY">Restaurante / Pastelería / Comida</option>
                    <option value="COMMERCE_RETAIL">Comercio / Retail / Tienda</option>
                    <option value="SERVICES">Servicios / Citas / Clínica</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Teléfono Oficial de WhatsApp</label>
                <input type="text" id="e-phone" placeholder="+51999888777" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Dirección Fiscal / Sede Principal</label>
                <input type="text" id="e-address" placeholder="Av. Larco 456, Miraflores, Lima" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Horarios de Atención</label>
                <input type="text" id="e-hours" placeholder="Lunes a Domingo 08:00 - 22:00" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Catálogo Visual Web (URL)</label>
                <input type="url" id="e-website" placeholder="https://minegocio.com/tienda" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium">
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-purple-600/20 transition">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- VENTANA EMERGENTE ELABORADA DE CONFIRMACIÓN (REEMPLAZO DE ALERT NATIVO) -->
<div id="confirmModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
        <div class="flex items-center space-x-4">
            <div id="confirmModalIconBg" class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <h3 id="confirmModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Cambiar Estado de Licencia</h3>
                <p id="confirmModalSubtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Confirmación de acción en plataforma SaaS</p>
            </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300 leading-relaxed" id="confirmModalMessage">
            ¿Estás seguro de modificar la licencia comercial de esta empresa?
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2">
            <button onclick="closeConfirmModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition">
                Cancelar
            </button>
            <button id="confirmModalAcceptBtn" onclick="executePendingAction()" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-rose-600/20 transition">
                Sí, Confirmar Acción
            </button>
        </div>
    </div>
</div>

<!-- VENTANA EMERGENTE ELABORADA PARA CREAR NUEVA EMPRESA -->
<div id="createModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 backdrop-blur-md p-4 transition-all duration-300">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-7 shadow-2xl space-y-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-extrabold">
                    ✨
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Dar de Alta Nueva Empresa</h3>
                    <p class="text-xs text-slate-400">Creación de Tenant con aislamiento automático P0</p>
                </div>
            </div>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold">✕</button>
        </div>

        <form id="createTenantForm" onsubmit="handleCreateTenant(event)" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nombre Comercial de la Empresa *</label>
                <input type="text" id="t-name" placeholder="Ej: Pizzería Don Italia" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Categoría de Negocio</label>
                <select id="t-category" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                    <option value="RESTAURANT_BAKERY">Restaurante / Pastelería / Comida</option>
                    <option value="COMMERCE_RETAIL">Comercio / Retail / Tienda</option>
                    <option value="SERVICES">Servicios / Citas / Clínica</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Teléfono Oficial de WhatsApp</label>
                <input type="text" id="t-phone" placeholder="+51999888777" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Dirección Fiscal / Sede Principal</label>
                <input type="text" id="t-address" placeholder="Av. Principal 123, Miraflores, Lima" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    Cancelar
                </button>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-blue-600/20 transition">
                    Crear Empresa (Tenant)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST NOTIFICATION CONTAINER (REEMPLAZO DE ALERT SUCESO) -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 hidden animate-in slide-in-from-bottom duration-300">
    <div class="bg-slate-900 text-white border border-slate-700 px-5 py-3.5 rounded-2xl shadow-2xl flex items-center space-x-3">
        <span id="toastIcon" class="text-emerald-400 text-lg">✓</span>
        <span id="toastText" class="text-xs font-bold">Acción completada con éxito.</span>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let pendingActionFunction = null;
    let globalTenantsList = [];

    async function loadTenantsData() {
        try {
            const res = await fetch('/api/v1/admin/tenants');
            const data = await res.json();
            
            if (data.status === 'OK') {
                globalTenantsList = data.tenants;

                document.getElementById('m-total-tenants').innerText = data.global_metrics.total_tenants;
                document.getElementById('m-active-tenants').innerText = data.global_metrics.active_tenants;
                document.getElementById('m-suspended-tenants').innerText = data.global_metrics.suspended_tenants;
                document.getElementById('m-total-orders').innerText = data.global_metrics.total_platform_orders;

                const tbody = document.getElementById('tenants-table-body');
                tbody.innerHTML = '';

                if (data.tenants.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">No hay empresas registradas.</td></tr>';
                    return;
                }

                data.tenants.forEach(t => {
                    const isSpanActive = (t.status || 'ACTIVE').toUpperCase() === 'ACTIVE';
                    const statusBadge = isSpanActive 
                        ? `<span class="px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 font-extrabold rounded-full border border-emerald-200 dark:border-emerald-800 text-[10px]">●ACTIVA</span>`
                        : `<span class="px-3 py-1 bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 font-extrabold rounded-full border border-rose-200 dark:border-rose-800 text-[10px]">● SUSPENDIDA</span>`;
                    
                    const toggleBtn = isSpanActive
                        ? `<button onclick="promptToggleStatus(${t.id}, '${t.name}', true)" class="px-3.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 border border-rose-200 dark:border-rose-800/60 font-bold rounded-xl transition text-xs">Suspender Licencia</button>`
                        : `<button onclick="promptToggleStatus(${t.id}, '${t.name}', false)" class="px-3.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/60 font-bold rounded-xl transition text-xs">Reactivar Licencia</button>`;

                    const editBtn = `<button onclick="openEditModal(${t.id})" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 font-bold rounded-xl transition text-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 210.3H3v-3.572L16.732 3.732z"/></svg>
                        Editar
                    </button>`;

                    const enterBtn = `<button onclick="changeTenant(${t.id})" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl transition text-xs shadow-sm flex items-center gap-1">
                        🚀 Administrar
                    </button>`;

                    tbody.innerHTML += `
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">#${t.id}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">${t.name}</td>
                            <td class="py-3.5 px-4"><span class="px-2.5 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg text-[10px] font-bold">${t.category}</span></td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-semibold">${t.phone}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">${t.orders_count} pedidos</td>
                            <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">${t.conversations_count} chats</td>
                            <td class="py-3.5 px-4">${statusBadge}</td>
                            <td class="py-3.5 px-4 text-right flex items-center justify-end space-x-2">
                                ${enterBtn}
                                ${editBtn}
                                ${toggleBtn}
                            </td>
                        </tr>
                    `;
                });
            }
        } catch (e) {
            console.error("Error al cargar empresas:", e);
        }
    }

    function openCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
    function closeCreateModal() { document.getElementById('createModal').classList.add('hidden'); }

    function openEditModal(id) {
        const tenant = globalTenantsList.find(x => x.id === id);
        if (!tenant) return;

        document.getElementById('e-tenant-id').value = tenant.id;
        document.getElementById('e-name').value = tenant.name || tenant.commercial_name || '';
        document.getElementById('e-category').value = tenant.category || 'RESTAURANT_BAKERY';
        document.getElementById('e-phone').value = tenant.phone || '';
        document.getElementById('e-address').value = tenant.address || '';
        document.getElementById('e-hours').value = tenant.opening_hours || '';
        document.getElementById('e-website').value = tenant.website_url || '';

        document.getElementById('editModalHeaderTitle').innerText = `Editar Empresa #${tenant.id} (${tenant.name})`;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

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

    function promptToggleStatus(id, name, isActiveNow) {
        const title = isActiveNow ? '¿Suspender Licencia Comercial?' : '¿Reactivar Licencia Comercial?';
        const msg = isActiveNow 
            ? `Al suspender la licencia del Tenant <strong>#${id} (${name})</strong>, la IA dejará de responder a sus clientes en WhatsApp inmediatamente.`
            : `Al reactivar la licencia del Tenant <strong>#${id} (${name})</strong>, AIVA reanudará la atención automática en WhatsApp.`;

        document.getElementById('confirmModalTitle').innerText = title;
        document.getElementById('confirmModalMessage').innerHTML = msg;

        pendingActionFunction = async () => {
            try {
                const res = await fetch(`/api/v1/admin/tenants/${id}/toggle-status`, { method: 'POST' });
                const data = await res.json();
                if (data.status === 'SUCCESS') {
                    showToast(`Estado de Tenant #${id} actualizado correctamente.`);
                    loadTenantsData();
                } else {
                    showToast("Error: " + data.message, true);
                }
            } catch (err) {
                showToast("Error de conexión al cambiar licencia.", true);
            }
            closeConfirmModal();
        };

        document.getElementById('confirmModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.add('hidden');
        pendingActionFunction = null;
    }

    function executePendingAction() {
        if (pendingActionFunction) pendingActionFunction();
    }

    async function handleCreateTenant(e) {
        e.preventDefault();
        const name = document.getElementById('t-name').value;
        const category = document.getElementById('t-category').value;
        const phone = document.getElementById('t-phone').value;
        const address = document.getElementById('t-address').value;

        try {
            const res = await fetch('/api/v1/admin/tenants', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, category, phone, address })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(`Empresa "${name}" registrada exitosamente.`);
                closeCreateModal();
                loadTenantsData();
                document.getElementById('createTenantForm').reset();
            } else {
                showToast("Error: " + data.message, true);
            }
        } catch (err) {
            showToast("Error al conectar con el servidor.", true);
        }
    }

    async function handleUpdateTenant(e) {
        e.preventDefault();
        const id = document.getElementById('e-tenant-id').value;
        const name = document.getElementById('e-name').value;
        const category = document.getElementById('e-category').value;
        const phone = document.getElementById('e-phone').value;
        const address = document.getElementById('e-address').value;
        const openingHours = document.getElementById('e-hours').value;
        const websiteUrl = document.getElementById('e-website').value;

        try {
            const res = await fetch(`/api/v1/admin/tenants/${id}/update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    category: category,
                    phone: phone,
                    address: address,
                    opening_hours: openingHours,
                    website_url: websiteUrl,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                closeEditModal();
                loadTenantsData();
            } else {
                showToast("Error: " + data.message, true);
            }
        } catch (err) {
            showToast("Error de conexión al actualizar empresa.", true);
        }
    }

    // Inicializar al cargar vista
    loadTenantsData();
</script>
@endsection
