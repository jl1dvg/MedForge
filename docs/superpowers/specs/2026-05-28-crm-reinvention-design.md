# CRM Reinvention — Diseño Final

**Fecha:** 2026-05-28
**Estado:** Aprobado — listo para plan de implementación
**Sesión de brainstorming:** `/brainstorming Rediseño del modelo de datos CRM`

---

## Visión

El CRM deja de ser un módulo de leads aislado y se convierte en el **hub centralizado de inteligencia comercial** de MedForge. Todos los módulos (WhatsApp, Solicitudes, Exámenes Solicitados) alimentan automáticamente un pipeline unificado. El departamento comercial tiene un panel dedicado para ver **dónde se quedan las oportunidades** y actuar sobre ellas. El equipo operativo no tiene carga manual extra — el sistema mapea los estados clínicos automáticamente.

**Problema actual resuelto:** 21,505 oportunidades (una por examen/solicitud) → ~5,994 (una por paciente). TATIANA PINEDA aparece 1 vez, con sus 3 exámenes como actividades en su línea de tiempo.

---

## 1. Modelo de Datos

### Regla de oro del grain

**Una oportunidad = un paciente (crm_contact).**

Todos sus exámenes, solicitudes y conversaciones de WhatsApp se convierten en **actividades** dentro de esa oportunidad. No en filas separadas. Un paciente con cirugía + seguimiento posterior sigue siendo la misma oportunidad — el pipeline refleja el estado actual de la relación comercial.

---

### `crm_contacts` — sin cambios estructurales

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| patient_id | bigint FK nullable | Vinculado cuando tiene expediente clínico |
| name | string | |
| phone | string | |
| cedula | string nullable | Identificador canónico fuerte |
| resolution | enum | `provisional` · `identified` · `linked` |
| source | enum | `whatsapp` · `solicitud` · `examen` · `manual` |
| created_at, updated_at | timestamps | |

---

### `crm_opportunities` — cambios significativos

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| contact_id | bigint FK | → crm_contacts (UNIQUE — 1 por paciente) |
| title | string | Nombre descriptivo del paciente/relación |
| stage | enum | Ver pipeline abajo |
| **phase** | enum | `operational` · `commercial` — fase actual de propiedad |
| source | enum | `whatsapp` · `solicitud` · `examen` · `manual` (primera fuente que creó la opp) |
| source_id | bigint nullable | ID del registro de primera fuente (se conserva del schema actual) |
| source_type | string nullable | Morph de primera fuente (se conserva del schema actual) |
| assigned_to | bigint FK nullable | → users |
| **last_activity_at** | timestamp nullable | Última actividad registrada (auto-updated) |
| **escalation_at** | timestamp nullable | Cuándo debe dispararse la escalación automática |
| lost_reason | string nullable | |
| created_at, updated_at | timestamps | |

> **UNIQUE constraint:** `contact_id` debe ser único — una oportunidad por contacto.

**Cambio de schema requerido (migración nueva):**
```sql
ALTER TABLE crm_opportunities
  ADD COLUMN phase VARCHAR(20) NOT NULL DEFAULT 'operational' AFTER stage,
  ADD COLUMN last_activity_at TIMESTAMP NULL AFTER assigned_to,
  ADD COLUMN escalation_at TIMESTAMP NULL AFTER last_activity_at;

-- Nuevos valores del enum de stage
-- nuevo | contactado | en_evaluacion | propuesta | comprometido | ganado | perdido
-- (reemplaza: nuevo | en_contacto | interesado | propuesta_enviada | ganado | perdido)
```

---

### `crm_activities` — nuevos tipos de fuente

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| opportunity_id | bigint FK | → crm_opportunities |
| type | enum | `nota` · `llamada` · `cambio_etapa` · `email` · **`examen`** · **`solicitud`** · **`whatsapp`** |
| description | text | |
| **source_id** | bigint nullable | ID en la tabla de origen clínico |
| **source_type** | string nullable | `consulta_examenes` · `solicitud_procedimiento` · `whatsapp_lead` |
| user_id | bigint FK nullable | null = Sistema |
| created_at | timestamp | |

Los registros clínicos (exámenes, solicitudes, leads WA) se registran como actividades con `source_id + source_type` para navegación bidireccional.

---

## 2. Pipeline de Etapas

```
[FASE OPERATIVA]                    [FASE COMERCIAL]
🆕 Nuevo → 📞 Contactado → 🔍 En evaluación ⟶⚡⟶ 📋 Propuesta → 🤝 Comprometido → ✅ Ganado
                                                                                    ↘ ❌ Perdido
```

### Definición de etapas

| Etapa | Fase | Significado |
|-------|------|-------------|
| `nuevo` | operational | Registro ingresó — nadie ha contactado aún |
| `contactado` | operational | Ejecutivo hizo contacto y el paciente respondió |
| `en_evaluacion` | operational | Exámenes en curso o cita de evaluación agendada |
| `propuesta` | commercial | Plan de tratamiento + precio presentado |
| `comprometido` | commercial | Paciente confirmó, coordinando fecha/pago |
| `ganado` | commercial | **Procedimiento realizado** |
| `perdido` | commercial | Paciente descartó, no responde, fue a otra clínica |

**"Ganado" = procedimiento realizado.** No es "cirugía agendada" ni "pago recibido" — es la entrega efectiva del servicio.

### Reglas de transición automática

| Trigger | Acción |
|---------|--------|
| Nueva solicitud/examen entra | Oportunidad creada en `nuevo` (o actividad si ya existe) |
| Oportunidad llega a `propuesta` | `phase = commercial`, `assigned_to` limpiado (ahora es del equipo comercial) |
| Escalación por tiempo (ver §3) | `phase = commercial`, notificación al equipo comercial |
| `ganado` registrado | `last_activity_at = now()`, KPI de conversión actualizado |

---

## 3. Motor de Escalación

### Regla

Una oportunidad escala de `operational → commercial` cuando se cumple **cualquiera** de:

1. **Por etapa:** llega a `propuesta` — siempre pasa a comercial inmediatamente
2. **Por tiempo sin actividad:**
   - En `contactado` sin actividad ≥ `crm.escalacion.dias_contactado` días (default: **7**)
   - En `en_evaluacion` sin actividad ≥ `crm.escalacion.dias_en_evaluacion` días (default: **14**)

### Implementación

**Comando artisan:** `crm:escalate`
- Registrado en `Console/Kernel.php` → `$schedule->command('crm:escalate')->dailyAt('08:00')`
- En IONOS: el cron del servidor apunta al scheduler de Laravel (`* * * * * cd /path && php artisan schedule:run`) — **no** un cron directo al comando
- Busca oportunidades donde `phase = operational AND escalation_at <= NOW()`
- Para cada una: `phase = commercial`, registra actividad de tipo `cambio_etapa` con descripción "Escalado automáticamente a Comercial — sin actividad por X días"
- Recalcula `escalation_at` al registrar cualquier actividad nueva en una oportunidad

**Configuración (Settings):**
```php
// config/crm.php
'escalacion' => [
    'dias_contactado'    => env('CRM_ESC_DIAS_CONTACTADO', 7),
    'dias_en_evaluacion' => env('CRM_ESC_DIAS_EN_EVALUACION', 14),
],
```

Estos valores son editables desde el módulo Settings de MedForge (sin tocar código ni `.env`). Se guardan en la tabla `settings` con clave `crm.escalacion.dias_contactado` y `crm.escalacion.dias_en_evaluacion`.

---

## 4. Migración de Consolidación (21K → ~5,994)

### Estrategia

**Comando:** `crm:consolidate-opportunities`

Para cada `crm_contact`:
1. Obtener todas sus `crm_opportunities` ordenadas por `created_at ASC`
2. Tomar la **más antigua** como oportunidad canónica (la que más historial tiene)
3. Mover todas las demás oportunidades a actividades en la canónica
4. Determinar etapa inicial con mapeo automático (ver tabla abajo)
5. Actualizar `crm_opportunity_id` en tablas clínicas para apuntar a la canónica
6. Borrar las oportunidades no-canónicas

### Mapeo automático de etapa inicial

El comando revisa todos los registros clínicos del paciente y asigna la etapa según:

| Condición (evaluada en orden) | Etapa asignada |
|-------------------------------|----------------|
| Alguna `solicitud_procedimiento` con estado `en_proceso` | `en_evaluacion` |
| Alguna `solicitud_procedimiento` con estado `aprobada` | `contactado` |
| Registro clínico más reciente < 30 días | `nuevo` |
| Registro clínico más reciente 30–90 días | `contactado` |
| Todos los registros clínicos > 6 meses | `ganado` (procedimiento histórico ya entregado) |
| Sin registros clínicos identificables | `nuevo` |

### Estimación de impacto

| Métrica | Antes | Después |
|---------|-------|---------|
| crm_opportunities | 21,505 | ~5,994 |
| crm_activities | existentes | existentes + actividades migradas |
| Filas duplicadas visibles | TATIANA ×3 | TATIANA ×1 con 3 actividades |

---

## 5. Arquitectura de Eventos — sin cambios

El listener ya existe y funciona sincrónico. El cambio es que cuando entra un evento para un contacto **que ya tiene oportunidad**, en lugar de crear una nueva oportunidad se crea una actividad en la existente.

```
Evento entrante (WhatsappLeadQualified | SolicitudCreada | ExamenSolicitado)
  → CrmContactResolverService::resolve($payload)
      → Encuentra o crea crm_contact
  → CrmOpportunityService::upsertFromEvent($contact, $event)
      → ¿Ya tiene oportunidad?
          SÍ → crear crm_activity en la existente (type = examen|solicitud|whatsapp)
          NO → crear crm_opportunity (stage = nuevo, phase = operational)
               + crear primera crm_activity
```

**Método nuevo en `CrmOpportunityService`:** `upsertFromEvent()` — reemplaza `createFromEvent()`.

---

## 6. Panel Comercial — UI

### Stack

React + Vite (ya configurado). La app continúa montada en `GET /crm` dentro del layout `medforge.blade.php`.

### Design system: MedForge (no Tailwind genérico)

El panel anterior usaba clases Tailwind genéricas (`bg-slate-100`, `border-slate-200`) que no corresponden al estilo de MedForge. El nuevo panel usa el design system de `medforge-design-system.css`:

**Tokens CSS a usar:**
- Fondo de página: `var(--bg-soft)` (#f3f6f9)
- Superficies/cards: `var(--bg-surface)` (#ffffff)
- Bordes: `var(--border)` (#e4e6ef), suave: `var(--border-soft)`
- Texto: `var(--fg-1)` (#172b4c) principal, `var(--fg-2)` secundario, `var(--fg-mute)` muted
- Primary: `var(--primary)` (#5156be) y `var(--primary-fade)` para fondos
- Ganado/success: `var(--success)` (#05825f) + `var(--success-light)`
- Escalación/warning: `var(--warning)` (#ffa800) + `var(--warning-light)`
- Perdido/danger: `var(--danger)` (#ee3158) + `var(--danger-light)`
- Operativo (info): `var(--info)` (#3596f7) + `var(--info-light)`

**Patrones de componentes:**
- Contenedores: `border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-surface); box-shadow: var(--shadow-xs)`
- Botones: `btn btn-primary` (MedForge/Bootstrap), `btn btn-sm` para acciones de fila
- Badges de etapa: `background: var(--primary-fade); color: var(--primary)` para primary, semánticos por etapa
- Tabla: header con `font-size: 11px; text-transform: uppercase; color: var(--fg-1)`, rows con `border-color: var(--border-soft)`
- Tipografía: `font-family: var(--font-body)` (IBM Plex Sans); valores KPI con `var(--font-display)` (Rubik)
- Actividades clínicas: usar los colores de categoría — `var(--cat-examen-bg)`/`var(--cat-examen-fg)`, `var(--cat-cirugia-bg)`/`var(--cat-cirugia-fg)`

**Inyección de tokens en React:** El React se monta dentro del layout MedForge que ya carga el CSS. Los componentes usan `style={{ color: 'var(--fg-1)' }}` o un archivo `crm-panel.css` separado que referencia las variables — no clases Tailwind hardcodeadas.

### Cambios en la tabla principal

| Antes | Ahora |
|-------|-------|
| TATIANA PINEDA ×3 filas | TATIANA PINEDA ×1 fila |
| Colores Tailwind genéricos (slate/blue) | Tokens MedForge (primary #5156be, etc.) |
| Sin indicador de fase | Badge `Operativo` (info) / `Comercial` (success) |
| Sin historial de actividad | `last_activity_at` visible en `var(--fg-mute)` |
| Sin escalación visible | Fila con fondo `var(--warning-light)` + "Escala en X días" |

### Panel de detalle

- La **línea de tiempo** muestra todas las actividades: exámenes, solicitudes, notas, llamadas, WA, cambios de etapa — cada tipo con su color de categoría MedForge
- Cada actividad clínica tiene link "Ver en módulo" → navega al registro original
- Indicador de fase visible y editable por comercial (con confirmación)
- Selector visual de etapa: 7 chips en fila, activo con `background: var(--primary); color: #fff`

---

## 7. Rutas y controladores

| Ruta | Controlador | Acción |
|------|------------|-------|
| `GET /crm` | `CrmUiController::index` | Panel SPA |
| `GET /crm/opportunities` | `CrmOpportunityController::index` | Lista con filtros (JSON), paginada |
| `GET /crm/opportunities/{id}` | `CrmOpportunityController::show` | Detalle con actividades (JSON) |
| `PATCH /crm/opportunities/{id}` | `CrmOpportunityController::update` | Cambio de etapa / asignación / fase |
| `POST /crm/opportunities` | `CrmOpportunityController::store` | Registro manual |
| `POST /crm/opportunities/{id}/activities` | `CrmActivityController::store` | Nota/llamada |
| `GET /crm/contacts` | `CrmContactController::index` | Lista contactos |
| `PATCH /crm/contacts/{id}` | `CrmContactController::update` | Identificar / cédula |
| `POST /crm/contacts/{id}/merge` | `CrmContactController::merge` | Fusión duplicados |
| `GET /crm/stats` | `CrmStatsController::index` | KPIs |

---

## 8. Servicios

| Servicio | Responsabilidad clave |
|---------|----------------------|
| `CrmContactResolverService` | Deduplicación. Sin cambios — ya funciona. |
| `CrmOpportunityService` | Agregar `upsertFromEvent()`. Lógica de "crear o agregar actividad". Recalcular `escalation_at` al registrar actividad. |
| `CrmActivityService` | Agregar soporte para types clínicos (`examen`, `solicitud`, `whatsapp`). |
| `CrmStatsService` | Agregar KPIs de fase (cuántas en operativo vs comercial, tasa de escalación). |
| `CrmEscalationService` | Nuevo. Lógica de escalación, invocado por el comando `crm:escalate`. |

---

## 9. Comandos artisan

| Comando | Cuándo ejecutar |
|---------|----------------|
| `crm:consolidate-opportunities` | **Una vez** en producción (migración). Tiene `--dry-run`. |
| `crm:escalate` | Diario (cron). Escala oportunidades por tiempo. |
| `crm:backfill-clinical` | Ya existe. Respaldo por si quedan registros sin `crm_opportunity_id`. |

---

## 10. Impacto en planes existentes

### Planes Onda 5-B y 5-C — **REEMPLAZADOS**

Las ondas 5-B y 5-C ya están **parcialmente implementadas**. Esta reinvención requiere:

1. **Nueva migración** (`ALTER TABLE crm_opportunities` + nuevas columnas `phase`, `last_activity_at`, `escalation_at`)
2. **Cambio de enum** de etapas en la tabla (requiere `ALTER TABLE` con los nuevos valores)
3. **`upsertFromEvent()`** en `CrmOpportunityService` — el listener necesita este método
4. **UNIQUE constraint en `contact_id`** — consolidación primero, luego `ALTER TABLE crm_opportunities ADD UNIQUE(contact_id)` en una migración separada
5. **React UI** — cambios visuales de fase + indicadores de escalación
6. **`crm:escalate` command** — nuevo
7. **`crm:consolidate-opportunities` command** — nuevo, ejecutar en producción tras migración

### Orden de ejecución

1. **Migración DB**: columnas nuevas + enum nuevo
2. **Backend**: `upsertFromEvent()`, `CrmEscalationService`, comandos
3. **Frontend**: UI de fase + indicadores
4. **Datos**: ejecutar `crm:consolidate-opportunities --dry-run` → revisar → sin dry-run
5. **Cron**: activar `crm:escalate` en el scheduler de Laravel
6. **Settings**: agregar sección de escalación en UI de Settings
