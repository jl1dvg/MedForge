# WhatsApp Bot — Grupo A: Optimizaciones de Robustez

**Fecha:** 2026-05-23
**PR objetivo:** `whatsapp-bot-grupo-a-optimizaciones`
**Rama base:** `main`

---

## Contexto

El bot de WhatsApp tiene 10 puntos de fallo identificados en un análisis de código. El Grupo A agrupa los 4 que se pueden resolver con cambios aditivos sin tocar `executeActions` ni el matching de escenarios, eliminando el riesgo de romper el sistema de listas ya configurado.

---

## Optimizaciones incluidas

| ID | Nombre | Fallo que resuelve |
|----|--------|-------------------|
| Opt-2 | Fallback configurable desde flowmaker | Bot silencioso cuando nada hace match |
| Opt-3 | Respuesta a mensajes no-texto | Audio/imagen/sticker ignorados sin respuesta |
| Opt-5 | Caché de `humanQueueIsOpen()` | 5 queries DB repetidas por cada mensaje entrante |
| Opt-6 | Estado de cancelación hermético | `agenda_confirmar_cancelacion` rompía con input inválido |

---

## Arquitectura

### Archivos modificados

```
app/Modules/Whatsapp/Services/
├── FlowRuntimeExecutionService.php   ← 4 cambios puntuales y aditivos
└── FlowmakerService.php              ← agrega campo al schema + sanitizeFlow

app/Modules/Settings/Services/
└── SettingsService.php               ← invalida caché en saves de whatsapp_handoff_*

resources/views/whatsapp/
└── v2-flowmaker.blade.php            ← panel "Configuración del flujo"
```

### Orden de ejecución después de los cambios

```
executeInbound()
  │
  ├─ guard assigned_user / needs_human
  │     └─ humanQueueIsOpen() ← [Opt-5] con Cache::remember(60s)
  │
  ├─ [Opt-3] ¿message_type ∈ {audio,image,video,sticker,document,location}?
  │     └─ sí → responder con CTA "Escribe MENU" y salir
  │         (solo llega aquí si el bot está activo, no el agente humano)
  │
  ├─ [Opt-6] ¿state === 'agenda_confirmar_cancelacion'?
  │     ├─ confirmed → cancelar (lógica existente)
  │     ├─ rejected  → rechazar (lógica existente)
  │     └─ else      → pedir confirmación nuevamente y salir [NUEVO]
  │
  ├─ loop de escenarios → executeActions() [SIN CAMBIOS]
  │
  └─ sin match → [Opt-2] leer flow.settings.no_match_fallback_message y enviar
```

---

## Opt-3 — Respuesta a mensajes no-texto

### Ubicación
`FlowRuntimeExecutionService::executeInbound()` — **después** de los guards de `assigned_user_id` y `needs_human`, antes de cargar el flow. Esto garantiza que si la conversación está asignada a un agente humano, el bot no interfiere: el agente ya ve el audio/imagen en su bandeja.

### Lógica

```php
$type = trim((string) ($inboundMessage->message_type ?? 'text'));

if ($text === '' && in_array($type, ['audio', 'image', 'video', 'sticker', 'document', 'location'], true)) {
    $mediaReplies = [
        'audio'    => "Recibí un audio, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
        'image'    => "Recibí una imagen, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
        'video'    => "Recibí un video, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
        'sticker'  => "¡Gracias! 😄 Si necesitas ayuda, escribe *MENU*.",
        'document' => "Recibí un documento, pero solo proceso texto.\n¿Necesitas ayuda? Escribe *MENU*.",
        'location' => "Recibí tu ubicación, pero no la proceso aún.\n¿Necesitas ayuda? Escribe *MENU*.",
    ];
    $this->sendFlowMessage($conversation, [
        'type' => 'text',
        'body' => $mediaReplies[$type] ?? "Solo proceso mensajes de texto.\nEscribe *MENU* para ver las opciones.",
    ], []);
    return $this->result(true, false, 'non_text_reply', 1, false, 'empty_text');
}
// guard original intacto debajo
```

### Condición de activación
Solo actúa cuando el bot está activo. Al ubicarse después de los guards de `assigned_user_id` y `needs_human`, si la conversación está en manos de un agente el código ya habrá retornado antes de llegar aquí — el agente ve el mensaje de media en su bandeja sin interferencia del bot.

**Nota:** El guard original `if ($text === '')` permanece intacto debajo para otros casos edge (ej. `interactive` reply sin body resoluble).

---

## Opt-5 — Caché de `humanQueueIsOpen()`

### Problema
`humanQueueIsOpen()` ejecuta 5 queries a la tabla `settings` y se llama mínimo 2 veces por cada mensaje entrante (hasta 3 si se ejecuta `releaseConversationToBot`).

### Cambio en `FlowRuntimeExecutionService`

```php
private function humanQueueIsOpen(): bool
{
    return Cache::remember('whatsapp.queue_open_status', 60, function (): bool {
        $options = $this->settingsOptions([
            'whatsapp_handoff_business_timezone',
            'whatsapp_handoff_business_schedule',
            'whatsapp_handoff_business_holidays',
            'whatsapp_handoff_business_start',
            'whatsapp_handoff_business_end',
        ]);
        return $this->computeHumanQueueIsOpen($options);
    });
}

// Lógica actual extraída sin cambios:
private function computeHumanQueueIsOpen(array $options): bool
{
    // ... misma lógica que tiene humanQueueIsOpen() hoy
}
```

### Invalidación en `SettingsService`

Al final de cualquier operación de guardado (individual o batch), después de persistir los valores:

```php
$touchedHandoff = collect(array_keys($savedData))
    ->contains(fn (string $k) => str_starts_with($k, 'whatsapp_handoff_'));

if ($touchedHandoff) {
    Cache::forget('whatsapp.queue_open_status');
}
```

### TTL elegido
60 segundos. La resolución de horario de atención es por minuto — un cache de 60s es más que suficiente y transparente para el usuario.

---

## Opt-6 — Estado de cancelación hermético

### Problema
Cuando `context['state'] === 'agenda_confirmar_cancelacion'` y el usuario manda texto no reconocido (ni confirmación ni rechazo), el código caía al loop de escenarios. Un escenario podía matchear (ej. "HOLA" → menú principal), dejando la cita sin cancelar y borrando el estado.

### Cambio en `FlowRuntimeExecutionService::executeInbound()`

Se separa el guard en dos bloques independientes:

```php
// Bloque 1: estado correcto → hermético
if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion') {
    $activeBooking = $this->activeSigcenterBooking($conversation, $context);
    if ($activeBooking !== null) {
        if ($this->bookingCancellationConfirmed($text)) {
            // lógica existente de cancelación confirmada
        } elseif ($this->bookingCancellationRejected($text)) {
            // lógica existente de rechazo
        } else {
            // NUEVO: texto no reconocido → re-preguntar y mantener estado
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => "No entendí tu respuesta 🤔\n\n"
                    . "¿Confirmas la cancelación de tu cita?\n"
                    . "Escribe *SÍ* para cancelar o *NO* para mantenerla.",
            ], $context);
            return $this->result(true, true, 'booking_cancel_awaiting', 1, false, null);
        }
    }
    // sin booking activo → cae al loop de escenarios normales
}

// Bloque 2: OR de confirmación explícita (sin cambios al comportamiento existente)
if ($this->isExplicitCancelConfirmationReply($text)) {
    $activeBooking = $this->activeSigcenterBooking($conversation, $context);
    // ... lógica existente
}
```

### Estado de sesión
El estado no se modifica en el `else`. La próxima interacción del usuario vuelve a entrar al Bloque 1 y se le vuelve a preguntar. El abandonment monitor cierra la sesión si el usuario no responde en el tiempo configurado para ese estado.

---

## Opt-2 — Fallback configurable desde el flowmaker

### Tres componentes

#### 1. Schema del flow — `FlowmakerService::defaultFlowPayload()`

```php
'settings' => [
    'timezone' => 'America/Guayaquil',
    'no_match_fallback_message' => "No entendí tu mensaje 🤔\nEscribe *MENU* para ver las opciones disponibles.",
],
```

#### 2. `FlowmakerService::sanitizeFlow()`

Verificar (y corregir si aplica) que `sanitizeFlow()` preserve `settings.no_match_fallback_message`. Si tiene un allowlist de keys en `settings`, agregar `no_match_fallback_message`. **Criterio de aceptación del PR:** publicar un flow con el campo seteado y verificar que se mantiene en `entry_settings` después del publish.

#### 3. Runtime — `FlowRuntimeExecutionService::executeInbound()`

Al final del método, cuando ningún escenario hizo match y `recoverNoMatchFlow()` retornó `null`:

```php
$fallbackBody = trim((string) ($flow['settings']['no_match_fallback_message'] ?? ''));
if ($fallbackBody === '') {
    $fallbackBody = "No entendí tu mensaje.\nEscribe *MENU* para ver las opciones.";
}

$this->sendFlowMessage($conversation, ['type' => 'text', 'body' => $fallbackBody], $context);

WhatsappAutoresponderSession::query()->updateOrCreate(
    ['conversation_id' => $conversation->id],
    [
        'wa_number'           => (string) $conversation->wa_number,
        'scenario_id'         => 'no_match_fallback',
        'awaiting'            => null,
        'context'             => $context,   // estado preservado sin cambios
        'last_payload'        => $messagePayload,
        'last_interaction_at' => now(),
    ]
);

return $this->result(true, false, 'no_match_fallback', 1, false, 'no_match');
```

**El estado del contexto no se modifica.** Si el usuario estaba en un paso con cedula/médico capturado, ese progreso se preserva.

#### 4. UI del flowmaker — `v2-flowmaker.blade.php`

Nuevo panel en la columna izquierda, debajo del panel de Escenarios:

```html
<div class="wa-flow-panel" id="wa-flow-settings-panel">
    <div class="wa-flow-panel__head">
        <div class="wa-flow-sideheading__title">Configuración del flujo</div>
        <div class="wa-flow-sideheading__meta">
            Opciones globales que aplican a todo el flujo.
        </div>
    </div>
    <div class="wa-flow-panel__body">
        <div class="wa-flow-stack">
            <div>
                <label class="form-label">Mensaje cuando ningún escenario responde</label>
                <textarea
                    id="wa-flow-setting-fallback"
                    class="form-control"
                    rows="3"
                    placeholder="No entendí tu mensaje. Escribe MENU para ver las opciones."
                ></textarea>
                <div class="wa-flow-inline-note mt-6">
                    Se envía automáticamente cuando el usuario escribe algo
                    que ningún escenario reconoce.
                </div>
            </div>
        </div>
    </div>
</div>
```

JS (inline en la blade, siguiendo el patrón existente):
- **Al cargar el flow:** leer `flow.settings.no_match_fallback_message` y setear el value del textarea.
- **Al publicar:** incluir el valor en `flow.settings.no_match_fallback_message` dentro del payload.

---

## Manejo de errores

| Situación | Comportamiento |
|-----------|----------------|
| `sendFlowMessage` falla en Opt-3 | Error silencioso logueado, no bloquea el webhook |
| Cache no disponible (Redis down) | `Cache::remember` degrada a lógica directa — sin fallo |
| `no_match_fallback_message` vacío en flow | Se usa el texto hardcoded de defensa |
| `sanitizeFlow` borra el campo | Fallback usa el texto hardcoded — no hay regresión funcional |

---

## Criterios de aceptación

- [ ] Enviar audio/imagen/sticker/video al bot → recibe respuesta con CTA "Escribe MENU"
- [ ] Enviar texto no reconocido → recibe el mensaje configurado en flowmaker (no silencio)
- [ ] En flowmaker: editar el mensaje de fallback, publicar, simular → el simulador retorna el nuevo texto
- [ ] Estar en `agenda_confirmar_cancelacion` y enviar "ok" → bot repregunta, no muestra el menú
- [ ] Estar en `agenda_confirmar_cancelacion` y enviar "SI" → cancela correctamente (regresión)
- [ ] Estar en `agenda_confirmar_cancelacion` y enviar "NO" → rechaza correctamente (regresión)
- [ ] Guardar `whatsapp_handoff_business_schedule` en Settings → caché de horario se invalida
- [ ] Tests existentes de `WhatsappWebhookControllerTest` pasan sin modificación

---

## Lo que NO cambia

- `executeActions()` — sin tocar
- Matching de escenarios (`scenarioMatches`) — sin tocar
- Sistema de listas (SigCenter, catálogos, opciones interactivas) — sin tocar
- `recoverNoMatchFlow()` — sin tocar (el fallback actúa después de que retorna `null`)
- Modelos Eloquent — sin migraciones ni cambios de schema DB

---

## Grupos siguientes (fuera de scope de este PR)

- **Grupo B:** Cola asíncrona (Opt-1) + Version pinning de sesión (Opt-4)
- **Grupo C:** Descomponer `executeActions` (Opt-7) + AI fallback (Opt-8)
