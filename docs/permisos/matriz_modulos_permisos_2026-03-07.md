# Matriz detallada de permisos por módulo (Laravel v2 + legacy)
Fecha: 2026-03-07

## Resumen ejecutivo
- Permisos catalogados y asignables (legacy + Laravel): `68`.
- Permisos nuevos incorporados en esta iteración: `5`.
  - `agenda.view`
  - `derivaciones.view`
  - `pacientes.flujo.view`
  - `ipl.view`
  - `farmacia.view`
- Pantallas v2 en `web.php` protegidas con `legacy.permission`: `100%` de las listadas en ese archivo.
- Objetivo de simplificación: ocultar pestañas/opciones por permisos y bloquear backend aunque un usuario intente acceder por URL directa.

## Permisos nuevos incorporados
1. `agenda.view`: permite acceso explícito a Agenda sin depender de permisos amplios de otros módulos.
2. `derivaciones.view`: permite acceso explícito a Derivaciones sin forzar permisos de Pacientes/Solicitudes.
3. `pacientes.flujo.view`: permite mostrar y consumir Flujo de pacientes de forma separada de la ficha completa de Pacientes.
4. `ipl.view`: permite controlar la visibilidad del planificador de IPL sin abrir todo el bloque quirúrgico.
5. `farmacia.view`: permite controlar visibilidad del dashboard de farmacia sin abrir todo el módulo de inventario.

## Matriz backend + frontend por módulo

| Módulo | Rutas v2 principales | Permisos backend (middleware) | Pestañas/opciones UI condicionadas | Estado de dependencia legacy |
|---|---|---|---|---|
| Dashboard | `/v2/dashboard`, `/v2/dashboard/summary` | `administrativo` o `dashboard.view` | `Inicio` | Parcial (auth/sesión legacy) |
| Agenda | `/v2/agenda`, `/v2/api/agenda*` | `administrativo` o `agenda.view` o `pacientes.view` o `solicitudes.view` o `examenes.view` | `Agenda`, `Agendamiento` (en atención) | Parcial |
| Pacientes | `/v2/pacientes*` | Core: `administrativo` o `pacientes.view` o `pacientes.manage` ; Flujo: `administrativo` o `pacientes.flujo.view` o `pacientes.view` o `pacientes.manage` | `Lista de Pacientes`, `Flujo de Pacientes` | Parcial |
| Derivaciones | `/v2/derivaciones`, `/v2/derivaciones/*` | `administrativo` o `derivaciones.view` o `pacientes.view` o `solicitudes.view` | `Derivaciones` | Parcial |
| Solicitudes | `/v2/solicitudes*`, `/v2/api/solicitudes*` | `administrativo` o `solicitudes.view` o `solicitudes.update` o `solicitudes.turnero` o `solicitudes.dashboard.view` o `solicitudes.manage` | `Solicitudes (Kanban)`, `Turnero solicitudes`, `Dashboard quirúrgico` (si aplica) | Parcial (UI con feature-flag y fallback) |
| Cirugías | `/v2/cirugias*` | `administrativo` o `cirugias.view` o `cirugias.manage` o `cirugias.dashboard.view` | `Protocolos Realizados`, `Dashboard quirúrgico` | Parcial |
| Exámenes | `/v2/examenes*`, `/v2/api/examenes*` | `administrativo` o `examenes.view` o `examenes.manage` | `Exámenes (Kanban)`, `Exámenes realizados`, `Dashboard imágenes` | Alta (parity + bridge legacy) |
| CRM | `/v2/crm/*`, `/v2/api/crm/*` | `administrativo` o `crm.view` o `crm.manage` | `CRM`, `Campañas y Leads` | Parcial |
| Consultas IA | `/v2/api/consultas*` | `administrativo` o `ai.manage` o `ai.consultas.enfermedad` o `ai.consultas.plan` | Opciones IA dentro de módulos | Parcial |
| Reporting clínico | `/v2/reports/*` | `administrativo` o `reportes.view` o `reportes.export` | Reportes/PDF asociados | Alta (reporting usa controlador legacy en definiciones) |
| Billing / Informes | `/v2/billing*`, `/v2/informes*`, `/v2/api/billing*` | `administrativo` o `reportes.view` o `reportes.export` | Árbol `Finanzas y análisis` (informes, no facturado, dashboard, honorarios) | Parcial (fallback a tablas legacy en casos puntuales) |
| Usuarios | `/v2/usuarios*` | Lista: `administrativo` o `admin.usuarios.view` o `admin.usuarios.manage` o `admin.usuarios`; edición: `administrativo` o `admin.usuarios.manage` o `admin.usuarios` | `Administración y TI > Usuarios` | Parcial (auth legacy) |
| Roles | `/v2/roles*` | Lista: `administrativo` o `admin.roles.view` o `admin.roles.manage` o `admin.roles`; edición: `administrativo` o `admin.roles.manage` o `admin.roles` | `Administración y TI > Roles` | Parcial (auth legacy) |
| Módulos legacy aún en navbar | `/doctores`, `/insumos*`, `/farmacia`, `/protocolos`, `/settings`, `/codes*`, `/whatsapp*`, `/mailbox`, `/ipl`, `/leads` | Condicionados por permisos en navbar (según módulo) | Se muestran/ocultan por permiso, pero aún no todos son v2 nativos | Alta |

## Regla de simplificación de usuarios (práctica)
1. Definir el perfil operativo por módulo (qué sí necesita usar en el día).
2. Asignar sólo permisos del módulo y submódulo (por ejemplo `solicitudes.turnero` sin `solicitudes.manage`).
3. Validar que navbar oculte automáticamente árboles/opciones no autorizadas.
4. Verificar acceso directo por URL: debe responder `403` si no tiene permiso.

## Perfiles rápidos disponibles
Archivo: `laravel-app/config/permission_profiles.php`
- `recepcion`
- `coordinacion_quirurgica`
- `imagenes_examenes`
- `facturacion`
- `crm_captacion`
- `administracion_ti`

## Criterio de “100% migrado” (sin dependencia legacy)
La migración se considera cerrada sólo cuando se cumplan simultáneamente:
1. **Auth y sesión**: reemplazar `LegacySessionAuth` por guard nativo Laravel (sin `PHPSESSID` legacy).
2. **Backend funcional**: eliminar bridges/parity que invocan controladores legacy.
3. **Frontend funcional**: pantallas v2 sin feature-flag con fallback a URLs legacy.
4. **Rutas**: cero redirects/proxies a endpoints legacy para operación principal.
5. **Datos**: sin fallback a tablas legacy para casos normales de operación.
6. **Operación**: smoke tests E2E sobre rutas v2 pasan sin tocar código legacy.

## Dependencias legacy activas a retirar (prioridad alta)
1. `ExamenesParityController` + `LegacyExamenesBridge` (bridge directo a `Controllers\\ExamenController`).
2. `Reporting` en definiciones de solicitud usando `Controllers\\SolicitudController`.
3. `SolicitudesUiController` y `ExamenesUiController` con fallback `redirectLegacy(...)` por flag.
4. Redirectores en `routes/api.php` para reportes e imágenes históricas.
5. Servicios con fallback a datos legacy (ej. billing/prefactura en escenarios puntuales).

## Próxima iteración recomendada
1. Separar permisos `view` vs `write` en rutas con escrituras críticas (Billing, Solicitudes, Exámenes).
2. Migrar Exámenes parity a implementación nativa completa y eliminar bridge.
3. Migrar reporting de solicitud a servicio Laravel puro (sin `Controllers\\SolicitudController`).
4. Cerrar feature flags de UI v2 (quitar fallback).
