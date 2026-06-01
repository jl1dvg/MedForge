@extends('layouts.medforge')

{{--
    MedForge — Dashboard V3 · Centro de operaciones
    Single-screen, no-scroll operational dashboard.

    Consumes the SAME data passed by DashboardUiController::index() — we use
    a sibling method (indexV3) that delegates to index() with this view name.
    The shape mirrors what DashboardParityService->buildUiPayload() returns:
    `summary`, `date_range`, `solicitudes_quirurgicas`, `doctores_top`, `ai_summary`,
    `cirugias_recientes`, etc.

    The V3 view must not invent operational numbers. It reads real values
    from DashboardParityService and renders empty states when a module has
    no connected aggregate yet.
--}}

@php
    /* === Real data (when present) =================================== */
    $summaryData       = is_array($summary['data'] ?? null) ? $summary['data'] : [];
    $meta              = is_array($summary['meta'] ?? null) ? $summary['meta'] : [];
    $dateRange         = is_array($date_range ?? null) ? $date_range : (is_array($meta['date_range'] ?? null) ? $meta['date_range'] : []);
    $rangeLabel        = (string) ($dateRange['label'] ?? 'Hoy');
    $aiSummary         = is_array($ai_summary ?? null) ? $ai_summary : ['provider' => '', 'provider_configured' => false, 'features' => []];
    $doctoresTop       = is_array($doctores_top ?? null) ? $doctores_top : [];
    $dashboardV3       = is_array($dashboard_v3 ?? null) ? $dashboard_v3 : [];
    $solicitudesFunnel = is_array($summaryData['solicitudes_funnel'] ?? null) ? $summaryData['solicitudes_funnel'] : ['etapas' => [], 'totales' => [], 'prioridades' => []];
    $crmBacklog        = is_array($summaryData['crm_backlog'] ?? null) ? $summaryData['crm_backlog'] : [];
    $currentUserName   = trim((string) ($currentUser['display_name'] ?? ($currentUser['fname'] ?? 'doctor/a')));
    $greetingFirstName = $currentUserName !== '' ? (explode(' ', $currentUserName)[0]) : 'doctor/a';

    $hour = (int) date('G');
    $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

    $heroKpis     = is_array($dashboardV3['hero_kpis'] ?? null) ? $dashboardV3['hero_kpis'] : [];
    $agendaRaw    = is_array($dashboardV3['agenda'] ?? null) ? $dashboardV3['agenda'] : [];
    $agenda       = is_array($agendaRaw['items'] ?? null) ? $agendaRaw['items'] : $agendaRaw;
    $agendaPivot  = (int) ($agendaRaw['pivot_index'] ?? 0);
    $flujoColumns = is_array($dashboardV3['flujo_columns'] ?? null) ? $dashboardV3['flujo_columns'] : [];
    $salas        = is_array($dashboardV3['salas'] ?? null) ? $dashboardV3['salas'] : [];

    $referidosHoy   = is_array($dashboardV3['referidos_hoy'] ?? null) ? $dashboardV3['referidos_hoy'] : ['total' => 0, 'breakdown' => []];
    $referidosTotal = (int) ($referidosHoy['total'] ?? 0);
    $referidosBreak = is_array($referidosHoy['breakdown'] ?? null) ? $referidosHoy['breakdown'] : [];
    $referidosColors = ['#5156be', '#0863be', '#05825f', '#f5a623', '#e74c3c', '#7479d4', '#3596f7', '#2ecc71'];

    $ops = is_array($dashboardV3['ops'] ?? null) ? $dashboardV3['ops'] : [];

    $congestionMedicos = is_array($dashboardV3['congestion_medicos'] ?? null) ? $dashboardV3['congestion_medicos'] : [];
    $congestionMedicos = array_map(static function (array $m): array {
        $name     = $m['doctor'];
        $words    = preg_split('/\s+/u', $name);
        $initials = strtoupper(mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
        return array_merge($m, ['initials' => $initials !== '' ? $initials : 'DR']);
    }, $congestionMedicos);

    $iaSuggestions = is_array($dashboardV3['ia_suggestions'] ?? null) ? $dashboardV3['ia_suggestions'] : [];

    // Period buttons
    $today = date('Y-m-d');
    $activePeriod = 'hoy';
    $sd = trim((string) ($start_date ?? ''));
    $ed = trim((string) ($end_date ?? ''));
    if ($sd !== '' || $ed !== '') {
        $days = (strtotime($ed ?: $today) - strtotime($sd ?: $today)) / 86400 + 1;
        if ($days <= 1)  { $activePeriod = 'hoy'; }
        elseif ($days <= 7)  { $activePeriod = '7d'; }
        else             { $activePeriod = '30d'; }
    }
    $activeSede  = strtoupper(trim((string) ($sede ?? '')));
    $sedeParam   = $activeSede !== '' ? '&sede=' . $activeSede : '';
    $periodUrls = [
        'hoy' => '/v3/dashboard?start_date=' . $today . '&end_date=' . $today . $sedeParam,
        '7d'  => '/v3/dashboard?start_date=' . date('Y-m-d', strtotime('-6 days')) . '&end_date=' . $today . $sedeParam,
        '30d' => '/v3/dashboard?start_date=' . date('Y-m-d', strtotime('-29 days')) . '&end_date=' . $today . $sedeParam,
    ];
    $sedeUrls = [
        ''       => '/v3/dashboard?start_date=' . ($sd ?: $today) . '&end_date=' . ($ed ?: $today),
        'MATRIZ' => '/v3/dashboard?start_date=' . ($sd ?: $today) . '&end_date=' . ($ed ?: $today) . '&sede=MATRIZ',
        'CEIBOS' => '/v3/dashboard?start_date=' . ($sd ?: $today) . '&end_date=' . ($ed ?: $today) . '&sede=CEIBOS',
    ];
    $iaProvider = !empty($aiSummary['provider']) ? strtoupper((string) $aiSummary['provider']) : 'OPENAI';
    $iaActive   = (bool) ($aiSummary['provider_configured'] ?? false);

    /* === Tone palette for tiles ====================================== */
    $toneStyle = [
        'primary' => ['bg' => '#edf2ff', 'fg' => '#5156be'],
        'success' => ['bg' => '#dff5ee', 'fg' => '#05825f'],
        'warning' => ['bg' => '#fff0d1', 'fg' => '#8a5d0a'],
        'danger'  => ['bg' => '#fde2e7', 'fg' => '#ee3158'],
        'info'    => ['bg' => '#cfe5fd', 'fg' => '#0863be'],
    ];
@endphp

@push('styles')
@vite('resources/css/dashboard-v3.css')
@endpush

@section('content')
<section class="dash3" data-source="LARAVEL V2 / V3">
    {{-- =================== Page head =================== --}}
    <header class="dash3-head">
        <div class="dash3-head-left">
            <div class="dash3-crumb">
                <i class="mdi mdi-home-outline"></i>
                <span>Centro de operaciones</span>
            </div>
            <h1>
                {{ $greeting }}, {{ $greetingFirstName }}
                <span class="dash3-live" id="dash3-live-badge">
                    <span class="dash3-pulse"></span>en vivo
                    <span id="dash3-countdown" style="font-size:.7em;opacity:.7;margin-left:4px"></span>
                </span>
            </h1>
        </div>
        <div class="dash3-head-right">
            <div class="dash3-period" role="tablist">
                <a href="{{ $periodUrls['hoy'] }}" class="dash3-period-btn active">Hoy</a>
            </div>
            <div class="dash3-ctx">
                <span class="dash3-ctx-chip"><i class="mdi mdi-domain"></i>{{ (string) ($currentUser['sede_name'] ?? 'Todas las sedes') }}</span>
            </div>
            <div class="dash3-period" role="tablist">
                <a href="{{ $sedeUrls[''] }}"       class="dash3-period-btn {{ $activeSede === ''       ? 'active' : '' }}">Todas</a>
                <a href="{{ $sedeUrls['MATRIZ'] }}"  class="dash3-period-btn {{ $activeSede === 'MATRIZ'  ? 'active' : '' }}">Matriz</a>
                <a href="{{ $sedeUrls['CEIBOS'] }}"  class="dash3-period-btn {{ $activeSede === 'CEIBOS'  ? 'active' : '' }}">Ceibos</a>
            </div>
        </div>
    </header>

    {{-- =================== Hero KPI strip =================== --}}
    <section class="dash3-kpis" id="dash3-kpis">
        @foreach($heroKpis as $k)
            @php $t = $toneStyle[$k['tone']] ?? $toneStyle['primary']; @endphp
            <article class="dash3-kpi" style="--kpi-fg: {{ $t['fg'] }}">
                <div class="dash3-kpi-top">
                    <span class="dash3-kpi-tile" style="background: {{ $t['bg'] }}; color: {{ $t['fg'] }};">
                        <i class="mdi {{ $k['icon'] }}"></i>
                    </span>
                    <div class="dash3-kpi-main">
                        <p class="dash3-kpi-label">{{ $k['label'] }}</p>
                        <p class="dash3-kpi-value">{{ number_format((float) $k['value'], 0, ',', '.') }}</p>
                        <p class="dash3-kpi-trend">{{ $k['trend'] }}</p>
                    </div>
                </div>
                <div class="dash3-kpi-break">
                    @foreach($k['breakdown'] as $b)
                        <span class="dash3-kpi-stat">
                            <span class="dash3-dot dash3-dot--{{ $b['dot'] }}"></span>
                            <strong>{{ $b['n'] }}</strong>
                            <span class="dash3-kpi-stat-label">{{ $b['label'] }}</span>
                        </span>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    {{-- =================== Row 1 — operations =================== --}}
    <section class="dash3-row">
        {{-- Agenda --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-calendar-clock"></i>Agenda del día</h3>
                <a class="dash3-panel-link" href="/v2/agenda">Ver agenda <i class="mdi mdi-arrow-right-thin"></i></a>
            </header>
            <div class="dash3-panel-body dash3-agenda" id="dash3-agenda-body">
                @forelse($agenda as $e)
                    <div class="dash3-ag-row dash3-ag-row--{{ $e['state'] }}">
                        <span class="dash3-ag-time">{{ $e['time'] }}</span>
                        <span class="dash3-ag-marker dash3-mk--{{ $e['cat'] }}"></span>
                        <div class="dash3-ag-body">
                            <p class="dash3-ag-name">{{ $e['name'] }}</p>
                            <p class="dash3-ag-sub">{{ $e['doc'] }} · {{ $e['room'] }}</p>
                        </div>
                        @if($e['state'] === 'live')
                            <span class="dash3-ag-state dash3-ag-state--live">
                                <span class="dash3-pulse dash3-pulse--danger"></span>en curso
                            </span>
                        @elseif($e['state'] === 'next')
                            <span class="dash3-ag-state dash3-ag-state--next">próximo</span>
                        @else
                            <span class="dash3-ag-state dash3-ag-state--done">realizado</span>
                        @endif
                    </div>
                @empty
                    <div class="dash3-empty">No hay agenda registrada para hoy.</div>
                @endforelse
            </div>
        </article>

        {{-- Flujo de pacientes --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-account-multiple-check-outline"></i>Flujo de pacientes</h3>
                <a class="dash3-panel-link" href="/v2/pacientes/flujo">Tablero <i class="mdi mdi-arrow-right-thin"></i></a>
            </header>
            <div class="dash3-panel-body dash3-flujo" id="dash3-flujo-body">
                @forelse($flujoColumns as $c)
                    <div class="dash3-fl-col dash3-fl-col--{{ $c['id'] }}">
                        <div class="dash3-fl-top">
                            <span class="dash3-fl-count">{{ $c['count'] }}</span>
                            <span class="dash3-fl-label">{{ $c['label'] }}</span>
                        </div>
                        <ul class="dash3-fl-list">
                            @foreach($c['sample'] as $s)
                                <li>{{ $s }}</li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="dash3-empty">No hay flujo de pacientes registrado para hoy.</div>
                @endforelse
            </div>
        </article>

        {{-- Quirófanos --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-hospital-building"></i>Quirófanos</h3>
                <a class="dash3-panel-link" href="/v2/cirugias">Cirugías <i class="mdi mdi-arrow-right-thin"></i></a>
            </header>
            <div class="dash3-panel-body dash3-salas" id="dash3-salas-body">
                @forelse($salas as $s)
                    <div class="dash3-sala dash3-sala--{{ $s['state'] }}">
                        <div class="dash3-sala-head">
                            <span class="dash3-sala-name">{{ $s['patient'] }}</span>
                            <span class="dash3-sala-state">
                                @if($s['state'] === 'realizada')
                                    <span class="dash3-pulse dash3-pulse--success"></span>realizada
                                @else
                                    <span class="dash3-pulse dash3-pulse--warning"></span>pendiente
                                @endif
                            </span>
                        </div>
                        <p class="dash3-sala-proc">{{ $s['proc'] }}</p>
                        <div class="dash3-sala-foot">
                            <span><i class="mdi mdi-doctor"></i> {{ $s['doc'] }}</span>
                            <span><i class="mdi mdi-clock-outline"></i> {{ $s['time'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="dash3-empty">Sin cirugías con pacientes presentes hoy.</div>
                @endforelse
            </div>
        </article>
    </section>

    {{-- =================== Row 2 — backlog =================== --}}
    <section class="dash3-row">
        {{-- Referidos hoy --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-account-arrow-right-outline"></i>Referidos hoy</h3>
                <span class="dash3-chip dash3-chip--muted" id="dash3-referidos-chip">{{ $referidosTotal }} pacientes</span>
            </header>
            <div class="dash3-panel-body dash3-funnel" id="dash3-referidos-body">
                @php $maxRef = $referidosTotal ?: 1; @endphp
                @forelse($referidosBreak as $idx => $r)
                    <div class="dash3-fn-row">
                        <span class="dash3-fn-label">{{ $r['label'] }}</span>
                        <div class="dash3-fn-track">
                            <div class="dash3-fn-fill" style="width: {{ $r['pct'] }}%; background: {{ $referidosColors[$idx] ?? '#5156be' }};"></div>
                        </div>
                        <span class="dash3-fn-value">{{ $r['n'] }} <small style="color:#999">{{ $r['pct'] }}%</small></span>
                    </div>
                @empty
                    <div class="dash3-empty">Sin datos de referidos para hoy.</div>
                @endforelse
            </div>
        </article>

        {{-- Pendientes administrativos --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-bullhorn-outline"></i>Pendientes administrativos</h3>
                <span class="dash3-chip dash3-chip--muted">{{ count($ops) }} módulos</span>
            </header>
            <div class="dash3-panel-body dash3-ops" id="dash3-ops-body">
                @forelse($ops as $o)
                    @php $t = $toneStyle[$o['tone']] ?? $toneStyle['primary']; @endphp
                    <a class="dash3-ops-row" href="{{ $o['href'] }}">
                        <span class="dash3-ops-tile" style="background: {{ $t['bg'] }}; color: {{ $t['fg'] }};">
                            <i class="mdi {{ $o['icon'] }}"></i>
                        </span>
                        <div class="dash3-ops-main">
                            <p class="dash3-ops-module">{{ $o['module'] }}</p>
                            <p class="dash3-ops-sub">{{ $o['sub'] }}</p>
                        </div>
                        <div class="dash3-ops-num">
                            <strong>{{ $o['value'] }}</strong>
                            <span>{{ $o['label'] }}</span>
                        </div>
                        <i class="mdi mdi-chevron-right dash3-ops-chev"></i>
                    </a>
                @empty
                    <div class="dash3-empty">No hay métricas administrativas conectadas.</div>
                @endforelse
            </div>
        </article>

        {{-- Congestión médicos hoy --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-account-clock-outline"></i>Congestión médicos</h3>
                <span class="dash3-chip dash3-chip--muted">Hoy</span>
            </header>
            <div class="dash3-panel-body dash3-team">
                <div class="dash3-team-list" id="dash3-team-body">
                    @forelse($congestionMedicos as $m)
                        @php
                            $pct = $m['total_agenda'] > 0
                                ? round($m['atendidos'] / $m['total_agenda'] * 100)
                                : 0;
                            $espera = $m['avg_espera_min'];
                            $esperaLabel = $espera !== null
                                ? ($espera >= 60
                                    ? floor($espera / 60) . 'h ' . ($espera % 60) . 'min'
                                    : $espera . ' min')
                                : '--';
                            $congChip = $m['en_espera'] >= 5 ? 'danger' : ($m['en_espera'] >= 2 ? 'warning' : 'success');
                        @endphp
                        <div class="dash3-tm-row">
                            <span class="dash3-tm-avatar">{{ $m['initials'] }}</span>
                            <div class="dash3-tm-info">
                                <p class="dash3-tm-name">{{ $m['doctor'] }}</p>
                                <p class="dash3-tm-role">
                                    {{ $m['atendidos'] }}/{{ $m['total_agenda'] }} atendidos
                                    &nbsp;·&nbsp;
                                    <span class="dash3-dot dash3-dot--{{ $congChip }}"></span>
                                    {{ $m['en_espera'] }} en espera
                                </p>
                            </div>
                            <div class="dash3-tm-stats" style="min-width:60px;text-align:right">
                                <span style="font-size:.75rem;color:#666">⏱ {{ $esperaLabel }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="dash3-empty">Sin médicos con agenda activa hoy.</div>
                    @endforelse
                </div>
            </div>
        </article>
    </section>
</section>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const INTERVAL = 60;
    const TONE = {
        primary: { bg: '#edf2ff', fg: '#5156be' },
        success:  { bg: '#dff5ee', fg: '#05825f' },
        warning:  { bg: '#fff0d1', fg: '#8a5d0a' },
        danger:   { bg: '#fde2e7', fg: '#ee3158' },
        info:     { bg: '#cfe5fd', fg: '#0863be' },
    };
    const REF_COLORS = ['#5156be','#0863be','#05825f','#f5a623','#e74c3c','#7479d4','#3596f7','#2ecc71'];
    const EMPTY = (msg) => `<div class="dash3-empty">${msg}</div>`;

    const params = new URLSearchParams(window.location.search);
    const dataUrl = '/v3/dashboard/data?' + params.toString();

    let countdown = INTERVAL;
    const cdEl = document.getElementById('dash3-countdown');
    const badge = document.getElementById('dash3-live-badge');

    function tick() {
        countdown--;
        if (cdEl) cdEl.textContent = `(${countdown}s)`;
        if (countdown <= 0) {
            countdown = INTERVAL;
            fetchData();
        }
    }

    setInterval(tick, 1000);

    function fmt(n) {
        return Number(n).toLocaleString('es-EC');
    }

    function renderKpis(kpis) {
        const el = document.getElementById('dash3-kpis');
        if (!el || !kpis) return;
        el.innerHTML = kpis.map(k => {
            const t = TONE[k.tone] || TONE.primary;
            const breakdown = (k.breakdown || []).map(b =>
                `<span class="dash3-kpi-stat">
                    <span class="dash3-dot dash3-dot--${b.dot}"></span>
                    <strong>${b.n}</strong>
                    <span class="dash3-kpi-stat-label">${b.label}</span>
                </span>`
            ).join('');
            return `<article class="dash3-kpi" style="--kpi-fg: ${t.fg}">
                <div class="dash3-kpi-top">
                    <span class="dash3-kpi-tile" style="background: ${t.bg}; color: ${t.fg};">
                        <i class="mdi ${k.icon}"></i>
                    </span>
                    <div class="dash3-kpi-main">
                        <p class="dash3-kpi-label">${k.label}</p>
                        <p class="dash3-kpi-value">${fmt(k.value)}</p>
                        <p class="dash3-kpi-trend">${k.trend}</p>
                    </div>
                </div>
                <div class="dash3-kpi-break">${breakdown}</div>
            </article>`;
        }).join('');
    }

    function renderAgenda(agendaRaw) {
        const el = document.getElementById('dash3-agenda-body');
        if (!el) return;
        const items = agendaRaw?.items ?? (Array.isArray(agendaRaw) ? agendaRaw : []);
        if (!items.length) { el.innerHTML = EMPTY('No hay agenda registrada para hoy.'); return; }
        el.innerHTML = items.map(e => {
            let stateHtml = '';
            if (e.state === 'live') {
                stateHtml = `<span class="dash3-ag-state dash3-ag-state--live"><span class="dash3-pulse dash3-pulse--danger"></span>en curso</span>`;
            } else if (e.state === 'next') {
                stateHtml = `<span class="dash3-ag-state dash3-ag-state--next">próximo</span>`;
            } else {
                stateHtml = `<span class="dash3-ag-state dash3-ag-state--done">realizado</span>`;
            }
            return `<div class="dash3-ag-row dash3-ag-row--${e.state}">
                <span class="dash3-ag-time">${e.time}</span>
                <span class="dash3-ag-marker dash3-mk--${e.cat}"></span>
                <div class="dash3-ag-body">
                    <p class="dash3-ag-name">${e.name}</p>
                    <p class="dash3-ag-sub">${e.doc} · ${e.room}</p>
                </div>
                ${stateHtml}
            </div>`;
        }).join('');
    }

    function renderFlujo(cols) {
        const el = document.getElementById('dash3-flujo-body');
        if (!el) return;
        if (!cols?.length) { el.innerHTML = EMPTY('No hay flujo de pacientes registrado para hoy.'); return; }
        el.innerHTML = cols.map(c =>
            `<div class="dash3-fl-col dash3-fl-col--${c.id}">
                <div class="dash3-fl-top">
                    <span class="dash3-fl-count">${c.count}</span>
                    <span class="dash3-fl-label">${c.label}</span>
                </div>
                <ul class="dash3-fl-list">${(c.sample || []).map(s => `<li>${s}</li>`).join('')}</ul>
            </div>`
        ).join('');
    }

    function renderSalas(salas) {
        const el = document.getElementById('dash3-salas-body');
        if (!el) return;
        if (!salas?.length) { el.innerHTML = EMPTY('Sin cirugías con pacientes presentes hoy.'); return; }
        el.innerHTML = salas.map(s => {
            const stateHtml = s.state === 'realizada'
                ? `<span class="dash3-pulse dash3-pulse--success"></span>realizada`
                : `<span class="dash3-pulse dash3-pulse--warning"></span>pendiente`;
            return `<div class="dash3-sala dash3-sala--${s.state}">
                <div class="dash3-sala-head">
                    <span class="dash3-sala-name">${s.patient}</span>
                    <span class="dash3-sala-state">${stateHtml}</span>
                </div>
                <p class="dash3-sala-proc">${s.proc}</p>
                <div class="dash3-sala-foot">
                    <span><i class="mdi mdi-doctor"></i> ${s.doc}</span>
                    <span><i class="mdi mdi-clock-outline"></i> ${s.time}</span>
                </div>
            </div>`;
        }).join('');
    }

    function renderReferidos(ref) {
        const el = document.getElementById('dash3-referidos-body');
        const chip = document.getElementById('dash3-referidos-chip');
        if (!el || !ref) return;
        const total = ref.total ?? 0;
        const breakdown = ref.breakdown ?? [];
        if (chip) chip.textContent = `${total} pacientes`;
        if (!breakdown.length) { el.innerHTML = EMPTY('Sin datos de referidos para hoy.'); return; }
        el.innerHTML = breakdown.map((r, idx) =>
            `<div class="dash3-fn-row">
                <span class="dash3-fn-label">${r.label}</span>
                <div class="dash3-fn-track">
                    <div class="dash3-fn-fill" style="width: ${r.pct}%; background: ${REF_COLORS[idx] ?? '#5156be'};"></div>
                </div>
                <span class="dash3-fn-value">${r.n} <small style="color:#999">${r.pct}%</small></span>
            </div>`
        ).join('');
    }

    function renderOps(ops) {
        const el = document.getElementById('dash3-ops-body');
        if (!el) return;
        if (!ops?.length) { el.innerHTML = EMPTY('No hay métricas administrativas conectadas.'); return; }
        el.innerHTML = ops.map(o => {
            const t = TONE[o.tone] || TONE.primary;
            return `<a class="dash3-ops-row" href="${o.href}">
                <span class="dash3-ops-tile" style="background: ${t.bg}; color: ${t.fg};">
                    <i class="mdi ${o.icon}"></i>
                </span>
                <div class="dash3-ops-main">
                    <p class="dash3-ops-module">${o.module}</p>
                    <p class="dash3-ops-sub">${o.sub}</p>
                </div>
                <div class="dash3-ops-num">
                    <strong>${o.value}</strong>
                    <span>${o.label}</span>
                </div>
                <i class="mdi mdi-chevron-right dash3-ops-chev"></i>
            </a>`;
        }).join('');
    }

    function getInitials(name) {
        const words = (name || '').trim().split(/\s+/);
        const i = ((words[0] || '').charAt(0) + (words[1] || '').charAt(0)).toUpperCase();
        return i || 'DR';
    }

    function renderTeam(medicos) {
        const el = document.getElementById('dash3-team-body');
        if (!el) return;
        if (!medicos?.length) { el.innerHTML = EMPTY('Sin médicos con agenda activa hoy.'); return; }
        el.innerHTML = medicos.map(m => {
            const pct = m.total_agenda > 0 ? Math.round(m.atendidos / m.total_agenda * 100) : 0;
            const espera = m.avg_espera_min;
            let esperaLabel = '--';
            if (espera !== null && espera !== undefined) {
                esperaLabel = espera >= 60
                    ? `${Math.floor(espera/60)}h ${espera%60}min`
                    : `${espera} min`;
            }
            const chip = m.en_espera >= 5 ? 'danger' : (m.en_espera >= 2 ? 'warning' : 'success');
            return `<div class="dash3-tm-row">
                <span class="dash3-tm-avatar">${getInitials(m.doctor)}</span>
                <div class="dash3-tm-info">
                    <p class="dash3-tm-name">${m.doctor}</p>
                    <p class="dash3-tm-role">
                        ${m.atendidos}/${m.total_agenda} atendidos
                        &nbsp;·&nbsp;
                        <span class="dash3-dot dash3-dot--${chip}"></span>
                        ${m.en_espera} en espera
                    </p>
                </div>
                <div class="dash3-tm-stats" style="min-width:60px;text-align:right">
                    <span style="font-size:.75rem;color:#666">⏱ ${esperaLabel}</span>
                </div>
            </div>`;
        }).join('');
    }

    function pulse() {
        if (!badge) return;
        badge.classList.add('dash3-live--flash');
        setTimeout(() => badge.classList.remove('dash3-live--flash'), 800);
    }

    async function fetchData() {
        try {
            const res = await fetch(dataUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const json = await res.json();
            const d = json.dashboard_v3 || {};
            renderKpis(d.hero_kpis);
            renderAgenda(d.agenda);
            renderFlujo(d.flujo_columns);
            renderSalas(d.salas);
            renderReferidos(d.referidos_hoy);
            renderOps(d.ops);
            renderTeam(d.congestion_medicos);
            pulse();
        } catch (_) { /* silent — next tick will retry */ }
    }
}());
</script>
@endpush

