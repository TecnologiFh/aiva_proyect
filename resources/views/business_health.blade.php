@extends('layouts.app')

@section('title', 'AIVA BOS — Business Health & Go-Live Gate')

@section('content')
<div class="space-y-6">

    <!-- HEADER TITLE & LIFECYCLE BADGE -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs font-extrabold rounded-full border border-emerald-200 dark:border-emerald-800">
                    GO-LIVE ENGINE
                </span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Business Health & Certificador de Producción</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Autodiagnóstico en tiempo real del Tenant. Evalúa los 12 puntos clave antes de permitir la salida a producción.</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="text-xs font-bold text-slate-400">Estado de Negocio:</span>
            <span id="lifecycleBadge" class="px-3.5 py-1.5 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 text-xs font-black rounded-xl border border-blue-200 dark:border-blue-800 uppercase tracking-wider">
                ● {{ $lifecycle }}
            </span>
        </div>
    </div>

    <!-- METRICAS DE SALUD DEL NEGOCIO (SCORE 12/12) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">Checks Completados</span>
            <div class="flex items-baseline space-x-2">
                <span class="text-3xl font-extrabold text-slate-900 dark:text-white" id="healthScore">{{ $health['score'] }}</span>
                <span class="text-slate-400 text-base font-bold">/ {{ $health['total'] }}</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full mt-3 overflow-hidden">
                <div id="healthProgressBar" class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: {{ $health['percentage'] }}%;"></div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">Porcentaje de Certificación</span>
            <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400" id="healthPercentage">{{ $health['percentage'] }}%</div>
            <p class="text-[11px] text-slate-400 mt-2 font-medium">Requerido 100% para activación en vivo</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Acción Go-Live (Backend Guard)</span>
            <div>
                <button id="btnActivateProduction" onclick="attemptActivateProduction()" class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-extrabold rounded-xl shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    ACTIVAR IA EN PRODUCCIÓN
                </button>
            </div>
        </div>
    </div>

    <!-- LISTA DE LOS 12 CHECKS ACCIONABLES DEL ENGINE -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100 dark:border-slate-800/80">
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Semáforo Maestro de Producción (12 Evaluaciones de Salud)
            </h3>
            <button onclick="refreshHealthData()" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl transition flex items-center gap-1">
                🔄 Re-evaluar
            </button>
        </div>

        <div id="checksGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($health['checks'] as $chk)
                @php $isPass = $chk['status'] === 'PASS'; @endphp
                <div class="p-4 rounded-2xl border transition flex items-start justify-between gap-4 {{ $isPass ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/40' : 'bg-rose-50/40 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/40' }}">
                    <div class="flex items-start space-x-3.5">
                        <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center font-bold text-sm {{ $isPass ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400' : 'bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400' }}">
                            {{ $isPass ? '✓' : '✕' }}
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h4 class="font-extrabold text-xs text-slate-900 dark:text-white">{{ $chk['title'] }}</h4>
                                <span class="px-2 py-0.5 text-[9px] font-extrabold rounded-md {{ $isPass ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400' : 'bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400' }}">
                                    {{ $chk['status'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ $chk['message'] }}</p>
                        </div>
                    </div>

                    @if(!$isPass && !empty($chk['action_url']))
                        <a href="{{ $chk['action_url'] }}" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold rounded-xl shadow-md transition shrink-0">
                            Corregir ➔
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 hidden animate-in slide-in-from-bottom duration-300">
    <div class="bg-slate-900 text-white border border-slate-700 px-5 py-3.5 rounded-2xl shadow-2xl flex items-center space-x-3">
        <span id="toastIcon" class="text-emerald-400 text-lg">✓</span>
        <span id="toastText" class="text-xs font-bold">Acción completada con éxito.</span>
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
        setTimeout(() => { toast.classList.add('hidden'); }, 4000);
    }

    async function refreshHealthData() {
        try {
            const res = await fetch('/api/v1/business-health');
            const data = await res.json();
            document.getElementById('healthScore').innerText = data.score;
            document.getElementById('healthPercentage').innerText = data.percentage + '%';
            document.getElementById('healthProgressBar').style.width = data.percentage + '%';
            document.getElementById('lifecycleBadge').innerText = '● ' + data.lifecycle;
            showToast('Evaluación de salud de negocio actualizada.');
        } catch (e) {
            showToast('Error al conectar con el servidor.', true);
        }
    }

    async function attemptActivateProduction() {
        try {
            const res = await fetch('/api/v1/business-health/activate-production', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast('Bloqueado por Backend: ' + data.message, true);
            }
        } catch (e) {
            showToast('Error de conexión al activar producción.', true);
        }
    }
</script>
@endsection
