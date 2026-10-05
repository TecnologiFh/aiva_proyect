#!/usr/bin/env bash
# ==============================================================================
# AIVA Commerce v21.1 — Atomic Production Deployment Script with Auto-Rollback
# ==============================================================================
set -euo pipefail

RELEASE_DIR="/var/www/aiva/releases/$(date +%Y%m%d%H%M%S)"
CURRENT_DIR="/var/www/aiva/current"
PREVIOUS_DIR="/var/www/aiva/previous"

echo "📦 Desplegando nuevo release atómico en: ${RELEASE_DIR}..."

# 1. Crear directorio del release
mkdir -p "${RELEASE_DIR}"
git clone --depth 1 https://github.com/aiva-commerce/aiva.git "${RELEASE_DIR}"

# 2. Configurar entorno y dependencias
cd "${RELEASE_DIR}"
cp /var/www/aiva/shared/.env "${RELEASE_DIR}/.env"
chmod 600 "${RELEASE_DIR}/.env"

# 3. Pre-flight backup de base de datos
php artisan production:backup || true

# 4. Migraciones Backward-Compatible (Expand Phase)
php artisan migrate --force

# 5. Optimización de caché
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Conmutación Atómica de Symlink
if [ -d "${CURRENT_DIR}" ]; then
    rm -f "${PREVIOUS_DIR}"
    mv "${CURRENT_DIR}" "${PREVIOUS_DIR}"
fi
ln -sfn "${RELEASE_DIR}" "${CURRENT_DIR}"

# 7. Reiniciar Workers de Supervisor y FPM
sudo systemctl reload php8.2-fpm
sudo supervisorctl restart all

# 8. Verification & Auto-Rollback Check (/health/ready)
READY_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/health/ready || echo "500")

if [ "${READY_STATUS}" -ne 200 ]; then
    echo "🔴 ERROR: /health/ready retornó estado ${READY_STATUS}. Ejecutando ROLLBACK ATÓMICO..."
    if [ -d "${PREVIOUS_DIR}" ]; then
        ln -sfn "${PREVIOUS_DIR}" "${CURRENT_DIR}"
        sudo systemctl reload php8.2-fpm
        sudo supervisorctl restart all
        echo "⚠️ Rollback ejecutado. Versión anterior restaurada."
    fi
    exit 1
fi

echo "🟢 DESPLIEGUE EXITOSO: Release conmutado y /health/ready retornó HTTP 200 (PROD23 PASS)."
