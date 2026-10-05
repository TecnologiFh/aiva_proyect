@extends('layouts.app')

@section('title', 'AIVA — Business Knowledge Hub & Media Assets')

@section('content')
<div class="space-y-6">

    <!-- FLOATING TOAST NOTIFICATION MODAL (SIN ALERT() NATIVO) -->
    <div id="toast-modal" class="fixed top-6 right-6 z-50 transition-all duration-300 transform translate-y-[-20px] opacity-0 pointer-events-none">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xl flex items-center space-x-3.5 max-w-md">
            <div id="toast-icon-container" class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 pr-2">
                <h4 id="toast-title" class="text-xs font-bold text-slate-900 dark:text-white">Notificación AIVA</h4>
                <p id="toast-message" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium"></p>
            </div>
            <button onclick="closeToastModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-base font-bold p-1">&times;</button>
        </div>
    </div>

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Business Knowledge Hub & Recursos Multimedia</h1>
                <span class="px-2.5 py-0.5 bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 rounded-full text-xs font-bold">
                    WhatsApp Media & Links
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Gestión de recursos multimedia (PDFs de menú, enlaces a catálogos interactivos y fotos) que AIVA enviará a los clientes en WhatsApp.</p>
        </div>

        <div class="flex items-center space-x-3">
            <a href="/dashboard" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 transition">
                ← Volver al Dashboard
            </a>
            <button onclick="publishCatalog()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>PUBLICAR EN AIVA</span>
            </button>
        </div>
    </div>

    <!-- INPUT OCULTO DE ARCHIVO -->
    <input type="file" id="real-file-input" class="hidden" onchange="uploadSelectedFile(this.files)" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv">

    <!-- GRID DE 2 COLUMNAS (RECURSOS MULTIMEDIA VS BORRADORES TRANSACCIONALES) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- CAPA A: RECURSOS MULTIMEDIA & ENLACES PARA WHATSAPP -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-6 rounded-2xl space-y-4 shadow-sm">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span>A. Recursos & Enlaces WhatsApp</span>
                </h2>
                <span class="text-xs bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2.5 py-1 rounded-lg font-semibold">Media & Links</span>
            </div>

            <!-- REGISTRO DE ENLACE DE CATÁLOGO VISUAL -->
            <div class="space-y-2 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">Registrar Enlace de Catálogo Visual / Tienda</label>
                <div class="flex items-center space-x-2">
                    <input type="url" id="url-input" placeholder="https://minegocio.com/tienda" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button onclick="uploadUrlSource()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition whitespace-nowrap shadow-sm">
                        + Enlace
                    </button>
                </div>
                <p class="text-[10px] text-slate-400">AIVA enviará este link a los clientes cuando pidan ver el catálogo visual.</p>
            </div>

            <!-- DROPZONE ARCHIVOS PDF / FOTOS MENU -->
            <div onclick="document.getElementById('real-file-input').click()" 
                 ondragover="event.preventDefault()" 
                 ondrop="handleDrop(event)"
                 class="border-2 border-dashed border-slate-200 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 rounded-xl p-5 text-center space-y-2 transition cursor-pointer bg-slate-50 dark:bg-slate-900/50 hover:bg-blue-50 dark:hover:bg-blue-950/20">
                <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>
                <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Subir PDF de Carta o Fotos</p>
                <p class="text-[10px] text-slate-400">PDF, JPG, PNG (Se enviarán por WhatsApp)</p>
            </div>

            <!-- LISTA DINÁMICA DE RECURSOS ACTIVOS DE LA IA -->
            <div class="space-y-2 pt-2" id="sources-list-container">
                <p class="text-xs text-slate-400 text-center py-4">Cargando recursos activos...</p>
            </div>

            <!-- C. INSTRUCCIONES Y RESTRICCIONES PERSONALIZADAS DE LA EMPRESA PARA LA IA -->
            <div class="space-y-3 bg-gradient-to-br from-blue-50/70 to-indigo-50/50 dark:from-slate-900 dark:to-blue-950/40 p-4 rounded-xl border border-blue-200 dark:border-blue-800/60 shadow-sm mt-4">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-extrabold text-slate-900 dark:text-white flex items-center space-x-1.5">
                        <span>📝 Reglas & Restricciones Especiales del Negocio</span>
                    </label>
                    <span class="px-2 py-0.5 bg-blue-600 text-white font-extrabold text-[9px] rounded-full uppercase">IA CUSTOMizer</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Escriba las restricciones comerciales, políticas de envío, adelantos o reglas especiales de su empresa para que la IA las acate estrictamente.</p>
                <textarea id="custom-ai-instructions-text" rows="4" placeholder="Ejemplos:
- No realizamos entregas en días feriados ni domingos por la tarde.
- Todos los pedidos mayores a S/ 80.00 requieren 50% de adelanto Yape/Plin.
- No aceptamos llamadas telefónicas; la atención es 100% por WhatsApp." class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"></textarea>
                <button onclick="saveCustomAiInstructions()" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-md transition flex items-center justify-center space-x-1.5">
                    <span>💾 Guardar Reglas de Negocio en la IA</span>
                </button>
            </div>
        </div>

        <!-- CAPA B: BORRADORES TRANSACCIONALES PARA EL CATÁLOGO (EXCEL / MANUAL) -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-6 rounded-2xl space-y-4 md:col-span-2 shadow-sm">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        <span>B. Borradores Transaccionales de Productos (Pending Approval)</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Productos extraídos desde Excel o borrador manual que se publicarán en el catálogo oficial.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button id="btn-approve-all" onclick="approveAllCandidates()" class="hidden text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition shadow-sm flex items-center space-x-1">
                        <span>✓ Aprobar Todos e Importar al Catálogo</span>
                    </button>
                    <span id="pending-counter-badge" class="text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 px-3 py-1 rounded-full font-bold border border-amber-200 dark:border-amber-800">
                        0 Pendientes
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-slate-400 uppercase border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="pb-3 font-bold">Producto</th>
                            <th class="pb-3 font-bold">Categoría</th>
                            <th class="pb-3 font-bold">Precio (S/)</th>
                            <th class="pb-3 font-bold">Confianza IA</th>
                            <th class="pb-3 font-bold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="candidates-table-body" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <tr>
                            <td colspan="5" class="py-6 text-center text-xs text-slate-400">No hay borradores de catálogo pendientes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN DE BORRADORES -->
            <div id="candidates-pagination" class="hidden items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span id="candidates-pagination-info" class="text-slate-400 font-medium"></span>
                <div class="flex items-center space-x-2">
                    <button id="btn-prev-drafts" onclick="changeDraftsPage(-1)" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-200 transition">← Anterior</button>
                    <span id="candidates-page-num" class="font-bold text-slate-800 dark:text-slate-200">1 / 1</span>
                    <button id="btn-next-drafts" onclick="changeDraftsPage(1)" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-200 transition">Siguiente →</button>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    const activeTenantId = Number('{{ $tenantId ?? "" }}') || window.activeTenantId || 4;
    let toastTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadKnowledgeHubData();
    });

    function showToastModal(title, message, type = 'success') {
        const modal = document.getElementById('toast-modal');
        const titleEl = document.getElementById('toast-title');
        const msgEl = document.getElementById('toast-message');
        const iconBox = document.getElementById('toast-icon-container');

        titleEl.innerText = title;
        msgEl.innerText = message;

        if (type === 'success') {
            iconBox.className = 'w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0';
            iconBox.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`;
        } else {
            iconBox.className = 'w-9 h-9 rounded-xl bg-red-50 dark:bg-red-950 text-red-600 dark:text-red-400 flex items-center justify-center font-bold shrink-0';
            iconBox.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
        }

        modal.classList.remove('translate-y-[-20px]', 'opacity-0', 'pointer-events-none');
        modal.classList.add('translate-y-0', 'opacity-100');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(closeToastModal, 4500);
    }

    function closeToastModal() {
        const modal = document.getElementById('toast-modal');
        modal.classList.remove('translate-y-0', 'opacity-100');
        modal.classList.add('translate-y-[-20px]', 'opacity-0', 'pointer-events-none');
    }

    async function loadKnowledgeHubData() {
        try {
            const res = await fetch(`/api/v1/business/knowledge-data?tenant_id=${activeTenantId}`);
            const data = await res.json();
            renderSources(data.sources || []);
            renderCandidates(data.knowledge_items || []);
            const customTextarea = document.getElementById('custom-ai-instructions-text');
            if (customTextarea && data.custom_ai_instructions !== undefined) {
                customTextarea.value = data.custom_ai_instructions;
            }
        } catch (err) {
            console.error('Error cargando fuentes:', err);
        }
    }

    async function saveCustomAiInstructions() {
        const textarea = document.getElementById('custom-ai-instructions-text');
        const instructions = textarea.value.trim();

        try {
            const res = await fetch('/api/v1/business/custom-ai-instructions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tenant_id: activeTenantId,
                    custom_ai_instructions: instructions
                })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToastModal('Instrucciones Guardadas', 'AIVA acatará de forma inmediata tus reglas y restricciones comerciales en las conversaciones.');
            } else {
                showToastModal('Error', data.message || 'No se pudieron guardar las instrucciones.', 'error');
            }
        } catch(err) {
            showToastModal('Error de Servidor', 'No se pudo conectar con el servidor.', 'error');
        }
    }

    function renderSources(sources) {
        const container = document.getElementById('sources-list-container');
        if (sources.length === 0) {
            container.innerHTML = `<p class="text-xs text-slate-400 text-center py-4">No hay enlaces ni recursos registrados para WhatsApp.</p>`;
            return;
        }

        container.innerHTML = sources.map(s => `
            <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 flex justify-between items-center space-x-2">
                <div class="flex items-center space-x-3 overflow-hidden">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${s.source_type === 'WEB_URL' ? 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1' : 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'}"/></svg>
                    </div>
                    <div class="truncate">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">${s.title}</p>
                        <p class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold truncate">${s.external_url ? s.external_url : s.source_type}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-1.5 shrink-0">
                    ${s.source_type === 'EXCEL_CSV' ? `
                    <button onclick="reprocessSource(${s.id})" title="Volver a leer este archivo Excel" class="text-[10px] bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 px-2 py-1 rounded-lg font-bold transition">
                        ⚡ Re-extraer
                    </button>` : ''}
                    <button onclick="testSendResource('${s.title}')" title="Probar cómo envía AIVA este recurso por WhatsApp" class="text-[10px] bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 px-2 py-1 rounded-lg font-bold transition">
                        📱 Probar
                    </button>
                    <button onclick="deleteSource(${s.id})" title="Quitar recurso de WhatsApp" class="text-[10px] bg-red-50 dark:bg-red-950/40 hover:bg-red-100 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 px-2 py-1 rounded-lg font-bold transition">
                        ✕ Quitar
                    </button>
                </div>
            </div>
        `).join('');
    }

    let currentCandidates = [];
    let candidatePage = 1;
    const candidatePageSize = 10;

    function renderCandidates(items) {
        currentCandidates = items || [];
        candidatePage = 1;
        renderCandidatesPage();
    }

    function changeDraftsPage(delta) {
        const totalPages = Math.ceil(currentCandidates.length / candidatePageSize) || 1;
        candidatePage = Math.max(1, Math.min(totalPages, candidatePage + delta));
        renderCandidatesPage();
    }

    function renderCandidatesPage() {
        const tbody = document.getElementById('candidates-table-body');
        const badge = document.getElementById('pending-counter-badge');
        const btnApproveAll = document.getElementById('btn-approve-all');
        const paginContainer = document.getElementById('candidates-pagination');
        const paginInfo = document.getElementById('candidates-pagination-info');
        const pageNum = document.getElementById('candidates-page-num');
        const btnPrev = document.getElementById('btn-prev-drafts');
        const btnNext = document.getElementById('btn-next-drafts');

        badge.innerText = `${currentCandidates.length} Pendientes`;
        if (btnApproveAll) {
            if (currentCandidates.length > 0) {
                btnApproveAll.classList.remove('hidden');
                btnApproveAll.classList.add('flex');
            } else {
                btnApproveAll.classList.add('hidden');
                btnApproveAll.classList.remove('flex');
            }
        }

        if (currentCandidates.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-xs text-slate-400">No hay borradores pendientes. Los enlaces y PDFs registrados están listos para ser enviados por AIVA en WhatsApp.</td></tr>`;
            if (paginContainer) {
                paginContainer.classList.add('hidden');
                paginContainer.classList.remove('flex');
            }
            return;
        }

        const totalPages = Math.ceil(currentCandidates.length / candidatePageSize);
        if (candidatePage > totalPages) candidatePage = totalPages;

        const start = (candidatePage - 1) * candidatePageSize;
        const end = Math.min(start + candidatePageSize, currentCandidates.length);
        const slice = currentCandidates.slice(start, end);

        tbody.innerHTML = slice.map(item => `
            <tr>
                <td class="py-3.5 font-bold text-slate-800 dark:text-slate-100 text-xs">${item.detected_name}</td>
                <td class="py-3.5 text-slate-500 dark:text-slate-400 text-xs">${item.detected_category || 'General'}</td>
                <td class="py-3.5 font-bold text-blue-600 dark:text-blue-400 text-xs">S/ ${parseFloat(item.detected_price || 0).toFixed(2)}</td>
                <td class="py-3.5">
                    <span class="text-[10px] bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded-md border border-emerald-200 dark:border-emerald-800 font-bold">${(item.confidence_score * 100).toFixed(1)}%</span>
                </td>
                <td class="py-3.5 text-right space-x-2">
                    <button onclick="approveCandidate(${item.id})" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-sm">
                        ✓ Aprobar
                    </button>
                    <button onclick="rejectCandidate(${item.id})" class="px-3 py-1 bg-red-600/10 hover:bg-red-600/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-lg text-xs font-bold transition">
                        ✕ Descartar
                    </button>
                </td>
            </tr>
        `).join('');

        if (paginContainer) {
            if (totalPages > 1) {
                paginContainer.classList.remove('hidden');
                paginContainer.classList.add('flex');
                if (paginInfo) paginInfo.innerText = `Mostrando ${start + 1} - ${end} de ${currentCandidates.length}`;
                if (pageNum) pageNum.innerText = `${candidatePage} / ${totalPages}`;
                if (btnPrev) {
                    btnPrev.disabled = (candidatePage <= 1);
                    btnPrev.className = candidatePage <= 1 ? 'px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed text-xs' : 'px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 cursor-pointer text-xs font-semibold';
                }
                if (btnNext) {
                    btnNext.disabled = (candidatePage >= totalPages);
                    btnNext.className = candidatePage >= totalPages ? 'px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed text-xs' : 'px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 cursor-pointer text-xs font-semibold';
                }
            } else {
                paginContainer.classList.add('hidden');
                paginContainer.classList.remove('flex');
            }
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            uploadSelectedFile(e.dataTransfer.files);
        }
    }

    async function uploadSelectedFile(files) {
        if (!files || files.length === 0) return;
        const formData = new FormData();
        formData.append('tenant_id', activeTenantId);
        formData.append('file', files[0]);

        try {
            const res = await fetch('/api/v1/business/upload-source', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToastModal('Recurso Multimedia Registrado', 'AIVA ya conoce este archivo y podrá enviarlo a los clientes en WhatsApp.');
                loadKnowledgeHubData();
            } else {
                showToastModal('Error al Cargar', data.error || 'No se pudo subir el archivo.', 'error');
            }
        } catch (err) {
            showToastModal('Error de Servidor', 'No se pudo conectar con el servidor.', 'error');
        }
    }

    function testSendResource(title) {
        showToastModal('Prueba de Envío WhatsApp', `Simulación: AIVA enviará el recurso "${title}" cuando el cliente lo pida en WhatsApp.`);
    }

    async function deleteSource(sourceId) {
        try {
            const res = await fetch(`/api/v1/business/sources/${sourceId}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'DELETED') {
                showToastModal('Recurso Quitado', 'El recurso ha sido eliminado de los medios de AIVA.');
                loadKnowledgeHubData();
            }
        } catch (err) {
            showToastModal('Error', 'No se pudo eliminar el recurso.', 'error');
        }
    }

    async function uploadUrlSource() {
        const urlInput = document.getElementById('url-input');
        const url = urlInput.value.trim();
        if (!url) return showToastModal('Atención', 'Por favor ingresa una URL válida de tu catálogo visual.', 'error');

        const formData = new FormData();
        formData.append('tenant_id', activeTenantId);
        formData.append('external_url', url);
        formData.append('title', 'Catálogo Visual Interactivo');

        try {
            const res = await fetch('/api/v1/business/upload-source', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                urlInput.value = '';
                showToastModal('Catálogo Visual Registrado', 'AIVA compartirá este enlace con los clientes en WhatsApp cuando pidan ver la tienda o catálogo.');
                loadKnowledgeHubData();
            }
        } catch (err) {
            showToastModal('Error', 'Error registrando URL.', 'error');
        }
    }

    async function approveCandidate(id) {
        try {
            const res = await fetch(`/api/v1/business/knowledge-items/${id}/approve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'APPROVED') {
                showToastModal('Producto Aprobado', 'El producto ha sido importado al Catálogo & Inventario oficial con éxito.');
                loadKnowledgeHubData();
            } else {
                showToastModal('Error', 'Error al aprobar borrador.', 'error');
            }
        } catch (err) {
            showToastModal('Error', 'Error al aprobar borrador.', 'error');
        }
    }

    async function approveAllCandidates() {
        try {
            showToastModal('Importando', 'Aprobando e importando productos al Catálogo oficial...');
            const res = await fetch('/api/v1/business/knowledge-items/approve-all', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'APPROVED_ALL') {
                showToastModal('¡Catálogo Actualizado!', data.message || 'Todos los productos han sido importados a Catálogo & Inventario.');
                loadKnowledgeHubData();
            } else {
                showToastModal('Error', data.message || 'Error al aprobar productos.', 'error');
            }
        } catch (err) {
            showToastModal('Error', 'Error al procesar la aprobación masiva.', 'error');
        }
    }

    async function reprocessSource(sourceId) {
        try {
            showToastModal('Procesando Archivo', 'Leyendo y extrayendo los productos reales desde el archivo Excel...');
            const res = await fetch(`/api/v1/business/sources/${sourceId}/reprocess`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'SUCCESS') {
                showToastModal('Extracción Exitosa', `Se han extraído ${data.items_count} productos reales desde tu Excel.`);
                loadKnowledgeHubData();
            } else {
                showToastModal('Error', data.message || 'No se pudo reprocesar el archivo.', 'error');
            }
        } catch (err) {
            showToastModal('Error', 'Error al comunicarse con el servidor.', 'error');
        }
    }

    async function rejectCandidate(id) {
        try {
            const res = await fetch(`/api/v1/business/knowledge-items/${id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'REJECTED') {
                showToastModal('Producto Descartado', 'El borrador ha sido retirado de la lista.');
                loadKnowledgeHubData();
            }
        } catch (err) {
            showToastModal('Error', 'Error al descartar borrador.', 'error');
        }
    }

    async function publishCatalog() {
        try {
            const res = await fetch(`/api/v1/business/publish-catalog`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: activeTenantId })
            });
            const data = await res.json();
            if (data.status === 'PUBLISHED') {
                showToastModal('Catálogo Publicado', `Se ha publicado la Versión ${data.version.version_number} en AIVA con ${data.version.published_items_count} productos oficiales.`);
                loadKnowledgeHubData();
            }
        } catch (err) {
            showToastModal('Error', 'Error al publicar el catálogo.', 'error');
        }
    }
</script>
@endsection
