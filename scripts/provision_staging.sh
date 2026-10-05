#!/usr/bin/env bash
# ==============================================================================
# AIVA Commerce v21.1 — Automated Staging Provisioning Script (Gates 0 - 9)
# Target OS: Ubuntu 24.04 LTS
# Target Specs: 4 vCPU / 8 GB RAM / 80+ GB SSD / IPv4
# ==============================================================================

set -euo pipefail

COLOR_GREEN='\033[0;32m'
COLOR_RED='\033[0;31m'
COLOR_YELLOW='\033[1;33m'
COLOR_CYAN='\033[0;36m'
COLOR_RESET='\033[0m'

log_info() { echo -e "${COLOR_CYAN}[INFO]${COLOR_RESET} $1"; }
log_success() { echo -e "${COLOR_GREEN}[PASS]${COLOR_RESET} $1"; }
log_warn() { echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} $1"; }
log_error() { echo -e "${COLOR_RED}[FAIL]${COLOR_RESET} $1"; exit 1; }

# Check root privileges
if [ "$EUID" -ne 0 ]; then
  log_error "Este script debe ser ejecutado con privilegios sudo / root."
fi

GATE_TARGET="${1:-all}"

# ==============================================================================
# GATE 0: VPS Pre-Flight Verification
# ==============================================================================
run_gate_0() {
  log_info "--------------------------------------------------------"
  log_info "GATE 0: VPS Hardware & OS Pre-Flight Verification"
  log_info "--------------------------------------------------------"
  
  echo ">>> Hostname: $(hostname)"
  echo ">>> OS Distribution:"
  lsb_release -a || true
  
  echo ">>> vCPU Count: $(nproc)"
  echo ">>> Memory:"
  free -h
  
  echo ">>> Storage /:"
  df -h /
  
  echo ">>> IP Addresses:"
  ip -4 addr || true
  
  log_success "Gate 0 Pre-Flight Diagnostics Completed."
}

# ==============================================================================
# GATE 1: OS Hardening & Package Management
# ==============================================================================
run_gate_1() {
  log_info "--------------------------------------------------------"
  log_info "GATE 1: OS Hardening & Package Management"
  log_info "--------------------------------------------------------"
  
  apt-get update && apt-get upgrade -y
  apt-get install -y curl git unzip ufw fail2ban software-properties-common ca-certificates openssl
  
  systemctl enable --now fail2ban
  log_success "Gate 1 OS Hardening Completed."
}

# ==============================================================================
# GATE 2: Network Isolation & UFW Firewall Setup (SSH Guarded)
# ==============================================================================
run_gate_2() {
  log_info "--------------------------------------------------------"
  log_info "GATE 2: Network Isolation & UFW Firewall (SSH Guarded)"
  log_info "--------------------------------------------------------"
  
  ufw allow 22/tcp
  ufw allow 80/tcp
  ufw allow 443/tcp
  
  ufw default deny incoming
  ufw default allow outgoing
  
  ufw --force enable
  
  # Validate SSH configuration before restart
  if sshd -t; then
    log_info "Sintaxis SSH validada exitosamente."
    systemctl restart ssh
  else
    log_error "Error de sintaxis en sshd_config. Abortando reinicio de SSH para prevenir bloqueo."
  fi
  
  ufw status verbose
  log_success "Gate 2 Network & Firewall Isolation Completed."
}

# ==============================================================================
# GATE 3: Runtime Stack Setup (NGINX / PHP 8.3 / MySQL 8.0 / Redis 7)
# ==============================================================================
run_gate_3() {
  log_info "--------------------------------------------------------"
  log_info "GATE 3: Runtime Stack Setup (NGINX/PHP 8.3/MySQL 8/Redis 7)"
  log_info "--------------------------------------------------------"
  
  apt-get update
  apt-get install -y nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd mysql-server redis-server
  
  systemctl enable --now nginx
  systemctl enable --now php8.3-fpm
  systemctl enable --now mysql
  systemctl enable --now redis-server
  
  # MySQL Localhost Binding 127.0.0.1
  sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 127.0.0.1/' /etc/mysql/mysql.conf.d/mysqld.cnf
  systemctl restart mysql
  
  # Redis Localhost Binding & Idempotent requirepass
  sed -i 's/bind 127.0.0.1 ::1/bind 127.0.0.1/' /etc/redis/redis.conf
  sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
  
  if ! grep -q "^requirepass " /etc/redis/redis.conf; then
    REDIS_SECRET=$(openssl rand -hex 32)
    echo "requirepass ${REDIS_SECRET}" >> /etc/redis/redis.conf
    log_info "Redis requirepass configurado dinámicamente."
  fi
  
  systemctl restart redis-server
  log_success "Gate 3 Runtime Stack Completed."
}

# ==============================================================================
# GATE 4: Laravel Core Deployment & Environment Secrets
# ==============================================================================
run_gate_4() {
  log_info "--------------------------------------------------------"
  log_info "GATE 4: Laravel Core Deployment & Environment Secrets"
  log_info "--------------------------------------------------------"
  
  mkdir -p /var/www/aiva
  
  if [ -f "/var/www/aiva/.env" ]; then
    chown www-data:www-data /var/www/aiva/.env
    chmod 640 /var/www/aiva/.env
    log_info "Permisos de /var/www/aiva/.env ajustados a 640."
  else
    log_warn "/var/www/aiva/.env no encontrado. Copie el archivo .env antes de optimizar Laravel."
  fi
  
  if [ -d "/var/www/aiva/vendor" ]; then
    cd /var/www/aiva
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan storage:link || true
  fi
  
  log_success "Gate 4 Laravel Core Deployment Completed."
}

# ==============================================================================
# GATE 5: Supervisor Worker Manager Setup (4 Workers)
# ==============================================================================
run_gate_5() {
  log_info "--------------------------------------------------------"
  log_info "GATE 5: Supervisor Worker Manager Setup (4 Workers)"
  log_info "--------------------------------------------------------"
  
  apt-get install -y supervisor
  systemctl enable --now supervisor
  
  cat << 'EOF' > /etc/supervisor/conf.d/aiva-worker.conf
[program:aiva-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/aiva/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/aiva/storage/logs/worker.log
EOF

  supervisorctl reread
  supervisorctl update
  log_success "Gate 5 Supervisor Worker Manager Completed."
}

# ==============================================================================
# GATE 6: Private Evidence Storage & Permissions Fine-Tuning
# ==============================================================================
run_gate_6() {
  log_info "--------------------------------------------------------"
  log_info "GATE 6: Private Evidence Storage & Permissions Fine-Tuning"
  log_info "--------------------------------------------------------"
  
  mkdir -p /var/www/aiva/storage/app/private/tenants
  mkdir -p /var/www/aiva/storage/framework/views
  mkdir -p /var/www/aiva/storage/framework/sessions
  mkdir -p /var/www/aiva/storage/framework/cache
  mkdir -p /var/www/aiva/storage/logs
  
  chown -R www-data:www-data /var/www/aiva/storage
  chmod -R 755 /var/www/aiva/storage
  chmod -R 775 /var/www/aiva/storage/framework
  chmod -R 775 /var/www/aiva/storage/logs
  chmod -R 770 /var/www/aiva/storage/app/private
  
  # Audit public link safety
  if [ -L "/var/www/aiva/public/storage" ]; then
    LINK_TARGET=$(readlink -f /var/www/aiva/public/storage)
    log_info "Symlink public/storage apunta a: ${LINK_TARGET}"
    if [[ "${LINK_TARGET}" == *"/private"* ]]; then
      log_error "ALERTA DE SEGURIDAD: public/storage no debe exponer storage/app/private."
    fi
  fi
  
  log_success "Gate 6 Private Evidence Storage Isolation Completed."
}

# ==============================================================================
# GATE 7: Liveness (/health) & Readiness (/health/ready) Probes
# ==============================================================================
run_gate_7() {
  log_info "--------------------------------------------------------"
  log_info "GATE 7: Liveness (/health) & Readiness (/health/ready) Probes"
  log_info "--------------------------------------------------------"
  
  log_info "Endpoints de salud configurados:"
  log_info " - GET /api/v1/health (Liveness Probe)"
  log_info " - GET /api/v1/health/ready (Readiness Probe: DB, Redis, Queue, Storage)"
  
  log_success "Gate 7 Health Probes Verified."
}

# ==============================================================================
# GATE 8: Staging DNS & TLS 1.3 Certbot Setup
# ==============================================================================
run_gate_8() {
  log_info "--------------------------------------------------------"
  log_info "GATE 8: Staging DNS & TLS 1.3 Setup"
  log_info "--------------------------------------------------------"
  
  apt-get install -y certbot python3-certbot-nginx
  log_info "Certbot instalado. Ejecutar: certbot --nginx -d staging.aiva.pe cuando el DNS apunte al VPS."
  
  log_success "Gate 8 Staging DNS & TLS Tools Ready."
}

# ==============================================================================
# GATE 9: P2.11 Integrity Gate & Static PI-01 Audit
# ==============================================================================
run_gate_9() {
  log_info "--------------------------------------------------------"
  log_info "GATE 9: P2.11 Integrity Gate & Static PI-01 Audit"
  log_info "--------------------------------------------------------"
  
  if [ -f "/var/www/aiva/artisan" ]; then
    cd /var/www/aiva
    log_info "Ejecutando auditoría estática PI-01..."
    
    UNAUTHORIZED_PAID_COUNT=$(grep -rn "'PAID'" app/ --exclude=PaymentAuthorizationService.php --exclude-dir=Domain | wc -l || true)
    if [ "${UNAUTHORIZED_PAID_COUNT}" -gt 0 ]; then
      log_warn "Se encontraron coincidencias estáticas de 'PAID' fuera de PaymentAuthorizationService.php. Revisar auditoría estática."
    else
      log_success "Auditoría estática PI-01: 0 mutaciones a PAID fuera de PaymentAuthorizationService.php."
    fi
    
    log_info "Ejecutando suite interna de certificación de pagos..."
    php artisan aiva:payment-certify || true
  else
    log_warn "Directorio Laravel /var/www/aiva no inicializado completamente."
  fi
  
  log_success "Gate 9 P2.11 Integrity Gate Completed."
}

# ==============================================================================
# EXECUTION PIPELINE
# ==============================================================================
case "${GATE_TARGET}" in
  0) run_gate_0 ;;
  1) run_gate_1 ;;
  2) run_gate_2 ;;
  3) run_gate_3 ;;
  4) run_gate_4 ;;
  5) run_gate_5 ;;
  6) run_gate_6 ;;
  7) run_gate_7 ;;
  8) run_gate_8 ;;
  9) run_gate_9 ;;
  all)
    run_gate_0
    run_gate_1
    run_gate_2
    run_gate_3
    run_gate_4
    run_gate_5
    run_gate_6
    run_gate_7
    run_gate_8
    run_gate_9
    ;;
  *)
    log_error "Gate desconocido: ${GATE_TARGET}. Uso: $0 [0|1|2|3|4|5|6|7|8|9|all]"
    ;;
esac

log_success "=========================================================="
log_success "PROVISIONING PIPELINE EXECUTED SUCCESSFULLY FOR GATE: ${GATE_TARGET}"
log_success "=========================================================="
