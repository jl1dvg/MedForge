# Plan de Migración de WhatsApp a `laravel-app`

Fecha: 2026-04-10

## Objetivo

Migrar el módulo de WhatsApp desde el stack legacy de MedForge hacia `laravel-app` sin perder operación clínica ni reglas de handoff, y aprovechando patrones de producto y UX de `whatsbox` cuando aporten valor real.

La meta no es clonar `whatsbox`. La meta es:

- conservar la lógica operativa que MedForge ya tiene en producción
- mover el dominio y la operación a Laravel
- mejorar la experiencia del chat, templates, agentes, estadísticas y flowmaker
- dejar una base mantenible para campañas, automatizaciones y futuras integraciones

## Estado actual

### 1. Legacy MedForge

El módulo legacy ya cubre operación real:

- webhook de WhatsApp Cloud API
- conversaciones y mensajes
- bandeja/chat operativo
- envío manual de texto, media, interactivos y templates
- handoff a humano
- asignación y transferencia
- presencia de agentes
- KPIs de WhatsApp
- búsqueda de pacientes y relación con HC

Referencias:

- `modules/WhatsApp/README.md`
- `modules/WhatsApp/routes.php`
- `modules/WhatsApp/Controllers/ChatController.php`
- `modules/WhatsApp/Controllers/KpiController.php`
- `modules/WhatsApp/Controllers/TemplateController.php`
- `modules/WhatsApp/Services/ConversationService.php`
- `modules/WhatsApp/Services/HandoffService.php`
- `modules/WhatsApp/Services/Messenger.php`
- `modules/WhatsApp/Services/TemplateManager.php`

### 2. Whatsbox

`whatsbox` aporta patrones útiles de arquitectura y UX:

- módulo `Wpbox` para chat, contactos, templates, replies, campañas y reportes
- módulo `Agents`
- módulo `Flowmaker`
- módulo `Dashboard`
- chat con filtros, contadores, asignación rápida y side apps
- campañas/broadcast
- respuestas rápidas y notas
- reportes por tráfico y por agente

Referencias:

- `../whatsbox/Modules/Wpbox/Routes/web.php`
- `../whatsbox/Modules/Wpbox/Http/Controllers/ChatController.php`
- `../whatsbox/Modules/Wpbox/Http/Controllers/TemplatesController.php`
- `../whatsbox/Modules/Flowmaker/Http/Controllers/FlowsController.php`

### 3. Laravel App

`laravel-app` ya tiene base de dominio, pero todavía no el módulo operativo completo:

- tablas core de conversaciones, mensajes, inbox, handoffs y presencia
- modelos para core WhatsApp
- modelos para templates versionados
- modelos para autoresponder y flowmaker
- catálogo de permisos y navegación preparados para WhatsApp

Referencias:

- `laravel-app/database/migrations/2026_03_03_171500_create_whatsapp_core_tables.php`
- `laravel-app/app/Models/WhatsappConversation.php`
- `laravel-app/app/Models/WhatsappMessage.php`
- `laravel-app/app/Models/WhatsappMessageTemplate.php`
- `laravel-app/app/Models/WhatsappTemplateRevision.php`
- `laravel-app/app/Models/WhatsappAutoresponderFlow.php`
- `laravel-app/app/Modules/Shared/Support/LegacyPermissionCatalog.php`
- `laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php`

## Principio rector

La migración se hará con enfoque strangler:

- primero paridad de backend
- luego paridad operativa
- luego mejora de UX
- después producto nuevo

No se migra todo a la vez. Primero se estabiliza el core.

## Qué conservar de MedForge

Estas capacidades son obligatorias y no deben degradarse:

- vínculo conversación <-> paciente <-> HC
- reglas de ventana de 24h y uso de plantilla oficial
- handoff con rol, notas, cola y TTL
- restricción de respuesta por asignación
- presencia de agentes
- KPIs operativos de atención
- búsqueda de pacientes desde el chat
- permisos finos por rol

## Qué imitar de Whatsbox

Estas capacidades sí conviene imitar o reinterpretar:

- inbox más claro y más rápido de operar
- filtros de lista tipo `mis chats`, `sin leer`, `resueltos`, `todos`
- contadores visibles por estado
- acciones rápidas de asignación y cambio de idioma/estado
- panel lateral de contexto y herramientas
- respuestas rápidas y notas internas
- campañas/broadcast
- reportes por agente y tráfico
- flowmaker visual como herramienta de edición
- estructura modular separada por dominios

## Qué no copiar de forma ciega

- cualquier simplificación que rompa el modelo clínico de MedForge
- cualquier flujo que ignore HC, paciente o contexto asistencial
- cualquier regla de asignación más simple que el handoff actual
- cualquier dashboard placeholder que reemplace métricas reales por métricas superficiales

## Matriz de capacidades

| Capacidad | Legacy MedForge | Whatsbox | Laravel App | Acción |
| --- | --- | --- | --- | --- |
| Webhook inbound/outbound | Sí | Sí | Parcial | Migrar primero |
| Conversaciones y mensajes | Sí | Sí | Parcial | Migrar primero |
| Chat operativo | Sí | Sí | No | Rehacer en Laravel |
| Asignación/handoff | Sí | Parcial | Parcial | Migrar y mejorar |
| Presencia de agentes | Sí | Parcial | Parcial | Migrar |
| Templates oficiales | Sí | Sí | Parcial | Migrar y mejorar editor |
| Respuestas rápidas/notas | Parcial | Sí | No | Agregar |
| KPI dashboard | Sí | Parcial | No | Migrar |
| Reportes por agente/tráfico | Parcial | Sí | No | Agregar después de KPI |
| Flowmaker visual | Parcial | Sí | Parcial modelo | Construir sobre modelos existentes |
| Campañas/broadcast | No claro | Sí | No | Fase posterior |
| Contexto paciente/HC | Sí | No | Parcial | Mantener como ventaja propia |

## Arquitectura objetivo en `laravel-app`

Se propone separar el módulo en dominios claros:

- `Whatsapp/Core`
  - conversaciones
  - mensajes
  - estados de entrega
  - media
- `Whatsapp/Webhooks`
  - verify webhook
  - inbound processing
  - status updates
- `Whatsapp/Chat`
  - inbox
  - detalle de conversación
  - envío manual
  - acciones operativas
- `Whatsapp/Agents`
  - presencia
  - asignación
  - handoff
  - supervisión
- `Whatsapp/Templates`
  - sync
  - CRUD funcional
  - revisiones
  - preview
- `Whatsapp/Reports`
  - KPIs
  - drilldown
  - reportes por agente
- `Whatsapp/Automation`
  - autoresponder
  - sesiones
  - flow versions
  - schedules
- `Whatsapp/Flowmaker`
  - editor visual
  - publicación
  - validación

## Estrategia de datos

### Decisión inicial

Usar las tablas nuevas de Laravel como destino principal, pero no cortar legacy hasta tener:

- backfill validado
- doble lectura o verificación de paridad
- smoke tests de webhook, chat y templates

### Reglas

- una sola fuente de verdad por fase
- no mezclar escrituras legacy y Laravel indefinidamente
- durante la transición, permitir lectura comparativa cuando haga falta

## Flags de activación

La fase 1 debe permanecer protegida por flags de entorno para evitar cortes prematuros.

Variables definidas en `laravel-app/.env.example`:

- `WHATSAPP_LARAVEL_ENABLED`
- `WHATSAPP_LARAVEL_FALLBACK_TO_LEGACY`
- `WHATSAPP_LARAVEL_COMPARE_WITH_LEGACY`
- `WHATSAPP_LARAVEL_UI_ENABLED`
- `WHATSAPP_LARAVEL_API_READ_ENABLED`
- `WHATSAPP_LARAVEL_API_WRITE_ENABLED`
- `WHATSAPP_LARAVEL_WEBHOOK_ENABLED`

Regla recomendada:

- mantener `WHATSAPP_LARAVEL_ENABLED=false` hasta tener paridad básica
- activar primero `WHATSAPP_LARAVEL_API_READ_ENABLED=true` para comparar lectura
- activar `WHATSAPP_LARAVEL_UI_ENABLED=true` solo cuando el inbox v2 ya sea usable
- mantener `WHATSAPP_LARAVEL_FALLBACK_TO_LEGACY=true` mientras exista riesgo operativo

## Verificación por terminal

Comandos base para esta fase:

```bash
cd laravel-app
php8.3-cli artisan whatsapp:phase1-smoke
php8.3-cli artisan route:list --path=whatsapp
php8.3-cli artisan test --filter=Whatsapp
```

Objetivo de estos checks:

- confirmar flags activos
- confirmar rutas registradas
- verificar contrato base del API de conversaciones
- confirmar fallback a legacy cuando Laravel aún no debe tomar tráfico

Estado actual de esta fase:

- lectura de conversaciones en Laravel bajo flag
- escritura manual de mensajes de texto en Laravel bajo flag
- webhook `GET/POST` en Laravel bajo flag
- verify token con compatibilidad de configuración
- persistencia entrante idempotente y actualización de estados de entrega
- pruebas de paridad en terminal con `php artisan test --filter=Whatsapp`

### Backfill mínimo requerido

- `whatsapp_conversations`
- `whatsapp_messages`
- `whatsapp_contact_consent`
- `whatsapp_inbox_messages`
- `whatsapp_handoffs`
- `whatsapp_handoff_events`
- `whatsapp_agent_presence`

### Datos adicionales a mapear

- usuarios con `whatsapp_number`
- `whatsapp_notify`
- relaciones de rol y permisos
- templates y revisiones
- sesiones de autoresponder y flujos activos

## Roadmap por fases

## Fase 0. Alineación y contrato

Objetivo:

- fijar alcance
- definir tablas
- definir ownership de módulos
- fijar criterios de paridad

Entregables:

- este plan
- matriz de endpoints legacy
- inventario de tablas y settings
- definición de fuente de verdad por entidad

## Fase 1. Core WhatsApp en Laravel

Objetivo:

- tener backend Laravel capaz de operar conversaciones

Incluye:

- controllers y services para conversaciones
- recepción webhook
- persistencia de inbound/outbound/status
- envío por Cloud API
- repositorios/casos de uso de mensajes y media
- endpoints base de conversaciones y detalle

Criterio de salida:

- Laravel puede recibir y persistir mensajes reales
- Laravel puede listar conversaciones y detalle
- Laravel puede enviar mensajes salientes

## Fase 2. Chat operativo

Objetivo:

- reemplazar el chat legacy con una UI Laravel usable

Base funcional:

- lista de conversaciones
- búsqueda por nombre, HC y número
- filtros de cola
- detalle de conversación
- envío manual
- adjuntos
- apertura externa en WhatsApp

Mejoras a imitar de `whatsbox`:

- contadores por estado
- `mis chats`
- `sin leer`
- `resueltos`
- panel lateral de herramientas
- acciones rápidas

Criterio de salida:

- operadores pueden trabajar el turno completo desde `laravel-app`

Estado 2026-04-12:

- inbox v2 operativo disponible en `/v2/whatsapp/chat` bajo flag
- filtros operativos activos: `todos`, `sin leer`, `mis chats`, `en cola`, `resueltos`
- contadores por estado activos en el API y en la UI
- abrir una conversación en Laravel ya marca lectura como en legacy
- envío manual de texto integrado desde la vista v2
- reglas legacy de ownership activas al responder
- acciones operativas activas en la UI v2: `tomar`, `transferir`, `cerrar`
- apertura externa a WhatsApp disponible desde la conversación

Mejoras no bloqueantes posteriores al cierre de Fase 2:

- adjuntos y media nativos en la UI v2
- panel lateral clínico más completo con contexto paciente/HC
- refresco en tiempo real y polling/pusher
- más accesos rápidos inspirados en `whatsbox`

## Fase 3. Agentes, presencia y handoff

Objetivo:

- mantener intacta la lógica operativa de toma, derivación y supervisión

Incluye:

- presencia `available`, `away`, `offline`
- tomar chat
- asignar
- transferir
- cerrar
- reencolar handoffs vencidos
- auditoría de eventos

Mejoras a imitar:

- mejor visualización de ownership
- contadores por agente
- supervisor con filtros por equipo/rol

Criterio de salida:

- cero regresión en reglas de handoff respecto a legacy

Estado 2026-04-12:

- presencia de agente disponible en la UI y API v2 con estados `available`, `away`, `offline`
- reencolado de handoffs vencidos disponible para supervisor desde Laravel
- auditoría de expiración y reencolado persistida en `whatsapp_handoff_events`
- inbox v2 ya expone ownership legible en lista y detalle
- filtros de supervisor activos por agente y rol para acercar la operación a legacy
- resumen de carga por agente visible en inbox supervisor
- comando `php artisan whatsapp:handoff-requeue-expired` disponible para validación y automatización del TTL

## Fase 4. Templates

Objetivo:

- mover gestión completa de plantillas a Laravel

Incluye:

- sincronización con Meta
- listado con filtros
- creación y edición
- preview
- soporte de variables
- soporte de media
- revisiones/versionado

Mejoras a imitar:

- flujo de sync más explícito
- uploads guiados
- preview más cercano al chat real

Criterio de salida:

- operadores y admins ya no necesitan legacy para templates

Estado 2026-04-12:

- `/v2/whatsapp/templates` ya dejó de ser placeholder y muestra catálogo real en Laravel
- listado con filtros activos por búsqueda, estado, categoría e idioma
- preview básico disponible sobre la revisión actual o el payload remoto
- `POST /v2/whatsapp/api/templates/sync` disponible para sincronización manual con Meta
- persistencia al cache local soportada sobre `whatsapp_message_templates` y `whatsapp_template_revisions`
- creación de borradores locales activa en `POST /v2/whatsapp/api/templates`
- edición de revisiones locales activa en `POST /v2/whatsapp/api/templates/{templateId}`
- publicación manual de borradores a Meta activa en `POST /v2/whatsapp/api/templates/{templateId}/publish`
- la UI v2 ya permite crear, editar y publicar borradores sin volver a legacy
- headers multimedia (`image`, `video`, `document`) ya pueden vivir en borrador local y verse en preview
- la publicación de headers multimedia ya usa el mismo payload compatible con Meta desde Laravel
- las plantillas sincronizadas o remotas ya no se editan en sitio: primero se clonan a borrador local
- `POST /v2/whatsapp/api/templates/clone` disponible para derivar borradores editables desde una plantilla remota o sincronizada
- la UI ya distingue `Remota aprobada`, `Sincronizada desde Meta`, `Borrador local` y `Borrador publicado`
- el preview ya expone historial de revisiones local y versión actual

Estado de cierre:

- Fase 4 cerrada operativamente: operadores y admins ya pueden listar, sincronizar, clonar, versionar y publicar plantillas desde Laravel

Ajustes menores posteriores:

- ajustes finos de UX del builder y validaciones por tipo de botón/header

## Fase 5. KPI y reportes

Objetivo:

- reponer y mejorar visibilidad operativa

Incluye:

- KPIs actuales de MedForge
- drilldown
- reportes por agente
- tráfico por conversación
- exportación

Mejoras a imitar:

- reportes operativos rápidos del estilo `whatsbox`

Estado actual:

- Fase 5 cerrada operativamente
- `GET /v2/whatsapp/dashboard` ya dejó de ser placeholder y renderiza dashboard KPI sobre datos Laravel
- `GET /v2/whatsapp/api/kpis` disponible para resumen y tendencias
- `GET /v2/whatsapp/api/kpis/drilldown` disponible para drilldown por métrica soportada
- `GET /v2/whatsapp/api/kpis/export` disponible para exportación CSV
- el dashboard actual ya expone resumen operativo, series por período, atención humana por agente, handoffs por equipo y carga por agente
- la implementación se validó contra SQLite y MySQL para evitar divergencias entre tests y producción

KPIs ya repuestos en Laravel:

- conversaciones nuevas
- mensajes inbound y outbound
- personas que escribieron
- conversaciones y personas con atención humana
- conversaciones y personas perdidas
- tasa de atención
- tasa de pérdida
- promedio de primera respuesta humana
- conversaciones abandonadas y resueltas
- pico de conversaciones abiertas
- cola viva: en cola, asignadas y vencidas
- ventana de 24h, necesidad de plantilla y pendientes por respuesta a plantilla
- cumplimiento SLA por asignación
- transferencias de handoff

Reportes y salidas ya cubiertas:

- exportación CSV desde Laravel
- drilldown para inbound/outbound, conversaciones nuevas, atendidas, perdidas, cola viva, necesidad de plantilla, SLA y transferencias
- paneles rápidos de supervisor para salud operativa, ventana de 24h y accesos directos a drilldown

Criterio de salida:

- dashboard y análisis operan sobre datos Laravel

## Fase 6. Flowmaker y automatización

Objetivo:

- consolidar autoresponder y dar una herramienta visual usable

Nota de estrategia:

- no se reemplaza el autorespondedor actual de golpe
- se conserva el runtime productivo y se migra por compatibilidad hacia Laravel
- estrategia detallada en `docs/strangler/whatsapp-autoresponder-flowmaker-strategy-2026-04-12.md`

Incluye:

- editor visual
- publicación por versiones
- validaciones
- programación y ventanas horarias
- filtros de audiencia
- sesiones activas

Mejoras a imitar:

- edición visual por nodos
- experiencia más simple para negocio

Condición:

- no empezar esta fase sin chat, handoff y templates estabilizados

Estado actual:

- Fase 6 iniciada
- `GET /v2/whatsapp/flowmaker` ya muestra overview real del flujo activo, versiones recientes y sesiones activas
- `GET /v2/whatsapp/api/flowmaker/contract` disponible para leer contrato y esquema actual desde Laravel
- `POST /v2/whatsapp/api/flowmaker/publish` disponible para publicar nuevas versiones en las tablas canónicas
- la publicación ya escribe en `whatsapp_autoresponder_flows`, `whatsapp_autoresponder_flow_versions`, `whatsapp_autoresponder_steps`, `whatsapp_autoresponder_step_actions` y `whatsapp_autoresponder_step_transitions`
- el runtime productivo legacy todavía se mantiene como ejecutor del webhook; Laravel por ahora cubre lectura, publicación y observabilidad básica

Pendiente para cierre:

- adapter de runtime con paridad frente a legacy
- comparación controlada Laravel vs legacy para sesiones y escalado a humano
- flags específicos de automatización para shadow mode y fallback
- editor visual más completo sobre el contrato actual

## Fase 7. Campañas y mejoras de producto

Objetivo:

- sumar capacidades que hoy no son core operativo pero sí valiosas

Incluye:

- campañas/broadcast
- respuestas rápidas
- notas internas
- side apps o panel contextual
- integración con CRM

## MVP de migración

El MVP real para poder empezar corte de tráfico debe incluir:

- webhook en Laravel
- conversaciones
- mensajes
- chat operativo básico
- asignación y handoff
- presencia
- templates
- búsqueda de paciente
- KPI base

No entra en MVP:

- campañas
- flowmaker visual completo
- side apps complejas
- mobile API

## Mejoras UX priorizadas

### Prioridad alta

- lista de conversaciones más densa y filtrable
- indicadores visuales de dueño, estado y ventana
- composer de mensaje más rápido
- preview real de templates
- búsqueda paciente más clara
- panel fijo de contexto del paciente/chat

### Prioridad media

- respuestas rápidas
- notas internas
- acciones de supervisor en lote
- reportes rápidos por agente

### Prioridad baja

- campañas
- extensibilidad por side apps

## Riesgos

- duplicar lógica entre legacy y Laravel por demasiado tiempo
- migrar UI antes de cerrar reglas operativas
- romper la ventana de 24h o el uso correcto de templates
- perder relaciones paciente/HC por una migración incompleta
- subestimar el backfill de handoffs y sesiones de autoresponder

## Mitigaciones

- mover primero casos de uso y reglas
- usar checklist de paridad por endpoint
- pruebas manuales con números reales controlados
- logs comparativos durante la transición
- feature flags por ruta o por submódulo

## Primer sprint recomendado

### Sprint 1

- inventario completo de endpoints legacy de WhatsApp
- inventario de tablas legacy y mapeo a Laravel
- crear estructura de módulos `Whatsapp/*` en `laravel-app`
- implementar `WebhookController`, `ConversationController` y `MessageController`
- montar lectura de `whatsapp_conversations` y `whatsapp_messages`
- preparar vista Laravel mínima de inbox

### Sprint 2

- envío manual desde Laravel
- detalle de conversación
- presencia de agentes
- tomar/asignar/transferir/cerrar
- filtros y contadores del inbox

### Sprint 3

- templates
- búsqueda de paciente
- KPI base
- smoke tests de operación

## Checklist de arranque técnico

- confirmar si Laravel escribirá a tablas existentes o a tablas nuevas con backfill
- mapear settings legacy de WhatsApp a config/servicios Laravel
- definir rutas v2 internas para chat, messages, handoff, templates y KPI
- definir política de realtime: polling primero, websocket después si hace falta
- definir componentes UI base para inbox, panel de chat y panel lateral
- preparar datos de prueba y runbook de validación

## Primera decisión de implementación

La recomendación es iniciar por `Chat + Core`, no por `Flowmaker`.

Orden:

1. webhook y persistencia
2. conversaciones y mensajes
3. UI de inbox/chat
4. handoff y presencia
5. templates
6. KPI
7. flowmaker

## Criterio de éxito

La migración se considera bien encaminada cuando:

- un operador puede gestionar un chat completo desde `laravel-app`
- un supervisor puede asignar y transferir sin tocar legacy
- se mantiene el contexto clínico del paciente
- la experiencia es igual o mejor que legacy
- la UX toma lo mejor de `whatsbox` sin sacrificar reglas propias de MedForge
