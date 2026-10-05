#!/usr/bin/env bash
# ==============================================================================
# AIVA Commerce v21.1 — Production VPS Provisioning Script (Ubuntu 24.04 LTS)
# ==============================================================================
set -euo pipefail

echo "🚀 Iniciando aprovisionamiento del servidor de producción AIVA Commerce..."

# Update system
sudo apt-get update && sudo apt-get upgrade -y

# Install dependencies
sudo apt-get install -y software-properties-common curl git unzip ufw supervisor nginx certbot python3-certbot-nginx redis-server

# Install PHP 8.2 & Extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-redis php8.2-bcmath php8.2-intl

# Enable Redis & Nginx
sudo systemctl enable --now redis-server
sudo systemctl enable --now nginx
sudo systemctl enable --now supervisor

# Configure Firewall (UFW)
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

echo "✅ Servidor Ubuntu 24.04 LTS aprovisionado exitosamente para AIVA Commerce."
