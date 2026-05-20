# WhatsApp Dashboard Charts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar las 13 tablas del dashboard de WhatsApp con gráficos ApexCharts interactivos, barras de progreso en tablas operativas, un banner de alertas condicional y un resumen ejecutivo colapsable para gerencia.

**Architecture:** Solo frontend — se modifica únicamente `v2-dashboard.blade.php` y los tests de feature. ApexCharts se carga vía CDN. Los gráficos se inicializan con IIFEs al final del blade usando datos PHP serializados con `@json()`. Las tablas existentes se conservan en acordeones colapsables.

**Tech Stack:** Laravel 10+ · Blade · ApexCharts 3.x CDN · Vanilla JS (IIFEs) · CSS inline (patrón existente del proyecto) · PHPUnit

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|-----------------|
| `resources/views/whatsapp/v2-dashboard.blade.php` | Modificar | Todos los cambios de UI: CDN, CSS, charts, banners, T+ tables |
| `tests/Feature/WhatsappKpiDashboardTest.php` | Modificar | 4 tests nuevos para banner, summary y contenedores de charts |

---

## Variables de datos disponibles en el blade

Para referencia al escribir los charts:

```
$trends['labels']             → array de strings de fecha  ['2026-05-01', ...]
$trends['conversations']      → array de ints (total por día)
$trends['handoff_transfers']  → array de ints (handoffs por día)
$trends['sigcenter_bookings'] → array de ints (citas por día)

$analyticsSources[]           → source_label, total, share, identified, bookings, booking_rate
$analyticsIntents[]           → intent_label, total, share, bookings, booking_rate, handoffs
$analyticsConversationTypes[] → type_label, total, share, bookings, booking_rate, handoffs
$analyticsSegments[]          → segment_label, total, share, identified, bookings, booking_rate
$analyticsLeadScores[]        → bucket_label, total, share, avg_score, bookings, booking_rate
$analyticsFrictions[]         → friction_label, total, share
$analyticsFunnel[]            → label, value, rate_from_start, rate_to_next
$analyticsAds[]               → headline, source_id, platform, platform_label, media_type,
                                 conversations, identified, bookings, booking_rate, handoffs

$breakdowns['human_attention_by_agent'][]  → agent_name, attended_conversations, avg_first_response_minutes
$breakdowns['human_response_by_queue'][]   → label, total_handoffs, attended_handoffs, response_rate,
                                              pending_handoffs, median_first_response_minutes, avg_first_response_minutes
$breakdowns['handoffs_by_role'][]          → role_name, total, queued, assigned, resolved
$breakdowns['handoffs_by_agent'][]         → agent_name, assigned_count, active_count, resolved_count

$summary['unanswered_no_human']    → int
$summary['sla_assignments_rate']   → int (%)
$summary['live_queue_queued']      → int
$filters['sla_target_minutes']     → int (default 15)
```

---

## Tarea 1: Rama nueva + ApexCharts CDN + CSS para barras de progreso

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Paso 1: Crear rama nueva desde main**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
git checkout main && git pull
git checkout -b feat/whatsapp-dashboard-charts
```

- [ ] **Paso 2: Agregar CDN de ApexCharts al final de `@push('scripts')`**

Localiza el `@endsection` al final del blade (línea ~1317). El blade no tiene `@push('scripts')` aún — agrégalo antes del `@endsection`:

```blade
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js"></script>
@endpush
```

- [ ] **Paso 3: Agregar CSS para barras de progreso T+ en `@push('styles')`**

Agrega esto al final del bloque `@push('styles')` existente (busca el último `}` antes de `</style>`):

```css
    /* ── T+ Progress bars ─────────────────────────────── */
    .wa-prog-wrap { display: flex; align-items: center; gap: 8px; }
    .wa-prog-bg   { flex: 1; background: #f1f5f9; border-radius: 4px; height: 6px; overflow: hidden; }
    .wa-prog-fill { height: 6px; border-radius: 4px; transition: width .3s ease; }
    .wa-prog-fill--green  { background: #10b981; }
    .wa-prog-fill--yellow { background: #f59e0b; }
    .wa-prog-fill--red    { background: #ef4444; }
    .wa-prog-val  { font-size: 10px; font-weight: 700; white-space: nowrap; min-width: 36px; text-align: right; }
    .wa-prog-val--green  { color: #059669; }
    .wa-prog-val--yellow { color: #d97706; }
    .wa-prog-val--red    { color: #dc2626; }
    /* ── Chart section group labels ───────────────────── */
    .wa-group-label {
        font-size: 11px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: .08em;
        padding: 6px 0 4px; border-bottom: 2px solid #e2e8f0;
        margin-bottom: 10px; margin-top: 18px;
    }
    /* ── Chart wrappers ───────────────────────────────── */
    .wa-chart-wrap { min-height: 200px; }
    .wa-chart-empty {
        display: flex; align-items: center; justify-content: center;
        min-height: 160px; color: #94a3b8; font-size: 13px;
        background: #f8fafc; border-radius: 10px;
    }
    /* ── Chart summary chips ──────────────────────────── */
    .wa-chart-chips { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-top: 10px; }
    .wa-chart-chip  { text-align: center; }
    .wa-chart-chip__val { font-size: 18px; font-weight: 800; line-height: 1; }
    .wa-chart-chip__lbl { font-size: 10px; color: #64748b; margin-top: 2px; }
```

- [ ] **Paso 4: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): scaffold ApexCharts CDN and progress bar CSS"
```

---

## Tarea 2: Tests TDD — escribir los 4 tests nuevos (deben fallar)

**Archivos:**
- Modificar: `tests/Feature/WhatsappKpiDashboardTest.php`

- [ ] **Paso 1: Agregar los 4 tests al final de la clase, antes del `}`**

Añade este bloque después del último método del test (después de `agent_filter_includes_conversations_from_historical_handoffs`):

```php
public function test_it_renders_chart_containers_in_dashboard_ui(): void
{
    $response = $this
        ->withoutMiddleware([
            RequireAppPermission::class,
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])
        ->get('/v2/whatsapp/dashboard');

    $response->assertOk();

    // Serie diaria
    $response->assertSee('id="chart-serie-diaria"', false);
    // Origen de demanda
    $response->assertSee('id="chart-origen-demanda"', false);
    // Secciones analíticas (patrón A)
    $response->assertSee('id="chart-intencion"', false);
    $response->assertSee('id="chart-tipo-conv"', false);
    $response->assertSee('id="chart-segmento"', false);
    $response->assertSee('id="chart-lead-scoring"', false);
    $response->assertSee('id="chart-fricciones"', false);
    $response->assertSee('id="chart-embudo"', false);
    $response->assertSee('id="chart-ads"', false);
}

public function test_it_renders_alert_banner_when_queue_is_high(): void
{
    // Forzar cola > 10 en el mock del dashboard
    // El dashboard se construye desde la ruta GET /v2/whatsapp/dashboard
    // que llama a KpiDashboardService::buildDashboard().
    // Mockeamos la respuesta parcheando el summary en el controller.
    // Forma más simple: acceder a la ruta con parámetros que generan carga real.
    // Como los tests usan la BD de test con datos del setUp, la cola estará en 0.
    // Usamos una vista directa con datos inyectados para validar la lógica del blade.

    $summary = $this->makeTestSummary(['live_queue_queued' => 15]);

    $html = view('whatsapp.v2-dashboard', [
        'dashboard' => $this->makeTestDashboard($summary),
        'filters'   => ['sla_target_minutes' => 15],
    ])->render();

    $this->assertStringContainsString('id="wa-alert-banner"', $html);
}

public function test_it_does_not_render_alert_banner_when_all_ok(): void
{
    $summary = $this->makeTestSummary([
        'live_queue_queued'    => 2,
        'sla_assignments_rate' => 95,
        'unanswered_no_human'  => 0,
    ]);

    $html = view('whatsapp.v2-dashboard', [
        'dashboard' => $this->makeTestDashboard($summary),
        'filters'   => ['sla_target_minutes' => 15],
    ])->render();

    $this->assertStringNotContainsString('id="wa-alert-banner"', $html);
}

public function test_it_renders_executive_summary_section(): void
{
    $response = $this
        ->withoutMiddleware([
            RequireAppPermission::class,
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ])
        ->get('/v2/whatsapp/dashboard');

    $response->assertOk();
    $response->assertSee('id="exec-summary"', false);
    $response->assertSee('Resumen ejecutivo');
}

// ── Helpers para los tests de vista directa ──────────────────────────────

private function makeTestSummary(array $overrides = []): array
{
    return array_merge([
        'messages_inbound'         => 1,
        'messages_outbound'        => 0,
        'unique_contacts'          => 1,
        'new_conversations'        => 1,
        'live_queue_total'         => 0,
        'live_queue_queued'        => 0,
        'live_queue_assigned'      => 0,
        'unanswered_no_human'      => 0,
        'unanswered_handoff_gt24h' => 0,
        'sla_assignments_rate'     => 100,
        'median_first_reply_seconds' => 0,
        'median_from_handoff_seconds' => 0,
        'attention_rate'           => 100,
        'window_open_count'        => 0,
        'window_template_count'    => 0,
        'handoff_transfers'        => 0,
        'sigcenter_bookings_created' => 0,
        'coverage_rate'            => 100,
        'sla_target_minutes'       => 15,
    ], $overrides);
}

private function makeTestDashboard(array $summary): array
{
    return [
        'summary'    => $summary,
        'trends'     => ['labels' => [], 'conversations' => [], 'handoff_transfers' => [], 'sigcenter_bookings' => [], 'messages_inbound' => [], 'messages_outbound' => []],
        'breakdowns' => ['handoffs_by_role' => [], 'handoffs_by_agent' => [], 'human_attention_by_agent' => [], 'human_response_by_queue' => [], 'sigcenter_bookings_by_sede' => []],
        'analytics'  => [
            'summary'            => [],
            'lifecycle'          => [],
            'sources'            => [],
            'funnel'             => [],
            'outcomes'           => [],
            'intents'            => [],
            'conversation_types' => [],
            'segments'           => [],
            'lead_scores'        => [],
            'frictions'          => [],
            'insights'           => [],
            'ads'                => [],
        ],
        'options'    => ['roles' => [], 'agents' => []],
        'filters'    => ['sla_target_minutes' => 15],
    ];
}
```

- [ ] **Paso 2: Verificar que los tests FALLAN antes de implementar**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
php artisan test tests/Feature/WhatsappKpiDashboardTest.php --filter="chart_containers|alert_banner|executive_summary" --no-coverage 2>&1
```

Resultado esperado: 4 tests FAIL (los 8 existentes siguen pasando).

- [ ] **Paso 3: Commit**

```bash
git add tests/Feature/WhatsappKpiDashboardTest.php
git commit -m "test(whatsapp): add failing tests for charts, alert banner, and exec summary"
```

---

## Tarea 3: Banner de alertas condicional + Resumen ejecutivo

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Paso 1: Agregar helper PHP para detección de alertas**

Justo después del bloque `@php ... @endphp` que abre el blade (después de `$sectionHelp = [...]`), añade:

```blade
@php
    $slaMeta = (int) ($filters['sla_target_minutes'] ?? 15);
    $alertQueue     = ($summary['live_queue_queued'] ?? 0) > 10;
    $alertSla       = ($summary['sla_assignments_rate'] ?? 100) < 70;
    $alertUnanswered = ($summary['unanswered_no_human'] ?? 0) >= 5;
    $hasAlerts      = $alertQueue || $alertSla || $alertUnanswered;
    $alertCount     = (int) $alertQueue + (int) $alertSla + (int) $alertUnanswered;

    // Datos para resumen ejecutivo
    $topSource   = collect($analyticsSources)->sortByDesc('total')->first();
    $topIntent   = collect($analyticsIntents)->sortByDesc('total')->first();
    $topSegment  = collect($analyticsSegments)->sortByDesc('total')->first();
    $topFriction = collect($analyticsFrictions)->sortByDesc('total')->first();
    $topAd       = collect($analyticsAds)->sortByDesc('bookings')->first();
    $totalConvs  = $analyticsSummary['total_conversations'] ?? 0;
    $frictionHighShare = isset($topFriction['share']) && (int) $topFriction['share'] > 30;
@endphp
```

- [ ] **Paso 2: Insertar el banner HTML justo antes del bloque de filtros**

Busca el comentario o el `<div class="row">` que contiene los filtros (alrededor de línea 386). Inserta este bloque ANTES de ese div:

```blade
{{-- ── Banner de alertas condicional ─────────────────────────────── --}}
@if($hasAlerts)
<div id="wa-alert-banner" class="mb-3" style="background:linear-gradient(135deg,#fef2f2,#fff7ed);border:1px solid #fecaca;border-radius:14px;padding:14px 20px;display:flex;align-items:center;gap:14px;">
    <span style="font-size:24px;flex-shrink:0">⚠️</span>
    <div style="flex:1">
        <div style="font-size:13px;font-weight:700;color:#dc2626;line-height:1.3">
            {{ $alertCount }} {{ $alertCount === 1 ? 'alerta activa' : 'alertas activas' }} en este periodo
        </div>
        <div style="font-size:11px;color:#64748b;margin-top:3px;line-height:1.5">
            @if($alertQueue) Cola activa alta ({{ $summary['live_queue_queued'] }} en espera). @endif
            @if($alertSla) SLA por debajo de meta ({{ $summary['sla_assignments_rate'] }}%). @endif
            @if($alertUnanswered) {{ $summary['unanswered_no_human'] }} conversaciones sin respuesta humana. @endif
        </div>
    </div>
    <a href="#exec-summary" onclick="document.getElementById('exec-summary-body').classList.remove('d-none')" style="font-size:11px;color:#3b82f6;font-weight:600;white-space:nowrap;text-decoration:none;">Ver resumen ejecutivo ↓</a>
</div>
@endif
```

- [ ] **Paso 3: Insertar el Resumen Ejecutivo al final del grid de resultados**

Justo antes de `@include('whatsapp.partials.dashboard-guide')` al final del blade:

```blade
{{-- ── Resumen ejecutivo para gerencia ────────────────────────────── --}}
<div id="exec-summary" class="col-12 mt-3">
    <div class="wa-kpi-panel">
        <div class="wa-kpi-panel__head" style="cursor:pointer"
             onclick="document.getElementById('exec-summary-body').classList.toggle('d-none')">
            <div class="wa-kpi-title-row">
                <div class="wa-kpi-sideheading__title">📋 Resumen ejecutivo del periodo</div>
                <button type="button" class="wa-section-toggle ms-auto">▼</button>
            </div>
            <div class="wa-kpi-sideheading__meta">Lectura consolidada para gerencia — origen, intención, SLA y fricción.</div>
        </div>
        <div id="exec-summary-body" class="wa-kpi-panel__body d-none">
            <p style="font-size:13px;color:#475569;line-height:1.75;margin-bottom:16px">
                El canal recibió <strong>{{ number_format($totalConvs) }} conversaciones nuevas</strong> en el periodo.
                @if($topSource) La principal fuente fue <strong>{{ $topSource['source_label'] }} ({{ $topSource['share'] }}%)</strong>. @endif
                @if($topIntent) La intención dominante fue <strong>{{ $topIntent['intent_label'] }} ({{ $topIntent['share'] }}%)</strong>. @endif
                @if($frictionHighShare) Se detectó <strong>fricción significativa</strong> en "{{ $topFriction['friction_label'] }}" ({{ $topFriction['share'] }}% de conversaciones). @endif
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                @if($frictionHighShare)
                <div style="background:#fef2f2;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:18px">🔴</span>
                    <div><strong style="font-size:12px;color:#dc2626">Acción recomendada</strong><div style="font-size:11px;color:#64748b;margin-top:3px">Revisar "{{ $topFriction['friction_label'] }}" — representa {{ $topFriction['share'] }}% de las fricciones</div></div>
                </div>
                @endif
                @if($topAd)
                <div style="background:#f0fdf4;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:18px">🟢</span>
                    <div><strong style="font-size:12px;color:#166534">Mejor anuncio</strong><div style="font-size:11px;color:#64748b;margin-top:3px">{{ $topAd['headline'] }} — {{ $topAd['bookings'] }} citas ({{ $topAd['platform_label'] ?? '' }})</div></div>
                </div>
                @endif
                <div style="background:#fffbeb;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:18px">🟡</span>
                    <div><strong style="font-size:12px;color:#d97706">SLA del periodo</strong><div style="font-size:11px;color:#64748b;margin-top:3px">{{ $summary['sla_assignments_rate'] ?? 0 }}% respondidos dentro de meta de {{ $slaMeta }} min</div></div>
                </div>
                @if($topSegment)
                <div style="background:#eff6ff;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:18px">🔵</span>
                    <div><strong style="font-size:12px;color:#2563eb">Segmento dominante</strong><div style="font-size:11px;color:#64748b;margin-top:3px">{{ $topSegment['segment_label'] }} — {{ $topSegment['share'] }}% de las conversaciones</div></div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
```

- [ ] **Paso 4: Correr los tests de banner y summary**

```bash
php artisan test tests/Feature/WhatsappKpiDashboardTest.php --no-coverage 2>&1
```

Resultado esperado: `test_it_renders_alert_banner_when_queue_is_high`, `test_it_does_not_render_alert_banner_when_all_ok`, `test_it_renders_executive_summary_section` pasan. `test_it_renders_chart_containers_in_dashboard_ui` aún falla (normal).

- [ ] **Paso 5: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add conditional alert banner and collapsible executive summary"
```

---

## Tarea 4: Chart puro — Serie diaria (reemplaza CSS bars)

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php` — sección "Series del periodo" (~línea 1096)

- [ ] **Paso 1: Reemplazar el bloque `wa-kpi-series-bar` con el contenedor del chart**

Encuentra el bloque completo de la sección "Series del periodo" (línea ~1096-1129). Reemplaza **el contenido de `wa-kpi-panel__body`** (desde `@php $series = ...` hasta el `</div>` de cierre del body):

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-serie-diaria" class="wa-chart-wrap"></div>
                    @php
                        $chipTotals = [
                            'Nuevas'        => array_sum($trends['conversations'] ?? []),
                            'Con handoff'   => array_sum($trends['handoff_transfers'] ?? []),
                            'Con cita'      => array_sum($trends['sigcenter_bookings'] ?? []),
                        ];
                    @endphp
                    <div class="wa-chart-chips">
                        @foreach($chipTotals as $chipLabel => $chipVal)
                            <div class="wa-chart-chip">
                                <div class="wa-chart-chip__val">{{ number_format($chipVal) }}</div>
                                <div class="wa-chart-chip__lbl">{{ $chipLabel }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-muted mt-10" style="font-size:.82rem;">
                        Inbound {{ $summary['messages_inbound'] ?? 0 }} · Outbound {{ $summary['messages_outbound'] ?? 0 }} · Citas {{ $summary['sigcenter_bookings_created'] ?? 0 }} · Derivaciones {{ $summary['handoff_transfers'] ?? 0 }}
                    </div>
                </div>
```

- [ ] **Paso 2: Agregar el IIFE del chart al bloque `@push('scripts')`**

Agrega este bloque dentro del `@push('scripts')` (después de la etiqueta CDN de ApexCharts):

```blade
<script>
(function () {
    var el = document.getElementById('chart-serie-diaria');
    if (!el) return;
    var labels = @json($trends['labels'] ?? []);
    var convs  = @json(array_values($trends['conversations'] ?? []));
    var handoffs = @json(array_values($trends['handoff_transfers'] ?? []));
    var bookings = @json(array_values($trends['sigcenter_bookings'] ?? []));
    if (!labels.length) {
        el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
        return;
    }
    new ApexCharts(el, {
        chart:  { type: 'area', height: 220, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Nuevas',      data: convs    },
            { name: 'Con handoff', data: handoffs },
            { name: 'Con cita',    data: bookings },
        ],
        colors:  ['#3b82f6', '#10b981', '#f59e0b'],
        fill:    { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
        stroke:  { curve: 'smooth', width: [2.5, 2, 1.5] },
        xaxis:   { categories: labels, labels: { rotate: -30, style: { fontSize: '10px' } }, tickAmount: Math.min(labels.length, 10) },
        yaxis:   { labels: { style: { fontSize: '11px' } } },
        tooltip: { shared: true, intersect: false },
        legend:  { position: 'top', fontSize: '12px' },
        grid:    { borderColor: '#f1f5f9' },
        dataLabels: { enabled: false },
    }).render();
}());
</script>
```

- [ ] **Paso 3: Verificar que los tests siguen pasando**

```bash
php artisan test tests/Feature/WhatsappKpiDashboardTest.php --no-coverage 2>&1
```

`test_it_renders_chart_containers_in_dashboard_ui` ahora debería tener `#chart-serie-diaria` ✓ (pero aún falla por otros IDs).

- [ ] **Paso 4: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): replace CSS series bar with ApexCharts area chart"
```

---

## Tarea 5: Chart puro — Origen de demanda (reemplaza tabla con donut)

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php` — sección "Origen de demanda" (~línea 674)

- [ ] **Paso 1: Reemplazar el `wa-kpi-panel__body p-0` de la sección**

Encuentra el panel "Origen de demanda" (~línea 674). Reemplaza todo el `<div class="wa-kpi-panel__body p-0">...</div>` por:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-origen-demanda" class="wa-chart-wrap"></div>
                </div>
```

- [ ] **Paso 2: Agregar IIFE al `@push('scripts')`**

```blade
<script>
(function () {
    var el = document.getElementById('chart-origen-demanda');
    if (!el) return;
    var rows = @json($analyticsSources);
    if (!rows || !rows.length) {
        el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
        return;
    }
    var labels = rows.map(function(r){ return r.source_label; });
    var series = rows.map(function(r){ return parseInt(r.total) || 0; });
    new ApexCharts(el, {
        chart:    { type: 'donut', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series:   series,
        labels:   labels,
        colors:   ['#3b82f6','#e879f9','#10b981','#f59e0b','#6366f1','#94a3b8'],
        legend:   { position: 'right', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px' } } } } },
        dataLabels: { enabled: true, formatter: function(val){ return Math.round(val) + '%'; }, style: { fontSize: '11px' } },
        tooltip:  { y: { formatter: function(val, opts){ return val + ' conv. (' + (rows[opts.seriesIndex] ? rows[opts.seriesIndex].booking_rate : 0) + '% cita)'; } } },
    }).render();
}());
</script>
```

- [ ] **Paso 3: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): replace sources table with ApexCharts donut chart"
```

---

## Tarea 6: Patrón A — Charts + tablas colapsadas (Intención · Tipo · Segmento)

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php`

Para cada sección: se inserta el chart DIV + IIFE encima de la tabla existente, y se envuelve la tabla en un toggle colapsable.

### Sección: Intención inicial (~línea 760)

- [ ] **Paso 1: Modificar el panel — agregar chart y colapsar tabla**

Reemplaza el contenido de `wa-kpi-panel__body p-0` (tabla de intención):

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-intencion" class="wa-chart-wrap" style="height:200px"></div>
                </div>
                <div class="wa-kpi-panel__body p-0" id="chart-intencion-table" style="display:none">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead><tr><th>Intención</th><th>Total</th><th>Participación</th><th>Citas</th><th>Handoffs</th></tr></thead>
                            <tbody>
                            @forelse($analyticsIntents as $row)
                                <tr>
                                    <td>{{ $row['intent_label'] }}</td><td>{{ $row['total'] }}</td>
                                    <td>{{ $row['share'] }}%</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
```

Modifica también el `wa-kpi-panel__head` para agregar el toggle — añade `style="cursor:pointer" onclick="..."` y el botón:

```blade
                <div class="wa-kpi-panel__head" style="cursor:pointer"
                     onclick="var t=document.getElementById('chart-intencion-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                    <div class="wa-kpi-title-row">
                        <div class="wa-kpi-sideheading__title">Intención inicial</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Intención inicial" onclick="event.stopPropagation()">?<span class="wa-kpi-help__tooltip">{{ $sectionHelp['initial_intent'] }}</span></button>
                        <button type="button" class="wa-section-toggle ms-auto">▼</button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Clasificación del primer mensaje útil de cada conversación nueva.</div>
                </div>
```

- [ ] **Paso 2: IIFE para chart de intención**

```blade
<script>
(function () {
    var el = document.getElementById('chart-intencion');
    if (!el) return;
    var rows = @json($analyticsIntents);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    new ApexCharts(el, {
        chart: { type: 'bar', height: 200, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '65%', borderRadius: 4 } },
        series: [{ name: 'Conversaciones', data: rows.map(function(r){ return parseInt(r.total)||0; }) }],
        xaxis: { categories: rows.map(function(r){ return r.intent_label; }), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        colors: ['#3b82f6','#6366f1','#10b981','#f59e0b','#ef4444','#e879f9','#94a3b8'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: function(val){ return val; }, style: { fontSize: '10px' } },
        tooltip: { y: { formatter: function(val, opts){ return val + ' conv. (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].share : 0) + '%)'; } } },
        grid: { borderColor: '#f1f5f9' },
    }).render();
}());
</script>
```

### Sección: Tipo de conversación (~línea 805)

- [ ] **Paso 3: Mismo patrón para Tipo de conversación**

Reemplaza el `wa-kpi-panel__body p-0` con:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-tipo-conv" class="wa-chart-wrap" style="height:200px"></div>
                </div>
                <div class="wa-kpi-panel__body p-0" id="chart-tipo-conv-table" style="display:none">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead><tr><th>Tipo</th><th>Total</th><th>Participación</th><th>Citas</th><th>Handoffs</th></tr></thead>
                            <tbody>
                            @forelse($analyticsConversationTypes as $row)
                                <tr>
                                    <td>{{ $row['type_label'] }}</td><td>{{ $row['total'] }}</td>
                                    <td>{{ $row['share'] }}%</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
```

Modifica el head del panel para agregar toggle y cursor igual que intención (reemplazando el onclick con `chart-tipo-conv-table`).

IIFE para tipo de conversación:

```blade
<script>
(function () {
    var el = document.getElementById('chart-tipo-conv');
    if (!el) return;
    var rows = @json($analyticsConversationTypes);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    new ApexCharts(el, {
        chart: { type: 'bar', height: 200, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '65%', borderRadius: 4 } },
        series: [{ name: 'Conversaciones', data: rows.map(function(r){ return parseInt(r.total)||0; }) }],
        xaxis: { categories: rows.map(function(r){ return r.type_label; }), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        colors: ['#6366f1','#3b82f6','#10b981','#f59e0b','#ef4444','#94a3b8'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: function(val){ return val; }, style: { fontSize: '10px' } },
        tooltip: { y: { formatter: function(val, opts){ return val + ' (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].share : 0) + '%)'; } } },
        grid: { borderColor: '#f1f5f9' },
    }).render();
}());
</script>
```

### Sección: Segmento del paciente (~línea 850)

- [ ] **Paso 4: Mismo patrón para Segmento del paciente**

Reemplaza el `wa-kpi-panel__body p-0` con:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-segmento" class="wa-chart-wrap" style="height:220px"></div>
                </div>
                <div class="wa-kpi-panel__body p-0" id="chart-segmento-table" style="display:none">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead><tr><th>Segmento</th><th>Total</th><th>Participación</th><th>Identificadas</th><th>Citas</th></tr></thead>
                            <tbody>
                            @forelse($analyticsSegments as $row)
                                <tr>
                                    <td>{{ $row['segment_label'] }}</td><td>{{ $row['total'] }}</td>
                                    <td>{{ $row['share'] }}%</td><td>{{ $row['identified'] }}</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
```

IIFE para segmento (donut):

```blade
<script>
(function () {
    var el = document.getElementById('chart-segmento');
    if (!el) return;
    var rows = @json($analyticsSegments);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    new ApexCharts(el, {
        chart: { type: 'donut', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
        series: rows.map(function(r){ return parseInt(r.total)||0; }),
        labels: rows.map(function(r){ return r.segment_label; }),
        colors: ['#6366f1','#f59e0b','#10b981','#3b82f6'],
        legend: { position: 'bottom', fontSize: '11px' },
        plotOptions: { pie: { donut: { size: '55%' } } },
        dataLabels: { enabled: true, formatter: function(val){ return Math.round(val) + '%'; }, style: { fontSize: '10px' } },
        tooltip: { y: { formatter: function(val){ return val + ' conversaciones'; } } },
    }).render();
}());
</script>
```

- [ ] **Paso 5: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add ApexCharts for intencion, tipo conv, and segmento sections"
```

---

## Tarea 7: Patrón A — Charts (Lead scoring · Fricciones · Embudo)

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php`

### Sección: Lead scoring (~línea 895)

- [ ] **Paso 1: Agregar chart + tabla colapsada**

Reemplaza `wa-kpi-panel__body p-0`:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-lead-scoring" class="wa-chart-wrap" style="height:180px"></div>
                </div>
                <div class="wa-kpi-panel__body p-0" id="chart-lead-scoring-table" style="display:none">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead><tr><th>Bucket</th><th>Total</th><th>Participación</th><th>Score promedio</th><th>Citas</th></tr></thead>
                            <tbody>
                            @forelse($analyticsLeadScores as $row)
                                <tr>
                                    <td>{{ $row['bucket_label'] }}</td><td>{{ $row['total'] }}</td>
                                    <td>{{ $row['share'] }}%</td><td>{{ $row['avg_score'] }}</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
```

IIFE:

```blade
<script>
(function () {
    var el = document.getElementById('chart-lead-scoring');
    if (!el) return;
    var rows = @json($analyticsLeadScores);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    new ApexCharts(el, {
        chart: { type: 'bar', height: 180, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4 } },
        series: [{ name: 'Conversaciones', data: rows.map(function(r){ return parseInt(r.total)||0; }) }],
        xaxis: { categories: rows.map(function(r){ return r.bucket_label; }), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        colors: ['#10b981','#3b82f6','#f59e0b','#ef4444'],
        legend: { show: false },
        dataLabels: { enabled: true, style: { fontSize: '10px' } },
        grid: { borderColor: '#f1f5f9' },
    }).render();
}());
</script>
```

### Sección: Fricciones (~línea 940)

- [ ] **Paso 2: Agregar chart + tabla colapsada para Fricciones**

Reemplaza `wa-kpi-panel__body p-0`:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-fricciones" class="wa-chart-wrap" style="height:180px"></div>
                </div>
                <div class="wa-kpi-panel__body p-0" id="chart-fricciones-table" style="display:none">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead><tr><th>Fricción</th><th>Total</th><th>Participación</th></tr></thead>
                            <tbody>
                            @forelse($analyticsFrictions as $row)
                                <tr><td>{{ $row['friction_label'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['share'] }}%</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-20">Sin fricciones relevantes.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
```

IIFE — usa colores de alerta según share:

```blade
<script>
(function () {
    var el = document.getElementById('chart-fricciones');
    if (!el) return;
    var rows = @json($analyticsFrictions);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    var colors = rows.map(function(r){
        var s = parseInt(r.share)||0;
        return s >= 30 ? '#ef4444' : (s >= 15 ? '#f59e0b' : '#94a3b8');
    });
    new ApexCharts(el, {
        chart: { type: 'bar', height: 180, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4 } },
        series: [{ name: '% de fricciones', data: rows.map(function(r){ return parseFloat(r.share)||0; }) }],
        xaxis: { categories: rows.map(function(r){ return r.friction_label; }), labels: { style: { fontSize: '11px' } }, max: 100 },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        colors: colors,
        legend: { show: false },
        dataLabels: { enabled: true, formatter: function(val){ return val + '%'; }, style: { fontSize: '10px' } },
        tooltip: { y: { formatter: function(val, opts){ return val + '% (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].total : 0) + ' conv.)'; } } },
        grid: { borderColor: '#f1f5f9' },
    }).render();
}());
</script>
```

### Sección: Embudo conversacional (~línea 981)

- [ ] **Paso 3: Reemplazar KPI cards del embudo con chart de funnel**

El embudo actualmente usa `wa-kpi-grid` con cards. Reemplaza el body completo:

```blade
                <div class="wa-kpi-panel__body">
                    <div id="chart-embudo" class="wa-chart-wrap" style="height:260px"></div>
                </div>
                <div class="wa-kpi-panel__body" id="chart-embudo-table" style="display:none">
                    <div class="wa-kpi-grid">
                        @forelse($analyticsFunnel as $step)
                            <div class="wa-kpi-card">
                                <div class="wa-kpi-label">{{ $step['label'] }}</div>
                                <div class="wa-kpi-value">{{ $step['value'] }}</div>
                                <div class="wa-kpi-sub">Desde inicio {{ $step['rate_from_start'] }}% · Paso {{ $step['rate_to_next'] }}%</div>
                            </div>
                        @empty
                            <div class="text-muted">Sin datos para el rango actual.</div>
                        @endforelse
                    </div>
                </div>
```

IIFE — funnel chart via barras horizontales ordenadas descendentemente:

```blade
<script>
(function () {
    var el = document.getElementById('chart-embudo');
    if (!el) return;
    var steps = @json($analyticsFunnel);
    if (!steps || !steps.length) { el.innerHTML = '<div class="wa-chart-empty">Sin datos</div>'; return; }
    new ApexCharts(el, {
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: false, barHeight: '55%', borderRadius: 4, isFunnel: true } },
        series: [{ name: 'Conversaciones', data: steps.map(function(s){ return parseInt(s.value)||0; }) }],
        xaxis: { categories: steps.map(function(s){ return s.label; }), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        colors: ['#3b82f6'],
        dataLabels: {
            enabled: true,
            formatter: function(val, opts) {
                var s = steps[opts.dataPointIndex];
                return val + (s ? ' (' + s.rate_from_start + '%)' : '');
            },
            style: { fontSize: '11px' }
        },
        legend: { show: false },
        grid: { borderColor: '#f1f5f9' },
        tooltip: { y: { formatter: function(val, opts){ var s = steps[opts.dataPointIndex]; return val + ' conv. · ' + (s ? s.rate_to_next + '% al siguiente' : ''); } } },
    }).render();
}());
</script>
```

- [ ] **Paso 4: Correr tests**

```bash
php artisan test tests/Feature/WhatsappKpiDashboardTest.php --no-coverage 2>&1
```

- [ ] **Paso 5: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add ApexCharts for lead scoring, fricciones, and embudo sections"
```

---

## Tarea 8: Patrón A — Chart Top Ads (barra horizontal antes de tabla existente)

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php` — sección "Top Ads" (~línea 1036)

- [ ] **Paso 1: Agregar chart div justo antes del `wa-kpi-panel__body p-0 d-none` existente**

El panel "Top Ads" ya tiene toggle (`d-none`) en el body de la tabla. Solo agrega un nuevo body con el chart ANTES del body de tabla existente:

```blade
                {{-- Chart de Ads: insertado entre el head y el cuerpo de la tabla --}}
                <div class="wa-kpi-panel__body">
                    <div id="chart-ads" class="wa-chart-wrap" style="height:220px"></div>
                </div>
                {{-- Tabla detalle (ya existente, mantener el d-none) --}}
                <div class="wa-kpi-panel__body p-0 d-none">
                    ... (tabla existente sin cambios) ...
```

- [ ] **Paso 2: IIFE para el chart de ads**

```blade
<script>
(function () {
    var el = document.getElementById('chart-ads');
    if (!el) return;
    var rows = @json($analyticsAds);
    if (!rows || !rows.length) { el.innerHTML = '<div class="wa-chart-empty">Sin conversaciones atribuibles a Ads en el rango actual</div>'; return; }
    var platformIcons = { facebook: '📘', instagram: '📷', whatsapp: '💬' };
    var labels = rows.map(function(r){
        var icon = platformIcons[r.platform] || '❓';
        return icon + ' ' + (r.headline ? r.headline.substring(0, 28) + (r.headline.length > 28 ? '…' : '') : 'Sin nombre');
    });
    new ApexCharts(el, {
        chart: { type: 'bar', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4 } },
        series: [{ name: 'Citas', data: rows.map(function(r){ return parseInt(r.bookings)||0; }) }],
        xaxis: { categories: labels, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '10px' }, maxWidth: 160 } },
        colors: rows.map(function(r){ return r.platform === 'instagram' ? '#e879f9' : '#3b82f6'; }),
        legend: { show: false },
        dataLabels: { enabled: true, formatter: function(val){ return val + ' citas'; }, style: { fontSize: '10px' } },
        tooltip: { y: { formatter: function(val, opts){ var r = rows[opts.dataPointIndex]; return val + ' citas · ' + (r ? r.conversations : 0) + ' conv.'; } } },
        grid: { borderColor: '#f1f5f9' },
    }).render();
}());
</script>
```

- [ ] **Paso 3: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add ApexCharts horizontal bar for top ads section"
```

---

## Tarea 9: T+ Tables — Barras de progreso inline en tablas operativas

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php` — 4 secciones de operación

### Atención humana por agente (~línea 1131)

- [ ] **Paso 1: Agregar columna de barra a la tabla de agentes**

La tabla está en `wa-kpi-panel__body p-0 d-none`. Reemplaza el `thead` y el `@forelse`:

```blade
                            <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Atendidas</th>
                                <th>1ra respuesta</th>
                                <th style="min-width:140px">Velocidad</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $slaMeta = (int)($filters['sla_target_minutes'] ?? 15); @endphp
                            @forelse(($breakdowns['human_attention_by_agent'] ?? []) as $row)
                                @php
                                    $mins = $row['avg_first_response_minutes'];
                                    $pct  = $mins !== null ? min(100, (int)round(($mins / ($slaMeta * 2)) * 100)) : 0;
                                    $color = $mins === null ? 'green' : ($mins > $slaMeta * 2 ? 'red' : ($mins > $slaMeta ? 'yellow' : 'green'));
                                    $badge = $mins === null ? '—' : ($mins > $slaMeta * 2 ? '✗ Alto' : ($mins > $slaMeta ? '~ OK' : '✓ OK'));
                                @endphp
                                <tr>
                                    <td>{{ $row['agent_name'] }}</td>
                                    <td>{{ $row['attended_conversations'] }}</td>
                                    <td class="wa-prog-val--{{ $color }}">{{ $mins !== null ? $mins . ' min' : '—' }}</td>
                                    <td>
                                        <div class="wa-prog-wrap">
                                            <div class="wa-prog-bg"><div class="wa-prog-fill wa-prog-fill--{{ $color }}" style="width:{{ $pct }}%"></div></div>
                                            <span class="wa-prog-val wa-prog-val--{{ $color }}">{{ $badge }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
```

### Primera respuesta por cola (~línea 1174)

- [ ] **Paso 2: Agregar columna de barra a la tabla de colas**

Reemplaza el `thead` y `@forelse` de la tabla "Primera respuesta por cola":

```blade
                            <thead>
                            <tr>
                                <th>Cola</th>
                                <th>Handoffs</th>
                                <th>Atendidos</th>
                                <th>Pendientes</th>
                                <th>Mediana</th>
                                <th>Promedio</th>
                                <th style="min-width:120px">SLA</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $slaMeta = (int)($filters['sla_target_minutes'] ?? 15); @endphp
                            @forelse(($breakdowns['human_response_by_queue'] ?? []) as $row)
                                @php
                                    $avg   = $row['avg_first_response_minutes'];
                                    $pct   = $avg !== null ? min(100, (int)round(($avg / ($slaMeta * 2)) * 100)) : 0;
                                    $color = $avg === null ? 'green' : ($avg > $slaMeta * 2 ? 'red' : ($avg > $slaMeta ? 'yellow' : 'green'));
                                @endphp
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['total_handoffs'] }}</td>
                                    <td>{{ $row['attended_handoffs'] }} · {{ $row['response_rate'] }}%</td>
                                    <td>{{ $row['pending_handoffs'] }}</td>
                                    <td>{{ $row['median_first_response_minutes'] !== null ? $row['median_first_response_minutes'] . ' min' : '—' }}</td>
                                    <td class="wa-prog-val--{{ $color }}">{{ $avg !== null ? $avg . ' min' : '—' }}</td>
                                    <td>
                                        <div class="wa-prog-wrap">
                                            <div class="wa-prog-bg"><div class="wa-prog-fill wa-prog-fill--{{ $color }}" style="width:{{ $pct }}%"></div></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
```

### Handoffs por equipo (~línea 1221)

- [ ] **Paso 3: Agregar barra de % resueltos a la tabla de handoffs**

Reemplaza thead y @forelse:

```blade
                            <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Total</th>
                                <th>Cola</th>
                                <th>Asignadas</th>
                                <th>Resueltas</th>
                                <th style="min-width:120px">% Resueltas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($breakdowns['handoffs_by_role'] ?? []) as $row)
                                @php
                                    $pctR  = $row['total'] > 0 ? (int)round(($row['resolved'] / $row['total']) * 100) : 0;
                                    $colorR = $pctR >= 85 ? 'green' : ($pctR >= 60 ? 'yellow' : 'red');
                                @endphp
                                <tr>
                                    <td>{{ $row['role_name'] }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['queued'] }}</td>
                                    <td>{{ $row['assigned'] }}</td>
                                    <td>{{ $row['resolved'] }}</td>
                                    <td>
                                        <div class="wa-prog-wrap">
                                            <div class="wa-prog-bg"><div class="wa-prog-fill wa-prog-fill--{{ $colorR }}" style="width:{{ $pctR }}%"></div></div>
                                            <span class="wa-prog-val wa-prog-val--{{ $colorR }}">{{ $pctR }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
```

### Carga por agente (~línea 1268)

- [ ] **Paso 4: Agregar barra de carga relativa**

Reemplaza thead y @forelse:

```blade
                            <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Asignadas</th>
                                <th>Activas</th>
                                <th>Resueltas</th>
                                <th style="min-width:120px">Carga</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $maxAssigned = max(1, ...array_map(fn($r) => (int)($r['assigned_count'] ?? 0), $breakdowns['handoffs_by_agent'] ?? [1 => ['assigned_count' => 1]]));
                            @endphp
                            @forelse(($breakdowns['handoffs_by_agent'] ?? []) as $row)
                                @php
                                    $loadPct  = (int)round(($row['assigned_count'] / $maxAssigned) * 100);
                                    $colorL   = $loadPct >= 90 ? 'red' : ($loadPct >= 70 ? 'yellow' : 'green');
                                @endphp
                                <tr>
                                    <td>{{ $row['agent_name'] }}</td>
                                    <td>{{ $row['assigned_count'] }}</td>
                                    <td>{{ $row['active_count'] }}</td>
                                    <td>{{ $row['resolved_count'] }}</td>
                                    <td>
                                        <div class="wa-prog-wrap">
                                            <div class="wa-prog-bg"><div class="wa-prog-fill wa-prog-fill--{{ $colorL }}" style="width:{{ $loadPct }}%"></div></div>
                                            <span class="wa-prog-val wa-prog-val--{{ $colorL }}">{{ $loadPct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td></tr>
                            @endforelse
```

- [ ] **Paso 5: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add T+ progress bars to agent, queue, handoff, and load tables"
```

---

## Tarea 10: Verificación final, tests completos y push

**Archivos:**
- `tests/Feature/WhatsappKpiDashboardTest.php`
- `resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Paso 1: Correr todos los tests**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
php artisan test tests/Feature/WhatsappKpiDashboardTest.php --no-coverage 2>&1
```

Resultado esperado: **12 tests pasando** (8 existentes + 4 nuevos). Si falla alguno:
- `test_it_renders_chart_containers_in_dashboard_ui` falla → algún ID falta en el blade. Verifica que todos los `id="chart-*"` estén presentes.
- `test_it_renders_alert_banner_when_queue_is_high` falla → verifica que el helper `makeTestSummary` y `makeTestDashboard` existen y que el blade evalúa la condición `$hasAlerts`.
- `test_it_does_not_render_alert_banner_when_all_ok` falla → verifica los umbrales en el blade.
- `test_it_renders_executive_summary_section` falla → verifica que `id="exec-summary"` está en el blade.

- [ ] **Paso 2: Verificar que la página carga sin errores PHP**

```bash
php artisan route:list --path=v2/whatsapp 2>&1 | head -5
php artisan view:clear
php artisan config:clear
```

- [ ] **Paso 3: Push a rama remota**

```bash
git push -u origin feat/whatsapp-dashboard-charts
```

- [ ] **Paso 4: Abrir PR**

```bash
gh pr create \
  --title "feat(whatsapp): replace 13 tables with ApexCharts + progress bars + exec summary" \
  --body "$(cat <<'EOF'
## Summary

- Replace CSS mini-bar with ApexCharts area chart (multi-series: nuevas/handoffs/citas) for daily series
- Replace Origen de demanda table with donut chart (FB/IG/Orgánico)
- Add horizontal bar charts for Intención, Tipo, Segmento, Lead scoring, Fricciones, Ads
- Add funnel chart for Embudo conversacional (replaces KPI cards)
- All existing tables preserved in collapsible toggles (accordion pattern)
- Add T+ progress bars (green/yellow/red by SLA) to agent, queue, handoff, and load tables
- Conditional alert banner below filters (appears only when queue > 10, SLA < 70%, or unanswered ≥ 5)
- Collapsible executive summary panel at bottom with dynamic PHP-interpolated bullets

## Test plan

- [ ] All 12 tests pass (`php artisan test tests/Feature/WhatsappKpiDashboardTest.php`)
- [ ] Dashboard loads without JS errors in browser console
- [ ] Charts render with real data on staging
- [ ] Alert banner appears/disappears based on conditions
- [ ] Executive summary expands/collapses
- [ ] All original tables still accessible via accordions

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review checklist

- [x] Serie diaria → `#chart-serie-diaria` ✓
- [x] Origen de demanda → `#chart-origen-demanda` ✓
- [x] Intención → `#chart-intencion` ✓
- [x] Tipo de conversación → `#chart-tipo-conv` ✓
- [x] Segmento → `#chart-segmento` ✓
- [x] Lead scoring → `#chart-lead-scoring` ✓
- [x] Fricciones → `#chart-fricciones` ✓
- [x] Embudo → `#chart-embudo` ✓
- [x] Ads → `#chart-ads` ✓
- [x] Banner condicional → `id="wa-alert-banner"` con 3 condiciones (queue > 10, SLA < 70, unanswered ≥ 5) ✓
- [x] Resumen ejecutivo → `id="exec-summary"` + `id="exec-summary-body"` ✓
- [x] T+ tables → 4 secciones con barras de progreso coloreadas por SLA ✓
- [x] Guard de datos vacíos → cada IIFE tiene `if (!rows || !rows.length)` ✓
- [x] ApexCharts CDN → en `@push('scripts')` ✓
- [x] 4 tests TDD → cubren IDs de charts, banner ON/OFF, y exec-summary ✓
- [x] Helpers `makeTestSummary` y `makeTestDashboard` definidos para tests de vista directa ✓
