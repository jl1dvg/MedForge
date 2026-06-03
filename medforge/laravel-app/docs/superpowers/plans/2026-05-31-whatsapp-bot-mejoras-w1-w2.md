# WhatsApp Bot — Mejoras Wave 1 + Wave 2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir dos bugs críticos de flujo y mejorar la UX interactiva del bot de WhatsApp de CIVE con mensajes de lista, botones en confirmación de cita, feedback de procesamiento, detección de frustración y nudge contextual de re-engagement.

**Architecture:** Todos los cambios viven en los servicios de `app/Modules/Whatsapp/Services/`. Los cambios son aditivos: nuevos métodos privados + modificaciones quirúrgicas a métodos existentes. Sin nuevas tablas ni migraciones.

**Tech Stack:** PHP 8.3, Laravel 10, WhatsApp Cloud API (Meta), Sigcenter API (CIVE)

**Deploy:** SSH a `u98115706@access793096920.webspace-data.io`. Ruta producción: `/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/`. El cron corre `schedule:run` cada minuto — los cambios en PHP entran en vigor en la siguiente ejecución automáticamente.

---

## Mapa de archivos

| Archivo | Cambios |
|---|---|
| `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` | W1-1, W1-2, W2-2, W2-3, W2-4, W2-5a |
| `app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php` | W2-5b |

---

## Task 1 (W1-1): Guard `awaiting_field` en cortesía

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php:291`

- [ ] **Step 1: Localizar la línea exacta en el servidor**

```bash
grep -n 'isCourtesyMessage' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Buscar la línea que contiene `if ($this->isCourtesyMessage($text)) {` (actualmente ~línea 291).

- [ ] **Step 2: Aplicar el fix**

```bash
python3 - << 'EOF'
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = "        if ($this->isCourtesyMessage($text)) {"
new = "        if (empty($context['awaiting_field']) && $this->isCourtesyMessage($text)) {"

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK")
EOF
```

- [ ] **Step 3: Verificar el cambio**

```bash
grep -n 'isCourtesyMessage' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Output esperado: la línea debe contener `empty($context['awaiting_field']) &&`.

- [ ] **Step 4: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "fix(whatsapp): courtesy reply only when no awaiting_field active

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 2 (W1-2): Retry count con escalado al 3er intento inválido

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (métodos `captureAwaitingInput` ~línea 639 y `shouldRetryCedula` ~línea 1597)

El mecanismo: `$context['input_retry_{field}']` lleva el conteo. Se incrementa en el método de retry del campo. Al llegar a 3 → handoff automático.

- [ ] **Step 1: Modificar `shouldRetryCedula` para incrementar y escalar**

Línea actual ~1597:
```php
private function shouldRetryCedula(array $facts): bool
```

Reemplazar el interior del método (manteniendo la firma) para añadir el conteo. El método actualmente retorna `bool`. Vamos a hacerlo retornar `bool` pero con side-effect de escalar en el contexto — mejor: crear un método wrapper que recibe y devuelve contexto.

Aplicar via Python3 en el servidor:

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

# Añadir método nuevo antes de shouldRetryCedula
old = "    private function shouldRetryCedula(array $facts): bool"
new = """    private function incrementInputRetry(array &$context, string $field): int
    {
        $key = 'input_retry_' . $field;
        $context[$key] = (int) ($context[$key] ?? 0) + 1;
        return $context[$key];
    }

    private function resetInputRetry(array &$context, string $field): void
    {
        unset($context['input_retry_' . $field]);
    }

    private function shouldRetryCedula(array $facts): bool"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: incrementInputRetry + resetInputRetry added")
```

- [ ] **Step 2: Usar el contador en el bloque de cedula retry**

Buscar el bloque donde se llama a `shouldRetryCedula` (línea ~354 en `recoverNoMatchFlow`) y añadir el conteo:

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """        if ($this->shouldRetryCedula($facts)) {
            $this->sendFlowMessage($conversation, $this->cedulaRetryMessage(), $context);
            $context['state'] = 'esperando_cedula';
            $context['awaiting_field'] = 'cedula';"""

new = """        if ($this->shouldRetryCedula($facts)) {
            $retryCount = $this->incrementInputRetry($context, 'cedula');
            if ($retryCount >= 3) {
                $this->resetInputRetry($context, 'cedula');
                $this->sendFlowMessage($conversation, [
                    'type' => 'text',
                    'body' => 'No pudimos verificar tu información. Un asesor te contactará para ayudarte. 🙏',
                ], $context);
                $context['handoff_requested'] = true;
                $context['handoff_topic'] = 'cedula_no_reconocida';
                $context['handoff_note'] = 'Paciente no pudo ingresar cédula válida tras 3 intentos.';
                $context['state'] = 'handoff_cedula';
                unset($context['awaiting_field']);
                return $this->result(true, true, 'cedula_max_retries', 1, true, null);
            }
            $this->sendFlowMessage($conversation, $this->cedulaRetryMessage(), $context);
            $context['state'] = 'esperando_cedula';
            $context['awaiting_field'] = 'cedula';"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: cedula retry escalation added")
```

- [ ] **Step 3: Resetear el contador cuando la cédula es capturada exitosamente**

En `captureAwaitingInput` (~línea 639), después de `unset($context['awaiting_field'])`:

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """        if (in_array($field, ['cedula', 'identificacion', 'identifier', 'hc_number'], true)) {
            $context['cedula'] = $this->normalizeIdentifier($value);
            $context['identifier'] = $context['cedula'];
            $context['current_identifier'] = $context['cedula'];
        }"""

new = """        if (in_array($field, ['cedula', 'identificacion', 'identifier', 'hc_number'], true)) {
            $context['cedula'] = $this->normalizeIdentifier($value);
            $context['identifier'] = $context['cedula'];
            $context['current_identifier'] = $context['cedula'];
            $this->resetInputRetry($context, 'cedula');
        }"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: retry reset on cedula capture")
```

- [ ] **Step 4: Verificar los 3 cambios**

```bash
grep -n 'incrementInputRetry\|resetInputRetry\|cedula_max_retries' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Debe aparecer en al menos 4 líneas.

- [ ] **Step 5: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): escalate to human after 3 invalid cedula attempts

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 3 (W2-3): Mensaje "procesando..." antes de consultas Sigcenter lentas

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (~línea 793, bloque `sigcenter_agenda`)

Las operaciones lentas (>2s) son: `list_times`, `list_days`, `book_appointment`, `cancel_appointment`, `list_doctors_by_name`.

- [ ] **Step 1: Añadir el mensaje de espera antes de `$this->sigcenterAgendaService->execute()`**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """                $preview = $this->sigcenterAgendaService->execute($action, $context, [
                    'wa_number' => $conversation->wa_number,
                    'text' => $text,
                    'conversation_id' => $conversation->id,
                    'current_identifier' => $context['current_identifier'] ?? $conversation->patient_hc_number,
                    'cedula' => $context['cedula'] ?? $conversation->patient_hc_number,
                ], $this->bookingIsConfirmed($action, $context, $text));"""

new = """                $slowOperations = ['list_times', 'list_days', 'book_appointment', 'cancel_appointment', 'list_doctors_by_name'];
                $currentOperation = $this->normalizeSigcenterOperation((string)($action['operation'] ?? ''));
                if (in_array($currentOperation, $slowOperations, true)) {
                    $waitingMessages = [
                        'book_appointment' => '📋 Confirmando tu cita...',
                        'cancel_appointment' => '⚙️ Procesando la cancelación...',
                        'list_times' => '🔍 Buscando horarios disponibles...',
                        'list_days' => '🔍 Consultando fechas disponibles...',
                        'list_doctors_by_name' => '🔍 Buscando al médico...',
                    ];
                    $this->sendFlowMessage($conversation, [
                        'type' => 'text',
                        'body' => $waitingMessages[$currentOperation] ?? '🔍 Consultando disponibilidad...',
                    ], $context);
                }

                $preview = $this->sigcenterAgendaService->execute($action, $context, [
                    'wa_number' => $conversation->wa_number,
                    'text' => $text,
                    'conversation_id' => $conversation->id,
                    'current_identifier' => $context['current_identifier'] ?? $conversation->patient_hc_number,
                    'cedula' => $context['cedula'] ?? $conversation->patient_hc_number,
                ], $this->bookingIsConfirmed($action, $context, $text));"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: waiting message before slow Sigcenter operations")
```

- [ ] **Step 2: Verificar**

```bash
grep -n 'slowOperations\|Consultando disponibilidad\|Buscando horarios' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

- [ ] **Step 3: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): send processing feedback before slow Sigcenter calls

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 4 (W2-4): Detección de frustración

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`

- [ ] **Step 1: Añadir método `isFrustrationSignal()`**

Insertar antes de `isCourtesyMessage` (línea ~1296):

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = "    private function isCourtesyMessage(string $text): bool"
new = """    private function isFrustrationSignal(string $text): int
    {
        // Returns 0 = no frustration, 1 = mild (offer menu+help), 2 = explicit (handoff immediately)
        $normalized = mb_strtolower(trim($text));

        $explicitFrustration = ['no funciona', 'esto no sirve', 'que malo', 'qué malo', 'pesimo', 'pésimo',
            'terrible', 'no me ayuda', 'no sirve', 'inutil', 'inútil', 'horrible'];
        foreach ($explicitFrustration as $p) {
            if (str_contains($normalized, $p)) {
                return 2;
            }
        }

        $mildFrustration = ['???', '??', 'no entiendo', 'no comprendo', 'ayuda urgente',
            'no puedo', 'como funciona', 'no sé', 'no se', 'que hago', 'qué hago'];
        // bare "?" or "??" counts as mild
        if (preg_match('/^\?{1,3}$/', $normalized)) {
            return 1;
        }
        foreach ($mildFrustration as $p) {
            if (str_contains($normalized, $p)) {
                return 1;
            }
        }

        return 0;
    }

    private function isCourtesyMessage(string $text): bool"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: isFrustrationSignal added")
```

- [ ] **Step 2: Añadir check de frustración antes del fallback**

El check de cortesía ya está en línea ~291. Añadir el check de frustración justo después de él:

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """        if (empty($context['awaiting_field']) && $this->isCourtesyMessage($text)) {
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => '¡Con gusto! 😊 Si necesitas algo más, escribe *MENU* y te ayudo.',
            ], $context);
            return $this->result(true, true, 'courtesy_reply', 1, false, null);
        }

        $fallbackBody"""

new = """        if (empty($context['awaiting_field']) && $this->isCourtesyMessage($text)) {
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => '¡Con gusto! 😊 Si necesitas algo más, escribe *MENU* y te ayudo.',
            ], $context);
            return $this->result(true, true, 'courtesy_reply', 1, false, null);
        }

        $frustrationLevel = $this->isFrustrationSignal($text);
        if ($frustrationLevel === 2) {
            $context['handoff_requested'] = true;
            $context['handoff_topic'] = 'frustracion_explicita';
            $context['handoff_note'] = 'Paciente expresó frustración explícita: "' . mb_substr($text, 0, 80) . '"';
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => 'Lamentamos tu experiencia. Un asesor te atenderá de inmediato. 🙏',
            ], $context);
            $this->saveSession($conversation, (string)$conversation->wa_number, 'frustration_handoff',
                null, null, $context, $messagePayload);
            $this->markConversationForHandoff($conversation, [], $context);
            return $this->result(true, true, 'frustration_handoff', 1, true, null);
        }
        if ($frustrationLevel === 1) {
            $this->sendFlowMessage($conversation, [
                'type' => 'buttons',
                'body' => 'Disculpa la confusión 🙏 ¿Cómo te podemos ayudar?',
                'buttons' => [
                    ['id' => 'agendar', 'title' => '📅 Agendar cita'],
                    ['id' => 'consultar_cita', 'title' => '🔍 Ver mi cita'],
                    ['id' => 'ayuda', 'title' => '🙋 Hablar con asesor'],
                ],
            ], $context);
            return $this->result(true, true, 'frustration_mild', 1, false, null);
        }

        $fallbackBody"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: frustration handler added before fallback")
```

- [ ] **Step 3: Verificar**

```bash
grep -n 'isFrustrationSignal\|frustration_handoff\|frustration_mild' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

Debe aparecer en al menos 5 líneas.

- [ ] **Step 4: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): detect frustration signals, escalate level-2 to human

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 5 (W2-1): Añadir opciones faltantes al menú principal

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (~línea 2093, método `mainMenuRows`)

**Nota:** El menú YA es un `list_message`. Esta tarea añade las opciones "Precios y convenios" y "Especialidades" que faltaban en el diseño acordado, y mejora las descripciones.

- [ ] **Step 1: Ampliar el catálogo del menú**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """        $catalog = [
            ['id' => 'agendar', 'title' => '📅 Agendar cita', 'description' => 'Programa una nueva cita médica', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_agendar_enabled', true)],
            ['id' => 'consultar_cita', 'title' => '📄 Consultar cita', 'description' => 'Revisa tu cita vigente', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_consultar_cita_enabled', true)],
            ['id' => 'servicios_y_sedes', 'title' => '📍 Servicios y sedes', 'description' => 'Sedes, horarios y especialidades', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_servicios_sedes_enabled', true)],
            ['id' => 'promociones', 'title' => '🎁 Promociones', 'description' => 'Consulta campañas vigentes', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_promociones_enabled', true)],
            ['id' => 'ayuda', 'title' => '🆘 Ayuda', 'description' => 'Hablar con un asesor', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_ayuda_enabled', true)],
        ];"""

new = """        $catalog = [
            ['id' => 'agendar', 'title' => '📅 Agendar cita', 'description' => 'Consultas, cirugías y especialistas', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_agendar_enabled', true)],
            ['id' => 'consultar_cita', 'title' => '🔍 Ver mi cita', 'description' => 'Consultar o cancelar cita vigente', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_consultar_cita_enabled', true)],
            ['id' => 'servicios_y_sedes', 'title' => '📍 Sedes y horarios', 'description' => 'Dónde atendemos y en qué horario', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_servicios_sedes_enabled', true)],
            ['id' => 'especialidades', 'title' => '🩺 Especialidades', 'description' => 'Qué tratamos en CIVE', 'enabled' => true],
            ['id' => 'precios_convenios', 'title' => '💰 Precios y convenios', 'description' => 'Tarifas, seguros y convenios', 'enabled' => true],
            ['id' => 'promociones', 'title' => '🎁 Promociones', 'description' => 'Campañas y descuentos vigentes', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_promociones_enabled', true)],
            ['id' => 'ayuda', 'title' => '🙋 Hablar con asesor', 'description' => 'Atención personalizada', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_ayuda_enabled', true)],
        ];"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: menu items updated")
```

- [ ] **Step 2: Asegurarse que `especialidades` y `precios_convenios` tienen handlers en el flowmaker**

Verificar en la tabla `flows` que los escenarios con `id` matching `especialidades` y `precios_convenios` existen, o que `isBotReactivationCommand` / el flowmaker los rutea. Si no existen, el bot caerá al fallback con un mensaje informativo — aceptable por ahora.

```bash
grep -rn 'especialidades\|precios_convenios' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php | head -10
```

- [ ] **Step 3: Verificar**

```bash
grep -n 'Especialidades\|precios_convenios\|Precios y convenios' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
```

- [ ] **Step 4: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): add especialidades + precios to main menu, improve descriptions

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 6 (W2-2): Confirmación de cita con botones + mensaje de éxito mejorado

**Files:**
- Modify: `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php` (líneas ~835 y ~1025)

**Parte A:** Cuando `book_appointment` no está confirmado aún, en vez de `bookingFailureMessage` mostrar 3 botones.  
**Parte B:** Modificar `bookingSuccessMessage()` para agregar botones de siguiente acción.  
**Parte C:** Manejar el botón "Cambiar horario" para retroceder al estado de selección de horario.

- [ ] **Step 1 (Parte A): Reemplazar error de no-confirmación con mensaje de 3 botones**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """                if (($preview['operation'] ?? null) === 'book_appointment') {
                    $context = $this->recordSigcenterBooking($preview, $context, $conversation, $inboundMessage);
                    $message = !empty($preview['ok'])
                        ? $this->bookingSuccessMessage()
                        : $this->bookingFailureMessage((string)($preview['error'] ?? ''));
                    $this->sendFlowMessage($conversation, $message, $context);
                    $messagesSent++;"""

new = """                if (($preview['operation'] ?? null) === 'book_appointment') {
                    if (!empty($preview['ok'])) {
                        $context = $this->recordSigcenterBooking($preview, $context, $conversation, $inboundMessage);
                        $this->sendFlowMessage($conversation, $this->bookingSuccessMessage($context), $context);
                        $messagesSent++;
                    } elseif (str_contains((string)($preview['error'] ?? ''), 'Confirmación requerida')) {
                        $this->sendFlowMessage($conversation, $this->bookingPreConfirmationMessage($context), $context);
                        $messagesSent++;
                        $context['state'] = 'agenda_confirmar_cita';
                    } else {
                        $this->sendFlowMessage($conversation, $this->bookingFailureMessage((string)($preview['error'] ?? '')), $context);
                        $messagesSent++;
                    }"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: pre-confirmation 3-button message")
```

- [ ] **Step 2 (Parte A): Añadir método `bookingPreConfirmationMessage()`**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = "    private function bookingSuccessMessage(): array"
new = """    private function bookingPreConfirmationMessage(array $context): array
    {
        $parts = [];
        foreach ([
            'sede_id_label'         => '🏥 Sede',
            'trabajador_id_label'   => '👨‍⚕️ Médico',
            'fecha_inicio'          => '🗓️ Fecha',
            'horario_texto'         => '🕙 Hora',
            'subespecialidad_label' => '🔬 Especialidad',
        ] as $key => $label) {
            $value = trim((string)($context[$key] ?? ''));
            if ($value !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }

        $summary = $parts !== [] ? "\n\n" . implode("\n", $parts) : '';

        return [
            'type' => 'buttons',
            'body' => "📋 *Resumen de tu cita*{$summary}\n\n¿Confirmamos el agendamiento?",
            'buttons' => [
                ['id' => 'confirmar_cita',   'title' => '✅ Confirmar'],
                ['id' => 'cambiar_horario',  'title' => '🔄 Cambiar horario'],
                ['id' => 'cancelar_agenda',  'title' => '❌ Cancelar'],
            ],
        ];
    }

    private function bookingSuccessMessage(): array"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: bookingPreConfirmationMessage added")
```

- [ ] **Step 3 (Parte B): Mejorar `bookingSuccessMessage()` con botones de siguiente acción**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = """    private function bookingSuccessMessage(): array
    {
        return [
            'type' => 'text',
            'body' => "✅ *Tu cita ha sido agendada exitosamente.*\\n\\n📅 *Fecha:* {{fecha}}\\n🕒 *Horario:* {{fecha_inicio}}\\n📍 *Sede:* {{sede_id}}\\n🩺 *Procedimiento:* {{procedimiento_id}}\\n\\n*Recomendaciones:*\\n▪️ Uso obligatorio de mascarilla\\n▪️ Estar 10 minutos antes de la hora de su cita\\n▪️ Traer documento de identidad del paciente (cédula o pasaporte)\\n▪️ Venir solo con un acompañante\\n▪️ Es probable que dilaten su pupila, por lo que se recomienda no conducir\\n\\n🙌 *Te esperamos.*",
        ];
    }"""

new = """    private function bookingSuccessMessage(array $context = []): array
    {
        $parts = [];
        foreach ([
            'sede_id_label'         => '🏥 Sede',
            'trabajador_id_label'   => '👨‍⚕️ Médico',
            'fecha_inicio'          => '🗓️ Fecha',
            'horario_texto'         => '🕙 Hora',
            'subespecialidad_label' => '🔬 Especialidad',
        ] as $key => $label) {
            $value = trim((string)($context[$key] ?? ''));
            if ($value !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }
        $summary = $parts !== [] ? "\n" . implode("\n", $parts) : '';

        return [
            'type' => 'buttons',
            'body' => "✅ *¡Cita agendada exitosamente!*{$summary}\n\n*Recomendaciones:*\n▪️ Estar 10 min antes\n▪️ Traer cédula o pasaporte\n▪️ Mascarilla obligatoria\n▪️ Venir con máximo un acompañante\n\n🙌 *¡Te esperamos!*\n\n_Te enviaremos un recordatorio 24h antes._",
            'buttons' => [
                ['id' => 'agendar',       'title' => '📅 Agendar otra cita'],
                ['id' => 'menu_principal', 'title' => '🏠 Menú principal'],
            ],
        ];
    }"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: bookingSuccessMessage returns buttons")
```

- [ ] **Step 4 (Parte C): Manejar el botón "Cambiar horario" en `executeInbound`**

El botón envía `id = cambiar_horario` como reply interactivo. Añadir el handler antes del dispatch de escenarios (cerca de `isBookingChangeRequest`, línea ~220):

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php"
with open(path) as f:
    content = f.read()

old = "        if ($this->isBookingChangeRequest($text)) {"
new = """        // Botón "Cambiar horario" desde la pantalla de pre-confirmación
        if ($this->normalizeText($text) === 'cambiar horario' || trim($text) === 'cambiar_horario') {
            $context['state'] = 'agenda_esperando_horario';
            unset($context['awaiting_field'], $context['horario_texto'], $context['fecha_inicio_raw']);
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => '🔄 Sin problema. ¿Qué horario prefieres? Escribe la fecha o elige de las opciones disponibles.',
            ], $context);
            $this->saveSession($conversation, (string)$conversation->wa_number, 'cambiar_horario',
                null, null, $context, $messagePayload);
            return $this->result(true, true, 'cambiar_horario', 1, false, null);
        }

        if ($this->isBookingChangeRequest($text)) {"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: cambiar_horario button handler added")
```

- [ ] **Step 5: Verificar todos los cambios de esta task**

```bash
grep -n 'bookingPreConfirmationMessage\|bookingSuccessMessage\|cambiar_horario\|Cambiar horario\|confirmacion_requerida\|Confirmación requerida' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php | head -20
```

- [ ] **Step 6: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php
git commit -m "feat(whatsapp): booking confirmation with 3 buttons + success message with next actions

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 7 (W2-5): Nudge de re-engagement contextual

**Files:**
- Modify: `app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php`

**Lógica:** Si el estado de la sesión tiene prefijo `agenda_` o es uno de los estados de espera de agenda → nudge contextual con médico/sede + botones. Cualquier otro estado → texto plano.

- [ ] **Step 1: Reemplazar el nudge fijo por uno inteligente**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php"
with open(path) as f:
    content = f.read()

old = """                if ($nudgedAt === null || !$this->sessionHasNoNewInboundSince($session->last_interaction_at, $nudgedAt)) {
                    $this->dispatchService->sendSystemText(
                        $conversation,
                        (string) config(
                            'whatsapp.migration.abandonment_monitor.nudge_message',
                            '😔 Parece que se interrumpió tu proceso. Si aún deseas continuar con tu cita, responde este mensaje y con gusto te ayudo.'
                        )
                    );"""

new = """                if ($nudgedAt === null || !$this->sessionHasNoNewInboundSince($session->last_interaction_at, $nudgedAt)) {
                    $nudgeMessage = $this->buildNudgeMessage($state, $context);
                    if (is_array($nudgeMessage)) {
                        $this->dispatchService->sendSystemMessage($conversation, $nudgeMessage);
                    } else {
                        $this->dispatchService->sendSystemText($conversation, $nudgeMessage);
                    }"""

if old not in content:
    print("ERROR: pattern not found"); exit(1)
content = content.replace(old, new, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: nudge dispatch updated")
```

- [ ] **Step 2: Añadir método `buildNudgeMessage()` al final de la clase (antes del último `}`)**

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php"
with open(path) as f:
    content = f.read()

# Insert before the last closing brace of the class
insertion = """
    /**
     * Builds a contextual or plain nudge message depending on session state.
     * Agenda states get buttons + context; other states get plain text.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string
     */
    private function buildNudgeMessage(string $state, array $context): array|string
    {
        $agendaStates = [
            'agenda_esperando_sede', 'agenda_esperando_subespecialidad', 'agenda_esperando_medico',
            'esperando_nombre_doctor', 'agenda_esperando_doctor_directo', 'agenda_esperando_sede_directa',
            'agenda_esperando_fecha_general', 'agenda_esperando_medico_general_por_fecha',
            'agenda_esperando_horario_general_fecha', 'agenda_confirmar_cita_fecha_general',
            'agenda_esperando_procedimiento', 'agenda_esperando_dia', 'agenda_esperando_horario',
            'agenda_confirmar_cita', 'agenda_filtro_sector', 'agenda_esperando_sede_inicio',
            'menu_agendar_modo',
        ];

        $isAgendaState = str_starts_with($state, 'agenda_') || in_array($state, $agendaStates, true);

        if (!$isAgendaState) {
            return (string) config(
                'whatsapp.migration.abandonment_monitor.nudge_message',
                '⏳ Parece que se interrumpió tu proceso. Si aún deseas continuar, responde este mensaje y con gusto te ayudo.'
            );
        }

        // Build contextual message for agenda state
        $parts = [];
        $medico = trim((string)($context['trabajador_id_label'] ?? $context['medico_nombre'] ?? ''));
        $sede   = trim((string)($context['sede_id_label'] ?? $context['sede_nombre'] ?? ''));

        if ($medico !== '') {
            $parts[] = "el *{$medico}*";
        }
        if ($sede !== '') {
            $parts[] = "en *{$sede}*";
        }

        $contextLine = $parts !== []
            ? 'Estabas eligiendo un horario con ' . implode(' ', $parts) . '.'
            : 'Estabas a punto de agendar una cita.';

        return [
            'type' => 'buttons',
            'body' => "⏳ ¡Hola! {$contextLine}\n\n¿Continuamos?",
            'buttons' => [
                ['id' => 'agendar',       'title' => '✅ Sí, continuar'],
                ['id' => 'menu_principal', 'title' => '🔄 Empezar de nuevo'],
            ],
        ];
    }

"""

# Insert before final class closing brace
last_brace = content.rfind("\n}")
if last_brace == -1:
    print("ERROR: closing brace not found"); exit(1)
content = content[:last_brace] + insertion + content[last_brace:]
with open(path, "w") as f:
    f.write(content)
print("OK: buildNudgeMessage added")
```

- [ ] **Step 3: Verificar que `sendSystemMessage` existe en `AutomatedConversationDispatchService`**

```bash
grep -n 'sendSystemMessage\|sendSystemText' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/AutomatedConversationDispatchService.php | head -10
```

Si solo existe `sendSystemText` y no `sendSystemMessage`, añadir el método:

```python
path = "/kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/AutomatedConversationDispatchService.php"
with open(path) as f:
    content = f.read()

# Find sendSystemText to insert sendSystemMessage nearby
if 'sendSystemMessage' in content:
    print("sendSystemMessage already exists, skip"); exit(0)

target = "    public function sendSystemText("
if target not in content:
    print("ERROR: sendSystemText not found"); exit(1)

insertion = """    /**
     * Sends a structured interactive message (buttons/list) from the system.
     *
     * @param array<string, mixed> $message
     */
    public function sendSystemMessage(WhatsappConversation $conversation, array $message): void
    {
        $this->sendFlowMessage($conversation, $message, []);
    }

    """

content = content.replace(target, insertion + target, 1)
with open(path, "w") as f:
    f.write(content)
print("OK: sendSystemMessage added to dispatch service")
```

- [ ] **Step 4: Verificar**

```bash
grep -n 'buildNudgeMessage\|sendSystemMessage\|isAgendaState' /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php | head -10
```

- [ ] **Step 5: Commit**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git add medforge/laravel-app/app/Modules/Whatsapp/Services/ConversationAbandonmentMonitorService.php
git add medforge/laravel-app/app/Modules/Whatsapp/Services/AutomatedConversationDispatchService.php
git commit -m "feat(whatsapp): contextual re-engagement nudge with buttons for agenda states

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Verificación final de producción

Después de todas las tasks, ejecutar un smoke-test manual:

- [ ] **Smoke test 1 — Cortesía con awaiting_field:** Iniciar el flujo de cédula, luego enviar "gracias". El bot NO debe responder "¡Con gusto!" — debe seguir esperando la cédula.

- [ ] **Smoke test 2 — Cortesía sin awaiting_field:** Desde el menú, enviar "muchas gracias". El bot debe responder "¡Con gusto! 😊..."

- [ ] **Smoke test 3 — Retry cédula:** Enviar 3 cédulas inválidas. Al tercero el bot debe escalar a humano.

- [ ] **Smoke test 4 — Frustración leve:** Enviar "???" o "no entiendo". El bot debe responder con 3 botones: Agendar / Ver cita / Hablar con asesor.

- [ ] **Smoke test 5 — Frustración explícita:** Enviar "esto no sirve". El bot debe responder con mensaje de handoff y transferir.

- [ ] **Smoke test 6 — Menú:** Enviar "MENU". Deben aparecer 7 opciones en la lista incluyendo "Especialidades" y "Precios y convenios".

- [ ] **Smoke test 7 — Confirmación:** Llegar al paso de confirmación de cita. Deben aparecer 3 botones: Confirmar / Cambiar horario / Cancelar.

- [ ] **Smoke test 8 — Cita exitosa:** Confirmar la cita. El mensaje de éxito debe tener botones "Agendar otra" y "Menú principal".

- [ ] **Revisar logs:**

```bash
tail -50 /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/storage/logs/laravel-schedule.log
tail -50 /kunden/homepages/26/d793096920/htdocs/medforge/laravel-app/storage/logs/laravel-queue.log
```

No deben aparecer errores relacionados con los métodos nuevos.
