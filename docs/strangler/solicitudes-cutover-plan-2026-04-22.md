# Solicitudes Laravel Cutover Plan

Fecha: 2026-04-22
Alcance: `Solicitudes` quirúrgicas
Objetivo: corte `todo o nada` hacia Laravel, sin compatibilidad temporal operativa con legacy.

## 1. Decisión

Para `Solicitudes`, Laravel debe pasar a ser:

- única UI operativa;
- única capa de lectura y escritura;
- única fuente de verdad para estado, checklist, CRM y conciliación;
- único emisor de eventos para Kanban, CRM, WhatsApp, Mailbox y recordatorios.

Legacy no debe quedar como plan B ni como puente funcional después del corte.

## 2. Estado actual

### 2.1 Qué ya está en Laravel

- UI principal v2:
  - [SolicitudesUiController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesUiController.php)
  - [v2-index.blade.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/resources/views/solicitudes/v2-index.blade.php)
  - [v2-index.js](/Users/jorgeluisdevera/PhpstormProjects/MedForge/public/js/pages/solicitudes/v2-index.js)
- Lecturas Kanban/tabla/CRM/conciliación:
  - [SolicitudesReadController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesReadController.php)
  - [SolicitudesReadParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesReadParityService.php)
- Escrituras de estado/CRM/checklist/tareas/notas/bloqueos/adjuntos/conciliación:
  - [SolicitudesWriteController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php)
  - [SolicitudesWriteParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesWriteParityService.php)
- Prefactura/derivación/cobertura mail:
  - [SolicitudesPrefacturaController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesPrefacturaController.php)
  - [SolicitudesPrefacturaService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesPrefacturaService.php)
- Rutas v2:
  - [solicitudes.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/routes/v2/solicitudes.php)

### 2.2 Qué sigue existiendo en legacy

- Controlador legacy de solicitudes:
  - [SolicitudController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/controllers/SolicitudController.php)
- Rutas legacy aún activas:
  - [routes.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/routes.php)
- Vistas legacy aún presentes:
  - [solicitudes.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/views/solicitudes.php)
  - [prefactura_detalle.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/views/prefactura_detalle.php)

### 2.3 Qué ya es “Laravel-first” y qué no

Laravel-first hoy:

- render del Kanban/tabla/conciliación;
- panel CRM;
- checklist CRM;
- tareas, notas, adjuntos, bloqueos;
- confirmación de conciliación;
- prefactura y cobertura mail en v2.
- autenticación y autorización operativa de `Solicitudes` en Laravel;
- rutas web y endpoints v2 de `Solicitudes` sobre sesión Laravel;
- login Laravel funcional en producción para `Solicitudes` con cookie dedicada:
  - `SESSION_COOKIE=medforge_session_v2`

No Laravel-first todavía:

- semántica completa del estado de negocio;
- integración operativa real con Mailbox;
- integración operativa real con WhatsApp V2.

## 3. Bloqueos duros para un corte total

### 3.1 Estado de auth/sesión para `Solicitudes`

`Solicitudes` ya no depende operativamente de `legacy.auth`, `legacy.permission` ni de `LegacySessionAuth` dentro del módulo.

Estado actual validado:

- rutas web de `Solicitudes` sobre `app.auth` y `app.permission`;
- endpoints v2 de `Solicitudes` ejecutándose con middleware `web`;
- frontend v2 enviando `X-CSRF-TOKEN`;
- login Laravel operativo en producción para `Solicitudes`;
- cookie de sesión dedicada para evitar colisión con cookies heredadas:
  - `SESSION_COOKIE=medforge_session_v2`

Implicación:

- Fase 2 está cumplida para `Solicitudes`;
- la dependencia legacy de auth/sesión sigue existiendo a nivel aplicación para otros módulos, no para el módulo `Solicitudes`.

### 3.2 La verdad del estado todavía está mezclada con lógica heredada

`SolicitudesWriteParityService` sigue usando el `estado` heredado de `solicitud_procedimiento` como insumo para calcular checklist y estado Kanban:

- [SolicitudesWriteParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesWriteParityService.php)
- [SolicitudesReadParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesReadParityService.php)

Implicación:

- el estado Laravel aún no es un modelo de dominio independiente;
- el “kanban state”, el checklist y el estado clínico/operativo siguen mezclados.

### 3.3 Completar tareas CRM no equivale a cambiar estado del Kanban

Hoy:

- `crm/checklist` sí recalcula el estado de la solicitud;
- `crm/tareas/estado` solo actualiza `crm_tasks` y devuelve resumen CRM.

Referencia:

- [SolicitudesWriteParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesWriteParityService.php:125)
- [SolicitudesWriteParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesWriteParityService.php:727)

Implicación:

- existen dos capas operativas:
  - checklist/estado;
  - tareas CRM.
- no hay una máquina de estados única.

### 3.4 Mailbox no está migrado a Laravel

La navegación Laravel apunta todavía a `/mailbox`, que vive en el módulo legacy:

- [MedforgeNavigation.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php:290)
- [modules/Mail/routes.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/routes.php)
- [MailboxController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/Controllers/MailboxController.php)

Implicación:

- `Solicitudes` puede mostrar historial de correos de cobertura;
- pero el buzón operativo sigue siendo legacy.

### 3.5 WhatsApp V2 no está conectado al CRM panel como flujo de trabajo

Hoy la integración visible en `Solicitudes` es por eventos de notificación:

- `whatsapp.handoff` en el panel de avisos.

Referencia:

- [v2-index.js](/Users/jorgeluisdevera/PhpstormProjects/MedForge/public/js/pages/solicitudes/v2-index.js:1269)

Implicación:

- existe integración de señales;
- no existe aún un caso de uso nativo del CRM panel tipo:
  - abrir conversación relacionada;
  - ver handoff del paciente;
  - registrar contacto WhatsApp como acción CRM.

## 4. Qué sí conviene conservar de legacy antes del corte

Solo como insumo de migración, no como runtime:

- mapa de estados usados por el negocio real;
- reglas de prefactura/derivación/cobertura que ya funcionan;
- catálogos y convenciones de permisos;
- comportamiento esperado del CRM offcanvas y del checklist.

No conviene conservar en runtime:

- sesión legacy;
- middleware legacy;
- rutas espejo legacy;
- bridges JS tipo `syncLegacyKanbanBridge()`.

Referencia:

- [v2-index.js](/Users/jorgeluisdevera/PhpstormProjects/MedForge/public/js/pages/solicitudes/v2-index.js:460)

## 5. Fuente de verdad objetivo en Laravel

### 5.1 Modelo objetivo

Laravel debe definir explícitamente:

- `estado de negocio de solicitud`
- `pipeline CRM`
- `checklist operativo`
- `tareas CRM`
- `estado de conciliación`
- `estado de cobertura/mail`

Estas piezas no deben inferirse desde legacy ni mezclarse entre sí.

### 5.2 Reglas objetivo

- `checklist` mueve el `estado operativo`;
- `tareas CRM` acompañan, pero no reemplazan el estado;
- `conciliación` puede completar checklist/estado bajo reglas explícitas;
- `prefactura/cobertura` actualizan evidencias y estados definidos;
- `WhatsApp` y `Mailbox` consumen la capa Laravel, no la determinan.

## 6. Criterio de corte

El corte solo debe hacerse cuando estas condiciones sean verdaderas:

1. `Solicitudes` ya no usa `legacy.auth` ni `legacy.permission`.
2. El login Laravel resuelve usuario, permisos y navegación de `Solicitudes` sin depender de `PHPSESSID` heredada.
3. Todas las escrituras de solicitudes ocurren solo en controladores/servicios Laravel.
4. La máquina de estados nativa está documentada y probada.
5. `crm/tareas/estado`, `crm/checklist` y `actualizar-estado` ya tienen semántica coherente entre sí.
6. Prefactura, derivación y cobertura mail funcionan solo por Laravel.
7. Conciliación funciona solo por Laravel.
8. Las alertas en tiempo real salen solo desde Laravel.
9. La UI legacy de solicitudes y sus endpoints quedan fuera de operación.

## 7. Orden exacto de migración

### Fase 1. Congelar alcance

- No agregar nuevas funciones en legacy `Solicitudes`.
- No aceptar nuevas dependencias a `LegacySessionAuth` dentro del módulo.
- Declarar a Laravel como único destino de nuevos cambios.

### Fase 2. Separar autenticación y permisos

- Estado: `cerrada para Solicitudes`

Hecho:

- `Solicitudes` web y v2 ya usan `app.auth` y `app.permission`;
- controladores de `Solicitudes` ya no dependen de `LegacySessionAuth`;
- `RequireAppSession` y `RequireAppPermission` ya no bootstrappean auth desde legacy;
- login Laravel ya no autoentra desde cookie legacy;
- producción validada con cookie dedicada:
  - `SESSION_COOKIE=medforge_session_v2`

Pendiente fuera del alcance de esta fase:

- otros módulos siguen en `legacy.auth` / `legacy.permission`;
- el bridge de compatibilidad del login sigue habilitado para sostener esos módulos.

### Fase 3. Formalizar la máquina de estados

- Definir una tabla/regla oficial para estado operativo.
- Dejar de usar `legacyState` como base de cálculo.
- Documentar transiciones:
  - kanban
  - checklist
  - tareas CRM
  - conciliación
  - completado

Resultado esperado:

- una sola verdad de estado;
- menos lógica implícita heredada.

Estado: `cerrada`

### Fase 4. Unificar CRM y Kanban

- Hacer que una tarea CRM ligada a una etapa actualice la capa de estado cuando corresponda.
- Evitar que `crm_tasks` y `solicitud_checklist` compitan como fuentes de verdad.

Resultado esperado:

- el panel CRM y el kanban reflejan exactamente la misma realidad operativa.

Estado: `cerrada en backend`

Implementado:

- `crm_tasks.checklist_slug` ya se expone en lectura CRM;
- al actualizar el estado de una tarea CRM ligada a checklist:
  - `completada` completa la etapa operativa equivalente;
  - `pendiente` reabre la etapa operativa equivalente;
- la sincronización usa la misma transición canónica del checklist (`transitionChecklistStage`), no una lógica paralela.

Pendiente fuera del alcance de este cierre:

- enriquecer UI/auditoría para mostrar explícitamente que la transición vino de una tarea CRM;
- mapear otros estados de tarea (`en_progreso`, `cancelada`) a semántica operativa solo si negocio lo necesita.

### Fase 5. Cerrar integraciones Laravel

- Definir integración real de `Solicitudes` con WhatsApp V2.
- Definir integración real de `Solicitudes` con Mailbox.
- Decidir si Mailbox se migra o si `Solicitudes` corta cualquier dependencia con él.

Resultado esperado:

- `Solicitudes` ya no depende de módulos runtime legacy para operación.

Estado: `parcial`

Implementado:

- el CRM de `Solicitudes` ya expone `whatsapp_context` con búsqueda o conversación enlazada por teléfono/HC;
- el header del CRM ya muestra acceso directo a `/v2/whatsapp/chat` por conversación o búsqueda;
- `Solicitudes` ya puede operar cobertura mail desde Laravel y mostrar historial en `solicitud_mail_log` sin requerir navegación a `Mailbox`.
- rutas web y API v2 de `WhatsApp` ya quedaron montadas sobre `app.auth` / `app.permission` y sesión `web`, alineadas con el acceso Laravel de `Solicitudes`.

Decisión actual:

- `Mailbox` no es requisito runtime para `Solicitudes`;
- el módulo de `Solicitudes` conserva solo historial/evidencia de correos de cobertura y su envío desde Laravel;
- migrar o no el buzón completo queda como frente aparte, no como bloqueo del cutover de `Solicitudes`.

Pendiente:

- retirar el uso residual de helpers legacy dentro de algunos controladores de `WhatsApp` si se quiere dejar ese módulo completamente Laravel-native por implementación, no solo por entrada;
- si negocio lo pide, agregar acciones CRM sobre conversación WhatsApp dentro del panel, no solo navegación contextual.

### Fase 6. Ejecutar corte

- apagar rutas legacy de `modules/solicitudes/routes.php`;
- eliminar vistas legacy de solicitudes de la navegación;
- remover bridges JS heredados;
- dejar solo `/v2/solicitudes`.

## 8. Go / No-Go

### Go

- Laravel autentica y autoriza sin leer sesión legacy.
- UI v2 cubre todo el inventario funcional.
- no quedan escrituras activas en legacy.
- se validó operación diaria:
  - cambio de estado
  - checklist
  - CRM
  - conciliación
  - prefactura
  - cobertura mail
  - turnero

### No-Go

- el módulo sigue leyendo `PHPSESSID`;
- Mailbox sigue siendo requisito operativo no migrado;
- la verdad del estado sigue dependiendo de `legacyState`;
- tareas CRM y checklist siguen divergentes.

## 9. Recomendación

Si el enfoque es `todo o nada`, el primer trabajo no debe ser más UI.

El primer trabajo debe ser:

1. sacar `Solicitudes` de `LegacySessionAuth`;
2. definir máquina de estados Laravel-first;
3. unificar checklist, tareas y kanban;
4. recién después cerrar WhatsApp/Mailbox sobre esa base.

Ese orden reduce deuda y evita que el corte se vuelva una migración infinita.
