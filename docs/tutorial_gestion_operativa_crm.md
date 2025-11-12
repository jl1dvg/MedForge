# Tutorial operativo de CRM para solicitudes quirúrgicas y de exámenes

Este tutorial resume cómo usar el tablero de solicitudes y el módulo de exámenes con su CRM embebido, qué comunicaciones genera la plataforma y cómo organizar al equipo para sacar el máximo provecho del flujo operativo.

## 1. Conceptos clave

- **Solicitud quirúrgica**. Registro que combina datos clínicos, prioridad, programación y seguimiento comercial. Se visualiza en el tablero `/solicitudes` y se alimenta de consultas y formularios relacionados.【F:modules/solicitudes/controllers/SolicitudController.php†L45-L143】
- **Solicitud de exámenes**. Caso independiente con su propio tablero `/examenes` pero que comparte la lógica CRM (responsables, etapas, fuentes y tareas) con el módulo de cirugías.【F:modules/examenes/controllers/ExamenController.php†L37-L210】
- **Panel CRM contextual**. Offcanvas que se abre desde cada tarjeta para editar responsable, etapa, contactos, notas, adjuntos y tareas sin salir del tablero. Toda la lógica se canaliza a través de los servicios `SolicitudCrmService` y `ExamenCrmService` para mantener sincronizados los leads del CRM principal.【F:modules/solicitudes/views/solicitudes.php†L400-L723】【F:modules/solicitudes/services/SolicitudCrmService.php†L42-L161】【F:modules/examenes/controllers/ExamenController.php†L156-L210】

## 2. Roles sugeridos

1. **Coordinador/a quirúrgico/a**: monitorea prioridades, fechas y alertas SLA desde el overview del tablero, y mantiene actualizados responsable y etapa de cada caso.【F:public/js/pages/solicitudes/index.js†L98-L207】
2. **Ejecutivo/a CRM**: gestiona contactos, notas y tareas desde el panel, asegurando que cada solicitud tenga responsable asignado y seguimiento documentado.【F:modules/solicitudes/services/SolicitudCrmService.php†L76-L237】
3. **Equipo de exámenes**: opera el tablero `/examenes`, atiende recordatorios de vencimientos y coordina logística de estudios diagnósticos.【F:modules/examenes/controllers/ExamenController.php†L37-L210】
4. **Responsable de comunicaciones**: supervisa los canales habilitados (correo, SMS, resúmenes diarios) y la mensajería de WhatsApp automatizada.【F:modules/Notifications/Services/PusherConfigService.php†L54-L188】【F:modules/solicitudes/services/SolicitudCrmService.php†L308-L338】【F:modules/WhatsApp/Services/Messenger.php†L24-L170】

## 3. Flujo diario de trabajo

1. **Ingreso y filtros iniciales**
   - Accede a `/solicitudes` o `/examenes` para ver el Kanban con métricas de estado, SLA y prioridad calculadas automáticamente en la cabecera.【F:modules/solicitudes/views/solicitudes.php†L37-L120】【F:public/js/pages/solicitudes/index.js†L98-L207】【F:public/js/pages/examenes/index.js†L87-L200】
   - Usa los filtros de afiliación, doctor, prioridad y rango de fechas para concentrarte en la carga relevante. El backend consolida la información y límites de columnas según la configuración CRM.【F:modules/solicitudes/controllers/SolicitudController.php†L93-L161】【F:modules/examenes/controllers/ExamenController.php†L91-L153】

2. **Revisión operativa**
   - Prioriza tarjetas con semáforos críticos (SLA vencido o urgencia) y con alertas pendientes (reprogramaciones, consentimiento). Estas métricas se calculan en `buildOperationalMetrics()` y se muestran en el overview y tabla dinámica.【F:modules/solicitudes/controllers/SolicitudController.php†L105-L139】【F:public/js/pages/solicitudes/index.js†L159-L207】
   - En exámenes, el overview destaca casos por vencer y permite alternar entre vista Kanban y tabla para seguimiento masivo.【F:public/js/pages/examenes/index.js†L87-L200】

3. **Gestión CRM contextual**
   - Desde la tarjeta, abre el panel CRM y actualiza responsable, etapa y fuente; el sistema sincroniza el lead correspondiente y guarda campos personalizados dentro de una transacción segura.【F:modules/solicitudes/services/SolicitudCrmService.php†L76-L161】
   - Registra notas, adjuntos o tareas con fechas de vencimiento. Cada acción dispara validaciones y puede generar notificaciones automáticas (incluyendo WhatsApp) para los involucrados.【F:modules/solicitudes/services/SolicitudCrmService.php†L163-L303】
   - En exámenes, el flujo es idéntico y envía eventos específicos al canal `examenes-kanban` para mantener sincronizados otros clientes.【F:modules/examenes/controllers/ExamenController.php†L171-L205】

4. **Control de recordatorios**
   - El servicio de recordatorios ejecuta barridos periódicos para enviar avisos de preparación, cirugía, postoperatorio y vigencias de exámenes, evitando duplicados mediante caché en disco.【F:modules/solicitudes/services/SolicitudReminderService.php†L73-L190】
   - Puedes extender las ventanas de tiempo o los escenarios para adaptar la cadencia de alertas según la operación.

## 4. Comunicaciones y notificaciones

- **Pusher en tiempo real**. Cada vez que se crea o actualiza una solicitud, se emiten eventos `new_request`, `status_updated` o `crm_updated` (y sus equivalentes en exámenes) que actualizan el tablero, muestran toasts y registran pendientes en el panel de notificaciones. Las preferencias de canales (correo, SMS, resumen diario) se inyectan en el cliente para decidir dónde avisar.【F:modules/Notifications/Services/PusherConfigService.php†L18-L188】【F:public/js/pages/solicitudes/index.js†L18-L84】【F:public/js/pages/examenes/index.js†L8-L66】
- **Notificaciones de escritorio**. Si están habilitadas en configuración, el script pedirá permisos del navegador y cerrará automáticamente los avisos según el tiempo definido.【F:public/js/pages/solicitudes/index.js†L18-L96】【F:public/js/pages/examenes/index.js†L8-L85】
- **WhatsApp automatizado**. Cuando cambia el responsable, etapa o se agregan notas, tareas y adjuntos, `notifyWhatsAppEvent()` reúne los teléfonos relevantes y construye mensajes con enlaces directos a la solicitud. Solo se envían si hay cambios significativos y la integración está activa.【F:modules/solicitudes/services/SolicitudCrmService.php†L308-L338】
- **Canales futuros**. El `Messenger` soporta mensajes de texto, botones y listas, por lo que puedes diseñar journeys interactivos para confirmaciones, recordatorios o encuestas, reutilizando la normalización de plantillas y registros de conversación.【F:modules/WhatsApp/Services/Messenger.php†L34-L189】

## 5. Qué debe hacer cada usuario al iniciar y cerrar su turno

1. **Al iniciar**
   - Revisar el overview y las métricas SLA para detectar casos críticos.【F:public/js/pages/solicitudes/index.js†L159-L207】
   - Confirmar que el panel de notificaciones no tenga pendientes sin asignar; si los hay, abrir cada caso y actualizar responsable o etapa.【F:public/js/pages/solicitudes/index.js†L29-L84】【F:modules/solicitudes/services/SolicitudCrmService.php†L76-L205】
   - Validar si hay recordatorios programados próximos para organizar recursos quirúrgicos o de diagnóstico.【F:modules/solicitudes/services/SolicitudReminderService.php†L73-L190】

2. **Durante el día**
   - Mantener los filtros activos para su cartera y registrar cualquier contacto (nota) o documento recibido (adjunto).【F:modules/solicitudes/controllers/SolicitudController.php†L93-L161】【F:modules/solicitudes/services/SolicitudCrmService.php†L163-L303】
   - Crear tareas con responsables y recordatorios para asegurar cumplimiento de pendientes operativos.【F:modules/solicitudes/services/SolicitudCrmService.php†L189-L262】
   - Monitorear el panel de notificaciones y responder a toasts en tiempo real para evitar duplicidad de acciones.【F:public/js/pages/solicitudes/index.js†L29-L84】

3. **Al cerrar**
   - Cambiar etapas y estados según el avance real para mantener los tableros limpios y que las métricas se recalculen correctamente.【F:modules/solicitudes/controllers/SolicitudController.php†L105-L143】
   - Documentar acuerdos finales en notas y adjuntar respaldos (consentimientos, órdenes) antes de archivar el caso.【F:modules/solicitudes/services/SolicitudCrmService.php†L163-L303】
   - Verificar que no queden tareas críticas vencidas; de haberlas, reasignarlas o actualizar sus fechas con comentarios adicionales.【F:modules/solicitudes/services/SolicitudCrmService.php†L189-L262】

## 6. Cómo potenciar la automatización

- **Segmentar recordatorios**. Ajusta los escenarios y ventanas de `SolicitudReminderService` para enviar mensajes diferenciados (ej. recordatorios de consentimiento o vigencia de exámenes específicos).【F:modules/solicitudes/services/SolicitudReminderService.php†L25-L190】
- **Plantillas de WhatsApp**. Usa `Messenger::sendInteractiveButtons()` o `sendInteractiveList()` para captar respuestas rápidas (confirmar cirugía, reagendar, solicitar financiamiento) y registrarlas como notas automáticas mediante webhooks.【F:modules/WhatsApp/Services/Messenger.php†L78-L189】
- **Preferencias CRM por contexto**. Configura ordenamiento y límites de columnas distintos para cirugías y exámenes usando `LeadConfigurationService::getKanbanPreferences()` con el contexto adecuado, agilizando la vista de cada equipo.【F:modules/examenes/controllers/ExamenController.php†L100-L138】
- **Sincronización con el CRM global**. Aprovecha que cada guardado ejecuta `sincronizarLead()` para mantener una sola ficha comercial; garantiza que los responsables en CRM y solicitudes estén alineados, evitando esfuerzos duplicados.【F:modules/solicitudes/services/SolicitudCrmService.php†L93-L161】

Con esta guía, el equipo puede operar el CRM integrado para cirugías y exámenes, mantener comunicaciones oportunas y aprovechar las automatizaciones existentes para ofrecer una coordinación más ágil y trazable.
