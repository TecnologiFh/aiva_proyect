@extends('admin.layout')

@section('title', 'Feature Entitlements — Tenant #' . $tenant->id)

@section('content')
<div class="space-y-6">
    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-3">
                <a href="/admin/tenants" class="text-slate-400 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-white">Feature Entitlements</h1>
            </div>
            <p class="text-slate-400 text-sm mt-1">Configuración de capacidades habilitadas para <span class="text-purple-400 font-semibold">{{ $tenant->name }}</span> (Tenant #{{ $tenant->id }})</p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $tenant->status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' }}">
                ESTADO: {{ $tenant->status }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex items-center space-x-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- FEATURE MATRIX FORM -->
    <form action="/admin/tenants/{{ $tenant->id }}/features" method="POST" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
        @csrf

        <div class="border-b border-slate-800 pb-4">
            <h3 class="text-lg font-semibold text-white">Módulos & Capacidades Disponibles</h3>
            <p class="text-slate-400 text-xs mt-0.5">Active las funcionalidades que este Tenant tiene contratadas en la plataforma AIVA.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @php
                $availableFeatures = [
                    'dashboard_inbox' => ['name' => 'Dashboard & WhatsApp Inbox', 'desc' => 'Canal de mensajería y atención multitrabajo.', 'icon' => '💬'],
                    'operations_center' => ['name' => 'Operations Center', 'desc' => 'Tablero de control de pedidos y estados de cocina.', 'icon' => '📦'],
                    'catalog_inventory' => ['name' => 'Catálogo & Inventario', 'desc' => 'Gestión de productos, categorías y stock.', 'icon' => '🍩'],
                    'customer_360' => ['name' => 'Clientes & Customer 360', 'desc' => 'CRM y expediente unificado de compradores.', 'icon' => '👥'],
                    'business_settings' => ['name' => 'Configuración del Negocio', 'desc' => 'Parámetros comerciales, horarios y métodos de pago.', 'icon' => '⚙️'],
                    'knowledge_hub' => ['name' => 'Knowledge Hub & Grounding', 'desc' => 'Base de conocimientos para entrenamiento del bot IA.', 'icon' => '🧠'],
                    'ai_sales' => ['name' => 'Agente IA de Ventas', 'desc' => 'Atención autónoma de consultas mediante LLM.', 'icon' => '🤖'],
                    'ai_playground' => ['name' => 'AI Playground', 'desc' => 'Consola de pruebas de prompts y simulador IA.', 'icon' => '🧪'],
                    'business_health' => ['name' => 'Salud Comercial', 'desc' => 'Diagnóstico de métricas de ventas y conversión.', 'icon' => '📊'],
                    'advanced_analytics' => ['name' => 'Analytics Avanzado', 'desc' => 'Reportes profundos de rendimiento operativo.', 'icon' => '📈'],
                ];
            @endphp

            @foreach($availableFeatures as $key => $feat)
                @php
                    $isEnabled = isset($features[$key]) ? (bool)$features[$key] : false;
                @endphp
                <div class="p-4 rounded-xl border {{ $isEnabled ? 'bg-purple-950/20 border-purple-500/30' : 'bg-slate-800/40 border-slate-800' }} transition flex items-start justify-between space-x-4">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="text-lg">{{ $feat['icon'] }}</span>
                            <span class="font-semibold text-sm text-white">{{ $feat['name'] }}</span>
                        </div>
                        <p class="text-xs text-slate-400">{{ $feat['desc'] }}</p>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                        <input type="checkbox" name="features[{{ $key }}]" value="1" class="sr-only peer" {{ $isEnabled ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
            <a href="/admin/tenants" class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition">Cancelar</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 text-white text-xs font-semibold hover:bg-purple-500 transition shadow-lg shadow-purple-600/30">Guardar Cambios de Entitlements</button>
        </div>
    </form>
</div>
@endsection
