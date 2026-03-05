# Inventario funcional — View Solicitudes

Fecha: 2026-03-05
Módulo fuente: `modules/solicitudes/views/solicitudes.php` + `modules/solicitudes/routes.php`

## 1) Tablero principal (Kanban)

### 1.1 Vista Kanban
- **Objetivo:** gestionar solicitudes por etapa visualmente.
- **UI:** toggle `Tablero` (`data-solicitudes-view="kanban"`), columnas `kanban-*`.
- **Acciones clave:** mover tarjetas por estado, revisar badges (estado/turno/CRM), abrir CRM.
- **Backend asociado:**
  - `GET/POST /solicitudes/kanban-data`
  - `POST /solicitudes/actualizar-estado`
- **Riesgo de uso:** mover estado sin contexto clínico/documental.

### 1.2 Vista Tabla
- **Objetivo:** lectura tabular, búsqueda rápida, revisión masiva.
- **UI:** toggle `Tabla` (`data-solicitudes-view="table"`), tabla `#solicitudesTable`.
- **Backend asociado:** `GET/POST /solicitudes/kanban-data` (fuente de datos).

### 1.3 Vista Conciliación
- **Objetivo:** conciliar solicitudes con cirugías realizadas/pendientes.
- **UI:** toggle `Conciliación`, sección `#solicitudesConciliacionSection`.
- **Backend asociado:**
  - `GET /solicitudes/conciliacion-cirugias`
  - `POST /solicitudes/{id}/conciliacion-cirugia/confirmar`

## 2) Filtros operativos

### 2.1 Filtros de búsqueda
- **Campos:**
  - Buscar (`#kanbanSearchFilter`)
  - Doctor (`#kanbanDoctorFilter`)
  - Fecha (`#kanbanDateFilter`)
  - Afiliación (`#kanbanAfiliacionFilter`)
  - Tipo solicitud (`#kanbanTipoFilter`)
- **Objetivo:** segmentar carga de trabajo por criterio clínico/administrativo.
- **Backend:** `kanban-data`.

### 2.2 Filtros de derivación
- **Campos:**
  - Solo derivación vencida (`#kanbanDerivacionVencidaFilter`)
  - Derivación por vencer (`#kanbanDerivacionPorVencerFilter` + días)
- **Objetivo:** priorizar riesgo documental/cobertura.

### 2.3 Filtros CRM
- **Campos:**
  - Responsable (`#kanbanResponsableFilter`)
  - Sin responsable (`#kanbanCrmSinResponsableFilter`)
- **Objetivo:** controlar ownership y seguimiento.

## 3) Herramientas de operación rápida

### 3.1 Turnero unificado
- **UI:** botón `/turneros/unificado`.
- **Objetivo:** operación de llamados en tiempo real.

### 3.2 Exportación PDF/ZIP/Excel
- **UI:**
  - Botón `#solicitudesExportPdfButton`
  - Export ZIP/Excel en caja inferior
- **Backend:**
  - `POST /solicitudes/reportes/pdf`
  - `POST /solicitudes/reportes/excel`
- **Objetivo:** auditoría, respaldo y reportes.

### 3.3 Panel de notificaciones
- **UI:** botón `data-notification-panel-toggle="true"`.
- **Objetivo:** seguimiento sin perder eventos de cambios.

## 4) Dashboard y analítica de Solicitudes

### 4.1 Dashboard dedicado
- **Rutas:**
  - `GET /solicitudes/dashboard`
  - `POST /solicitudes/dashboard-data`
- **Objetivo:** KPIs de funnel, estado operativo y carga.

### 4.2 Prefactura / derivación / cobertura
- **Rutas:**
  - `GET /solicitudes/prefactura`
  - `GET /solicitudes/derivacion`
  - `POST /solicitudes/cobertura-mail`
- **Objetivo:** cerrar ciclo administrativo para cirugía.

## 5) API de estado y turnero

### 5.1 API estado
- **Rutas:**
  - `GET /solicitudes/api/estado`
  - `POST /solicitudes/api/estado`
- **Objetivo:** consulta/actualización por integración o automatizaciones.

### 5.2 Turnero data y llamado
- **Rutas:**
  - `GET /solicitudes/turnero-data`
  - `POST /solicitudes/turnero-llamar`
- **Objetivo:** ejecutar llamado y sincronizar estado operativo.

## 6) CRM de Solicitudes (offcanvas)

### 6.1 Resumen y detalle del caso
- **UI:** offcanvas `#crmOffcanvas`, formulario `#crmDetalleForm`.
- **Campos clave:** etapa pipeline, responsable, lead vinculado, fuente, seguidores, contacto email/teléfono, campos personalizados.
- **Backend:**
  - `GET /solicitudes/{id}/crm`
  - `POST /solicitudes/{id}/crm`
  - `POST /solicitudes/{id}/crm/bootstrap`

### 6.2 Checklist CRM
- **Backend:**
  - `GET /solicitudes/{id}/crm/checklist-state`
  - `POST /solicitudes/{id}/crm/checklist`
- **Objetivo:** estandarizar control de pasos.

### 6.3 Notas internas
- **UI:** `#crmNotaForm`.
- **Backend:** `POST /solicitudes/{id}/crm/notas`.
- **Objetivo:** trazabilidad conversacional y acuerdos.

### 6.4 Tareas y recordatorios
- **UI:** `#crmTareaForm`.
- **Backend:**
  - `POST /solicitudes/{id}/crm/tareas`
  - `POST /solicitudes/{id}/crm/tareas/estado`
- **Objetivo:** seguimiento operativo y accountability.

### 6.5 Bloqueos de agenda
- **UI:** `#crmBloqueoForm` (inicio/fin/duración/sala/doctor/motivo).
- **Backend:** `POST /solicitudes/{id}/crm/bloqueo`.
- **Objetivo:** reservar recursos y evitar colisiones.

### 6.6 Adjuntos y cobertura
- **UI:** `#crmAdjuntoForm` + lista cobertura.
- **Backend:**
  - `POST /solicitudes/{id}/crm/adjuntos`
  - (lectura cobertura incluida en resumen CRM)
- **Objetivo:** mantener evidencia documental del caso.

## 7) Integraciones complementarias
- `POST /solicitudes/notificaciones/recordatorios`
- `POST /solicitudes/re-scrape-derivacion`
- `POST /solicitudes/derivacion-preseleccion`
- `POST /solicitudes/derivacion-preseleccion/guardar`
- `POST /solicitudes/{id}/cirugia`

## 8) Flags de migración relevantes
- `SOLICITUDES_V2_UI_ENABLED` (redirect `/solicitudes` -> `/v2/solicitudes`)
- `SOLICITUDES_V2_READS_ENABLED`
- `SOLICITUDES_V2_WRITES_ENABLED`

## 9) Recomendación para tutoriales (orden sugerido)
1. Navegación + filtros (base)
2. Kanban + cambio de estado seguro
3. CRM (detalle/notas/tareas)
4. Turnero y llamado
5. Derivación/cobertura/prefactura
6. Dashboard + conciliación
7. Exportes + cierre de jornada
