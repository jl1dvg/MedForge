# Módulo WhatsApp (MedForge)

Este módulo integra MedForge con **WhatsApp Cloud API** y cubre:

- recepción de mensajes (webhook)
- envío de mensajes (texto, plantillas, adjuntos, interactivos)
- bandeja/chat operativo
- handoff a humano (asignación a agentes)
- dashboard KPI de WhatsApp

---

## 1) Arquitectura rápida

### Entradas
- `Controllers/WebhookController.php`
  - Verifica `GET /whatsapp/webhook` (token challenge)
  - Procesa `POST /whatsapp/webhook` (mensajes y estados)

### Salidas (envío a Meta)
- `Services/Messenger.php`
- `Services/CloudApiTransport.php`

### Orquestación de conversación
- `Services/ConversationService.php`
- `Repositories/ConversationRepository.php`
- `Repositories/InboxRepository.php`

### Handoff humano
- `Services/HandoffService.php`
- `Repositories/HandoffRepository.php`

### Configuración
- `Config/WhatsAppSettings.php`
  - Lee `SettingsModel` (DB)
  - Fallback a variables de entorno para verify token

### Templates
- `Controllers/TemplateController.php`
- `Services/TemplateManager.php`

### UI
- `views/chat.php`
- `views/templates.php`
- `views/dashboard.php`

---

## 2) Rutas principales

Definidas en `modules/WhatsApp/routes.php`.

- UI:
  - `GET /whatsapp/chat`
  - `GET /whatsapp/templates`
  - `GET /whatsapp/dashboard`
- API chat:
  - `GET /whatsapp/api/conversations`
  - `GET /whatsapp/api/conversations/{id}`
  - `POST /whatsapp/api/messages`
  - `POST /whatsapp/api/conversations/{id}/assign`
  - `POST /whatsapp/api/conversations/{id}/transfer`
  - `POST /whatsapp/api/conversations/{id}/close`
  - `POST /whatsapp/api/conversations/{id}/delete`
  - `GET /whatsapp/api/media/{mediaId}`
- API agentes/presencia:
  - `GET /whatsapp/api/agents`
  - `GET /whatsapp/api/agent-presence`
  - `POST /whatsapp/api/agent-presence`
- API templates:
  - `GET /whatsapp/api/templates`
  - `POST /whatsapp/api/templates`
  - `POST /whatsapp/api/templates/{templateId}`
  - `POST /whatsapp/api/templates/{templateId}/delete`
- Webhook:
  - `GET|POST /whatsapp/webhook`

---

## 3) Configuración mínima para funcionar

En Settings (persistido por `SettingsModel`) deben existir:

- `whatsapp_cloud_enabled = 1`
- `whatsapp_cloud_phone_number_id`
- `whatsapp_cloud_access_token`
- opcional: `whatsapp_cloud_api_version` (default `v17.0`)
- `whatsapp_webhook_verify_token` (si falta, usa env/fallback)

Si faltan `phone_number_id` o `access_token`, el módulo se considera **disabled**.

---

## 4) Flujo funcional real

1. Meta llama `POST /whatsapp/webhook`.
2. Se registran inbound y estados de entrega/lectura.
3. Si el remitente es un agente con comandos `TOMAR_x` / `IGNORAR_x`, se procesa handoff.
4. Si la conversación ya está asignada a humano, el bot no responde.
5. Si no está asignada:
   - se evalúa `ScenarioEngine` (autoresponder),
   - se maneja consentimiento/protección de datos si aplica,
   - se envían respuestas por `Messenger`.

---

## 5) Reglas operativas importantes

- Solo el agente asignado puede responder una conversación tomada.
- Si el contacto no ha iniciado conversación (sin inbound), se exige plantilla para abrir ventana.
- Handoff usa TTL (default 24h) y puede reencolarse por tarea programada.
- Notificación de handoff puede ir por in-app (Pusher) y/o WhatsApp a agentes según settings.

---

## 6) Base de datos / migraciones relevantes

En `database/migrations/`:

- `20250115_create_whatsapp_chat_tables.sql`
- `20241010_create_whatsapp_contact_consent.sql`
- `20241111_create_whatsapp_inbox_messages.sql`
- `20260211_create_whatsapp_handoff_tables.sql`
- `20260211_add_whatsapp_agent_fields.sql`
- `20260211_whatsapp_handoff_assignment.sql`
- `20260226_create_whatsapp_agent_presence.sql`
- (y migraciones KPI/index)

---

## 7) Diagnóstico rápido

1. Verifica webhook challenge: `GET /whatsapp/webhook` con token correcto.
2. Verifica settings (`enabled`, phone_number_id, token).
3. Si falla envío, revisar `transport_error` devuelto por `ChatController`.
4. Confirmar que migraciones de handoff/presencia estén aplicadas.
5. Revisar permisos del usuario (`whatsapp.chat.*`, `whatsapp.manage`).

---

## 8) Documentación relacionada

- `modules/WhatsApp/TUTORIAL.md`
- `docs/whatsapp_autoresponder_handoff.md`
- `docs/whatsapp_autoresponder_dependencies.md`
