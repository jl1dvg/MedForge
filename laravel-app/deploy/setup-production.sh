#!/usr/bin/env bash
# =============================================================================
# setup-production.sh — Preparar el servidor de PRODUCCIÓN
# Ejecutar como root o con sudo desde el directorio raíz del proyecto.
# =============================================================================
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/medforge}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.3-cli}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
WEB_USER="${WEB_USER:-www-data}"

echo "=== MedForge — Setup PRODUCCIÓN ==="
echo "Directorio: $PROJECT_DIR"
echo ""

# 1. Verificar que el .env tiene SERVER_ROLE=production
if [ -f "$PROJECT_DIR/.env" ]; then
    ROLE=$(grep -oP '(?<=^SERVER_ROLE=)\S+' "$PROJECT_DIR/.env" || true)
    if [ "$ROLE" != "production" ]; then
        echo "[AVISO] SERVER_ROLE en .env es '$ROLE', debería ser 'production'."
        echo "        Edita $PROJECT_DIR/.env y vuelve a ejecutar."
        exit 1
    fi
else
    echo "[ERROR] No existe $PROJECT_DIR/.env"
    echo "        Copia laravel-app/deploy/envs/.env.production a $PROJECT_DIR/.env y completa los valores."
    exit 1
fi

if [ -f "$PROJECT_DIR/laravel-app/.env" ]; then
    LARAVEL_ROLE=$(grep -oP '(?<=^SERVER_ROLE=)\S+' "$PROJECT_DIR/laravel-app/.env" || true)
    if [ "$LARAVEL_ROLE" != "production" ]; then
        echo "[AVISO] SERVER_ROLE en laravel-app/.env es '$LARAVEL_ROLE', debería ser 'production'."
        exit 1
    fi
else
    echo "[ERROR] No existe $PROJECT_DIR/laravel-app/.env"
    echo "        Copia laravel-app/deploy/envs/.env.production a $PROJECT_DIR/laravel-app/.env y completa los valores."
    exit 1
fi

echo "[OK] SERVER_ROLE=production confirmado en ambos .env"

# 2. Dependencias PHP (Laravel)
echo ""
echo "=== Instalando dependencias Composer ==="
cd "$PROJECT_DIR/laravel-app"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

# 3. Optimizar Laravel para producción
echo ""
echo "=== Optimizando Laravel ==="
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

# 4. Permisos
echo ""
echo "=== Ajustando permisos ==="
chown -R "$WEB_USER":"$WEB_USER" "$PROJECT_DIR/storage" "$PROJECT_DIR/laravel-app/storage" "$PROJECT_DIR/laravel-app/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/laravel-app/storage"

# 5. Nginx
echo ""
echo "=== Configurando nginx ==="
cp "$PROJECT_DIR/laravel-app/deploy/nginx.production.conf" /etc/nginx/sites-available/medforge
ln -sf /etc/nginx/sites-available/medforge /etc/nginx/sites-enabled/medforge
nginx -t && systemctl reload nginx
echo "[OK] nginx recargado"

# 6. Instalar crontab
echo ""
echo "=== Instalando crontab de producción ==="
crontab -u "$WEB_USER" "$PROJECT_DIR/laravel-app/deploy/crontab.production"
echo "[OK] crontab instalado para $WEB_USER"

echo ""
echo "=== Setup de PRODUCCIÓN completo ==="
echo "Scrapers → corren en el servidor STAGING (SERVER_ROLE=scraper)"
echo "Lógica de negocio → corre en ESTE servidor (recordatorios, billing, KPIs)"
