# Auditoría operacional: whatsapp:handoff-auto-assign

Fecha: 2026-06-24
Rama: `test/whatsapp-operational-stack`

## Qué consume hoy el autoassign

El comando actual consume handoffs con:

- `whatsapp_handoffs.status = queued`
- `whatsapp_handoffs.assigned_agent_id IS NULL`
- `whatsapp_conversations.needs_human = true`
- `whatsapp_conversations.assigned_user_id IS NULL`
- tópico en:
  - `captacion_agendar`
  - `agenda_sin_disponibilidad`
  - `faq_escalada`
  - `operacion_cita_vigente`
  - `operacion_reagenda`
- edad basada en `whatsapp_handoffs.queued_at` o, si está vacío, `whatsapp_handoffs.created_at`
- ventana por parámetro `--max-age-hours`, hoy programada en 72 horas

El comando no usa todavía los buckets operacionales nuevos.

## Simulación con datos reales

Comando:

```bash
php artisan whatsapp:handoff-auto-assign --dry-run --limit=500 --max-age-hours=72
```

Resultado:

| Métrica | Valor |
|---|---:|
| Elegibles | 2 |
| Asignaría | 2 |
| Supervisor | 0 |
| Ignoradas | 0 |

Distribución por tópico:

| Tópico | Total |
|---|---:|
| `agenda_sin_disponibilidad` | 1 |
| `captacion_agendar` | 1 |

Distribución por bucket operacional:

| Bucket | Total |
|---|---:|
| `HOT_OPEN` | 1 |
| `HOT_NEEDS_TEMPLATE` | 1 |
| `RESCUE` | 0 |
| `BACKLOG` | 0 |
| `LOST` | 0 |

Ambas conversaciones serían asignadas a `Solange Katherine Villafuerte Castro` según la carga actual.

## Prueba de riesgo con ventana amplia

Comando:

```bash
php artisan whatsapp:handoff-auto-assign --dry-run --limit=500 --max-age-hours=720
```

Resultado:

| Bucket | Total |
|---|---:|
| `HOT_OPEN` | 1 |
| `HOT_NEEDS_TEMPLATE` | 1 |
| `RESCUE` | 57 |
| `BACKLOG` | 3 |
| `LOST` | 0 |

Esto confirma el riesgo principal: si una conversación antigua es reencolada o si se amplía `--max-age-hours`, el autoassign puede tratar deuda operacional como oportunidad comercial activa, porque su filtro se basa en `handoff.queued_at` y no en una política explícita de bucket.

## Política propuesta

| Bucket | Acción recomendada |
|---|---|
| `HOT_OPEN` | Autoasignar inmediatamente dentro de horario laboral. Es la única cola que debe entrar al autoassign comercial normal. |
| `HOT_NEEDS_TEMPLATE` | No autoasignar como conversación libre. Enviar a cola de recuperación con plantilla o supervisor, porque Meta ya exige template. |
| `RESCUE` | No autoasignar masivamente. Procesar con reporte diario y asignación controlada por supervisor o job con límite separado. |
| `BACKLOG` | No autoasignar. Mostrar como deuda histórica operacional y limpiar/cerrar con política explícita. |
| `LOST` | No autoasignar. Mostrar como perdido/expirado; requiere campaña o decisión gerencial, no contact center en vivo. |

## Propuesta de siguiente PR

PR sugerido: `feat/whatsapp-autoassign-operational-buckets`

Cambios:

1. Reemplazar el filtro de `candidateRows()` para consumir solo `HOT_OPEN`.
2. Agregar opción `--bucket=hot_open|hot_needs_template|rescue` con default `hot_open`.
3. Mantener `--max-age-hours` como defensa secundaria, no como criterio principal.
4. Reportar en dry-run:
   - total por bucket
   - asignables
   - requieren plantilla
   - supervisor
   - deuda ignorada
5. Agregar tests para impedir que `RESCUE`, `BACKLOG` y `LOST` entren al autoassign por defecto.

No se implementó ese cambio en este PR; este PR deja la auditoría y el Daily Rescue Report.
