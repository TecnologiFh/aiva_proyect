@extends('layouts.app')

@section('title', 'AIVA SaaS — Control Plane (/admin)')

@section('content')
<div class="space-y-8 text-slate-100 font-sans">

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 p-6 rounded-3xl border border-blue-900/50 shadow-2xl">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-black rounded-full border border-emerald-500/30 tracking-wider">
                    👑 SUPER ADMIN PLATFORM CONTROL PLANE
                </span>
                <h1 class="text-3xl font-black tracking-tight text-white">AIVA SaaS Control Plane (/admin)</h1>
            </div>
            <p class="text-xs text-slate-300 mt-2 font-medium">Panel de gobernanza SaaS multi-tenant 24/7. Monitoreo de matriz de salud, economía unitaria, estado de tenants y suite de certificación.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="runCertificationSuite()" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-xs font-black rounded-xl shadow-lg transition flex items-center gap-2">
                🛡️ Ejecutar 20 Gates de Certificación
            </button>
            <a href="/settings/ai" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-lg transition flex items-center gap-2">
                🤖 Gateway de IA
            </a>
        </div>
    </div>

    <!-- GLOBAL SAAS METRICS WIDGETS -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-5 bg-slate-900/90 border border-slate-800/80 rounded-2xl shadow-xl space-y-1">
            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">MRR SaaS Estimado</span>
            <div class="text-2xl font-black text-emerald-400">S/ {{ number_format($totalOrders * 35, 2) }}</div>
            <span class="text-[10px] text-slate-500">Basado en pedidos procesados</span>
        </div>
        <div class="p-5 bg-slate-900/90 border border-slate-800/80 rounded-2xl shadow-xl space-y-1">
            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Tenants Activos</span>
            <div class="text-2xl font-black text-blue-400">{{ count($tenants) }} Empresas</div>
            <span class="text-[10px] text-slate-500">Pastelería, Restaurantes, Retail</span>
        </div>
        <div class="p-5 bg-slate-900/90 border border-slate-800/80 rounded-2xl shadow-xl space-y-1">
            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Pedidos Totales</span>
            <div class="text-2xl font-black text-purple-400">{{ $totalOrders }} Órdenes</div>
            <span class="text-[10px] text-slate-500">Ciclo 4D State Machine</span>
        </div>
        <div class="p-5 bg-slate-900/90 border border-slate-800/80 rounded-2xl shadow-xl space-y-1">
            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Handovers Escalados</span>
            <div class="text-2xl font-black text-amber-400">{{ $totalHandovers }} Atenciones</div>
            <span class="text-[10px] text-slate-500">MTTA medio &lt; 2 min</span>
        </div>
        <div class="p-5 bg-slate-900/90 border border-slate-800/80 rounded-2xl shadow-xl space-y-1">
            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Redis Emergency Queue</span>
            <div class="text-2xl font-black {{ $emergencyLogsCount > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                {{ $emergencyLogsCount }} PENDIENTES
            </div>
            <span class="text-[10px] text-slate-500">Fail-Safe Recovery Path</span>
        </div>
    </div>

    <!-- HEALTH MATRIX ESTRUCTURADA -->
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    🩺 Health Matrix Estructurada en Tiempo Real
                </h3>
                <p class="text-xs text-slate-400">Supervisión operacional de latencias, tasa de errores y workers activos.</p>
            </div>
            <button onclick="fetchHealthMatrix()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-xl text-slate-300 transition flex items-center gap-1.5">
                🔄 Actualizar Matrix
            </button>
        </div>

        <div id="health-matrix-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
            <!-- Cargado dinámicamente vía API /api/v1/admin/health-matrix -->
            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800 animate-pulse text-xs text-slate-500">
                Cargando matriz de salud...
            </div>
        </div>
    </div>

    <!-- DIRECTORIO Y GESTIÓN DE TENANTS CON UNIT ECONOMICS -->
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-white">🏢 Directorio de Tenants & Economía Unitaria SaaS</h3>
                <p class="text-xs text-slate-400">Gobernanza de estados (PRODUCTION, PAUSED, SUSPENDED) y asignación de costos por empresa.</p>
            </div>
        </div>

        <div class="overflow-x-auto border border-slate-800 rounded-2xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/80 text-slate-400 font-extrabold uppercase text-[10px] border-b border-slate-800">
                    <tr>
                        <th class="p-3.5">ID / Comercio</th>
                        <th class="p-3.5">Estado Operativo</th>
                        <th class="p-3.5">Órdenes</th>
                        <th class="p-3.5">Conversaciones</th>
                        <th class="p-3.5">Costo Variable</th>
                        <th class="p-3.5">Costo Fijo VPS</th>
                        <th class="p-3.5">MRR</th>
                        <th class="p-3.5">Margen Bruto</th>
                        <th class="p-3.5 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @foreach($tenants as $t)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="p-3.5 font-bold text-white">
                                #{{ $t['id'] }} — {{ $t['name'] }}
                            </td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 text-[10px] font-black rounded-full border {{ $t['status'] === 'PRODUCTION' || $t['status'] === 'ACTIVE' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : ($t['status'] === 'PAUSED' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-rose-500/20 text-rose-400 border-rose-500/30') }}">
                                    {{ $t['status'] }}
                                </span>
                            </td>
                            <td class="p-3.5 font-mono text-slate-300">{{ $t['orders'] }} ops</td>
                            <td class="p-3.5 font-mono text-slate-300">{{ $t['conversations'] }} msgs</td>
                            <td class="p-3.5 font-mono text-rose-400">S/ {{ $t['var_cost'] }}</td>
                            <td class="p-3.5 font-mono text-slate-400">S/ {{ $t['fixed_cost'] }}</td>
                            <td class="p-3.5 font-mono text-emerald-400 font-bold">S/ {{ $t['mrr'] }}</td>
                            <td class="p-3.5 font-mono font-bold {{ (float)$t['gross_margin'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                S/ {{ $t['gross_margin'] }}
                            </td>
                            <td class="p-3.5 text-right space-x-1">
                                <select onchange="changeTenantStatus({{ $t['id'] }}, this.value)" class="bg-slate-950 border border-slate-700 text-slate-200 text-[11px] font-bold rounded-lg px-2 py-1 outline-none">
                                    <option value="PRODUCTION" {{ $t['status'] === 'PRODUCTION' || $t['status'] === 'ACTIVE' ? 'selected' : '' }}>🟢 PRODUCTION</option>
                                    <option value="PAUSED" {{ $t['status'] === 'PAUSED' ? 'selected' : '' }}>🟡 PAUSED</option>
                                    <option value="SUSPENDED" {{ $t['status'] === 'SUSPENDED' ? 'selected' : '' }}>🔴 SUSPENDED</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- 20 PRODUCTION CERTIFICATION GATES MODAL/PANEL -->
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    🛡️ Production Certification Suite (20 Gates Automatizados)
                </h3>
                <p class="text-xs text-slate-400">Verificación de requisitos técnicos, seguridad, idempotencia y failover antes de producción.</p>
            </div>
            <button onclick="runCertificationSuite()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black text-xs rounded-xl shadow-lg transition">
                ⚡ Ejecutar Auditoría Completa
            </button>
        </div>

        <div id="cert-results-container" class="space-y-2">
            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800 text-xs text-slate-500">
                Presiona "Ejecutar Auditoría Completa" para evaluar los 20 Gates automatizados.
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        fetchHealthMatrix();
    });

    async function fetchHealthMatrix() {
        try {
            const res = await fetch('/api/v1/admin/health-matrix');
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                renderHealthMatrix(data.health_matrix);
            }
        } catch (err) {
            console.error('Error al cargar Health Matrix:', err);
        }
    }

    function renderHealthMatrix(services) {
        const container = document.getElementById('health-matrix-container');
        container.innerHTML = services.map(s => {
            const statusColor = s.status === 'GREEN' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' :
                               (s.status === 'YELLOW' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-rose-500/20 text-rose-400 border-rose-500/30');
            const statusDot = s.status === 'GREEN' ? '🟢' : (s.status === 'YELLOW' ? '🟡' : '🔴');
            
            return `
                <div class="p-4 bg-slate-950/80 rounded-2xl border border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-xs text-white">${s.name}</span>
                        <span class="px-2 py-0.5 text-[10px] font-black rounded-full border ${statusColor}">
                            ${statusDot} ${s.status}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[11px] font-mono pt-1 text-slate-400">
                        <div>Latencia: <span class="text-emerald-400 font-bold">${s.latency_ms} ms</span></div>
                        <div>Workers: <span class="text-blue-400 font-bold">${s.active_workers}</span></div>
                        <div>Error Rate: <span class="text-slate-300">${s.error_rate}</span></div>
                        <div>Versión: <span class="text-purple-400">${s.version}</span></div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function changeTenantStatus(tenantId, newStatus) {
        try {
            const res = await fetch('/api/v1/admin/tenant/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ tenant_id: tenantId, status: newStatus })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, true);
            }
        } catch (err) {
            showToast('Error al actualizar estado del tenant.', true);
        }
    }

    async function runCertificationSuite() {
        const container = document.getElementById('cert-results-container');
        container.innerHTML = `<div class="p-4 bg-slate-950/60 rounded-2xl text-xs text-emerald-400 font-mono animate-pulse">Ejecutando suite de 20 Gates de Certificación con generación de Evidencia Auditable...</div>`;
        try {
            const res = await fetch('/api/v1/admin/certification/run');
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                container.innerHTML = `
                    <div class="p-5 bg-slate-950 rounded-2xl border border-emerald-500/30 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800 pb-4">
                            <div>
                                <h4 class="font-black text-base text-emerald-400 flex items-center gap-2">
                                    ${data.certified ? '🟢 STAGING CERTIFIED FOR PILOT (' + data.result_code + ')' : '🟡 CERTIFICACIÓN INCOMPLETA'}
                                </h4>
                                <p class="text-xs text-slate-400 mt-1">
                                    Master Correlation ID: <span class="font-mono text-purple-400 font-bold">${data.master_correlation_id}</span> | 
                                    Git SHA: <span class="font-mono text-amber-400 font-bold">${data.git_commit_sha}</span> | 
                                    Duración: <span class="font-mono text-emerald-400">${data.total_duration_ms} ms</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 bg-slate-900 text-slate-300 text-xs font-mono font-bold rounded-lg border border-slate-700">
                                    ENV: ${data.environment.toUpperCase()}
                                </span>
                                <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-black rounded-full border border-emerald-500/30 font-mono">
                                    SCORE: ${data.score_percentage}% (${data.passed_count}/${data.total_gates})
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            ${data.gates.map(g => `
                                <div class="p-3.5 bg-slate-900/90 rounded-xl border border-slate-800/80 space-y-2 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-white flex items-center gap-1.5">
                                            ${g.critical ? '<span class="text-rose-400 text-[10px] uppercase font-black tracking-wider px-1.5 py-0.5 bg-rose-500/20 rounded border border-rose-500/30">CRÍTICO</span>' : ''}
                                            Gate ${g.id}: ${g.name}
                                        </span>
                                        <span class="px-2 py-0.5 text-[10px] font-black rounded-full border ${g.status === 'PASS' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border-rose-500/30'}">
                                            ${g.status} (${g.duration_ms}ms)
                                        </span>
                                    </div>
                                    <div class="p-2 bg-slate-950/80 rounded-lg font-mono text-[10px] text-slate-400 border border-slate-800 space-y-0.5">
                                        <div class="text-purple-400 font-bold">Correlation: ${g.correlation_id}</div>
                                        ${Object.entries(g.evidence).map(([k, v]) => `<div>${k}: <span class="text-slate-200">${typeof v === 'object' ? JSON.stringify(v) : v}</span></div>`).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        } catch (err) {
            showToast('Error al ejecutar la suite de certificación.', true);
        }
    }
</script>
@endsection
