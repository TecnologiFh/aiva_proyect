@extends('layouts.app')

@section('title', 'AIVA BOS — Configuration Center (/settings)')

@section('content')
<div class="space-y-6">

    <!-- PAGE TITLE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 text-xs font-extrabold rounded-full border border-blue-200 dark:border-blue-800">
                    CONFIGURACIÓN COMERCIAL
                </span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Configuration Center (/settings)</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Panel administrativo unificado para gestionar todos los parámetros comerciales, canales, delivery y reglas de IA de la empresa.</p>
        </div>
        <a href="/business-health" class="px-4 py-2.5 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white text-xs font-extrabold rounded-xl shadow-md transition flex items-center gap-2">
            🩺 Ir a Business Health
        </a>
    </div>

    <!-- PESTAÑAS DE CONFIGURACIÓN (11 TABS ADMINISTRATIVAS) -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800/80 overflow-x-auto">
            <nav class="flex space-x-2 text-xs font-bold text-slate-500 pb-3">
                <a href="/settings/profile" class="px-4 py-2 rounded-xl transition {{ $tab === 'profile' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">🏢 Empresa</a>
                <a href="/settings/channels" class="px-4 py-2 rounded-xl transition {{ $tab === 'channels' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">💬 Canales WhatsApp</a>
                <a href="/settings/hours" class="px-4 py-2 rounded-xl transition {{ $tab === 'hours' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">⏰ Horarios</a>
                <a href="/settings/payments" class="px-4 py-2 rounded-xl transition {{ $tab === 'payments' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">💳 Pagos</a>
                <a href="/settings/delivery" class="px-4 py-2 rounded-xl transition {{ $tab === 'delivery' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">🚚 Delivery</a>
                <a href="/settings/catalog" class="px-4 py-2 rounded-xl transition {{ $tab === 'catalog' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">📦 Catálogo</a>
                <a href="/settings/inventory" class="px-4 py-2 rounded-xl transition {{ $tab === 'inventory' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">📊 Inventario</a>
                <a href="/settings/knowledge" class="px-4 py-2 rounded-xl transition {{ $tab === 'knowledge' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">📚 Knowledge</a>
                <a href="/settings/ai" class="px-4 py-2 rounded-xl transition {{ $tab === 'ai' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">🤖 IA Guardian</a>
                <a href="/settings/team" class="px-4 py-2 rounded-xl transition {{ $tab === 'users' || $tab === 'team' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">👥 Usuarios & Equipo</a>
                <a href="/settings/roles" class="px-4 py-2 rounded-xl transition {{ $tab === 'roles' ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400' }}">🔑 Roles & Permisos</a>
            </nav>
        </div>

        <!-- TAB 1: PERFIL EMPRESARIAL -->
        @if($tab === 'profile')
            <form onsubmit="handleSaveProfile(event)" class="max-w-xl space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nombre Comercial de la Empresa *</label>
                    <input type="text" id="cfg-name" value="{{ $profile->commercial_name ?? $tenant->name ?? '' }}" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Categoría de Negocio</label>
                    <select id="cfg-category" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="RESTAURANT_BAKERY" {{ ($profile->business_category ?? '') === 'RESTAURANT_BAKERY' ? 'selected' : '' }}>Restaurante / Pastelería / Comida</option>
                        <option value="COMMERCE_RETAIL" {{ ($profile->business_category ?? '') === 'COMMERCE_RETAIL' ? 'selected' : '' }}>Comercio / Retail / Tienda</option>
                        <option value="SERVICES" {{ ($profile->business_category ?? '') === 'SERVICES' ? 'selected' : '' }}>Servicios / Citas / Clínica</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Teléfono Oficial de WhatsApp</label>
                    <input type="text" id="cfg-phone" value="{{ $profile->phone ?? '' }}" placeholder="Ej: +51999888777" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Dirección Fiscal / Sede Principal</label>
                    <input type="text" id="cfg-address" value="{{ $profile->address ?? '' }}" placeholder="Ej: Av. Principal 123, Lima" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="pt-3">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition">
                        Guardar Cambios de Perfil
                    </button>
                </div>
            </form>

        <!-- TAB 2: CANALES WHATSAPP -->
        @elseif($tab === 'channels')
            <div class="space-y-6 max-w-2xl text-xs">
                <div class="p-5 bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 rounded-2xl flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500 text-white font-black text-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                            WA
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">WhatsApp Business Gateway</h3>
                                <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 font-extrabold rounded-md text-[10px]">🟢 VINCULADO</span>
                            </div>
                            <p class="text-slate-500 dark:text-slate-400 mt-0.5">Número activo: <span class="font-bold text-slate-900 dark:text-white">{{ $profile->phone ?? 'Sin número registrado' }}</span> (Baileys / Meta API)</p>
                        </div>
                    </div>
                    <button onclick="showToast('Sincronizando estado de sesión WhatsApp...')" class="px-4 py-2 bg-slate-900 text-white font-extrabold rounded-xl hover:bg-slate-800 transition">
                        🔄 Re-sincronizar
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Webhook Inbound URL (Handshake Auto)</label>
                        <input type="text" readonly value="https://aiva.pe/api/v1/messaging/whatsapp/webhook" class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-400 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Verify Token de Webhook</label>
                        <input type="text" readonly value="AIVA_V21_PROD_VERIFY_TOKEN_SECRET" class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-400 font-mono">
                    </div>
                </div>
            </div>

        <!-- TAB 3: HORARIOS (FLEXIBILIDAD TOTAL POR DÍA DE LA SEMANA) -->
        @elseif($tab === 'hours')
            <form onsubmit="handleSaveHours(event)" class="space-y-6 text-xs max-w-3xl">
                <!-- PARAMETROS GLOBALES -->
                <div class="p-5 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center space-x-2">
                        <span>⚙️ Reglas Operativas & Anticipación Comercial</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tiempo Promedio Preparación (Mins)</label>
                            <input type="number" id="prep-time" value="{{ $profile->preparation_time_mins ?? 25 }}" required class="w-full px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Anticipación General (Días)</label>
                            <input type="number" id="gen-advance-days" value="{{ $profile->general_advance_days ?? 1 }}" required class="w-full px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Adelanto Requerido General (%)</label>
                            <input type="number" step="5" id="gen-advance-pct" value="{{ $profile->general_advance_percentage ?? 50.00 }}" required class="w-full px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-emerald-600 dark:text-emerald-400">
                        </div>
                    </div>
                </div>

                <!-- HORARIOS DIA A DIA (LUNES A DOMINGO) -->
                <div class="p-5 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4">
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Horarios de Atención Personalizados por Día</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-[11px] mt-0.5">Defina qué días opera su negocio y el rango exacto de apertura y cierre para que la Agente IA informe con exactitud a sus clientes.</p>
                    </div>

                    @php
                        $daysList = [
                            'lunes' => 'Lunes',
                            'martes' => 'Martes',
                            'miercoles' => 'Miércoles',
                            'jueves' => 'Jueves',
                            'viernes' => 'Viernes',
                            'sabado' => 'Sábado',
                            'domingo' => 'Domingo',
                        ];
                    @endphp

                    <div class="space-y-2.5">
                        @foreach($daysList as $dayKey => $dayLabel)
                            @php
                                $bh = $businessHours[$dayKey] ?? null;
                                $isOpen = true;
                                if ($bh) {
                                    if (isset($bh->is_open)) {
                                        $isOpen = (bool)$bh->is_open;
                                    } elseif (isset($bh->is_closed)) {
                                        $isOpen = !(bool)$bh->is_closed;
                                    }
                                } else {
                                    $isOpen = ($dayKey !== 'domingo');
                                }
                                $openTime = $bh->open_time ?? '08:00';
                                $closeTime = $bh->close_time ?? '20:00';
                            @endphp
                            <div class="flex items-center justify-between p-3.5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="flex items-center space-x-3 w-40">
                                    <input type="checkbox" id="day-open-{{ $dayKey }}" {{ $isOpen ? 'checked' : '' }} onchange="toggleDayInputs('{{ $dayKey }}')" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                    <label for="day-open-{{ $dayKey }}" class="font-extrabold text-slate-900 dark:text-white cursor-pointer">{{ $dayLabel }}</label>
                                </div>
                                <div id="day-inputs-{{ $dayKey }}" class="flex items-center space-x-3 {{ !$isOpen ? 'opacity-40 pointer-events-none' : '' }}">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="text-slate-400 font-bold text-[10px]">Apertura:</span>
                                        <input type="time" id="day-start-{{ $dayKey }}" value="{{ $openTime }}" class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg font-mono font-bold">
                                    </div>
                                    <span class="text-slate-400 font-bold">—</span>
                                    <div class="flex items-center space-x-1.5">
                                        <span class="text-slate-400 font-bold text-[10px]">Cierre:</span>
                                        <input type="time" id="day-end-{{ $dayKey }}" value="{{ $closeTime }}" class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg font-mono font-bold">
                                    </div>
                                </div>
                                <div class="w-24 text-right">
                                    <span id="day-badge-{{ $dayKey }}" class="px-2 py-0.5 rounded-md text-[10px] font-extrabold {{ $isOpen ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 dark:bg-slate-700 text-slate-500' }}">
                                        {{ $isOpen ? 'ABIERTO' : 'CERRADO' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition">
                        Guardar Horarios y Reglas de Operación
                    </button>
                </div>
            </form>

        <!-- TAB 4: PAGOS -->
        @elseif($tab === 'payments')
            <form onsubmit="handleSavePayments(event)" class="max-w-xl space-y-4 text-xs">
                @csrf
                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Billeteras Digitales & Cuentas Bancarias</h3>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Teléfono / Titular Yape *</label>
                        <input type="text" id="cfg-yape" name="yape_info" value="{{ $profile->yape_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: 999888777 - Nombre de Titular">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Teléfono / Titular Plin</label>
                        <input type="text" id="cfg-plin" name="plin_info" value="{{ $profile->plin_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: 999888777 - Nombre de Titular">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Cuenta Banco de Crédito del Perú (BCP)</label>
                        <input type="text" id="cfg-bcp" name="bcp_info" value="{{ $profile->bcp_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: CTA 000-00000000-0-00 / CCI 000-000-000000000000-00">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Cuenta Banco BBVA</label>
                        <input type="text" id="cfg-bbva" name="bbva_info" value="{{ $profile->bbva_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: CTA 0000-0000-0000000000 / CCI 000-000-000000000000-00">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Cuenta Interbank</label>
                        <input type="text" id="cfg-interbank" name="interbank_info" value="{{ $profile->interbank_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: CTA 000-0000000000 / CCI 000-000-000000000000-00">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Cuenta Bancaria Adicional / CCI Interbancario</label>
                        <input type="text" id="cfg-bank" name="bank_account_info" value="{{ $profile->bank_account_info ?? '' }}" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium" placeholder="Ej: 000-000-000000000000-00">
                    </div>
                </div>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl transition shadow-md">
                    Guardar Configuración de Pago
                </button>
            </form>

        <!-- TAB 5: DELIVERY -->
        @elseif($tab === 'delivery')
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Zonas de Delivery & Cobertura Configurada</h3>
                    <button onclick="openZoneModal()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition">
                        + Agregar Zona
                    </button>
                </div>
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 uppercase">
                                <th class="p-3.5">Distrito / Zona</th>
                                <th class="p-3.5">Tarifa Envío</th>
                                <th class="p-3.5">Pedido Mínimo</th>
                                <th class="p-3.5">Tiempo Estimado</th>
                                <th class="p-3.5">Estado</th>
                                <th class="p-3.5">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                            @forelse($zones as $z)
                                <tr>
                                    <td class="p-3.5 font-bold text-slate-900 dark:text-white">{{ $z->zone_name }}</td>
                                    <td class="p-3.5 text-emerald-600 dark:text-emerald-400 font-bold">S/ {{ number_format($z->delivery_fee, 2) }}</td>
                                    <td class="p-3.5">S/ {{ number_format($z->min_order_amount ?? 0, 2) }}</td>
                                    <td class="p-3.5">{{ $z->estimated_time_mins ?? 35 }} mins</td>
                                    <td class="p-3.5"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-md">ACTIVO</span></td>
                                    <td class="p-3.5">
                                        <button onclick="openZoneModal('{{ $z->id }}', '{{ addslashes($z->zone_name) }}', '{{ $z->delivery_fee }}', '{{ $z->min_order_amount }}', '{{ $z->estimated_time_mins }}')" class="text-blue-600 font-bold hover:underline">Editar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-3.5 font-bold text-slate-900 dark:text-white">Miraflores</td>
                                    <td class="p-3.5 text-emerald-600 dark:text-emerald-400 font-bold">S/ 8.00</td>
                                    <td class="p-3.5">S/ 0.00</td>
                                    <td class="p-3.5">35 mins</td>
                                    <td class="p-3.5"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-md">ACTIVO</span></td>
                                    <td class="p-3.5"><button onclick="openZoneModal(null, 'Miraflores', 8.00, 0, 35)" class="text-blue-600 font-bold hover:underline">Editar</button></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB 6: CATÁLOGO CON REGLAS DE IA POR PRODUCTO -->
        @elseif($tab === 'catalog')
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Catálogo de Productos con Especificaciones para la IA</h3>
                        <p class="text-slate-400 text-[11px]">Configure precios, días de anticipación, porcentaje de adelanto e instrucciones para el Agente IA.</p>
                    </div>
                    <button onclick="openProductModal()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition flex items-center space-x-1.5">
                        <span>+ Nuevo Producto</span>
                    </button>
                </div>
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 uppercase">
                                <th class="p-3.5">Producto</th>
                                <th class="p-3.5">SKU</th>
                                <th class="p-3.5">Precio Base</th>
                                <th class="p-3.5">Anticipación</th>
                                <th class="p-3.5">Adelanto (%)</th>
                                <th class="p-3.5">Instrucción IA</th>
                                <th class="p-3.5">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                            @forelse($products as $p)
                                <tr>
                                    <td class="p-3.5 font-bold text-slate-900 dark:text-white">{{ $p->name }}</td>
                                    <td class="p-3.5 font-mono text-slate-500">{{ $p->sku ?? 'N/A' }}</td>
                                    <td class="p-3.5 font-extrabold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($p->base_price, 2) }}</td>
                                    <td class="p-3.5"><span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-bold rounded-md">{{ $p->advance_days ?? 0 }} días</span></td>
                                    <td class="p-3.5"><span class="px-2 py-0.5 bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 font-bold rounded-md">{{ $p->advance_percentage ?? 0 }}%</span></td>
                                    <td class="p-3.5 text-slate-400 max-w-xs truncate">{{ $p->ai_instructions ?? 'Sin reglas especiales' }}</td>
                                    <td class="p-3.5">
                                        <button onclick="openProductModal('{{ $p->id }}', '{{ addslashes($p->name) }}', '{{ $p->base_price }}', '{{ $p->sku }}', '{{ $p->stock ?? 0 }}', '{{ $p->advance_days ?? 0 }}', '{{ $p->advance_percentage ?? 0 }}', '{{ addslashes($p->ai_instructions ?? '') }}')" class="text-blue-600 font-bold hover:underline">Editar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400 dark:text-slate-500 font-medium italic">
                                        No hay productos registrados en el catálogo de esta empresa. Haz clic en "+ Nuevo Producto" para ingresar tus productos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB 7: INVENTARIO -->
        @elseif($tab === 'inventory')
            <div class="space-y-4 text-xs">
                <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Control de Stock e Inventario en Tiempo Real</h3>
                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                    @forelse($products as $p)
                        <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800/80 last:border-0">
                            <div>
                                <h4 class="font-extrabold text-slate-900 dark:text-white">{{ $p->name }}</h4>
                                <p class="text-slate-400 text-[11px]">SKU: {{ $p->sku ?? 'N/A' }}</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="showToast('Stock actualizado')" class="px-3 py-1 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white font-black rounded-lg">-</button>
                                <span class="px-3 py-1 font-mono font-extrabold text-base text-slate-900 dark:text-white">{{ $p->stock ?? 0 }} u.</span>
                                <button onclick="showToast('Stock actualizado')" class="px-3 py-1 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white font-black rounded-lg">+</button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-slate-400 dark:text-slate-500 font-medium italic">
                            No hay productos en inventario para esta empresa.
                        </div>
                    @endforelse
                </div>
            </div>

        <!-- TAB 8: KNOWLEDGE -->
        @elseif($tab === 'knowledge')
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Fuentes de Conocimiento y Políticas del Negocio</h3>
                    <a href="/knowledge" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl transition">
                        📚 Abrir Knowledge Hub
                    </a>
                </div>
                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <p class="font-medium text-slate-600 dark:text-slate-300">Carga menús en PDF, listas de precios en Excel o políticas de devolución para que la IA responda con confianza 100% libre de alucinaciones.</p>
                </div>
            </div>

        <!-- TAB 9: IA GUARDIAN & MOTORES DE IA (SUPER ADMIN) -->
        @elseif($tab === 'ai')
            <div class="space-y-6 text-xs max-w-2xl">
                <!-- PANEL SUPER ADMIN: CONFIGURACIÓN DE MOTORES DE IA -->
                <div class="p-5 bg-gradient-to-br from-slate-900 to-blue-950 text-white rounded-2xl border border-blue-900/50 space-y-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-400 font-extrabold text-[10px] rounded-full border border-emerald-500/30">
                                👑 SUPER ADMIN EXCLUSIVO
                            </span>
                            <h3 class="font-extrabold text-sm text-white">Configuración Global de Motores & Gateway de IA</h3>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-slate-400">Sin tocar código</span>
                    </div>
                    <p class="text-slate-300 text-[11px]">Selecciona y conmuta en tiempo real el proveedor principal de Inteligencia Artificial para el SaaS. Si un proveedor cae, puedes cambiar de motor de forma inmediata.</p>
                    
                    <form onsubmit="handleSaveAiConfig(event)" class="space-y-4">
                        <div>
                            <label class="block font-bold text-slate-200 mb-1.5">Proveedor de IA Principal (Active Provider)</label>
                            <select id="sa-provider" class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl font-bold text-emerald-400 focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="GROQ" selected>GROQ Cloud LPU (Ultra-Rápido ~180ms - Recomendado)</option>
                                <option value="OPENAI">OpenAI (gpt-4o-mini)</option>
                                <option value="GEMINI">Google Gemini (Gemini 1.5 Flash)</option>
                                <option value="OLLAMA">Ollama Local (Local Llama 3.2 Offline)</option>
                                <option value="DEEPSEEK">DeepSeek AI Cloud</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">GROQ API Key</label>
                                <input type="password" id="sa-groq-key" value="{{ env('GROQ_API_KEY') }}" placeholder="gsk_..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl font-mono text-slate-200">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">OpenAI API Key</label>
                                <input type="password" id="sa-openai-key" value="{{ env('OPENAI_API_KEY') }}" placeholder="sk-proj-..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl font-mono text-slate-200">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Gemini API Key</label>
                                <input type="password" id="sa-gemini-key" value="{{ env('GEMINI_API_KEY') }}" placeholder="AIzaSy..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl font-mono text-slate-200">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Ollama URL Local</label>
                                <input type="text" id="sa-ollama-url" value="{{ env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434') }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl font-mono text-slate-200">
                            </div>
                        </div>

                        <div class="pt-3 flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black rounded-xl shadow-lg transition flex items-center gap-2">
                                ⚡ Guardar y Aplicar Motor de IA
                            </button>
                        </div>
                    </form>
                </div>

                <!-- PANEL DE CONFIGURACIÓN DE ALARMA Y ALERTAS DE INTERVENCIÓN HUMANA -->
                <div class="p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                🔔 Configuración de Alarma & Alertas de Intervención Humana
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-0.5">Personalice el tono de timbre y la frecuencia del aviso sonoro cuando un cliente solicite un asesor humano en WhatsApp.</p>
                        </div>
                        <span class="px-3 py-1 bg-amber-500/20 text-amber-600 dark:text-amber-400 font-extrabold text-[10px] rounded-full">HANDOVER ALERTS</span>
                    </div>

                    <form onsubmit="handleSaveAlarmSettings(event)" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Tono / Sonido de Alarma</label>
                                <select id="alarm-sound-select" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="sound_chime">🔔 Campana Estándar (Chime Beep - 880Hz)</option>
                                    <option value="sound_siren">🚨 Sirena de Emergencia (Pulsante Agudo)</option>
                                    <option value="sound_gentle">🎶 Acorde Comercial Suave (Gentle Chord)</option>
                                    <option value="sound_radar">📡 Doble Pitido Radar (Radar Beep)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Frecuencia de Repetición del Aviso Sonoro</label>
                                <select id="alarm-frequency-select" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="3">Cada 3 segundos (Alta Frecuencia - Urgente)</option>
                                    <option value="5" selected>Cada 5 segundos (Frecuencia Estándar)</option>
                                    <option value="10">Cada 10 segundos (Frecuencia Moderada)</option>
                                    <option value="15">Cada 15 segundos (Baja Frecuencia)</option>
                                    <option value="0">Alerta Única (Sonar 1 sola vez al activarse)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button" onclick="testSelectedAlarmSound()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-extrabold text-xs rounded-xl transition flex items-center gap-1.5">
                                🔊 Probar Sonido Seleccionado
                            </button>
                            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-md transition">
                                💾 Guardar Preferencias de Alarma
                            </button>
                        </div>
                    </form>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center space-x-2">
                            <span>🛡️ AI Guardian & Tono de Marca (Por Tenant)</span>
                        </h3>
                        <span class="text-[10px] bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 font-extrabold px-2.5 py-0.5 rounded-full border border-blue-200 dark:border-blue-800">
                            PERSONALIZACIÓN IA
                        </span>
                    </div>
                    <form onsubmit="saveAiTone(event)" class="space-y-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tono de Conversación de AIVA</label>
                            <select id="ai-tone-select" name="ai_tone" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                                <option value="FRIENDLY_EMPATHETIC" {{ (($profile->ai_tone ?? 'FRIENDLY_EMPATHETIC') === 'FRIENDLY_EMPATHETIC') ? 'selected' : '' }}>Amigable, Cálido y Comercial (Recomendado)</option>
                                <option value="FORMAL_CORPORATE" {{ (($profile->ai_tone ?? '') === 'FORMAL_CORPORATE') ? 'selected' : '' }}>Formal y Corporativo</option>
                                <option value="FAST_EXPRESS" {{ (($profile->ai_tone ?? '') === 'FAST_EXPRESS') ? 'selected' : '' }}>Rápido y Conciso (Inmediatez)</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">AIVA adaptará su empatía, estilo conversacional y amabilidad según el tono seleccionado.</p>
                        </div>
                        <div class="flex justify-end pt-1">
                            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-md transition flex items-center space-x-1.5">
                                <span>💾 Guardar Tono de Marca & Guardian</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <!-- TAB 10: USUARIOS & EQUIPO -->
        @elseif($tab === 'users' || $tab === 'team')
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Gestión de Usuarios y Equipo del Negocio</h3>
                        <p class="text-slate-400 text-[11px]">Cree e invite miembros de su equipo con roles específicos (Admin, Gerente, Operador, Agente).</p>
                    </div>
                    <button onclick="openUserModal()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition flex items-center space-x-1.5">
                        <span>+ Invitar / Crear Usuario</span>
                    </button>
                </div>
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 uppercase">
                                <th class="p-3.5">Nombre</th>
                                <th class="p-3.5">Email</th>
                                <th class="p-3.5">Rol de Acceso</th>
                                <th class="p-3.5">Estado</th>
                                <th class="p-3.5">Cambiar Rol</th>
                                <th class="p-3.5 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-medium">
                            @forelse($teamUsers as $u)
                                <tr>
                                    <td class="p-3.5 font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-blue-500/10 text-blue-600 font-extrabold flex items-center justify-center text-xs">
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        </div>
                                        <span>{{ $u->name }}</span>
                                    </td>
                                    <td class="p-3.5 text-slate-500 dark:text-slate-400 font-mono">{{ $u->email }}</td>
                                    <td class="p-3.5">
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold {{ $u->role_name === 'OWNER' ? 'bg-purple-100 text-purple-700 border border-purple-300' : ($u->role_name === 'ADMIN' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-emerald-100 text-emerald-700 border border-emerald-300') }}">
                                            {{ $u->role_name }}
                                        </span>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-md">ACTIVO</span>
                                    </td>
                                    <td class="p-3.5">
                                        @if($u->role_name !== 'OWNER')
                                            <select onchange="handleRoleChange({{ $u->id }}, this.value)" class="px-2.5 py-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-900 dark:text-white focus:outline-none">
                                                <option value="ADMIN" {{ $u->role_name === 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                                                <option value="MANAGER" {{ $u->role_name === 'MANAGER' ? 'selected' : '' }}>MANAGER</option>
                                                <option value="OPERATOR" {{ $u->role_name === 'OPERATOR' ? 'selected' : '' }}>OPERATOR</option>
                                                <option value="AGENT" {{ $u->role_name === 'AGENT' ? 'selected' : '' }}>AGENT</option>
                                            </select>
                                        @else
                                            <span class="text-slate-400 text-[10px] italic">Propietario Principal</span>
                                        @endif
                                    </td>
                                    <td class="p-3.5 text-right">
                                        <button onclick="openUserModal('{{ addslashes($u->name) }}', '{{ $u->email }}', '{{ $u->role_name }}')" class="px-3 py-1.5 bg-slate-900 dark:bg-slate-800 text-white font-bold rounded-lg text-xs hover:bg-slate-800 transition">
                                            ✏️ Editar Credenciales
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-slate-400">No hay usuarios registrados en el equipo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB 11: ROLES & PERMISOS DEL NEGOCIO -->
        @elseif($tab === 'roles')
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white">Matriz de Roles y Niveles de Permiso en la Empresa</h3>
                        <p class="text-slate-400 text-[11px]">Consulte el alcance de cada rol configurado para proteger el acceso a configuraciones y catálogo.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($rolesCatalog as $roleItem)
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="px-3 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-extrabold text-xs rounded-xl">
                                    {{ $roleItem->name }}
                                </span>
                                <span class="text-[10px] font-mono text-slate-400">ID #{{ $roleItem->id }}</span>
                            </div>
                            <p class="text-slate-600 dark:text-slate-300 font-medium text-xs">{{ $roleItem->description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

</div>

<!-- MODAL FORMULARIO DE PRODUCTO CON REGLAS DE IA -->
<div id="productModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden animate-in fade-in duration-200">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 id="productModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">📦 Agregar / Editar Producto</h3>
            <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>
        <form onsubmit="handleSaveProduct(event)" class="space-y-3.5 text-xs">
            <input type="hidden" id="pm-id">
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Nombre del Producto *</label>
                <input type="text" id="pm-name" required placeholder="Ej: Torta Personalizada de Cumpleaños" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Precio (S/) *</label>
                    <input type="number" step="0.50" id="pm-price" required placeholder="Ej: 45.00" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Código SKU</label>
                    <input type="text" id="pm-sku" placeholder="Ej: TOR-001" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Stock Inicial</label>
                    <input type="number" id="pm-stock" value="20" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
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
                        <input type="number" id="pm-adv-days" value="0" placeholder="Ej: 2 días antes" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold">
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
                    <textarea id="pm-ai-notes" rows="2" placeholder="Ej: Solo disponible en sabores Chocolate y Vainilla. Incluye dedicatoria personalizada gratis." class="w-full px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none"></textarea>
                </div>
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 font-bold rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CREAR/INVITAR USUARIO AL EQUIPO -->
<div id="userModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden animate-in fade-in duration-200">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 id="userModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">👥 Invitar / Crear Usuario del Equipo</h3>
            <button onclick="closeUserModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>
        <form onsubmit="handleSaveUser(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Nombre Completo *</label>
                <input type="text" id="um-name" required placeholder="Ej: Juan Pérez" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Correo Electrónico (Login) *</label>
                <input type="email" id="um-email" required placeholder="Ej: juan@donaspepa.pe" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Contraseña Inicial</label>
                <input type="password" id="um-password" placeholder="Pass123!" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Rol de Acceso *</label>
                <select id="um-role" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold outline-none">
                    <option value="ADMIN">ADMIN — Administrador Operativo</option>
                    <option value="MANAGER">MANAGER — Gerente Comercial</option>
                    <option value="OPERATOR" selected>OPERATOR — Operador de Despacho / Cocina</option>
                    <option value="AGENT">AGENT — Agente de Atención al Cliente</option>
                </select>
            </div>
            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 font-bold rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md">Crear / Invitar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL FORMULARIO DE ZONA DE DELIVERY (NUEVO / EDITAR) -->
<div id="zoneModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden animate-in fade-in duration-200">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 id="zoneModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">🚚 Agregar / Editar Zona de Delivery</h3>
            <button onclick="closeZoneModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>
        <form onsubmit="handleSaveZone(event)" class="space-y-3 text-xs">
            <input type="hidden" id="zm-id">
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Distrito / Nombre de Zona *</label>
                <input type="text" id="zm-name" required placeholder="Ej: Miraflores, San Isidro, Surco" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tarifa de Envío (S/) *</label>
                <input type="number" step="0.50" id="zm-fee" required placeholder="Ej: 8.00" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Pedido Mínimo (S/)</label>
                    <input type="number" step="1.00" id="zm-min" value="0.00" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tiempo Estimado (Mins)</label>
                    <input type="number" id="zm-mins" value="35" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium outline-none">
                </div>
            </div>
            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeZoneModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 font-bold rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md">Guardar Zona</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 hidden animate-in slide-in-from-bottom duration-300">
    <div class="bg-slate-900 text-white border border-slate-700 px-5 py-3.5 rounded-2xl shadow-2xl flex items-center space-x-3">
        <span id="toastIcon" class="text-emerald-400 text-lg">✓</span>
        <span id="toastText" class="text-xs font-bold">Configuración guardada exitosamente.</span>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const activeTenantId = Number('{{ $tenantId }}') || 4;

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

    // TOGGLE HORARIOS DIA A DIA
    function toggleDayInputs(dayKey) {
        const isChecked = document.getElementById(`day-open-${dayKey}`).checked;
        const inputsContainer = document.getElementById(`day-inputs-${dayKey}`);
        const badge = document.getElementById(`day-badge-${dayKey}`);

        if (isChecked) {
            inputsContainer.classList.remove('opacity-40', 'pointer-events-none');
            badge.innerText = 'ABIERTO';
            badge.className = 'px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-emerald-100 text-emerald-700';
        } else {
            inputsContainer.classList.add('opacity-40', 'pointer-events-none');
            badge.innerText = 'CERRADO';
            badge.className = 'px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-slate-200 dark:bg-slate-700 text-slate-500';
        }
    }

    async function handleSaveHours(e) {
        e.preventDefault();
        const days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        const hoursPayload = {};

        days.forEach(day => {
            hoursPayload[day] = {
                is_open: document.getElementById(`day-open-${day}`).checked,
                open_time: document.getElementById(`day-start-${day}`).value,
                close_time: document.getElementById(`day-end-${day}`).value,
            };
        });

        try {
            const res = await fetch('/api/v1/settings/hours/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    preparation_time_mins: document.getElementById('prep-time').value,
                    general_advance_days: document.getElementById('gen-advance-days').value,
                    general_advance_percentage: document.getElementById('gen-advance-pct').value,
                    hours: hoursPayload
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al conectar con el servidor.', true);
        }
    }

    // MODAL USUARIOS
    function openUserModal(name = '', email = '', role = 'OPERATOR') {
        document.getElementById('um-name').value = name || '';
        document.getElementById('um-email').value = email || '';
        document.getElementById('um-password').value = '';
        document.getElementById('um-role').value = role || 'OPERATOR';
        
        const titleEl = document.getElementById('userModalTitle');
        if (titleEl) {
            titleEl.innerText = name ? '✏️ Editar Credenciales de Usuario' : '👥 Invitar / Crear Usuario del Equipo';
        }
        document.getElementById('userModal').classList.remove('hidden');
    }
    function closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
    }
    async function handleSaveUser(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/v1/settings/users/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('um-name').value,
                    email: document.getElementById('um-email').value,
                    password: document.getElementById('um-password').value,
                    role: document.getElementById('um-role').value,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                closeUserModal();
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al registrar usuario.', true);
        }
    }

    async function handleRoleChange(userId, newRole) {
        try {
            const res = await fetch('/api/v1/settings/users/role', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, role: newRole })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al modificar el rol del usuario.', true);
        }
    }

    // MODAL PRODUCTO
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

    // MODAL ZONA DELIVERY
    function openZoneModal(id = null, name = '', fee = 0, min = 0, mins = 35) {
        document.getElementById('zm-id').value = id || '';
        document.getElementById('zm-name').value = name || '';
        document.getElementById('zm-fee').value = fee || 0;
        document.getElementById('zm-min').value = min || 0;
        document.getElementById('zm-mins').value = mins || 35;
        document.getElementById('zoneModalTitle').innerText = id ? '✏️ Editar Zona de Delivery' : '🚚 Agregar Nueva Zona de Delivery';
        document.getElementById('zoneModal').classList.remove('hidden');
    }
    function closeZoneModal() {
        document.getElementById('zoneModal').classList.add('hidden');
    }
    async function handleSaveZone(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/v1/settings/zones/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: document.getElementById('zm-id').value,
                    zone_name: document.getElementById('zm-name').value,
                    delivery_fee: document.getElementById('zm-fee').value,
                    min_order_amount: document.getElementById('zm-min').value,
                    estimated_time_mins: document.getElementById('zm-mins').value,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                closeZoneModal();
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al conectar con el servidor.', true);
        }
    }

    async function handleSaveAiConfig(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/v1/settings/ai-config', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    ai_provider: document.getElementById('sa-provider').value,
                    groq_api_key: document.getElementById('sa-groq-key').value,
                    openai_api_key: document.getElementById('sa-openai-key').value,
                    gemini_api_key: document.getElementById('sa-gemini-key').value,
                    ollama_base_url: document.getElementById('sa-ollama-url').value,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al actualizar motores de IA.', true);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const soundSel = document.getElementById('alarm-sound-select');
        const freqSel = document.getElementById('alarm-frequency-select');
        if (soundSel) soundSel.value = localStorage.getItem('aiva_alarm_sound') || 'sound_chime';
        if (freqSel) freqSel.value = localStorage.getItem('aiva_alarm_interval_sec') || '5';
    });

    function handleSaveAlarmSettings(e) {
        e.preventDefault();
        const sound = document.getElementById('alarm-sound-select').value;
        const freq = document.getElementById('alarm-frequency-select').value;
        localStorage.setItem('aiva_alarm_sound', sound);
        localStorage.setItem('aiva_alarm_interval_sec', freq);
        showToast('🔔 Preferencias de sonido y frecuencia de alarma guardadas correctamente.');
    }

    function testSelectedAlarmSound() {
        const soundType = document.getElementById('alarm-sound-select').value;
        localStorage.setItem('aiva_alarm_sound', soundType);
        playChimeAlertTest(soundType);
    }

    function playChimeAlertTest(soundType) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const gain = ctx.createGain();
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.connect(ctx.destination);

            if (soundType === 'sound_siren') {
                const osc = ctx.createOscillator();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(1040, ctx.currentTime);
                osc.frequency.linearRampToValueAtTime(750, ctx.currentTime + 0.2);
                osc.frequency.linearRampToValueAtTime(1040, ctx.currentTime + 0.4);
                osc.connect(gain);
                osc.start();
                osc.stop(ctx.currentTime + 0.4);
            } else if (soundType === 'sound_gentle') {
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                osc1.type = 'sine';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(523.25, ctx.currentTime);
                osc2.frequency.setValueAtTime(659.25, ctx.currentTime);
                osc1.connect(gain);
                osc2.connect(gain);
                osc1.start();
                osc2.start();
                osc1.stop(ctx.currentTime + 0.5);
                osc2.stop(ctx.currentTime + 0.5);
            } else if (soundType === 'sound_radar') {
                const osc = ctx.createOscillator();
                osc.type = 'square';
                osc.frequency.setValueAtTime(750, ctx.currentTime);
                osc.connect(gain);
                osc.start();
                osc.stop(ctx.currentTime + 0.1);
                setTimeout(() => {
                    try {
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        gain2.gain.setValueAtTime(0.3, ctx.currentTime);
                        gain2.connect(ctx.destination);
                        osc2.type = 'square';
                        osc2.frequency.setValueAtTime(750, ctx.currentTime);
                        osc2.connect(gain2);
                        osc2.start();
                        osc2.stop(ctx.currentTime + 0.1);
                    } catch(e) {}
                }, 150);
            } else {
                const osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.35);
                osc.connect(gain);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            }
        } catch(e) {}
    }

    async function handleSaveProfile(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/v1/settings/profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    commercial_name: document.getElementById('cfg-name').value,
                    business_category: document.getElementById('cfg-category').value,
                    phone: document.getElementById('cfg-phone').value,
                    address: document.getElementById('cfg-address').value,
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al conectar con el servidor.', true);
        }
    }

    async function saveAiTone(e) {
        if (e) e.preventDefault();
        const select = document.getElementById('ai-tone-select');
        const selectedTone = select ? select.value : 'FRIENDLY_EMPATHETIC';

        try {
            const res = await fetch('/api/v1/settings/ai-tone', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    tenant_id: typeof activeTenantId !== 'undefined' ? activeTenantId : 4,
                    ai_tone: selectedTone
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message || 'Tono de conversación de AIVA actualizado con éxito.');
            } else {
                showToast('Error: ' + (data.message || 'No se pudo guardar el tono.'), true);
            }
        } catch(err) {
            console.error("saveAiTone error:", err);
            showToast('Error al guardar el tono de conversación.', true);
        }
    }

    async function handleSavePayments(e) {
        if (e) e.preventDefault();
        const yape = document.getElementById('cfg-yape')?.value || '';
        const plin = document.getElementById('cfg-plin')?.value || '';
        const bcp = document.getElementById('cfg-bcp')?.value || '';
        const bbva = document.getElementById('cfg-bbva')?.value || '';
        const interbank = document.getElementById('cfg-interbank')?.value || '';
        const bank = document.getElementById('cfg-bank')?.value || '';

        try {
            const res = await fetch('/settings/payments', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({
                    yape_info: yape,
                    plin_info: plin,
                    bcp_info: bcp,
                    bbva_info: bbva,
                    interbank_info: interbank,
                    bank_account_info: bank
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message || 'Cuentas y métodos de pago oficiales guardados correctamente.');
            } else {
                showToast('Error: ' + (data.message || 'No se pudieron guardar las cuentas de pago.'), true);
            }
        } catch(err) {
            console.error("handleSavePayments error:", err);
            showToast('Error de conexión al guardar la configuración de pago.', true);
        }
    }

    async function handleSaveHours(e) {
        if (e) e.preventDefault();

        const days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        const hoursPayload = {};

        days.forEach(d => {
            const isOpen = document.getElementById('day-open-' + d)?.checked || false;
            const start = document.getElementById('day-start-' + d)?.value || '08:00';
            const end = document.getElementById('day-end-' + d)?.value || '20:00';

            hoursPayload[d] = {
                is_open: isOpen ? 1 : 0,
                open_time: start,
                close_time: end
            };
        });

        const prepTime = document.getElementById('prep-time')?.value || 25;
        const genDays = document.getElementById('gen-advance-days')?.value || 1;
        const genPct = document.getElementById('gen-advance-pct')?.value || 50;

        try {
            const res = await fetch('/settings/hours', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    hours: hoursPayload,
                    preparation_time_mins: prepTime,
                    general_advance_days: genDays,
                    general_advance_percentage: genPct
                })
            });

            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message || 'Horarios y reglas comerciales guardadas correctamente.');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + (data.message || 'No se pudieron guardar los horarios.'), true);
            }
        } catch(err) {
            console.error("handleSaveHours error:", err);
            showToast('Error de conexión al guardar los horarios.', true);
        }
    }
</script>
@endsection
