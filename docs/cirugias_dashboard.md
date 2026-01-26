# Propuesta de dashboard quirúrgico (análisis de viabilidad)

## 1) Datos disponibles hoy en el módulo Cirugías

**Fuentes principales**

- `protocolo_data`: fechas/horas de inicio y fin, procedimientos, diagnósticos, lateralidad, tipo de anestesia, cirujano y staff, insumos/medicamentos, estado de revisión (`status`) y bandera de impresión (`printed`).【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】【F:modules/Cirugias/Services/CirugiaService.php†L328-L432】
- `patient_data`: afiliación (convenio/aseguradora), datos demográficos básicos para segmentación.【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】
- `procedimiento_proyectado` y `procedimientos`: catálogo y procedimientos proyectados asociados al `form_id`/`hc_number`.【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】【F:modules/Cirugias/Models/ProcedimientoModel.php†L13-L31】
- `insumos_pack` (estándar por procedimiento) y `protocolo_insumos` (consumo registrado en protocolo).【F:modules/Cirugias/Models/ProcedimientoModel.php†L63-L107】【F:modules/Cirugias/Services/CirugiaService.php†L398-L468】
- `kardex` (medicamentos por procedimiento).【F:modules/Cirugias/Models/ProcedimientoModel.php†L79-L107】【F:modules/Cirugias/Services/CirugiaService.php†L313-L326】
- `billing_main` (existencia de facturación asociada al `form_id`).【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】

**Campos clave ya presentes**

- Volumen/fechas: `fecha_inicio`, `hora_inicio`, `hora_fin`, `form_id`, `procedimiento_id`, `procedimientos` (JSON).【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】
- Staff quirúrgico: `cirujano_1`, `cirujano_2`, `primer_ayudante`, `segundo_ayudante`, `tercer_ayudante`, `anestesiologo`, `instrumentista`, `circulante`, `ayudante_anestesia`.【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】
- Convenio/aseguradora: `afiliacion` en `patient_data` (usable para segmentación por convenio).【F:modules/Cirugias/Services/CirugiaService.php†L102-L205】
- Protocolo: `status` (revisado/no revisado) y validación de completitud en el modelo `Cirugia`.【F:modules/Cirugias/Models/Cirugia.php†L21-L71】
- Insumos/medicamentos: `insumos` (JSON en protocolo) y detalle por `protocolo_insumos`; `medicamentos`/`kardex`.【F:modules/Cirugias/Services/CirugiaService.php†L271-L326】【F:modules/Cirugias/Services/CirugiaService.php†L398-L468】

## 2) Métricas viables con datos actuales

### 🧩 Volumen y Producción Quirúrgica

**Viable hoy:**
- Cirugías realizadas (total y por período) usando `fecha_inicio`.
- Cirugías por tipo de procedimiento usando `procedimientos` (JSON) y/o `procedimiento_id`.
- Cirugías por médico cirujano (`cirujano_1`).
- Cirugías por convenio/aseguradora (`afiliacion`).
- Top 10 procedimientos (a partir de `procedimientos`).
- % crecimiento vs período anterior (comparando series por `fecha_inicio`).

**No disponible aún:**
- Cirugías por quirófano (no hay campo de quirófano).

### ⏱️ Eficiencia Operativa

**Viable hoy:**
- Tiempo quirúrgico real (inicio–fin) con `hora_inicio`/`hora_fin`.
- Tiempo promedio por procedimiento (si se normaliza `procedimientos`).

**No disponible aún:**
- Tiempo desde solicitud → cirugía (no existe fecha de solicitud).
- Tiempo desde cirugía → protocolo firmado (no existe fecha de firma).
- Retrasos quirúrgicos y reprogramaciones (no hay timestamps de programación ni estado de reprogramación).
- Cumplimiento SLA (no hay SLA definido ni fecha comprometida).

### 🧾 Calidad y Protocolo

**Viable hoy:**
- Protocolos completos vs incompletos mediante reglas de `Cirugia::getEstado()`.
- Protocolos firmados vs pendientes (aprox. usando `status` y completitud).
- Tiempo de cierre de protocolo (no está explícito; requiere fecha de firma).

**No disponible aún:**
- Protocolos con insumos faltantes vs estándar (requiere comparar contra `insumos_pack` y reglas de negocio).

### 🧰 Insumos y Recursos

**Viable hoy:**
- Insumos usados por cirugía (`protocolo_insumos`).
- Insumos promedio por procedimiento (consolidando consumo real).
- Insumos fuera de protocolo (comparando `protocolo_insumos` vs `insumos_pack`).
- Insumos por convenio (via `afiliacion`).

**No disponible aún:**
- Costo promedio por cirugía (no hay precios/costos por insumo).

### 💰 Métricas Económicas

**Parcialmente viable hoy:**
- Cirugías con/sin facturación (existe `billing_main`).
- Alertas de no facturación (cuando `billing_main` no existe).

**No disponible aún:**
- Ingreso quirúrgico mensual, ingreso promedio, facturación estimada vs real (no hay montos en el módulo Cirugías).

### 🧠 Flujo y Cuellos de Botella

**No disponible aún:**
- Tiempo por estado, etapas del flujo, usuarios con retrasos, horas pico por etapa (faltan eventos de workflow).

### 🧑‍⚕️ Métricas por Médico/Equipo

**Viable hoy:**
- Volumen por cirujano.
- Tiempo promedio quirúrgico por cirujano.
- Uso de insumos por cirujano (si consolidamos `protocolo_insumos`).

**Parcialmente viable:**
- Protocolos fuera de estándar (requiere reglas de completitud más detalladas).
- Reprogramaciones (falta dato).

## 3) Mapa rápido de KPIs recomendados (ahora vs futuro)

| KPI | Estado | Fuente | Notas |
| --- | --- | --- | --- |
| Cirugías mensuales | ✅ Ahora | `protocolo_data.fecha_inicio` | Agrupar por mes. |
| Top procedimientos | ✅ Ahora | `protocolo_data.procedimientos` | Normalizar JSON. |
| Top cirujanos | ✅ Ahora | `protocolo_data.cirujano_1` | Considerar alias. |
| % crecimiento | ✅ Ahora | `fecha_inicio` | Serie temporal. |
| Tiempo quirúrgico | ✅ Ahora | `hora_inicio`/`hora_fin` | Duración real. |
| SLA cumplimiento | ❌ Futuro | Nuevos campos | Fecha comprometida + SLA. |
| Protocolos completos | ✅ Ahora | `Cirugia::getEstado()` | Regla existente. |
| Backlog protocolos | ⚠️ Parcial | `status` | Falta fecha de firma. |
| Insumos vs estándar | ⚠️ Parcial | `protocolo_insumos`/`insumos_pack` | Definir comparación. |
| Cirugías sin facturar | ✅ Ahora | `billing_main` | Alerta simple. |
| Ingresos | ❌ Futuro | Billing | Necesita montos. |

## 4) Propuesta de dashboard ejecutivo

**Mensaje para gerencia**

> “El módulo quirúrgico de MedForge transforma la cirugía de un evento clínico aislado a un proceso medible, controlable y optimizable, permitiendo a la gerencia visualizar productividad, eficiencia, calidad y rentabilidad en tiempo real.”

**Secciones recomendadas (con viabilidad actual)**

1. **Resumen ejecutivo** (✅)
   - Cirugías mes
   - % protocolos completos
   - Alertas: cirugías sin facturar
2. **Producción** (✅)
   - Volumen por mes, procedimiento, cirujano, convenio
3. **Tiempos y eficiencia** (⚠️ parcial)
   - Duración quirúrgica promedio y por procedimiento
4. **Calidad y protocolos** (✅)
   - Completitud y backlog básico
5. **Costos e insumos** (⚠️ parcial)
   - Consumo real vs estándar (sin costo)
6. **Alertas operativas** (✅)
   - Protocolos incompletos/no revisados
   - Cirugías sin facturación

## 5) Ideas nuevas para capturar mejores datos (y abrir más KPIs)

1. **Registrar hitos de flujo** (nueva tabla `cirugia_eventos`)
   - `fecha_solicitud`, `fecha_programada`, `fecha_ingreso_qx`, `fecha_fin_qx`, `fecha_protocolo_firmado`.
   - Permite lead time, SLA, cuellos de botella y reprogramaciones.

2. **Agregar quirófano y turno**
   - Campos `quirofano_id`, `turno`, `bloque_horario` en `protocolo_data`.
   - Permite ocupación de quirófanos y horas pico.

3. **Integración con costos de insumos**
   - Tabla de costos (`insumos_costos`) o campos en `insumos`.
   - Permite costo promedio por cirugía y variación real vs estándar.

4. **Estandarizar convenios**
   - Catálogo de convenios (ISSFA, IESS, privado, etc.) y referencia por `afiliacion`.
   - Evita dispersión por texto libre.

5. **Firma y auditoría de protocolo**
   - Campos `protocolo_firmado_por`, `fecha_firma`, `version`.
   - Permite métricas de cierre y trazabilidad.

6. **Etiquetas de complejidad/riesgo**
   - `complejidad` (baja/media/alta) y `riesgo`.
   - Mejora comparaciones de eficiencia y productividad.

7. **Catálogo de procedimientos normalizado**
   - Normalizar `procedimientos` para evitar texto libre.
   - Permite KPI confiables de top procedimientos y duración por tipo.

## 6) Próximos pasos sugeridos

1. **Dashboard MVP** con métricas viables hoy.
2. **Roadmap de datos** para habilitar SLA, rentabilidad e ingresos.
3. **Definición de KPIs con gerencia** (SLA objetivo, estándar de insumos, alertas críticas).
