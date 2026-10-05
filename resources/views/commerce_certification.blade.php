@extends('layouts.app')

@section('title', 'AIVA Commerce — Product Certification PR0–PR14')

@section('content')
<div class="space-y-6">

    <!-- HEADER TITLE & RUN BUTTON -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 text-xs font-extrabold rounded-full border border-purple-200 dark:border-purple-800">
                    CERTIFICACIÓN DE PRODUCTO
                </span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Suite de Homologación PR0–PR14</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Batería de 15 pruebas de producto automatizadas con generación de evidencia JSON, resiliencia e idempotencia.</p>
        </div>
        <div>
            <button onclick="runCertificationSuite()" class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-purple-500/20 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                EJECUTAR PR0–PR14 SUITE
            </button>
        </div>
    </div>

    <!-- TARJETA DE ESTADO DE CERTIFICACIÓN -->
    <div id="certSummaryCard" class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-2xl shrink-0">
                🏆
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">AIVA COMMERCE PRODUCT CERTIFICATE</h3>
                <p class="text-xs text-slate-400 mt-0.5">Tenant: {{ $tenant->name ?? 'DonutsFs' }} (#{{ $tenantId }}) — Estado Lifecycle: <span class="font-bold text-purple-600 dark:text-purple-400">{{ $lifecycle }}</span></p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <span class="text-xs text-slate-400 font-bold block">Pruebas Aprobadas</span>
                <span id="certScoreText" class="text-2xl font-black text-slate-900 dark:text-white">0 / 15</span>
            </div>
            <div id="certStatusBadge" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-500 font-extrabold text-xs rounded-xl">
                PENDIENTE DE EJECUCIÓN
            </div>
        </div>
    </div>

    <!-- GRILLA DE RESULTADOS DE CADA TEST PR0-PR14 -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
        <h3 class="font-extrabold text-base text-slate-900 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Desglose de Pruebas & Evidencias Generadas
        </h3>
        <div id="testsGrid" class="space-y-3">
            <p class="text-xs text-slate-400 py-6 text-center">Presiona <strong>EJECUTAR PR0–PR14 SUITE</strong> para iniciar la homologación automatizada.</p>
        </div>
    </div>

</div>

<!-- TOAST CONTAINER -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 hidden animate-in slide-in-from-bottom duration-300">
    <div class="bg-slate-900 text-white border border-slate-700 px-5 py-3.5 rounded-2xl shadow-2xl flex items-center space-x-3">
        <span id="toastIcon" class="text-emerald-400 text-lg">✓</span>
        <span id="toastText" class="text-xs font-bold">Suite ejecutada con éxito.</span>
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

    async function runCertificationSuite() {
        const grid = document.getElementById('testsGrid');
        grid.innerHTML = '<p class="text-xs text-slate-400 py-8 text-center animate-pulse">Ejecutando batería PR0–PR14 y capturando evidencias de producto...</p>';

        try {
            const res = await fetch('/api/v1/commerce-certification/run', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();

            if (data.status === 'SUCCESS') {
                document.getElementById('certScoreText').innerText = `${data.summary.passed} / ${data.summary.total}`;
                
                const badge = document.getElementById('certStatusBadge');
                if (data.summary.is_certified) {
                    badge.innerText = '🟢 CERTIFIED (15/15 PASS)';
                    badge.className = 'px-4 py-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 font-extrabold text-xs rounded-xl border border-emerald-200 dark:border-emerald-800 shadow-md';
                }

                grid.innerHTML = '';
                data.results.forEach(r => {
                    grid.innerHTML += `
                        <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <span class="px-2.5 py-1 bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400 font-mono font-black text-xs rounded-lg">${r.test_id}</span>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 dark:text-white">${r.name}</h4>
                                    <pre class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5 overflow-x-auto">${JSON.stringify(r.evidence)}</pre>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 shrink-0">
                                <span class="text-[10px] text-slate-400 font-mono">${r.duration_ms}ms</span>
                                <span class="px-3 py-1 bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 text-[10px] font-extrabold rounded-full border border-emerald-200 dark:border-emerald-800">
                                    ✓ ${r.status}
                                </span>
                            </div>
                        </div>
                    `;
                });

                showToast(`Suite PR0–PR14 completada: ${data.summary.passed}/${data.summary.total} PASS.`);
            }
        } catch (e) {
            showToast('Error de conexión al ejecutar la suite.', true);
        }
    }
</script>
@endsection
