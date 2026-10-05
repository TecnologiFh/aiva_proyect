@extends('layouts.app')

@section('title', 'AIVA BOS — Tenant Onboarding Engine')

@section('content')
<div class="space-y-6">

    <!-- HEADER TITLE & WIZARD STEPS -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 text-xs font-extrabold rounded-full border border-purple-200 dark:border-purple-800">
                    AUTOSERVICIO NO-CODE
                </span>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Asistente de Onboarding de Empresa</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Configuración guiada en 10 pasos para dar de alta y dejar lista una nueva empresa para el mercado.</p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-xs font-bold text-slate-400">Paso Activo:</span>
            <span id="stepIndicator" class="px-3 py-1 bg-purple-600 text-white text-xs font-black rounded-lg">Paso 1 de 10</span>
        </div>
    </div>

    <!-- BARRA DE PROGRESO DEL WIZARD -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden">
            <div id="onboardingProgress" class="bg-gradient-to-r from-blue-600 to-purple-600 h-full rounded-full transition-all duration-300" style="width: 10%;"></div>
        </div>
    </div>

    <!-- TARJETA DEL WIZARD INTERACTIVO -->
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800/80 rounded-2xl p-7 shadow-sm max-w-2xl mx-auto space-y-6">
        <div id="wizardStepContent" class="space-y-4">
            <!-- PASO 1 -->
            <div class="step-card space-y-4">
                <div class="flex items-center space-x-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 font-extrabold flex items-center justify-center text-sm">1</span>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Identidad Comercial & Razón Social</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ingresa el nombre comercial de la empresa tal cual se presentará ante los clientes en WhatsApp.</p>
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Nombre Comercial de la Empresa *</label>
                        <input type="text" id="w-name" value="{{ $profile->commercial_name ?? $tenant->name ?? '' }}" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Dirección Física / Sede Principal</label>
                        <input type="text" id="w-address" value="{{ $profile->address ?? '' }}" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white font-medium">
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTROLES NAVEGACIÓN PASO A PASO -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <button id="btnPrevStep" onclick="prevStep()" disabled class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-400 text-xs font-bold rounded-xl disabled:opacity-50 transition">
                ◄ Anterior
            </button>
            <button id="btnNextStep" onclick="nextStep()" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-purple-600/20 transition">
                Siguiente Paso ►
            </button>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    let currentStep = 1;
    const totalSteps = 10;

    const stepTitles = [
        "1. Identidad Comercial",
        "2. Canal de WhatsApp",
        "3. Webhook Endpoint",
        "4. Horarios de Atención",
        "5. Métodos de Pago",
        "6. Tarifas de Delivery",
        "7. Knowledge Hub & FAQs",
        "8. Catálogo & Precios",
        "9. Inventario Inicial",
        "10. AI Guardian & Go-Live"
    ];

    function updateWizardUI() {
        document.getElementById('stepIndicator').innerText = `Paso ${currentStep} de ${totalSteps}`;
        document.getElementById('onboardingProgress').style.width = `${(currentStep / totalSteps) * 100}%`;
        document.getElementById('btnPrevStep').disabled = (currentStep === 1);
        
        if (currentStep === totalSteps) {
            document.getElementById('btnNextStep').innerText = '✓ Finalizar & Ver Business Health';
            document.getElementById('btnNextStep').className = 'px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-emerald-600/20 transition';
        } else {
            document.getElementById('btnNextStep').innerText = 'Siguiente Paso ►';
            document.getElementById('btnNextStep').className = 'px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-purple-600/20 transition';
        }
    }

    function nextStep() {
        if (currentStep < totalSteps) {
            currentStep++;
            updateWizardUI();
        } else {
            window.location.href = '/business-health';
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            currentStep--;
            updateWizardUI();
        }
    }
</script>
@endsection
