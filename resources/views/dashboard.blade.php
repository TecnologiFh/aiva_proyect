@extends('layouts.app')

@section('title', 'AIVA — Dashboard & WhatsApp Inbox')

@section('content')
<div class="space-y-6">

    <!-- PAGE TITLE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Dashboard & Inbox WhatsApp</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Monitoreo de conversaciones en tiempo real, latencia de Groq LPU e intervención humana.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="/settings/catalog" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold rounded-xl border border-blue-200 dark:border-blue-800 transition hover:bg-blue-100">
                + Subir Menú o Catálogo
            </a>
        </div>
    </div>

    <!-- ALERT BANNER DE INTERVENCIÓN HUMANA -->
    <div id="handoverAlertBanner" class="hidden p-4 bg-amber-500 text-slate-950 rounded-2xl shadow-xl font-extrabold text-xs flex items-center justify-between animate-bounce">
        <div class="flex items-center space-x-2">
            <span class="text-lg">🚨</span>
            <span>¡ATENCIÓN REQUERIDA! Hay cliente(s) esperando la atención de un asesor humano en WhatsApp.</span>
        </div>
        <span class="px-3 py-1 bg-slate-950 text-white rounded-xl text-[10px] font-black uppercase">HANDOVER ACTIVO</span>
    </div>

    <!-- METRICS KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Conversaciones</span>
                <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
            </div>
            <div id="metric-total-convs" class="text-3xl font-extrabold text-slate-900 dark:text-white mt-3">0</div>
            <p class="text-[11px] text-slate-400 mt-1">Frontera de aislamiento por Tenant</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Atendidas por IA</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div id="metric-ai-active" class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">0</div>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400/80 mt-1 font-semibold">Respuesta activa por Groq LPU</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Handover Humano</span>
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>
            <div id="metric-handover" class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">0</div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400/80 mt-1 font-semibold">Intervención de operador</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Latencia Groq</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
            </div>
            <div id="metric-latency" class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">0 ms</div>
            <p class="text-[11px] text-purple-600 dark:text-purple-300 mt-1">Velocidad promedio ultra-rápida</p>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }
    </style>

    <!-- PANEL PRINCIPAL DE CHAT INBOX DE WHATSAPP -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[620px] max-h-[620px] overflow-hidden">
        
        <!-- COLUMNA IZQUIERDA: LISTA DE CHATS DE WHATSAPP CON BUSCADOR -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 flex flex-col h-full max-h-[620px] shadow-sm overflow-hidden">
            <div class="flex flex-col space-y-2 pb-3 border-b border-slate-100 dark:border-slate-800 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Chats WhatsApp</h3>
                    <span id="chat-badge-count" class="text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2.5 py-0.5 rounded-full font-bold">0</span>
                </div>
                <!-- BUSCADOR EN TIEMPO REAL DE CLIENTES Y CHATS -->
                <div class="relative w-full">
                    <svg class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="chat-search-input" onkeyup="filterChatList()" placeholder="Buscar cliente por nombre o teléfono..." class="w-full pl-9 pr-3 py-1.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                </div>
            </div>

            <div id="conversations-list" class="space-y-2 pt-3 flex-1 overflow-y-auto max-h-[480px] pr-1 custom-scrollbar">
                <p class="text-xs text-slate-400 text-center py-10">Cargando conversaciones de WhatsApp...</p>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CONVERSACIÓN EN TIEMPO REAL & PANEL DE HANDOVER -->
        <div class="lg:col-span-2 bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col h-full max-h-[620px] shadow-sm overflow-hidden">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-slate-700 dark:text-slate-200 text-sm">
                        💬
                    </div>
                    <div>
                        <h3 id="active-contact-name" class="font-bold text-sm text-slate-900 dark:text-white">Selecciona una conversación</h3>
                        <p id="active-contact-phone" class="text-xs text-slate-400 font-medium">WhatsApp Chat ID</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <span id="active-status-badge" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold rounded-full">INACTIVO</span>
                    <button id="toggle-handover-btn" onclick="toggleCurrentHandover()" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition disabled:opacity-50" disabled>
                        Pausar IA (Intervenir)
                    </button>
                </div>
            </div>

            <!-- CHAT MESSAGES STREAM (CON SCROLL INTERNO AUTÓNOMO) -->
            <div id="messages-container" class="flex-1 min-h-0 overflow-y-auto max-h-[440px] py-4 space-y-3 pr-2 custom-scrollbar">
                <p class="text-xs text-slate-400 text-center py-20">Selecciona un chat del panel izquierdo para ver la conversación.</p>
            </div>

            <!-- AGENT MANUAL INPUT BAR -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center space-x-2 flex-shrink-0">
                <input type="text" id="agent-message-input" onkeypress="if(event.key==='Enter') sendManualMessage()" placeholder="Escribe una respuesta manual como operador..." class="flex-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button onclick="sendManualMessage()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition shadow-sm">
                    Enviar
                </button>
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    let activeConversationId = null;
    const currentTenantId = {{ $tenantId ?? 5 }};
    let lastHandoverCount = 0;

    function playChimeAlert() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.35);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch(e) {}
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadMetrics();
        loadConversations();
        setInterval(() => {
            loadMetrics();
            loadConversations();
        }, 3000);
    });

    async function loadMetrics() {
        try {
            const res = await fetch(`/api/v1/dashboard/metrics?tenant_id=${currentTenantId}`);
            const data = await res.json();
            const el1 = document.getElementById('kpi-total-convs');
            const el2 = document.getElementById('kpi-ai-active');
            const el3 = document.getElementById('kpi-handover');
            const el4 = document.getElementById('kpi-latency');
            if (el1) el1.innerText = data.total_conversations ?? 0;
            if (el2) el2.innerText = data.ai_active ?? 0;
            if (el3) el3.innerText = data.human_handover ?? 0;
            if (el4) el4.innerText = (data.average_latency_ms ?? 0) + ' ms';
        } catch (e) {
            console.error('Error metrics:', e);
        }
    }

    async function loadConversations() {
        try {
            const res = await fetch(`/api/v1/dashboard/conversations?tenant_id=${currentTenantId}`);
            const data = await res.json();
            const convs = data.conversations || [];
            const metrics = data.metrics || {};
            const container = document.getElementById('conversations-list');
            
            const badgeCount = document.getElementById('chat-badge-count');
            if (badgeCount) badgeCount.innerText = convs.length;

            if (metrics.total_conversations !== undefined) {
                const elTotal = document.getElementById('metric-total-convs');
                const elAi = document.getElementById('metric-ai-active');
                const elHandover = document.getElementById('metric-handover');
                const elLatency = document.getElementById('metric-latency');

                if (elTotal) elTotal.innerText = metrics.total_conversations;
                if (elAi) elAi.innerText = metrics.ai_active;
                if (elHandover) elHandover.innerText = metrics.human_handover;
                if (elLatency) elLatency.innerText = metrics.avg_latency_ms + 'ms';
            }

            // Detección de Handover y Sonido de Alerta
            const currentHandovers = convs.filter(c => c.mode === 'HUMAN' || c.status === 'HANDOVER' || c.status === 'HUMAN').length;
            const banner = document.getElementById('handoverAlertBanner');
            if (currentHandovers > 0) {
                if (banner) banner.classList.remove('hidden');
                if (currentHandovers > lastHandoverCount) {
                    playChimeAlert();
                }
            } else {
                if (banner) banner.classList.add('hidden');
            }
            lastHandoverCount = currentHandovers;

            allLoadedConversations = convs;

            if (convs.length === 0) {
                container.innerHTML = `<p class="text-xs text-slate-400 text-center py-10">No hay chats activos para este Tenant.</p>`;
                return;
            }

            // Auto-seleccionar primer chat si ninguno está activo
            if (!activeConversationId && convs.length > 0) {
                selectConversation(convs[0].id, convs[0].customer_name, convs[0].customer_phone, convs[0].mode || convs[0].status);
            } else if (activeConversationId) {
                loadMessages(activeConversationId);
            }

            filterChatList();
        } catch (err) {
            console.error('Error cargando conversaciones:', err);
        }
    }

    function filterChatList() {
        const query = (document.getElementById('chat-search-input')?.value || '').toLowerCase().trim();
        const container = document.getElementById('conversations-list');
        if (!container) return;

        const filtered = allLoadedConversations.filter(c => {
            const name = (c.customer_name || '').toLowerCase();
            const phone = (c.customer_phone || '').toLowerCase();
            return name.includes(query) || phone.includes(query);
        });

        if (filtered.length === 0) {
            container.innerHTML = `<p class="text-xs text-slate-400 text-center py-10">No se encontraron chats para "${query}".</p>`;
            return;
        }

        renderConversationCards(filtered);
    }

    function renderConversationCards(convs) {
        const container = document.getElementById('conversations-list');
        if (!container) return;

        try {
            container.innerHTML = convs.map(c => {
                const isHandover = c.mode === 'HUMAN' || c.status === 'HANDOVER' || c.status === 'HUMAN';
                const isSelected = activeConversationId === c.id;

                let cardStyle = '';
                let statusBadge = '';
                let statusDot = '';

                if (isHandover) {
                    cardStyle = isSelected
                        ? 'bg-amber-100/80 dark:bg-amber-950/60 border-l-4 border-l-amber-500 border-amber-300 dark:border-amber-700 shadow-md scale-[1.01]'
                        : 'bg-amber-50/60 dark:bg-amber-950/30 border-l-4 border-l-amber-400 border-amber-200 dark:border-amber-800/60 hover:bg-amber-100/50';
                    statusDot = `<span class="relative flex h-2.5 w-2.5 mr-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span></span>`;
                    statusBadge = `<span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 flex items-center">HANDOVER</span>`;
                } else if (isSelected) {
                    cardStyle = 'bg-blue-50 dark:bg-blue-950/70 border-l-4 border-l-blue-600 border-blue-300 dark:border-blue-700 shadow-md scale-[1.01]';
                    statusDot = `<span class="relative flex h-2.5 w-2.5 mr-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-600"></span></span>`;
                    statusBadge = `<span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-500/30 flex items-center">EN CHAT</span>`;
                } else {
                    cardStyle = 'bg-slate-50 dark:bg-slate-900/50 border-l-4 border-l-emerald-500 border-slate-200 dark:border-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-800/50';
                    statusDot = `<span class="inline-flex rounded-full h-2 w-2 bg-emerald-500 mr-1.5"></span>`;
                    statusBadge = `<span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center">IA ACTIVA</span>`;
                }

                const safeName = addslashes(c.customer_name || 'Cliente');

                return `
                    <div onclick="selectConversation(${c.id}, '${safeName}', '${c.customer_phone}', '${isHandover ? 'HANDOVER' : 'AI_ACTIVE'}')" 
                         class="p-3.5 rounded-xl border transition-all duration-200 cursor-pointer flex justify-between items-center ${cardStyle}">
                        <div class="overflow-hidden pr-2">
                            <div class="flex items-center">
                                ${statusDot}
                                <p class="text-xs font-extrabold text-slate-900 dark:text-white truncate">${c.customer_name || 'Cliente'}</p>
                            </div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">${c.customer_phone || ''}</p>
                        </div>
                        ${statusBadge}
                    </div>
                `;
            }).join('');
        } catch (e) {
            console.error('Error conversations:', e);
        }
    }

    function addslashes(str) {
        return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
    }

    async function selectConversation(id, name, phone, status) {
        activeConversationId = id;
        document.getElementById('active-contact-name').innerText = name;
        document.getElementById('active-contact-phone').innerText = phone;
        
        const badge = document.getElementById('active-status-badge');
        const btn = document.getElementById('toggle-handover-btn');
        btn.disabled = false;

        if (status === 'AI_ACTIVE' || status === 'AI') {
            badge.innerText = 'IA ACTIVA';
            badge.className = 'px-3 py-1 bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold rounded-full';
            btn.innerText = 'Pausar IA (Intervenir)';
            btn.className = 'px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm transition';
        } else {
            badge.innerText = 'HANDOVER (HUMANO)';
            badge.className = 'px-3 py-1 bg-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-bold rounded-full';
            btn.innerText = 'Reactivar IA';
            btn.className = 'px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition';
        }

        loadMessages(id);
    }

    async function loadMessages(id) {
        try {
            const res = await fetch(`/api/v1/dashboard/conversations/${id}/messages`);
            const responseData = await res.json();
            const msgs = Array.isArray(responseData) ? responseData : (responseData.messages || []);
            const container = document.getElementById('messages-container');

            if (!container) return;

            if (msgs.length === 0) {
                container.innerHTML = `<p class="text-xs text-slate-400 text-center py-10">No hay mensajes en esta conversación.</p>`;
                return;
            }

            const isAtBottom = (container.scrollHeight - container.scrollTop - container.clientHeight) < 120;
            const isNewConversation = (typeof currentLoadedConvId === 'undefined' || currentLoadedConvId !== id);

            container.innerHTML = msgs.map(m => {
                const meta = m.metadata ? (typeof m.metadata === 'string' ? JSON.parse(m.metadata) : m.metadata) : {};
                const attachments = m.attachments || meta.attachments || [];
                const mediaUrl = meta.media_url || m.media_url || null;
                const voucher = meta.voucher || null;

                let mediaHtml = '';
                if (Array.isArray(attachments) && attachments.length > 0) {
                    mediaHtml = attachments.map(att => {
                        const imgUrl = att.display_url || att.url;
                        const caption = att.caption || 'Carta / Menú del Negocio';
                        return `
                            <div class="my-2 max-w-xs sm:max-w-md">
                                <div onclick="openVoucherLightbox('${imgUrl}')" class="cursor-pointer group relative overflow-hidden rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-900/90 shadow-md">
                                    <img src="${imgUrl}" class="max-h-[320px] w-auto mx-auto object-contain group-hover:scale-[1.01] transition duration-200 p-1 rounded-xl" alt="${caption}">
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center backdrop-blur-[2px]">
                                        <span class="px-3 py-1.5 bg-blue-600 text-white text-[11px] font-extrabold rounded-lg shadow-xl flex items-center space-x-1.5">
                                            <span>🔍 Ampliar Imagen HD</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="px-2.5 py-1.5 text-[11px] font-bold bg-slate-900 text-slate-200 flex items-center justify-between rounded-b-xl border-t border-slate-800">
                                    <span class="truncate">📄 ${caption}</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else if (mediaUrl) {
                    mediaHtml = `
                        <div class="my-2.5 max-w-xs sm:max-w-md">
                            <div onclick="openVoucherLightbox('${mediaUrl}')" class="cursor-pointer group relative overflow-hidden rounded-2xl border border-slate-300 dark:border-slate-700 bg-slate-900/90 shadow-md">
                                <img src="${mediaUrl}" class="max-h-[380px] w-auto mx-auto object-contain group-hover:scale-[1.01] transition duration-200 p-1.5 rounded-2xl" alt="Comprobante Adjunto">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center backdrop-blur-[2px]">
                                    <span class="px-4 py-2 bg-blue-600 text-white text-xs font-extrabold rounded-xl shadow-xl flex items-center space-x-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                        <span>Ampliar Comprobante HD</span>
                                    </span>
                                </div>
                            </div>
                            <div onclick="openVoucherLightbox('${mediaUrl}')" class="cursor-pointer px-3 py-2 text-xs font-bold bg-slate-900 text-white flex items-center justify-between rounded-b-2xl border-t border-slate-800 hover:bg-slate-800 transition">
                                <span class="flex items-center space-x-1.5 text-slate-200">
                                    <span>🧾 Comprobante Adjunto</span>
                                </span>
                                <span class="text-blue-400 font-extrabold flex items-center space-x-1 hover:underline">
                                    <span>Ver en HD legible</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </span>
                            </div>
                        </div>
                    `;
                }

                let badgeHtml = '';
                if (voucher) {
                    if (voucher.is_valid) {
                        badgeHtml = `<div class="mt-1 px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 font-extrabold text-[10px]">✅ Comprobante Válido ${voucher.amount ? 'S/ ' + voucher.amount : ''} ${voucher.operation_number ? '(N° ' + voucher.operation_number + ')' : ''}</div>`;
                    } else {
                        badgeHtml = `<div class="mt-1 px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-300 font-extrabold text-[10px]">❌ Comprobante Rechazado (${voucher.rejection_reason || 'Formato no válido'})</div>`;
                    }
                }

                return `
                    <div class="flex flex-col ${m.sender_type === 'CUSTOMER' || m.sender_type === 'USER' ? 'items-start' : 'items-end'} mb-2">
                        <div class="max-w-md px-3.5 py-2 rounded-2xl text-xs ${m.sender_type === 'CUSTOMER' || m.sender_type === 'USER' ? 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200' : (m.sender_type === 'AI' ? 'bg-blue-600 text-white' : 'bg-amber-600 text-white')}">
                            ${mediaHtml}
                            <p class="font-medium whitespace-pre-line">${m.content || m.message_body || ''}</p>
                            ${badgeHtml}
                        </div>
                        <span class="text-[9px] text-slate-400 mt-1 px-1 font-semibold">${m.sender_type} • ${m.created_at ? new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : ''}</span>
                    </div>
                `;
            }).join('');

            if (isNewConversation || isAtBottom) {
                container.scrollTop = container.scrollHeight;
            }
            window.currentLoadedConvId = id;
        } catch (e) {
            console.error('Error messages:', e);
        }
    }

    async function toggleCurrentHandover() {
        if (!activeConversationId) return;
        try {
            const res = await fetch(`/api/v1/dashboard/conversations/${activeConversationId}/toggle-handover`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            loadConversations();
            const newStatus = data.new_mode === 'AI' ? 'AI_ACTIVE' : 'HANDOVER';
            selectConversation(activeConversationId, document.getElementById('active-contact-name').innerText, document.getElementById('active-contact-phone').innerText, newStatus);
        } catch (e) {
            console.error('Error handover:', e);
        }
    }

    async function sendManualMessage() {
        const input = document.getElementById('agent-message-input');
        const text = input.value.trim();
        if (!text || !activeConversationId) return;

        try {
            await fetch(`/api/v1/dashboard/conversations/${activeConversationId}/send-agent-message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: text })
            });
            input.value = '';
            loadMessages(activeConversationId);
            loadConversations();
        } catch (e) {
            console.error('Error manual message:', e);
        }
    }

    function openVoucherLightbox(url) {
        document.getElementById('lightbox-image-target').src = url;
        document.getElementById('lightbox-download-btn').href = url;
        document.getElementById('voucher-lightbox-modal').classList.remove('hidden');
    }

    function closeVoucherLightbox() {
        document.getElementById('voucher-lightbox-modal').classList.add('hidden');
    }
</script>

<!-- MODAL DE VISUALIZACIÓN DE COMPROBANTE EN TAMAÑO COMPLETO (HD LIGHTBOX) -->
<div id="voucher-lightbox-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-4xl w-full p-6 shadow-2xl flex flex-col items-center space-y-4 relative">
        <div class="w-full flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center space-x-2">
                <span>🧾 Comprobante de Pago en Alta Resolución (HD)</span>
            </h3>
            <button onclick="closeVoucherLightbox()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl font-bold px-2 py-1 transition">
                ✕
            </button>
        </div>
        <div class="w-full overflow-auto max-h-[75vh] flex justify-center bg-slate-950/50 rounded-2xl p-3 border border-slate-800">
            <img id="lightbox-image-target" src="" class="max-w-full h-auto object-contain rounded-xl shadow-lg" alt="Comprobante HD Ampliado">
        </div>
        <div class="w-full flex justify-between items-center pt-2">
            <a id="lightbox-download-btn" href="" target="_blank" download class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl transition shadow-md flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Abrir / Descargar Imagen Original HD</span>
            </a>
            <button onclick="closeVoucherLightbox()" class="px-5 py-2.5 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl hover:bg-slate-300 dark:hover:bg-slate-700 transition">
                Cerrar Visor
            </button>
        </div>
    </div>
</div>
@endsection
