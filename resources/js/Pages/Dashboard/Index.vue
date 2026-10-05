<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 p-8">
    <header class="mb-8 flex justify-between items-center border-b border-slate-800 pb-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent">
          AIVA Operational Dashboard
        </h1>
        <p class="text-slate-400 text-sm mt-1">Supervisión en tiempo real e intervención humana</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          Core Engine v20.0 Ready
        </span>
      </div>
    </header>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-xl p-5">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Ventas Totales</span>
        <div class="text-2xl font-bold text-white mt-2">S/ {{ (metrics?.orders?.revenue || 3.50).toFixed(2) }}</div>
        <div class="text-xs text-slate-500 mt-1">{{ metrics?.orders?.total || 1 }} Órdenes procesadas</div>
      </div>

      <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-xl p-5">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Conversaciones Activas</span>
        <div class="text-2xl font-bold text-white mt-2">{{ metrics?.conversations?.total || 1 }}</div>
        <div class="text-xs text-slate-500 mt-1">Canal WhatsApp</div>
      </div>

      <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-xl p-5">
        <span class="text-xs font-medium text-cyan-400 uppercase tracking-wider">Atendidas por IA</span>
        <div class="text-2xl font-bold text-cyan-300 mt-2">{{ metrics?.conversations?.ai_active || 1 }}</div>
        <div class="text-xs text-slate-500 mt-1">AIVA Guardian Activo</div>
      </div>

      <div class="bg-slate-900/60 backdrop-blur-md border border-slate-800 rounded-xl p-5">
        <span class="text-xs font-medium text-amber-400 uppercase tracking-wider">Handover Humano</span>
        <div class="text-2xl font-bold text-amber-300 mt-2">{{ metrics?.conversations?.human_handover || 0 }}</div>
        <div class="text-xs text-slate-500 mt-1">Requieren atención de operador</div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  metrics: Object
})
</script>
