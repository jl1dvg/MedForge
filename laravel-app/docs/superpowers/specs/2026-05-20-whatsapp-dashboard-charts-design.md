# WhatsApp Dashboard — Rediseño con Gráficos

**Fecha:** 2026-05-20  
**Estado:** Aprobado  
**Rama objetivo:** rama nueva desde `main` (el overhaul anterior ya fue mergeado)

---

## Contexto y objetivo

El dashboard de WhatsApp actual tiene 13 tablas HTML y un solo mini-gráfico de barras CSS. El personal clínico no técnico (supervisores, coordinadores) tiene dificultad leyendo tablas de números para tomar decisiones operativas rápidas. Gerencia requiere una lectura ejecutiva resumida sin necesidad de interpretar filas de datos.

**Objetivo:** Transformar el dashboard reemplazando y complementando tablas con gráficos ApexCharts, manteniendo el layout general (filtros + grupos de resultados) y preservando todos los datos existentes en tablas colapsables.

---

## Decisiones de diseño

| Decisión | Elección | Razón |
|----------|----------|-------|
| Librería de gráficos | **ApexCharts** (CDN) | Balance entre visual atractivo, animaciones, documentación excelente y mantenimiento activo. ~400KB. |
| Patrón principal | **A+C combinados** | Chart encima con tabla colapsada (A) para secciones analíticas; chart puro sin tabla (C) para serie diaria y origen donde la tabla no aporta. |
| Layout general | Sin cambios | Filtros arriba, grupos de resultados abajo. Mismos grupos existentes. |
| Tablas | Conservadas | Todas quedan como acordeón colapsado. Las de operación humana se mejoran con barras de progreso inline. |
| Insights | **Banner condicional + resumen colapsable** | Banner debajo de filtros solo cuando hay alertas activas. Resumen narrativo completo al final para gerencia. |

---

## Audiencia y casos de uso

| Usuario | Frecuencia | Qué necesita |
|---------|-----------|--------------|
| Supervisor / coordinador clínico | Diario | Cola actual, SLA, alertas de fricción, carga de agentes |
| Gerencia | Quincenal | Tendencias, origen de conversiones, efectividad de ads, resumen ejecutivo |

---

## Arquitectura del cambio

**Solo frontend.** No se modifican:
- `KpiDashboardService.php` (datos ya correctos tras overhaul anterior)
- Rutas, controladores, migraciones
- Ninguna estructura de datos

**Se modifica:**
- `resources/views/whatsapp/v2-dashboard.blade.php` — integración de ApexCharts + nuevo layout de secciones

**ApexCharts se carga via CDN** en el `@push('scripts')` existente del blade. No requiere npm ni build step.

---

## Mapa de gráficos por sección

### Patrón C — Chart puro (sin tabla)

| Sección | Tipo de chart | Datos | Razón |
|---------|--------------|-------|-------|
| Serie diaria del periodo | Área multi-serie | `$trends` — nuevas / con handoff / con cita por día | Tendencia temporal; una tabla de 30 filas no aporta lectura rápida |
| Origen de demanda | Donut | `$analyticsSources` — Facebook / Instagram / Orgánico / Campaña | Distribución porcentual; el donut comunica proporciones de un vistazo |

**Serie diaria:** 3 series sobrelapadas (área + línea). Eje X = fechas, Eje Y = cantidad. Tooltips con los 3 valores al hover. Resumen de totales debajo del chart (3 chips: Nuevas / Con handoff / Con cita).

**Origen de demanda:** Donut con leyenda lateral. Incluye plataforma del ad (columna `platform` agregada en overhaul anterior: `facebook` / `instagram`). Etiquetas con porcentaje.

---

### Patrón A — Chart + tabla colapsada

| Sección | Tipo de chart | Datos | Detalle en tabla |
|---------|--------------|-------|-----------------|
| Embudo conversacional | Funnel | `$analyticsFunnel` | Números exactos por etapa + tasa de conversión |
| Intención inicial | Barras horizontales | `$analyticsIntents` | Ranking completo con handoffs y citas |
| Puntos de fricción | Barras horizontales (colores de alerta) | `$analyticsFrictions` | Tabla con conteo y % por tipo de fricción |
| Segmento del paciente | Donut | `$analyticsSegments` | Tabla con nuevo / recurrente / reactivado + handoffs |
| Tipo de conversación | Barras horizontales | `$analyticsConversationTypes` | Tabla con breakdown por tipo |
| Top Ads por citas | Barras horizontales | `$analyticsAds` | Tabla completa con plataforma (📘/📷), headline, handoffs, citas |
| Lead scoring | Barras horizontales | `$analyticsLeadScores` | Tabla con scores y conversiones |

**Colores de barras de fricción:** rojo (`#ef4444`) para ≥30%, amarillo (`#f59e0b`) para 15-29%, gris para <15%. Comunica urgencia visualmente.

**Top Ads:** barras ordenadas por citas. Cada barra etiquetada con ícono de plataforma (📘 Facebook, 📷 Instagram). Tabla colapsada muestra columna `platform_label` ya existente.

**Tabla colapsada:** implementada con el patrón `wa-section-toggle` + `d-none` ya existente en el blade. Click en "Ver detalle ▼" expande la tabla original sin modificarla.

---

### Patrón T+ — Tablas mejoradas con barras de progreso inline

| Sección | Mejora | Colores |
|---------|--------|---------|
| Atención humana por agente | Columna de tiempo de respuesta con barra de progreso + badge OK/Alto | Verde ≤ meta, amarillo ≤ 2× meta, rojo > 2× meta |
| Carga por agente | Columna de carga relativa con barra de progreso | Verde ≤70%, amarillo ≤90%, rojo >90% |
| Handoffs / Derivaciones por equipo | Columna de % respondidos con barra | Verde ≥85%, amarillo ≥60%, rojo <60% |
| Tiempo 1ª respuesta por cola | Barras de progreso por cola con color según SLA | Misma lógica que agente |

La meta de SLA se obtiene del filtro `$filters['sla_target_minutes']` (default: 15). Las barras se calculan como `(tiempo / (meta * 2)) * 100` para que el rojo empiece en 2× la meta.

---

## Banner de alertas y resumen ejecutivo

### Banner condicional
Posición: justo debajo de los filtros, antes de la Zona Ahora.  
Condición de aparición (OR):
- `$summary['unanswered_no_human'] >= 5` — al menos 5 conversaciones sin respuesta humana (umbral práctico para evitar falsos positivos en periodos cortos)
- SLA incumplido: `$summary['sla_assignments_rate'] < 70` (umbral fijo; 70% = zona de alerta)
- Cola activa alta: `$summary['live_queue_queued'] > 10`

Contenido: ícono ⚠️ + texto dinámico con número de alertas + enlace "Ver resumen ejecutivo ↓" que hace scroll al panel al final.

Si no hay alertas: el banner no se renderiza (no ocupa espacio).

### Resumen ejecutivo (colapsable al final)
Sección al final del dashboard, colapsada por defecto, con `id="exec-summary"`.  
Contenido:
- Párrafo narrativo generado con variables PHP interpoladas: totales, fuente dominante, intención dominante, fricción detectada.
- 4 bullets de acción/hallazgo con íconos semafóricos (🔴/🟡/🟢/🔵) generados condicionalmente:
  - 🔴 si hay fricción alta (sin respuesta humana > 30%)
  - 🟢 el ad con mejor tasa de conversión
  - 🟡 SLA promedio vs meta
  - 🔵 segmento dominante (nuevo/recurrente/reactivado)

---

## Integración técnica de ApexCharts

```html
{{-- En @push('scripts') al final del blade --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js"></script>
```

Cada chart se inicializa en un `<script>` IIFE al final del blade (patrón ya existente en el proyecto):

```js
(function() {
    var options = { /* config ApexCharts */ };
    var chart = new ApexCharts(document.querySelector("#chart-serie-diaria"), options);
    chart.render();
})();
```

**Datos del servidor al JS:** via `@json()` de Laravel:

```js
var seriesData = @json($trends);
var sourcesData = @json($analyticsSources);
// etc.
```

**Guard de datos vacíos:** cada chart verifica `if (!container) return;` antes de renderizar. Si no hay datos, muestra un mensaje "Sin datos para el periodo seleccionado" en el contenedor.

**Tema visual:** consistente con el dashboard existente. Fondo blanco, fuente `inherit`, colores principales `#3b82f6` (azul), `#10b981` (verde), `#f59e0b` (amarillo), `#ef4444` (rojo), `#6366f1` (índigo), `#e879f9` (fucsia para Instagram). Sin toolbar de ApexCharts visible (`toolbar: { show: false }`).

---

## Estructura del blade (orden de secciones)

```
1. @push('styles')        — CSS existente + nuevos estilos para barras T+
2. @section('content')
   ├── Header bar          — sin cambios
   ├── Banner alertas      — NUEVO (condicional)
   ├── Filtros             — sin cambios
   ├── Zona Ahora          — sin cambios (4 tarjetas)
   ├── Grupo: Tendencias
   │   ├── Serie diaria    — NUEVO chart puro (elimina el bloque wa-kpi-series-bar existente y lo reemplaza por div#chart-serie-diaria)
   │   └── Origen demanda  — NUEVO chart puro (reemplaza tabla)
   ├── Grupo: Análisis de conversaciones
   │   ├── Embudo          — NUEVO chart + tabla colapsada
   │   ├── Intención       — NUEVO chart + tabla colapsada
   │   ├── Fricciones      — NUEVO chart + tabla colapsada
   │   ├── Segmento        — NUEVO chart + tabla colapsada
   │   ├── Tipo conv.      — NUEVO chart + tabla colapsada
   │   ├── Top Ads         — NUEVO chart + tabla colapsada
   │   └── Lead scoring    — NUEVO chart + tabla colapsada
   ├── Grupo: Operación humana
   │   ├── Agentes         — MEJORADO barras inline
   │   ├── Carga agentes   — MEJORADO barras inline
   │   ├── Derivaciones    — MEJORADO barras inline
   │   └── Tiempo por cola — MEJORADO barras + chart
   ├── Resumen ejecutivo   — NUEVO (colapsable, para gerencia)
   └── @include('dashboard-guide') — sin cambios
3. @push('scripts')       — ApexCharts CDN + IIFEs de charts
```

---

## Tests

Se extiende `WhatsappKpiDashboardTest.php` existente:

- `it_renders_chart_containers_in_dashboard_ui` — verifica que los IDs de contenedor de charts existen en el HTML (`#chart-serie-diaria`, `#chart-origen`, etc.)
- `it_renders_alert_banner_when_queue_is_high` — mock de datos con cola > 10, verifica que el banner aparece
- `it_does_not_render_alert_banner_when_all_ok` — mock limpio, verifica que el banner no aparece
- `it_renders_executive_summary_section` — verifica que `#exec-summary` existe en el HTML

---

## Archivos modificados / creados

| Archivo | Acción |
|---------|--------|
| `resources/views/whatsapp/v2-dashboard.blade.php` | Modificar — integrar charts, barras inline, banner, resumen ejecutivo |
| `tests/Feature/WhatsappKpiDashboardTest.php` | Modificar — agregar 4 tests nuevos |

---

## Criterio de éxito

- [ ] Los 8 tests existentes siguen pasando
- [ ] Los 4 tests nuevos pasan
- [ ] El dashboard carga sin errores JS en consola
- [ ] Los charts renderizan con datos reales (validado manualmente en staging)
- [ ] Si no hay datos para un chart, muestra mensaje "Sin datos" en lugar de chart vacío
- [ ] El banner de alertas aparece y desaparece según condición
- [ ] Todas las tablas originales siguen accesibles vía acordeón
- [ ] El resumen ejecutivo tiene contenido dinámico (no texto hardcoded)
