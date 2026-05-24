@extends('layouts.medforge')

@php
    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
    $trends = is_array($dashboard['trends'] ?? null) ? $dashboard['trends'] : [];
    $breakdowns = is_array($dashboard['breakdowns'] ?? null) ? $dashboard['breakdowns'] : [];
    $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
    $reminders = is_array($dashboard['reminders'] ?? null) ? $dashboard['reminders'] : [];
    $analyticsSummary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
    $analyticsLifecycle = is_array($analytics['lifecycle'] ?? null) ? $analytics['lifecycle'] : [];
    $analyticsSources = is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [];
    $analyticsFunnel = is_array($analytics['funnel'] ?? null) ? $analytics['funnel'] : [];
    $analyticsOutcomes = is_array($analytics['outcomes'] ?? null) ? $analytics['outcomes'] : [];
    $analyticsIntents = is_array($analytics['intents'] ?? null) ? $analytics['intents'] : [];
    $analyticsConversationTypes = is_array($analytics['conversation_types'] ?? null) ? $analytics['conversation_types'] : [];
    $analyticsSegments = is_array($analytics['segments'] ?? null) ? $analytics['segments'] : [];
    $analyticsLeadScores = is_array($analytics['lead_scores'] ?? null) ? $analytics['lead_scores'] : [];
    $analyticsFrictions = is_array($analytics['frictions'] ?? null) ? $analytics['frictions'] : [];
    $analyticsInsights = is_array($analytics['insights'] ?? null) ? $analytics['insights'] : [];
    $analyticsAds = is_array($analytics['ads'] ?? null) ? $analytics['ads'] : [];
    $reminderSummary = is_array($reminders['summary'] ?? null) ? $reminders['summary'] : [];
    $reminderConfig = is_array($reminders['config'] ?? null) ? $reminders['config'] : [];
    $reminderBySourceWindow = is_array($reminders['by_source_window'] ?? null) ? $reminders['by_source_window'] : [];
    $reminderRecent = is_array($reminders['recent'] ?? null) ? $reminders['recent'] : [];
    $reminderTimezone = trim((string) ($reminderConfig['timezone'] ?? 'America/Guayaquil')) ?: 'America/Guayaquil';
    $formatReminderDate = static function ($value, string $format = 'd/m H:i') use ($reminderTimezone): string {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value, 'UTC')
                ->setTimezone($reminderTimezone)
                ->format($format);
        } catch (\Throwable) {
            return '—';
        }
    };
    $options = is_array($dashboard['options'] ?? null) ? $dashboard['options'] : ['roles' => [], 'agents' => []];
    $filters = is_array($filters ?? null) ? $filters : [];
    $exportQuery = http_build_query(array_filter([
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'role_id' => $filters['role_id'] ?? null,
        'agent_id' => $filters['agent_id'] ?? null,
        'sla_target_minutes' => $filters['sla_target_minutes'] ?? null,
    ], static fn ($value) => $value !== null && $value !== ''));
    $sectionHelp = [
        'dashboard_title' => 'Resumen general del canal para leer salud operativa, carga humana, ventana de 24 horas, atribución y conversión sin bajar a tablas técnicas.',
        'supervisor_band' => 'Lectura ejecutiva compacta de cobertura humana, conversaciones sin respuesta, cola actual y cumplimiento del SLA de asignación.',
        'window_band' => 'Resume la presión operativa fuera de ventana: cuántas conversaciones requieren plantilla y cuántas ya están esperando respuesta tras enviarla.',
        'drilldown_band' => 'Accesos rápidos a listados detallados por métrica para auditoría y revisión de casos concretos.',
        'executive_view' => 'Agrupa el canal en captación, operación, seguimiento clínico y reactivación para separar demanda comercial de carga operativa.',
        'executive_mix' => 'Compara cada macro-categoría por volumen, identificación, booking y dependencia de humano.',
        'channel_capture' => 'Resumen gerencial de conversaciones nuevas: origen, identificación, handoff y conversión a cita.',
        'source_demand' => 'Distribución por origen de demanda para distinguir Ads, orgánico, campañas y retornos clínicos o operativos.',
        'conversation_outcomes' => 'Cierre final de cada conversación nueva: cita, handoff, resolución o conversación abierta/sin cierre.',
        'initial_intent' => 'Clasificación del primer mensaje útil detectado en la conversación nueva.',
        'conversation_type' => 'Lectura operativa/comercial del contacto una vez resuelto el contexto del caso.',
        'patient_segment' => 'Distribución entre paciente nuevo, recurrente y reactivado en conversaciones nuevas.',
        'lead_scoring' => 'Priorización comercial estimada según identificación, avance en flujo y conversión efectiva.',
        'frictions' => 'Estados donde más se frenan conversaciones sin cierre efectivo para detectar puntos de fricción del flujo.',
        'funnel' => 'Embudo desde conversación nueva hasta booking creado para entender pérdidas y avance comercial.',
        'insights' => 'Síntesis automática para gerencia basada en origen, intención, calidad y fricciones del canal.',
        'ads' => 'Ranking de anuncios que más aportan conversaciones, identificación, handoff y citas.',
        'reminders_summary' => 'Volumen y resultado real de recordatorios persistidos en base: envío, entrega, respuesta y desvío a agente.',
        'reminders_mix' => 'Corte por tipo de servicio y por ventana para distinguir comportamiento de servicios oftalmológicos generales frente a imágenes.',
        'reminders_recent' => 'Últimos recordatorios generados con su estado, respuesta y plantilla usada para auditoría rápida.',
        'series' => 'Serie diaria del periodo para leer volumen general del canal y sus principales eventos.',
        'human_by_agent' => 'Qué agente absorbió más conversaciones y en cuánto tiempo respondió por primera vez tras el handoff.',
        'human_by_queue' => 'Tiempo de primera respuesta humana agrupado por cola operativa para diferenciar captación, operación, información y backlog crítico.',
        'handoffs_by_role' => 'Distribución de handoffs por equipo para medir entrada, asignación y cierre operativo.',
        'agent_load' => 'Carga por agente para detectar saturación, reparto desigual o capacidad ociosa.',
    ];
@endphp

@php
    $slaMeta         = (int) ($filters['sla_target_minutes'] ?? 15);
    $alertQueue      = ($summary['live_queue_queued'] ?? 0) > 10;
    $alertSla        = ($summary['sla_assignments_rate'] ?? 100) < 70;
    $alertUnanswered = ($summary['unanswered_no_human'] ?? 0) >= 5;
    $hasAlerts       = $alertQueue || $alertSla || $alertUnanswered;
    $alertCount      = (int) $alertQueue + (int) $alertSla + (int) $alertUnanswered;

    $topSource   = collect($analyticsSources)->sortByDesc('total')->first();
    $topIntent   = collect($analyticsIntents)->sortByDesc('total')->first();
    $topSegment  = collect($analyticsSegments)->sortByDesc('total')->first();
    $topFriction = collect($analyticsFrictions)->sortByDesc('total')->first();

    $analyticsAds = collect($analyticsAds)
        ->groupBy(function ($row) {
            $headline = trim((string) ($row['headline'] ?? ''));
            return $headline !== '' ? $headline : 'Sin nombre de anuncio';
        })
        ->map(function ($rows, $headline) {
            $conversations = (int) $rows->sum('conversations');
            $identified    = (int) $rows->sum('identified');
            $bookings      = (int) $rows->sum('bookings');
            $handoffs      = (int) $rows->sum('handoffs');
            $sourceIds     = $rows->pluck('source_id')->filter()->unique()->values();
            $platforms     = $rows->pluck('platform')->filter()->unique()->values();
            $mediaTypes    = $rows->pluck('media_type')->filter()->unique()->values();

            return [
                'headline'       => $headline,
                'source_id'      => $sourceIds->count() === 1 ? $sourceIds->first() : $sourceIds->count() . ' anuncios agrupados',
                'source_ids'     => $sourceIds->all(),
                'platform'       => $platforms->count() === 1 ? $platforms->first() : 'multiple',
                'platform_label' => $platforms->count() === 1 ? ($rows->first()['platform_label'] ?? 'Desconocido') : 'Varias',
                'media_type'     => $mediaTypes->count() === 1 ? $mediaTypes->first() : 'Mixto',
                'conversations'  => $conversations,
                'identified'     => $identified,
                'bookings'       => $bookings,
                'booking_rate'   => $conversations > 0 ? round(($bookings / $conversations) * 100, 1) : 0,
                'handoffs'       => $handoffs,
            ];
        })
        ->sortByDesc('bookings')
        ->sortByDesc('conversations')
        ->values()
        ->all();

    $topAd       = collect($analyticsAds)->sortByDesc('bookings')->first();
    $totalConvs  = $analyticsSummary['total_conversations'] ?? 0;
    $frictionHighShare = isset($topFriction['share']) && (int) $topFriction['share'] > 30;
@endphp

@push('styles')
    <style>
        .wa-dashboard-pagebar {
            border-radius: 28px;
            padding: 24px 26px;
            background: radial-gradient(circle at top left, rgba(14, 165, 233, .16), transparent 34%),
            radial-gradient(circle at top right, rgba(16, 185, 129, .14), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 48%, #115e59 100%);
            color: #f8fafc;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
        }

        .wa-dashboard-pagebar__top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }

        .wa-dashboard-pagebar__title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -.03em;
        }

        .wa-dashboard-pagebar__subtitle {
            margin-top: 8px;
            color: rgba(248, 250, 252, .82);
            max-width: 780px;
            font-size: 14px;
            line-height: 1.6;
        }

        .wa-dashboard-pagebar__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .wa-dashboard-hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .14);
            color: #f8fafc;
            font-size: 12px;
            font-weight: 700;
        }

        .wa-dashboard-filter-shell {
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, .18);
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
            padding: 18px 20px;
        }

        .wa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
        }

        .wa-kpi-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .96));
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        }

        .wa-kpi-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .wa-kpi-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .wa-kpi-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wa-kpi-help {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .45);
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            cursor: help;
            flex: 0 0 auto;
            background: rgba(255, 255, 255, .92);
            position: relative;
            user-select: none;
        }

        .wa-kpi-help__tooltip {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 260px;
            max-width: min(260px, 72vw);
            padding: 10px 12px;
            border-radius: 12px;
            background: #0f172a;
            color: #f8fafc;
            font-size: 12px;
            line-height: 1.45;
            text-transform: none;
            letter-spacing: normal;
            font-weight: 500;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .22);
            border: 1px solid rgba(148, 163, 184, .2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity .15s ease, transform .15s ease, visibility .15s ease;
            z-index: 20;
            pointer-events: none;
        }

        .wa-kpi-help__tooltip::before {
            content: "";
            position: absolute;
            top: -6px;
            right: 10px;
            width: 12px;
            height: 12px;
            background: #0f172a;
            border-left: 1px solid rgba(148, 163, 184, .2);
            border-top: 1px solid rgba(148, 163, 184, .2);
            transform: rotate(45deg);
        }

        .wa-kpi-help:hover .wa-kpi-help__tooltip,
        .wa-kpi-help:focus .wa-kpi-help__tooltip,
        .wa-kpi-help:focus-visible .wa-kpi-help__tooltip,
        .wa-kpi-help:active .wa-kpi-help__tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .wa-kpi-help--light {
            background: rgba(255, 255, 255, .14);
            border-color: rgba(255, 255, 255, .22);
            color: #f8fafc;
        }

        .wa-kpi-help--light .wa-kpi-help__tooltip {
            background: #f8fafc;
            color: #0f172a;
            border-color: rgba(15, 23, 42, .12);
        }

        .wa-kpi-help--light .wa-kpi-help__tooltip::before {
            background: #f8fafc;
            border-left-color: rgba(15, 23, 42, .12);
            border-top-color: rgba(15, 23, 42, .12);
        }

        .wa-kpi-value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.04em;
            color: #0f172a;
            line-height: 1.1;
            margin-top: .45rem;
        }

        .wa-kpi-sub {
            margin-top: .45rem;
            font-size: 12px;
            color: #64748b;
        }

        .wa-kpi-table td, .wa-kpi-table th {
            vertical-align: middle;
            font-size: .84rem;
        }

        .wa-kpi-series-bar {
            display: flex;
            gap: 4px;
            align-items: flex-end;
            min-height: 120px;
        }

        .wa-kpi-series-bar span {
            display: block;
            flex: 1 1 0;
            border-radius: 8px 8px 0 0;
            background: linear-gradient(180deg, #0d6efd 0%, #67a4ff 100%);
            min-width: 8px;
        }

        .wa-kpi-series-labels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(48px, 1fr));
            gap: 4px;
            margin-top: .5rem;
            font-size: .72rem;
            color: #64748b;
        }

        .wa-kpi-band {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            border-radius: 22px;
            padding: 18px 20px;
            min-height: 100%;
            box-shadow: 0 16px 30px rgba(15, 23, 42, .12);
        }

        .wa-kpi-band h5 {
            margin: 0 0 .35rem;
            color: #fff;
        }

        .wa-kpi-band .muted {
            color: rgba(255, 255, 255, .72);
            font-size: .84rem;
        }

        .wa-kpi-actions a {
            margin-right: .5rem;
            margin-bottom: .5rem;
        }

        .wa-kpi-link-list a {
            display: inline-flex;
            align-items: center;
            margin: 0 .5rem .5rem 0;
        }

        .wa-kpi-panel {
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, .18);
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .wa-kpi-panel__head {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            background: radial-gradient(circle at top left, rgba(14, 165, 233, .06), transparent 34%), #fff;
        }

        .wa-kpi-panel__body {
            padding: 18px 20px;
        }

        .wa-kpi-sideheading__title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.02em;
            color: #0f172a;
        }

        .wa-kpi-sideheading__meta {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 767px) {
            .wa-dashboard-pagebar {
                padding: 20px 18px;
                border-radius: 24px;
            }

            .wa-dashboard-pagebar__top {
                flex-direction: column;
            }

            .wa-dashboard-filter-shell,
            .wa-kpi-panel__head,
            .wa-kpi-panel__body {
                padding: 16px;
            }
        }

        /* Zona Ahora */
        .wa-now-zone {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .wa-now-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px 20px;
            border-left: 4px solid #e2e8f0;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .wa-now-card--alert {
            border-left-color: #ef4444;
            background: #fff5f5;
        }

        .wa-now-card--warn {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }

        .wa-now-card--ok {
            border-left-color: #10b981;
            background: #f0fdf4;
        }

        .wa-now-card__value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .wa-now-card__label {
            font-size: .82rem;
            color: #64748b;
            font-weight: 500;
        }

        .wa-now-card__action {
            font-size: .75rem;
            color: #3b82f6;
            margin-top: 4px;
            text-decoration: none;
        }

        .wa-now-card__action:hover {
            text-decoration: underline;
        }

        .wa-section-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: .82rem;
            color: #64748b;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .wa-section-toggle:hover {
            background: #f1f5f9;
        }

        /* ── T+ Progress bars ─────────────────────────────── */
        .wa-prog-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wa-prog-bg {
            flex: 1;
            background: #f1f5f9;
            border-radius: 4px;
            height: 6px;
            overflow: hidden;
        }

        .wa-prog-fill {
            height: 6px;
            border-radius: 4px;
            transition: width .3s ease;
        }

        .wa-prog-fill--green {
            background: #10b981;
        }

        .wa-prog-fill--yellow {
            background: #f59e0b;
        }

        .wa-prog-fill--red {
            background: #ef4444;
        }

        .wa-prog-val {
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            min-width: 36px;
            text-align: right;
        }

        .wa-prog-val--green {
            color: #059669;
        }

        .wa-prog-val--yellow {
            color: #d97706;
        }

        .wa-prog-val--red {
            color: #dc2626;
        }

        /* ── Chart section group labels ───────────────────── */
        .wa-group-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 6px 0 4px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 10px;
            margin-top: 18px;
        }

        /* ── Chart wrappers ───────────────────────────────── */
        .wa-chart-wrap {
            min-height: 200px;
        }

        .wa-chart-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 160px;
            color: #94a3b8;
            font-size: 13px;
            background: #f8fafc;
            border-radius: 10px;
        }

        /* ── Chart summary chips ──────────────────────────── */
        .wa-chart-chips {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .wa-chart-chip {
            text-align: center;
        }

        .wa-chart-chip__val {
            font-size: 18px;
            font-weight: 800;
            line-height: 1;
        }

        .wa-chart-chip__lbl {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="row g-3">
            <div class="col-12">
                <div class="wa-dashboard-pagebar">
                    <div class="wa-dashboard-pagebar__top">
                        <div>
                            <div class="wa-kpi-title-row">
                                <div class="wa-dashboard-pagebar__title">WhatsApp KPI Dashboard</div>
                                <span class="visually-hidden">Personas que escribieron · Tiempo a primera respuesta humana · Desde handoff · mediana {{ isset($summary['median_first_human_response_minutes']) ? $summary['median_first_human_response_minutes'] . ' min' : '—' }}</span>
                                <button type="button" class="wa-kpi-help wa-kpi-help--light"
                                        aria-label="Ver ayuda del dashboard">
                                    ?
                                    <span class="wa-kpi-help__tooltip">{{ $sectionHelp['dashboard_title'] }}</span>
                                </button>
                            </div>
                            <div class="wa-dashboard-pagebar__subtitle">
                                Salud operativa y análisis del canal — actualizado al aplicar filtros
                            </div>
                        </div>
                        <div class="wa-dashboard-pagebar__meta">
                            @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                                <span class="wa-dashboard-hero-pill"><i class="mdi mdi-calendar-range"></i> {{ $filters['date_from'] ?? '—' }} → {{ $filters['date_to'] ?? '—' }}</span>
                            @endif
                            @if(!empty($filters['agent_id']))
                                <span class="wa-dashboard-hero-pill"><i
                                        class="mdi mdi-account"></i> Agente filtrado</span>
                            @else
                                <span class="wa-dashboard-hero-pill"><i class="mdi mdi-account-group"></i> Todos los agentes</span>
                            @endif
                            <span class="wa-dashboard-hero-pill"><i class="mdi mdi-timer-outline"></i> Meta SLA: {{ $filters['sla_target_minutes'] ?? 15 }} min</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Banner de alertas condicional ─────────────────────────────── --}}
            @if($hasAlerts)
                <div class="col-12">
                    <div id="wa-alert-banner" class="mb-3"
                         style="background:linear-gradient(135deg,#fef2f2,#fff7ed);border:1px solid #fecaca;border-radius:14px;padding:14px 20px;display:flex;align-items:center;gap:14px;">
                        <span style="font-size:24px;flex-shrink:0">⚠️</span>
                        <div style="flex:1">
                            <div style="font-size:13px;font-weight:700;color:#dc2626;line-height:1.3">
                                {{ $alertCount }} {{ $alertCount === 1 ? 'alerta activa' : 'alertas activas' }} en este
                                periodo
                            </div>
                            <div style="font-size:11px;color:#64748b;margin-top:3px;line-height:1.5">
                                @if($alertQueue)
                                    Cola activa alta ({{ $summary['live_queue_queued'] }} en espera).
                                @endif
                                @if($alertSla)
                                    SLA por debajo de meta ({{ $summary['sla_assignments_rate'] }}%).
                                @endif
                                @if($alertUnanswered)
                                    {{ $summary['unanswered_no_human'] }} conversaciones sin respuesta humana.
                                @endif
                            </div>
                        </div>
                        <a href="#exec-summary"
                           onclick="document.getElementById('exec-summary-body').classList.remove('d-none')"
                           style="font-size:11px;color:#3b82f6;font-weight:600;white-space:nowrap;text-decoration:none;">Ver
                            resumen ejecutivo ↓</a>
                    </div>
                </div>
            @endif

            <div class="col-12">
                <div class="wa-dashboard-filter-shell">
                    <form method="GET" action="/v2/whatsapp/dashboard" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Desde</label>
                            <input type="date" class="form-control" name="date_from"
                                   value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasta</label>
                            <input type="date" class="form-control" name="date_to"
                                   value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Equipo</label>
                            <select class="form-select" name="role_id">
                                <option value="">Todos</option>
                                @foreach(($options['roles'] ?? []) as $role)
                                    <option
                                        value="{{ $role['id'] }}" {{ (int) ($filters['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' }}>{{ $role['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Agente</label>
                            <select class="form-select" name="agent_id">
                                <option value="">Todos</option>
                                @foreach(($options['agents'] ?? []) as $agent)
                                    <option
                                        value="{{ $agent['id'] }}" {{ (int) ($filters['agent_id'] ?? 0) === (int) $agent['id'] ? 'selected' : '' }}>{{ $agent['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SLA (min)</label>
                            <input type="number" min="1" max="1440" class="form-control" name="sla_target_minutes"
                                   value="{{ $filters['sla_target_minutes'] ?? 15 }}">
                        </div>
                        <div class="col-12 d-flex gap-10">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                            <a href="/v2/whatsapp/dashboard" class="btn btn-light">Limpiar</a>
                            <a href="/v2/whatsapp/api/kpis/export?{{ $exportQuery }}" class="btn btn-success">Exportar
                                CSV</a>
                            <a href="/v2/whatsapp/api/kpis/export/pdf?{{ $exportQuery }}" class="btn btn-dark">Resumen
                                PDF</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 col-12">
                <div class="wa-kpi-band">
                    <div class="text-uppercase muted" style="letter-spacing:.08em;">Supervisor</div>
                    <div class="wa-kpi-title-row">
                        <h5>Salud operativa</h5>
                        <button type="button" class="wa-kpi-help wa-kpi-help--light"
                                aria-label="Ver ayuda de Salud operativa">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['supervisor_band'] }}</span>
                        </button>
                    </div>
                    <div class="muted">Cobertura {{ $summary['attention_rate'] ?? 0 }}% · Sin
                        respuesta {{ $summary['loss_rate'] ?? 0 }}%
                    </div>
                    <div class="mt-10 fw-600">Cola {{ $summary['live_queue_total'] ?? 0 }} ·
                        SLA {{ $summary['sla_assignments_rate'] ?? 0 }}%
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12">
                <div class="wa-kpi-band" style="background:linear-gradient(135deg, #14532d 0%, #166534 100%);">
                    <div class="text-uppercase muted" style="letter-spacing:.08em;">Ventana</div>
                    <div class="wa-kpi-title-row">
                        <h5>Conversaciones fuera de 24h</h5>
                        <button type="button" class="wa-kpi-help wa-kpi-help--light"
                                aria-label="Ver ayuda de Conversaciones fuera de 24h">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['window_band'] }}</span>
                        </button>
                    </div>
                    <div class="muted">Requieren plantilla {{ $summary['queue_needs_template'] ?? 0 }}</div>
                    <div class="mt-10 fw-600">Esperando respuesta a
                        plantilla {{ $summary['queue_awaiting_template_reply'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 col-12">
                <div class="wa-kpi-band" style="background:linear-gradient(135deg, #7c2d12 0%, #9a3412 100%);">
                    <div class="text-uppercase muted" style="letter-spacing:.08em;">Acciones rápidas</div>
                    <div class="wa-kpi-title-row">
                        <h5>Drilldown API</h5>
                        <button type="button" class="wa-kpi-help wa-kpi-help--light"
                                aria-label="Ver ayuda de Drilldown API">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['drilldown_band'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-link-list mt-10">
                        <a class="btn btn-sm btn-light"
                           href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_lost&{{ $exportQuery }}"
                           target="_blank" rel="noopener">Sin respuesta</a>
                        <a class="btn btn-sm btn-light"
                           href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_attended_human&{{ $exportQuery }}"
                           target="_blank" rel="noopener">Atendidas</a>
                        <a class="btn btn-sm btn-light"
                           href="/v2/whatsapp/api/kpis/drilldown?metric=queue_needs_template&{{ $exportQuery }}"
                           target="_blank" rel="noopener">Plantillas</a>
                        <a class="btn btn-sm btn-light"
                           href="/v2/whatsapp/api/kpis/drilldown?metric=sla_assignments_total&{{ $exportQuery }}"
                           target="_blank" rel="noopener">SLA</a>
                    </div>
                </div>
            </div>

            <div class="col-12">
                {{-- ══ ZONA AHORA ══ --}}
                @php
                    $queueTotal   = (int)($summary['live_queue_total'] ?? 0);
                    $sinRespuesta = (int)($summary['conversations_lost'] ?? 0);
                    $cobertura    = (float)($summary['attention_rate'] ?? 0);
                    $respondidos  = (float)($summary['sla_assignments_rate'] ?? 0);
                    $cerradosSeguimiento = (int)($summary['conversations_closed_followup'] ?? 0);
                    $resueltosReales = (int)($summary['conversations_closed_resolved'] ?? 0);
                    $leadsSeguimiento = (int)($summary['whatsapp_followup_leads_created'] ?? 0);
                    $firstHumanAvg = isset($summary['avg_first_human_response_minutes']) ? $summary['avg_first_human_response_minutes'] . ' min' : '—';
                    $firstHumanMedian = isset($summary['median_first_human_response_minutes']) ? $summary['median_first_human_response_minutes'] . ' min' : '—';

                    $queueClass    = $queueTotal > 10 ? 'alert' : ($queueTotal > 5 ? 'warn' : 'ok');
                    $sinRespClass  = $sinRespuesta > 5 ? 'alert' : ($sinRespuesta > 2 ? 'warn' : 'ok');
                    $coberturaClass = $cobertura < 70 ? 'alert' : ($cobertura < 85 ? 'warn' : 'ok');
                    $slaClass      = $respondidos < 60 ? 'alert' : ($respondidos < 80 ? 'warn' : 'ok');
                @endphp
                <div class="wa-group-label">⚡ En este momento</div>
                <div class="wa-now-zone mb-20">
                    <div class="wa-now-card wa-now-card--{{ $queueClass }}">
                        <div class="wa-now-card__value">{{ $queueTotal }}</div>
                        <div class="wa-now-card__label">En espera ahora</div>
                        @if($queueTotal > 0)
                            <a href="/v2/whatsapp" class="wa-now-card__action">Ver conversaciones →</a>
                        @endif
                    </div>
                    <div class="wa-now-card wa-now-card--{{ $sinRespClass }}">
                        <div class="wa-now-card__value">{{ $sinRespuesta }}</div>
                        <div class="wa-now-card__label">Sin atender en el periodo</div>
                    </div>
                    <div class="wa-now-card wa-now-card--{{ $coberturaClass }}">
                        <div class="wa-now-card__value">{{ $cobertura }}%</div>
                        <div class="wa-now-card__label">De cada 10 que escriben, reciben respuesta</div>
                    </div>
                    <div class="wa-now-card wa-now-card--{{ $slaClass }}">
                        <div class="wa-now-card__value">{{ $respondidos }}%</div>
                        <div class="wa-now-card__label">Respondidos a tiempo ({{ $summary['sla_target_minutes'] ?? 15 }}
                            min)
                        </div>
                    </div>
                    <div class="wa-now-card wa-now-card--ok">
                        <div class="wa-now-card__value">{{ $resueltosReales }}</div>
                        <div class="wa-now-card__label">Resueltos reales</div>
                    </div>
                    <div class="wa-now-card wa-now-card--ok">
                        <div class="wa-now-card__value">{{ $cerradosSeguimiento }}</div>
                        <div class="wa-now-card__label">Cerrados para seguimiento · Leads {{ $leadsSeguimiento }}</div>
                    </div>
                </div>
                <div class="wa-now-zone mb-20">
                    <div class="wa-now-card wa-now-card--ok">
                        <div class="wa-now-card__value">{{ $summary['people_inbound'] ?? 0 }}</div>
                        <div class="wa-now-card__label">Personas que escribieron</div>
                    </div>
                    <div class="wa-now-card wa-now-card--ok">
                        <div class="wa-now-card__value">{{ $firstHumanAvg }}</div>
                        <div class="wa-now-card__label">Tiempo a primera respuesta humana</div>
                        <div class="wa-now-card__label">Desde handoff · mediana {{ $firstHumanMedian }}</div>
                    </div>
                </div>
                {{-- ══ FIN ZONA AHORA ══ --}}
                {{--
                <div class="wa-kpi-grid">
                    @php
                        $slaTargetMinutes = (int) ($summary['sla_target_minutes'] ?? ($filters['sla_target_minutes'] ?? 15));
                        $firstHumanAvg = isset($summary['avg_first_human_response_minutes']) ? $summary['avg_first_human_response_minutes'] . ' min' : '—';
                        $firstHumanMedian = isset($summary['median_first_human_response_minutes']) ? $summary['median_first_human_response_minutes'] . ' min' : '—';
                        $cards = [
                            ['label' => 'Personas que escribieron', 'value' => $summary['people_inbound'] ?? 0, 'sub' => 'Números únicos inbound', 'help' => 'Cantidad de números únicos que enviaron al menos un mensaje inbound en el periodo.'],
                            ['label' => 'Conversaciones atendidas', 'value' => $summary['conversations_attended_human'] ?? 0, 'sub' => ($summary['people_attended_human'] ?? 0) . ' personas atendidas', 'help' => 'Conversaciones que recibieron al menos una respuesta humana.'],
                            ['label' => 'Sin atender por humano', 'value' => $summary['conversations_lost'] ?? 0, 'sub' => ($summary['people_lost'] ?? 0) . ' personas · ' . ($summary['loss_rate'] ?? 0) . '% · ' . ($summary['conversations_lost_with_handoff'] ?? 0) . ' con handoff', 'help' => 'Conversaciones inbound que no registraron respuesta humana. Puede incluir casos con handoff pendiente y casos que nunca llegaron a handoff.'],
                            ['label' => 'Cobertura humana', 'value' => ($summary['attention_rate'] ?? 0) . '%', 'sub' => 'Personas atendidas / personas inbound', 'help' => 'Porcentaje de números únicos inbound que sí recibieron respuesta humana.'],
                            ['label' => 'Tiempo a primera respuesta humana', 'value' => $firstHumanAvg, 'sub' => 'Desde handoff · mediana ' . $firstHumanMedian, 'help' => 'Promedio medido desde la solicitud de ayuda o ingreso a handoff hasta la primera respuesta humana. No usa el primer mensaje del bot como punto de partida.'],
                            ['label' => 'Conversaciones inactivas >24h sin respuesta humana', 'value' => $summary['conversations_abandoned'] ?? 0, 'sub' => ($summary['abandonment_rate'] ?? 0) . '% del inbound único', 'help' => 'Conversaciones sin respuesta humana cuyo último inbound ocurrió hace más de 24 horas. No implica necesariamente falla operativa: puede incluir cierres naturales del paciente como ok, gracias o adiós.'],
                            ['label' => 'Urgente: derivado sin atender >24h', 'value' => $summary['conversations_abandoned_with_handoff'] ?? 0, 'sub' => ($summary['conversations_lost_with_handoff'] ?? 0) . ' sin respuesta humana tras handoff', 'help' => 'Subset realmente accionable para operación: conversaciones que sí pidieron ayuda o cayeron en handoff y siguen sin respuesta humana después de 24 horas.'],
                            ['label' => 'Conversaciones resueltas', 'value' => $summary['conversations_resolved'] ?? 0, 'sub' => 'Sin actividad inbound 24h', 'help' => 'Conversaciones atendidas que no han recibido nuevos inbound en las últimas 24 horas.'],
                            ['label' => 'Resueltos reales', 'value' => $summary['conversations_closed_resolved'] ?? 0, 'sub' => 'Cierre manual como resuelto', 'help' => 'Conversaciones cerradas explícitamente por el agente con motivo Resuelto.'],
                            ['label' => 'Cerrados para seguimiento', 'value' => $summary['conversations_closed_followup'] ?? 0, 'sub' => ($summary['whatsapp_followup_leads_created'] ?? 0) . ' leads WhatsApp generados', 'help' => 'Conversaciones cerradas para seguimiento. No se mezclan con los casos resueltos.'],
                            ['label' => 'No interesados', 'value' => $summary['conversations_closed_not_interested'] ?? 0, 'sub' => 'Cierre estructurado', 'help' => 'Conversaciones cerradas porque el paciente indicó no estar interesado.'],
                            ['label' => 'Sin respuesta', 'value' => $summary['conversations_closed_no_response'] ?? 0, 'sub' => 'Cierre estructurado', 'help' => 'Conversaciones cerradas por falta de respuesta del paciente.'],
                            ['label' => 'Pico simultáneo', 'value' => $summary['peak_open_conversations'] ?? 0, 'sub' => $summary['peak_open_at'] ?? 'Sin dato', 'help' => 'Máximo de conversaciones abiertas al mismo tiempo detectado dentro del rango analizado.'],
                            ['label' => 'Mensajes inbound', 'value' => $summary['messages_inbound'] ?? 0, 'sub' => 'Recibidos', 'help' => 'Total de mensajes recibidos desde pacientes o contactos en el periodo.'],
                            ['label' => 'Mensajes outbound', 'value' => $summary['messages_outbound'] ?? 0, 'sub' => 'Enviados', 'help' => 'Total de mensajes enviados desde el canal, incluyendo bot, humanos y plantillas.'],
                            ['label' => 'Citas desde WhatsApp', 'value' => $summary['sigcenter_bookings_created'] ?? 0, 'sub' => ($summary['sigcenter_booking_patients'] ?? 0) . ' pacientes · ' . ($summary['sigcenter_booking_failures'] ?? 0) . ' fallidas', 'help' => 'Citas creadas exitosamente desde WhatsApp según integración con Sigcenter, junto con pacientes únicos y fallos registrados.'],
                            ['label' => 'Respondidos a tiempo (meta: ' . $slaTargetMinutes . ' min)', 'value' => ($summary['sla_assignments_rate'] ?? 0) . '%', 'sub' => ($summary['sla_assignments_in_target'] ?? 0) . '/' . ($summary['sla_assignments_total'] ?? 0) . ' en meta', 'help' => 'Mide tiempo de asignación interna del handoff, no tiempo de respuesta efectiva al paciente.'],
                            ['label' => 'Conversaciones en atención ahora', 'value' => $summary['live_queue_total'] ?? 0, 'sub' => 'Cola ' . ($summary['live_queue_queued'] ?? 0) . ' · Asignadas ' . ($summary['live_queue_assigned'] ?? 0), 'help' => 'Conversaciones actualmente en circuito humano: pendientes en cola o ya asignadas a un agente.'],
                            ['label' => 'Pueden recibir mensaje libre', 'value' => $summary['queue_window_open'] ?? 0, 'sub' => ($summary['queue_window_open_rate'] ?? 0) . '% del total', 'help' => 'Conversaciones que todavía pueden recibir mensaje libre sin necesidad de plantilla porque su último inbound sigue dentro de 24 horas.'],
                            ['label' => 'Solo pueden reabrirse con plantilla', 'value' => $summary['queue_needs_template'] ?? 0, 'sub' => ($summary['queue_needs_template_rate'] ?? 0) . '% del total', 'help' => 'Conversaciones fuera de la ventana de 24 horas que solo pueden reabrirse con plantilla.'],
                            ['label' => 'Esperando plantilla', 'value' => $summary['queue_awaiting_template_reply'] ?? 0, 'sub' => ($summary['queue_awaiting_template_reply_rate'] ?? 0) . '% fuera de ventana', 'help' => 'Conversaciones fuera de ventana donde ya se envió plantilla y todavía no hay respuesta inbound.'],
                            ['label' => 'Derivaciones entre agentes', 'value' => $summary['handoff_transfers'] ?? 0, 'sub' => 'Entre agentes', 'help' => 'Cambios de ownership entre agentes o equipos dentro del proceso de atención humana.'],
                        ];
                    @endphp
                    @foreach($cards as $card)
                        <div class="wa-kpi-card">
                            <div class="wa-kpi-label-row">
                                <div class="wa-kpi-label">{{ $card['label'] }}</div>
                                @if(!empty($card['help']))
                                    <button type="button" class="wa-kpi-help"
                                            aria-label="Ver ayuda de {{ $card['label'] }}">
                                        ?
                                        <span class="wa-kpi-help__tooltip">{{ $card['help'] }}</span>
                                    </button>
                                @endif
                            </div>
                            <div class="wa-kpi-value">{{ $card['value'] }}</div>
                            <div class="wa-kpi-sub">{{ $card['sub'] }}</div>
                        </div>
                    @endforeach
                </div>
--}}
            </div>

            <div class="col-12">
                <div class="wa-group-label">📣 Recordatorios automáticos</div>
            </div>

            <div class="col-12">
                @php
                    $reminderCards = [
                        ['label' => 'Generados', 'value' => number_format((int) ($reminderSummary['total'] ?? 0)), 'sub' => 'Persistidos en el periodo'],
                        ['label' => 'Enviados', 'value' => number_format((int) ($reminderSummary['sent'] ?? 0)), 'sub' => 'Templates emitidos'],
                        ['label' => 'Entregados', 'value' => number_format((int) ($reminderSummary['delivered'] ?? 0)), 'sub' => ($reminderSummary['delivery_rate'] ?? 0) . '% de entrega'],
                        ['label' => 'Respondidos', 'value' => number_format((int) ($reminderSummary['responded'] ?? 0)), 'sub' => ($reminderSummary['response_rate'] ?? 0) . '% respondió'],
                        ['label' => 'Confirmaron', 'value' => number_format((int) ($reminderSummary['confirmed'] ?? 0)), 'sub' => ($reminderSummary['confirmation_rate'] ?? 0) . '% de respuestas'],
                        ['label' => 'Pidieron agente', 'value' => number_format((int) ($reminderSummary['agent_requested'] ?? 0)), 'sub' => ($reminderSummary['agent_rate'] ?? 0) . '% de respuestas'],
                        ['label' => 'Fallidos', 'value' => number_format((int) ($reminderSummary['failed'] ?? 0)), 'sub' => 'Revisar template o número'],
                        ['label' => 'Pendientes', 'value' => number_format((int) ($reminderSummary['pending'] ?? 0)), 'sub' => 'Aún sin despacho final'],
                    ];
                    $reminderConfigChips = [
                        'Estado' => !empty($reminderConfig['enabled']) ? 'Activo' : 'Inactivo',
                        'Timezone' => $reminderConfig['timezone'] ?? 'America/Guayaquil',
                        'Plantilla servicios' => $reminderConfig['service_template'] ?? '—',
                        'Plantilla imágenes' => $reminderConfig['imaging_template'] ?? '—',
                        'Ventana 24h' => !empty($reminderConfig['window_24h_enabled']) ? (($reminderConfig['window_24h_minutes'] ?? 1440) . ' min') : 'Apagada',
                        'Ventana 2h' => !empty($reminderConfig['window_2h_enabled']) ? (($reminderConfig['window_2h_minutes'] ?? 120) . ' min') : 'Apagada',
                        'Tolerancia' => ($reminderConfig['tolerance_minutes'] ?? 15) . ' min',
                        'Máx. por paciente/día' => (string) ($reminderConfig['max_per_patient_per_day'] ?? 0),
                        'Outbound reciente' => ($reminderConfig['recent_outbound_hours'] ?? 0) . ' h',
                    ];
                @endphp
                <div class="wa-kpi-panel mb-20">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Resumen de recordatorios</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Resumen de recordatorios">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['reminders_summary'] }}</span>
                            </button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Vista operativa de envíos, entrega, respuesta y confirmación para servicios e imágenes.</div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div class="wa-kpi-grid">
                            @foreach($reminderCards as $card)
                                <div class="wa-kpi-card">
                                    <div class="wa-kpi-label">{{ $card['label'] }}</div>
                                    <div class="wa-kpi-value">{{ $card['value'] }}</div>
                                    <div class="wa-kpi-sub">{{ $card['sub'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="wa-chart-chips mt-14">
                            @foreach($reminderConfigChips as $label => $value)
                                <div class="wa-chart-chip">
                                    <div class="wa-chart-chip__val" style="font-size:12px;">{{ $value }}</div>
                                    <div class="wa-chart-chip__lbl">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Mix por tipo y ventana</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Mix por tipo y ventana">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['reminders_mix'] }}</span>
                            </button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Separación entre servicios oftalmológicos generales e imágenes, y entre ventanas 24h y 2h.</div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div class="table-responsive">
                            <table class="table table-sm wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Ventana</th>
                                    <th>Total</th>
                                    <th>Entregados</th>
                                    <th>Respondidos</th>
                                    <th>Confirmó</th>
                                    <th>Agente</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($reminderBySourceWindow as $row)
                                    <tr>
                                        <td>{{ $row['source_label'] ?? '—' }}</td>
                                        <td>{{ $row['window_label'] ?? '—' }}</td>
                                        <td>{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                        <td>{{ number_format((int) ($row['delivered'] ?? 0)) }}</td>
                                        <td>{{ number_format((int) ($row['responded'] ?? 0)) }} <span class="text-muted">({{ $row['response_rate'] ?? 0 }}%)</span></td>
                                        <td>{{ number_format((int) ($row['confirmed'] ?? 0)) }}</td>
                                        <td>{{ number_format((int) ($row['agent_requested'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-muted">No hay recordatorios persistidos en el rango seleccionado.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Últimos recordatorios</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Últimos recordatorios">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['reminders_recent'] }}</span>
                            </button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Auditoría rápida de los últimos envíos con paciente, estado y acción tomada.</div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div class="table-responsive">
                            <table class="table table-sm wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Paciente</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Respuesta</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($reminderRecent as $row)
                                    <tr>
                                        <td>
                                            <div>{{ $formatReminderDate($row['event_at'] ?? null) }}</div>
                                            <div class="text-muted" style="font-size:12px;">{{ $row['window_label'] ?? '—' }} · #{{ $row['form_id'] ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $row['patient_name'] ?: 'Sin nombre' }}</div>
                                            <div class="text-muted" style="font-size:12px;">HC {{ $row['hc_number'] ?: '—' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $row['source_label'] ?? '—' }}</div>
                                            <div class="text-muted" style="font-size:12px;">{{ $row['template_code'] ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $row['status_label'] ?? '—' }}</div>
                                            <div class="text-muted" style="font-size:12px;">
                                                @if(!empty($row['responded_at']))
                                                    {{ $formatReminderDate($row['responded_at']) }}
                                                @elseif(!empty($row['sent_at']))
                                                    {{ $formatReminderDate($row['sent_at']) }}
                                                @else
                                                    {{ $formatReminderDate($row['created_at'] ?? null) }}
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $row['response_label'] ?? 'Sin respuesta' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted">No hay actividad de recordatorios en el rango actual.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── TENDENCIAS DEL CANAL ─────────────────────────────────────── --}}
            <div class="col-12">
                <div class="wa-group-label">📊 Tendencias del canal</div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Serie diaria del periodo</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Series del periodo">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['series'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#eff6ff;color:#2563eb;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto">📈 Chart puro</span>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Evolución diaria de conversaciones nuevas, handoffs y
                            citas creadas.
                        </div>
                    </div>
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
                            Inbound {{ $summary['messages_inbound'] ?? 0 }} ·
                            Outbound {{ $summary['messages_outbound'] ?? 0 }} ·
                            Citas {{ $summary['sigcenter_bookings_created'] ?? 0 }} ·
                            Derivaciones {{ $summary['handoff_transfers'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Origen de demanda</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Origen de demanda">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['source_demand'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#eff6ff;color:#2563eb;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto">🍩 Chart puro</span>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Cómo se reparte la entrada del canal entre Ads, orgánico y
                            conversaciones iniciadas desde el equipo.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-origen-demanda" class="wa-chart-wrap"></div>
                    </div>
                </div>
            </div>

            {{-- ── ANÁLISIS DE CONVERSACIONES ───────────────────────────────── --}}
            <div class="col-12">
                <div class="wa-group-label">🔍 Análisis de conversaciones</div>
            </div>


            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="var t=document.getElementById('chart-embudo-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Embudo conversacional y comercial</div>
                            <button type="button" class="wa-kpi-help"
                                    aria-label="Ver ayuda de Embudo conversacional y comercial"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['funnel'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">🔻 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Avance de las conversaciones nuevas desde el inicio hasta
                            la creación efectiva de cita.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-embudo" class="wa-chart-wrap" style="height:260px"></div>
                    </div>
                    <div class="wa-kpi-panel__body" id="chart-embudo-table" style="display:none">
                        <div class="wa-kpi-grid">
                            @forelse($analyticsFunnel as $step)
                                <div class="wa-kpi-card">
                                    <div class="wa-kpi-label">{{ $step['label'] }}</div>
                                    <div class="wa-kpi-value">{{ $step['value'] }}</div>
                                    <div class="wa-kpi-sub">Desde inicio {{ $step['rate_from_start'] }}% ·
                                        Paso {{ $step['rate_to_next'] }}%
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Sin datos para el rango actual.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            {{-- chart-embudo rendered via @push('scripts') after ApexCharts CDN --}}

            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer"
                         onclick="var t=document.getElementById('chart-intencion-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Intención inicial</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Intención inicial"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['initial_intent'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📊 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Clasificación del primer mensaje útil de cada conversación
                            nueva.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-intencion" class="wa-chart-wrap" style="height:200px"></div>
                    </div>
                    <div class="wa-kpi-panel__body p-0" id="chart-intencion-table" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Intención</th>
                                    <th>Total</th>
                                    <th>Participación</th>
                                    <th>Citas</th>
                                    <th>Handoffs</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsIntents as $row)
                                    <tr>
                                        <td>{{ $row['intent_label'] }}</td>
                                        <td>{{ $row['total'] }}</td>
                                        <td>{{ $row['share'] }}%</td>
                                        <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                        </td>
                                        <td>{{ $row['handoffs'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer"
                         onclick="var t=document.getElementById('chart-tipo-conv-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Tipo de conversación</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Tipo de conversación"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['conversation_type'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📊 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Clasificación operativa/comercial del contacto una vez
                            interpretado el contexto de entrada.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-tipo-conv" class="wa-chart-wrap" style="height:200px"></div>
                    </div>
                    <div class="wa-kpi-panel__body p-0" id="chart-tipo-conv-table" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Total</th>
                                    <th>Participación</th>
                                    <th>Citas</th>
                                    <th>Handoffs</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsConversationTypes as $row)
                                    <tr>
                                        <td>{{ $row['type_label'] }}</td>
                                        <td>{{ $row['total'] }}</td>
                                        <td>{{ $row['share'] }}%</td>
                                        <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                        </td>
                                        <td>{{ $row['handoffs'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer"
                         onclick="var t=document.getElementById('chart-segmento-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Segmento del paciente</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Segmento del paciente"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['patient_segment'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📊 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Distribución entre paciente nuevo, recurrente y reactivado
                            en las conversaciones nuevas.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-segmento" class="wa-chart-wrap" style="height:220px"></div>
                    </div>
                    <div class="wa-kpi-panel__body p-0" id="chart-segmento-table" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Segmento</th>
                                    <th>Total</th>
                                    <th>Participación</th>
                                    <th>Identificadas</th>
                                    <th>Citas</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsSegments as $row)
                                    <tr>
                                        <td>{{ $row['segment_label'] }}</td>
                                        <td>{{ $row['total'] }}</td>
                                        <td>{{ $row['share'] }}%</td>
                                        <td>{{ $row['identified'] }}</td>
                                        <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="var t=document.getElementById('chart-lead-scoring-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Lead scoring</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Lead scoring"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['lead_scoring'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📊 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Prioridad comercial estimada por progreso, identificación
                            y cierre efectivo.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-lead-scoring" class="wa-chart-wrap" style="height:180px"></div>
                    </div>
                    <div class="wa-kpi-panel__body p-0" id="chart-lead-scoring-table" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Bucket</th>
                                    <th>Total</th>
                                    <th>Participación</th>
                                    <th>Score promedio</th>
                                    <th>Citas</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsLeadScores as $row)
                                    <tr>
                                        <td>{{ $row['bucket_label'] }}</td>
                                        <td>{{ $row['total'] }}</td>
                                        <td>{{ $row['share'] }}%</td>
                                        <td>{{ $row['avg_score'] }}</td>
                                        <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {{-- chart-lead-scoring rendered via @push('scripts') after ApexCharts CDN --}}

            <div class="col-xl-4 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="var t=document.getElementById('chart-fricciones-table');t.style.display=t.style.display==='none'?'block':'none';this.querySelector('.wa-section-toggle').textContent=t.style.display==='none'?'▼':'▲'">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Fricciones del flujo</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Fricciones del flujo"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['frictions'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#fff7ed;color:#c2410c;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">⚠️ Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Estados donde más se frenan conversaciones sin cierre
                            efectivo.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div id="chart-fricciones" class="wa-chart-wrap" style="height:180px"></div>
                    </div>
                    <div class="wa-kpi-panel__body p-0" id="chart-fricciones-table" style="display:none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Fricción</th>
                                    <th>Total</th>
                                    <th>Participación</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsFrictions as $row)
                                    <tr>
                                        <td>{{ $row['friction_label'] }}</td>
                                        <td>{{ $row['total'] }}</td>
                                        <td>{{ $row['share'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-20">Sin fricciones relevantes
                                            en el rango actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {{-- chart-fricciones rendered via @push('scripts') after ApexCharts CDN --}}

            <div class="col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Insights automáticos</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Insights automáticos">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['insights'] }}</span>
                            </button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Lectura resumida para gerencia sobre origen, intención,
                            calidad y fricción del canal.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body">
                        <div class="wa-kpi-grid">
                            @forelse($analyticsInsights as $insight)
                                <div class="wa-kpi-card">
                                    <div class="wa-kpi-label">{{ $insight['title'] ?? 'Insight' }}</div>
                                    <div class="wa-kpi-sub"
                                         style="margin-top:.85rem; font-size:.92rem; color:#334155;">{{ $insight['body'] ?? '' }}</div>
                                </div>
                            @empty
                                <div class="text-muted">Sin insights para el rango actual.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="this.nextElementSibling.nextElementSibling.classList.toggle('d-none')">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Top Ads por citas</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Top Ads por citas"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['ads'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#f0fdf4;color:#166534;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📊 Chart + tabla</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Ranking inicial de anuncios que más conversaciones y citas
                            aportan al canal.
                        </div>
                    </div>
                    {{-- Chart de Ads (nuevo) --}}
                    <div class="wa-kpi-panel__body">
                        <div id="chart-ads" class="wa-chart-wrap" style="height:220px"></div>
                    </div>
                    {{-- Tabla detalle (ya existente, mantener el d-none) --}}
                    <div class="wa-kpi-panel__body p-0 d-none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
                                <thead>
                                <tr>
                                    <th>Anuncio</th>
                                    <th>Red social</th>
                                    <th>Tipo de pieza</th>
                                    <th>Conversaciones</th>
                                    <th>Identificadas</th>
                                    <th>Citas</th>
                                    <th>Handoffs</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($analyticsAds as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-600">{{ $row['headline'] }}</div>
                                            <div class="text-muted small">{{ $row['source_id'] ?? 'Sin ID' }}</div>
                                        </td>
                                        <td>
                                            @php
                                                $platformIcons = ['facebook' => '📘', 'instagram' => '📷', 'whatsapp' => '💬'];
                                                $icon = $platformIcons[$row['platform'] ?? ''] ?? '❓';
                                            @endphp
                                            {{ $icon }} {{ $row['platform_label'] ?? 'Desconocido' }}
                                        </td>
                                        <td>{{ $row['media_type'] }}</td>
                                        <td>{{ $row['conversations'] }}</td>
                                        <td>{{ $row['identified'] }}</td>
                                        <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                        </td>
                                        <td>{{ $row['handoffs'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-20">No hubo conversaciones
                                            atribuibles a Ads en el rango actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── OPERACIÓN HUMANA ─────────────────────────────────────────── --}}
            <div class="col-12">
                <div class="wa-group-label">👥 Operación humana</div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Primera respuesta por cola</div>
                            <button type="button" class="wa-kpi-help"
                                    aria-label="Ver ayuda de Primera respuesta por cola">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['human_by_queue'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#faf5ff;color:#6d28d9;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto">📋 Tabla mejorada</span>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Mide el tiempo desde ingreso a handoff hasta la primera
                            respuesta humana por tipo de cola.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
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
                                                <div class="wa-prog-bg">
                                                    <div class="wa-prog-fill wa-prog-fill--{{ $color }}"
                                                         style="width:{{ $pct }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="this.nextElementSibling.classList.toggle('d-none')">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Handoffs por equipo</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Handoffs por equipo"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['handoffs_by_role'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#faf5ff;color:#6d28d9;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📋 Tabla mejorada</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Distribución de cola, asignación y resolución por equipo
                            operativo.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body p-0 d-none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
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
                                        $pctR   = $row['total'] > 0 ? (int)round(($row['resolved'] / $row['total']) * 100) : 0;
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
                                                <div class="wa-prog-bg">
                                                    <div class="wa-prog-fill wa-prog-fill--{{ $colorR }}"
                                                         style="width:{{ $pctR }}%"></div>
                                                </div>
                                                <span class="wa-prog-val wa-prog-val--{{ $colorR }}">{{ $pctR }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="this.nextElementSibling.classList.toggle('d-none')">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Atención humana por agente</div>
                            <button type="button" class="wa-kpi-help"
                                    aria-label="Ver ayuda de Atención humana por agente"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['human_by_agent'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#faf5ff;color:#6d28d9;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📋 Tabla mejorada</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Quién absorbió más conversaciones y cómo respondió en
                            primera intervención.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body p-0 d-none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
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
                                        $mins  = $row['avg_first_response_minutes'];
                                        $pct   = $mins !== null ? min(100, (int)round(($mins / ($slaMeta * 2)) * 100)) : 0;
                                        $color = $mins === null ? 'green' : ($mins > $slaMeta * 2 ? 'red' : ($mins > $slaMeta ? 'yellow' : 'green'));
                                        $badge = $mins === null ? '—' : ($mins > $slaMeta * 2 ? '✗ Alto' : ($mins > $slaMeta ? '~ OK' : '✓ OK'));
                                    @endphp
                                    <tr>
                                        <td>{{ $row['agent_name'] }}</td>
                                        <td>{{ $row['attended_conversations'] }}</td>
                                        <td class="wa-prog-val--{{ $color }}">{{ $mins !== null ? $mins . ' min' : '—' }}</td>
                                        <td>
                                            <div class="wa-prog-wrap">
                                                <div class="wa-prog-bg">
                                                    <div class="wa-prog-fill wa-prog-fill--{{ $color }}"
                                                         style="width:{{ $pct }}%"></div>
                                                </div>
                                                <span class="wa-prog-val wa-prog-val--{{ $color }}">{{ $badge }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-12">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer;"
                         onclick="this.nextElementSibling.classList.toggle('d-none')">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">Carga por agente</div>
                            <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Carga por agente"
                                    onclick="event.stopPropagation()">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['agent_load'] }}</span>
                            </button>
                            <span
                                style="font-size:10px;background:#faf5ff;color:#6d28d9;border-radius:4px;padding:2px 7px;font-weight:600;margin-left:auto;margin-right:6px">📋 Tabla mejorada</span>
                            <button type="button" class="wa-section-toggle">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Lectura de workload para saber quién está tomado, activo o
                            ya resolvió.
                        </div>
                    </div>
                    <div class="wa-kpi-panel__body p-0 d-none">
                        <div class="table-responsive">
                            <table class="table table-striped wa-kpi-table mb-0">
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
                                    $agentRows = $breakdowns['handoffs_by_agent'] ?? [];
                                    $maxAssigned = count($agentRows) > 0 ? max(1, ...array_map(fn($r) => (int)($r['assigned_count'] ?? 0), $agentRows)) : 1;
                                @endphp
                                @forelse(($breakdowns['handoffs_by_agent'] ?? []) as $row)
                                    @php
                                        $loadPct = (int)round(($row['assigned_count'] / $maxAssigned) * 100);
                                        $colorL  = $loadPct >= 90 ? 'red' : ($loadPct >= 70 ? 'yellow' : 'green');
                                    @endphp
                                    <tr>
                                        <td>{{ $row['agent_name'] }}</td>
                                        <td>{{ $row['assigned_count'] }}</td>
                                        <td>{{ $row['active_count'] }}</td>
                                        <td>{{ $row['resolved_count'] }}</td>
                                        <td>
                                            <div class="wa-prog-wrap">
                                                <div class="wa-prog-bg">
                                                    <div class="wa-prog-fill wa-prog-fill--{{ $colorL }}"
                                                         style="width:{{ $loadPct }}%"></div>
                                                </div>
                                                <span
                                                    class="wa-prog-val wa-prog-val--{{ $colorL }}">{{ $loadPct }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango
                                            actual.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {{-- ── ANÁLISIS GERENCIAL COMPLEMENTARIO ──────────────────────────── --}}
            <div class="col-12">
                <div class="wa-group-label" style="cursor:pointer;user-select:none"
                     onclick="var b=document.getElementById('wa-gerencial-body');var a=this.querySelector('.wa-gerencial-arrow');b.style.display=b.style.display==='none'?'contents':'none';a.textContent=b.style.display==='none'?'▼':'▲'">
                    📋 Análisis gerencial complementario
                    <span class="wa-gerencial-arrow" style="float:right;font-size:13px;color:#94a3b8">▼</span>
                </div>
            </div>
            <div id="wa-gerencial-body" style="display:none">

                <div class="col-12">
                    <div class="wa-kpi-panel">
                        <div class="wa-kpi-panel__head">
                            <div class="wa-kpi-title-row">
                                <div class="wa-kpi-sideheading__title">Vista ejecutiva del canal</div>
                                <button type="button" class="wa-kpi-help"
                                        aria-label="Ver ayuda de Vista ejecutiva del canal">
                                    ?
                                    <span class="wa-kpi-help__tooltip">{{ $sectionHelp['executive_view'] }}</span>
                                </button>
                            </div>
                            <div class="wa-kpi-sideheading__meta">Separación macro entre captación, operación,
                                seguimiento clínico y reactivación.
                            </div>
                        </div>
                        <div class="wa-kpi-panel__body">
                            <div class="wa-kpi-grid">
                                @php
                                    $executiveCards = [
                                        ['label' => 'Captación', 'value' => $analyticsSummary['captacion_conversations'] ?? 0, 'sub' => 'Demanda nueva y entrada comercial', 'help' => 'Conversaciones nuevas orientadas a adquisición o primera entrada comercial al canal.'],
                                        ['label' => 'Operación', 'value' => $analyticsSummary['operacion_conversations'] ?? 0, 'sub' => 'Cambios, soporte y gestión operativa', 'help' => 'Conversaciones centradas en cambios de cita, soporte, campañas reactivas y gestión operativa.'],
                                        ['label' => 'Seguimiento clínico', 'value' => $analyticsSummary['seguimiento_clinico_conversations'] ?? 0, 'sub' => 'Post consulta y post cirugía', 'help' => 'Conversaciones asociadas a continuidad clínica, seguimiento post consulta o post cirugía.'],
                                        ['label' => 'Reactivación', 'value' => $analyticsSummary['reactivacion_conversations'] ?? 0, 'sub' => 'Pacientes que vuelven al canal', 'help' => 'Pacientes reactivados o que regresan tras un periodo sin interacción relevante.'],
                                    ];
                                @endphp
                                @foreach($executiveCards as $card)
                                    <div class="wa-kpi-card">
                                        <div class="wa-kpi-label-row">
                                            <div class="wa-kpi-label">{{ $card['label'] }}</div>
                                            @if(!empty($card['help']))
                                                <button type="button" class="wa-kpi-help"
                                                        aria-label="Ver ayuda de {{ $card['label'] }}">
                                                    ?
                                                    <span class="wa-kpi-help__tooltip">{{ $card['help'] }}</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="wa-kpi-value">{{ $card['value'] }}</div>
                                        <div class="wa-kpi-sub">{{ $card['sub'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="wa-kpi-panel">
                        <div class="wa-kpi-panel__head">
                            <div class="wa-kpi-title-row">
                                <div class="wa-kpi-sideheading__title">Mix ejecutivo del canal</div>
                                <button type="button" class="wa-kpi-help"
                                        aria-label="Ver ayuda de Mix ejecutivo del canal">
                                    ?
                                    <span class="wa-kpi-help__tooltip">{{ $sectionHelp['executive_mix'] }}</span>
                                </button>
                            </div>
                            <div class="wa-kpi-sideheading__meta">Comparativo entre las cuatro líneas principales del
                                WhatsApp por volumen, booking y dependencia de humano.
                            </div>
                        </div>
                        <div class="wa-kpi-panel__body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped wa-kpi-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Total</th>
                                        <th>Participación</th>
                                        <th>Identificadas</th>
                                        <th>Citas</th>
                                        <th>Handoffs</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($analyticsLifecycle as $row)
                                        <tr>
                                            <td>{{ $row['lifecycle_label'] }}</td>
                                            <td>{{ $row['total'] }}</td>
                                            <td>{{ $row['share'] }}%</td>
                                            <td>{{ $row['identified'] }}</td>
                                            <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span>
                                            </td>
                                            <td>{{ $row['handoffs'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-20">Sin datos para el rango
                                                actual.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="wa-kpi-panel">
                        <div class="wa-kpi-panel__head">
                            <div class="wa-kpi-title-row">
                                <div class="wa-kpi-sideheading__title">Captación y conversión del canal</div>
                                <button type="button" class="wa-kpi-help"
                                        aria-label="Ver ayuda de Captación y conversión del canal">
                                    ?
                                    <span class="wa-kpi-help__tooltip">{{ $sectionHelp['channel_capture'] }}</span>
                                </button>
                            </div>
                            <div class="wa-kpi-sideheading__meta">Resumen gerencial de conversaciones nuevas: origen,
                                identificación, handoff y conversión a cita.
                            </div>
                        </div>
                        <div class="wa-kpi-panel__body">
                            <div class="wa-kpi-grid">
                                @php
                                    $commercialCards = [
                                        ['label' => 'Conversaciones nuevas', 'value' => $analyticsSummary['total_conversations'] ?? 0, 'sub' => 'Base analítica del periodo'],
                                        ['label' => 'Desde Ads', 'value' => $analyticsSummary['conversations_from_ads'] ?? 0, 'sub' => 'Orgánico ' . ($analyticsSummary['conversations_organic'] ?? 0)],
                                        ['label' => 'Iniciadas por equipo', 'value' => $analyticsSummary['conversations_outbound_started'] ?? 0, 'sub' => 'Seguimientos o arranque manual'],
                                        ['label' => 'Pacientes nuevos', 'value' => $analyticsSummary['new_patients'] ?? 0, 'sub' => 'Recurrentes ' . ($analyticsSummary['returning_patients'] ?? 0)],
                                        ['label' => 'Pacientes reactivados', 'value' => $analyticsSummary['reactivated_patients'] ?? 0, 'sub' => 'Más de 180 días sin toque clínico'],
                                        ['label' => 'Lead score promedio', 'value' => $analyticsSummary['avg_lead_score'] ?? 0, 'sub' => 'Alto valor ' . ($analyticsSummary['high_value_leads'] ?? 0)],
                                        ['label' => 'Identificadas', 'value' => ($analyticsSummary['identification_rate'] ?? 0) . '%', 'sub' => ($analyticsSummary['identified_conversations'] ?? 0) . ' conversaciones'],
                                        ['label' => 'Con cita creada', 'value' => ($analyticsSummary['booking_rate'] ?? 0) . '%', 'sub' => ($analyticsSummary['booked_conversations'] ?? 0) . ' conversaciones'],
                                        ['label' => 'Con handoff humano', 'value' => ($analyticsSummary['handoff_rate'] ?? 0) . '%', 'sub' => ($analyticsSummary['handoff_conversations'] ?? 0) . ' conversaciones'],
                                    ];
                                @endphp
                                @foreach($commercialCards as $card)
                                    <div class="wa-kpi-card">
                                        <div class="wa-kpi-label">{{ $card['label'] }}</div>
                                        <div class="wa-kpi-value">{{ $card['value'] }}</div>
                                        <div class="wa-kpi-sub">{{ $card['sub'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="wa-kpi-panel">
                        <div class="wa-kpi-panel__head">
                            <div class="wa-kpi-title-row">
                                <div class="wa-kpi-sideheading__title">Outcome de conversaciones</div>
                                <button type="button" class="wa-kpi-help"
                                        aria-label="Ver ayuda de Outcome de conversaciones">
                                    ?
                                    <span
                                        class="wa-kpi-help__tooltip">{{ $sectionHelp['conversation_outcomes'] }}</span>
                                </button>
                            </div>
                            <div class="wa-kpi-sideheading__meta">Resultado final más relevante para cada conversación
                                nueva del periodo.
                            </div>
                        </div>
                        <div class="wa-kpi-panel__body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped wa-kpi-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Resultado</th>
                                        <th>Total</th>
                                        <th>Participación</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($analyticsOutcomes as $row)
                                        <tr>
                                            <td>{{ $row['outcome_label'] }}</td>
                                            <td>{{ $row['total'] }}</td>
                                            <td>{{ $row['share'] }}%</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-20">Sin datos para el rango
                                                actual.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /wa-gerencial-body --}}

            {{-- ── Resumen ejecutivo para gerencia ────────────────────────────── --}}
            <div id="exec-summary" class="col-12 mt-3">
                <div class="wa-kpi-panel">
                    <div class="wa-kpi-panel__head" style="cursor:pointer"
                         onclick="document.getElementById('exec-summary-body').classList.toggle('d-none')">
                        <div class="wa-kpi-title-row">
                            <div class="wa-kpi-sideheading__title">📋 Resumen ejecutivo del periodo</div>
                            <button type="button" class="wa-section-toggle ms-auto">▼</button>
                        </div>
                        <div class="wa-kpi-sideheading__meta">Lectura consolidada para gerencia — origen, intención, SLA
                            y fricción.
                        </div>
                    </div>
                    <div id="exec-summary-body" class="wa-kpi-panel__body d-none">
                        <p style="font-size:13px;color:#475569;line-height:1.75;margin-bottom:16px">
                            El canal recibió <strong>{{ number_format($totalConvs) }} conversaciones nuevas</strong> en
                            el periodo.
                            @if($topSource)
                                La principal fuente fue <strong>{{ $topSource['source_label'] }}
                                    ({{ $topSource['share'] }}%)</strong>.
                            @endif
                            @if($topIntent)
                                La intención dominante fue <strong>{{ $topIntent['intent_label'] }}
                                    ({{ $topIntent['share'] }}%)</strong>.
                            @endif
                            @if($frictionHighShare)
                                Se detectó <strong>fricción significativa</strong> en
                                "{{ $topFriction['friction_label'] }}" ({{ $topFriction['share'] }}% de conversaciones).
                            @endif
                        </p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            @if($frictionHighShare)
                                <div
                                    style="background:#fef2f2;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                                    <span style="font-size:18px">🔴</span>
                                    <div><strong style="font-size:12px;color:#dc2626">Acción recomendada</strong>
                                        <div style="font-size:11px;color:#64748b;margin-top:3px">Revisar
                                            "{{ $topFriction['friction_label'] }}" —
                                            representa {{ $topFriction['share'] }}% de las fricciones
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if($topAd)
                                <div
                                    style="background:#f0fdf4;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                                    <span style="font-size:18px">🟢</span>
                                    <div><strong style="font-size:12px;color:#166534">Mejor anuncio</strong>
                                        <div
                                            style="font-size:11px;color:#64748b;margin-top:3px">{{ $topAd['headline'] }}
                                            — {{ $topAd['bookings'] }} citas ({{ $topAd['platform_label'] ?? '' }})
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div
                                style="background:#fffbeb;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                                <span style="font-size:18px">🟡</span>
                                <div><strong style="font-size:12px;color:#d97706">SLA del periodo</strong>
                                    <div
                                        style="font-size:11px;color:#64748b;margin-top:3px">{{ $summary['sla_assignments_rate'] ?? 0 }}
                                        % respondidos dentro de meta de {{ $slaMeta }} min
                                    </div>
                                </div>
                            </div>
                            @if($topSegment)
                                <div
                                    style="background:#eff6ff;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start">
                                    <span style="font-size:18px">🔵</span>
                                    <div><strong style="font-size:12px;color:#2563eb">Segmento dominante</strong>
                                        <div
                                            style="font-size:11px;color:#64748b;margin-top:3px">{{ $topSegment['segment_label'] }}
                                            — {{ $topSegment['share'] }}% de las conversaciones
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Guía de uso interactiva --}}
    @include('whatsapp.partials.dashboard-guide')

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js"></script>
        <script>
            (function () {
                var el = document.getElementById('chart-serie-diaria');
                if (!el) return;
                var labels = @json($trends['labels'] ?? []);
                var convs = @json(array_values($trends['conversations'] ?? []));
                var handoffs = @json(array_values($trends['handoff_transfers'] ?? []));
                var bookings = @json(array_values($trends['sigcenter_bookings'] ?? []));
                if (!labels.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {
                        type: 'area',
                        height: 220,
                        toolbar: {show: false},
                        zoom: {enabled: false},
                        fontFamily: 'inherit'
                    },
                    series: [
                        {name: 'Nuevas', data: convs},
                        {name: 'Con handoff', data: handoffs},
                        {name: 'Con cita', data: bookings},
                    ],
                    colors: ['#3b82f6', '#10b981', '#f59e0b'],
                    fill: {type: 'gradient', gradient: {opacityFrom: 0.35, opacityTo: 0.02}},
                    stroke: {curve: 'smooth', width: [2.5, 2, 1.5]},
                    xaxis: {
                        categories: labels,
                        labels: {rotate: -30, style: {fontSize: '10px'}},
                        tickAmount: Math.min(labels.length, 10)
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    tooltip: {shared: true, intersect: false},
                    legend: {position: 'top', fontSize: '12px'},
                    grid: {borderColor: '#f1f5f9'},
                    dataLabels: {enabled: false},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-origen-demanda');
                if (!el) return;
                var rows = @json($analyticsSources);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                var labels = rows.map(function (r) {
                    return r.source_label;
                });
                var series = rows.map(function (r) {
                    return parseInt(r.total) || 0;
                });
                new ApexCharts(el, {
                    chart: {type: 'donut', height: 260, toolbar: {show: false}, fontFamily: 'inherit'},
                    series: series,
                    labels: labels,
                    colors: ['#3b82f6', '#e879f9', '#10b981', '#f59e0b', '#6366f1', '#94a3b8'],
                    legend: {position: 'right', fontSize: '12px'},
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '60%',
                                labels: {show: true, total: {show: true, label: 'Total', fontSize: '12px'}}
                            }
                        }
                    },
                    dataLabels: {
                        enabled: true, formatter: function (val) {
                            return Math.round(val) + '%';
                        }, style: {fontSize: '11px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                return val + ' conv. (' + (rows[opts.seriesIndex] ? rows[opts.seriesIndex].booking_rate : 0) + '% cita)';
                            }
                        }
                    },
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-intencion');
                if (!el) return;
                var rows = @json($analyticsIntents);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 200, toolbar: {show: false}, fontFamily: 'inherit'},
                    plotOptions: {bar: {horizontal: true, distributed: true, barHeight: '65%', borderRadius: 4}},
                    series: [{
                        name: 'Conversaciones', data: rows.map(function (r) {
                            return parseInt(r.total) || 0;
                        })
                    }],
                    xaxis: {
                        categories: rows.map(function (r) {
                            return r.intent_label;
                        }), labels: {style: {fontSize: '11px'}}
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    colors: ['#3b82f6', '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#e879f9', '#94a3b8'],
                    legend: {show: false},
                    dataLabels: {
                        enabled: true, formatter: function (val) {
                            return val;
                        }, style: {fontSize: '10px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                return val + ' conv. (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].share : 0) + '%)';
                            }
                        }
                    },
                    grid: {borderColor: '#f1f5f9'},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-tipo-conv');
                if (!el) return;
                var rows = @json($analyticsConversationTypes);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 200, toolbar: {show: false}, fontFamily: 'inherit'},
                    plotOptions: {bar: {horizontal: true, distributed: true, barHeight: '65%', borderRadius: 4}},
                    series: [{
                        name: 'Conversaciones', data: rows.map(function (r) {
                            return parseInt(r.total) || 0;
                        })
                    }],
                    xaxis: {
                        categories: rows.map(function (r) {
                            return r.type_label;
                        }), labels: {style: {fontSize: '11px'}}
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    colors: ['#6366f1', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
                    legend: {show: false},
                    dataLabels: {
                        enabled: true, formatter: function (val) {
                            return val;
                        }, style: {fontSize: '10px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                return val + ' (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].share : 0) + '%)';
                            }
                        }
                    },
                    grid: {borderColor: '#f1f5f9'},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-segmento');
                if (!el) return;
                var rows = @json($analyticsSegments);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {type: 'donut', height: 220, toolbar: {show: false}, fontFamily: 'inherit'},
                    series: rows.map(function (r) {
                        return parseInt(r.total) || 0;
                    }),
                    labels: rows.map(function (r) {
                        return r.segment_label;
                    }),
                    colors: ['#6366f1', '#f59e0b', '#10b981', '#3b82f6'],
                    legend: {position: 'bottom', fontSize: '11px'},
                    plotOptions: {pie: {donut: {size: '55%'}}},
                    dataLabels: {
                        enabled: true, formatter: function (val) {
                            return Math.round(val) + '%';
                        }, style: {fontSize: '10px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val) {
                                return val + ' conversaciones';
                            }
                        }
                    },
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-ads');
                if (!el) return;

                var rows = @json($analyticsAds);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin conversaciones atribuibles a Ads en el rango actual</div>';
                    return;
                }

                rows = rows.slice();

                var totalBookings = rows.reduce(function (total, row) {
                    return total + (parseInt(row.bookings) || 0);
                }, 0);

                var metricKey = totalBookings > 0 ? 'bookings' : 'conversations';
                var metricLabel = totalBookings > 0 ? 'Citas' : 'Conversaciones';
                var metricSuffix = totalBookings > 0 ? ' citas' : ' conversaciones';

                rows.sort(function (a, b) {
                    return (parseInt(b[metricKey]) || 0) - (parseInt(a[metricKey]) || 0);
                });

                var platformIcons = {facebook: '📘', instagram: '📷', whatsapp: '💬', multiple: '📣'};
                var labels = rows.map(function (r) {
                    var icon = platformIcons[r.platform] || '📣';
                    var headline = r.headline || 'Sin nombre';
                    return icon + ' ' + headline.substring(0, 32) + (headline.length > 32 ? '…' : '');
                });

                var data = rows.map(function (r) {
                    return parseInt(r[metricKey]) || 0;
                });

                new ApexCharts(el, {
                    chart: {
                        type: 'bar',
                        height: 220,
                        toolbar: {show: false},
                        fontFamily: 'inherit'
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            distributed: true,
                            barHeight: '60%',
                            borderRadius: 4
                        }
                    },
                    series: [{
                        name: metricLabel,
                        data: data
                    }],
                    xaxis: {
                        categories: labels,
                        min: 0,
                        labels: {style: {fontSize: '10px'}}
                    },
                    yaxis: {
                        labels: {style: {fontSize: '10px'}, maxWidth: 180}
                    },
                    colors: rows.map(function (r) {
                        if (r.platform === 'instagram') return '#e879f9';
                        if (r.platform === 'facebook') return '#3b82f6';
                        if (r.platform === 'whatsapp') return '#10b981';
                        return '#10b981';
                    }),
                    legend: {show: false},
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) {
                            return Number(val || 0).toLocaleString() + metricSuffix;
                        },
                        style: {fontSize: '10px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                var r = rows[opts.dataPointIndex];
                                if (!r) return Number(val || 0).toLocaleString() + metricSuffix;
                                return Number(val || 0).toLocaleString() + metricSuffix + ' · ' + (parseInt(r.bookings) || 0) + ' citas · ' + (parseInt(r.conversations) || 0) + ' conv.';
                            }
                        }
                    },
                    grid: {borderColor: '#f1f5f9'},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-lead-scoring');
                if (!el) return;
                var rows = @json($analyticsLeadScores);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 180, toolbar: {show: false}, fontFamily: 'inherit'},
                    plotOptions: {bar: {horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4}},
                    series: [{
                        name: 'Conversaciones', data: rows.map(function (r) {
                            return parseInt(r.total) || 0;
                        })
                    }],
                    xaxis: {
                        categories: rows.map(function (r) {
                            return r.bucket_label;
                        }), labels: {style: {fontSize: '11px'}}
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    legend: {show: false},
                    dataLabels: {enabled: true, style: {fontSize: '10px'}},
                    grid: {borderColor: '#f1f5f9'},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-fricciones');
                if (!el) return;
                var rows = @json($analyticsFrictions);
                if (!rows || !rows.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                var colors = rows.map(function (r) {
                    var s = parseFloat(r.share) || 0;
                    return s >= 30 ? '#ef4444' : (s >= 15 ? '#f59e0b' : '#94a3b8');
                });
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 180, toolbar: {show: false}, fontFamily: 'inherit'},
                    plotOptions: {bar: {horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4}},
                    series: [{
                        name: '% de fricciones', data: rows.map(function (r) {
                            return parseFloat(r.share) || 0;
                        })
                    }],
                    xaxis: {
                        categories: rows.map(function (r) {
                            return r.friction_label;
                        }), labels: {style: {fontSize: '11px'}}, max: 100
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    colors: colors,
                    legend: {show: false},
                    dataLabels: {
                        enabled: true, formatter: function (val) {
                            return val + '%';
                        }, style: {fontSize: '10px'}
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                return val + '% (' + (rows[opts.dataPointIndex] ? rows[opts.dataPointIndex].total : 0) + ' conv.)';
                            }
                        }
                    },
                    grid: {borderColor: '#f1f5f9'},
                }).render();
            }());
        </script>
        <script>
            (function () {
                var el = document.getElementById('chart-embudo');
                if (!el) return;
                var steps = @json($analyticsFunnel);
                if (!steps || !steps.length) {
                    el.innerHTML = '<div class="wa-chart-empty">Sin datos para el periodo seleccionado</div>';
                    return;
                }
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 260, toolbar: {show: false}, fontFamily: 'inherit'},
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            distributed: false,
                            barHeight: '55%',
                            borderRadius: 4,
                            isFunnel: true
                        }
                    },
                    series: [{
                        name: 'Conversaciones', data: steps.map(function (s) {
                            return parseInt(s.value) || 0;
                        })
                    }],
                    xaxis: {
                        categories: steps.map(function (s) {
                            return s.label;
                        }), labels: {style: {fontSize: '11px'}}
                    },
                    yaxis: {labels: {style: {fontSize: '11px'}}},
                    colors: ['#3b82f6'],
                    dataLabels: {
                        enabled: true, formatter: function (val, opts) {
                            var s = steps[opts.dataPointIndex];
                            return val + (s ? ' (' + s.rate_from_start + '%)' : '');
                        }, style: {fontSize: '11px'}
                    },
                    legend: {show: false},
                    grid: {borderColor: '#f1f5f9'},
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                var s = steps[opts.dataPointIndex];
                                return val + ' conv. · ' + (s ? s.rate_to_next + '% al siguiente' : '');
                            }
                        }
                    },
                }).render();
            }());
        </script>
    @endpush

@endsection
