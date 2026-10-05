# ==============================================================================
# AIVA Enterprise On-Premise 1-Click Installer for Windows
# Autores: Equipo de Desarrollo AIVA
# ==============================================================================

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host " 🚀 INICIANDO INSTALADOR AUTOMÁTICO DE AIVA ENTERPRISE + OLLAMA LOCAL" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Verificar e instalar Ollama si no existe
Write-Host "🔍 [1/3] Verificando presencia de Ollama en el sistema..." -ForegroundColor Yellow
if (-not (Get-Command "ollama" -ErrorAction SilentlyContinue)) {
    Write-Host "⏳ Ollama no detectado. Descargando instalador oficial de Ollama..." -ForegroundColor White
    $installerPath = "$env:TEMP\OllamaSetup.exe"
    Invoke-WebRequest -Uri "https://ollama.com/download/OllamaSetup.exe" -OutFile $installerPath
    Write-Host "⚡ Instalando Ollama silenciosamente..." -ForegroundColor White
    Start-Process -FilePath $installerPath -ArgumentList "/silent" -Wait
    Remove-Item $installerPath -Force
    Write-Host "✅ Ollama instalado con éxito." -ForegroundColor Green
} else {
    Write-Host "✅ Ollama ya se encuentra instalado." -ForegroundColor Green
}

# 2. Descargar e Inicializar el Modelo de IA (Llama 3.2)
Write-Host ""
Write-Host "🧠 [2/3] Descargando e inicializando el modelo de IA Llama 3.2..." -ForegroundColor Yellow
Start-Process -FilePath "ollama" -ArgumentList "serve" -WindowStyle Hidden
Start-Sleep -Seconds 3

Write-Host "📥 Descargando modelo llama3.2 (esto puede tomar un par de minutos según tu conexión)..." -ForegroundColor White
ollama pull llama3.2

# 3. Configurar Entorno AIVA (.env)
Write-Host ""
Write-Host "⚙️ [3/3] Configurando entorno de producción AIVA..." -ForegroundColor Yellow
$envPath = ".\.env"
if (Test-Path $envPath) {
    $content = Get-Content $envPath
    if ($content -match "AI_PROVIDER=") {
        (Get-Content $envPath) -replace "AI_PROVIDER=.*", "AI_PROVIDER=OLLAMA" | Set-Content $envPath
    } else {
        Add-Content -Path $envPath -Value "`nAI_PROVIDER=OLLAMA"
    }
    Write-Host "✅ Archivo .env actualizado con AI_PROVIDER=OLLAMA." -ForegroundColor Green
}

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host " 🎉 ¡INSTALACIÓN COMPLETADA EXITOSAMENTE!" -ForegroundColor Green
Write-Host " AIVA ya está lista para atender clientes localmente con IA 100% Privada." -ForegroundColor Green
Write-Host "======================================================================" -ForegroundColor Green
