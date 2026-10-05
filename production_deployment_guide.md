# Guía Maestra de Despliegue a Producción - AIVA SaaS Platform (v20.0)

Esta guía detalla el procedimiento paso a paso para desplegar **AIVA SaaS Platform** en producción en un servidor VPS/Nube (AWS EC2, DigitalOcean, Hetzner o GCP) bajo Ubuntu 22.04 LTS.

---

## 1. Requisitos de Infraestructura Recomendados

- **Servidor VPS**: Mínimo 2 vCPU / 4 GB RAM (Recomendado: 4 vCPU / 8 GB RAM).
- **Sistema Operativo**: Ubuntu 22.04 LTS.
- **Motor Web**: Nginx 1.24+.
- **Lenguaje**: PHP 8.2+ con extensiones (`pdo_mysql`, `redis`, `bcmath`, `curl`, `mbstring`, `openssl`, `gd`, `zip`).
- **Base de Datos**: MySQL 8.0+ / MariaDB 10.11+.
- **Caché en Memoria**: Redis 7.0+ (Crucial para el Business Brain & Customer Brain a 0ms).
- **Gestor de Procesos**: Supervisor (Para Queue Workers de campanas y automatizaciones).
- **Seguridad SSL**: Certbot (Let's Encrypt para HTTPS obligatorio por Meta Webhooks).

---

## 2. Paso 1: Configuración de Variables de Entorno (`.env`)

Crea o actualiza el archivo `.env` en la raíz del proyecto en el servidor de producción:

```ini
APP_NAME=AIVA
APP_ENV=production
APP_KEY=base64:GENERA_TUS_PROPIA_LLAVE_CON_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://app.aiva.pe

LOG_CHANNEL=daily
LOG_LEVEL=error

# BASE DE DATOS
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aiva_production
DB_USERNAME=aiva_user
DB_PASSWORD=TU_PASSWORD_SEGURO_AQUI

# REDIS CACHÉ & BRAIN (0ms)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=TU_REDIS_PASSWORD
REDIS_PORT=6379

# QUEUES & COLAS ASÍNCRONAS
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_DRIVER=redis

# AI GATEWAY MULTI-PROVEEDOR (Sprint 4)
OPENAI_API_KEY=sk-proj-TU_LLAVE_OPENAI
GEMINI_API_KEY=AIzaSy_TU_LLAVE_GEMINI
DEFAULT_AI_PROVIDER=openai

# WHATSAPP META CLOUD API (Sprint 5)
WHATSAPP_TOKEN=EAAG_TU_META_TOKEN
WHATSAPP_PHONE_NUMBER_ID=1092837465
WHATSAPP_WEBHOOK_VERIFY_TOKEN=TU_VERIFY_TOKEN_SECRETO

# FACTURACIÓN ELECTRÓNICA SUNAT (Sprint 15)
SUNAT_RUC=20601234567
SUNAT_SOL_USER=MODDATOS
SUNAT_SOL_PASS=moddatos
SUNAT_MODE=beta # Cambiar a 'production' al pasar auditoría PSE
```

---

## 3. Paso 2: Instalación de Dependencias & Optimización

Ejecuta los siguientes comandos en el directorio del proyecto en producción:

```bash
# 1. Instalar dependencias optimizadas de Composer
composer install --no-dev --optimize-autoloader

# 2. Generar llave de aplicación (si no está creada)
php artisan key:generate

# 3. Ejecutar migraciones en modo producción
php artisan migrate --force

# 4. Cargar seeders iniciales de roles, permisos e integraciones
php artisan db:seed --class=ActivateSunatSeeder --force

# 5. Optimización y caché de Laravel para rendimiento máximo
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 4. Paso 3: Configuración de Nginx & SSL Certbot

Crea el archivo de configuración `/etc/nginx/sites-available/aiva.conf`:

```nginx
server {
    listen 80;
    server_name app.aiva.pe;
    root /var/www/AIVA/public;

    index index.php index.html;

    charset utf-8;

    # Endpoint SSE en tiempo real (Sprint 18) - Sin búfer de proxy
    location /api/v1/realtime/stream {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Connection '';
        proxy_set_header Host $host;
        proxy_buffering off;
        proxy_cache off;
        chunked_transfer_encoding off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Activa el sitio y genera el certificado HTTPS SSL con Certbot:

```bash
sudo ln -s /etc/nginx/sites-available/aiva.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Obtener Certificado SSL Gratuito Let's Encrypt
sudo certbot --nginx -d app.aiva.pe
```

---

## 5. Paso 4: Gestor de Procesos en Segundo Plano (Supervisor)

Crea el archivo `/etc/supervisor/conf.d/aiva-worker.conf` para procesar las colas asíncronas de campañas masivas (Sprint 12), webhooks y automatizaciones (Sprint 11):

```ini
[program:aiva-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/AIVA/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/AIVA/storage/logs/worker.log
stopwaitsecs=3600
```

Reinicia y arranca los trabajadores en segundo plano:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aiva-worker:*
```

---

## 6. Paso 5: Tareas Programadas Crónicas (Cron Job Matutino)

Configura el cron job del sistema operativo para ejecutar el programador de tareas de Laravel cada minuto (`crontab -e`):

```cron
* * * * * cd /var/www/AIVA && php artisan schedule:run >> /dev/null 2>&1
```

> **Hito**: Esto ejecutará automáticamente el envío matutino del **AIVA Business Advisor (Sprint 20)** a las 8:00 AM para todos los empresarios registrados.

---

## 7. Paso 6: Verificación de Meta Webhook (WhatsApp)

1. En la consola de Facebook Developers / Meta Cloud API, configura el URL del Webhook:
   `https://app.aiva.pe/api/v1/whatsapp/webhook`
2. Ingresa el `WHATSAPP_WEBHOOK_VERIFY_TOKEN` configurado en el `.env`.
3. Suscríbete a los eventos: `messages`, `messaging_postbacks`, `message_deliveries`.

¡AIVA SaaS Platform v20.0 se encuentra oficialmente lista y operando en Producción!
