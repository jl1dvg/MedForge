# WhatsApp Bot — Mejoras Wave 1 y Wave 2
**Fecha:** 2026-05-31  
**Proyecto:** MedForge / CIVE  
**Archivo principal:** `app/Modules/Whatsapp/Services/FlowRuntimeExecutionService.php`  
**Estrategia:** Riesgo ascendente — Wave 1 (bugs) → Wave 2 (UX interactiva). Wave 3 (templates Meta) diferida.

---

## Contexto

El bot de WhatsApp de CIVE maneja el ciclo completo de captación de leads: consentimiento → identificación → agenda en Sigcenter. Opera vía `FlowRuntimeExecutionService` (107 métodos, ~122KB) con estados almacenados en `WhatsappAutoresponderSession.context`.

Problemas identificados en sesión de brainstorming del 2026-05-31.

---

## Wave 1 — Bug Fixes (sin riesgo, efecto inmediato)

### W1-1: `isCourtesyMessage` no debe disparar con `awaiting_field` activo

**Problema:** El fix de cortesía reciente responde "¡Con gusto!" cuando el paciente dice "gracias" en cualquier momento — incluso si el bot está esperando un dato (`awaiting_field` activo, ej. cédula). Esto limpia el estado de la sesión y rompe el flujo.

**Fix:** En `FlowRuntimeExecutionService::executeInbound()`, el check de cortesía debe evaluarse solo si `$context['awaiting_field']` está vacío.

```php
// ANTES (línea ~291):
if ($this->isCourtesyMessage($text)) { ... }

// DESPUÉS:
if (empty($context['awaiting_field']) && $this->isCourtesyMessage($text)) { ... }
```

**Archivos:** `FlowRuntimeExecutionService.php`  
**Riesgo:** Nulo. Una condición adicional, no toca lógica existente.

---

### W1-2: Retry count por campo inválido con escalado al 3er intento

**Problema:** Si el paciente ingresa datos inválidos repetidamente (ej. cédula mal formada), el bot repite el mensaje de error indefinidamente. No hay conteo de intentos ni escalado.

**Diseño:**
- Usar `$context['input_retry_{field}']` como contador por campo
- Incrementar en cada intento fallido de validación del campo
- Al 3er intento fallido → enviar mensaje de handoff + marcar `handoff_requested`
- Resetear el contador cuando el campo se captura exitosamente

**Campos afectados por validación:** `cedula`, `correo`, y cualquier campo con validación explícita en `captureAwaitingInput`.

**Mensaje en el 3er intento:**
```
No pudimos verificar tu información. Un asesor te contactará para ayudarte. 🙏
```

**Archivos:** `FlowRuntimeExecutionService.php` (métodos `captureAwaitingInput`, `shouldRetryCedula`)  
**Riesgo:** Bajo. Añade un contador al contexto de sesión, no modifica lógica de validación existente.

---

## Wave 2 — UX Interactiva

### W2-1: Menú principal como `list_message`

**Decisión:** Opción A — List message completo con todas las opciones.

**Diseño del mensaje:**

```
Header: ¡Hola! ¿En qué te ayudamos hoy?
Botón: "📋 Ver opciones"

Items del list:
1. 📅 Agendar cita       → "Consultas, cirugías, especialistas"
2. 🔍 Ver mi cita         → "Consultar o cancelar"
3. 📍 Sedes y horarios   → "Dónde atendemos"
4. 💰 Precios y convenios → "Tarifas y seguros"
5. 🩺 Especialidades      → "Qué tratamos"
6. 🙋 Hablar con asesor  → "Atención personalizada"
```

**Implementación:** Reemplazar `mainMenuMessage()` para que retorne `type: list` con la estructura anterior en lugar de `type: text`. El método `sendFlowMessage` ya soporta `list` vía `CloudApiTransportService`.

**Archivos:** `FlowRuntimeExecutionService.php` (método `mainMenuMessage`)  
**Riesgo:** Bajo. Solo cambia el formato del mensaje. La lógica de routing por opción ya existe.

---

### W2-2: Confirmación de cita con 3 botones

**Decisión:** B (pre-agendamiento) + C (post-agendamiento).

**Mensaje B — Pre-confirmación (estado `agenda_confirmar_cita`):**
```
📋 Resumen de tu cita

🏥 Sede: {sede}
👨‍⚕️ Médico: {medico}
🗓️ Fecha: {fecha}
🕙 Hora: {hora}
🔬 Especialidad: {especialidad}

[✅ Confirmar] [🔄 Cambiar horario] [❌ Cancelar]
```

**Comportamiento de botones:**
- **Confirmar** → ejecuta agendamiento en Sigcenter (flujo actual)
- **Cambiar horario** → regresa al estado `agenda_esperando_horario` conservando médico y sede en contexto
- **Cancelar** → cierra limpiamente, estado `menu_principal`

**Mensaje C — Post-agendamiento (éxito de Sigcenter):**
```
✅ ¡Cita agendada exitosamente!

📅 {fecha} · {hora}
👨‍⚕️ {medico} · {especialidad}
🏥 {sede}

Te enviaremos un recordatorio 24h antes.

[🗓️ Agendar otra] [🏠 Menú]
```

**Archivos:** `FlowRuntimeExecutionService.php`, `FlowSigcenterAgendaService.php`  
**Riesgo:** Medio. Requiere manejar el botón "Cambiar horario" como nuevo caso en el dispatcher de estados.

---

### W2-3: Mensaje de "procesando..." antes de consultas Sigcenter

**Problema:** Las consultas a Sigcenter toman 3-8s. El paciente ve silencio.

**Diseño:** Enviar un mensaje de texto breve ANTES de llamar a Sigcenter en operaciones lentas:

```
🔍 Buscando disponibilidad...
```

```
📋 Consultando tu información...
```

**Cuándo enviarlo:** Antes de `sigcenterAgendaService->execute()` en operaciones tipo `list_times`, `confirm_appointment`, `lookup_patient`.

**Archivos:** `FlowRuntimeExecutionService.php` (método `executeActions`, casos de acciones Sigcenter)  
**Riesgo:** Bajo. Mensaje adicional, no modifica lógica.

---

### W2-4: Detección de frustración

**Problema:** Mensajes de frustración (`???`, `no funciona`, `esto no sirve`, `no entiendo`, `ayuda urgente`) caen al fallback genérico de "No entendí tu mensaje".

**Diseño:** Nuevo método `isFrustrationSignal(string $text): bool` con dos niveles:

- **Nivel 1** (solo texto de frustración corta: `?`, `??`, `???`, `no entiendo`, `ayuda`) → responder con opciones claras + oferta de asesor
- **Nivel 2** (frustración explícita: `no funciona`, `esto no sirve`, `terrible`, `pésimo`) → handoff directo con nota "paciente frustrado"

**Mensaje Nivel 1:**
```
Disculpa la confusión 🙏 ¿Cómo te podemos ayudar?

[📅 Agendar cita] [🔍 Ver mi cita] [🙋 Hablar con asesor]
```

**Mensaje Nivel 2:**
```
Lamentamos tu experiencia. Un asesor te atenderá de inmediato. 🙏
```

**Archivos:** `FlowRuntimeExecutionService.php`  
**Riesgo:** Bajo. Nuevo método + check antes del fallback, igual que `isCourtesyMessage`.

---

### W2-5: Nudge de re-engagement inteligente

**Decisión:** Comportamiento diferenciado según estado de la sesión:

**Si el estado es de tipo agenda** (prefijo `agenda_` o estados `esperando_medico`, `esperando_dia`, etc.):
```
⏳ ¡Hola! Estabas eligiendo un horario con el {medico} en {sede}.

¿Quieres que retomemos desde ahí?

[✅ Sí, continuar] [🔄 Empezar de nuevo]
```
- **Continuar** → retoma desde el estado guardado en sesión
- **Empezar de nuevo** → resetea contexto de agenda, va al inicio del flujo de agenda

**Si el estado es otro proceso** (consentimiento, cédula, etc.):
```
⏳ Parece que se interrumpió tu proceso. Si aún deseas continuar, responde este mensaje y te ayudo.
```

**Implementación:** En `ConversationAbandonmentMonitorService::scan()`, construir el mensaje nudge condicionalmente según el estado. Los datos de médico/sede están disponibles en `$context` de la sesión.

**Archivos:** `ConversationAbandonmentMonitorService.php`, `AutomatedConversationDispatchService.php`  
**Riesgo:** Medio. Cambia el mensaje enviado y agrega manejo del botón de respuesta.

---

## Orden de implementación sugerido

```
W1-1 → W1-2 → W2-3 → W2-4 → W2-1 → W2-2 → W2-5
```

Razonamiento: primero los fixes que no cambian UX, luego los mensajes de soporte (procesando, frustración), luego los cambios de UI principales (menú, confirmación), finalmente el nudge inteligente que depende de datos de sesión.

---

## Wave 3 (diferida)

- Reminder 24h con botones Quick Reply → requiere modificar template en Meta Business Manager
- NPS post-consulta → requiere crear template nuevo en Meta (no existe)
- Ambos tienen dependencia externa. Se retoman cuando CIVE gestione los templates.

---

## Archivos principales involucrados

| Archivo | Waves |
|---|---|
| `FlowRuntimeExecutionService.php` | W1-1, W1-2, W2-1, W2-2, W2-3, W2-4 |
| `ConversationAbandonmentMonitorService.php` | W2-5 |
| `AutomatedConversationDispatchService.php` | W2-5 |
| `FlowSigcenterAgendaService.php` | W2-2 (post-confirmación) |
