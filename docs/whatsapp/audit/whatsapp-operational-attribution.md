# Auditoria tecnica: atribucion operacional de citas WhatsApp

Fecha de auditoria: 2026-06-24  
Alcance: backend solamente. No incluye frontend, React, Blade, dashboards, CSS ni Flowmaker.

## Pregunta auditada

Por que `bookings_after_operational_intervention = 0` en el baseline operacional.

La auditoria valida si el valor significa:

1. No hubo citas posteriores a una intervencion operacional.
2. El tracking esta incompleto y no permite atribuirlas.

## Resumen ejecutivo

El `0` es correcto bajo la regla estricta actual del baseline: solo cuenta citas de `whatsapp_sigcenter_bookings` enlazadas al mismo `conversation_id` que un handoff con evento operacional previo en `whatsapp_handoff_events`.

Pero el tracking operacional todavia esta incompleto. La base tiene eventos `auto_assigned`, `expired` y `requeued`, pero no registra varios eventos que el baseline intenta consumir: `handoff_requeued`, `reminder_rescue`, `template_rescue`, `supervisor_alerted`. Ademas, algunas citas tienen handoff previo en la misma conversacion, pero esos handoffs no tienen evento operacional registrado antes de la cita. Por eso no se pueden atribuir como "recuperadas" aunque operacionalmente podrian estar cerca del proceso de rescate.

Conclusion: el valor `0` no prueba que la capa operacional no genero impacto. Prueba que, con la instrumentacion actual, no existe evidencia suficiente para atribuir citas a intervenciones operacionales.

## Como se calcula hoy

Archivo principal:

- `app/Modules/Whatsapp/Services/WhatsappOperationalBaselineService.php`

La metrica `bookings_after_operational_intervention` usa:

- `whatsapp_sigcenter_bookings` como fuente de citas.
- `whatsapp_handoffs` como puente por `conversation_id`.
- `whatsapp_handoff_events` como fuente de intervenciones.

Condiciones actuales:

```sql
b.conversation_id = h.conversation_id
e.handoff_id = h.id
b.status IN ('created', 'confirmed')
e.event_type IN (
  'auto_assigned',
  'handoff_requeued',
  'requeued',
  'expired',
  'abandonment_escalated',
  'supervisor_alerted',
  'reminder_rescue',
  'template_rescue'
)
COALESCE(b.booked_at, b.created_at) > e.created_at
```

Esto es una atribucion estricta y conservadora. Evita atribuir citas a eventos no relacionados, pero pierde casos donde la trazabilidad no fue registrada con suficiente precision.

## Tablas verificadas

Existen:

- `whatsapp_handoff_events`
- `whatsapp_handoffs`
- `whatsapp_conversations`
- `whatsapp_sigcenter_bookings`

No existe:

- `whatsapp_operational_events`

Por lo tanto, la tabla real de eventos operativos hoy es `whatsapp_handoff_events`.

## Eventos encontrados

Ventana auditada: `2026-06-17 00:00:00` a `2026-06-25 00:00:00`.

Eventos operativos encontrados:

| Evento | Total |
| --- | ---: |
| `auto_assigned` | 131 |
| `expired` | 297 |
| `requeued` | 297 |

Eventos esperados por el baseline pero no encontrados en la ventana:

| Evento | Estado |
| --- | --- |
| `abandonment_escalated` | No encontrado |
| `handoff_requeued` | No encontrado |
| `reminder_rescue` | No encontrado |
| `template_rescue` | No encontrado |
| `supervisor_alerted` | No encontrado |

Hallazgo de codigo:

- `auto_assigned` se registra en `WhatsappHandoffAutoAssignService`.
- `expired` y `requeued` se registran en `ConversationOpsService`.
- `abandonment_escalated` existe en `ConversationAbandonmentMonitorService`, pero no aparecio en los datos auditados.
- `reminder_rescue`, `template_rescue`, `supervisor_alerted` y `handoff_requeued` aparecen como eventos consumidos por metricas, pero no tienen productores activos encontrados en codigo funcional.

## Citas encontradas

Ventana auditada: `2026-06-17 00:00:00` a `2026-06-25 00:00:00`.

Citas en `whatsapp_sigcenter_bookings`:

| Estado | Total | Primera | Ultima |
| --- | ---: | --- | --- |
| `created` | 5 | 2026-06-17 05:48:46 | 2026-06-23 22:21:54 |

Para `2026-06-24`, no se encontraron citas creadas en la tabla. Por eso el baseline del dia devuelve:

```json
"bookings_after_operational_intervention": {
  "total": 0
}
```

## Citas atribuibles

Pruebas de atribucion en ventana `2026-06-17` a `2026-06-25`:

| Metodo | Citas atribuibles |
| --- | ---: |
| Join actual por mismo `conversation_id` + evento previo | 0 |
| Join alternativo por `wa_number` + evento previo | 0 |
| Join alternativo por `patient_hc_number` + evento previo sin limite temporal | 2 |
| Join alternativo por `patient_hc_number` + evento previo dentro de 7 dias | 0 |

Los 2 matches por paciente no son confiables: los eventos eran de abril y las citas de junio, en conversaciones distintas. No deben contarse como rescate operacional.

## Donde se pierde trazabilidad

### 1. No hay evento para varios handoffs antes de cita

Se encontraron citas con handoff en la misma conversacion antes de la cita, pero sin evento operativo:

| Booking | Booking at | Handoff creado | Topic | Evento |
| ---: | --- | --- | --- | --- |
| 35 | 2026-06-17 05:48:46 | 2026-06-17 05:47:11 | `agenda_sin_disponibilidad` | Ninguno |
| 35 | 2026-06-17 05:48:46 | 2026-06-17 05:48:11 | `agenda_sin_disponibilidad` | Ninguno |
| 37 | 2026-06-17 13:56:47 | 2026-06-17 13:35:43 | `agenda_sin_disponibilidad` | Ninguno |
| 39 | 2026-06-23 22:21:54 | 2026-06-23 22:20:46 | `agenda_sin_disponibilidad` | Ninguno |

Interpretacion: el sistema tiene evidencia de handoff cerca de una cita, pero no evidencia de una intervencion operacional formal. El baseline no puede atribuirlas sin arriesgar inflar la metrica.

### 2. Hay handoffs despues de la cita

Ejemplos:

| Booking | Booking at | Handoff creado | Evento |
| ---: | --- | --- | --- |
| 36 | 2026-06-17 11:42:38 | 2026-06-19 17:47:49 | Ninguno |
| 38 | 2026-06-18 15:20:00 | 2026-06-22 16:01:06 | `assigned` el 2026-06-23 |

Estos no deben contar como recuperacion porque el handoff ocurrio despues de la cita.

### 3. Eventos esperados no tienen productores

El baseline incluye eventos que aun no se registran:

- `reminder_rescue`
- `template_rescue`
- `supervisor_alerted`
- `handoff_requeued`

Esto deja huecos:

- Los recordatorios se miden como `confirmed` o `failed`, pero no como intervencion operacional atribuible a cita.
- Los rescates por template no pueden medirse si no se registra un evento.
- Las alertas de supervisor no pueden medirse si no existe aun el engine que emita `supervisor_alerted`.

### 4. `expired` puede contaminar la definicion

El baseline considera `expired` como evento operacional. Eso es discutible: `expired` representa vencimiento de TTL, no una accion de rescate. Si una cita ocurre despues de `expired`, no necesariamente fue recuperada por la operacion.

Recomendacion: excluir `expired` de la metrica principal de "recuperadas" o separarlo como `system_expired_before_booking` con confianza baja.

### 5. Falta entidad explicita de atribucion

Hoy la atribucion se recalcula al vuelo con joins. No queda un registro persistente que diga:

- booking X fue atribuido a evento Y
- por que metodo
- con que confianza
- en que fecha fue calculado

Esto impide auditar cambios historicos cuando se ajustan reglas.

## Diagnostico

El tracking esta parcialmente implementado:

- Autoassign si registra evento.
- Requeue/expired si registran evento.
- Las citas del bot si guardan `conversation_id`.
- El baseline puede contar casos estrictos.

Pero el tracking esta incompleto para responder con confianza:

> Cuantas citas fueron recuperadas gracias a la capa operacional.

Faltan eventos para puntos operativos clave y falta una tabla/registro de atribucion persistente.

## Propuesta de correccion

### Fase 1: normalizar eventos operativos

Definir catalogo canonico:

| Evento | Productor |
| --- | --- |
| `handoff_created` | cuando se crea un handoff nuevo |
| `handoff_requeued` | cuando una conversacion vuelve a cola por accion operacional |
| `auto_assigned` | autoassign |
| `agent_taken` | asignacion manual o toma por agente |
| `first_response_after_assignment` | primer outbound despues de asignacion |
| `abandonment_escalated` | monitor de abandono |
| `template_rescue_sent` | template enviado para rescate |
| `reminder_rescue_sent` | recordatorio usado como rescate |
| `supervisor_alerted` | alert engine |

Mantener compatibilidad con eventos existentes:

- `requeued` debe mapear a `handoff_requeued`.
- `expired` debe quedar separado como evento de sistema, no como rescate.

### Fase 2: crear atribucion persistente

Crear tabla sugerida:

```sql
whatsapp_operational_booking_attributions
```

Campos minimos:

- `id`
- `booking_id`
- `booking_conversation_id`
- `attributed_conversation_id`
- `handoff_id`
- `event_id`
- `event_type`
- `attribution_method`
- `confidence`
- `event_at`
- `booking_at`
- `created_at`
- `updated_at`

Metodos de atribucion propuestos:

| Metodo | Confianza |
| --- | --- |
| mismo `conversation_id`, evento previo dentro de 7 dias | alta |
| mismo `patient_hc_number`, evento previo dentro de 72h, sin cita previa | media |
| mismo `wa_number` normalizado, evento previo dentro de 72h | media |
| reminder/template con booking posterior en misma conversacion | alta |

### Fase 3: recalcular metrica desde atribuciones

Cambiar `bookings_after_operational_intervention` para leer de la tabla persistente:

- `total`
- `by_event`
- `by_bucket`
- `by_agent`
- `by_method`
- `high_confidence_total`
- `medium_confidence_total`

La metrica ejecutiva debe usar solo alta confianza.

### Fase 4: tests

Agregar pruebas para:

1. Cita con evento `auto_assigned` previo en misma conversacion cuenta.
2. Cita sin evento previo no cuenta.
3. Handoff creado antes de cita pero sin evento no cuenta hasta que exista `handoff_created`.
4. Evento despues de cita no cuenta.
5. Match por paciente fuera de ventana no cuenta.
6. Match por paciente dentro de ventana queda como confianza media.
7. `expired` no cuenta como rescate principal.

## Recomendacion ejecutiva

No interpretar `bookings_after_operational_intervention = 0` como fracaso de la capa operacional todavia.

Interpretarlo asi:

> Hoy no existe evidencia trazable suficiente para atribuir citas a intervenciones operacionales bajo la regla estricta.

La siguiente mejora backend debe ser un PR de instrumentacion, no de dashboard:

```text
feat/whatsapp-operational-attribution
```

Objetivo:

1. Registrar eventos canonicos faltantes.
2. Crear atribucion persistente booking-event.
3. Excluir `expired` de rescates de alta confianza.
4. Mantener `bookings_after_operational_intervention` conservador y auditable.

Con esto se podra responder correctamente:

> Cuantas citas fueron recuperadas gracias a autoassign, requeue, abandono, recordatorio, template o alerta de supervisor.
