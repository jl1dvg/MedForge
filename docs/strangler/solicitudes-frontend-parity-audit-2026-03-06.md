# Solicitudes Frontend Parity Audit (Legacy vs V2)

Fecha de auditoria: 2026-03-06
Modulo: `Solicitudes`
Objetivo evaluado: **100% = backend + frontend (UI/UX operativa)**

## Evidencia usada

- Legacy UI: `modules/solicitudes/views/solicitudes.php`
- Legacy JS: `public/js/pages/solicitudes/index.js`
- Legacy routes: `modules/solicitudes/routes.php`
- V2 UI: `laravel-app/resources/views/solicitudes/v2-index.blade.php`
- V2 JS: `public/js/pages/solicitudes/v2-index.js`
- V2 turnero/dashboard: `laravel-app/resources/views/solicitudes/v2-turnero.blade.php`, `laravel-app/resources/views/solicitudes/v2-dashboard.blade.php`
- V2 API routes: `laravel-app/routes/v2/solicitudes.php`
- Smoke compartido en produccion (cookie autenticada):
  - `solicitudes_cutover`: 3/3 PASS
  - `solicitudes_kanban_data`, `solicitudes_dashboard_data`, `solicitudes_turnero_data`: 3/3 PASS
  - `solicitudes_writes`: 2/2 PASS
  - `solicitudes_crm_writes`: 5/5 PASS

## Resultado ejecutivo

- Backend/API (reads+writes+cutover): **100% operativo** por smoke.
- Frontend estricto (tu criterio de 100): **72.5%**.

Formula usada:

- 20 capacidades frontend (inventario funcional).
- `FULL = 1`, `PARTIAL = 0.5`, `MISSING = 0`.
- Puntaje: `14 FULL + 1 PARTIAL + 5 MISSING = 14.5 / 20 = 72.5%`.

## Matriz de paridad frontend

| # | Capacidad | Legacy | V2 | Estado |
|---|---|---|---|---|
| 1 | Vista Kanban (columnas) | Presente (`solicitudes.php:1230`, `1459`) | Presente (`v2-index.blade.php:445`) | FULL |
| 2 | Cambio de estado (avanzar) | Presente | Presente (`v2-index.js` accion `advance`) | FULL |
| 3 | Abrir detalle (prefactura) | Presente | Presente (`v2-index.js` `openDetalleModal`) | FULL |
| 4 | Abrir CRM desde tarjeta | Presente | Presente (`v2-index.js` `openCrmPanelForSolicitud`) | FULL |
| 5 | CRM detalle (guardar) | Presente (`crmDetalleForm`) | Presente (`v2-index.blade.php:482`) | FULL |
| 6 | CRM notas | Presente | Presente (`v2-index.blade.php:551`) | FULL |
| 7 | CRM tareas | Presente | Presente (`v2-index.blade.php:591`) | FULL |
| 8 | CRM bloqueos | Presente | Presente (`v2-index.blade.php:637`) | FULL |
| 9 | CRM adjuntos | Presente | Presente (`v2-index.blade.php:568`) | FULL |
| 10 | Dashboard de solicitudes (UI) | Presente (`/solicitudes/dashboard`) | Presente (`/v2/solicitudes/dashboard`) | FULL |
| 11 | Dashboard data/charts | Presente | Presente (`dashboard.js`, endpoint v2) | FULL |
| 12 | Turnero pantalla dedicada | Presente | Presente (`v2-turnero.blade.php`) | FULL |
| 13 | Turnero data + refresh | Presente | Presente (`turnero.js`, `/v2/solicitudes/turnero-data`) | FULL |
| 14 | Filtros base (buscar, doctor, fechas, afiliacion, sede, prioridad) | Presente | Presente (`v2-index.blade.php:387`) | FULL |
| 15 | Vista Tabla (toggle + tabla) | Presente (`solicitudes.php:1233`, `1529`) | No existe en v2 | MISSING |
| 16 | Vista Conciliacion | Presente (`solicitudes.php:1236`, `1370`) | No existe en v2 index (solo API) | MISSING |
| 17 | Exportes toolbar (PDF/Excel/ZIP) | Presente (`solicitudesExportPdfButton`, `index.js`) | No existe en v2 index | MISSING |
| 18 | Panel de avisos/notificaciones | Presente (`data-notification-panel-toggle`, `index.js`) | No existe en v2 index | MISSING |
| 19 | Filtros avanzados (tipo, derivacion, responsable CRM) | Presente (`solicitudes.php:1312`, `1329`, `1344`) | No existe en v2 index | MISSING |
| 20 | Acceso rapido a turnero desde toolbar principal | Boton directo (`solicitudes.php:1239`) | No boton equivalente en v2 index | PARTIAL |

## Que falta para llegar al 100% frontend

Bloques faltantes (impacto alto):

1. Reponer `Tabla` en `/v2/solicitudes`.
2. Reponer `Conciliacion` en `/v2/solicitudes` (la API v2 ya existe).
3. Reponer `Exportes` en toolbar (PDF/Excel/ZIP).
4. Reponer `Panel de avisos` con toggle y estado.
5. Reponer filtros avanzados: tipo solicitud, derivacion (vencida/por vencer), responsable CRM/sin responsable.

Ajuste menor:

1. Agregar boton de acceso rapido a turnero en toolbar de v2 index.

## Propuesta de cierre (sin tocar Reporting)

Orden recomendado para cerrar rapido:

1. `Tabla + Conciliacion` (cubre mayor brecha funcional).
2. `Exportes + Avisos`.
3. `Filtros avanzados + boton turnero`.

Con este orden, `Solicitudes` puede subir de **72.5% -> 100% frontend** sin tocar el modulo `Reporting`.

## Actualizacion de implementacion (Block 1)

Fecha: 2026-03-06

- Se implemento en `/v2/solicitudes`:
  - Toggle de vistas `Tablero | Tabla | Conciliación`.
  - Vista `Tabla` con acciones `Detalle`, `CRM`, `Avanzar`.
  - Vista `Conciliación` conectada al modulo JS existente (`conciliacion.js`) y endpoints v2.
- Archivos tocados:
  - `laravel-app/resources/views/solicitudes/v2-index.blade.php`
  - `public/js/pages/solicitudes/v2-index.js`

Estado post-Block 1 (pendiente validacion visual/UAT):

- Frontend estimado: **82.5%**
- Pendiente para 100%:
  1. Exportes toolbar (PDF/Excel/ZIP)
  2. Panel de avisos/notificaciones
  3. Filtros avanzados (tipo, derivacion, responsable CRM)
  4. Boton rapido de turnero en toolbar
