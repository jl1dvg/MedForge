---
name: run-medforge
description: >
  Levantar, correr, verificar, testear o tomar screenshot de la app MedForge
  (Laravel). Usar cuando se pida iniciar la app, probar un módulo, verificar
  que un cambio funciona, o hacer smoke test de rutas.
---

# Run MedForge (Laravel)

MedForge es una app Laravel + Vite. El agente la levanta con `php artisan serve`
y la verifica con `curl`. No hay `chromium-cli` disponible en este entorno —
las verificaciones son HTTP.

**Todas las rutas son relativas a `laravel-app/`.**

---

## Prerequisites

```bash
# PHP 8.4 está disponible como `php` (no php8.3-cli — eso es para producción)
php --version   # debe mostrar 8.4.x

# Node y npm para los assets
node --version
npm --version
```

No se necesita instalar paquetes del sistema adicionales.

---

## Setup (primera vez o entorno limpio)

```bash
cd laravel-app

# 1. Dependencias PHP
php composer.phar install --no-interaction --no-dev

# 2. Env
cp .env.example .env
php artisan key:generate

# 3. Assets Vite
npm install
npm run build
```

> **Nota DB:** Sin base de datos MySQL disponible, las rutas que requieren DB
> devuelven 500. El login (`/auth/login`) y las páginas de solo-vista funcionan
> sin DB.

---

## Levantar la app

```bash
cd laravel-app
php artisan serve --port=8765 &>/tmp/laravel.log &
sleep 2

# Verificar que arrancó
curl -s -o /dev/null -w "%{http_code}" http://localhost:8765/auth/login
# Debe retornar: 200
```

---

## Smoke test de rutas principales

```bash
for path in "/auth/login" "/kpis" "/insumos" "/doctores" "/cron-manager"; do
  code=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8765$path)
  echo "$code  $path"
done
```

Resultados esperados (sin DB):
- `200` — `/auth/login` (no requiere DB)
- `302` o `500` — rutas protegidas (redirigen a login o fallan si DB no está disponible)

---

## Verificar un módulo específico

```bash
# Ver el HTML de login (la única ruta sin auth/DB en este entorno)
curl -s http://localhost:8765/auth/login | grep '<title>'
# → <title>MedForge - Iniciar sesión</title>

# Verificar que los assets Vite se sirven
curl -s -o /dev/null -w "%{http_code}" \
  http://localhost:8765$(curl -s http://localhost:8765/auth/login | grep -o '/build/assets/app[^"]*\.css' | head -1)
```

---

## Listar todas las rutas disponibles

```bash
cd laravel-app
php artisan route:list 2>&1 | head -50
```

Rutas web clave (sin prefijo `api/`):
- `GET /auth/login` — login
- `GET /kpis` / `GET /kpis/{key}` — dashboards KPI
- `GET /insumos` — control de insumos
- `GET /doctores` — catálogo de doctores
- `GET /cron-manager` — panel de crons
- `GET /protocolos` — protocolos quirúrgicos
- `GET /mail` / `GET /mailbox` — buzón de correo

Rutas de la extensión Chrome (requieren header `Authorization` con `CIVE_EXTENSION_SECRET_KEY`):
- `GET /api/cive-extension/config`
- `POST /api/cive-extension/health-check`

---

## Detener la app

```bash
kill $(lsof -ti:8765) 2>/dev/null || true
```

---

## Ejecutar tests

```bash
cd laravel-app
php artisan test 2>&1 | tail -20
```

---

## Gotchas

- **`php8.3-cli` no existe en este entorno** — usar `php` directamente. En producción/staging se usa `/usr/bin/php8.3-cli` (es CGI allá, no aquí).
- **`composer` global no existe** — usar `php composer.phar` desde `laravel-app/`.
- **Sin DB MySQL:** la mayoría de rutas devuelve 500 o redirige. Solo `/auth/login` y páginas públicas funcionan completamente. Para probar módulos con datos, se necesita la DB real o SQLite en modo test.
- **Los assets Vite deben compilarse** antes de servir — sin `npm run build` las páginas cargan sin estilos.
- **Puerto 8765** — elegido para no colisionar con otros servicios comunes (8000, 8080).
- **`APP_KEY` vacía en `.env.example`** — `php artisan key:generate` es obligatorio o Laravel lanza error 500 en toda petición.
