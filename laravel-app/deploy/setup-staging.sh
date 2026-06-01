#!/usr/bin/env bash
# =============================================================================
# setup-staging.sh — Preparar el servidor de STAGING + SCRAPER
# Ejecutar como root o con sudo desde el directorio raíz del proyecto.
# =============================================================================
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/medforge}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
PYTHON_BIN="${PYTHON_BIN:-/usr/bin/python3}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
WEB_USER="${WEB_USER:-www-data}"

echo "=== MedForge — Setup STAGING + SCRAPER ==="
echo "Directorio: $PROJECT_DIR"
echo ""

# 1. Verificar que el .env tiene SERVER_ROLE=scraper
if [ -f "$PROJECT_DIR/.env" ]; then
    ROLE=$(grep -oP '(?<=^SERVER_ROLE=)\S+' "$PROJECT_DIR/.env" || true)
    if [ "$ROLE" != "scraper" ]; then
        echo "[AVISO] SERVER_ROLE en .env es '$ROLE', debería ser 'scraper'."
        echo "        Edita $PROJECT_DIR/.env y vuelve a ejecutar."
        exit 1
    fi
else
    echo "[ERROR] No existe $PROJECT_DIR/.env"
    echo "        Copia laravel-app/deploy/envs/.env.staging a $PROJECT_DIR/.env y completa los valores."
    exit 1
fi

if [ -f "$PROJECT_DIR/laravel-app/.env" ]; then
    LARAVEL_ROLE=$(grep -oP '(?<=^SERVER_ROLE=)\S+' "$PROJECT_DIR/laravel-app/.env" || true)
    if [ "$LARAVEL_ROLE" != "scraper" ]; then
        echo "[AVISO] SERVER_ROLE en laravel-app/.env es '$LARAVEL_ROLE', debería ser 'scraper'."
        exit 1
    fi
else
    echo "[ERROR] No existe $PROJECT_DIR/laravel-app/.env"
    echo "        Copia laravel-app/deploy/envs/.env.staging a $PROJECT_DIR/laravel-app/.env y completa los valores."
    exit 1
fi

echo "[OK] SERVER_ROLE=scraper confirmado en ambos .env"

# 2. Dependencias Python (scrapers)
echo ""
echo "=== Instalando dependencias Python ==="
$PYTHON_BIN -m pip install -q -r "$PROJECT_DIR/scrapping/requirements.txt"
echo "[OK] Dependencias Python instaladas"

# 3. Dependencias PHP (Laravel — incluye dev para staging)
echo ""
echo "=== Instalando dependencias Composer ==="
cd "$PROJECT_DIR/laravel-app"
$COMPOSER install --no-interaction

# 4. Optimizar Laravel (sin cachear config para facilitar cambios en staging)
echo ""
echo "=== Configurando Laravel ==="
$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear

# 5. Permisos
echo ""
echo "=== Ajustando permisos ==="
chown -R "$WEB_USER":"$WEB_USER" "$PROJECT_DIR/storage" "$PROJECT_DIR/laravel-app/storage" "$PROJECT_DIR/laravel-app/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/laravel-app/storage"

# 6. Nginx
echo ""
echo "=== Configurando nginx ==="
cp "$PROJECT_DIR/laravel-app/deploy/nginx.staging.conf" /etc/nginx/sites-available/medforge-staging
ln -sf /etc/nginx/sites-available/medforge-staging /etc/nginx/sites-enabled/medforge-staging
nginx -t && systemctl reload nginx
echo "[OK] nginx recargado"

# 7. Instalar crontab con mayor frecuencia de scraping
echo ""
echo "=== Instalando crontab de staging/scraper ==="
crontab -u "$WEB_USER" "$PROJECT_DIR/laravel-app/deploy/crontab.staging"
echo "[OK] crontab instalado para $WEB_USER"

echo ""
echo "=== Setup de STAGING + SCRAPER completo ==="
echo "Scrapers → corren en ESTE servidor (CIVE, IESS, IA)"
echo "Lógica de negocio → corre en el servidor PRODUCCIÓN"
echo ""
echo "RECUERDA: DB_HOST en ambos .env debe apuntar al servidor de DB dedicado,"
echo "no a 127.0.0.1."
