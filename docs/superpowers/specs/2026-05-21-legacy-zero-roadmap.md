# MedForge Legacy-Zero Roadmap

**Fecha:** 2026-05-21  
**Objetivo:** Eliminar todos los módulos bajo `modules/` alcanzando legacy-0  
**Estrategia:** Ondas de complejidad creciente — muertos primero, gigantes al final

---

## Contexto

MedForge es un sistema médico en migración de PHP legacy hacia Laravel 12. Comparten la misma base de datos. El bridge en `public/index.php` controla qué prefijos van a Laravel (`v2_kernel.php`) vs. al router legacy. Cada módulo eliminado reduce la superficie de deuda técnica y el riesgo operacional.

### Estado al 2026-05-21

**Ya eliminados:** Auth, Settings, Codes, Dashboard, Farmacia, Solicitudes, Examenes

**Bridge activo:** `/v2`, `/usuarios`, `/roles`, `/feedback`, `/protocolos`, `/examenes`, `/imagenes`

**Módulos legacy restantes:** 26 módulos, ~100k líneas totales

---

## Principios de migración

1. **Bridge-first:** agregar el prefijo al bridge antes de eliminar rutas activas
2. **Dependencias entrantes primero:** eliminar consumidores antes que proveedores
3. **Verificación antes de rm -rf:** grep de cero referencias cross-module obligatorio
4. **Un plan por onda:** cada onda tiene su propio archivo de plan ejecutable en sesión propia

---

## Clasificación de módulos

| Módulo | Archivos | Líneas | Rutas activas | Deps entrantes | v2 routes | Onda |
|--------|----------|--------|--------------|----------------|-----------|------|
| Usuarios | 14 | 3.4k | **0** | 1 | ✅ bridge | 1 |
| EditorProtocolos | 11 | 1.4k | 5* | 0 | ✅ bridge | 1 |
| Agenda | 6 | 1k | 2 | 0 | ✅ 7 | 2 |
| Derivaciones | 5 | 1.7k | 4* | 1 | ✅ 5 | 2 |
| Reporting (rutas) | — | — | 2+queue | 3 | ✅ 15 | 2 |
| Mail | 6 | 2.8k | 4 | 3 | ❌ | 2 |
| AI | 2 | 197 | 2 | 0 | ❌ | 3 |
| Search | 3 | 709 | 2 | 0 | ❌ | 3 |
| CiveExtension | 6 | 685 | 3 | 0 | ❌ | 3 |
| MailTemplates | 5 | 894 | 4 | 0 | ❌ | 3 |
| Insumos | 8 | 813 | 11 | 0 | ❌ | 3 |
| KPI | 8 | 1.4k | 2 | 0 | ❌ | 3 |
| Doctores | 5 | 3.4k | 2 | 0 | ❌ | 3 |
| IdentityVerification | 11 | 2.9k | 6 | 5 | ❌ | 4 |
| CronManager | 7 | 3k | 4 | 0 | ❌ | 4 |
| Cirugias | 27 | 8k | 11 | 0 | ✅ 13 | 4 |
| Pacientes | 17 | 3.4k | 6 | **14** | ✅ 11 | 4 |
| CRM | 31 | 9.4k | 31 | 1 | ✅ 20 | 5 |
| Billing | 37 | 13k | 27 | 0 | ✅ 52 | 5 |
| WhatsApp | 29 | 15k | 24 | **18** | ✅ 60 | 5 |
| Autoresponder | 11 | 5.3k | 5 | 4 | ❌ | 5 |
| Reporting (engine) | 49 | 20k | — | 3 | ✅ 15 | 6 |
| Shared | 2 | 729 | — | 3 | — | 6 |
| Notifications | 1 | 262 | — | 5 | — | 6 |
| Core | 2 | 85 | — | — | — | 6 |
| Flujo | 2 | 222 | — | 0 | — | 6 |

*\* Rutas bloqueadas por bridge — código muerto en producción*

---

## Ondas de migración

### 🟢 Onda 1 — Código muerto (~1 día)

El bridge ya cubre sus prefijos. Las rutas activas nunca se alcanzan en producción.

**Módulos:** Usuarios, EditorProtocolos  
**Trabajo:** Verificar cobertura Laravel → rm -rf  
**Plan:** `docs/superpowers/plans/2026-05-21-onda1-dead-code.md`  
**Sesión:** Una sola sesión, ejecución directa

### 🟡 Onda 2 — Casi-listos (1–2 semanas)

Tienen v2 routes. Pocas rutas activas pendientes de portar.

**Módulos:** Agenda, Derivaciones, Reporting (rutas), Mail  
**Trabajo:** Portar 2–4 rutas activas por módulo + agregar prefijos al bridge  
**Plan:** `docs/superpowers/plans/2026-05-21-onda2-quick-completions.md`  
**Sesión:** Sesión dedicada, subagent-driven por módulo

### 🟠 Onda 3 — Pequeños independientes (2–4 semanas)

Sin dependencias entrantes, sin v2 routes. Se crean desde cero pero son pequeños.

**Módulos:** AI, Search, CiveExtension, MailTemplates, Insumos, KPI, Doctores  
**Trabajo:** Crear v2 routes + portar endpoints + bridge + delete  
**Plan:** `docs/superpowers/plans/2026-05-21-onda3-small-independents.md`  
**Sesión:** Sesión dedicada, módulos paralelizables

### 🔴 Onda 4 — Medianos con contexto (4–8 semanas)

Mayor volumen de código o dependencias que requieren coordinación.

**Módulos:** IdentityVerification, CronManager, Cirugias, Pacientes  
**Trabajo:** IdentityVerification + CronManager primero (liberan deps entrantes de Pacientes), luego Cirugias, luego Pacientes  
**Plan:** `docs/superpowers/plans/2026-05-21-onda4-medium-modules.md`  
**Sesión:** Sesión dedicada por módulo (4 sesiones), coordinadas secuencialmente

### 🔴🔴 Onda 5 — Gigantes (10–16 semanas)

Los módulos más grandes. Orden de ejecución obligatorio por dependencias.

**Orden:** CRM → Billing (paralelo con CRM) → WhatsApp → Autoresponder  
**Módulos:** CRM, Billing, WhatsApp, Autoresponder  
**Trabajo:** Completar parity, limpiar cross-deps, portar servicios compartidos  
**Plan:** `docs/superpowers/plans/2026-05-21-onda5-giants.md` (dividido por módulo)  
**Sesión:** Una sesión por módulo (4 sesiones), CRM y Billing pueden ser concurrentes

### ⚫ Onda 6 — Infraestructura compartida (2–3 semanas)

Muere sola cuando sus consumidores desaparecen. Limpieza final.

**Módulos:** Reporting (engine), Shared, Notifications, Core, Flujo  
**Trabajo:** Verificar que nada los importa → rm -rf → eliminar autoloader entries del bootstrap  
**Plan:** `docs/superpowers/plans/2026-05-21-onda6-infrastructure.md`  
**Sesión:** Una sesión de limpieza final

---

## Cronograma estimado

| Onda | Esfuerzo | Líneas eliminadas (acum.) | Módulos legacy restantes |
|------|---------|--------------------------|--------------------------|
| 1 | 1 día | ~4.4k | 24 |
| 2 | ~1 semana | ~13k | 20 |
| 3 | 2–3 semanas | ~21k | 13 |
| 4 | 4–6 semanas | ~38k | 9 |
| 5 | 10–14 semanas | ~81k | 5 |
| 6 | 2–3 semanas | **~100k → 0** | **0** |

**Total: 5–7 meses para legacy-0**

---

## Archivos de planes (por crear)

| Onda | Archivo del plan | Estado |
|------|-----------------|--------|
| 1 | `docs/superpowers/plans/2026-05-21-onda1-dead-code.md` | ⬜ por crear |
| 2 | `docs/superpowers/plans/2026-05-21-onda2-quick-completions.md` | ⬜ por crear |
| 3 | `docs/superpowers/plans/2026-05-21-onda3-small-independents.md` | ⬜ por crear |
| 4 | `docs/superpowers/plans/2026-05-21-onda4-medium-modules.md` | ⬜ por crear |
| 5 | `docs/superpowers/plans/2026-05-21-onda5-giants.md` | ⬜ por crear |
| 6 | `docs/superpowers/plans/2026-05-21-onda6-infrastructure.md` | ⬜ por crear |

---

## Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|-----------|
| WhatsApp tiene 18 deps entrantes | Alta | Ondas 1–4 eliminan la mayoría antes de llegar a Onda 5 |
| Pacientes tiene 14 deps entrantes | Alta | Ondas 3–4 eliminan consumidores primero |
| Reporting engine de 20k líneas | Media | Muere naturalmente cuando Billing + Cirugias migran |
| Descubrimientos en módulos pequeños (servicios compartidos ocultos) | Media | Verificación de deps antes de cada rm -rf |
| Tests pre-existentes fallando | Baja | 29 fallos son pre-existentes (WhatsApp/NAS), no bloquean migración |
