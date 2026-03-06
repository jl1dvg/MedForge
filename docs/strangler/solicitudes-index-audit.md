# Solicitudes Index Audit

Auditoría rápida de cobertura de índices para las consultas críticas del módulo `Solicitudes` migrado a Laravel.

## Objetivo

Verificar que las tablas usadas por `SolicitudesReadParityService` y `SolicitudesWriteParityService` tengan índices mínimos para:

- listados Kanban y dashboard;
- detalle CRM (notas, adjuntos, tareas, checklist);
- acciones de turnero y trazabilidad de correo.

## Script

El script está en:

- `tools/solicitudes_index_audit.php`

### Uso

```bash
php tools/solicitudes_index_audit.php
php tools/solicitudes_index_audit.php --strict
php tools/solicitudes_index_audit.php --table=solicitud_procedimiento,crm_tasks
```

### Modo `--strict`

- Sale con `exit code 2` si hay checks sin cobertura de índice.
- Útil para CI/CD o para el checklist de cierre de módulo.

## Tablas auditadas

- `solicitud_procedimiento`
- `consulta_data`
- `solicitud_checklist`
- `solicitud_crm_detalles`
- `solicitud_crm_notas`
- `solicitud_crm_adjuntos`
- `crm_tasks`
- `crm_calendar_blocks`
- `solicitud_mail_log`

## Nota operacional

Si el entorno local no tiene acceso al host de base productivo, ejecuta el script en el servidor donde corre la app (mismo entorno de smoke) para obtener evidencia real antes de declarar `Solicitudes` al 100%.
