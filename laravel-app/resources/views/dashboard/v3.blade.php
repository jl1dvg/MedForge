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
    $agenda       = is_array($dashboardV3['agenda'] ?? null) ? $dashboardV3['agenda'] : [];
    $flujoColumns = is_array($dashboardV3['flujo_columns'] ?? null) ? $dashboardV3['flujo_columns'] : [];
    $salas        = is_array($dashboardV3['salas'] ?? null) ? $dashboardV3['salas'] : [];

    $etapas      = is_array($solicitudesFunnel['etapas'] ?? null) ? $solicitudesFunnel['etapas'] : [];
    $conversion  = (float) ($solicitudesFunnel['totales']['conversion_agendada'] ?? 0.0);
    $funnelColors = ['#5156be', '#7479d4', '#3596f7', '#0863be', '#05825f'];
    $funnelLabels = [
        'recibido' => 'Recibido',
        'llamado' => 'Llamado',
        'en-atencion' => 'En atención',
        'revision-codigos' => 'Rev. códigos',
        'docs-completos' => 'Docs completos',
        'aprobacion-anestesia' => 'Aprob. anestesia',
        'listo-para-agenda' => 'Listo agenda',
        'otros' => 'Otros',
    ];
    $funnelStages = [];
    $i = 0;
    foreach (array_slice((array) $etapas, 0, 5, true) as $label => $value) {
        $funnelStages[] = [
            'label' => (string) ($funnelLabels[(string) $label] ?? $label),
            'value' => (int) $value,
            'color' => $funnelColors[$i] ?? '#5156be',
        ];
        $i++;
    }

    $ops = is_array($dashboardV3['ops'] ?? null) ? $dashboardV3['ops'] : [];

    if (!empty($doctoresTop)) {
        $equipo = array_map(static function ($row) {
            $name = trim((string) ($row['cirujano_1'] ?? ''));
            $initials = strtoupper(substr(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñ]/u', '', $name), 0, 1)
                                . substr(preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñ]/u', '', explode(' ', $name)[1] ?? ''), 0, 1));
            return [
                'initials' => $initials !== '' ? $initials : 'DR',
                'name'     => $name !== '' ? $name : 'Sin nombre',
                'role'     => 'Equipo quirúrgico',
                'cir'      => (int) ($row['total'] ?? 0),
                'cons'     => 0,
            ];
        }, array_slice($doctoresTop, 0, 3));
    } else {
        $equipo = [];
    }

    $iaSuggestions = is_array($dashboardV3['ia_suggestions'] ?? null) ? $dashboardV3['ia_suggestions'] : [];
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
                <span class="dash3-live"><span class="dash3-pulse"></span>en vivo</span>
            </h1>
        </div>
        <div class="dash3-head-right">
            <div class="dash3-period" role="tablist">
                <button type="button" class="active" data-period="hoy">Hoy</button>
                <button type="button" data-period="7d">7 días</button>
                <button type="button" data-period="30d">30 días</button>
            </div>
            <div class="dash3-ctx">
                <span class="dash3-ctx-chip"><i class="mdi mdi-calendar-today"></i>{{ $rangeLabel }}</span>
                <span class="dash3-ctx-chip"><i class="mdi mdi-domain"></i>{{ (string) ($currentUser['sede_name'] ?? 'Todas las sedes') }}</span>
            </div>
            <a href="/v2/dashboard" class="dash3-ctx-btn" title="Volver al dashboard clásico">
                <i class="mdi mdi-tune"></i>Filtros
            </a>
        </div>
    </header>

    {{-- =================== Hero KPI strip =================== --}}
    <section class="dash3-kpis">
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
            <div class="dash3-panel-body dash3-agenda">
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
            <div class="dash3-panel-body dash3-flujo">
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
            <div class="dash3-panel-body dash3-salas">
                @forelse($salas as $s)
                    <div class="dash3-sala dash3-sala--{{ $s['state'] }}">
                        <div class="dash3-sala-head">
                            <span class="dash3-sala-name">
                                <span class="dash3-sala-id">Q{{ $s['n'] }}</span>
                                Quirófano {{ $s['n'] }}
                            </span>
                            <span class="dash3-sala-state">
                                @if($s['state'] === 'ocupado')
                                    <span class="dash3-pulse dash3-pulse--danger"></span>ocupado
                                @elseif($s['state'] === 'preparando')
                                    <span class="dash3-pulse dash3-pulse--warning"></span>preparando
                                @elseif($s['state'] === 'registrado')
                                    registrado
                                @else
                                    {{ $s['state'] }}
                                @endif
                            </span>
                        </div>
                        <p class="dash3-sala-patient">{{ $s['patient'] }}</p>
                        <p class="dash3-sala-proc">{{ $s['proc'] }}</p>
                        <div class="dash3-sala-bar">
                            <div class="dash3-sala-bar-fill" style="width: {{ (int) $s['pct'] }}%"></div>
                        </div>
                        <div class="dash3-sala-foot">
                            <span><i class="mdi mdi-doctor"></i> {{ $s['doc'] }}</span>
                            <span><i class="mdi mdi-clock-outline"></i> {{ $s['elapsed'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="dash3-empty">No hay cirugías registradas hoy.</div>
                @endforelse
            </div>
        </article>
    </section>

    {{-- =================== Row 2 — backlog =================== --}}
    <section class="dash3-row">
        {{-- Embudo solicitudes --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-filter-variant"></i>Embudo solicitudes</h3>
                <span class="dash3-chip dash3-chip--success">{{ number_format($conversion, 1) }} % conversión</span>
            </header>
            <div class="dash3-panel-body dash3-funnel">
                @php $max = max(array_column($funnelStages, 'value')) ?: 1; @endphp
                @forelse($funnelStages as $s)
                    <div class="dash3-fn-row">
                        <span class="dash3-fn-label">{{ $s['label'] }}</span>
                        <div class="dash3-fn-track">
                            <div class="dash3-fn-fill" style="width: {{ ($s['value'] / $max) * 100 }}%; background: {{ $s['color'] }};"></div>
                        </div>
                        <span class="dash3-fn-value">{{ $s['value'] }}</span>
                    </div>
                @empty
                    <div class="dash3-empty">No hay solicitudes quirúrgicas en el rango.</div>
                @endforelse
                <div class="dash3-fn-foot">
                    <span>Registradas <strong>{{ (int) ($solicitudesFunnel['totales']['registradas'] ?? 0) }}</strong></span>
                    <span>Agendadas <strong>{{ (int) ($solicitudesFunnel['totales']['agendadas'] ?? 0) }}</strong></span>
                    <span>Con cirugía <strong>{{ (int) ($solicitudesFunnel['totales']['con_cirugia'] ?? 0) }}</strong></span>
                </div>
            </div>
        </article>

        {{-- Pendientes administrativos --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-bullhorn-outline"></i>Pendientes administrativos</h3>
                <span class="dash3-chip dash3-chip--muted">{{ count($ops) }} módulos</span>
            </header>
            <div class="dash3-panel-body dash3-ops">
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

        {{-- Equipo + IA --}}
        <article class="dash3-panel">
            <header class="dash3-panel-head">
                <h3><i class="mdi mdi-account-tie-outline"></i>Equipo &amp; Asistente IA</h3>
                <span class="dash3-chip dash3-chip--{{ $iaActive ? 'success' : 'muted' }}">
                    @if($iaActive)<span class="dash3-pulse dash3-pulse--success"></span>@endif
                    IA {{ $iaActive ? 'activa' : 'inactiva' }}
                </span>
            </header>
            <div class="dash3-panel-body dash3-team">
                <div class="dash3-team-list">
                    @forelse($equipo as $d)
                        <div class="dash3-tm-row">
                            <span class="dash3-tm-avatar">{{ $d['initials'] }}</span>
                            <div class="dash3-tm-info">
                                <p class="dash3-tm-name">{{ $d['name'] }}</p>
                                <p class="dash3-tm-role">{{ $d['role'] }}</p>
                            </div>
                            <div class="dash3-tm-stats">
                                <span><strong>{{ $d['cir'] }}</strong>cir</span>
                                <span><strong>{{ $d['cons'] }}</strong>cons</span>
                            </div>
                        </div>
                    @empty
                        <div class="dash3-empty">No hay actividad quirúrgica de equipo en el rango.</div>
                    @endforelse
                </div>
                <div class="dash3-ia">
                    <div class="dash3-ia-head">
                        <span class="dash3-ia-tile"><i class="mdi mdi-auto-fix"></i></span>
                        <div class="dash3-ia-meta">
                            <p class="dash3-ia-title">Asistente IA · {{ $iaProvider }}</p>
                            <p class="dash3-ia-sub">{{ $iaActive ? 'Métricas IA conectadas' : 'Sin fuente IA conectada' }}</p>
                        </div>
                    </div>
                    <ul class="dash3-ia-list">
                        @forelse($iaSuggestions as $s)
                            <li><i class="mdi mdi-circle-small"></i>{{ $s }}</li>
                        @empty
                            <li><i class="mdi mdi-circle-small"></i>No hay sugerencias recientes conectadas.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </article>
    </section>
</section>
@endsection

@push('scripts')
<script>
    /* Period segmented control — purely visual for now. Wire to a real
       query-string filter once `today / 7d / 30d` aggregates exist. */
    (function () {
        var btns = document.querySelectorAll('.dash3-period button');
        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                btns.forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
            });
        });
    })();
</script>
@endpush
