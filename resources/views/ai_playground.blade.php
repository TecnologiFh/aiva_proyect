@extends('layouts.app')

@section('title', 'AIVA — AI Playground & Provider Gateway')

@section('content')
<div class="space-y-6">

    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">AI Playground & Multi-LLM Gateway</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Soporte Híbrido: Conmutación Automática en Cascada (Failover Groq / Ollama / OpenAI / Claude)</p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="px-3 py-1 bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-full text-xs font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Failover Activo (Groq LPU Primario)
            </span>
        </div>
    </div>

    <!-- AI PROVIDERS CONFIG CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Groq LPU (Speed)</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">PRIMARIO</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-3">llama-3.3-70b</div>
            <p class="text-[11px] text-slate-400 mt-1">Latencia promedio ~0.2s</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ollama Local</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">SECUNDARIO</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-3">llama3:8b</div>
            <p class="text-[11px] text-slate-400 mt-1">Ejecución Local de Respaldo</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">OpenAI API</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">RESPALDO</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-3">gpt-4o-mini</div>
            <p class="text-[11px] text-slate-400 mt-1">Conmutación Cloud 3er Nivel</p>
        </div>

        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Claude Anthropic</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">AVANZADO</span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-3">claude-3-5-sonnet</div>
            <p class="text-[11px] text-slate-400 mt-1">Razonamiento Complejo</p>
        </div>
    </div>

    <!-- AI CHAT TESTER INTERACTION CONTAINER -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Probador de Prompts & Herramientas de IA (AI Guardian Playground)</h2>
        
        <div id="playground-messages" class="h-96 overflow-y-auto bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 space-y-3 border border-slate-200 dark:border-slate-800 text-xs">
            <div class="flex flex-col items-start">
                <div class="max-w-md bg-blue-600 text-white p-3 rounded-2xl">
                    <p class="font-medium">Hola, soy AIVA AI Guardian. Puedes probar cualquier prompt o solicitar información sobre productos, delivery o políticas del negocio.</p>
                </div>
                <span class="text-[10px] text-slate-400 mt-1">AIVA Engine • Groq LPU</span>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <input type="text" id="prompt-input" onkeydown="if(event.key === 'Enter') sendTestPrompt()" placeholder="Escribe tu mensaje para chatear en vivo con la IA (presiona Enter o clic en Enviar)..." class="flex-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button onclick="sendTestPrompt()" id="btnSendPrompt" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition shrink-0">
                Enviar Mensaje
            </button>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="/js/aiva-alerts.js"></script>
<script>
    let conversationHistory = [];

    async function sendTestPrompt() {
        const input = document.getElementById('prompt-input');
        const text = input.value.trim();
        if (!text) return;

        const container = document.getElementById('playground-messages');
        const btn = document.getElementById('btnSendPrompt');

        // Deshabilitar temporalmente mientras procesa
        input.disabled = true;
        btn.disabled = true;
        
        // Render user message
        container.innerHTML += `
            <div class="flex flex-col items-end">
                <div class="max-w-md bg-blue-600 text-white p-3 rounded-2xl font-medium shadow-sm">
                    <p>${text}</p>
                </div>
                <span class="text-[10px] text-slate-400 mt-1">Tú (Cliente)</span>
            </div>
        `;

        // Indicador de pensando
        const loadingId = 'loading-' + Date.now();
        container.innerHTML += `
            <div id="${loadingId}" class="flex flex-col items-start animate-pulse">
                <div class="max-w-md bg-slate-200 dark:bg-slate-800 text-slate-500 p-3 rounded-2xl font-medium text-xs">
                    <span>AIVA está analizando catálogo y respondiendo en vivo... ⏳</span>
                </div>
            </div>
        `;

        input.value = '';
        container.scrollTop = container.scrollHeight;

        try {
            const res = await fetch('/ai-playground/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: text,
                    tenant_id: 4,
                    history: conversationHistory
                })
            });
            const data = await res.json();
            document.getElementById(loadingId)?.remove();

            const aiText = data.response || data.reply_text || 'Respuesta procesada por AIVA Core.';
            const provider = data.provider || 'AIVA Real Engine';
            const latency = data.latency_ms || 210;

            if (data.trigger_sound && window.aivaAlerts) {
                window.aivaAlerts.playChime();
            }

            // Actualizar historial
            conversationHistory.push({ role: 'user', content: text });
            conversationHistory.push({ role: 'assistant', content: aiText });

            container.innerHTML += `
                <div class="flex flex-col items-start">
                    <div class="max-w-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 p-3 rounded-2xl font-medium shadow-sm leading-relaxed">
                        <p>${aiText}</p>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1">🟢 ${provider} (${latency} ms)</span>
                </div>
            `;
            container.scrollTop = container.scrollHeight;
        } catch (e) {
            document.getElementById(loadingId)?.remove();
            container.innerHTML += `
                <div class="flex flex-col items-start">
                    <div class="max-w-md bg-rose-600 text-white p-3 rounded-2xl font-medium">
                        <p>Error de conexión al servidor de IA.</p>
                    </div>
                </div>
            `;
            container.scrollTop = container.scrollHeight;
        } finally {
            input.disabled = false;
            btn.disabled = false;
            input.focus();
        }
    }
</script>
@endsection
