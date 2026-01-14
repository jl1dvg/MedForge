# Settings de solicitudes (MVP-1)

Este documento describe las keys de Settings utilizadas por el módulo `solicitudes`.

## SLA

| Key | Tipo | Default | Descripción |
| --- | --- | --- | --- |
| `solicitudes.sla.warning_hours` | int | `72` | Horas para advertencia del SLA. |
| `solicitudes.sla.critical_hours` | int | `24` | Horas para estado crítico del SLA. |
| `solicitudes.sla.labels` | json map | labels actuales | Mapa de etiquetas para mostrar estados SLA. |

## Turnero

| Key | Tipo | Default | Descripción |
| --- | --- | --- | --- |
| `solicitudes.turnero.allowed_states` | json array | estados actuales y variantes normalizadas | Estados permitidos para el turnero. |
| `solicitudes.turnero.default_state` | string | `Llamado` | Estado utilizado cuando no se envía uno explícito. |
| `solicitudes.turnero.refresh_ms` | int | `30000` | Intervalo de refresco automático en ms. |

## Reportes

| Key | Tipo | Default | Descripción |
| --- | --- | --- | --- |
| `solicitudes.report.formats` | json array | `["pdf","excel"]` | Formatos habilitados (PDF/Excel). |
| `solicitudes.report.quick_metrics` | json map | quick reports actuales | Mapa de quick metrics y filtros. |

> Nota: si algún JSON es inválido o falta la configuración, se usan defaults actuales.
