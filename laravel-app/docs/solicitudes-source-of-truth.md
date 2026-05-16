# Solicitudes: Fuentes De Verdad

## Estado actual

El modulo `Solicitudes` ya opera principalmente desde Laravel para lectura y escritura de CRM operativo. La UI `v2` consume controladores Laravel y la mayor parte de la logica de negocio se concentra en:

- `App\Modules\Solicitudes\Services\SolicitudesReadParityService`
- `App\Modules\Solicitudes\Services\SolicitudesWriteParityService`
- `App\Modules\Solicitudes\Services\SolicitudesCommunicationService`

Las rutas alias `/api/...` y `kanban_data.php` ya no representan una segunda logica: hoy terminan entrando a los mismos controladores Laravel del modulo.

## Fuente de verdad por bloque

### Solicitud operativa

- Lectura base: `solicitud_procedimiento`
- Datos paciente: `patient_data`
- Fecha clinica de apoyo: `consulta_data`
- Servicio lector principal:
  - `SolicitudesReadParityService::kanbanData()`
  - `SolicitudesReadParityService::crmResumen()`
  - `SolicitudesReadParityService::conciliacionCirugiasMes()`
- Servicio escritor principal:
  - `SolicitudesWriteParityService::actualizarEstado()`
  - `SolicitudesWriteParityService::guardarDetallesCirugia()`

Conclusión:
`solicitud_procedimiento.estado` sigue siendo la columna operativa principal del estado kanban y ya la persiste Laravel.

### Checklist operativo

- Tabla fuente: `solicitud_checklist`
- Orquestacion:
  - `SolicitudesWriteParityService::transitionChecklistStage()`
  - `SolicitudesWriteParityService::crmActualizarChecklist()`
  - `SolicitudesReadParityService::queryChecklistRows()`
- Motor de interpretacion:
  - `SolicitudesStateMachineService`

Conclusión:
La verdad del checklist ya no debe inferirse desde UI ni desde legacy. La tabla `solicitud_checklist` y la `state machine` Laravel mandan.

### Tareas CRM ligadas al checklist

- Tabla fuente: `crm_tasks`
- Scope valido:
  - `source_module = "solicitudes"`
  - `source_ref_id = solicitud_id`
- Lectura:
  - `SolicitudesReadParityService::queryCrmTareas()`
- Escritura:
  - `SolicitudesWriteParityService::crmGuardarTarea()`
  - `SolicitudesWriteParityService::syncChecklistLinkedTasks()`

Conclusión:
Las tareas manuales y las tareas materializadas desde checklist viven en `crm_tasks`. El checklist ya sincroniza tareas automaticamente desde Laravel.

### Estado operativo final

- Persistencia final: `solicitud_procedimiento.estado`
- Derivacion del estado:
  - checklist persistido
  - tareas ligadas al checklist
  - `SolicitudesStateMachineService`
- Metodo critico:
  - `SolicitudesWriteParityService::persistOperationalState()`

Conclusión:
El estado visible del kanban ya se recalcula desde Laravel y se persiste otra vez en `solicitud_procedimiento.estado`.

### CRM detalle

- Tabla principal: `solicitud_crm_detalles`
- Tablas secundarias:
  - `solicitud_crm_notas`
  - `solicitud_crm_adjuntos`
  - `solicitud_crm_meta`
  - `crm_leads`
  - `users`
- Lectura:
  - `SolicitudesReadParityService::queryCrmDetalle()`
- Escritura:
  - `SolicitudesWriteController::crmGuardarDetalles()`
  - `SolicitudesWriteParityService` para detalles y meta

Conclusión:
La verdad del panel CRM ya vive en `solicitud_crm_detalles` y tablas hermanas, no en estructuras legacy de vista.

### Propuestas CRM

- Tabla principal: `crm_proposals`
- Tabla items: `crm_proposal_items`
- Relacion con solicitud:
  - por `crm_lead_id` asociado en `solicitud_crm_detalles`
- Lectura:
  - `SolicitudesReadParityService::queryCrmPropuestas()`
- Escritura:
  - `SolicitudesWriteParityService::crmCrearPropuesta()`
- Entrega:
  - `CrmProposalController`
  - `CrmProposalService`
  - `CrmProposalPdfService`

Conclusión:
La verdad de propuestas ya es compartida Laravel CRM, no un artefacto del legacy de solicitudes.

### Conciliacion de cirugias

- Lectura base:
  - `SolicitudesReadParityService::conciliacionCirugiasMes()`
- Confirmacion manual:
  - `SolicitudesWriteParityService::confirmarConciliacionCirugia()`
- Persistencia de confirmacion:
  - `solicitud_crm_meta`
  - keys `cirugia_confirmada_*`
- Efecto operativo:
  - completa checklist
  - completa tareas de conciliacion
  - persiste estado terminal en `solicitud_procedimiento.estado`

Conclusión:
La conciliacion ya tiene su propia fuente de verdad Laravel en meta + checklist + tasks + estado final.

### Comunicacion

- WhatsApp de solicitud:
  - `SolicitudesCommunicationService::sendWhatsapp()`
  - usa `WhatsappConversation` + `ConversationWriteService`
- Correo manual del panel:
  - `SolicitudesCommunicationService::sendEmail()`
  - registra en `solicitud_mail_log`
- Proposal por correo:
  - `CrmProposalController::sendEmail()`
  - usa `NotificationMailer`

Conclusión:
La comunicacion todavia no tiene una sola fuente de verdad tecnica. Operativamente ya sale desde Laravel, pero existen dos motores de correo distintos.

## Rutas Laravel canonicas

Las rutas que hoy deben considerarse canonicas para `Solicitudes` son:

- `GET|POST /v2/solicitudes/kanban-data`
- `GET /v2/solicitudes/{id}/crm`
- `GET /v2/solicitudes/conciliacion-cirugias`
- `POST /v2/solicitudes/actualizar-estado`
- `POST /v2/solicitudes/{id}/crm/checklist`
- `POST /v2/solicitudes/{id}/crm/tareas`
- `POST /v2/solicitudes/{id}/crm/propuestas`
- `POST /v2/solicitudes/{id}/conciliacion-cirugia/confirmar`
- `POST /v2/solicitudes/{id}/crm/whatsapp`
- `POST /v2/solicitudes/{id}/crm/email`

Los endpoints alias `/api/solicitudes/...` ya son compatibilidad de entrada, no una fuente alternativa de negocio.

## Puntos grises pendientes

### 1. Correo dividido en dos motores

Hoy conviven:

- `SolicitudesCommunicationService::sendEmail()` usando `Mail::raw`
- `CrmProposalController::sendEmail()` usando `NotificationMailer`

Riesgo:
No hay una unica politica SMTP, trazabilidad ni manejo de errores.

Decisión recomendada:
Unificar correo operativo de `Solicitudes` y `Proposal` sobre `NotificationMailer`.

### 2. Fallback de lectura CRM

`SolicitudesReadParityService::queryCrmDetalle()` cae a `queryCrmDetalleFallback()` si falla la consulta completa.

Riesgo:
El panel puede degradar silenciosamente y ocultar que faltan joins, columnas o tablas.

Decisión recomendada:
Mantener el fallback solo mientras termina la fase de endurecimiento; luego registrar y eliminar degradaciones silenciosas.

### 3. Estado legado como fallback semantico

Aunque Laravel ya persiste el estado, el sistema todavia usa:

- `legacyStateBySolicitud()`
- `operationalFallbackState()`

como apoyo para reconstruir checklist/contexto cuando faltan filas persistidas.

Riesgo:
Si falta materializacion del checklist, el comportamiento depende del estado heredado guardado en la solicitud.

Decisión recomendada:
Mantener temporalmente mientras se garantice backfill completo y materializacion consistente de checklist.

### 4. Alias de rutas heredadas

Persisten aliases:

- `/api/solicitudes/kanban_data.php`
- `/api/solicitudes/actualizar_estado.php`
- otras variantes `/api/...`

Riesgo:
No rompen la verdad, pero mantienen superficie legacy innecesaria.

Decisión recomendada:
Cuando clientes internos ya usen `/v2/...`, despublicar aliases progresivamente.

## Inventario actual de aliases y consumidores

### Alias que hoy entran a Laravel

Todos estos endpoints ya caen en los mismos controladores Laravel del modulo:

- Legacy estilo PHP:
  - `/api/solicitudes/kanban_data.php`
  - `/api/solicitudes/dashboard_data.php`
  - `/api/solicitudes/actualizar_estado.php`
  - `/api/solicitudes/turnero_llamar.php`
  - `/api/solicitudes/estado.php`
- Alias limpios:
  - `/api/solicitudes/kanban`
  - `/api/solicitudes/dashboard`
  - `/api/solicitudes/turnero`
  - `/api/solicitudes/crm/*`
  - `/api/solicitudes/conciliacion-cirugias`
  - `/api/solicitudes/estado`
  - `/api/solicitudes/estado/actualizar`
  - `/api/solicitudes/turnero/llamar`
  - `/api/solicitudes/derivacion/guardar`
  - `/api/solicitudes/{id}/cirugia`
  - `/api/solicitudes/{id}/crm/*`
  - `/api/solicitudes/{id}/conciliacion-cirugia/confirmar`

Conclusión:
Estos aliases ya no son otra logica. Son solo puertas de entrada alternativas hacia Laravel.

### Consumidores locales detectados

- `modules/solicitudes/views/solicitudes.js`
  - migrado a `/v2/solicitudes/kanban-data`
- `modules/examenes/views/examenes.js`
  - migrado a `/v2/solicitudes/kanban-data`
- `public/js/pages/solicitudes/index.js`
  - ya prioriza solo `/solicitudes/api/estado` dentro del stack V2
- `public/js/pages/solicitudes/kanban/modalDetalles/api.js`
  - ya prioriza solo `/solicitudes/api/estado` dentro del stack V2
- `public/js/pages/solicitudes/kanban/config.js`
  - ya no trata `/api/solicitudes` como ruta normal del frontend V2
- `modules/solicitudes/routes.php`
  - conserva redirect server-side de `/solicitudes/api/estado` hacia `/v2/solicitudes/api/estado`

Conclusión:
El bloqueo principal ya no es frontend V2. Lo que queda es compatibilidad server-side y observar uso real antes de retirar aliases.

### Endurecimiento agregado

Desde Laravel ahora los aliases `/api/solicitudes/...` quedan marcados con:

- header `X-Legacy-Alias: 1`
- header `X-Legacy-Alias-Type: php|clean`
- header `X-Canonical-Path: /v2/...`
- header `Deprecation: true`
- log `solicitudes.legacy_alias_used`

Conclusión:
Ya se puede medir uso real antes de apagar rutas.

## Criterio de cierre de la fase

La fase `Fuentes de verdad` de `Solicitudes` se puede considerar cerrada cuando:

- todas las escrituras operativas del modulo salen por servicios Laravel
- `solicitud_procedimiento.estado` solo cambia por Laravel
- checklist y tareas ya no requieren fallback semantico heredado
- correo del panel y proposal usan el mismo motor de envio
- los aliases legacy dejan de ser necesarios para operacion interna

## Siguiente cierre recomendado

Orden de trabajo para terminar esta fase:

1. Unificar correo de `SolicitudesCommunicationService` con `NotificationMailer`
2. Auditar y reducir `fallbacks` silenciosos en `SolicitudesReadParityService`
3. Verificar que `solicitud_checklist` este completo para todas las solicitudes activas
4. Inventariar consumidores de rutas alias `/api/solicitudes/...`
5. Desactivar aliases cuando los clientes internos ya esten migrados

## Estado del plan

- `1.` Cerrado
- `2.` Cerrado parcialmente con trazabilidad y endurecimiento
- `3.` En progreso
- `4.` Cerrado
- `5.` En progreso
