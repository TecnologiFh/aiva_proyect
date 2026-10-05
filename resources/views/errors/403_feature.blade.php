@extends('layouts.app')

@section('title', 'Módulo No Contratado — AIVA Commerce')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-xl text-center space-y-6">
        <div class="w-16 h-16 bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center mx-auto text-3xl shadow-inner">
            🔒
        </div>
        <div class="space-y-2">
            <span class="px-3 py-1 bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 text-[10px] font-extrabold rounded-full border border-amber-200 dark:border-amber-800 uppercase tracking-wider">
                FEATURE_DISABLED (HTTP 403)
            </span>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">Módulo No Contratado</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed">
                Esta capacidad no se encuentra habilitada dentro del plan de suscripción de tu empresa. Contacta al Administrador de AIVA para activar este módulo.
            </p>
        </div>
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80">
            <a href="/dashboard" class="inline-flex items-center justify-center px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
                ← Volver al Dashboard Operativo
            </a>
        </div>
    </div>
</div>
@endsection
