@extends('admin.layout')

@section('title', 'Platform Roles & Permission Catalog')

@section('content')
<div class="max-w-7xl mx-auto space-y-8">
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl shadow-2xl">
        <div>
            <div class="flex items-center space-x-3">
                <span class="p-2.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-xl text-xl">🔐</span>
                <div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">Catálogo de Permisos & Plataforma (Platform RBAC)</h1>
                    <p class="text-slate-400 text-xs mt-0.5">Definición de roles de plataforma, permisos globales y simulación visual de arquitectura (Role Preview Sandbox).</p>
                </div>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold rounded-xl flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Permission Ceiling: Active</span>
            </span>
        </div>
    </div>

    <!-- ROLE PREVIEW SANDBOX (RG18 & RG28) -->
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-6 shadow-2xl space-y-5 backdrop-blur-md">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center space-x-2.5">
                    <span class="text-lg">👁️</span>
                    <span>Role Preview Sandbox</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-lg">Simulación Aislada RG28</span>
                </h3>
                <p class="text-slate-400 text-xs mt-1">Previsualice cómo AIVA construye el menú y la superficie de navegación para cada rol sin modificar la sesión activa.</p>
            </div>
            <div>
                <form action="/admin/roles" method="GET" class="flex items-center space-x-3">
                    <label class="text-xs font-bold text-slate-300">Seleccionar Rol:</label>
                    <select name="preview_role" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-white text-xs font-bold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer shadow-inner">
                        <option value="OWNER" {{ request('preview_role', 'OWNER') === 'OWNER' ? 'selected' : '' }}>Propietario (OWNER)</option>
                        <option value="ADMIN" {{ request('preview_role') === 'ADMIN' ? 'selected' : '' }}>Administrador Operativo (ADMIN)</option>
                        <option value="MANAGER" {{ request('preview_role') === 'MANAGER' ? 'selected' : '' }}>Gerente (MANAGER)</option>
                        <option value="OPERATOR" {{ request('preview_role') === 'OPERATOR' ? 'selected' : '' }}>Operador (OPERATOR)</option>
                        <option value="AGENT" {{ request('preview_role') === 'AGENT' ? 'selected' : '' }}>Agente de Atención (AGENT)</option>
                    </select>
                </form>
            </div>
        </div>

        @php
            $selectedRole = request('preview_role', 'OWNER');
            $simulatedNav = \App\Services\Navigation\NavigationResolver::getTenantNavigation($selectedRole);
        @endphp

        <div class="p-5 bg-slate-950/80 rounded-xl border border-slate-800/80 space-y-4">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                Resultado de Navegación Renderizada para <span class="text-indigo-400 font-extrabold px-2 py-0.5 bg-indigo-500/10 border border-indigo-500/20 rounded-md">{{ $selectedRole }}</span>:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-xs">
                <!-- OPERACIÓN -->
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800/80 space-y-3 shadow-md">
                    <p class="font-bold text-blue-400 uppercase text-[10px] tracking-wider flex items-center space-x-1.5">
                        <span>⚡ Operación</span>
                    </p>
                    <ul class="space-y-2 text-slate-300">
                        @if(empty($simulatedNav['main']))
                            <li class="text-slate-500 italic text-[11px]">Sin acceso a esta sección</li>
                        @else
                            @foreach($simulatedNav['main'] as $m)
                                <li class="flex items-center space-x-2.5 p-1.5 rounded-lg bg-slate-950/50 border border-slate-800/50">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-bold">✓</span>
                                    <span class="font-medium">{{ $m['title'] }}</span>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>

                <!-- MI NEGOCIO -->
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800/80 space-y-3 shadow-md">
                    <p class="font-bold text-purple-400 uppercase text-[10px] tracking-wider flex items-center space-x-1.5">
                        <span>🏢 Mi Negocio</span>
                    </p>
                    <ul class="space-y-2 text-slate-300">
                        @if(empty($simulatedNav['business']))
                            <li class="text-slate-500 italic text-[11px]">Sin acceso a esta sección</li>
                        @else
                            @foreach($simulatedNav['business'] as $b)
                                <li class="flex items-center space-x-2.5 p-1.5 rounded-lg bg-slate-950/50 border border-slate-800/50">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-bold">✓</span>
                                    <span class="font-medium">{{ $b['title'] }}</span>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>

                <!-- CONFIGURACIÓN -->
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-800/80 space-y-3 shadow-md">
                    <p class="font-bold text-amber-400 uppercase text-[10px] tracking-wider flex items-center space-x-1.5">
                        <span>⚙️ Configuración</span>
                    </p>
                    <ul class="space-y-2 text-slate-300">
                        @if(empty($simulatedNav['config']))
                            <li class="text-slate-500 italic text-[11px]">Sin acceso a esta sección</li>
                        @else
                            @foreach($simulatedNav['config'] as $c)
                                <li class="flex items-center space-x-2.5 p-1.5 rounded-lg bg-slate-950/50 border border-slate-800/50">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-bold">✓</span>
                                    <span class="font-medium">{{ $c['title'] }}</span>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- PLATFORM ROLES & PERMISSIONS TABLE -->
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <span>📋</span>
                <span>Catálogo Oficial de Roles & Permisos en Base de Datos</span>
            </h3>
            <span class="text-xs font-semibold text-slate-400">{{ count($roles) }} roles registrados</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800/80">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-950/80 border-b border-slate-800 text-slate-400 font-bold uppercase text-[10px] tracking-wider">
                        <th class="p-4 w-16">ID</th>
                        <th class="p-4 w-44">Nombre del Rol</th>
                        <th class="p-4 w-64">Descripción</th>
                        <th class="p-4">Permisos Asignados</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @foreach ($roles as $role)
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="p-4 font-extrabold text-slate-400">#{{ $role->id }}</td>
                        <td class="p-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold {{ $role->name === 'SUPERADMIN' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/30' : ($role->name === 'OWNER' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/30') }}">
                                {{ $role->name }}
                            </span>
                        </td>
                        <td class="p-4 text-slate-400 leading-relaxed">{{ $role->description }}</td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($role->permissions as $p)
                                    <span class="inline-block bg-slate-950 text-indigo-300 border border-indigo-500/20 px-2.5 py-1 rounded-md text-[11px] font-mono shadow-sm hover:border-indigo-500/50 transition">
                                        {{ $p->code ?? $p->name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
