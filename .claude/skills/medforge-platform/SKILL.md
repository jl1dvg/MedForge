---
name: medforge-platform
description: >
  Contexto completo de MedForge como plataforma. Usar SIEMPRE que se trabaje en
  cualquier módulo de MedForge: facturación, cirugía, consulta, paciente, WhatsApp,
  bot, CRM, flowmaker, insumos, honorarios, billing, reporting, agendamiento,
  flujo de pacientes, scrapers, crons, extensión Chrome, NAS o SigCenter.
---

# MedForge — Plataforma

## Qué es

MedForge es un ecosistema de aplicaciones clínicas para la clínica oftalmológica **CIVE**, desarrollado y mantenido por **MedForge SAS** (Dr. Jorge Luis de Vera). Centraliza gestión clínica, auditoría, reportería y comunicación en una sola plataforma.

## Stack tecnológico

| Capa | Tecnología actual | Dirección futura |
|------|------------------|-----------------|
| Backend | PHP Laravel | — |
| Frontend | Laravel Blade | React (migración en curso) |
| Base de datos | MySQL | — |
| Paquetes JS | NPM (en adopción progresiva) | Node.js + React |
| Repositorio | Git monorepo | Laravel app + extensión Chrome |

## Arquitectura general

- **Monolítico Laravel** — toda la comunicación entre módulos es interna (no hay microservicios ni APIs internas entre módulos).
- **Un único punto de API externa:** la extensión Chrome de CIVE consume endpoints REST de MedForge para capturar y enviar datos en tiempo real desde SigCenter.
- El repositorio Git de MedForge contiene **tanto el Laravel app como el código de la extensión Chrome** (`cive_extention/`).

## Fuentes de datos

MedForge se alimenta de tres vías:
1. **Ingreso manual** — usuarios ingresan datos directamente en la plataforma.
2. **Scraping** — extracción automatizada de datos externos (Python + PHP).
3. **Extensión Chrome de CIVE** — captura información en tiempo real desde SigCenter y la envía a MedForge vía API.

## Entidad central: Paciente

Todo el ecosistema gira alrededor del **paciente**. Cada paciente tiene:
- **ID interno de MedForge**
- **ID de SigCenter** (ambos coexisten y se vinculan para sincronización)

Flujo general: `Paciente → Cita/Agendamiento → Consulta / Cirugía → Facturación / Billing`

## Módulos activos

| Módulo | Descripción general |
|--------|-------------------|
| **Paciente** | Gestión del expediente central del paciente |
| **Agendamiento** | Programación de citas y agenda médica |
| **Flujo de pacientes** | Seguimiento del estado del paciente en la clínica |
| **Consulta** | Registro y gestión de consultas médicas |
| **Cirugía** | Gestión y auditoría de protocolos quirúrgicos |
| **Imágenes** | Manejo de imágenes clínicas (NAS + SigCenter) |
| **CRM** | Gestión de relación con pacientes |
| **WhatsApp** | Comunicación vía WhatsApp |
| **Bot** | Bot automatizado de WhatsApp |
| **Flowmaker** | Constructor de flujos automatizados (legacy Node+Vite) |
| **Insumos** | Control de insumos clínicos |
| **Honorarios** | Gestión de honorarios médicos |
| **Facturación / Billing** | Facturación y cobros |
| **Reporting PDF** | Generación de reportes en PDF |
| **Dashboards / KPIs** | Reportería y analytics para consultoría |
| **Derivaciones** | Scraping y gestión de derivaciones IESS |
| **CronManager** | Panel de gestión y monitoreo de crons |

## Deploy y entornos

```
feat/mi-feature → PR → main → GitHub Actions → deploy producción
                              └──────────────→ deploy staging (automático en cada PR)
```

- **Nunca pushear directo a `main`** — todo cambio entra por PR.
- PRs a `main` disparan deploy automático a **staging** para probar antes de aprobar.
- PHP CLI en producción: `/usr/bin/php8.3-cli` (NO `php` — es CGI en webspace-data.io).
- Composer: `php composer.phar` local (NO `composer` global).

## Multi-servidor (SERVER_ROLE)

| Servidor | `SERVER_ROLE` | Corre |
|---|---|---|
| Producción | `production` | Lógica de negocio: billing, CRM, recordatorios, KPIs |
| Staging/Scraper | `scraper` | Scraping externo: CIVE, IESS, NAS, Sigcenter, IA |
| Dev local | (sin valor) | Todas las tareas |

- Filtrado en `CronRunner::definitions()` — campo `scraper_only` por tarea.
- También filtrado en `routes/console.php` con bloque `SERVER_ROLE`.
- **Al agregar un cron nuevo:** decidir si es `scraper_only: true` o `false`.

## Scrapers externos

Los scrapers viven en `scrapping/` (Python + PHP):

| Script | Función |
|---|---|
| `scrape_derivacion.py` | Derivaciones del IESS |
| `scrape_index_admisiones.php` | Admisiones SigCenter |

Artisan commands relacionados:
- `derivaciones:scrape` / `derivaciones:scrape-missing`
- `imagenes:nas-index` / `imagenes:nas-diagnose`
- `imagenes:sigcenter-index`
- `index-admisiones:sync`

- Dependencias Python en `scrapping/requirements.txt` (se instalan en deploy).
- Solo corren en el servidor con `SERVER_ROLE=scraper`.

## NAS e imágenes clínicas

- Acceso dual: montaje directo (`NAS_IMAGES_MOUNT`) o SSH/SFTP (`NAS_IMAGES_SSH_*`).
- Indexación de imágenes vía `imagenes:nas-index` y `imagenes:sigcenter-index`.
- Servicios en `app/Modules/Examenes/Services/` (NAS, SigCenter).
- Solo corre en servidor scraper.

## Extensión Chrome — "Asistente CIVE"

- **Directorio:** `cive_extention/` (typo en el nombre — así está en el repo).
- **Versión:** 7.7.3, Manifest V3.
- **Autenticación:** header con `CIVE_EXTENSION_SECRET_KEY` (env var del servidor).
- **Módulos clave:** `paciente.js`, `consulta.js`, `protocolo.js`, `procedimientos.js`, `examenes.js`, `solicitud.js`.
- **Host targets:** `cive.ddns.net`, `asistentecive.consulmed.me`.
- **Endpoints que consume:** `GET /api/cive-extension/config`, `POST /api/cive-extension/health-check`, y endpoints en `routes/api.php`.

> ⚠️ **NUNCA modificar el flujo de la extensión ni su código sin consultar primero al Dr. Jorge Luis.**
> - Cambios en el Laravel app que **no tocan** la API de la extensión: se pueden hacer directamente.
> - Cualquier cambio que **afecte endpoints consumidos por la extensión**, el contrato de la API, o el código fuente de la extensión: **requiere aprobación explícita antes de ejecutar**.

## WhatsApp — flags de migración

Variables `.env` que controlan la transición al nuevo módulo Laravel:
- `WHATSAPP_LARAVEL_ENABLED` — activa el nuevo módulo
- `WHATSAPP_LARAVEL_API_READ_ENABLED` / `WHATSAPP_LARAVEL_API_WRITE_ENABLED` — lectura/escritura por separado

Al tocar el módulo WhatsApp: verificar qué flags están activos en cada entorno antes de hacer cambios.

## Relación con SigCenter

SigCenter es la plataforma **oficial y externa** de CIVE para historias clínicas y facturación. MedForge no controla SigCenter — lo lee y complementa a través de la extensión Chrome y scrapers. No intentar modificar ni reemplazar SigCenter; MedForge convive con él.

## Usuarios y permisos

- Acceden: médicos, enfermeras, administradores, auditores y otros roles del personal de CIVE.
- Los permisos son **granulares por módulo** — cada usuario tiene acceso configurado independientemente para cada módulo.
- Los permisos se asignan manualmente según el rol del usuario.

## Estructura de módulos

30+ módulos bajo `app/Modules/*/`. Cada módulo tiene típicamente:
- `Services/` — lógica de negocio (144+ servicios en total)
- `Http/Controllers/`
- Commands/Jobs/Events para operaciones async

Los Artisan commands están centralizados en `routes/console.php` (1600+ líneas).

## Migración frontend en curso

El Dr. está migrando progresivamente hacia **React + Node.js**. Al proponer soluciones frontend:
- Si es mantenimiento o mejora menor: usar Blade.
- Si es módulo nuevo o componente con interactividad compleja: considerar React y consultarlo.
- No asumir que todo debe migrarse de golpe.

## Convenciones de código

Estándar Laravel: `snake_case` en BD, `PascalCase` en clases, `camelCase` en JS.
