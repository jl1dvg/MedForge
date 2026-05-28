# CRM Reinvention — Diseño Aprobado

**Fecha:** 2026-05-28  
**Estado:** Aprobado — listo para plan de implementación  
**Reemplaza:** planes Onda 5-B y Onda 5-C (ver sección "Impacto en ondas existentes")

---

## Visión

El CRM deja de ser un módulo aislado de leads y se convierte en el **hub centralizado de inteligencia comercial** de MedForge. WhatsApp, Solicitudes y Examenes Solicitados alimentan automáticamente un pipeline unificado de oportunidades. El departamento comercial tiene un panel dedicado para saber qué oportunidades requieren atención y dónde se están perdiendo pacientes.

---

## 1. Modelo de Datos

### Entidad: Oportunidad = Contacto + Oportunidad por servicio

Una oportunidad es el intento de cerrar un servicio específico con una persona. La misma persona puede tener múltiples oportunidades abiertas al mismo tiempo (ej: una cirugía, un examen, una consulta). Esto aplica tanto a pacientes nuevos (primera atención, generados por WhatsApp) como a pacientes existentes que generan nuevos servicios.

### Tablas nuevas

#### `crm_contacts`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| patient_id | bigint FK nullable | Vinculado cuando se convierte o ya existe |
| name | string | |
| phone | string | Siempre disponible (WA) |
| email | string nullable | |
| cedula | string nullable | Identificador canónico fuerte |
| resolution | enum | `provisional` · `identified` · `linked` |
| source | enum | `whatsapp` · `solicitud` · `examen` · `manual` |
| created_at, updated_at | timestamps | |

#### `crm_opportunities`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| contact_id | bigint FK | → crm_contacts |
| title | string | Descripción breve del servicio |
| stage | enum | Ver pipeline abajo |
| source | enum | `whatsapp` · `solicitud` · `examen` · `manual` |
| source_id | bigint nullable | ID del registro origen |
| source_type | string nullable | Morph: App\Modules\Solicitudes\... etc. |
| assigned_to | bigint FK nullable | → users |
| lost_reason | string nullable | Motivo si stage = perdido |
| created_at, updated_at | timestamps | |

#### `crm_activities`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| opportunity_id | bigint FK | → crm_opportunities |
| type | enum | `nota` · `llamada` · `cambio_etapa` · `email` |
| description | text | |
| user_id | bigint FK nullable | null = Sistema |
| created_at | timestamp | |

### Enums

**stage:** `nuevo` · `en_contacto` · `interesado` · `propuesta_enviada` · `ganado` · `perdido`

**resolution:** `provisional` (solo teléfono) · `identified` (tiene cédula, sin expediente) · `linked` (cédula + patient_id vinculado)

---

## 2. Pipeline de Etapas

6 etapas diseñadas para equipo comercial con acceso digital limitado:

```
🆕 Nuevo → 📞 En contacto → 💬 Interesado → 📋 Propuesta enviada → ✅ Ganado
                                                                   ↘ ❌ Perdido
```

### Entrada automática por fuente

| Fuente | Etapa de entrada | Trigger |
|--------|-----------------|---------|
| WhatsApp | `nuevo` | Lead calificado en conversación |
| Solicitudes | `interesado` | Nueva solicitud de servicio creada |
| Examenes Solicitados | `propuesta_enviada` | Examen ordenado sin confirmar pago |
| Manual | Elige el comercial | Registro manual en el panel |

---

## 3. Arquitectura de Eventos (Laravel Event/Listener)

Los módulos NO dependen del CRM. Disparan eventos que ya deberían disparar por su propia lógica. El CRM escucha y reacciona.

### Eventos

| Evento | Clase | Módulo origen | Payload |
|--------|-------|--------------|---------|
| WhatsApp lead calificado | `WhatsappLeadQualified` | Whatsapp | `$lead`, `$conversation` |
| Solicitud creada | `SolicitudCreada` | Solicitudes | `$solicitud`, `$paciente` |
| Examen solicitado | `ExamenSolicitado` | Examenes | `$examen`, `$paciente` |

### Listener central

**`CrmOpportunityListener`** — escucha los 3 eventos, delega a `CrmContactResolverService` y luego crea la oportunidad.

```
Evento entrante
  → CrmContactResolverService::resolve($payload)
      → ¿Tiene cédula? → firstOrCreate por cédula  (strong match)
      → ¿Solo teléfono? → firstOrCreate por phone   (provisional)
      → ¿Cédula nueva pero teléfono ya existe? → merge propuesto
  → CrmOpportunityService::createFromEvent($contact, $event, $stage)
      → Crea crm_opportunity con source_id + source_type
      → Registra crm_activity tipo 'cambio_etapa' (Sistema)
```

### Trazabilidad bidireccional

- Desde CRM → `source_id + source_type` lleva al registro original (Solicitud, Examen, WA Lead)
- Desde Solicitud/Examen → `crm_opportunity_id` (campo agregado en tabla origen) lleva al CRM

---

## 4. Resolución de Contacto (deduplicación)

El punto más frágil del sistema. Dos niveles de confianza:

### Nivel 1 — Identificación fuerte (cédula disponible)
Aplica cuando: Solicitudes, Examenes, o WhatsApp con cédula compartida.
```php
$contact = CrmContact::firstOrCreate(['cedula' => $cedula], [...]);
// resolution: identified o linked si ya tiene patient_id
```

### Nivel 2 — Identificación provisional (solo teléfono)
Aplica cuando: WhatsApp sin cédula compartida. La oportunidad **sí se crea** — no se bloquea.
```php
$contact = CrmContact::firstOrCreate(['phone' => $waNumber, 'cedula' => null], [
    'resolution' => 'provisional', ...
]);
```

### Flujo de resolución posterior
1. Comercial ingresa cédula en el panel de detalle
2. Sistema busca: ¿existe `crm_contact` con esa cédula?
   - **No existe** → actualiza el contacto provisional, `resolution = identified`
   - **Sí existe** → propone fusión: el comercial confirma con un clic, oportunidades se consolidan bajo un solo contacto
3. Al vincular con `patient_id` → `resolution = linked`

---

## 5. Panel Comercial — UI

### Stack frontend: React + Vite (mini-SPA embebida)
Laravel sirve `GET /crm` → Blade shell → monta la app React. Toda la interactividad (filtros, panel de detalle, timeline en tiempo real vía Pusher) vive en React. Los datos llegan por las rutas JSON del punto 7. Tailwind CSS 4 para estilos (ya configurado).

### Vista principal: Lista con filtros

- **Barra de stats** con 5 KPIs del día: sin contactar, activas total, ganadas este mes, tiempo de respuesta promedio, tasa de conversión
- **Filtros rápidos** como chips: Todas · Urgentes · Nuevas · Propuesta · Por fuente (WA / Solicitudes / Exámenes)
- **Tabla de oportunidades** con columnas: Paciente/Contacto · Etapa · Origen · Asignado a · Tiempo · Acción
- **Filas urgentes** (sin contactar > umbral configurable) resaltadas en amarillo con ícono de alerta
- **Badge de resolución** por contacto: provisional / identificado / vinculado
- **Botón de acción contextual** por fila (cambia según etapa): Contactar · Avanzar · Seguimiento · Ver detalle

### Panel de detalle (50% del cuerpo, estilo PerfexCRM/Notion)

Se abre al hacer clic en cualquier fila. Dos columnas internas:

**Columna izquierda — datos y acciones:**
- Card del contacto con avatar, nombre, teléfono, cédula, email, badge de resolución
- Vínculo al origen (link directo a Solicitud/Examen/WA Lead)
- Selector visual de etapa (6 chips clicables)
- Asignación con opción de cambiar
- Botones de acción rápida: Llamar / Enviar email / Marcar como perdido

**Columna derecha — actividad:**
- Campo de texto para registrar nota/actividad (con botón Guardar)
- Timeline vertical con tarjetas por evento: cambios de etapa, notas del comercial, eventos del sistema

### Diseño UX para usuario digitalmente limitado
- Un solo CTA por fila (el más relevante según etapa)
- Etiquetas con íconos en todo — sin íconos solos
- Filas urgentes visualmente distintas sin necesidad de leer el texto
- Cambiar etapa = un clic en el chip, sin dropdown ni confirmación extra

---

## 6. Servicios Laravel a crear

| Servicio | Responsabilidad |
|---------|----------------|
| `CrmContactResolverService` | Deduplicación, resolución provisional/identificado/linked, propuesta de merge |
| `CrmOpportunityService` | CRUD de oportunidades, cambios de etapa, registro de actividad |
| `CrmActivityService` | Registro de notas, llamadas, eventos del sistema |
| `CrmStatsService` | Cálculo de KPIs del panel (urgentes, conversión, tiempo respuesta) |

---

## 7. Rutas y controladores

| Ruta | Controlador | Acción |
|------|------------|-------|
| `GET /crm` | `CrmUiController::index` | Panel comercial (SPA parcial) |
| `GET /crm/opportunities` | `CrmOpportunityController::index` | Lista con filtros (JSON) |
| `GET /crm/opportunities/{id}` | `CrmOpportunityController::show` | Detalle (JSON) |
| `PATCH /crm/opportunities/{id}` | `CrmOpportunityController::update` | Cambio de etapa / asignación |
| `POST /crm/opportunities` | `CrmOpportunityController::store` | Registro manual |
| `POST /crm/opportunities/{id}/activities` | `CrmActivityController::store` | Agregar nota/llamada |
| `GET /crm/contacts` | `CrmContactController::index` | Lista de contactos |
| `PATCH /crm/contacts/{id}` | `CrmContactController::update` | Identificar / vincular cédula |
| `POST /crm/contacts/{id}/merge` | `CrmContactController::merge` | Fusión de duplicados |
| `GET /crm/stats` | `CrmStatsController::index` | KPIs del panel |

---

## 8. Impacto en planes Onda 5-B y Onda 5-C

### Onda 5-B (CRM Leads) — **REEMPLAZADA**
El plan original portaba 8 rutas de leads legacy. Con esta reinvención:
- El concepto "lead" desaparece — se reemplaza por `crm_contacts + crm_opportunities`
- Las rutas `/crm/leads/{id}`, `/crm/leads/{id}/profile`, etc. **no se portan** — se reemplazan por las rutas nuevas del punto 7
- La ruta `POST /crm/leads/convert` se convierte en `PATCH /crm/contacts/{id}` (vincular a patient_id)
- La `CrmUiController` sí se crea (como se planificaba), pero sirve el nuevo panel

### Onda 5-C (CRM Entities + Delete) — **PARCIALMENTE VIGENTE**
- **Projects, Tasks, Tickets, Proposals:** se portan igual, no cambian con esta reinvención
- **Agregar `/crm` al bridge:** vigente, pero las rutas son las nuevas del punto 7
- **Eliminar `modules/CRM/`:** vigente — el legacy se elimina al terminar

### Orden de ejecución recomendado
1. **Onda 5-CRM-Core** (nueva): migraciones + modelos + eventos + listeners + servicios
2. **Onda 5-CRM-UI** (nueva): panel comercial, controladores, rutas, Blade/Livewire
3. **Onda 5-C reducida**: Projects, Tasks, Tickets, Proposals + bridge + delete legacy

---

## 9. Decisiones técnicas pendientes para el plan

- [x] **Frontend: React + Vite** — el proyecto ya tiene Vite configurado y Pusher.js instalado. El panel CRM es una mini-SPA React montada en una ruta Laravel (`GET /crm`). El resto del app sigue en Blade — no hay conflicto.
- [x] **Queued listeners con fallback síncrono** — `CrmOpportunityListener implements ShouldQueue`. No bloquea al usuario al guardar Solicitudes/Examenes, reintentos automáticos si falla. Si no hay worker activo (dev local sin Redis), Laravel ejecuta síncrono automáticamente como fallback.
- [x] **Migrar leads existentes** — los leads del CRM legacy se migran a `crm_contacts + crm_opportunities` como parte del plan de implementación. Un comando artisan `crm:migrate-legacy-leads` manejará la migración con mapeo de fuentes.
- [x] **Thresholds de urgencia configurables desde Settings** — defaults: 6h WhatsApp, 48h Solicitudes y Exámenes. Configurables en el módulo Settings para que el equipo comercial los ajuste sin tocar código. Clave sugerida: `crm.urgency_threshold_hours.whatsapp`, `crm.urgency_threshold_hours.solicitud`, `crm.urgency_threshold_hours.examen`.
- [x] **Trigger de `ExamenSolicitado`** — se dispara cuando el sistema detecta un examen ordenado que **aún no tiene pago ni confirmación operativa asociada**. No cuando el médico lo ordena (eso puede ser sin intención de pago inmediato), sino cuando el examen queda en estado pendiente de confirmación operativa.
