# WhatsApp Bot Grupo A — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar 4 optimizaciones de robustez en el bot de WhatsApp en un solo PR aditivo que no toca `executeActions` ni el matching de escenarios.

**Architecture:** Todos los cambios son inserciones quirúrgicas en `FlowRuntimeExecutionService::executeInbound()` (Opt-3, 5, 6, 2), más schema update en `FlowmakerService`, invalidación de caché en `SettingsService::upsert()`, y un nuevo panel HTML+JS en la blade del flowmaker.

**Tech Stack:** PHP 8.2, Laravel, `Illuminate\Support\Facades\Cache`, Eloquent, Blade + vanilla JS.

**Spec:** `docs/superpowers/specs/2026-05-23-whatsapp-bot-grupo-a-design.md`

---

## Mapa de archivos

| Archivo | Rol en este PR |
|---------|----------------|
| `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` | 4 cambios quirúrgicos en `executeInbound()` + extracción de `computeHumanQueueIsOpen()` |
| `app/Modules/Whatsapp/Services/FlowmakerService.php` | Agrega `no_match_fallback_message` en `defaultFlowPayload()` |
| `app/Modules/Settings/Services/SettingsService.php` | Invalida caché en `upsert()` cuando key empieza con `whatsapp_handoff_` |
| `resources/views/whatsapp/v2-flowmaker.blade.php` | Panel "Configuración del flujo" + JS para leer/escribir el campo |
| `tests/Feature/WhatsappWebhookControllerTest.php` | Tests para Opt-3, 5, 6 |
| `tests/Feature/WhatsappFlowmakerTest.php` | Test para Opt-2 (schema + fallback runtime) |

---

## Task 1: Opt-5 — Caché de `humanQueueIsOpen()`

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (método `humanQueueIsOpen` ~línea 509)
- Modify: `app/Modules/Settings/Services/SettingsService.php` (método `upsert` ~línea 52)
- Test: `tests/Feature/WhatsappWebhookControllerTest.php`

---

- [ ] **Step 1.1: Escribir el test de invalidación de caché**

Agregar al final de `tests/Feature/WhatsappWebhookControllerTest.php`, antes del último `}`:

```php
public function test_it_invalidates_queue_open_cache_when_handoff_setting_changes(): void
{
    // Primear el caché manualmente
    \Illuminate\Support\Facades\Cache::put('whatsapp.queue_open_status', true, 60);
    $this->assertTrue(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));

    $service = app(\App\Modules\Settings\Services\SettingsService::class);
    $service->upsert('whatsapp_handoff_business_timezone', 'America/Lima', 'whatsapp');

    $this->assertFalse(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));
}

public function test_it_does_not_invalidate_queue_cache_for_non_handoff_settings(): void
{
    \Illuminate\Support\Facades\Cache::put('whatsapp.queue_open_status', true, 60);

    $service = app(\App\Modules\Settings\Services\SettingsService::class);
    $service->upsert('whatsapp_cloud_api_token', 'abc123', 'whatsapp');

    $this->assertTrue(\Illuminate\Support\Facades\Cache::has('whatsapp.queue_open_status'));
}
```

- [ ] **Step 1.2: Correr el test para verificar que falla**

```bash
cd laravel-app && php artisan test --filter="test_it_invalidates_queue_open_cache_when_handoff_setting_changes" --stop-on-failure
```

Resultado esperado: `FAILED` — el caché sigue existiendo porque `upsert` aún no lo invalida.

- [ ] **Step 1.3: Agregar invalidación en `SettingsService::upsert()`**

Abrir `app/Modules/Settings/Services/SettingsService.php`. Reemplazar el método `upsert()` completo (líneas 52-63):

```php
public function upsert(string $name, string $value, string $category, string $type = 'text'): void
{
    AppSetting::query()->updateOrCreate(
        ['name' => $name],
        [
            'category' => $category,
            'value' => $value,
            'type' => $type,
            'autoload' => true,
        ]
    );

    if (str_starts_with($name, 'whatsapp_handoff_')) {
        Cache::forget('whatsapp.queue_open_status');
    }
}
```

- [ ] **Step 1.4: Correr los dos tests para verificar que pasan**

```bash
php artisan test --filter="test_it_invalidates_queue_open_cache_when_handoff_setting_changes|test_it_does_not_invalidate_queue_cache_for_non_handoff_settings" --stop-on-failure
```

Resultado esperado: `PASSED` para ambos.

- [ ] **Step 1.5: Extraer `computeHumanQueueIsOpen()` en `FlowRuntimeExecutionService`**

Abrir `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`. Localizar el método `humanQueueIsOpen()` (~línea 509). Reemplazarlo completo por estos dos métodos:

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

/**
 * @param array<string,string> $options
 */
private function computeHumanQueueIsOpen(array $options): bool
{
    $timezone = trim((string) ($options['whatsapp_handoff_business_timezone'] ?? 'America/Guayaquil'));
    if ($timezone === '') {
        $timezone = 'America/Guayaquil';
    }

    $now = Carbon::now($timezone);
    if ($this->isConfiguredHoliday($now->toDateString(), (string) ($options['whatsapp_handoff_business_holidays'] ?? ''))) {
        return false;
    }

    $daySchedule = $this->resolveDaySchedule($now->isoWeekday(), $options);
    if ($daySchedule === null || !($daySchedule['enabled'] ?? false)) {
        return false;
    }

    $start = $this->minutesFromHour((string) ($daySchedule['start'] ?? '08:00'), 8 * 60);
    $end = $this->minutesFromHour((string) ($daySchedule['end'] ?? '18:00'), 18 * 60);
    $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

    if ($start === $end) {
        return true;
    }

    if ($start < $end) {
        return $current >= $start && $current < $end;
    }

    return $current >= $start || $current < $end;
}
```

> La lógica es idéntica a la que estaba en `humanQueueIsOpen()`. Solo se extrae para poder envolverla con `Cache::remember`.

- [ ] **Step 1.6: Verificar que el import de `Cache` existe en `FlowRuntimeExecutionService`**

Buscar en los `use` al inicio del archivo:

```bash
grep "use Illuminate\Support\Facades\Cache" app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Si no aparece, agregar la línea al bloque de imports. Si ya existe, continuar.

- [ ] **Step 1.7: Correr la suite completa del webhook para detectar regresiones**

```bash
php artisan test tests/Feature/WhatsappWebhookControllerTest.php --stop-on-failure
```

Resultado esperado: todos los tests previos siguen en `PASSED`.

- [ ] **Step 1.8: Commit**

```bash
git add app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php \
        app/Modules/Settings/Services/SettingsService.php \
        tests/Feature/WhatsappWebhookControllerTest.php
git commit -m "$(cat <<'EOF'
perf(whatsapp): cache humanQueueIsOpen() with 60s TTL, invalidate on handoff settings change

Reduces 5 repeated DB queries per inbound message to a single cache hit.
Cache is busted immediately when any whatsapp_handoff_* setting is saved.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Opt-3 — Respuesta a mensajes no-texto

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (inicio de `executeInbound`)
- Test: `tests/Feature/WhatsappWebhookControllerTest.php`

---

- [ ] **Step 2.1: Escribir el test**

Agregar en `tests/Feature/WhatsappWebhookControllerTest.php`:

```php
public function test_it_responds_to_audio_message_with_text_only_notice(): void
{
    Http::fake([
        '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.1']]], 200),
    ]);

    config()->set('whatsapp.migration.automation.enabled', true);
    config()->set('whatsapp.migration.api.phone_number_id', '123456');
    config()->set('whatsapp.migration.api.token', 'test-token');

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'contacts' => [[
                        'wa_id' => '593999888777',
                        'profile' => ['name' => 'Paciente Audio'],
                    ]],
                    'messages' => [[
                        'from' => '593999888777',
                        'id' => 'wamid.audio.1',
                        'timestamp' => '1712745600',
                        'type' => 'audio',
                        'audio' => ['id' => 'audio_media_id_001'],
                    ]],
                ],
            ]],
        ]],
    ];

    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.messages_persisted', 1)
        ->assertJsonPath('data.automation_runs', 1)
        ->assertJsonPath('data.automation_messages_sent', 1);

    // El mensaje de audio fue persistido correctamente
    $this->assertDatabaseHas('whatsapp_messages', [
        'direction' => 'inbound',
        'message_type' => 'audio',
    ]);

    // Verificar que se intentó enviar la respuesta de "solo proceso texto"
    Http::assertSent(function ($request) {
        $body = $request->data();
        return str_contains($body['text']['body'] ?? '', 'MENU');
    });
}

public function test_it_does_not_respond_to_audio_when_conversation_is_assigned_to_agent(): void
{
    Http::fake([
        '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.2']]], 200),
    ]);

    config()->set('whatsapp.migration.automation.enabled', true);
    config()->set('whatsapp.migration.api.phone_number_id', '123456');
    config()->set('whatsapp.migration.api.token', 'test-token');

    // Crear conversación asignada a un agente humano
    \DB::table('whatsapp_conversations')->insert([
        'wa_number' => '593999777666',
        'needs_human' => false,
        'assigned_user_id' => 999, // agente asignado
        'unread_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insertar horario abierto para que humanQueueIsOpen() = true
    \DB::table('app_settings')->insert([
        ['name' => 'whatsapp_handoff_business_timezone', 'value' => 'America/Guayaquil', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'whatsapp_handoff_business_schedule', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'whatsapp_handoff_business_start', 'value' => '00:00', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'whatsapp_handoff_business_end', 'value' => '00:00', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '593999777666',
                        'id' => 'wamid.audio.assigned',
                        'timestamp' => '1712745600',
                        'type' => 'audio',
                        'audio' => ['id' => 'audio_media_id_002'],
                    ]],
                ],
            ]],
        ]],
    ];

    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertOk()
        ->assertJsonPath('data.automation_runs', 0)
        ->assertJsonPath('data.automation_messages_sent', 0);

    // El bot NO envió respuesta (el agente maneja la conversación)
    Http::assertNothingSent();
}
```

- [ ] **Step 2.2: Correr los tests para verificar que fallan**

```bash
php artisan test --filter="test_it_responds_to_audio_message_with_text_only_notice|test_it_does_not_respond_to_audio_when_conversation_is_assigned_to_agent" --stop-on-failure
```

Resultado esperado: `FAILED` — el bot actualmente no responde a audios (`automation_messages_sent` = 0).

- [ ] **Step 2.3: Modificar el inicio de `executeInbound()`**

Abrir `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`. Localizar estas 3 líneas al inicio de `executeInbound()`:

```php
$text = trim((string)($inboundMessage->body ?? ''));
if ($text === '') {
    return $this->result(false, false, null, 0, false, 'empty_text');
}
```

Reemplazarlas por:

```php
$text = trim((string)($inboundMessage->body ?? ''));
$type = trim((string)($inboundMessage->message_type ?? 'text'));
$isMediaMessage = $text === '' && in_array($type, ['audio', 'image', 'video', 'sticker', 'document', 'location'], true);

if ($text === '' && !$isMediaMessage) {
    return $this->result(false, false, null, 0, false, 'empty_text');
}
```

- [ ] **Step 2.4: Insertar el handler de no-texto después de los guards de humano**

En el mismo método `executeInbound()`, localizar el bloque que termina en los dos guards de verificación:

```php
if ((bool)($conversation->assigned_user_id ?? false)) {
    return $this->result(false, false, null, 0, false, 'conversation_assigned');
}

if ((bool) ($conversation->needs_human ?? false)) {
    return $this->result(false, false, null, 0, false, 'conversation_needs_human');
}
```

Insertar el siguiente bloque **inmediatamente después** de ese último `}`:

```php
if ($isMediaMessage) {
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
```

- [ ] **Step 2.5: Correr los tests para verificar que pasan**

```bash
php artisan test --filter="test_it_responds_to_audio_message_with_text_only_notice|test_it_does_not_respond_to_audio_when_conversation_is_assigned_to_agent" --stop-on-failure
```

Resultado esperado: `PASSED` para ambos.

- [ ] **Step 2.6: Correr la suite completa para detectar regresiones**

```bash
php artisan test tests/Feature/WhatsappWebhookControllerTest.php --stop-on-failure
```

Resultado esperado: todos los tests anteriores siguen en `PASSED`.

- [ ] **Step 2.7: Commit**

```bash
git add app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php \
        tests/Feature/WhatsappWebhookControllerTest.php
git commit -m "$(cat <<'EOF'
feat(whatsapp): respond to non-text messages with actionable CTA

Audio, image, video, sticker, document and location messages now receive
a friendly 'solo proceso texto' reply with MENU as the next action.
Handler runs only when the bot is active (after human-agent guards).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Opt-6 — Estado de cancelación hermético

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (guard de cancelación en `executeInbound`)
- Test: `tests/Feature/WhatsappWebhookControllerTest.php`

---

- [ ] **Step 3.1: Escribir el test**

Agregar en `tests/Feature/WhatsappWebhookControllerTest.php`:

```php
public function test_it_re_asks_when_user_sends_unrecognized_text_during_cancel_confirmation(): void
{
    Http::fake([
        '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.out.3']]], 200),
    ]);

    config()->set('whatsapp.migration.automation.enabled', true);
    config()->set('whatsapp.migration.api.phone_number_id', '123456');
    config()->set('whatsapp.migration.api.token', 'test-token');

    // Conversación con estado de cancelación pendiente
    \DB::table('whatsapp_conversations')->insert([
        'wa_number' => '593999555444',
        'needs_human' => false,
        'assigned_user_id' => null,
        'unread_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversation = \DB::table('whatsapp_conversations')->where('wa_number', '593999555444')->first();

    \DB::table('whatsapp_autoresponder_sessions')->insert([
        'wa_number' => '593999555444',
        'conversation_id' => $conversation->id,
        'scenario_id' => 'agenda_cancelar',
        'awaiting' => null,
        'context' => json_encode([
            'state' => 'agenda_confirmar_cancelacion',
            'sigcenter_agenda_id' => '42',
        ]),
        'last_interaction_at' => now()->subMinutes(2),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Registrar una cita activa en sigcenter bookings
    \DB::table('whatsapp_sigcenter_bookings')->insert([
        'conversation_id' => $conversation->id,
        'wa_number' => '593999555444',
        'sigcenter_agenda_id' => 42,
        'status' => 'confirmed',
        'booked_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Usuario manda texto ambiguo que no es ni SÍ ni NO
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '593999555444',
                        'id' => 'wamid.cancel.ambiguous',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'ok lo pienso'],
                    ]],
                ],
            ]],
        ]],
    ];

    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertOk()
        ->assertJsonPath('data.automation_runs', 1)
        ->assertJsonPath('data.automation_messages_sent', 1);

    // El bot re-preguntó (no mostró el menú principal ni otro escenario)
    Http::assertSent(function ($request) {
        $body = $request->data();
        return str_contains($body['text']['body'] ?? '', 'SÍ') &&
               str_contains($body['text']['body'] ?? '', 'NO');
    });

    // El estado de la sesión NO cambió a menu_principal
    $session = \DB::table('whatsapp_autoresponder_sessions')
        ->where('conversation_id', $conversation->id)
        ->first();
    $ctx = json_decode($session->context, true);
    $this->assertSame('agenda_confirmar_cancelacion', $ctx['state']);
}
```

- [ ] **Step 3.2: Verificar que la tabla `whatsapp_sigcenter_bookings` existe en setUp**

Buscar en el `setUp()` del test:

```bash
grep "whatsapp_sigcenter_bookings" tests/Feature/WhatsappWebhookControllerTest.php | head -5
```

Si el `setUp()` ya crea y destruye esa tabla, continuar. Si no, agregar al principio del `setUp()`:

```php
Schema::dropIfExists('whatsapp_sigcenter_bookings');
```

Y al final del bloque de `Schema::create(...)`:

```php
Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('conversation_id');
    $table->string('wa_number', 32);
    $table->unsignedBigInteger('sigcenter_agenda_id')->nullable();
    $table->string('status', 32)->default('confirmed');
    $table->timestamp('booked_at')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3.3: Correr el test para verificar que falla**

```bash
php artisan test --filter="test_it_re_asks_when_user_sends_unrecognized_text_during_cancel_confirmation" --stop-on-failure
```

Resultado esperado: `FAILED` — actualmente el bot cae al loop de escenarios y puede responder con el menú principal.

- [ ] **Step 3.4: Agregar el else hermético en `executeInbound()`**

Localizar en `executeInbound()` el bloque que empieza con:

```php
if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' || $this->isExplicitCancelConfirmationReply($text)) {
    $activeBooking = $this->activeSigcenterBooking($conversation, $context);
    if ($activeBooking !== null && $this->bookingCancellationConfirmed($text)) {
```

Dentro de ese bloque, después del `if ($this->bookingCancellationRejected($text)) { ... return ...; }` y antes del cierre `}` del bloque exterior, agregar:

```php
    // Hermético: si el estado es confirmar cancelación y el texto no fue reconocido, re-preguntar.
    if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' && $activeBooking !== null) {
        $this->sendFlowMessage($conversation, [
            'type' => 'text',
            'body' => "No entendí tu respuesta 🤔\n\n"
                . "¿Confirmas la cancelación de tu cita?\n"
                . "Escribe *SÍ* para cancelar o *NO* para mantenerla.",
        ], $context);
        return $this->result(true, true, 'booking_cancel_awaiting', 1, false, null);
    }
```

El bloque completo debe quedar así:

```php
if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' || $this->isExplicitCancelConfirmationReply($text)) {
    $activeBooking = $this->activeSigcenterBooking($conversation, $context);
    if ($activeBooking !== null && $this->bookingCancellationConfirmed($text)) {
        // ... lógica existente de cancelación confirmada (sin tocar)
        return $this->result(true, true, 'booking_cancel_confirmation', 1, false, null);
    }

    if ($this->bookingCancellationRejected($text)) {
        // ... lógica existente de rechazo (sin tocar)
        return $this->result(true, true, 'booking_cancel_rejected', 1, false, null);
    }

    // NUEVO: hermético
    if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' && $activeBooking !== null) {
        $this->sendFlowMessage($conversation, [
            'type' => 'text',
            'body' => "No entendí tu respuesta 🤔\n\n"
                . "¿Confirmas la cancelación de tu cita?\n"
                . "Escribe *SÍ* para cancelar o *NO* para mantenerla.",
        ], $context);
        return $this->result(true, true, 'booking_cancel_awaiting', 1, false, null);
    }
}
```

- [ ] **Step 3.5: Correr los tests de cancelación**

```bash
php artisan test --filter="test_it_re_asks_when_user_sends_unrecognized_text_during_cancel_confirmation" --stop-on-failure
```

Resultado esperado: `PASSED`.

- [ ] **Step 3.6: Correr la suite completa para detectar regresiones**

```bash
php artisan test tests/Feature/WhatsappWebhookControllerTest.php --stop-on-failure
```

Resultado esperado: todos los tests anteriores siguen en `PASSED`.

- [ ] **Step 3.7: Commit**

```bash
git add app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php \
        tests/Feature/WhatsappWebhookControllerTest.php
git commit -m "$(cat <<'EOF'
fix(whatsapp): make booking cancellation confirmation state hermetic

Unrecognized input during agenda_confirmar_cancelacion now re-asks the
yes/no question instead of falling through to scenario matching, which
could incorrectly show the main menu and clear the cancellation state.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Opt-2 — Fallback configurable (backend)

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowmakerService.php` (`defaultFlowPayload`, verificar `sanitizeFlow`)
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (final de `executeInbound`)
- Test: `tests/Feature/WhatsappWebhookControllerTest.php` + `tests/Feature/WhatsappFlowmakerTest.php`

---

- [ ] **Step 4.1: Escribir el test del schema en `WhatsappFlowmakerTest`**

Agregar en `tests/Feature/WhatsappFlowmakerTest.php`:

```php
public function test_default_flow_payload_includes_no_match_fallback_message(): void
{
    $service = app(\App\Modules\Whatsapp\Services\FlowmakerService::class);
    $payload = $service->getActiveFlowPayload();

    $this->assertArrayHasKey('settings', $payload);
    $this->assertArrayHasKey('no_match_fallback_message', $payload['settings']);
    $this->assertNotEmpty($payload['settings']['no_match_fallback_message']);
}

public function test_sanitize_flow_preserves_no_match_fallback_message(): void
{
    // Publicar un flow con el campo seteado
    $service = app(\App\Modules\Whatsapp\Services\FlowmakerService::class);

    $customMessage = 'Mensaje personalizado de prueba para fallback.';
    $flowPayload = $service->getActiveFlowPayload();
    $flowPayload['settings']['no_match_fallback_message'] = $customMessage;

    // Publicar (esto pasa por sanitizeFlow internamente)
    $result = $service->publish($flowPayload);
    $this->assertTrue($result['ok'] ?? false, 'El publish debería ser exitoso');

    // Leer el flow publicado y verificar que el campo se preservó
    $saved = $service->getActiveFlowPayload();
    $this->assertSame($customMessage, $saved['settings']['no_match_fallback_message'] ?? null);
}
```

- [ ] **Step 4.2: Escribir el test del fallback runtime en `WhatsappWebhookControllerTest`**

Agregar en `tests/Feature/WhatsappWebhookControllerTest.php`:

```php
public function test_it_sends_no_match_fallback_message_when_no_scenario_matches(): void
{
    Http::fake([
        '*graph.facebook.com*' => Http::response(['messages' => [['id' => 'wamid.fallback.1']]], 200),
    ]);

    config()->set('whatsapp.migration.automation.enabled', true);
    config()->set('whatsapp.migration.api.phone_number_id', '123456');
    config()->set('whatsapp.migration.api.token', 'test-token');

    // Publicar un flow real con el mensaje de fallback personalizado
    $flowmakerService = app(\App\Modules\Whatsapp\Services\FlowmakerService::class);
    $payload = $flowmakerService->getActiveFlowPayload();
    $payload['settings']['no_match_fallback_message'] = 'Texto fallback de prueba. Escribe MENU.';
    $flowmakerService->publish($payload);

    // Mensaje que no va a hacer match con ningún escenario
    $webhookPayload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '593999333222',
                        'id' => 'wamid.nomatch.1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'xyzabc123 mensaje sin sentido que nunca matchea'],
                    ]],
                ],
            ]],
        ]],
    ];

    $response = $this->postJson('/whatsapp/webhook', $webhookPayload);

    $response->assertOk()
        ->assertJsonPath('data.automation_runs', 1)
        ->assertJsonPath('data.automation_messages_sent', 1);

    // Verificar que se envió el fallback personalizado
    Http::assertSent(function ($request) {
        $body = $request->data();
        return str_contains($body['text']['body'] ?? '', 'Texto fallback de prueba');
    });

    // Sesión grabada como no_match_fallback
    $this->assertDatabaseHas('whatsapp_autoresponder_sessions', [
        'wa_number' => '593999333222',
        'scenario_id' => 'no_match_fallback',
    ]);
}
```

- [ ] **Step 4.3: Correr los tests para verificar que fallan**

```bash
php artisan test --filter="test_default_flow_payload_includes_no_match_fallback_message|test_sanitize_flow_preserves_no_match_fallback_message|test_it_sends_no_match_fallback_message_when_no_scenario_matches" --stop-on-failure
```

Resultado esperado: `FAILED` — el campo no existe aún en el schema, y el bot no envía fallback.

- [ ] **Step 4.4: Agregar `no_match_fallback_message` en `FlowmakerService::defaultFlowPayload()`**

Abrir `app/Modules/Whatsapp/Services/FlowmakerService.php`. Localizar el método `defaultFlowPayload()` (~línea 335). Reemplazar el bloque `'settings'`:

```php
// ANTES:
'settings' => [
    'timezone' => 'America/Guayaquil',
],

// DESPUÉS:
'settings' => [
    'timezone' => 'America/Guayaquil',
    'no_match_fallback_message' => "No entendí tu mensaje 🤔\nEscribe *MENU* para ver las opciones disponibles.",
],
```

- [ ] **Step 4.5: Verificar que `sanitizeFlow()` ya preserva el campo**

Leer la línea ~327 de `FlowmakerService.php`:

```php
'settings' => is_array($flow['settings'] ?? null) ? $flow['settings'] : ['timezone' => 'America/Guayaquil'],
```

Esta línea ya preserva `settings` completo como array. **No se requiere ningún cambio** — `no_match_fallback_message` se preserva automáticamente.

- [ ] **Step 4.6: Agregar el bloque de fallback al final de `executeInbound()`**

Localizar el final del método `executeInbound()`. Encontrar la última línea de retorno que dice algo como:

```php
return $this->result(true, false, null, 0, false, 'no_match');
```

Reemplazarla por:

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
        'context'             => $context,
        'last_payload'        => $messagePayload,
        'last_interaction_at' => now(),
    ]
);

return $this->result(true, false, 'no_match_fallback', 1, false, 'no_match');
```

- [ ] **Step 4.7: Correr los tres tests**

```bash
php artisan test --filter="test_default_flow_payload_includes_no_match_fallback_message|test_sanitize_flow_preserves_no_match_fallback_message|test_it_sends_no_match_fallback_message_when_no_scenario_matches" --stop-on-failure
```

Resultado esperado: `PASSED` para los tres.

- [ ] **Step 4.8: Suite completa**

```bash
php artisan test tests/Feature/WhatsappWebhookControllerTest.php tests/Feature/WhatsappFlowmakerTest.php --stop-on-failure
```

Resultado esperado: todos los tests pasan.

- [ ] **Step 4.9: Commit**

```bash
git add app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php \
        app/Modules/Whatsapp/Services/FlowmakerService.php \
        tests/Feature/WhatsappWebhookControllerTest.php \
        tests/Feature/WhatsappFlowmakerTest.php
git commit -m "$(cat <<'EOF'
feat(whatsapp): send configurable no-match fallback instead of silence

When no scenario matches and recovery paths return null, the bot now sends
a message from flow.settings.no_match_fallback_message (editable in
flowmaker) instead of going silent. Falls back to a hardcoded default if
the field is empty.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Opt-2 — Fallback configurable (UI del flowmaker)

**Files:**
- Modify: `resources/views/whatsapp/v2-flowmaker.blade.php`

---

- [ ] **Step 5.1: Agregar el panel HTML en la blade**

Abrir `resources/views/whatsapp/v2-flowmaker.blade.php`. Localizar el panel de Escenarios:

```html
<div class="wa-flow-panel">
    <div class="wa-flow-panel__head">
        <div class="d-flex justify-content-between align-items-center gap-10">
            <div>
                <div class="wa-flow-sideheading__title">Escenarios</div>
```

Insertar el siguiente bloque **inmediatamente antes** del panel de Escenarios:

```html
<div class="wa-flow-panel" id="wa-flow-settings-panel">
    <div class="wa-flow-panel__head">
        <div class="wa-flow-sideheading__title">Configuración del flujo</div>
        <div class="wa-flow-sideheading__meta">Opciones globales que aplican a todo el flujo.</div>
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
                    Se envía automáticamente cuando el usuario escribe algo que ningún escenario reconoce.
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 5.2: Agregar el JS para cargar el campo al inicializar el flow**

Buscar en el JS de la blade la función que inicializa el flow (buscar `normalized.settings` o `extractVersionFlow`). Agregar la lectura del campo justo donde se carga el flow en el editor.

Buscar la sección donde se rellena el editor con el flow activo. Debería existir una función que popula los escenarios en el canvas. Después de donde se asigna `normalized.settings`, agregar:

```javascript
// Cargar el mensaje de fallback en el panel de configuración
const fallbackTextarea = document.getElementById('wa-flow-setting-fallback');
if (fallbackTextarea) {
    fallbackTextarea.value = normalized.settings?.no_match_fallback_message ?? '';
}
```

- [ ] **Step 5.3: Agregar el JS para incluir el campo al publicar**

Buscar la función que construye el payload antes de enviarlo al endpoint de publish (buscar `wa-flow-payload` o la función de publicar). Localizar donde se construye el objeto `flow` o `settings`. Agregar:

```javascript
// Incluir el fallback message en settings antes de publicar
const fallbackVal = document.getElementById('wa-flow-setting-fallback')?.value?.trim() ?? '';
if (flow.settings === undefined || flow.settings === null) {
    flow.settings = {};
}
flow.settings.no_match_fallback_message = fallbackVal;
```

- [ ] **Step 5.4: Verificar manualmente en el browser**

1. Iniciar el servidor: `php artisan serve`
2. Abrir el flowmaker en el browser: `http://localhost:8000/whatsapp/v2` (o la ruta correcta)
3. Verificar que aparece el panel "Configuración del flujo" con el textarea
4. Verificar que el textarea muestra el mensaje actual del flow
5. Cambiar el mensaje, hacer publish, recargar la página → el nuevo mensaje debe persistir
6. Simular un mensaje que no hace match → el simulador debe mostrar el nuevo texto

- [ ] **Step 5.5: Commit**

```bash
git add resources/views/whatsapp/v2-flowmaker.blade.php
git commit -m "$(cat <<'EOF'
feat(flowmaker): add flow settings panel with no-match fallback message field

New 'Configuración del flujo' panel lets operators edit the message sent
when no scenario matches, without requiring a code deploy.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Verificación final y PR

- [ ] **Step 6.1: Correr la suite completa de tests**

```bash
php artisan test tests/Feature/WhatsappWebhookControllerTest.php tests/Feature/WhatsappFlowmakerTest.php --stop-on-failure
```

Resultado esperado: todos los tests pasan, incluyendo los preexistentes.

- [ ] **Step 6.2: Verificar los criterios de aceptación del spec**

Revisar cada criterio del spec:

```
[ ] Enviar audio/imagen/sticker/video → recibe respuesta con CTA "Escribe MENU"
[ ] Enviar texto no reconocido → recibe el mensaje configurado en flowmaker
[ ] En flowmaker: editar fallback, publicar, simular → simulador retorna nuevo texto
[ ] Estado agenda_confirmar_cancelacion + enviar "ok" → bot repregunta (no menú)
[ ] Estado agenda_confirmar_cancelacion + enviar "SI" → cancela (regresión)
[ ] Estado agenda_confirmar_cancelacion + enviar "NO" → rechaza (regresión)
[ ] Guardar whatsapp_handoff_business_schedule → caché de horario se invalida
[ ] Tests existentes de WhatsappWebhookControllerTest pasan sin modificación
```

- [ ] **Step 6.3: Crear el PR**

```bash
git push origin claude/cranky-goodall-3ab07d

gh pr create \
  --base main \
  --title "feat(whatsapp): Grupo A — 4 robustez improvements (no-text, fallback, cancel state, cache)" \
  --body "$(cat <<'EOF'
## Summary

- **Opt-3:** Bot now responds to audio/image/video/sticker with a friendly 'solo proceso texto' + MENU CTA instead of silence
- **Opt-5:** `humanQueueIsOpen()` cached for 60s (was hitting DB 5x per message); cache busted when `whatsapp_handoff_*` settings change
- **Opt-6:** `agenda_confirmar_cancelacion` state is now hermetic — unrecognized input re-asks the yes/no question instead of falling through to scenario matching
- **Opt-2:** Bot sends configurable fallback message (from flowmaker settings) instead of silence when no scenario matches

## What did NOT change
- `executeActions()`, `scenarioMatches()`, list-feeding system — untouched
- No DB migrations

## Test plan
- [ ] Run `php artisan test tests/Feature/WhatsappWebhookControllerTest.php tests/Feature/WhatsappFlowmakerTest.php`
- [ ] Manually send audio via WhatsApp sandbox → confirm bot responds
- [ ] Edit fallback message in flowmaker → confirm simulator shows new text
- [ ] Test cancellation flow with ambiguous input → confirm bot re-asks

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-Review Notes

**Cobertura del spec:**
- Opt-2 backend ✅ Task 4
- Opt-2 UI ✅ Task 5
- Opt-3 ✅ Task 2
- Opt-5 ✅ Task 1
- Opt-6 ✅ Task 3
- Invalidación de caché en batch saves: cubierto porque `upsertBatch()` llama `upsert()` por cada key

**Riesgos residuales documentados:**
- Step 5.2 y 5.3 requieren inspección manual del JS existente antes de insertar código — la ubicación exacta depende de la función de inicialización que ya existe en la blade (el implementador debe leer el JS circundante antes de pegar el código)
- El test de Step 3.1 requiere que `whatsapp_sigcenter_bookings` exista en setUp — verificado en Step 3.2
