# WhatsApp Bot — Grupo D: Validación de Cita Activa

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar la operación `check_pending_appointment` a `FlowSigcenterAgendaService` para que el bot detecte al inicio del flow de agendamiento si el paciente ya tiene una cita activa (creada por WhatsApp o en clínica) y le notifique con fecha/hora/médico/sede.

**Architecture:** Nueva operación en `FlowSigcenterAgendaService` que consulta primero `whatsapp_sigcenter_bookings` y luego `procedimiento_proyectado`. Devuelve un mensaje configurable si encuentra cita, o transiciona silenciosamente al primer paso de agenda si no encuentra. Se requiere un retoque mínimo en `FlowRuntimeExecutionService` para aplicar `next_state` sin outbound message en el caso "no encontrado".

**Tech Stack:** Laravel 10, PHP 8.2, Eloquent, DB::table, ProcedimientoProyectado (global scope sigcenter_present=true).

---

## Mapa de archivos

| Archivo | Cambio |
|---------|--------|
| `app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php` | Nueva operación + 4 métodos privados + normalizeOperation + early returns |
| `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` | 3 líneas: aplicar `next_state` para check_pending_appointment sin outbound_message |
| `tests/Feature/WhatsappWebhookControllerTest.php` | Tabla `procedimiento_proyectado` en setUp + 4 tests |

No se tocan: `WebhookService`, `WebhookController`, migraciones.

---

### Task 1: Operación `check_pending_appointment` en FlowSigcenterAgendaService + fix de estado en FlowRuntimeExecutionService

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php`
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`
- Test: `tests/Feature/WhatsappWebhookControllerTest.php`

> Contexto: `FlowSigcenterAgendaService` procesa operaciones de tipo `sigcenter_agenda`. Cada operación pasa por `preview()` y luego `execute()`. La operación `check_pending_appointment` necesita bypasear el flujo normal de preview/execute porque no construye un payload para SigCenter — solo consulta la DB local y devuelve un resultado directo.
>
> Problema: `FlowRuntimeExecutionService` solo actualiza `context['state']` cuando hay un `outbound_message`. Para el caso "no encontrado" (sin mensaje), necesitamos 3 líneas extra que apliquen `next_state` para esta operación específica.

- [ ] **Step 1: Agregar `use App\Models\ProcedimientoProyectado;` en FlowSigcenterAgendaService**

En `app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php`, el bloque de `use` actual termina en línea ~9. Agregar después de la última línea `use`:

```php
use App\Models\ProcedimientoProyectado;
```

El bloque de imports quedará:
```php
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\ProcedimientoProyectado;
```

Verificar que `Log` ya esté importado; si no, agregarlo también.

- [ ] **Step 2: Agregar `check_pending_appointment` a `normalizeOperation()`**

Localizar `normalizeOperation()` (~línea 1117). Agregar el case antes de `default`:

```php
private function normalizeOperation(string $operation): string
{
    return match ($operation) {
        'especialidades', 'specialties', 'list_specialties' => 'list_specialties',
        'medicos', 'doctors', 'list_doctors' => 'list_doctors',
        'buscar_medicos', 'search_doctors', 'list_doctors_by_name' => 'list_doctors_by_name',
        'sedes', 'list_sedes' => 'list_sedes',
        'sedes_por_medico', 'doctor_sedes', 'list_sedes_by_doctor' => 'list_sedes_by_doctor',
        'fechas_especialidad', 'dates_by_specialty', 'list_dates_by_specialty' => 'list_dates_by_specialty',
        'medicos_por_fecha', 'doctors_by_date', 'list_doctors_by_date' => 'list_doctors_by_date',
        'procedimientos', 'list_procedimientos' => 'list_procedimientos',
        'dias', 'days', 'list_days' => 'list_days',
        'horarios', 'times', 'list_times' => 'list_times',
        'agendar', 'book', 'book_appointment' => 'book_appointment',
        'cancelar', 'cancel', 'cancel_appointment' => 'cancel_appointment',
        'check_pending_appointment', 'verificar_cita' => 'check_pending_appointment',
        default => 'list_days',
    };
}
```

- [ ] **Step 3: Agregar early return en `preview()` para check_pending_appointment**

En `preview()` (~línea 48), después de las dos líneas de `normalizeOperation` e `inferOperationFromAction`, agregar el early return:

```php
public function preview(array $action, array $context, array $input): array
{
    $operation = $this->normalizeOperation((string) ($action['operation'] ?? 'list_days'));
    $operation = $this->inferOperationFromAction($operation, $action);

    // check_pending_appointment no usa el flujo normal de preview/execute
    if ($operation === 'check_pending_appointment') {
        return $this->executeCheckPendingAppointment($action, $context);
    }

    $payload = $this->buildPayload($operation, $action, $context, $input);
    // ... resto del método sin cambios
```

- [ ] **Step 4: Agregar early return en `execute()` para check_pending_appointment**

En `execute()` (~línea 106), después de `$preview = $this->preview(...)` y `$operation = (string) $preview['operation']`:

```php
public function execute(array $action, array $context, array $input, bool $confirmed = false): array
{
    $preview = $this->preview($action, $context, $input);
    $operation = (string) $preview['operation'];

    // preview() ya resuelve check_pending_appointment completamente
    if ($operation === 'check_pending_appointment') {
        return $preview;
    }

    if (in_array($operation, ['list_specialties', ...], true)) {
        // ... resto sin cambios
```

- [ ] **Step 5: Agregar los 4 métodos privados al final de FlowSigcenterAgendaService**

Antes del cierre `}` de la clase (última línea del archivo), agregar:

```php
    private function executeCheckPendingAppointment(array $action, array $context): array
    {
        $hcNumber = $this->patientIdentifierFromContext($action, $context);

        $base = [
            'type'                 => 'sigcenter_agenda',
            'operation'            => 'check_pending_appointment',
            'mutates_agenda'       => false,
            'requires_confirmation' => false,
            'preview_only'         => false,
            'executed'             => true,
        ];

        if ($hcNumber === '') {
            return array_merge($base, [
                'found'       => false,
                'send_result' => false,
                'next_state'  => (string) ($action['not_found_next_state'] ?? ''),
            ]);
        }

        try {
            $booking = $this->findActiveWhatsappBooking($hcNumber);
            if ($booking !== null) {
                return array_merge($base, $this->buildFoundResult($action, $booking));
            }

            $projected = $this->findActiveProjectedAppointment($hcNumber);
            if ($projected !== null) {
                return array_merge($base, $this->buildFoundResult($action, $projected));
            }
        } catch (\Throwable $e) {
            Log::warning('whatsapp.check_pending_appointment_error', [
                'hc_number' => $hcNumber,
                'error'     => $e->getMessage(),
            ]);
        }

        return array_merge($base, [
            'found'       => false,
            'send_result' => false,
            'next_state'  => (string) ($action['not_found_next_state'] ?? ''),
        ]);
    }

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

    private function findActiveProjectedAppointment(string $hcNumber): ?array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return null;
        }

        $row = ProcedimientoProyectado::query()
            ->where('hc_number', $hcNumber)
            ->whereRaw("UPPER(procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%'")
            ->whereBetween('fecha', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->where(function ($q): void {
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

    private function buildFoundResult(array $action, array $cita): array
    {
        $template = (string) ($action['found_message'] ?? 'Ya tienes una cita agendada para el {{fecha}} a las {{hora}}.');
        $body = strtr($template, [
            '{{fecha}}'  => $cita['fecha'],
            '{{hora}}'   => $cita['hora'],
            '{{medico}}' => $cita['medico'],
            '{{sede}}'   => $cita['sede'],
        ]);

        return [
            'found'           => true,
            'send_result'     => true,
            'next_state'      => (string) ($action['found_next_state'] ?? ''),
            'outbound_message' => ['type' => 'text', 'body' => $body],
        ];
    }
```

- [ ] **Step 6: Fix de 3 líneas en FlowRuntimeExecutionService para aplicar next_state sin outbound_message**

En `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`, localizar el bloque de `send_result` (~línea 840-849):

```php
                if (!empty($preview['send_result']) && is_array($preview['outbound_message'] ?? null)) {
                    $this->sendFlowMessage($conversation, $preview['outbound_message'], $context);
                    $messagesSent++;
                    if (is_string($preview['save_response_as'] ?? null) && $preview['save_response_as'] !== '') {
                        $context['awaiting_field'] = $preview['save_response_as'];
                    }
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                }
                continue;
```

Agregar las 3 líneas **antes** del `continue`:

```php
                if (!empty($preview['send_result']) && is_array($preview['outbound_message'] ?? null)) {
                    $this->sendFlowMessage($conversation, $preview['outbound_message'], $context);
                    $messagesSent++;
                    if (is_string($preview['save_response_as'] ?? null) && $preview['save_response_as'] !== '') {
                        $context['awaiting_field'] = $preview['save_response_as'];
                    }
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                }
                if (($preview['operation'] ?? null) === 'check_pending_appointment'
                    && is_string($preview['next_state'] ?? null)
                    && $preview['next_state'] !== '') {
                    $context['state'] = $preview['next_state'];
                }
                continue;
```

- [ ] **Step 7: Verificar que no hay errores de sintaxis**

```bash
cd laravel-app && php artisan --version
php -l app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php
php -l app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Expected: `No syntax errors detected` en ambos.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php \
        app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): add check_pending_appointment operation to block duplicate bookings (Grupo D)"
```

---

### Task 2: Tests para check_pending_appointment

**Files:**
- Modify: `tests/Feature/WhatsappWebhookControllerTest.php`

> Contexto: Los tests de WhatsApp usan `RefreshDatabase`, crean todas las tablas en `setUp()` con `Schema::create`, y envían webhooks POST a `/whatsapp/webhook`. La tabla `procedimiento_proyectado` NO existe en el setUp actual — hay que agregarla. La tabla `whatsapp_sigcenter_bookings` ya existe en setUp.
>
> El `ProcedimientoProyectado` model tiene un global scope `sigcenter_present = true`. En los tests, las filas deben tener `sigcenter_present = 1` para que el scope no las filtre.

- [ ] **Step 1: Escribir el test que falla — cita encontrada en whatsapp_sigcenter_bookings**

En `WhatsappWebhookControllerTest.php`, agregar al final de la clase (antes del cierre `}`):

```php
public function test_check_pending_appointment_blocks_when_wa_booking_exists(): void
{
    $this->publishFlowmakerScenarios([[
        'id'         => 'check_cita',
        'name'       => 'Check cita activa',
        'stage'      => 'custom',
        'status'     => 'published',
        'conditions' => [
            ['type' => 'message_contains', 'value' => 'check_cita_test_wa'],
        ],
        'actions' => [[
            'type'                  => 'sigcenter_agenda',
            'operation'             => 'check_pending_appointment',
            'found_message'         => 'Ya tienes cita el {{fecha}} a las {{hora}} con {{medico}} en {{sede}}.',
            'found_next_state'      => 'menu_principal',
            'not_found_next_state'  => 'agenda_esperando_subespecialidad',
        ]],
    ]]);

    $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
        'wa_number'    => '593900000001',
        'display_name' => 'Test',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    \DB::table('whatsapp_autoresponder_sessions')->insert([
        'conversation_id'      => $conversationId,
        'wa_number'            => '593900000001',
        'scenario_id'          => 'check_cita',
        'context'              => json_encode(['state' => 'menu_principal', 'cedula' => 'HC-001']),
        'last_payload'         => json_encode([]),
        'last_interaction_at'  => now(),
        'session_version'      => 1,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    \DB::table('whatsapp_sigcenter_bookings')->insert([
        'wa_number'          => '593900000001',
        'patient_hc_number'  => 'HC-001',
        'status'             => 'created',
        'medico_nombre'      => 'Dr. García',
        'sede_nombre'        => 'Villa Club',
        'fecha_inicio'       => now()->addDays(3)->setTime(10, 30),
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    $this->postJson('/whatsapp/webhook', [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from'      => '593900000001',
                        'id'        => 'wamid.check-cita-wa',
                        'timestamp' => (string) now()->timestamp,
                        'type'      => 'text',
                        'text'      => ['body' => 'check_cita_test_wa'],
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    $session = \DB::table('whatsapp_autoresponder_sessions')
        ->where('wa_number', '593900000001')->first();
    $ctx = json_decode((string) $session?->context, true);

    $this->assertSame('menu_principal', $ctx['state'] ?? null);

    $this->assertDatabaseHas('whatsapp_messages', [
        'direction' => 'outbound',
        'wa_number' => '593900000001',
    ]);

    $msg = \DB::table('whatsapp_messages')
        ->where('wa_number', '593900000001')
        ->where('direction', 'outbound')
        ->first();
    $this->assertStringContainsString('Dr. García', (string) $msg?->body);
    $this->assertStringContainsString('Villa Club', (string) $msg?->body);
}
```

- [ ] **Step 2: Ejecutar el test — verificar que falla**

```bash
php artisan test --filter=test_check_pending_appointment_blocks_when_wa_booking_exists
```

Expected: FAIL (el método `executeCheckPendingAppointment` no existe aún).

- [ ] **Step 3: Escribir test — cita encontrada en procedimiento_proyectado**

Agregar también la tabla `procedimiento_proyectado` en el `setUp()`. Localizar el bloque `Schema::create('whatsapp_sigcenter_bookings', ...)` en `setUp()` y agregar DESPUÉS de él:

```php
Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
    $table->id();
    $table->unsignedInteger('form_id')->default(0);
    $table->string('procedimiento_proyectado', 191);
    $table->string('doctor', 191)->nullable();
    $table->string('hc_number', 64)->index();
    $table->string('sede_departamento', 191)->nullable();
    $table->integer('id_sede')->nullable();
    $table->string('estado_agenda', 64)->nullable();
    $table->string('afiliacion', 64)->nullable();
    $table->date('fecha')->nullable();
    $table->time('hora')->nullable();
    $table->boolean('sigcenter_present')->default(true);
    $table->timestamp('sigcenter_last_seen_at')->nullable();
    $table->timestamp('sigcenter_missing_at')->nullable();
    $table->integer('visita_id')->nullable();
    $table->timestamps();
});
```

Y en `tearDown()` (o al inicio del `setUp()` donde se hace `dropIfExists`), agregar:

```php
Schema::dropIfExists('procedimiento_proyectado');
```

Agregar el test:

```php
public function test_check_pending_appointment_blocks_when_projected_appointment_exists(): void
{
    $this->publishFlowmakerScenarios([[
        'id'         => 'check_cita_pp',
        'name'       => 'Check cita proyectada',
        'stage'      => 'custom',
        'status'     => 'published',
        'conditions' => [
            ['type' => 'message_contains', 'value' => 'check_cita_test_pp'],
        ],
        'actions' => [[
            'type'                  => 'sigcenter_agenda',
            'operation'             => 'check_pending_appointment',
            'found_message'         => 'Tienes cita el {{fecha}} con {{medico}}.',
            'found_next_state'      => 'menu_principal',
            'not_found_next_state'  => 'agenda_esperando_subespecialidad',
        ]],
    ]]);

    $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
        'wa_number'    => '593900000002',
        'display_name' => 'Test PP',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    \DB::table('whatsapp_autoresponder_sessions')->insert([
        'conversation_id'      => $conversationId,
        'wa_number'            => '593900000002',
        'scenario_id'          => 'check_cita_pp',
        'context'              => json_encode(['state' => 'menu_principal', 'cedula' => 'HC-002']),
        'last_payload'         => json_encode([]),
        'last_interaction_at'  => now(),
        'session_version'      => 1,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    \DB::table('procedimiento_proyectado')->insert([
        'form_id'                  => 1,
        'procedimiento_proyectado' => 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-001',
        'doctor'                   => 'Dra. Mora',
        'hc_number'                => 'HC-002',
        'sede_departamento'        => 'Sede Norte',
        'fecha'                    => now()->addDays(2)->toDateString(),
        'hora'                     => '09:00:00',
        'estado_agenda'            => null,
        'sigcenter_present'        => true,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $this->postJson('/whatsapp/webhook', [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from'      => '593900000002',
                        'id'        => 'wamid.check-cita-pp',
                        'timestamp' => (string) now()->timestamp,
                        'type'      => 'text',
                        'text'      => ['body' => 'check_cita_test_pp'],
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    $session = \DB::table('whatsapp_autoresponder_sessions')
        ->where('wa_number', '593900000002')->first();
    $ctx = json_decode((string) $session?->context, true);

    $this->assertSame('menu_principal', $ctx['state'] ?? null);

    $msg = \DB::table('whatsapp_messages')
        ->where('wa_number', '593900000002')
        ->where('direction', 'outbound')
        ->first();
    $this->assertStringContainsString('Dra. Mora', (string) $msg?->body);
}
```

- [ ] **Step 4: Escribir test — sin cita activa → transiciona silenciosamente**

```php
public function test_check_pending_appointment_passes_through_when_no_active_booking(): void
{
    $this->publishFlowmakerScenarios([[
        'id'         => 'check_libre',
        'name'       => 'Sin cita activa',
        'stage'      => 'custom',
        'status'     => 'published',
        'conditions' => [
            ['type' => 'message_contains', 'value' => 'check_libre_test'],
        ],
        'actions' => [[
            'type'                  => 'sigcenter_agenda',
            'operation'             => 'check_pending_appointment',
            'found_message'         => 'Ya tienes cita.',
            'found_next_state'      => 'menu_principal',
            'not_found_next_state'  => 'agenda_esperando_subespecialidad',
        ]],
    ]]);

    $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
        'wa_number'    => '593900000003',
        'display_name' => 'Sin cita',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    \DB::table('whatsapp_autoresponder_sessions')->insert([
        'conversation_id'      => $conversationId,
        'wa_number'            => '593900000003',
        'scenario_id'          => 'check_libre',
        'context'              => json_encode(['state' => 'menu_principal', 'cedula' => 'HC-003']),
        'last_payload'         => json_encode([]),
        'last_interaction_at'  => now(),
        'session_version'      => 1,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    $this->postJson('/whatsapp/webhook', [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from'      => '593900000003',
                        'id'        => 'wamid.check-libre',
                        'timestamp' => (string) now()->timestamp,
                        'type'      => 'text',
                        'text'      => ['body' => 'check_libre_test'],
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    $session = \DB::table('whatsapp_autoresponder_sessions')
        ->where('wa_number', '593900000003')->first();
    $ctx = json_decode((string) $session?->context, true);

    $this->assertSame('agenda_esperando_subespecialidad', $ctx['state'] ?? null);

    $this->assertDatabaseMissing('whatsapp_messages', [
        'wa_number'  => '593900000003',
        'direction'  => 'outbound',
    ]);
}
```

- [ ] **Step 5: Escribir test — sin hc_number en contexto → pasa a not_found_next_state**

```php
public function test_check_pending_appointment_skips_when_no_hc_number_in_context(): void
{
    $this->publishFlowmakerScenarios([[
        'id'         => 'check_sin_hc',
        'name'       => 'Sin HC',
        'stage'      => 'custom',
        'status'     => 'published',
        'conditions' => [
            ['type' => 'message_contains', 'value' => 'check_sin_hc_test'],
        ],
        'actions' => [[
            'type'                  => 'sigcenter_agenda',
            'operation'             => 'check_pending_appointment',
            'found_message'         => 'Ya tienes cita.',
            'found_next_state'      => 'menu_principal',
            'not_found_next_state'  => 'agenda_esperando_subespecialidad',
        ]],
    ]]);

    $conversationId = \DB::table('whatsapp_conversations')->insertGetId([
        'wa_number'    => '593900000004',
        'display_name' => 'Sin HC',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    \DB::table('whatsapp_autoresponder_sessions')->insert([
        'conversation_id'      => $conversationId,
        'wa_number'            => '593900000004',
        'scenario_id'          => 'check_sin_hc',
        'context'              => json_encode(['state' => 'menu_principal']),
        'last_payload'         => json_encode([]),
        'last_interaction_at'  => now(),
        'session_version'      => 1,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    $this->postJson('/whatsapp/webhook', [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from'      => '593900000004',
                        'id'        => 'wamid.check-sin-hc',
                        'timestamp' => (string) now()->timestamp,
                        'type'      => 'text',
                        'text'      => ['body' => 'check_sin_hc_test'],
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    $session = \DB::table('whatsapp_autoresponder_sessions')
        ->where('wa_number', '593900000004')->first();
    $ctx = json_decode((string) $session?->context, true);

    $this->assertSame('agenda_esperando_subespecialidad', $ctx['state'] ?? null);

    $this->assertDatabaseMissing('whatsapp_messages', [
        'wa_number'  => '593900000004',
        'direction'  => 'outbound',
    ]);
}
```

- [ ] **Step 6: Ejecutar todos los tests nuevos para verificar que fallan correctamente**

```bash
php artisan test --filter="test_check_pending_appointment"
```

Expected: 4 tests FAIL (implementación pendiente de Task 1).

- [ ] **Step 7: Implementar Task 1 (steps 1–6 de Task 1) y volver a correr los tests**

```bash
php artisan test --filter="test_check_pending_appointment"
```

Expected: 4 tests PASS.

- [ ] **Step 8: Correr el suite completo de WhatsappWebhookControllerTest**

```bash
php artisan test --filter=WhatsappWebhookControllerTest
```

Expected: misma cantidad de tests que antes + 4 nuevos, sin regresiones en los existentes (2 fallas pre-existentes no relacionadas).

- [ ] **Step 9: Commit**

```bash
git add tests/Feature/WhatsappWebhookControllerTest.php
git commit -m "test(whatsapp): add 4 tests for check_pending_appointment — WA booking, proyectado, no-cita, no-hc"
```

---

### Task 3: Verificación final + snippet de configuración para el Flowmaker

**Files:**
- Ninguno (solo verificación + documentación para el operador)

- [ ] **Step 1: Correr el suite completo**

```bash
php artisan test --testsuite=Feature --filter=Whatsapp
```

Verificar que los 4 tests nuevos pasan y no hay nuevas regresiones.

- [ ] **Step 2: Verificar que `updateOrCreate` no fue introducido**

```bash
grep -n "updateOrCreate" app/Modules/Whatsapp/Services/FlowSigcenterAgendaService.php
```

Expected: sin resultados (no introducimos updateOrCreate).

- [ ] **Step 3: Verificar que WebhookService no fue tocado**

```bash
git diff HEAD app/Modules/Whatsapp/Services/WebhookService.php
```

Expected: sin diferencias.

- [ ] **Step 4: Snippet JSON para configurar el Flowmaker post-deploy**

El operador debe agregar este nodo en el Flowmaker UI **ANTES** del nodo `list_specialties` en el escenario de agendamiento:

```json
{
  "type": "sigcenter_agenda",
  "operation": "check_pending_appointment",
  "found_message": "Ya tienes una cita agendada:\n\n📅 *Fecha:* {{fecha}}\n🕒 *Horario:* {{hora}}\n👨‍⚕️ *Médico:* {{medico}}\n📍 *Sede:* {{sede}}\n\nSi necesitas cambiarla, escríbenos o comunícate con nosotros.",
  "found_next_state": "menu_principal",
  "not_found_next_state": "agenda_esperando_subespecialidad"
}
```

Ajustar `found_next_state` y `not_found_next_state` según los estados del escenario actual.

- [ ] **Step 5: Commit final y PR**

```bash
git add -p  # revisar que no hay archivos extra
git commit -m "chore(whatsapp): add Grupo D flowmaker config snippet in plan"
```

```bash
git push -u origin <rama-actual>
gh pr create \
  --title "feat(whatsapp): check_pending_appointment — bloquea re-agendamiento si ya hay cita activa (Grupo D)" \
  --body "$(cat <<'EOF'
## Summary

- Nueva operación \`check_pending_appointment\` en \`FlowSigcenterAgendaService\`: consulta primero \`whatsapp_sigcenter_bookings\` (citas creadas por WA) y luego \`procedimiento_proyectado\` (tipo SER-OFT, próximos 7 días, no canceladas). Si encuentra cita, envía mensaje configurable con {{fecha}}/{{hora}}/{{medico}}/{{sede}} y redirige al estado configurado. Si no encuentra, transiciona silenciosamente al primer paso de agenda.
- Fix mínimo en \`FlowRuntimeExecutionService\`: 3 líneas para aplicar \`next_state\` de \`check_pending_appointment\` cuando no hay outbound message (caso no-encontrado).
- 4 tests nuevos cubriendo: WA booking bloqueante, proyectado bloqueante, sin cita (pasa), sin hc_number (pasa).

## Pendiente post-merge (producción)

- [ ] Abrir Flowmaker → insertar nodo \`check_pending_appointment\` antes de \`list_specialties\` en el escenario de agendamiento
- [ ] Publicar el flow

## Test Plan

- [x] 4 tests nuevos pasan
- [x] Sin regresiones en WhatsappWebhookControllerTest
- [x] WebhookService sin tocar
- [x] Sin migraciones nuevas

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)" \
  --base main
```
