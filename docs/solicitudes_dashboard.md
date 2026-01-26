# Propuesta de dashboard de Solicitudes (análisis de viabilidad)

## 1) Datos disponibles hoy en el módulo Solicitudes

**Fuentes principales**

- `solicitud_procedimiento` (alias `sp`): fechas (`created_at`, `fecha`), tipo, afiliación, doctor, procedimiento, ojo, prioridad, turno y estado legacy. Se usa en listados, filtros y turnero.【F:modules/solicitudes/models/SolicitudModel.php†L30-L190】【F:modules/solicitudes/models/SolicitudModel.php†L302-L399】
- `solicitud_checklist` + `solicitud_checklist_log`: etapas con `completado_at`, `completado_por` y trazas de cambios por etapa (log con old/new timestamps).【F:modules/solicitudes/services/SolicitudEstadoService.php†L220-L314】
- `SolicitudEstadoService`: define etapas/columnas Kanban y calcula estado Kanban/next step/progreso de checklist.【F:modules/solicitudes/services/SolicitudEstadoService.php†L13-L208】
- `SolicitudSettingsService`: SLA (`warning_hours`, `critical_hours`), labels y parámetros del turnero (estados permitidos, refresh, etc.).【F:modules/solicitudes/services/SolicitudSettingsService.php†L11-L215】
- `SolicitudController::computeOperationalMetadata`: calcula SLA status, deadlines y alertas operativas a partir de fechas y settings.【F:modules/solicitudes/controllers/SolicitudController.php†L1240-L1354】
- CRM asociado: `solicitud_crm_detalles`, `solicitud_crm_notas`, `solicitud_crm_adjuntos`, `solicitud_crm_tareas` (se consultan desde `SolicitudModel` y se sincronizan en `SolicitudCrmService`).【F:modules/solicitudes/models/SolicitudModel.php†L151-L235】【F:modules/solicitudes/services/SolicitudCrmService.php†L94-L199】
- Agenda quirúrgica: `crm_calendar_blocks` vía `CalendarBlockService` (bloqueos por solicitud).【F:modules/solicitudes/services/CalendarBlockService.php†L18-L120】
- Derivaciones: joins a `derivaciones_forms` / `derivaciones_form_id` para detectar derivaciones por `form_id`/`hc_number`.【F:modules/solicitudes/models/SolicitudModel.php†L151-L211】【F:modules/solicitudes/models/SolicitudModel.php†L360-L447】
- Turnero: estados y cola en turnero (endpoint `turneroData`), con estado/turno/created_at de solicitud.【F:modules/solicitudes/controllers/SolicitudController.php†L1648-L1742】【F:modules/solicitudes/models/SolicitudModel.php†L302-L399】

> ⚠️ **No se encontró** en el código una tabla/servicio `solicitud_mail_log` o equivalente para Cobertura/Correo. Si existe en BD, habría que exponerla en servicios para métricas de email.

---

## 2) Métricas viables hoy (por bloque)

### 1) Volumen y demanda (cuánto entra)

**Viable hoy**
- Solicitudes por día/semana/mes (usa `created_at` o `fecha` en `solicitud_procedimiento`).【F:modules/solicitudes/models/SolicitudModel.php†L30-L190】
- Por afiliación (afiliación en `patient_data` enlazada) y por tipo/procedimiento (`sp.tipo`, `sp.procedimiento`).【F:modules/solicitudes/models/SolicitudModel.php†L151-L190】
- Por doctor (`sp.doctor`) y por lateralidad (`sp.ojo`).【F:modules/solicitudes/models/SolicitudModel.php†L30-L190】
- Por prioridad (`sp.prioridad`) y turno (`sp.turno`).【F:modules/solicitudes/models/SolicitudModel.php†L30-L190】

**Charts sugeridos**
- Serie temporal: solicitudes por día.
- Barras apiladas: afiliación por semana.
- Pareto: top procedimientos.

### 2) Flujo Kanban y embudo

**Viable hoy**
- WIP total y por columna Kanban (estados definidos en `SolicitudEstadoService`).【F:modules/solicitudes/services/SolicitudEstadoService.php†L13-L208】
- Throughput a “Programada” (etapa `programada` en checklist).【F:modules/solicitudes/services/SolicitudEstadoService.php†L44-L74】
- Conversión Recibida → Programada (contar completadas).【F:modules/solicitudes/services/SolicitudEstadoService.php†L44-L74】

**Parcial**
- Drop-off/estancadas: requiere definir “cancelada/no procede” o timeout operativo (no hay estado terminal explícito para “cancelada”).

### 3) Tiempos por etapa

**Viable hoy**
- Lead time total usando `solicitud_checklist` (`recibida` → `programada`).【F:modules/solicitudes/services/SolicitudEstadoService.php†L44-L74】【F:modules/solicitudes/services/SolicitudEstadoService.php†L284-L324】
- Cycle time por etapa usando `solicitud_checklist` y `completado_at` (y opcionalmente `solicitud_checklist_log` para auditoría).【F:modules/solicitudes/services/SolicitudEstadoService.php†L220-L314】

### 4) SLA (cumplimiento y riesgo)

**Viable hoy**
- SLA status por solicitud (`en_rango`, `advertencia`, `critico`, `vencido`, `sin_fecha`, `cerrado`).【F:modules/solicitudes/controllers/SolicitudController.php†L1240-L1354】
- SLA por columna (usando `kanban_estado` + SLA status).【F:modules/solicitudes/services/SolicitudEstadoService.php†L100-L208】【F:modules/solicitudes/controllers/SolicitudController.php†L1240-L1354】

### 5) Turnero

**Viable hoy**
- Backlog (recibidos/llamados) y cola en tiempo real (endpoint `turneroData`).【F:modules/solicitudes/controllers/SolicitudController.php†L1648-L1727】

**No disponible aún**
- Tiempos de espera/atención por estado (no hay timestamps por transición de estado del turnero, solo `created_at`).【F:modules/solicitudes/models/SolicitudModel.php†L302-L399】

### 6) Cobertura (correo, adjuntos, éxito/falla)

**No disponible aún**
- No hay `solicitud_mail_log` en el código; se requiere tabla + servicio para métricas de email.

### 7) Checklist (calidad/completitud)

**Viable hoy**
- % checklist completo por solicitud (ya se calcula en `computeProgress`).【F:modules/solicitudes/services/SolicitudEstadoService.php†L360-L384】
- Etapas más incompletas y notas por etapa (`solicitud_checklist`).【F:modules/solicitudes/services/SolicitudEstadoService.php†L284-L356】
- Overrides (existe permiso `solicitudes.checklist.override`; se puede contar en `solicitud_checklist_log`).【F:modules/solicitudes/services/SolicitudEstadoService.php†L78-L92】【F:modules/solicitudes/services/SolicitudEstadoService.php†L220-L314】

### 8) Actividad CRM asociada

**Viable hoy**
- Notas, adjuntos, tareas por solicitud (agregados en `SolicitudModel` + `SolicitudCrmService`).【F:modules/solicitudes/models/SolicitudModel.php†L151-L235】【F:modules/solicitudes/services/SolicitudCrmService.php†L94-L199】

### 9) Programación y bloqueos de agenda

**Viable hoy**
- Bloqueos creados por semana y horas bloqueadas (usa `crm_calendar_blocks` con `fecha_inicio`/`fecha_fin`).【F:modules/solicitudes/services/CalendarBlockService.php†L18-L120】
- Utilización por sala/doctor (están en `crm_calendar_blocks`).【F:modules/solicitudes/services/CalendarBlockService.php†L18-L120】

### 10) Derivaciones y scraping

**Parcial**
- % solicitudes con derivación encontrada (joins con `derivaciones_forms` / `derivaciones_form_id`).【F:modules/solicitudes/models/SolicitudModel.php†L151-L211】

---

## 3) “Tablero Gerencial” recomendado (solo solicitudes)

**Mensaje para gerencia**

> “El módulo solicitudes de MedForge, permitiendo a la gerencia visualizar productividad, eficiencia, calidad y rentabilidad en tiempo real.”

**Bloques sugeridos**

1. **Operación hoy**
   - WIP por columna (Kanban)
   - SLA vencidos/críticos del día
   - Turnero: backlog de llamados

2. **Eficiencia del flujo**
   - Lead time total (P50 / P90)
   - Cuello de botella principal (etapa con mayor tiempo)
   - Throughput semanal (Programadas/semana)

3. **Cobertura y documentación**
   - Checklist completitud promedio
   - Pendientes por etapa (cobertura/documentos)

4. **Capacidad quirúrgica**
   - Horas bloqueadas por sala y cirujano
   - Lead time solicitud → bloqueo agenda

---

## 4) Brechas de datos y mejoras recomendadas (para KPIs avanzados)

1. **Timestamps por estado de turnero**
   - Guardar `turnero_llamado_at`, `turnero_atendido_at` para medir espera/atención.

2. **Mail log de cobertura/documentos**
   - Crear `solicitud_mail_log` con `status`, `sent_at`, `error_message`, `template_key`.

3. **Estados terminales explícitos**
   - Estados `cancelada`, `no_procede` para medir drop-off real.

4. **Eventos de flujo**
   - Tabla `solicitud_eventos` (tipo, actor, timestamp) para embudos y tiempos detallados.

5. **Normalizar doctor y procedimiento**
   - Catálogos para evitar texto libre y mejorar rankings.

6. **Registro de reprogramaciones**
   - Guardar histórico de cambios de `fecha_programada` y bloques agenda.

---

## 5) Próximos pasos sugeridos

1. Definir el **MVP de dashboard** con los KPIs viables hoy.
2. Acordar con gerencia los **SLA y estados terminales**.
3. Implementar los **eventos/bitácoras** necesarios para tiempos y drop-off.
