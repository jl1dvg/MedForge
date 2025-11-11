# Tutorial de solicitudes quirúrgicas, CRM, notificaciones y WhatsApp

Este documento explica cómo está construida la experiencia de solicitudes quirúrgicas en MedForge, cómo se integra el panel CRM, qué notificaciones en tiempo real emite el sistema y cómo opera el módulo de WhatsApp.

## 1. Tablero de solicitudes quirúrgicas

El tablero principal vive en `/solicitudes` y renderiza la vista `modules/solicitudes/views/solicitudes.php`, donde se define el tablero Kanban, los filtros y el panel lateral de CRM. 【F:modules/solicitudes/views/solicitudes.php†L19-L748】

El `SolicitudController` protege la ruta, prepara la configuración de tiempo real y atiende las peticiones Ajax para poblar el tablero. Dentro de `kanbanData()` toma los filtros enviados, consulta al `SolicitudModel`, ordena y limita los resultados según las preferencias CRM, y devuelve datos y catálogos (afiliaciones, doctores, etapas, responsables, fuentes). 【F:modules/solicitudes/controllers/SolicitudController.php†L34-L143】

`SolicitudModel::fetchSolicitudesConDetallesFiltrado()` une `solicitud_procedimiento`, `patient_data` y `consulta_data` para entregar cada solicitud con información de paciente, procedimiento, prioridad, lateralidad, afiliación y diagnóstico, permitiendo filtros dinámicos por afiliación, doctor, prioridad y rango de fechas. 【F:models/SolicitudModel.php†L17-L88】

El front‑end (`public/js/pages/solicitudes/index.js`) registra los filtros, dispara el `fetch` a `/solicitudes/kanban-data`, llena combos únicos y actualiza el tablero cuando cambian filtros o se reciben eventos en tiempo real. También inicializa el panel de notificaciones y, si corresponde, las notificaciones de escritorio. Además, calcula indicadores SLA, alertas operativas (reprogramación, consentimiento) y métricas por responsable que se despliegan en el overview y la tabla. 【F:public/js/pages/solicitudes/index.js†L7-L395】

## 2. Panel CRM dentro de solicitudes

El panel CRM es un *offcanvas* definido en la misma vista y se abre con botones `.btn-open-crm`. Ofrece resumen de la solicitud, detalles CRM (etapa, responsable, fuente, contactos, seguidores, campos personalizados), notas, adjuntos y tareas. 【F:modules/solicitudes/views/solicitudes.php†L400-L723】

Las interacciones están centralizadas en `public/js/pages/solicitudes/kanban/crmPanel.js`, que vincula botones, actualiza selects con responsables y etapas, carga datos desde `/solicitudes/{id}/crm`, y envía formularios de detalles, notas, adjuntos y tareas a los endpoints REST definidos en `modules/solicitudes/routes.php`. 【F:modules/solicitudes/routes.php†L6-L53】【F:public/js/pages/solicitudes/kanban/crmPanel.js†L1-L120】

`SolicitudController` delega la lógica CRM a `SolicitudCrmService`. Las rutas `crmResumen`, `crmGuardarDetalles`, `crmAgregarNota`, `crmGuardarTarea`, `crmActualizarTarea` y `crmSubirAdjunto` validan sesión, leen el cuerpo y responden con el resumen actualizado. Al guardar detalles se dispara además un evento de Pusher para avisar a otros clientes. 【F:modules/solicitudes/controllers/SolicitudController.php†L145-L329】

`SolicitudCrmService` concentra la lógica de negocio: sincroniza leads con el módulo CRM (`LeadModel`), normaliza etapas y contactos, maneja transacciones para guardar detalles y campos personalizados, y expone métodos para notas, tareas y adjuntos. Cada operación relevante invoca `notifyWhatsAppEvent()` para potencialmente informar por WhatsApp. 【F:modules/solicitudes/services/SolicitudCrmService.php†L15-L303】

El servicio arma un resumen rico consultando múltiples tablas (`solicitud_procedimiento`, `patient_data`, `solicitud_crm_*`, `crm_leads`, `users`) e incluye contadores de notas, adjuntos y tareas. También sincroniza automáticamente leads cuando cambia el responsable o la etapa CRM. `SolicitudController::computeOperationalMetadata()` agrega prioridad automática, estado de SLA, fecha programada y alertas derivadas (reprogramación, consentimiento), mientras que `buildOperationalMetrics()` consolida esas señales por responsable. 【F:modules/solicitudes/controllers/SolicitudController.php†L17-L363】

## 3. CRM completo y configuración

El módulo CRM independiente (`/crm`) sigue disponible a través de `CRMController`, que carga catálogos de estatus, usuarios asignables y listas iniciales de leads, proyectos, tareas y tickets. También expone endpoints REST para crear, actualizar, listar y convertir leads. 【F:modules/CRM/Controllers/CRMController.php†L14-L167】

`LeadConfigurationService` (usado tanto por CRM como por solicitudes) centraliza etapas, preferencias de tablero y usuarios asignables, de modo que las configuraciones aplican en ambos contextos.

## 4. Notificaciones en tiempo real (Pusher)

`PusherConfigService` lee las credenciales y banderas de Settings, define los alias de eventos (`new_request`, `status_updated`, `crm_updated`, `surgery_reminder`, `preop_reminder`, `postop_reminder`, `exams_expiring`) y expone `getPublicConfig()` para el front‑end y `trigger()` para enviar eventos usando `pusher/pusher-php-server`. También informa qué canales (correo, SMS, resumen diario) están habilitados para incluirlos en el payload. 【F:modules/Notifications/Services/PusherConfigService.php†L10-L203】

En el cliente, `index.js` instancia Pusher si está habilitado, se suscribe al canal (por defecto `solicitudes-kanban`) y escucha los eventos configurados: nuevas solicitudes refrescan el tablero, cambios de estado muestran un toast, actualizaciones CRM generan avisos y los recordatorios operativos (preoperatorio, cirugía, postoperatorio y vigencias de exámenes) se encolan como pendientes diferenciando íconos y tonos. Todos los avisos pasan por un panel de notificaciones reutilizable. 【F:public/js/pages/solicitudes/index.js†L175-L325】

Los métodos del controlador de solicitudes disparan estos eventos: `crmGuardarDetalles()` emite `crm_updated`, `actualizarEstado()` emite `status_updated`, y `SolicitudReminderService::dispatchUpcoming()` emite los recordatorios multietapa evitando duplicados cada 6 horas mediante un caché en disco. 【F:modules/solicitudes/controllers/SolicitudController.php†L160-L421】【F:modules/solicitudes/services/SolicitudReminderService.php†L12-L180】

La vista inyecta `window.MEDF_PusherConfig` y, cuando hay credenciales, carga el SDK de Pusher. 【F:modules/solicitudes/views/solicitudes.php†L769-L780】

### ¿Dónde se ven las notificaciones?

Los toasts aparecen en la esquina superior derecha (`#toastContainer`) y el panel reutilizable se activa mediante el helper `createNotificationPanel()`. Si las notificaciones de escritorio están habilitadas, el script solicitará permisos y mostrará notificaciones nativas. 【F:modules/solicitudes/views/solicitudes.php†L767-L780】【F:public/js/pages/solicitudes/index.js†L17-L84】

## 5. Módulo de WhatsApp

`WhatsAppModule::messenger()` entrega una instancia de `Messenger`, que consulta `WhatsAppSettings` para obtener credenciales de WhatsApp Cloud API. Si la integración está habilitada (`whatsapp_cloud_enabled` más `phone_number_id` y `access_token`), `Messenger::sendTextMessage()` sanitiza el mensaje, normaliza números (agregando el código de país por defecto) y envía el payload contra Graph API mediante `CloudApiTransport`. 【F:modules/WhatsApp/WhatsAppModule.php†L1-L12】【F:modules/WhatsApp/Services/Messenger.php†L11-L69】【F:modules/WhatsApp/Config/WhatsAppSettings.php†L10-L104】

`SolicitudCrmService` llama a `notifyWhatsAppEvent()` después de cada cambio relevante (detalles, notas, tareas, adjuntos). Esa rutina valida que la integración esté activa, reúne teléfonos de paciente y contactos CRM, construye mensajes con emojis y enlaces de regreso a la solicitud, y delega el envío a `Messenger`. Se evita notificar cuando no hay cambios significativos (por ejemplo, etapa sin variación). 【F:modules/solicitudes/services/SolicitudCrmService.php†L308-L610】

De esta manera, el mismo flujo que actualiza el CRM puede avisar a pacientes o responsables por WhatsApp cuando la integración está configurada.

---

## 6. Estrategias de marketing y comunicación sobre WhatsApp

El mismo andamiaje técnico permite diseñar iniciativas de marketing relacional que acompañen a cada lead y paciente desde su captura hasta el seguimiento postoperatorio.

### 6.1 Journeys automatizados multietapa

- **Onboarding inteligente**. Configuren reglas en `SolicitudCrmService::notifyWhatsAppEvent()` para detectar la etapa inicial del lead (`lead_stage = 'captado'`) y enviar un flujo con botones de respuesta rápida (``WhatsappInteractiveBuilder`` soporta `buttons` y `list` en el payload del Cloud API) que capture preferencia de sede, urgencia o necesidad de financiamiento. Cada respuesta se registra como nota CRM mediante `crmAgregarNota`, conservando la trazabilidad. 【F:modules/solicitudes/services/SolicitudCrmService.php†L308-L610】【F:modules/solicitudes/controllers/SolicitudController.php†L202-L276】
- **Confirmaciones y recordatorios quirúrgicos**. Extiendan `SolicitudReminderService::dispatchUpcoming()` para incluir plantillas enriquecidas que combinen texto, botones “Confirmar asistencia” y enlaces a instrucciones preoperatorias. El botón puede abrir un `deep link` hacia el panel de consentimientos o un PDF hospedado en `public/storage`. 【F:modules/solicitudes/services/SolicitudReminderService.php†L12-L180】
- **Seguimiento postoperatorio**. Definan un `journey` basado en la etapa CRM `postoperatorio` que envíe encuestas NPS (`interactive`-`list`) y derive automáticamente tickets al módulo CRM cuando la calificación sea ≤7; `crmGuardarTarea` ya permite crear tareas asignadas a un coordinador de experiencia. 【F:modules/solicitudes/controllers/SolicitudController.php†L278-L320】

### 6.2 Segmentación operativa conectada al Kanban

- **Fuentes calientes vs frías**. Aprovechen los filtros de `SolicitudController::kanbanData()` para generar vistas específicas por fuente (`Facebook`, `Referido`, `Landing`). Con esa información, creen campañas diferenciadas exportando leads con `LeadModel::getLeadsByFilter` y alimentando plantillas en WhatsApp o email transaccional según la temperatura. 【F:modules/solicitudes/controllers/SolicitudController.php†L34-L143】
- **Asignación dinámica de responsables**. Cuando `crmGuardarDetalles` cambie el responsable, disparen una notificación WhatsApp interna al ejecutivo asignado con el resumen de la solicitud y botones para mover la etapa (`/crm/leads/{id}`), reduciendo tiempos de atención.
- **Listas temáticas**. Usen los campos personalizados sincronizados por `SolicitudCrmService` (ej. `tipo_lente`, `diagnostico_principal`) para armar listas de difusión segmentadas (`broadcast`). La normalización existente evita duplicados y respeta las preferencias de comunicación registradas en CRM.

### 6.3 Analítica de campañas y feedback loop

- **Métricas de conversión**. Persistan cada entrega, clic y respuesta en una tabla `whatsapp_metrics` enlazada al `crm_lead_id`. Desde ahí, alimenten tableros en `docs/sql` (por ejemplo `sql/solicitudes_conversion_rate.sql`) para medir ratios de confirmación, asistencia y satisfacción por campaña.
- **A/B testing de plantillas**. Versionen los mensajes dentro de `WhatsAppSettings` o un catálogo propio y almacenen el identificador de plantilla en el payload que se envía desde `Messenger::sendTemplateMessage()`. Comparen el desempeño con consultas programadas en el módulo de reportes.
- **Retroalimentación operativa**. Cada vez que un paciente responda con palabras clave (“financiamiento”, “reprogramar”, “llámenme”), usen los webhooks del Cloud API (disponibles en `modules/WhatsApp/Controllers/WebhookController.php`) para crear automáticamente un ticket en CRM y asignarlo a la bandeja correspondiente, asegurando seguimiento inmediato.

Con estos componentes, las solicitudes quirúrgicas disponen de un tablero operativo, gestión CRM integrada, notificaciones multi-canal y automatización de mensajes por WhatsApp totalmente reutilizable.
