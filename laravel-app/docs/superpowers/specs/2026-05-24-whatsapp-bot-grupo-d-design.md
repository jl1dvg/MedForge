# WhatsApp Bot — Grupo D: Validación de Cita Activa

**Fecha:** 2026-05-24
**PR objetivo:** `whatsapp-bot-grupo-d-cita-activa`
**Rama base:** `main`

---

## Contexto

Grupos A y B ya están en producción. Este Grupo D resuelve un problema de negocio:

- Un paciente que ya tiene una cita agendada (sea por WhatsApp o directamente en clínica) puede volver a iniciar el flow de agendamiento y crear una cita duplicada.
- El bot no hace ninguna validación previa — simplemente ejecuta `list_specialties` y permite continuar.

---

## Objetivo

Agregar una acción `check_pending_appointment` al `FlowSigcenterAgendaService` que, al colocarse como nodo en el Flowmaker **antes** del flow de agendamiento, detecte si el paciente ya tiene una cita activa y le notifique con fecha, hora, médico y sede — bloqueando el re-agendamiento.

---

## Fuentes de datos (en orden de prioridad)

| Prioridad | Tabla | Condición de "cita activa" |
|-----------|-------|---------------------------|
| 1 (primera) | `whatsapp_sigcenter_bookings` | `patient_hc_number = ?` AND `status = 'created'` AND `fecha_inicio >= NOW()` |
| 2 (fallback) | `procedimiento_proyectado` | `hc_number = ?` AND `UPPER(procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%'` AND `fecha BETWEEN CURDATE() AND CURDATE() + 7 días` AND `(estado_agenda IS NULL OR estado_agenda != 'CANCELADO')` AND `sigcenter_present = true` (global scope) |

Si Query 1 devuelve resultado → se usa esa cita (no se ejecuta Query 2).
Si Query 1 vacío → se ejecuta Query 2.
Si ambas vacías → no hay cita activa, continúa el flow.

---

## Arquitectura

### Flujo después del cambio

```
Usuario elige "Agendar cita"
        │
        ▼
Nodo: check_pending_appointment  ← nuevo en FlowSigcenterAgendaService
        │
        ├─ hc_number ausente en contexto → next_state_if_not_found (skip silencioso)
        │
        ├─ Query 1: whatsapp_sigcenter_bookings
        │     patient_hc_number = hc_number AND status = 'created' AND fecha_inicio >= NOW()
        │     ┌─ encontrado → mensaje con fecha/médico/sede → next_state_if_found
        │     └─ vacío ↓
        │
        ├─ Query 2: procedimiento_proyectado (sigcenter_present = true via global scope)
        │     hc_number = ? AND procedimiento_proyectado LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%'
        │     AND fecha BETWEEN hoy AND hoy+7d AND estado_agenda != 'CANCELADO'
        │     ┌─ encontrado → mensaje con fecha/hora/doctor → next_state_if_found
        │     └─ vacío ↓
        │
        └─ Sin cita activa → next_state_if_not_found (silencioso)
```

### Archivos modificados

```
app/Modules/Whatsapp/Services/
└── FlowSigcenterAgendaService.php   ← nueva operación check_pending_appointment

(Sin migraciones — no se necesitan tablas nuevas)
(Sin cambios en FlowRuntimeExecutionService — el routing de operaciones ya existe)
(Sin cambios en WebhookService)
```

---

## Interfaz de la acción en el Flowmaker

El operador del Flowmaker agrega un nodo con esta estructura **antes** del nodo `list_specialties`:

```json
{
  "type": "sigcenter_agenda",
  "operation": "check_pending_appointment",
  "found_message": "Ya tienes una cita agendada:\n\n📅 *Fecha:* {{fecha}}\n🕒 *Horario:* {{hora}}\n👨‍⚕️ *Médico:* {{medico}}\n📍 *Sede:* {{sede}}\n\nSi necesitas cambiarla, escríbenos o llámanos.",
  "found_next_state": "menu_principal",
  "not_found_next_state": "agenda_esperando_subespecialidad"
}
```

### Variables disponibles en `found_message`

| Variable | Fuente Query 1 | Fuente Query 2 |
|----------|---------------|---------------|
| `{{fecha}}` | `fecha_inicio` (formato Y-m-d) | `fecha` (formato Y-m-d) |
| `{{hora}}` | `fecha_inicio` (formato H:i) | `hora` (formato H:i) |
| `{{medico}}` | `medico_nombre` | `doctor` |
| `{{sede}}` | `sede_nombre` | `sede_departamento` |

Si un campo es null, se omite esa línea del mensaje o se muestra vacío según el template.

---

## Implementación en `FlowSigcenterAgendaService`

### Método principal

```php
private function executeCheckPendingAppointment(array $action, array $context): array
{
    $hcNumber = $this->patientIdentifierFromContext($action, $context);
    if ($hcNumber === '') {
        return [
            'type'       => 'check_pending_appointment',
            'found'      => false,
            'next_state' => (string) ($action['not_found_next_state'] ?? ''),
        ];
    }

    $booking = $this->findActiveWhatsappBooking($hcNumber);
    if ($booking !== null) {
        return $this->buildFoundResult($action, $booking['fecha'], $booking['hora'], $booking['medico'], $booking['sede']);
    }

    $projected = $this->findActiveProjectedAppointment($hcNumber);
    if ($projected !== null) {
        return $this->buildFoundResult($action, $projected['fecha'], $projected['hora'], $projected['medico'], $projected['sede']);
    }

    return [
        'type'       => 'check_pending_appointment',
        'found'      => false,
        'next_state' => (string) ($action['not_found_next_state'] ?? ''),
    ];
}
```

### Query 1 — `whatsapp_sigcenter_bookings`

```php
private function findActiveWhatsappBooking(string $hcNumber): ?array
{
    if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
        return null;
    }

    $row = DB::table('whatsapp_sigcenter_bookings')
        ->where('patient_hc_number', $hcNumber)
        ->where('status', 'created')
        ->where('fecha_inicio', '>=', now())
        ->orderBy('fecha_inicio')
        ->first();

    if ($row === null) {
        return null;
    }

    $fechaInicio = Carbon::parse($row->fecha_inicio);
    return [
        'fecha'  => $fechaInicio->format('d/m/Y'),
        'hora'   => $fechaInicio->format('H:i'),
        'medico' => (string) ($row->medico_nombre ?? ''),
        'sede'   => (string) ($row->sede_nombre ?? ''),
    ];
}
```

### Query 2 — `procedimiento_proyectado`

```php
private function findActiveProjectedAppointment(string $hcNumber): ?array
{
    if (!Schema::hasTable('procedimiento_proyectado')) {
        return null;
    }

    $row = ProcedimientoProyectado::query()
        ->where('hc_number', $hcNumber)
        ->whereRaw("UPPER(procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%'")
        ->whereBetween('fecha', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
        ->where(function ($q) {
            $q->whereNull('estado_agenda')
              ->orWhere('estado_agenda', '!=', 'CANCELADO');
        })
        ->orderBy('fecha')
        ->first();

    if ($row === null) {
        return null;
    }

    return [
        'fecha'  => Carbon::parse($row->fecha)->format('d/m/Y'),
        'hora'   => $row->hora ? Carbon::parse($row->hora)->format('H:i') : '',
        'medico' => (string) ($row->doctor ?? ''),
        'sede'   => (string) ($row->sede_departamento ?? ''),
    ];
}
```

### Builder del resultado cuando hay cita

```php
private function buildFoundResult(array $action, string $fecha, string $hora, string $medico, string $sede): array
{
    $template = (string) ($action['found_message'] ?? 'Ya tienes una cita agendada para el {{fecha}} a las {{hora}}.');
    $body = strtr($template, [
        '{{fecha}}'  => $fecha,
        '{{hora}}'   => $hora,
        '{{medico}}' => $medico,
        '{{sede}}'   => $sede,
    ]);

    return [
        'type'           => 'check_pending_appointment',
        'found'          => true,
        'next_state'     => (string) ($action['found_next_state'] ?? ''),
        'messages'       => [['type' => 'text', 'body' => $body]],
    ];
}
```

### Integración en `preview()` y `execute()`

En el método `preview()` de `FlowSigcenterAgendaService`, agregar el case en el switch de operaciones:

```php
'check_pending_appointment' => $this->executeCheckPendingAppointment($action, $context),
```

`check_pending_appointment` no requiere confirmación ni es una operación de mutación, por lo que `mutates_agenda` y `requires_confirmation` quedan en `false`.

---

## Manejo de errores

| Situación | Comportamiento |
|-----------|----------------|
| `hc_number` ausente en contexto | skip → `not_found_next_state` (sin queries) |
| `whatsapp_sigcenter_bookings` no existe | skip Query 1, ejecuta Query 2 |
| `procedimiento_proyectado` no existe | skip Query 2, continúa |
| Excepción de DB en cualquier query | `Log::warning` + return `not_found` (falla segura — el paciente puede agendar) |

**La falla segura es intencional**: es mejor permitir un re-agendamiento accidental que bloquear injustamente a un paciente.

---

## Configuración del Flowmaker (post-implementación)

Después del deploy, el operador debe:

1. Abrir el Flowmaker → escenario de agendamiento
2. Insertar un nodo `sigcenter_agenda` con `operation: check_pending_appointment` **antes** del nodo `list_specialties`
3. Configurar `found_next_state` (ej. `menu_principal`) y `not_found_next_state` (ej. `agenda_esperando_subespecialidad`)
4. Publicar el flow

No se modifica el JSON de la DB directamente — el Flowmaker UI es el canal correcto.

---

## Criterios de aceptación

- [ ] Paciente con cita en `whatsapp_sigcenter_bookings` (status=created, fecha futura) → recibe mensaje con fecha/hora/médico/sede, no llega a `list_specialties`
- [ ] Paciente con cita en `procedimiento_proyectado` (tipo SER-OFT, próximos 7 días, no cancelada) → mismo bloqueo
- [ ] Paciente sin cita → pasa directamente a `not_found_next_state` sin mensaje
- [ ] `hc_number` ausente en contexto → pasa a `not_found_next_state` sin queries
- [ ] Error de DB → log warning, pasa a `not_found_next_state` (no rompe el flow)
- [ ] Variables `{{fecha}}`, `{{hora}}`, `{{medico}}`, `{{sede}}` se reemplazan correctamente en `found_message`

---

## Lo que NO cambia

- `WebhookService.php` — sin tocar
- `FlowRuntimeExecutionService.php` — sin tocar
- `executeActions()` — sin tocar
- Migraciones — no se necesitan tablas nuevas
- Resto de operaciones de `FlowSigcenterAgendaService` — sin tocar

---

## Grupos anteriores (en producción)

- **Grupo A:** Opt-2, 3, 5, 6 — cache humanQueueIsOpen, no-texto, cancelación hermética, fallback configurable
- **Grupo B:** Opt-1, 4 — cola asíncrona, optimistic locking de sesión

## Grupos siguientes

- **Grupo C:** Opt-7 (descomponer executeActions) + Opt-8 (AI fallback)
