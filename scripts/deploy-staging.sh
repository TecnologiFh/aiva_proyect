#!/usr/bin/env bash
# ==============================================================================
# AIVA Commerce v21.1 — Automated Staging & Production Deployment Script
# Target OS: Ubuntu 24.04 LTS | PHP 8.3 FPM | NGINX | Redis 7 | Supervisor | Horizon
# ==============================================================================

set -e

RELEASE_REF="${1:-main}"
APP_DIR="/var/www/aiva"
MAINTENANCE_ENABLED=false

echo "🚀 Starting AIVA Commerce Deployment Pipeline (Ref: ${RELEASE_REF})..."

# 1. Directoria de instalacion
cd "$APP_DIR"

# 2. Trap de seguridad para asegurar salida del modo mantenimiento ante cualquier error
cleanup() {
    if [ "$MAINTENANCE_ENABLED" = true ]; then
        echo "🔓 Disabling Maintenance Mode via deployment safety trap..."
        php artisan up || true
    fi
}
trap cleanup EXIT

# 3. Verificar limpieza del arbol de trabajo Git antes de desplegar
if [ -n "$(git status --porcelain)" ]; then
    echo "❌ ERROR: Working tree is not clean on deployment server. Aborting deployment."
    exit 1
fi

# 4. Entrar en modo mantenimiento temporal
echo "🔒 Enabling Maintenance Mode..."
php artisan down || true
MAINTENANCE_ENABLED=true

# 5. Actualizar codigo fuente de forma reproducible (Soporte para ramas y tags)
echo "📦 Fetching tags and verifying release reference: ${RELEASE_REF}..."
git fetch --tags origin

if git show-ref --verify --quiet "refs/remotes/origin/${RELEASE_REF}"; then
    echo "📌 Checking out remote branch: origin/${RELEASE_REF}"
    git checkout -B deploy "origin/${RELEASE_REF}"
    git reset --hard "origin/${RELEASE_REF}"
elif git show-ref --verify --quiet "refs/tags/${RELEASE_REF}"; then
    echo "📌 Checking out tag release: ${RELEASE_REF}"
    git checkout --detach "refs/tags/${RELEASE_REF}"
else
    echo "❌ ERROR: RELEASE_REF '${RELEASE_REF}' does not exist as a remote branch or tag."
    exit 1
fi

# 6. Instalar dependencias PHP optimizadas
echo "⚡ Installing Composer dependencies (Production mode)..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

# 7. Ejecutar migraciones de base de datos seguras antes de cachear
echo "🗄️ Executing database migrations..."
php artisan migrate --force

# 8. Permisos de directorios de almacenamiento y enlace simbolico
echo "🔑 Setting storage & bootstrap permissions..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
php artisan storage:link --quiet || true

# 9. Optimizar caches de Laravel
echo "🧹 Optimizing Laravel caches (config, route, view, event)..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 10. Reiniciar Laravel Horizon bajo Supervisor
echo "🔄 Terminating Horizon for graceful worker restart..."
php artisan horizon:terminate || true
sudo supervisorctl restart aiva-horizon || true

# 11. Salir del modo mantenimiento (manejado de forma limpia)
echo "🔓 Disabling Maintenance Mode..."
php artisan up
MAINTENANCE_ENABLED=false

echo "✅ AIVA Commerce Deployment (Ref: ${RELEASE_REF}) Completed Successfully! 🚀"
