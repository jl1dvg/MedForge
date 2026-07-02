# Diseño de Fase 2 — `CirugiasDashboardService`

> **Este documento es solo diseño.** No se ha extraído ningún Repository. Sirve para acordar el plan antes de tocar código, según el Principio 10 y las reglas de ejecución de Fase 2 en [`pdo-to-container-migration.md`](./pdo-to-container-migration.md#reglas-de-ejecución--fase-2-por-servicio-cuando-aplique).

**Prerrequisito cumplido:** Fase 0 + Fase 1 completas para los 5 servicios de Cirugías (`CirugiaService`, `CirugiasDashboardService`, `ProtocolosTemplateReadService`, `ProtocolosTemplateWriteService`, `CirugiasDerivacionService`) — todos resueltos por el Service Container, ninguno recibe `PDO` en su constructor.

**Por qué este servicio califica para Fase 2** (criterio del Principio 10): supera ~1000 líneas (2272), mezcla reglas de negocio con acceso a datos de forma entrelazada, y es el reporte ejecutivo de mayor tráfico/visibilidad del sistema — exactamente el perfil que el criterio busca marcar.

---

## 1. Inventario de métodos (48 métodos, 2272 líneas)

| # | Método | Tipo | Líneas aprox. |
|---|--------|------|---------------|
| 1 | `getAfiliacionOptions` | público, delega a `AfiliacionDimensionService` | 5 |
| 2 | `getSeguroOptions` | público, delega | 5 |
| 3 | `getAfiliacionCategoriaOptions` | público, delega | 5 |
| 4 | `setSeguroFilter` | público, estado mutable | 5 |
| 5 | `getSedeOptions` | público, SQL propio | 42 |
| 6 | `getTotalCirugias` | público, SQL + filtros | 44 |
| 7 | `getCirugiasSinFacturar` | público, delega a #8 | 18 |
| 8 | `getCirugiasFacturacionTrazabilidad` | público, orquesta #10 + reglas de negocio (facturado/pendiente/cancelado) | 99 |
| 9 | `getCirugiasFacturacionDetalle` | público, delega a #11 | 19 |
| 10 | `fetchCirugiasFacturacionRows` | privado, SQL | 63 |
| 11 | `fetchCirugiasFacturacionDetalleRows` | privado, SQL | 96 |
| 12 | `buildCirugiasFacturacionSql` | privado, construcción dinámica de SQL (detección de columnas) | 107 |
| 13 | `mergeCirugiaBillingEvidence` | privado, regla de negocio (qué fuente de billing prevalece) | 34 |
| 14 | `hasCirugiaBillingSourceEvidence` | privado, regla de negocio | 25 |
| 15 | `resolveCirugiaDashboardBillingState` | privado, regla de negocio (clasifica estado) | 21 |
| 16 | `getDuracionPromedioMinutos` | público, SQL | 57 |
| 17 | `getTatRevisionProtocolos` | público, SQL + cálculo de percentiles en PHP | 92 |
| 18 | `getEstadoProtocolos` | público, SQL + delega a `Cirugia::getEstado()` | 60 |
| 19 | `getCirugiasPorMes` | público, SQL | 54 |
| 20 | `getTopProcedimientos` | público, SQL | 60 |
| 21 | `getTopCirujanos` | público, SQL | 67 |
| 22 | `getTopDoctoresSolicitudesRealizadas` | público, SQL con builder de fragmentos | 113 |
| 23 | `getCirugiasPorConvenio` | público, SQL | 82 |
| 24 | `getProgramacionKpis` | público, SQL + cálculo pesado en PHP (cumplimiento, backlog, lead time, lateralidad) | 225 |
| 25 | `getReingresoMismoDiagnostico` | público, delega al módulo KPI (⚠️ bug de namespace ya reportado) | 51 |
| 26 | `getCirugiasSinSolicitudPrevia` | público, SQL | 75 |
| 27-42 | Helpers de normalización/formato (`percentage`, `calculatePercentile`, `normalizeDate/Time`, `parseTimestamp`, `normalizeTextValue`, `normalize*Filter`, `formatCategoriaLabel`, `*Expr`, `resolve*Context`, `seguroFilter*`, `solicitudSede*`, `normalizeSql*`) | privados, utilidades puras o generadores de fragmentos SQL | ~350 |
| 43-44 | `tableExists`, `columnExists` | privados, introspección de schema con cache | 34 |
| 45-46 | `normalizeLateralidad`, `lateralidadCompatible` | privados, regla de negocio clínica | 68 |
| 47 | `fetchReportProtocoloAggregates` | privado, SQL consolidado + agregación en PHP (agregado en la Fase 3 de performance, PR #472) | 115 |
| 48 | `buildReportPayload` | público, **orquestador** — arma el payload completo del reporte ejecutivo | 148 |
| — | `emptyProgramacionKpis` | privado, valor por defecto | 20 |

---

## 2. Dominios naturales identificados

El servicio mezcla, sin separación de clases, cinco responsabilidades de negocio distintas más una responsabilidad de infraestructura transversal:

### Dominio A — Facturación y trazabilidad de billing
Métodos: `getTotalCirugias`, `getCirugiasSinFacturar`, `getCirugiasFacturacionTrazabilidad`, `getCirugiasFacturacionDetalle`, `fetchCirugiasFacturacionRows`, `fetchCirugiasFacturacionDetalleRows`, `buildCirugiasFacturacionSql`, `mergeCirugiaBillingEvidence`, `hasCirugiaBillingSourceEvidence`, `resolveCirugiaDashboardBillingState`.

Qué hace: determina si una cirugía está facturada, pendiente de facturar, pendiente de pago o cancelada, cruzando `protocolo_data` con dos fuentes de billing distintas (`billing_facturacion_real` para privados, `billing_main`/`billing_procedimientos` para públicos). Es el dominio más grande (~470 líneas) y el que más mezcla SQL con reglas de negocio (`mergeCirugiaBillingEvidence` y `resolveCirugiaDashboardBillingState` son reglas de negocio puras, ya separadas en métodos propios — buena señal para una extracción limpia).

### Dominio B — Programación y cumplimiento de agenda
Métodos: `getProgramacionKpis`, `getCirugiasSinSolicitudPrevia`, `normalizeLateralidad`, `lateralidadCompatible`.

Qué hace: cruza `solicitud_procedimiento`/`solicitud_crm_meta` con `protocolo_data` para calcular cumplimiento, backlog, lead time y concordancia de lateralidad (¿el ojo operado coincide con el solicitado?). Es el método más complejo del archivo (`getProgramacionKpis`, 225 líneas) — mezcla una query SQL grande con un bucle PHP que calcula 8 métricas distintas sobre el mismo result set.

### Dominio C — Catálogos y series (producción, top-N, convenios)
Métodos: `getCirugiasPorMes`, `getTopProcedimientos`, `getTopCirujanos`, `getTopDoctoresSolicitudesRealizadas`, `getCirugiasPorConvenio`, `getEstadoProtocolos`, `getSedeOptions`.

Qué hace: agregaciones de conteo/agrupación directas sobre `protocolo_data` (por mes, por procedimiento, por cirujano, por convenio). Es el dominio más "puro dato" — casi no tiene reglas de negocio mezcladas, son `GROUP BY` + formateo de labels. El mejor candidato para migrar a Query Builder en una eventual Fase 3 (ver criterios en `medforge-architecture-principles.md` §8).

### Dominio D — Calidad clínica y TAT (tiempos de respuesta)
Métodos: `getDuracionPromedioMinutos`, `getTatRevisionProtocolos`, `resolveInicioCirugiaTimestamp`, `normalizeDate`, `normalizeTime`, `parseTimestamp`, `calculatePercentile`, `percentage`.

Qué hace: calcula duración de cirugías y tiempo de revisión de protocolos (TAT), con lógica de resolución de timestamps en cascada (4 estrategias distintas para determinar "inicio de cirugía" según qué campos de fecha/hora existen) y cálculo de percentiles en PHP puro. Los helpers de fecha/percentil son reutilizables y no dependen de SQL — buenos candidatos a extraerse como utilidades independientes del Repository.

### Dominio E — Dimensiones y filtros (afiliación/seguro/sede)
Métodos: `getAfiliacionOptions`, `getSeguroOptions`, `getAfiliacionCategoriaOptions`, `setSeguroFilter`, `normalizeAfiliacionFilter`, `normalizeSeguroFilter`, `normalizeAfiliacionCategoriaFilter`, `normalizeSedeFilter`, `formatCategoriaLabel`, `afiliacionGroupKeyExpr`, `afiliacionLabelExpr`, `seguroLabelExpr`, `seguroKeyExpr`, `sedeExpr`, `resolveAfiliacionDimensionsContext`, `resolveAfiliacionCategoriaContext`, `seguroFilterSql`, `seguroFilterBindings`, `bindSeguroFilter`, `solicitudSedeFilterCondition`, `solicitudSedeJoin`, `normalizeSqlText`, `normalizeSqlKey`.

Qué hace: la mayoría de las opciones (`getAfiliacionOptions`, etc.) ya delegan a `AfiliacionDimensionService` — bien encapsulado tras la migración de Shared. Pero un conjunto grande de helpers *propios* de Cirugías sigue generando fragmentos SQL específicos (`sedeExpr`, `solicitudSedeFilterCondition/Join` — el fix del PR #463) para resolver el filtro de sede, que `AfiliacionDimensionService` no cubre porque sede es un concepto propio de `procedimiento_proyectado`, no de afiliación. Este dominio es transversal: casi todos los métodos de los dominios A-D lo usan para construir sus `WHERE`.

### Dominio F (transversal, no es un dominio de negocio) — Introspección de schema
Métodos: `tableExists`, `columnExists` (con cache de instancia).

Qué hace: detecta en runtime si ciertas tablas/columnas opcionales existen (`billing_facturacion_real`, `afiliacion_categoria_map`, `protocolo_data.fecha_firma`, etc.) para degradar con gracia en entornos donde el schema aún no tiene esas columnas. Es infraestructura pura, no lógica de negocio de Cirugías — candidato a vivir en una clase base compartida entre Repositories (ya existe duplicado casi idéntico en `AfiliacionDimensionService` y existía en `CronTaskRepository` antes de la migración).

### Orquestador — Reporte ejecutivo
Métodos: `buildReportPayload`, `fetchReportProtocoloAggregates`, `getReingresoMismoDiagnostico`.

Qué hace: es el método que consume TODOS los dominios anteriores para armar el payload que consume el frontend React (`/v2/cirugias/dashboard/report`). No tiene SQL propio salvo `fetchReportProtocoloAggregates` (el query consolidado que reemplazó 6 queries separadas en el PR #472 de performance). Este es, en esencia, el único método que debería sobrevivir en el `CirugiasDashboardService` final después de una extracción completa — todo lo demás se convierte en llamadas a Repositories.

---

## 3. Responsabilidades mezcladas — dónde está el acoplamiento real

1. **SQL y reglas de negocio en el mismo método**: `getCirugiasFacturacionTrazabilidad` (líneas 173-271) ejecuta la query (delegando a `fetchCirugiasFacturacionRows`) y en el mismo método clasifica cada fila en facturado/pendiente/cancelado/público/privado — la extracción debería separar "traer las filas" (Repository) de "clasificar y sumar" (Service).
2. **Construcción dinámica de SQL según schema disponible**: `buildCirugiasFacturacionSql` decide en runtime, según `tableExists()`/`columnExists()`, qué subquery de billing usar. Esto es responsabilidad de un Repository (sabe cómo obtener los datos pase lo que pase con el schema), no debería mezclarse con la clasificación de negocio que ocurre después.
3. **Cálculo pesado en PHP sobre result sets crudos**: `getProgramacionKpis` (225 líneas) y `getTatRevisionProtocolos` traen filas crudas y calculan 8+ métricas de negocio en un solo bucle `foreach`. Es lógica de negocio legítima, pero está atada al mismo método que ejecuta el SQL — separar el fetch (Repository) de la agregación (Service) permite testear la lógica de negocio con datos fixture, sin base de datos.
4. **Helpers de SQL usados por casi todos los dominios**: `sedeExpr`, `seguroFilterSql`, `resolveAfiliacionCategoriaContext`, `solicitudSedeJoin` son fragmentos de SQL reutilizados transversalmente. En un Repository, estos serían métodos protegidos/privados del propio Repository (o de una clase base), no de un "Service" que se supone no conoce SQL (Principio 4).

---

## 4. Repositories propuestos

No se implementan en esta fase — este es el diseño a validar antes de ejecutar.

| Repository propuesto | Dominio | Métodos que recibiría (fetch puro, sin clasificar) |
|---|---|---|
| `CirugiasBillingRepository` | A | `fetchCirugiasFacturacionRows`, `fetchCirugiasFacturacionDetalleRows`, `buildCirugiasFacturacionSql`, `fetchTotalCirugias` (renombrado de `getTotalCirugias`) |
| `CirugiasProgramacionRepository` | B | `fetchProgramacionRows` (la query cruda de `getProgramacionKpis`, sin el cálculo posterior), `fetchCirugiasSinSolicitudPreviaCount` |
| `CirugiasCatalogRepository` | C | `fetchPorMesRows`, `fetchTopProcedimientosRows`, `fetchTopCirujanosRows`, `fetchTopDoctoresSolicitudesRealizadasRows`, `fetchPorConvenioRows`, `fetchEstadoProtocolosRows`, `fetchSedeOptions` |
| `CirugiasCalidadRepository` | D | `fetchDuracionRows`, `fetchTatRevisionRows` |
| `CirugiasSedeFilterSupport` (helper, no Repository — ver nota abajo) | E (parte transversal) | `sedeExpr`, `solicitudSedeFilterCondition`, `solicitudSedeJoin` |
| — | E (parte ya resuelta) | Ya delegado a `AfiliacionDimensionService` (Shared, migrado) |
| `SchemaIntrospection` (trait o clase base compartida) | F | `tableExists`, `columnExists` — candidato a **compartirse** entre todos los Repositories nuevos, no solo los de Cirugías (ya duplicado en `AfiliacionDimensionService`) |

**Nota sobre `CirugiasSedeFilterSupport`**: no se propone como Repository porque no ejecuta queries — genera fragmentos SQL que otros Repositories consumen (similar a cómo `AfiliacionDimensionService::buildContext()` ya funciona para afiliación). El nombre y la forma final (trait, clase inyectada, o método estático) se decide en el momento de implementar, no en este diseño.

`CirugiasDashboardService` (el Service, post-extracción) conservaría: `getCirugiasFacturacionTrazabilidad` (ahora solo clasifica, llamando a `CirugiasBillingRepository`), `getProgramacionKpis` (ahora solo calcula, llamando a `CirugiasProgramacionRepository`), toda la lógica de `mergeCirugiaBillingEvidence`/`resolveCirugiaDashboardBillingState`/`normalizeLateralidad`/`lateralidadCompatible`/`calculatePercentile`, y el orquestador `buildReportPayload`.

---

## 5. Dependencias entre dominios

```
buildReportPayload (orquestador)
 ├── Dominio B (getProgramacionKpis) ──┐
 ├── Dominio A (getCirugiasFacturacionTrazabilidad, recibe resultado de B para no duplicar query)
 ├── fetchReportProtocoloAggregates (consolida C + D en una sola query — PR #472)
 ├── Dominio C (getTopDoctoresSolicitudesRealizadas — no está en el consolidado, corre aparte)
 └── getReingresoMismoDiagnostico (Dominio KPI externo, con el bug de namespace ya reportado)

Dominio E (sede/afiliación) es transversal: A, B y C lo consumen para construir sus WHERE.
Dominio F (schema introspection) es transversal: A y E lo consumen activamente; B y D en menor medida.
```

**Implicación para el orden de extracción**: A depende del resultado de B (`getCirugiasFacturacionTrazabilidad` recibe `$programacionKpis` como parámetro opcional, fix del PR #472 para no duplicar la query). Si se extrae A antes que B, el Repository de A necesita poder recibir el resultado de B igual que hoy — la extracción no cambia esa relación, solo mueve el fetch.

---

## 6. Plan de extracción propuesto (orden, no ejecutar aún)

1. **`SchemaIntrospection`** primero — es la base transversal que todos los Repositories nuevos usarán. Extraerla evita duplicar `tableExists`/`columnExists` en 4 clases nuevas (ya está duplicado en `AfiliacionDimensionService`; esta sería la tercera copia si no se comparte).
2. **`CirugiasCatalogRepository`** (Dominio C) — el más simple, sin reglas de negocio mezcladas, menor riesgo. Buen segundo paso para validar el patrón de extracción sobre este servicio específico antes de tocar los dominios con lógica de negocio pesada.
3. **`CirugiasCalidadRepository`** (Dominio D) — separar el fetch de TAT/duración de los helpers de cálculo (que se quedan en el Service, son lógica de negocio reutilizable).
4. **`CirugiasBillingRepository`** (Dominio A) — el más grande y el que requiere más cuidado: `getCirugiasFacturacionTrazabilidad` debe seguir recibiendo `$programacionKpis` sin duplicar la query de B.
5. **`CirugiasProgramacionRepository`** (Dominio B) — el más complejo de separar limpiamente (225 líneas con fetch y cálculo entrelazados); se deja para el final cuando el patrón ya esté probado 3 veces en este mismo servicio.
6. **`CirugiasSedeFilterSupport`** — se extrae en paralelo a cualquiera de los pasos 2-5, el primero que lo necesite lo crea; los siguientes lo reutilizan.

Cada paso es su propio PR (regla de Fase 2 en el plan de migración: un PR = un Repository), con el mismo estándar de verificación que este PR de Fase 0+1 (diff aislado, tests existentes, comparación de comportamiento).

---

## 7. Lo que este documento NO decide

- No decide si `CirugiasCatalogRepository` debería usar Query Builder en vez de SQL crudo — eso es una evaluación de Fase 3, posterior y por método, según los criterios ya documentados en `medforge-architecture-principles.md` §8.
- No decide nombres finales de clase, namespace exacto, ni si `CirugiasSedeFilterSupport` termina siendo un trait o una clase inyectada — se decide en el PR que lo implemente.
- No fija fecha para ejecutar Fase 2 en Cirugías — depende de que el usuario apruebe este diseño y decida cuándo priorizarlo frente al resto del plan de migración (Fase 0+1 de los demás módulos).
