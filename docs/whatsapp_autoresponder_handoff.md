# Flujo real: Autorespondedor + Handoff (WhatsApp)

Este documento explica **cómo se mueve una conversación** desde el webhook hasta el bot, y cómo se dispara la derivación a humano (handoff).

## Resumen rápido

- El **webhook** recibe mensajes y registra el inbound.
- El **ScenarioEngine** decide si responde el bot o si deriva a humano.
- Si hay **handoff**, se crea `whatsapp_handoffs` y se notifica a agentes.
- El **agente toma** con un botón (`TOMAR_{id}`), y el chat se asigna.
- Si el TTL vence, el caso **se re-encola** automáticamente.

---

## Diagrama de flujo (Mermaid)

```mermaid
flowchart TD
    A[Webhook WhatsApp] --> B{Mensaje válido?}
    B -->|No| Z[Ignorar]
    B -->|Sí| C[Registrar inbound en whatsapp_messages + inbox]

    C --> D{Mensaje es de agente?}
    D -->|Sí: TOMAR/IGNORAR| E[HandoffService::handleAgentReply]
    E --> F{TOMAR_{id}?}
    F -->|Sí| G[Asignar handoff + conversation.assigned_user_id]
    F -->|No| H[Registrar evento ignore]
    G --> I[Bot no responde]

    D -->|No| J{Conversación asignada?}
    J -->|Sí| I[Bot no responde]
    J -->|No| K[ScenarioEngine::handleIncoming]

    K --> L{Algún escenario coincide?}
    L -->|Sí| M[Ejecutar acciones]
    L -->|No| N[Fallback]

    M --> O{Acción: handoff_agent?}
    O -->|No| P[Enviar respuesta bot]
    O -->|Sí| Q[HandoffService::requestHandoff]

    Q --> R[Crear/actualizar whatsapp_handoffs]
    R --> S[Set needs_human + notes + role]
    R --> T[Notificar agentes por WhatsApp]

    T --> U[Agente recibe botón TOMAR/IGNORAR]
    U --> E

    subgraph Cron
        V[Requeue TTL] --> W[HandoffService::requeueExpired]
        W --> R
    end
```

---

## Reglas clave del Autorespondedor

1. **Orden de escenarios**
   - Se evalúan en orden.
   - El **primer match detiene** la evaluación.
   - El **Fallback** debe ir **siempre al final**.

2. **Detección de ayuda**
   - Usa condiciones tipo `message_contains` con keywords.
   - Si se activa `handoff_agent`, se solicita atención humana.

3. **Cuando ya hay agente asignado**
   - El bot **no responde** mientras `assigned_user_id` existe.

---

## Handoff: Estados y eventos

### Tabla principal
- `whatsapp_handoffs`
  - status: `queued`, `assigned`, `resolved`, `expired`
  - assigned_until: TTL (por defecto 24h)
  - handoff_role_id: rol objetivo

### Historial
- `whatsapp_handoff_events`
  - `queued`, `requested`, `assigned`, `transferred`, `resolved`, `expired`, `requeued`

---

## Configuración en Settings (WhatsApp → Handoff)

- `whatsapp_handoff_ttl_hours` (default 24)
- `whatsapp_handoff_notify_agents` (on/off)
- `whatsapp_handoff_agent_message` (template)
- `whatsapp_handoff_button_take_label`
- `whatsapp_handoff_button_ignore_label`

---

## Mensaje al agente (ejemplo)

```
Paciente {{contact}} necesita asistencia.
Toca para tomar ✅

Nota: {{notes}}
```

---

## Qué pasa cuando el agente toma el caso

- Webhook recibe la respuesta del agente `TOMAR_{id}`.
- Handoff se marca `assigned` con TTL.
- Conversation se asigna a `assigned_user_id`.
- El bot deja de responder hasta que se cierre/resuelva.

---

## Cierre y eliminación

- **Cerrar conversación**: resuelve handoff + limpia flags + marca leído.
- **Eliminar conversación**: borra conversación + mensajes + handoffs + inbox.

---

## Consejos operativos

- Mantén **Fallback** siempre último.
- Asegura que keywords **incluyan variantes** (ej. `menu` y `menú`).
- Si quieres reconocimiento más flexible, podemos normalizar acentos.

---

## Archivos relevantes

- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Controllers/WebhookController.php`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Autoresponder/Services/ScenarioEngine.php`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Services/HandoffService.php`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Repositories/HandoffRepository.php`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Services/ConversationService.php`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/helpers/SettingsHelper.php`

