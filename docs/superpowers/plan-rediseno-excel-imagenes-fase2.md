# Fase 2 — Rediseño del Excel de Imágenes: auditoría de impacto y plan de migración

> Solo auditoría y plan. **No implementado.** Pendiente de aprobación antes de tocar código.

**Exportador auditado:** `ExamenesParityController::imagenesDashboardExportExcel()` (`laravel-app/app/Modules/Examenes/Http/Controllers/ExamenesParityController.php:1551-1947`), alimentado por `ImagenesUiService::imagenesDashboardExportPayload()` (`laravel-app/app/Modules/Examenes/Services/ImagenesUiService.php:195-217`) y `buildImagenesDashboardReport()` (línea ~2030-2262, el método que construye todo el contenido narrativo/KPI de "Resumen KPI").

Ruta: `GET /v2/imagenes/dashboard/export/excel`. No toca Cirugías, no toca el PDF (que es un export aparte, aunque comparte la misma fuente de datos — fuera de alcance de esta fase), no toca el Excel legado de kanban (`/examenes/reportes/excel`).

---

## 1. Qué queda obsoleto exactamente

### 1.1 Bloques de `buildImagenesDashboardReport()` que dejan de usarse en el Excel

| Bloque (clave del array de retorno) | Líneas aprox. | Acción |
|---|---|---|
| `hallazgosClave` | 2092 (cálculo) / escritura 1614-1629 | Deja de escribirse en Excel. El método de cálculo puede quedar (lo sigue usando el PDF) — **no se borra del service**, solo se deja de invocar/escribir en el Excel. |
| `methodology` | 2092-2103 (cálculo) / escritura 1632-1647 | Deja de escribirse en Excel. Igual que arriba: el PDF puede seguir usándolo; si el PDF también se quiere limpiar, sería una fase 3 aparte (no pedida ahora). |
| `executiveKpis` | 2105-2112 | No se usa hoy en el Excel (ya estaba sin consumir) — confirmar y dejar igual. |
| `cohortKpis` (Bloque 2 — Solicitudes) | 2114-2124 / escritura 1710-1718 | Se reemplaza por los 2-3 números que sí van a "Resumen Operativo" (Solicitudes, Sin agenda, Oportunidad estimada/Pérdida por no agendar). El resto (Agendadas al corte, Realizadas al corte, Realizadas posterior al corte, Cumplimiento al corte, Ausentes, Pendientes vigentes) deja de exportarse. |
| `operationalKpis` (Bloque 1 — Operación del período) | 2126-2136 / escritura 1650-1658 | Se reemplaza por los 2 números que sí van a "Resumen Operativo" (Realizadas/Atendidos, Facturados, Pendiente de facturar). El resto (Informadas, Cumplimiento cita→realización, Cancelados, Pérdida operativa, Pendientes operativos) deja de exportarse. |
| `qualityKpis` (Cumplimiento y oportunidad: SLA, TAT prom/mediana/P90, Día pico) | 2138-2144 / escritura 1661-1669 | **Eliminado completo.** Ningún dato de este bloque pasa a la nueva hoja. |
| `economicKpis` (con columna "fórmula") | 2146-2183 / escritura 1672-1680 | Se reemplaza por 1 sola cifra ("Oportunidad estimada") en Resumen Operativo. El resto (Producción facturada con fórmula, Pendiente de pago, Ticket promedio, Procedimientos facturados) deja de exportarse — esos valores ya viven en el Mapa Ejecutivo / Sección 04 del reporte. |
| `operationalTables` (4 tablas: Top exámenes realizados, Backlog por categoría, Rendimiento económico, Desglose por seguro) | 2185-2223 / escritura 1682-1707 | **Eliminadas las 4.** "Backlog por categoría" no se pierde como dato — su función real (identificar pendientes de facturar) la cubre la nueva hoja "Backlog de Facturación" con detalle nominal, que es más útil para Billing que un agregado por categoría. |
| `cohortTables` (Top 10 doctores solicitantes, Top exámenes solicitados) | 2225-2240 / escritura 1720-1745 | **Eliminadas.** Ya están en Sección 03 del Reporte Ejecutivo. |
| `scopeNotice` | 2243 / escritura 1586-1593 | Se elimina o se reduce a una sola línea de contexto si se quiere mantener (es solo un aviso azul, no un KPI) — opcional, bajo impacto. |

### 1.2 Hojas que SÍ se mantienen sin tocar

- **"Operación"** (1752-1837): sin cambios. 24 columnas, datos desde `$detailRows`.
- **"Solicitudes"** (1839-1922): sin cambios. 23 columnas, datos desde `$requestRows` (vía `buildImagenesDashboardSolicitudRows()`).

### 1.3 Hoja nueva: "Backlog de Facturación"

**No requiere ninguna query nueva.** `$detailRows` (ya cargado para la hoja "Operación") ya contiene exactamente los campos necesarios: `paciente`, `hc_number`, `examen`/`codigo`, `sede`, `empresa_seguro`, `afiliacion`/`afiliacion_categoria`, `fecha_examen`, `estado_agenda`/`estado_realizacion`/`estado_informe`, `facturado`, `estado_facturacion`, `monto_pendiente_estimado`, `sin_tarifa_publica`, `billing_source`.

La hoja se construye **filtrando `$detailRows` donde `facturado` es falso** (mismo criterio que ya usa la columna "Facturación" de la hoja Operación para mostrar "PENDIENTE"). Esto significa: cero impacto en consultas SQL, cero riesgo de afectar `buildImagenesDashboardSummary()` u otro método compartido — es pura reutilización de un array que el controlador ya tiene en memoria.

Columnas propuestas (subconjunto de las ya existentes en "Operación", reordenadas para flujo de trabajo de Billing):
1. `#`
2. Paciente
3. HC
4. Examen
5. Sede
6. Empresa seguro
7. Afiliación / Categoría
8. Fecha del examen
9. Estado de realización
10. Estado de informe
11. Estado de facturación (operativo)
12. Monto pendiente estimado
13. Sin tarifa nivel 3 (alerta para revisión tarifaria)
14. Form ID (trazabilidad hacia el sistema origen)

---

## 2. Estimación de reducción de complejidad

| Métrica | Antes (hoja "Resumen KPI") | Después (hoja "Resumen Operativo") |
|---|---|---|
| Bloques/secciones | 11 (filtros, hallazgos, metodología, bloque 1, cumplimiento, economía, 4 tablas, bloque 2, 2 tablas) | 1 bloque único de 8 campos |
| Filas aproximadas generadas (rango típico) | ~60-90 filas (10 KPIs × 3 bloques + 4-6 tablas con 5-10 filas c/u + texto narrativo) | ~10 filas fijas |
| Consultas/cálculos que el Excel obliga a mantener sincronizados con el Reporte Ejecutivo | 9 bloques de KPIs/tablas duplicados | 0 — los 8 campos de Resumen Operativo son números crudos sin narrativa, no requieren mantenerse "alineados" en redacción con el reporte |
| Hojas totales | 3 | 4 (se agrega Backlog, pero es una reutilización de datos ya cargados, no una hoja "pesada") |
| Nuevas queries necesarias | — | **0** |
| Código a eliminar en el controlador (escritura Excel) | — | Aproximadamente las líneas 1586-1745 del controlador (160 líneas: scope notice, filtros, hallazgos, metodología, bloque 1, cumplimiento, economía, tablas operativas, bloque 2, tablas de cohorte) se reemplazan por ~20-30 líneas para escribir 8 campos fijos |
| Código a eliminar/dejar de invocar en el service | — | Las claves no usadas de `buildImagenesDashboardReport()` (`hallazgosClave`, `methodology`, `qualityKpis`, `operationalKpis`, `cohortKpis`, `economicKpis`, `operationalTables`, `cohortTables`) — **se recomienda no borrar el método**, solo dejar de leer esas claves en el Excel, porque el PDF (`imagenesDashboardExportPdf`) también consume `buildImagenesDashboardReport()` y está fuera de alcance de esta fase |

**Conclusión de complejidad:** reducción de ~85% en el contenido de la hoja de resumen, sin tocar ninguna consulta a base de datos nueva, y sin riesgo de afectar el PDF si se decide no tocar `buildImagenesDashboardReport()` (solo dejar de consumir esas claves desde el método de export de Excel).

---

## 3. Confirmación de que Billing y Agendamiento no se ven afectados

- **Billing:** hoy trabaja sobre la hoja "Operación" (conciliación, trazabilidad, auditoría) — **no se toca**. Además gana una hoja adicional ("Backlog de Facturación") que es literalmente un filtro de lo que ya usa, así que es una mejora, no una pérdida. La columna "fórmula" de `economicKpis` (que solo explicaba cómo se calculaba la producción facturada) desaparece del Excel, pero ese texto explicativo no es un dato operativo — es documentación, y se recomienda moverlo a un documento de referencia (ver sección 4).
- **Agendamiento:** hoy trabaja sobre la hoja "Solicitudes" (sin agenda, ausentes, pendientes, gestión operativa) — **no se toca**.
- **Ningún campo usado por Billing/Agendamiento en las hojas Operación/Solicitudes depende de las claves que se eliminan** de "Resumen KPI" — son arreglos completamente independientes en el código (`$detailRows` y `$requestRows` se calculan aparte de `$report`).
- **Riesgo residual:** si algún usuario de Radiología usa hoy el bloque "Cumplimiento y oportunidad" (TAT/SLA) del Excel para su seguimiento de calidad de informes, ese dato deja de existir en cualquier export. No hay evidencia en este audit de que Radiología use el Excel (el dashboard operativo es la herramienta natural para eso), pero se señala como el único grupo de usuarios no confirmado explícitamente en este plan. Recomendación: confirmar con Radiología antes de implementar, o mantener TAT/SLA como pestaña opcional fuera del Excel principal si lo siguen necesitando.

---

## 4. Metodología — recomendación

Coincido con la intuición: la metodología no debería viajar en cada export. Propuesta concreta:
- Mover el contenido actual de `$methodology` (las 10 líneas de `buildImagenesDashboardReport()`, líneas 2092-2103) a un documento markdown de referencia permanente, p. ej. `docs/imagenes-dashboard-metodologia.md`, versionado en el repo y enlazable desde el dashboard o el reporte ejecutivo (un link "¿Cómo se calculan estos datos?").
- El método `buildImagenesDashboardReport()` puede seguir devolviendo `methodology` (el PDF lo sigue usando si así se decide en una fase futura), pero el exportador de Excel deja de escribirlo.
- Esto es trabajo de documentación, no de código de negocio — bajo riesgo, cero impacto en cálculos.

---

## 5. Diseño final propuesto — las 4 hojas

### Hoja 1 — "Resumen Operativo"
Tabla simple de 2 columnas (Campo / Valor), sin gráficos, sin narrativa:

| Campo | Valor |
|---|---|
| Período | (rango de fechas filtrado) |
| Sede | (sede filtrada o "Todas") |
| Solicitudes | N |
| Realizadas | N |
| Facturadas | N |
| Pendiente de facturar | N |
| Solicitudes sin agenda | N |
| Oportunidad estimada | $N |

Fuente de cada valor (ya disponible, sin nuevas queries):
- Período/Sede → `filtersSummary` / `filters['sede']` (igual que hoy en el bloque de filtros).
- Solicitudes → `meta`/`cards` "Solicitudes de exámenes" (mismo dato que hoy alimenta `cohortKpis[0]`).
- Realizadas → `cards` "Atendidos" (mismo dato que hoy alimenta `operationalKpis[1]`).
- Facturadas → `cards` "Facturados".
- Pendiente de facturar → `cards` "Pendiente de facturar".
- Solicitudes sin agenda → `meta['solicitudes_sin_agenda']` (mismo dato que hoy en `cohortKpis[6]`).
- Oportunidad estimada → `meta['solicitudes_sin_agenda_monto_estimado']` (mismo dato que hoy en `cohortKpis[5]`, "Pérdida económica por no agendar" — se renombra a "Oportunidad estimada" para consistencia de nombre con el Reporte Ejecutivo).

### Hoja 2 — "Operación"
Sin cambios (24 columnas, igual que hoy).

### Hoja 3 — "Solicitudes"
Sin cambios (23 columnas, igual que hoy).

### Hoja 4 — "Backlog de Facturación" (nueva)
Filtro de `$detailRows` donde `facturado` es falso, con las 14 columnas detalladas en la sección 1.3.

---

## 6. Siguiente paso

Quedo a la espera de aprobación para implementar. Si se aprueba, el plan de implementación sería:
1. Modificar solo `ExamenesParityController::imagenesDashboardExportExcel()`: reemplazar el bloque de escritura de "Resumen KPI" (líneas ~1586-1745) por la escritura de "Resumen Operativo" (8 filas) y agregar la hoja "Backlog de Facturación" (filtro de `$detailRows`).
2. No modificar `buildImagenesDashboardReport()` ni ningún método de `ImagenesUiService` usado por el PDF — solo dejar de leer las claves no usadas en el flujo de Excel.
3. No tocar Cirugías, el dashboard operativo, ni el PDF.
4. Validar con `php -l`, descarga real del Excel en staging, y confirmar visualmente las 4 hojas con Billing/Agendamiento antes de aprobar el merge.
