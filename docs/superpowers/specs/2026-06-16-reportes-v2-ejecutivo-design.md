# Reportes v2 — Reporte Ejecutivo de Unidades de Negocio

**Fecha:** 2026-06-16  
**Rutas afectadas:** `/v2/cirugias/dashboard`, `/v2/imagenes/dashboard`  
**Tipo:** Nueva experiencia frontend + optimización backend + extensión de payload

---

## Contexto

Los dashboards actuales de Cirugías e Imágenes son vistas operativas densas (422 y 1449 líneas de Blade respectivamente) con múltiples gráficos, filtros avanzados y tablas. Son útiles para el equipo operativo, pero no están orientadas a la lectura ejecutiva.

El diseño fuente (`reportes_v2/`) define un reporte ejecutivo estilo documento: portada con síntesis, secciones numeradas con narrativa, Mapa Ejecutivo Financiero (flujo de la solicitud al cobro), y secciones específicas por unidad. Comparte gramática visual entre Cirugías e Imágenes.

Este spec cubre la implementación del nuevo frontend, las optimizaciones de backend previas, y la extensión de payloads sin romper funcionalidad existente.

---

## Perímetro tecnológico — todo dentro de Laravel

**Todo vive dentro del proyecto Laravel (`laravel-app/`).** No se crean dependencias externas ni se mueve nada fuera de este proyecto.

| Capa | Tecnología | Ubicación |
|---|---|---|
| Rutas | Laravel Router | `routes/v2/cirugias.php`, `routes/web.php` |
| Controladores | Laravel Controllers | `app/Modules/*/Http/Controllers/` |
| Servicios | Laravel Services (PHP) | `app/Modules/*/Services/` |
| Vistas | Blade (fullscreen) | `resources/views/*/` |
| JS/React | Vite + React 18 + TypeScript | `resources/js/v2/reportes-v2/` |
| CSS | Archivo estático versionado por Vite | `resources/css/v2/` ó `public/css/v2/` |
| Exportaciones | Endpoints Laravel existentes | Sin cambios |

Cuando el spec menciona "vista actual" o "vista anterior", se refiere exclusivamente a:
- `resources/views/cirugias/v2-dashboard.blade.php` (vista actual de Cirugías)
- `resources/views/examenes/v2-imagenes-dashboard.blade.php` (vista actual de Imágenes)

Ambas viven en Laravel y se mantienen como respaldo temporal dentro del mismo proyecto. No hay sistema PHP antiguo involucrado.

---

## Principio fundamental: dos informes independientes

**Cirugías e Imágenes son informes completamente separados.** No existe un reporte unificado ni un switch de datos entre módulos dentro de una misma pantalla.

| Aspecto | Compartido | Independiente por módulo |
|---|---|---|
| Sistema visual (CSS, tokens) | ✅ | |
| Componentes React genéricos (charts, KPI, BarsList, etc.) | ✅ | |
| Layout tipo documento (portada, secciones, footnote) | ✅ | |
| Blade view | | ✅ |
| Payload de datos | | ✅ |
| Secciones específicas (02, 03, 04) | | ✅ |
| Backend y lógica de negocio | | ✅ |
| Filtros (campos pueden diferir) | | ✅ |
| Exportaciones Excel/PDF | | ✅ |
| Entrada Vite / bundle JS | | ✅ (uno por módulo) |

El diseño fuente sirve como **lenguaje visual común**, no como reporte unificado. Si el toolbar del diseño original tiene un selector de unidad, en la implementación ese control se reemplaza por un **enlace externo** ("Ver informe de Imágenes" / "Ver informe de Cirugías"), no por un switch interno de datos.

---

## Decisiones de diseño

| Decisión | Elección |
|---|---|
| Layout | Fullscreen standalone — sin sidebar/topnav de MedForge |
| Rutas | Completamente separadas, backend independiente por módulo |
| Compilación JS | Vite (no Babel CDN en runtime) — un bundle por módulo |
| Datos | Solo reales; si falta fuente, el bloque se oculta |
| Deltas comparativos (portada) | Omitidos en v1 — requieren doble query de período anterior |
| Vistas actuales | Se preservan como respaldo; las rutas se conectan al nuevo diseño solo tras validación |
| Exportaciones | Los endpoints actuales de Excel y PDF se conservan; los botones los llaman con los filtros vigentes |
| Navegación entre informes | Solo enlace externo en el toolbar — no switch de datos |

---

## Arquitectura

### Frontend — Estructura por módulo

**CSS compartido** (una sola hoja, ambos módulos la usan):
```
public/css/v2/reportes-v2.css   ← report.styles.css del diseño (adaptado)
```

**Componentes compartidos** (biblioteca visual reutilizable):
```
resources/js/v2/reportes-v2/shared/
    charts.tsx    ← Recharts: TrendArea, AreaSeries, ColumnChart, DonutChart, RepTooltip
    lib.tsx       ← Cover, Section, ExecutiveMap, Kpi, BarsList, DonutLegend, Read, Recs, Footnote
    types.ts      ← Interfaces base compartidas (ReportPeriod, ReportSede, ExecFlow, ExecKpi, etc.)
```

**Bundle Cirugías** (entrada Vite independiente):
```
resources/js/v2/reportes-v2/cirugias/
    app.tsx         ← Entry point: monta React, lee window.MF_CIR_REPORT, renderiza CirugiasReport
    toolbar.tsx     ← Toolbar específico de Cirugías (filtros: período, sede + link a Imágenes)
    sections.tsx    ← Secciones 02, 03, 04 exclusivas de Cirugías
```

**Bundle Imágenes** (entrada Vite independiente):
```
resources/js/v2/reportes-v2/imagenes/
    app.tsx         ← Entry point: monta React, lee window.MF_IMG_REPORT, renderiza ImagenesReport
    toolbar.tsx     ← Toolbar específico de Imágenes (filtros: período, sede, tipo_examen + link a Cirugías)
    sections.tsx    ← Secciones 02, 03, 04 exclusivas de Imágenes
```

**Blade views** (cada módulo tiene el suyo, no comparten layout PHP):
```
resources/views/cirugias/
    v2-dashboard-report.blade.php   ← Vista fullscreen nueva (Cirugías)
    v2-dashboard.blade.php          ← Vista actual — NO se modifica hasta validación

resources/views/examenes/
    v2-imagenes-dashboard-report.blade.php   ← Vista fullscreen nueva (Imágenes)
    v2-imagenes-dashboard.blade.php          ← Vista actual — NO se modifica hasta validación
```

**Rutas temporales de validación** (paralelas a las actuales):
- `/v2/cirugias/dashboard/report` → `v2-dashboard-report.blade.php`
- `/v2/imagenes/dashboard/report` → `v2-imagenes-dashboard-report.blade.php`

Una vez validadas en staging, las rutas principales se conectan al nuevo diseño y las vistas actuales se renombran a `*-anterior.blade.php` (dentro de `resources/views/`, en Laravel).

### Vite entry points — dos bundles separados

```js
// vite.config.js (laravel-app) — dos entradas nuevas
input: {
  // ... entradas existentes ...
  'reportes-cirugias': 'resources/js/v2/reportes-v2/cirugias/app.tsx',
  'reportes-imagenes': 'resources/js/v2/reportes-v2/imagenes/app.tsx',
}
```

Cada entrada genera su propio bundle: `reportes-cirugias-[hash].js` y `reportes-imagenes-[hash].js`. Los componentes compartidos de `shared/` son importados directamente por cada entry (tree-shaking los mantiene eficientes).

### Blade fullscreen — Cirugías

```html
{{-- resources/views/cirugias/v2-dashboard-report.blade.php --}}
<!doctype html>
<html lang="es" data-unit="cirugias">
<head>
  <meta charset="utf-8">
  <title>Informe Ejecutivo · Cirugías · MedForge</title>
  @vite(['resources/js/v2/reportes-v2/cirugias/app.tsx'])
</head>
<body>
  <div id="root"></div>
  <script>
    window.MF_CIR_REPORT  = @json($reportPayload);
    window.MF_EXPORT_URLS = @json($exportUrls);
    window.MF_NAV_URLS    = @json($navUrls);
  </script>
</body>
</html>
```

### Blade fullscreen — Imágenes

```html
{{-- resources/views/examenes/v2-imagenes-dashboard-report.blade.php --}}
<!doctype html>
<html lang="es" data-unit="imagenes">
<head>
  <meta charset="utf-8">
  <title>Informe Ejecutivo · Imágenes · MedForge</title>
  @vite(['resources/js/v2/reportes-v2/imagenes/app.tsx'])
</head>
<body>
  <div id="root"></div>
  <script>
    window.MF_IMG_REPORT  = @json($reportPayload);
    window.MF_EXPORT_URLS = @json($exportUrls);
    window.MF_NAV_URLS    = @json($navUrls);
  </script>
</body>
</html>
```

---

## Toolbar por módulo — adaptaciones

El diseño original tiene un selector de unidad que actúa como switch de datos. **En la implementación, ese control no existe.** En su lugar, cada toolbar muestra:

| Control | Cirugías | Imágenes |
|---|---|---|
| Logo/brand MedForge | ✅ | ✅ |
| Título del informe | "Informe Ejecutivo · Cirugías" | "Informe Ejecutivo · Imágenes" |
| Período (Mes/Trim/Sem/Año) | ✅ — recarga con `?period=X` | ✅ — recarga con `?period=X` |
| Sede | ✅ — recarga con `?sede=X` | ✅ — recarga con `?sede=X` |
| Tipo de examen | ❌ no aplica | ✅ — filtro adicional |
| Exportar Excel | `<a href="{{ $exportUrls['excel'] }}">` | `<a href="{{ $exportUrls['excel'] }}">` |
| Exportar PDF | `<a href="{{ $exportUrls['pdf'] }}">` | `<a href="{{ $exportUrls['pdf'] }}">` |
| Enlace al otro informe | "Ver informe de Imágenes →" | "Ver informe de Cirugías →" |
| ← Volver | `/dashboard` | `/dashboard` |

**Comportamiento de filtros:** al cambiar período o sede, el toolbar hace `window.location.href = currentRoute + '?period=X&sede=Y'`. El backend recalcula y devuelve datos nuevos. No hay estado React que persista datos de ambas unidades.

---

## Backend — Optimizaciones (fase 0, antes del nuevo payload)

### 1. Cachear schema introspection (ambos servicios)

**Archivos:** `CirugiasDashboardService.php`, `ImagenesUiService.php`

```php
private static array $tableExistsCache = [];
private static array $columnExistsCache = [];

private function tableExists(string $table): bool {
    if (!array_key_exists($table, self::$tableExistsCache)) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        self::$tableExistsCache[$table] = (int)$stmt->fetchColumn() > 0;
    }
    return self::$tableExistsCache[$table];
}
// Igual para columnExists()
```

Impacto esperado: elimina 5-10 round-trips a `information_schema` por request.

### 2. Eliminar `similar_text()` en loop (Imágenes)

**Archivo:** `ImagenesUiService.php:3089-3123` (`findImagenTarifaCodeByDescription()`)

Reemplazar el loop PHP con `similar_text()` por una búsqueda `LIKE` en DB:

```php
private function findImagenTarifaCodeByDescription(string $desc): ?array {
    // Primero: búsqueda exacta normalizada
    $norm = $this->normalizarDescripcion($desc);
    $result = Tarifario2014::query()
        ->where('descripcion', 'LIKE', '%' . $norm . '%')
        ->select(['id', 'codigo', 'descripcion'])
        ->first();
    return $result ? $result->toArray() : null;
}
```

Si no hay match, retornar null (no degradarse a full scan).

### 3. Limitar carga del tarifario (Imágenes)

**Archivo:** `ImagenesUiService.php:3230` (`tarifaDescriptionIndex()`)

```php
// ANTES: ->get() sin límite
// DESPUÉS:
$rows = Tarifario2014::query()
    ->select(['id', 'codigo', 'descripcion', 'seccion'])
    ->orderBy('codigo')
    ->limit(2000)   // hard limit
    ->get();
```

Solo se llama si `findImagenTarifaCode()` (exacta) ya falló.

### 4. Reutilizar `$rows` para top exámenes (Imágenes)

**Archivo:** `ImagenesUiService.php` — `buildImagenesDashboardSummary()`

`fetchTopExamenesSolicitados()` actualmente re-ejecuta `buildImagenesSolicitudFlowSubquery()`. Los exámenes realizados (top por código) pueden derivarse de `$rows` ya cargados:

```php
// En lugar de fetchTopExamenesSolicitados() separada:
$topExamenes = collect($rows)
    ->groupBy('procedimiento_proyectado')
    ->map(fn($g, $k) => ['label' => $k, 'total' => $g->count()])
    ->sortByDesc('total')
    ->take(10)
    ->values()
    ->toArray();
```

`fetchTopDoctoresSolicitantes()` sí requiere `consulta_examenes` (no está en `$rows`), se mantiene pero se deja para ejecutarse una sola vez.

### 5. Deduplicar queries de opciones (Cirugías)

`getAfiliacionOptions()`, `getSeguroOptions()`, `getSedeOptions()`, `getAfiliacionCategoriaOptions()` — 4 queries siempre presentes independientemente de filtros. Se mantienen pero se agrupan en un solo método `getFilterOptions()` que las ejecuta con `async` semántico (una tras otra, pequeñas).

---

## Backend — Nuevos métodos de payload

### CirugiasUiController

Nuevo método `dashboardReport(Request $request): View` que llama a `buildReportPayload()` en `CirugiasDashboardService`.

**Payload de Cirugías:**

```php
[
    'unit'      => 'cirugias',
    'unitLabel' => 'Cirugías',
    'unitIcon'  => 'mdi-hospital-box-outline',
    'period'    => ['key' => 'trim', 'label' => 'Trimestre', 'fromLabel' => '01 mar 2026', 'toLabel' => '31 may 2026'],
    'sede'      => ['id' => 'todas', 'label' => 'Todas las sedes'],
    'generatedAt' => now()->format('d M Y · H:i'),
    'exec' => [
        'flow'    => [...],   // 5 etapas con valores y leaks
        'links'   => [...],   // % conversión entre etapas
        'kpis'    => [...],   // 5 KPIs ejecutivos (facturado, pendiente, pérdida, cartera, cumplimiento)
        'actions' => [...],   // acciones prioritarias generadas por lógica
        'summary' => ['oportunidad' => ..., 'arrastre' => ..., 'sla' => ...],
        'ledger'  => [...],
    ],
    'synth'    => [...],   // 4 células portada (sin delta — omitido en v1)
    'metrics'  => [
        'solicitudes', 'programadas', 'realizadas', 'informadas', 'facturadas',
        'ticketProm', 'facturadoReal', 'pendientePagoN', 'cumplimiento',
        'duracionProm', 'sinSolicitudPrevia', 'reingreso',
        'tatProm', 'tatMed', 'tatP90', 'tatMuestra',
    ],
    'produccionMensual'  => [...],   // getCirugiasPorMes → {label, realizadas, facturadas}
    'topProcedimientos'  => [...],   // getTopProcedimientos → {label, total}
    'topProcIngreso'     => [...],   // derivado: total × ticket_prom (aproximación)
    'topCirujanos'       => [...],   // getTopCirujanos → {name, realizadas}
    'topSolicitantes'    => [...],   // getTopDoctoresSolicitudesRealizadas → {name, total}
    'porConvenio'        => [...],   // getCirugiasPorConvenio → {label, total, cat}
    'mixCategoria'       => [...],   // agregado de porConvenio por cat
    'trazabilidad'       => [...],   // getCirugiasFacturacionTrazabilidad → estados
]
```

**Fuentes de datos para `exec.flow` (Cirugías):**

| Etapa | Fuente actual |
|---|---|
| solicitudes | `getProgramacionKpis().solicitudes_total` (si `solicitud_crm_meta` existe) o estimado |
| programadas | `getProgramacionKpis().programadas` |
| realizadas | `getTotalCirugias()` |
| protocolo (informadas) | `getEstadoProtocolos().revisado` |
| facturadas | `getCirugiasFacturacionTrazabilidad().facturadas` |

Si `getProgramacionKpis()` no disponible (tabla faltante), usar `realizadas` como proxy de `solicitudes` y marcar las etapas anteriores como nulas (no mostrar leaks).

### ImagenesUiController

Nuevo método `dashboardReport(Request $request): View` que extrae del payload existente de `imagenesDashboard()` y lo remapea al schema del diseño.

La mayor parte de los datos ya está en `$payload['dashboard']['charts']` y `$payload['dashboard']['meta']`. El nuevo método reorganiza sin recalcular.

**Payload de Imágenes:**

```php
[
    'unit'    => 'imagenes',
    'exec'    => [
        'flow'  => [solicitudes → agendadas → realizadas → informadas → facturadas],
        'kpis'  => [...],
        ...
    ],
    'metrics' => [
        'solicitudes', 'agendadas', 'realizadas', 'informadas', 'facturadas',
        'ticketProm', 'facturadoReal', 'cumplimiento', 'sla48',
        'tatProm', 'tatP90', 'pendientesSinTarifa',
    ],
    'serieDiaria'           => [...],   // charts.serie_diaria existente
    'agendaVsCierre'        => [...],   // charts.citas_vs_realizados existente
    'topExamenesRealizados' => [...],   // charts.mix_codigos existente
    'topExamenesSolicitados'=> [...],   // charts.top_examenes_solicitados existente
    'topMedicos'            => [...],   // charts.top_doctores_solicitantes existente
    'traficoPorDia'         => [...],   // charts.trafico_dia_semana existente
    'porConvenio'           => [...],   // charts.analisis_seguro existente
    'reconciliacion'        => [...],   // charts.backlog_facturacion_categoria existente
    'backlogCategoria'      => [...],   // igual
    'trazabilidad'          => [...],   // charts.trazabilidad existente
    'rendimientoEconomico'  => [...],   // charts.rendimiento_economico existente
]
```

---

## Interfaces TypeScript base compartidas

`shared/types.ts` define las interfaces que ambos módulos usan para portada, mapa ejecutivo y filtros. Cada módulo extiende con sus propios campos:

```ts
// shared/types.ts
export interface ReportPeriod { key: string; label: string; fromLabel: string; toLabel: string; }
export interface ReportSede   { id: string; label: string; }
export interface ExecFlowStage { key: string; label: string; value: number; context: string; cls: string; leak?: { label: string; count: number; amount: number }; }
export interface ExecKpi { label: string; value: string; hint: string; source: string; cls: string; }
export interface ExecAction { severity: string; title: string; metric: string; owner: string; action: string; }
export interface ExecMap { flow: ExecFlowStage[]; links: {pct:number}[]; kpis: ExecKpi[]; actions: ExecAction[]; summary: Record<string,string>; ledger: {label:string;value:string;tone?:string}[]; }
export interface SynthCell { label: string; value: string|number; unit?: string; delta?: number; deltaSuffix?: string; }

// Cirugías extiende con sus campos en cirugias/types.ts
// Imágenes extiende con sus campos en imagenes/types.ts
```

---

## Período → fechas (backend)

```php
// En ambos controllers — resolveReportPeriod(Request $request)
$preset = $request->input('period', 'trim');
$periodo = match ($preset) {
    'mes'  => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
    'trim' => [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()],
    'sem'  => [Carbon::now()->subMonths(6)->startOfMonth(), Carbon::now()->endOfMonth()],
    'ano'  => [Carbon::now()->subMonths(12)->startOfMonth(), Carbon::now()->endOfMonth()],
    default => [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()],
};
```

---

## Rutas nuevas (temporales para validación)

```php
// routes/v2/cirugias.php
Route::get('/cirugias/dashboard/report', [CirugiasUiController::class, 'dashboardReport']);

// routes/web.php (grupo imagenes)
Route::get('/v2/imagenes/dashboard/report', [ImagenesUiController::class, 'dashboardReport']);
```

Tras validación en staging:
1. Las rutas `/report` pasan a ser las principales
2. Las vistas vista anterior se renombran a `v2-dashboard-anterior.blade.php`

---

## Datos faltantes / bloques ocultos en v1

| Elemento del diseño | Estado |
|---|---|
| Deltas comparativos en portada (`synth`) | Omitidos — requieren query de período anterior |
| `topProcIngreso` (ingreso por procedimiento) | Aproximado con `total × ticketPromedioGlobal` si no hay ticket por tipo |
| `solicitudes` y `programadas` en Cirugías | Condicionales a existencia de `solicitud_crm_meta`; si falta, flow empieza desde `realizadas` |
| `sla48` en Imágenes | Ya calculado en `buildImagenesDashboardSummary` |

---

## Secuencia de implementación

1. **Fase 0 — Optimización backend** (sin cambios de comportamiento)
   - Cachear `tableExists` / `columnExists` en ambos servicios
   - Eliminar `similar_text()` en loop (Imágenes)
   - Limitar `tarifaDescriptionIndex` a 2000 registros
   - Reutilizar `$rows` para top exámenes realizados (Imágenes)

2. **Fase 1 — Payload nuevo**
   - `CirugiasDashboardService::buildReportPayload()` + `CirugiasUiController::dashboardReport()`
   - `ImagenesUiController::dashboardReport()` remapeando payload existente

3. **Fase 2 — Frontend Vite**
   - CSS → `public/css/v2/reportes-v2.css`
   - TSX → `resources/js/v2/reportes-v2/` (app, charts, lib, sections)
   - Vite entry en `vite.config.js`
   - Blades fullscreen para rutas `/report`

4. **Fase 3 — Validación y switch**
   - Probar en staging: datos reales, Excel, PDF, filtros
   - Conectar rutas principales al nuevo diseño
   - Mover vistas vista anterior a `*-anterior.blade.php`

---

## Archivos modificados / creados

### Backend
| Archivo | Acción |
|---|---|
| `app/Modules/Cirugias/Services/CirugiasDashboardService.php` | Optimizar schema cache + agregar `buildReportPayload()` |
| `app/Modules/Cirugias/Http/Controllers/CirugiasUiController.php` | Agregar `dashboardReport()` |
| `app/Modules/Examenes/Services/ImagenesUiService.php` | Optimizar schema cache + eliminar `similar_text()` + limitar tarifario |
| `app/Modules/Examenes/Http/Controllers/ImagenesUiController.php` | Agregar `dashboardReport()` |
| `routes/v2/cirugias.php` | Ruta `/cirugias/dashboard/report` temporal |
| `routes/web.php` | Ruta `/v2/imagenes/dashboard/report` temporal |

### Vistas
| Archivo | Acción |
|---|---|
| `resources/views/cirugias/v2-dashboard-report.blade.php` | Crear (fullscreen, bundle Cirugías) |
| `resources/views/examenes/v2-imagenes-dashboard-report.blade.php` | Crear (fullscreen, bundle Imágenes) |
| `resources/views/cirugias/v2-dashboard.blade.php` | NO modificar hasta validación |
| `resources/views/examenes/v2-imagenes-dashboard.blade.php` | NO modificar hasta validación |

### Frontend — CSS compartido
| Archivo | Acción |
|---|---|
| `public/css/v2/reportes-v2.css` | Crear (report.styles.css adaptado) |

### Frontend — Componentes compartidos
| Archivo | Acción |
|---|---|
| `resources/js/v2/reportes-v2/shared/charts.tsx` | Crear (TrendArea, AreaSeries, ColumnChart, DonutChart) |
| `resources/js/v2/reportes-v2/shared/lib.tsx` | Crear (Cover, Section, ExecutiveMap, Kpi, BarsList, etc.) |
| `resources/js/v2/reportes-v2/shared/types.ts` | Crear (interfaces base) |

### Frontend — Bundle Cirugías (independiente)
| Archivo | Acción |
|---|---|
| `resources/js/v2/reportes-v2/cirugias/app.tsx` | Crear (entry point) |
| `resources/js/v2/reportes-v2/cirugias/toolbar.tsx` | Crear |
| `resources/js/v2/reportes-v2/cirugias/sections.tsx` | Crear (secciones 02-04) |
| `resources/js/v2/reportes-v2/cirugias/types.ts` | Crear (extiende tipos base) |

### Frontend — Bundle Imágenes (independiente)
| Archivo | Acción |
|---|---|
| `resources/js/v2/reportes-v2/imagenes/app.tsx` | Crear (entry point) |
| `resources/js/v2/reportes-v2/imagenes/toolbar.tsx` | Crear |
| `resources/js/v2/reportes-v2/imagenes/sections.tsx` | Crear (secciones 02-04) |
| `resources/js/v2/reportes-v2/imagenes/types.ts` | Crear (extiende tipos base) |

### Build
| Archivo | Acción |
|---|---|
| `laravel-app/vite.config.js` | Agregar 2 entries: `reportes-cirugias` y `reportes-imagenes` |
