@extends('layouts.app')

@section('title', 'Roles & Permisos del Equipo')

@section('content')
<div class="p-6 space-y-6">
    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">🔑 Roles & Permisos del Equipo</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Administre las capacidades de acceso de su personal según su rol dentro de la empresa.</p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800 text-xs font-bold rounded-lg">
                Propietario Activo: {{ Auth::user()->name }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-600 dark:text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- MATRIZ INTERACTIVA RBAC DEL TENANT PLANE -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm overflow-x-auto space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Matriz de Autorizaciones Internas</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Los permisos se aplican sobre las funcionalidades que su empresa tiene contratadas.</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-md">
                Permission Ceiling: Namespace tenant.*
            </span>
        </div>

        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                    <th class="py-3 px-4">Módulo / Capacidad</th>
                    <th class="py-3 px-4 text-center">OWNER</th>
                    <th class="py-3 px-4 text-center">ADMIN</th>
                    <th class="py-3 px-4 text-center">MANAGER</th>
                    <th class="py-3 px-4 text-center">OPERATOR</th>
                    <th class="py-3 px-4 text-center">AGENT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                @php
                    $matrix = [
                        ['module' => 'Dashboard & WhatsApp Inbox', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'FULL', 'agent' => 'FULL'],
                        ['module' => 'Operations Center (Pedidos)', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'FULL', 'agent' => 'SCOPED (Asignados)'],
                        ['module' => 'Catálogo & Inventario', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'READ-ONLY', 'agent' => 'BLOCKED'],
                        ['module' => 'Clientes & Customer 360', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'FULL', 'agent' => 'SCOPED'],
                        ['module' => 'Configuración del Negocio', 'owner' => 'FULL', 'admin' => 'PARCIAL', 'manager' => 'BLOCKED', 'operator' => 'BLOCKED', 'agent' => 'BLOCKED'],
                        ['module' => 'Usuarios & Equipo', 'owner' => 'FULL', 'admin' => 'BLOCKED', 'manager' => 'BLOCKED', 'operator' => 'BLOCKED', 'agent' => 'BLOCKED'],
                        ['module' => 'Métodos de Pago', 'owner' => 'FULL', 'admin' => 'BLOCKED', 'manager' => 'BLOCKED', 'operator' => 'BLOCKED', 'agent' => 'BLOCKED'],
                        ['module' => 'Knowledge Hub & Grounding', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'BLOCKED', 'agent' => 'BLOCKED'],
                        ['module' => 'AI Playground', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'FULL', 'operator' => 'BLOCKED', 'agent' => 'BLOCKED'],
                        ['module' => 'Salud Comercial', 'owner' => 'FULL', 'admin' => 'FULL', 'manager' => 'READ-ONLY', 'operator' => 'READ-ONLY', 'agent' => 'BLOCKED'],
                    ];
                @endphp

                @foreach($matrix as $row)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                        <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">{{ $row['module'] }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-bold rounded text-[10px]">FULL</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 {{ $row['admin'] === 'BLOCKED' ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-bold' }} rounded text-[10px]">
                                {{ $row['admin'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 {{ $row['manager'] === 'BLOCKED' ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 font-bold' }} rounded text-[10px]">
                                {{ $row['manager'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 {{ $row['operator'] === 'BLOCKED' ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 font-bold' }} rounded text-[10px]">
                                {{ $row['operator'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 {{ $row['agent'] === 'BLOCKED' ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 font-bold' }} rounded text-[10px]">
                                {{ $row['agent'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
