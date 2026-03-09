# Tutorial operativo del módulo WhatsApp

Guía práctica para dejarlo funcionando y operar el chat en producción.

## 0) Objetivo

Al terminar este tutorial podrás:

- conectar webhook con Meta
- validar que entren/salgan mensajes
- usar handoff (tomar/transferir/cerrar)
- usar plantillas para iniciar conversación

---

## 1) Pre-requisitos

- Migraciones de WhatsApp aplicadas.
- Usuario con permisos:
  - `whatsapp.chat.view`
  - `whatsapp.chat.send`
  - `whatsapp.chat.assign` (si gestionará handoffs)
  - o `whatsapp.manage`
- Credenciales de WhatsApp Cloud API:
  - `phone_number_id`
  - `business_account_id` (requerido para templates)
  - `access_token`
  - verify token

---

## 2) Configurar integración

En Configuración > WhatsApp:

1. Activar integración (`whatsapp_cloud_enabled = 1`).
2. Guardar `phone_number_id` y `access_token`.
3. Definir versión API (si no, usa `v17.0`).
4. Configurar token de verificación de webhook.
5. Definir política de respuesta:
   - `whatsapp_chat_require_assignment_to_reply = 1` (recomendado en operación multiagente)
6. Definir rol default para handoff (opcional):
   - `whatsapp_handoff_default_role_id = <id_rol>`
7. Confirmar URL webhook esperada:
   - `https://<tu-dominio>/whatsapp/webhook`

> Internamente lo resuelve `Config/WhatsAppSettings.php`.

---

## 3) Verificar webhook (Meta)

### 3.1 Challenge GET
Meta envía challenge al registrar webhook.

- Ruta: `GET /whatsapp/webhook`
- Condición de éxito: `hub.mode=subscribe` y token correcto.

Si el token coincide, devuelve el `hub.challenge` (200).
Si no, devuelve 403.

### 3.2 Recepción POST
Con webhook activo, cualquier mensaje entrante llegará por:

- `POST /whatsapp/webhook`

La app:
- registra inbound en conversación + inbox,
- actualiza estados de mensajes (sent/delivered/read),
- dispara bot/handoff según reglas.

---

## 4) Operar chat humano

### 4.1 Abrir bandeja
- URL: `/whatsapp/chat`

### 4.2 Tomar conversación
- Cuando un caso está en handoff, usa **Tomar**.
- Solo el agente asignado podrá responder luego.

### 4.3 Reasignar/transferir
- Sin permisos de supervisión, la conversación debe estar tomada por ti para transferir.
- Con `whatsapp.chat.supervise` (o `whatsapp.manage`), puedes reasignar aunque esté tomada por otro agente.
- Transferir/reasignar mueve la conversación al nuevo agente.

### 4.4 Cerrar
- Cierra handoff activo.
- Limpia flags y deja conversación fuera de cola.

---

## 5) Enviar mensajes desde API

Endpoint: `POST /whatsapp/api/messages`

Puedes enviar:
- texto (`message`)
- plantilla (`template`)
- adjunto (`attachment` multipart)

Campos clave:
- `conversation_id` o `wa_number`
- `message` (opcional si hay template/adjunto)
- `template` (JSON con `name`, `language`, `components` opcionales)

Reglas importantes:
- Si el contacto no tiene inbound previo, debes abrir con **template aprobada**.
- Si envías por `conversation_id` y `whatsapp_chat_require_assignment_to_reply = 1`, debes tener la conversación tomada para responder.

---

## 6) Plantillas

UI: `/whatsapp/templates`

API:
- `GET /whatsapp/api/templates`
- `POST /whatsapp/api/templates`
- `POST /whatsapp/api/templates/{templateId}`
- `POST /whatsapp/api/templates/{templateId}/delete`

Notas:
- Requiere `business_account_id` + token válido.
- Si falla integración, verás errores/advertencias en la vista.

---

## 7) Handoff automático (bot -> humano)

Se activa desde escenarios del autoresponder (acción `handoff_agent`).

Config útil en settings:
- `whatsapp_handoff_ttl_hours`
- `whatsapp_handoff_notify_in_app`
- `whatsapp_handoff_notify_agents`
- `whatsapp_handoff_agent_message`
- labels de botones tomar/ignorar

Comandos de agente por WhatsApp:
- `TOMAR_<id>`
- `IGNORAR_<id>`

---

## 8) Validación rápida de funcionamiento (checklist)

1. `GET /whatsapp/webhook` challenge responde 200.
2. Entra un mensaje real y aparece en `/whatsapp/chat`.
3. Respuesta manual desde chat sale al contacto.
4. Estado de mensaje cambia con webhook status.
5. Se puede tomar/transferir/cerrar un handoff.
6. Envío de plantilla funciona para contacto sin ventana abierta.

---

## 9) Troubleshooting express

### Error: “No fue posible enviar el mensaje”
- Revisar token vencido/incorrecto.
- Revisar `phone_number_id`.
- Revisar `transport_error` en respuesta API.

### Error 403 en webhook verify
- Token de verificación no coincide con Meta.

### No se puede responder conversación
- Está asignada a otro agente.
- O está marcada para handoff y no la has tomado.

### No aparecen agentes para handoff
- Sin permisos `whatsapp.chat.send`/`whatsapp.manage`.
- `whatsapp_notify != 1`.
- número inválido o presencia `away/offline`.

---

## 10) Archivos que debes conocer sí o sí

- `modules/WhatsApp/Controllers/WebhookController.php`
- `modules/WhatsApp/Controllers/ChatController.php`
- `modules/WhatsApp/Services/Messenger.php`
- `modules/WhatsApp/Services/HandoffService.php`
- `modules/WhatsApp/Services/ConversationService.php`
- `modules/WhatsApp/Config/WhatsAppSettings.php`
- `modules/WhatsApp/routes.php`
