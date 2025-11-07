# Tutorial de solicitudes quirúrgicas, CRM, notificaciones y WhatsApp

Este documento explica cómo está construida la experiencia de solicitudes quirúrgicas en MedForge, cómo se integra el panel CRM, qué notificaciones en tiempo real emite el sistema y cómo opera el módulo de WhatsApp.

## 1. Tablero de solicitudes quirúrgicas

El tablero principal vive en `/solicitudes` y renderiza la vista `modules/solicitudes/views/solicitudes.php`, donde se define el tablero Kanban, los filtros y el panel lateral de CRM. 【F:modules/solicitudes/views/solicitudes.php†L19-L748】

El `SolicitudController` protege la ruta, prepara la configuración de tiempo real y atiende las peticiones Ajax para poblar el tablero. Dentro de `kanbanData()` toma los filtros enviados, consulta al `SolicitudModel`, ordena y limita los resultados según las preferencias CRM, y devuelve datos y catálogos (afiliaciones, doctores, etapas, responsables, fuentes). 【F:modules/solicitudes/controllers/SolicitudController.php†L34-L143】

`SolicitudModel::fetchSolicitudesConDetallesFiltrado()` une `solicitud_procedimiento`, `patient_data` y `consulta_data` para entregar cada solicitud con información de paciente, procedimiento, prioridad, lateralidad, afiliación y diagnóstico, permitiendo filtros dinámicos por afiliación, doctor, prioridad y rango de fechas. 【F:models/SolicitudModel.php†L17-L88】

El front‑end (`public/js/pages/solicitudes/index.js`) registra los filtros, dispara el `fetch` a `/solicitudes/kanban-data`, llena combos únicos y actualiza el tablero cuando cambian filtros o se reciben eventos en tiempo real. También inicializa el panel de notificaciones y, si corresponde, las notificaciones de escritorio. 【F:public/js/pages/solicitudes/index.js†L7-L173】

## 2. Panel CRM dentro de solicitudes

El panel CRM es un *offcanvas* definido en la misma vista y se abre con botones `.btn-open-crm`. Ofrece resumen de la solicitud, detalles CRM (etapa, responsable, fuente, contactos, seguidores, campos personalizados), notas, adjuntos y tareas. 【F:modules/solicitudes/views/solicitudes.php†L400-L723】

Las interacciones están centralizadas en `public/js/pages/solicitudes/kanban/crmPanel.js`, que vincula botones, actualiza selects con responsables y etapas, carga datos desde `/solicitudes/{id}/crm`, y envía formularios de detalles, notas, adjuntos y tareas a los endpoints REST definidos en `modules/solicitudes/routes.php`. 【F:modules/solicitudes/routes.php†L6-L53】【F:public/js/pages/solicitudes/kanban/crmPanel.js†L1-L120】

`SolicitudController` delega la lógica CRM a `SolicitudCrmService`. Las rutas `crmResumen`, `crmGuardarDetalles`, `crmAgregarNota`, `crmGuardarTarea`, `crmActualizarTarea` y `crmSubirAdjunto` validan sesión, leen el cuerpo y responden con el resumen actualizado. Al guardar detalles se dispara además un evento de Pusher para avisar a otros clientes. 【F:modules/solicitudes/controllers/SolicitudController.php†L145-L329】

`SolicitudCrmService` concentra la lógica de negocio: sincroniza leads con el módulo CRM (`LeadModel`), normaliza etapas y contactos, maneja transacciones para guardar detalles y campos personalizados, y expone métodos para notas, tareas y adjuntos. Cada operación relevante invoca `notifyWhatsAppEvent()` para potencialmente informar por WhatsApp. 【F:modules/solicitudes/services/SolicitudCrmService.php†L15-L303】

El servicio arma un resumen rico consultando múltiples tablas (`solicitud_procedimiento`, `patient_data`, `solicitud_crm_*`, `crm_leads`, `users`) e incluye contadores de notas, adjuntos y tareas. También sincroniza automáticamente leads cuando cambia el responsable o la etapa CRM. 【F:modules/solicitudes/services/SolicitudCrmService.php†L256-L773】

## 3. CRM completo y configuración

El módulo CRM independiente (`/crm`) sigue disponible a través de `CRMController`, que carga catálogos de estatus, usuarios asignables y listas iniciales de leads, proyectos, tareas y tickets. También expone endpoints REST para crear, actualizar, listar y convertir leads. 【F:modules/CRM/Controllers/CRMController.php†L14-L167】

`LeadConfigurationService` (usado tanto por CRM como por solicitudes) centraliza etapas, preferencias de tablero y usuarios asignables, de modo que las configuraciones aplican en ambos contextos.

## 4. Notificaciones en tiempo real (Pusher)

`PusherConfigService` lee las credenciales y banderas de Settings, define los alias de eventos (`new_request`, `status_updated`, `crm_updated`, `surgery_reminder`) y expone `getPublicConfig()` para el front‑end y `trigger()` para enviar eventos usando `pusher/pusher-php-server`. También informa qué canales (correo, SMS, resumen diario) están habilitados para incluirlos en el payload. 【F:modules/Notifications/Services/PusherConfigService.php†L10-L203】

En el cliente, `index.js` instancia Pusher si está habilitado, se suscribe al canal (por defecto `solicitudes-kanban`) y escucha los eventos configurados: nuevas solicitudes refrescan el tablero, cambios de estado muestran un toast, actualizaciones CRM generan avisos, y recordatorios de cirugía se encolan como pendientes. Todos los avisos pasan por un panel de notificaciones reutilizable. 【F:public/js/pages/solicitudes/index.js†L175-L325】

Los métodos del controlador de solicitudes disparan estos eventos: `crmGuardarDetalles()` emite `crm_updated`, `actualizarEstado()` emite `status_updated`, y `SolicitudReminderService::dispatchUpcoming()` emite `surgery_reminder` evitando duplicados cada 6 horas mediante un caché en disco. 【F:modules/solicitudes/controllers/SolicitudController.php†L160-L421】【F:modules/solicitudes/services/SolicitudReminderService.php†L12-L75】

La vista inyecta `window.MEDF_PusherConfig` y, cuando hay credenciales, carga el SDK de Pusher. 【F:modules/solicitudes/views/solicitudes.php†L769-L780】

### ¿Dónde se ven las notificaciones?

Los toasts aparecen en la esquina superior derecha (`#toastContainer`) y el panel reutilizable se activa mediante el helper `createNotificationPanel()`. Si las notificaciones de escritorio están habilitadas, el script solicitará permisos y mostrará notificaciones nativas. 【F:modules/solicitudes/views/solicitudes.php†L767-L780】【F:public/js/pages/solicitudes/index.js†L17-L84】

## 5. Módulo de WhatsApp

`WhatsAppModule::messenger()` entrega una instancia de `Messenger`, que consulta `WhatsAppSettings` para obtener credenciales de WhatsApp Cloud API. Si la integración está habilitada (`whatsapp_cloud_enabled` más `phone_number_id` y `access_token`), `Messenger::sendTextMessage()` sanitiza el mensaje, normaliza números (agregando el código de país por defecto) y envía el payload contra Graph API mediante `CloudApiTransport`. 【F:modules/WhatsApp/WhatsAppModule.php†L1-L12】【F:modules/WhatsApp/Services/Messenger.php†L11-L69】【F:modules/WhatsApp/Config/WhatsAppSettings.php†L10-L104】

`SolicitudCrmService` llama a `notifyWhatsAppEvent()` después de cada cambio relevante (detalles, notas, tareas, adjuntos). Esa rutina valida que la integración esté activa, reúne teléfonos de paciente y contactos CRM, construye mensajes con emojis y enlaces de regreso a la solicitud, y delega el envío a `Messenger`. Se evita notificar cuando no hay cambios significativos (por ejemplo, etapa sin variación). 【F:modules/solicitudes/services/SolicitudCrmService.php†L308-L610】

De esta manera, el mismo flujo que actualiza el CRM puede avisar a pacientes o responsables por WhatsApp cuando la integración está configurada.

---

Con estos componentes, las solicitudes quirúrgicas disponen de un tablero operativo, gestión CRM integrada, notificaciones multi-canal y automatización de mensajes por WhatsApp totalmente reutilizable.
