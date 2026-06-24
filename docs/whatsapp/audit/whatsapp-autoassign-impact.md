# Auditoria urgente: impacto de whatsapp:handoff-auto-assign

Fecha de auditoria: 2026-06-24  
Rama: `audit/whatsapp-autoassign-impact`  
Alcance: backend solamente. No se modifico frontend, React, Blade, dashboard, CSS ni Flowmaker.  
Accion de rollback: no ejecutada.

## Resumen ejecutivo

Se identificaron 132 conversaciones autoasignadas por el sistema mediante eventos `auto_assigned` con `actor_user_id IS NULL`.

Ventana detectada:

- Primera autoasignacion: `2026-06-24 07:00:02`
- Ultima autoasignacion auditada: `2026-06-24 15:50:01`
- Conversaciones unicas afectadas: 132

El codigo de `WhatsappHandoffAutoAssignService` solo asigna cuando:

- `whatsapp_conversations.assigned_user_id IS NULL`
- `whatsapp_handoffs.assigned_agent_id IS NULL`
- `whatsapp_handoffs.status = queued`

Por eso no hay evidencia de sobrescritura directa de una asignacion vigente. Si hubo reasignacion, fue operacional/historica: conversaciones que antes habian tenido eventos/asignaciones, luego quedaron en cola y el comando las tomo otra vez.

## Conteos principales

| Metrica | Total |
| --- | ---: |
| Autoasignaciones del sistema | 132 |
| Conversaciones unicas | 132 |
| Sobrescrituras directas de asignacion vigente | 0 |
| Senal de reasignacion historica | 74 |
| Sin primera respuesta humana posterior | 108 |
| Con primera respuesta humana posterior | 24 |
| Sin actividad posterior | 89 |
| Con actividad posterior | 43 |

Definiciones:

- `primera respuesta humana posterior`: primer mensaje `outbound` en `whatsapp_messages` despues del evento `auto_assigned`.
- `actividad posterior`: cualquier mensaje de WhatsApp posterior al evento, inbound u outbound.
- `senal de reasignacion historica`: existe una asignacion/evento previo en el mismo handoff o conversacion antes del `auto_assigned`. No significa overwrite directo.

## Estado actual frente a rollback

| Clase | Total | Con respuesta posterior | Con actividad posterior |
| --- | ---: | ---: | ---: |
| Candidatas todavia asignadas | 120 | 16 | 35 |
| No revertir: resueltas/cerradas | 12 | 8 | 8 |

Lectura:

- Hay 120 conversaciones que siguen con la asignacion automatica vigente.
- Dentro de esas 120, 16 ya tuvieron respuesta posterior y 35 tuvieron algun tipo de actividad posterior.
- Hay 12 que ya aparecen resueltas/cerradas; no deberian revertirse automaticamente.

## Distribucion por bucket operacional al momento de autoasignacion

| Bucket | Total |
| --- | ---: |
| `HOT_OPEN` | 66 |
| `HOT_NEEDS_TEMPLATE` | 18 |
| `RESCUE` | 48 |
| `BACKLOG` | 0 |
| `LOST` | 0 |

Interpretacion:

- El comando no consumio `BACKLOG` ni `LOST` en esta ventana.
- Si el criterio deseado es autoasignar `HOT_OPEN` y `HOT_NEEDS_TEMPLATE`, y condicionar `RESCUE`, entonces 48 asignaciones requieren revision de politica.

## Distribucion por topic

| Topic | Total |
| --- | ---: |
| `faq_escalada` | 90 |
| `agenda_sin_disponibilidad` | 31 |
| `captacion_agendar` | 9 |
| `operacion_cita_vigente` | 2 |

## Distribucion por origen

| Origen | Total |
| --- | ---: |
| `organic_direct` | 63 |
| `ad` | 41 |
| `patient_return` | 12 |
| `campaign_outbound` | 10 |
| `support_operational` | 3 |
| `post_consultation` | 2 |
| `post_surgery` | 1 |

## Agentes con mayor volumen autoasignado

| Agente | Autoasignadas | Con respuesta | Aun abiertas asignadas |
| --- | ---: | ---: | ---: |
| Alessia Fabiana Cumba Montiel | 12 | 9 | 11 |
| Valeria Michelle Hidalgo Valarezo | 11 | 3 | 11 |
| Anggie Cristell Coronel Carpio | 9 | 4 | 3 |
| Solange Katherine Villafuerte Castro | 4 | 2 | 2 |
| Nancy Aracely Cevallos Loor | 4 | 1 | 4 |
| Mariuxi Margaret Casal Vera | 4 | 1 | 4 |
| Valeria Damaris Garces Rodriguez | 4 | 2 | 4 |

El detalle completo por agente esta disponible con la consulta SQL incluida en `docs/whatsapp/audit/whatsapp-autoassign-impact.sql`.

## Hallazgos

### 1. Autoassign si modifico conversaciones reales

El comando no fue solo dry-run: existen 132 eventos `auto_assigned` reales y las conversaciones/handoffs quedaron actualizados.

### 2. No hay overwrite directo de asignacion vigente

El codigo bloquea filas y exige `assigned_user_id IS NULL` y `assigned_agent_id IS NULL` en la transaccion. Por tanto, no se detecta que haya pisado asignaciones manuales activas.

### 3. Si hubo reasignacion operacional historica

74 autoasignaciones tienen evidencia de asignacion previa en el mismo handoff o en la misma conversacion. Esto normalmente representa: asignada antes, expirada/reencolada, y luego autoasignada otra vez.

### 4. La mayor alerta es respuesta posterior baja

108 de 132 no tienen respuesta humana posterior registrada. Esto puede significar:

- agentes no actuaron despues de recibir la conversacion;
- la conversacion ya estaba fria;
- la autoasignacion distribuyo carga a usuarios disponibles tecnicamente, pero no operativamente activos;
- la respuesta ocurrio por otro canal no medido en `whatsapp_messages`.

### 5. RESCUE fue autoasignado

48 conversaciones estaban en `RESCUE` al momento de autoasignacion. Esto puede ser aceptable si hay capacidad, pero segun la politica operacional deseada deberia ser condicionado. Si no habia capacidad real, estas 48 explican parte del ruido operativo.

## Plan de reversion

No ejecutar sin aprobacion.

Se propone rollback conservador en dos niveles:

### Nivel A: rollback ultra conservador

Revertir solo conversaciones que:

- fueron autoasignadas por el sistema;
- siguen asignadas al mismo agente;
- el handoff sigue `assigned`;
- la conversacion sigue `needs_human = 1`;
- no tienen respuesta humana posterior al `auto_assigned`;
- no tienen eventos manuales posteriores en el handoff.

Este nivel evita tocar conversaciones que ya tuvieron gestion humana.

### Nivel B: rollback amplio pero todavia acotado

Revertir todas las que siguen asignadas por autoassign, aunque hayan tenido actividad posterior. No se recomienda como primera opcion porque 35 tienen actividad posterior y 16 respuesta humana posterior.

## SQL de reversion propuesto

Ver archivo:

- `docs/whatsapp/audit/whatsapp-autoassign-impact.sql`

El SQL esta escrito para auditar primero con `SELECT`, crear una tabla temporal de candidatos y dejar la transaccion en `ROLLBACK` por defecto. Para ejecutarlo realmente habria que revisar conteos y cambiar explicitamente `ROLLBACK` por `COMMIT`.

## Recomendacion

No haria rollback masivo.

Haria esto:

1. Pausar temporalmente el scheduler de `whatsapp:handoff-auto-assign` si sigue activo.
2. Ejecutar el SELECT de candidatos de rollback nivel A.
3. Revertir solo las candidatas sin respuesta ni actividad manual posterior.
4. Dejar intactas las resueltas/cerradas y las que ya tuvieron respuesta humana.
5. Ajustar el siguiente PR para que autoassign trabaje por politica:
   - `HOT_OPEN`: asignar.
   - `HOT_NEEDS_TEMPLATE`: asignar o enviar a cola de template.
   - `RESCUE`: asignar solo si hay capacidad real.
   - `BACKLOG`: no asignar.
   - `LOST`: nunca asignar.

