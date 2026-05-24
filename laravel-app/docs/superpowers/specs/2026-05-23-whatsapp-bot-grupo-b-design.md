# WhatsApp Bot — Grupo B: Cola Asíncrona y Version Pinning

**Fecha:** 2026-05-23
**PR objetivo:** `whatsapp-bot-grupo-b-optimizaciones`
**Rama base:** `main`

---

## Contexto

El Grupo A (Opt-2, 3, 5, 6) ya está en producción. El Grupo B resuelve dos problemas de infraestructura:

- **Opt-1**: El webhook procesa todo en el hilo HTTP. Si `WebhookService::process()` tarda >5s, WhatsApp considera el webhook caído y reintenta, causando duplicados.
- **Opt-4**: Dos mensajes del mismo número que llegan con <1s de diferencia pueden cargar la misma sesión simultáneamente, procesar en paralelo y el segundo sobrescribir el estado del primero. Esto ya ha causado pérdida de estados en producción.

---

## Optimizaciones incluidas

| ID | Nombre | Problema que resuelve |
|----|--------|----------------------|
| Opt-1 | Cola asíncrona (database queue + cron) | Timeout del webhook → retries de WhatsApp → duplicados |
| Opt-4 | Version pinning de sesión (optimistic locking) | Race condition entre mensajes concurrentes del mismo número |

---

## Arquitectura

### Flujo después de los cambios

```
WhatsApp Cloud API
       │ POST /webhook
       ▼
WebhookController::receive()
  ├─ valida payload
  ├─ dispatch(ProcessInboundMessageJob) ──→ tabla jobs (DB queue)
  └─ return 200 OK  ←── <100ms, WA no reintenta

                    Cron IONOS (cada minuto):
                    php8.3-cli artisan queue:work
                      --stop-when-empty --max-time=55
                              │
                    ProcessInboundMessageJob
                      middleware: WithoutOverlapping($waNumber)
                        releaseAfter(5s) · expireAfter(120s)
                              │
                    WebhookService::process()   ← sin cambios
                              │
                    FlowRuntimeExecutionService::executeInbound()
                      ├─ carga sesión → captura session_version
                      ├─ … lógica existente sin cambios …
                      └─ UPDATE WHERE session_version = :leído
                           ├─ 1 fila → OK, version++
                           └─ 0 filas → conflicto → log + discard
```

### Archivos modificados

```
app/Jobs/
└── ProcessInboundMessageJob.php              ← nuevo

app/Modules/Whatsapp/Http/Controllers/
└── WebhookController.php                     ← dispatch en lugar de call directo

app/Modules/Whatsapp/Services/
└── FlowRuntimeExecutionService.php           ← leer/escribir session_version

database/migrations/
└── 2026_05_23_XXXXXX_add_session_version_to_whatsapp_autoresponder_sessions.php  ← nuevo
```

`WebhookService.php` y toda la lógica de procesamiento quedan **sin tocar**.

---

## Opt-1 — Cola asíncrona

### `ProcessInboundMessageJob`

```php
namespace App\Jobs;

use App\Modules\Whatsapp\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly array $payload,
        private readonly string $waNumber,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->waNumber))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(WebhookService $service): void
    {
        $service->process($this->payload);
    }
}
```

### `WebhookController::receive()` — cambio puntual

```php
// helper privado — extrae el wa_number del payload para la clave de overlapping
private function extractWaNumber(array $payload): string
{
    $entry = $payload['entry'][0] ?? [];
    $change = $entry['changes'][0] ?? [];
    $messages = $change['value']['messages'] ?? [];
    return (string) ($messages[0]['from'] ?? 'unknown');
}

// en receive(), reemplazar el bloque try:
$waNumber = $this->extractWaNumber($payload);
ProcessInboundMessageJob::dispatch($payload, $waNumber);
return response()->json(['ok' => true, 'data' => ['queued' => true]]);
```

### Cron en IONOS

Panel de administración IONOS → Cron Jobs:

```
* * * * * php8.3-cli /homepages/26/d793096920/htdocs/medforge/laravel-app/artisan queue:work --stop-when-empty --max-time=55 2>&1
```

- `--stop-when-empty`: el worker termina cuando no hay más jobs, evita procesos zombie.
- `--max-time=55`: el worker para a los 55s aunque haya jobs, para que el siguiente cron no se solape.
- `WithoutOverlapping` usa el cache driver (file en IONOS) como mutex por `wa_number`.

### Comportamiento de `WithoutOverlapping`

- Si `+593...` ya tiene un job procesándose → el segundo job se repone en cola, reintenta en 5s.
- `tries = 3`, `backoff = 10s`: el job reintenta hasta 3 veces con 10s de espera entre intentos.
- Delay máximo para el usuario: ~20s en el peor caso (segundo mensaje de una ráfaga).
- `expireAfter(120)`: si un job muere sin liberar el lock, el lock expira en 2 minutos.

### Queue driver en producción

```env
QUEUE_CONNECTION=database
```

La tabla `jobs` ya existe en Laravel por defecto. Si no existe: `php artisan queue:table && php artisan migrate`.

---

## Opt-4 — Version pinning de sesión

### Migración

```php
Schema::table('whatsapp_autoresponder_sessions', function (Blueprint $table): void {
    $table->unsignedTinyInteger('session_version')->default(1)->after('last_interaction_at');
});
```

`TINYINT UNSIGNED` soporta 0–255. Dado que la versión se incrementa por mensaje, es suficiente para cualquier conversación (255 interacciones antes de que haga wrap-around a 0 — aceptable porque el conflicto de versión solo se detecta en la ventana de concurrencia, no históricamente).

### Cambios en `FlowRuntimeExecutionService::executeInbound()`

**Al cargar la sesión** — capturar versión antes de procesar:

```php
$session = WhatsappAutoresponderSession::query()
    ->where('conversation_id', $conversation->id)
    ->first();

$loadedVersion = (int) ($session?->session_version ?? 0);
$context  = $session ? ($session->context ?? []) : [];
$awaiting = $session ? ($session->awaiting ?? null) : null;
```

**Al guardar estado** — reemplazar cada `updateOrCreate` por update con version check + insert para sesión nueva:

```php
private function saveSession(
    WhatsappConversation $conversation,
    string $waNumber,
    int $loadedVersion,
    string $scenarioId,
    ?string $nodeId,
    ?string $awaiting,
    array $context,
    array $messagePayload,
): void {
    $data = [
        'wa_number'           => $waNumber,
        'scenario_id'         => $scenarioId,
        'node_id'             => $nodeId,
        'awaiting'            => $awaiting,
        'context'             => $context,
        'last_payload'        => $messagePayload,
        'last_interaction_at' => now(),
        'session_version'     => $loadedVersion + 1,
    ];

    if ($loadedVersion === 0) {
        // sesión nueva → insert
        WhatsappAutoresponderSession::create(
            array_merge($data, ['conversation_id' => $conversation->id, 'session_version' => 1])
        );
        return;
    }

    $affected = WhatsappAutoresponderSession::query()
        ->where('conversation_id', $conversation->id)
        ->where('session_version', $loadedVersion)
        ->update($data);

    if ($affected === 0) {
        Log::warning('whatsapp.session_conflict', [
            'wa_number'      => $waNumber,
            'loaded_version' => $loadedVersion,
        ]);
    }
}
```

Los llamadores de `updateOrCreate` dentro de `executeInbound` se reemplazan por `$this->saveSession(...)`. Los puntos de retorno que hoy hacen `updateOrCreate` antes de `return $this->result(...)` pasan a llamar `saveSession` primero.

### Casos cubiertos

| Escenario | Comportamiento |
|-----------|---------------|
| Mensaje único normal | update exitoso, version++ |
| Dos mensajes concurrentes | uno graba, el otro detecta 0 filas → log + return |
| Primer mensaje de un número | loadedVersion=0 → insert con version=1 |
| Job muerto sin grabar | siguiente mensaje encuentra versión consistente |

---

## Manejo de errores

| Situación | Comportamiento |
|-----------|---------------|
| Job falla 3 veces | pasa a `failed_jobs`, no bloquea el webhook |
| `WithoutOverlapping` lock expirado | el job siguiente toma el lock normalmente |
| Conflict de versión | log warning, return silencioso — el estado del "ganador" es correcto |
| Cron se solapa (two workers) | `WithoutOverlapping` por `wa_number` serializa igualmente |
| `jobs` table no existe | error evidente al primer dispatch — crear con `queue:table` |

---

## Criterios de aceptación

- [ ] Enviar webhook → respuesta 200 en <500ms — el job queda en `jobs` table
- [ ] Cron corre → job procesado → `jobs` table vacía
- [ ] Dos mensajes del mismo número en <1s → solo uno graba estado, el otro loguea `session_conflict`
- [ ] Mensaje único normal → estado grabado correctamente con `session_version` incrementada
- [ ] `failed_jobs` recibe el job si `WebhookService::process()` lanza excepción 3 veces
- [ ] Tests existentes de `WhatsappWebhookControllerTest` pasan (el controller ahora retorna `queued: true`)

---

## Lo que NO cambia

- `WebhookService.php` — sin tocar
- `FlowRuntimeExecutionService::executeActions()` — sin tocar
- Matching de escenarios — sin tocar
- Sistema de listas / SigCenter — sin tocar
- Modelos Eloquent (salvo la migración de `session_version`) — sin nuevas relaciones

---

## Grupos siguientes (fuera de scope)

- **Grupo C:** Descomponer `executeActions` (Opt-7) + AI fallback (Opt-8)
