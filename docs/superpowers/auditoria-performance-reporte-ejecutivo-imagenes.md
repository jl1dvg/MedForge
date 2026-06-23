# Auditoría de performance — Reporte Ejecutivo de Imágenes

> Documento de diagnóstico únicamente. **No se implementó ninguna estrategia de Redis/cache en este turno.** No se tocó lógica de KPIs, Excel, PDF, dashboard operativo ni Cirugías.

## 1. Diagnóstico de cuellos de botella

El endpoint `GET /v2/imagenes/dashboard/report` → `ImagenesUiController::dashboardReport()` → `ImagenesUiService::buildExecutiveReportPayload()` ejecuta, en cada request, esta secuencia (instrumentada con `microtime()` y logueada como `imagenes.executive_report.timings`):

```
filters            → construye $filters desde el query string
fetch_rows         → fetchImagenesRealizadas() + decorateImagenRow() por fila
solicitud_pipeline → fetchImagenesSolicitudPipeline()
                       └─ internamente llama fetchImagenesSolicitudesSinAgendaEstimate()
summary_build      → buildImagenesDashboardSummary()
tiempo_acceso      → fetchImagenesTiempoAccesoExamen()
sede_options       → estático, costo ~0
```

**Hallazgo principal — subquery pesado triplicado.**
`buildImagenesSolicitudFlowSubquery($filters)` (línea ~1077) construye un derived-table SQL con:
- joins contra `consulta_examenes`, `procedimiento_proyectado`
- subquery agregado sobre `imagenes_informes` (GROUP BY form_id)
- join opcional contra el índice de archivos NAS
- agregados de billing (`buildImagenesBillingAggregateSources()`)
- múltiples `MIN(CASE WHEN ...)` para fechas de agenda/realización

Este SQL se **reconstruye y re-ejecuta de forma independiente, 3 veces por carga de página**:

| # | Método que lo ejecuta | Para qué |
|---|---|---|
| 1 | `fetchImagenesSolicitudPipeline()` (línea 628) | KPIs del pipeline de solicitudes |
| 2 | `fetchImagenesSolicitudesSinAgendaEstimate()` (línea 917, llamado **dentro** de #1, línea 795) | Estimado de pérdida por solicitudes sin agenda |
| 3 | `fetchImagenesTiempoAccesoExamen()` (línea 833, llamado por separado desde `buildExecutiveReportPayload()`) | Mediana/P90 de tiempo de acceso |

Como ninguna de las tres comparte resultado, el motor de BD recalcula el mismo derived-table (con sus joins a `imagenes_informes`, NAS y billing) tres veces en la misma request. Este es, con alta probabilidad, el cuello de botella dominante — más aún si las tablas involucradas (`consulta_examenes`, `imagenes_informes`, billing) no tienen índices óptimos para el rango de fechas usado.

Adicionalmente, `fetchImagenesRealizadas()` (stage `fetch_rows`) recorre y decora cada fila con `decorateImagenRow()` en PHP — costo proporcional al volumen de filas del rango, no cacheable por SQL pero sí cacheable por payload completo.

## 2. ¿`buildExecutiveReportPayload()` cachea correctamente?

**No.** El método no contiene ninguna llamada a `Cache::`. Solo lleva instrumentación de tiempos (`$timings`), no cache de resultados. Las únicas 3 instancias de `Cache::remember()` en todo `ImagenesUiService.php` son:

| Cache key | TTL | Qué cachea | ¿Cubre el reporte? |
|---|---|---|---|
| `imagenes.afiliacion_categoria_map.rows` | 3600s | Tabla `afiliacion_categoria_map` completa | No — es un mapa de apoyo, no el payload |
| `imagenes.tarifa_description_index` | 3600s | Índice de `tarifario_2014` | No |
| `imagenes.tarifa_code_prices.{codeId}` | 3600s | Precios por código de tarifa | No |

Ninguna cubre `fetchImagenesRealizadas`, `fetchImagenesSolicitudPipeline`, `fetchImagenesSolicitudesSinAgendaEstimate` ni `fetchImagenesTiempoAccesoExamen` — que son los stages más caros según el propio `$timings`.

También existen ~9 arrays de cache **a nivel de instancia** (`tableExistsCache`, `columnExistsCache`, `tarifaCodeCache`, etc.) en `ImagenesUiService`, pero `ImagenesUiController` crea `new ImagenesUiService()` en su constructor — una instancia nueva por request — por lo que estos caches solo deduplican llamadas *dentro* de la misma request, nunca entre requests.

## 3. Queries/subqueries que se repiten

1. `buildImagenesSolicitudFlowSubquery()` — repetido 3 veces (ver sección 1).
2. `tableExists()` / `columnExists()` — chequeos de metadata de esquema, cacheados solo por instancia (irrelevante entre requests, pero correcto dentro de una).
3. `obtenerTarifarioPorCodigo()` dentro de `fetchImagenesSolicitudesSinAgendaEstimate()` — cacheado localmente por `$tarifarioCache` (variable local del método), por lo que no repite por código *dentro* de esa llamada, pero si el método se repitiera entre requests, se recalcularía siempre desde cero (no usa `Cache::remember`).

## 4. Métodos más costosos (ranking estimado)

1. **`buildImagenesSolicitudFlowSubquery()`** (ejecutado x3) — más caro por repetición, no por complejidad individual.
2. **`fetchImagenesRealizadas()` + `decorateImagenRow()` por fila** — costo lineal con el volumen de filas del rango de fechas.
3. **`fetchImagenesSolicitudPipeline()`** — agregación con 13 `SUM(CASE WHEN...)` sobre el flow subquery + join a `patient_data` + join de categoría.
4. **`fetchImagenesTiempoAccesoExamen()`** — relativamente liviano en SQL, pero repite el flow subquery completo solo para extraer 3 columnas.
5. **`fetchImagenesSolicitudesSinAgendaEstimate()`** — repite el flow subquery + lookup de tarifario por código (loop en PHP).

## 5. Qué se puede cachear sin riesgo

Datos derivados, de solo lectura, deterministas para el mismo conjunto de filtros (`fecha_inicio`, `fecha_fin`, `sede`, `tipo_examen`, `afiliacion`/`afiliacion_categoria`, `seguro`, `paciente`, `estado_agenda`):

| Candidato | Riesgo de cachear | Filtros que determinan la key |
|---|---|---|
| Payload completo de `buildExecutiveReportPayload()` | Bajo — es de solo lectura, no afecta KPIs operativos en vivo (agenda/facturación se actualizan async) | fecha_inicio, fecha_fin, sede, tipo_examen, afiliacion, afiliacion_categoria, seguro, paciente, estado_agenda |
| Resultado de `buildImagenesSolicitudFlowSubquery()` (como dataset materializado, no como SQL string) | Bajo — mismo motivo | mismos filtros |
| `fetchImagenesTiempoAccesoExamen()` | Bajo | fecha_inicio, fecha_fin, sede, tipo_examen |
| `fetchImagenesSolicitudesSinAgendaEstimate()` | Bajo | mismos filtros + afiliación/categoría |
| Tarifario/códigos (`obtenerTarifarioPorCodigo`, `pricesForTarifaCode`) | Ya parcialmente cacheado (TTL 3600s) — se puede extender el patrón | código + categoría |
| Charts de rentabilidad (si existen en otro endpoint, no auditado aquí) | Por confirmar — fuera del alcance de este documento | — |

No se recomienda cachear datos que dependan de `paciente` como filtro libre (búsqueda por texto) si el volumen de combinaciones es muy alto — mejor evaluar excluir ese filtro de la cache key y dejarlo como post-filtro en PHP, o aceptar cache miss frecuente en ese caso.

## 6. Propuesta de implementación incremental (NO implementada)

**Paso 1 — cache de payload completo (mayor beneficio, menor riesgo):**
- Envolver el cuerpo de `buildExecutiveReportPayload()` en `Cache::remember($key, $ttl, fn() => ...)`.
- Cache key: `imagenes_report:{hash_filtros}` donde `hash_filtros = md5(json_encode($filters_normalizados))`.
- TTL: 5–15 minutos (sugerido: 10 min como punto medio).
- Store: Redis (ya configurado como `CACHE_STORE=redis`, `REDIS_CACHE_DB=1`, prefijo `medforge_prod_cache_`).

**Paso 2 (opcional, si Paso 1 no es suficiente) — cache de sub-resultados:**
- Cachear el dataset del flow subquery una sola vez por combinación de filtros, y pasarlo como parámetro a los 3 métodos que hoy lo reconstruyen — esto elimina la triplicación incluso en cache miss. Es un cambio de firma de método (no solo de cache), así que tiene más riesgo que el Paso 1 y debería ir en una iteración separada.
- Cachear `fetchImagenesTiempoAccesoExamen()` y `fetchImagenesSolicitudesSinAgendaEstimate()` por separado con sus propias keys (`imagenes_tiempo_acceso:{hash}`, `imagenes_sin_agenda:{hash}`) si se necesita invalidación más granular que el payload completo.

**Archivos que se modificarían (cuando se apruebe implementar):**
- `laravel-app/app/Modules/Examenes/Services/ImagenesUiService.php` — agregar `Cache::remember()` en `buildExecutiveReportPayload()` (y opcionalmente en los 3 métodos del Paso 2).
- `laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php` — leer un parámetro `refresh` del request y pasarlo al service para forzar bypass de cache.

## 7. Invalidación (propuesta, no implementada)

- TTL corto (5–15 min) como mecanismo principal — aceptable que los datos no sean 100% en tiempo real para un reporte ejecutivo.
- Soporte de `?refresh=1` en la query string: si está presente, el controller le indica al service que ignore la cache (`Cache::forget($key)` antes de `remember`, o pasar un flag `$forceRefresh` que evite la rama de cache).
- No se propone invalidación activa por escritura (p. ej. invalidar al registrar una nueva agenda/factura) en esta primera fase — el TTL corto ya cubre el caso de uso de un reporte ejecutivo que no necesita ser instantáneo.

## 8. Riesgo esperado

- **Bajo**, si se limita al Paso 1 (cache de payload completo): es un wrapper aditivo, no cambia ninguna query ni lógica de negocio, no toca Excel/PDF/dashboard operativo.
- Riesgo de **datos ligeramente desactualizados** durante la ventana del TTL — mitigado por TTL corto + `?refresh=1`.
- Riesgo de **cache key incompleta** si se omite algún filtro relevante al construir el hash — debe incluirse explícitamente cada filtro usado por `buildFilters()`, no solo los documentados en la solicitud del usuario.
- Predis (cliente Redis configurado) se comporta de forma compatible con `Cache::remember` estándar de Laravel — sin riesgo adicional conocido para este caso de uso (no se usan pipelines ni Lua scripts).

## 9. Beneficio estimado

- Con TTL de 10 min y un patrón de uso típico de reporte ejecutivo (mismos filtros consultados repetidamente por el mismo usuario o por distintos usuarios en la misma sesión de revisión), se espera que la **mayoría de las cargas después de la primera caigan en cache hit**, reduciendo el tiempo de respuesta de "tiempo total de las 5 queries pesadas" a "tiempo de deserialización desde Redis" (típicamente de varios cientos de ms a low-double-digit ms).
- Incluso sin Paso 2, eliminar la espera de fetch_rows + solicitud_pipeline + tiempo_acceso en cache hit cubre el cuello de botella reportado por el usuario ("el reporte sigue cargando lento") para el caso más común: re-visitar el mismo rango de fechas/sede poco después de la primera carga.
