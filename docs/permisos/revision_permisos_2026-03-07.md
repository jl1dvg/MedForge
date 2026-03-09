# Revisión exhaustiva de permisos (legacy + Laravel)
Fecha: 2026-03-07

## Resultado
- Permisos detectados en uso real (código + compatibilidad): `68`
- Permisos catalogados en `PermissionRegistry`: `68`
- Brecha actual (`usados` y no `catalogados`): `0`

## Permisos en uso (inventario consolidado)
- `admin.roles`
- `admin.roles.manage`
- `admin.roles.view`
- `admin.usuarios`
- `admin.usuarios.manage`
- `admin.usuarios.view`
- `administrativo`
- `agenda.view`
- `ai.consultas.enfermedad`
- `ai.consultas.plan`
- `ai.manage`
- `cirugias.create`
- `cirugias.dashboard.view`
- `cirugias.delete`
- `cirugias.edit`
- `cirugias.manage`
- `cirugias.view`
- `codes.manage`
- `codes.view`
- `crm.leads.manage`
- `crm.manage`
- `crm.projects.manage`
- `crm.tasks.manage`
- `crm.tickets.manage`
- `crm.view`
- `dashboard.view`
- `derivaciones.view`
- `doctores.manage`
- `doctores.view`
- `examenes.checklist.override`
- `examenes.manage`
- `examenes.view`
- `farmacia.view`
- `insumos.create`
- `insumos.delete`
- `insumos.edit`
- `insumos.manage`
- `insumos.view`
- `ipl.view`
- `pacientes.create`
- `pacientes.delete`
- `pacientes.edit`
- `pacientes.flujo.view`
- `pacientes.manage`
- `pacientes.verification.manage`
- `pacientes.verification.view`
- `pacientes.view`
- `protocolos.manage`
- `protocolos.templates.manage`
- `protocolos.templates.view`
- `reportes.export`
- `reportes.view`
- `settings.manage`
- `settings.view`
- `solicitudes.checklist.override`
- `solicitudes.dashboard.view`
- `solicitudes.manage`
- `solicitudes.turnero`
- `solicitudes.update`
- `solicitudes.view`
- `superuser`
- `whatsapp.autoresponder.manage`
- `whatsapp.chat.assign`
- `whatsapp.chat.send`
- `whatsapp.chat.supervise`
- `whatsapp.chat.view`
- `whatsapp.manage`
- `whatsapp.templates.manage`

## Brecha encontrada y corregida en esta entrega
Se incorporaron al catálogo y a controles de acceso:
- `doctores.view`
- `doctores.manage`
- `solicitudes.view`
- `solicitudes.update`
- `solicitudes.turnero`
- `solicitudes.dashboard.view`
- `solicitudes.checklist.override`
- `solicitudes.manage`
- `examenes.view`
- `examenes.checklist.override`
- `examenes.manage`
- `agenda.view`
- `derivaciones.view`
- `pacientes.flujo.view`
- `ipl.view`
- `farmacia.view`

## Dónde se aplicó la corrección
- Catálogo legacy: `modules/Usuarios/Support/PermissionRegistry.php`
- Alias legacy: `core/Permissions.php`
- Catálogo Laravel: `laravel-app/app/Modules/Shared/Support/LegacyPermissionCatalog.php`
- Rutas endurecidas: `laravel-app/routes/web.php`, `laravel-app/routes/v2/*.php`
- Navbar condicionado: `laravel-app/resources/views/layouts/partials/navbar.blade.php`

## Permisos sugeridos para siguiente iteración (hardening)
Estos no existían como permisos explícitos y conviene separarlos para menor privilegio:
- `admin.usuarios.delete`
- `admin.roles.delete`
- `admin.roles.assign`
- `settings.audit.view`
- `settings.secrets.manage`
- `whatsapp.templates.approve`
- `whatsapp.handoff.supervise`
- `reportes.medicos.export`
- `reportes.financieros.export`
- `solicitudes.export`
- `examenes.export`
- `examenes.derivacion.manage`
- `billing.write`
- `solicitudes.write`
- `cirugias.write`

## Documento de detalle módulo por módulo
- `docs/permisos/matriz_modulos_permisos_2026-03-07.md`

## Criterio de migración a Laravel
- Mantener compatibilidad de `aliases` (`*.manage`, `admin.*`, `superuser`).
- Resolver permisos efectivos como `merge(user.permisos, role.permissions)`.
- Exigir permisos por ruta con middleware y no solo en vistas.
- Mantener `session PHPSESSID` como fuente de autenticación durante la transición.
