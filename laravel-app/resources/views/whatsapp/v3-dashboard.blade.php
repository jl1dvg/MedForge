@extends('layouts.medforge')

{{--
    MedForge — WhatsApp Dashboard V3
    Single-screen, no-scroll DECISION surface for the contact center.
    This is the dashboard (decide now); the table-heavy v2 view is the
    REPORT (evidence) — reachable from the "Ver reporte completo" button.

    Consumes the SAME data the existing dashboard() controller passes:
    $dashboard => ['summary' => [...], 'breakdowns' => [...], 'trends' => [...]]
    plus $filters. A sibling method (dashboardV3) delegates to dashboard()
    with this view name, so both routes share one KPI pipeline.

    Layout / density can be compared live via querystring:
      /v2/whatsapp/dashboard-v3?layout=operacion&density=compacto
--}}

@php
    $dashboard  = is_array($dashboard ?? null) ? $dashboard : [];
    $summary    = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
    $breakdowns = is_array($dashboard['breakdowns'] ?? null) ? $dashboard['breakdowns'] : [];
    $trends     = is_array($dashboard['trends'] ?? null) ? $dashboard['trends'] : [];
    $filters    = is_array($filters ?? null) ? $filters : [];

    $n = static fn ($k, $d = 0) => (int) ($summary[$k] ?? $d);

    $slaMeta      = (int) ($filters['sla_target_minutes'] ?? 15);
    $attention    = $n('attention_rate');
    $median       = $n('median_first_human_response_minutes');
    $avg          = $n('avg_first_human_response_minutes');
    $p75          = (int) ($summary['p75_first_human_response_minutes'] ?? $avg);
    $slaRate      = $n('sla_assignments_rate');
    $peopleIn     = $n('people_inbound');
    $peopleHandoff = $n('people_handoff');
    $attended     = $n('conversations_attended_human');
    $resolved     = $n('conversations_resolved');
    $lost         = $n('conversations_lost');
    $bookings     = $n('sigcenter_bookings_created');
    $bookingPats  = $n('sigcenter_booking_patients');
    $bookingFails = $n('sigcenter_booking_failures');
    $queueTotal   = $n('live_queue_total');
    $queued       = $n('live_queue_queued');
    $windowOpen   = $n('queue_window_open');
    $needsTpl     = $n('queue_needs_template');
    $awaitingTpl  = $n('queue_awaiting_template_reply');
    $abandoned        = $n('conversations_abandoned');
    $abandonedReal    = $n('conversations_abandoned_needs_human');
    $handoffs     = $n('handoff_transfers');

    $bookingRate = $peopleIn > 0 ? (int) round(($bookings / $peopleIn) * 100) : 0;
    $resolRate   = $attended  > 0 ? (int) round(($resolved / $attended) * 100) : 0;

    /* Severity ranges (per the clinical-kpi-dashboard skill) */
    $sevCoverage = $attention >= 85 ? 'success' : ($attention >= 70 ? 'warning' : 'danger');
    $sevResp     = ($median > 0 && $median <= $slaMeta) ? 'success' : ($median <= $slaMeta * 2 ? 'warning' : 'danger');
    $sevQueue    = $queued <= 5 ? 'success' : ($queued <= 12 ? 'warning' : 'danger');

    $exportQuery = http_build_query(array_filter([
        'date_from' => $filters['date_from'] ?? null,
        'date_to'   => $filters['date_to'] ?? null,
        'role_id'   => $filters['role_id'] ?? null,
        'agent_id'  => $filters['agent_id'] ?? null,
        'sla_target_minutes' => $filters['sla_target_minutes'] ?? null,
    ], static fn ($v) => $v !== null && $v !== ''));

    $layout  = request()->query('layout')  === 'operacion' ? 'operacion' : 'ejecutivo';
    $density = request()->query('density') === 'compacto'  ? 'compacto'  : 'comodo';

    $today     = date('Y-m-d');
    $minus7    = date('Y-m-d', strtotime('-6 days'));
    $minus29   = date('Y-m-d', strtotime('-29 days'));

    $activeFrom = trim((string) ($filters['date_from'] ?? ''));
    $activeTo   = trim((string) ($filters['date_to']   ?? ''));

    $quickRanges = [
        'hoy'   => ['label' => 'Hoy',    'from' => $today,  'to' => $today],
        '7d'    => ['label' => '7 días', 'from' => $minus7, 'to' => $today],
        '30d'   => ['label' => '30 días','from' => $minus29,'to' => $today],
    ];

    $activeRange = '30d';
    foreach ($quickRanges as $key => $r) {
        if ($activeFrom === $r['from'] && $activeTo === $r['to']) { $activeRange = $key; break; }
    }

    $periodLabel = trim((string) ($filters['date_from'] ?? '')) !== ''
        ? ($filters['date_from'] . ' → ' . ($filters['date_to'] ?? ''))
        : 'Últimos 30 días';

    $tone = [
        'primary' => ['bg' => '#edf2ff', 'fg' => '#5156be'],
        'success' => ['bg' => '#dff5ee', 'fg' => '#05825f'],
        'warning' => ['bg' => '#fff0d1', 'fg' => '#8a5d0a'],
        'danger'  => ['bg' => '#fde2e7', 'fg' => '#ee3158'],
        'info'    => ['bg' => '#cfe5fd', 'fg' => '#0863be'],
    ];
    $sevHex = ['success' => '#05825f', 'warning' => '#ffa800', 'danger' => '#ee3158', 'muted' => '#b5b5c3'];

    $heroKpis = [
        [
            'icon' => 'mdi-account-heart-outline', 'tone' => $sevCoverage,
            'label' => 'Cobertura humana', 'value' => $attention, 'unit' => '%',
            'badge' => ['sev' => $sevCoverage, 'text' => $sevCoverage === 'success' ? 'En meta' : ($sevCoverage === 'warning' ? 'Vigilar' : 'Crítico')],
            'trend' => $attended . ' de ' . $peopleHandoff . ' que solicitaron humano',
            'breakdown' => [
                ['dot' => 'success', 'n' => $attended,     'label' => 'Atendidas'],
                ['dot' => 'danger',  'n' => $lost,         'label' => 'Sin respuesta'],
                ['dot' => 'info',    'n' => $peopleHandoff,'label' => 'Con handoff'],
            ],
        ],
        [
            'icon' => 'mdi-timer-sand', 'tone' => $sevResp,
            'label' => '1ª respuesta humana', 'value' => $median, 'unit' => 'min',
            'badge' => ['sev' => $sevResp, 'text' => 'Meta ' . $slaMeta . ' min'],
            'trend' => 'Mediana desde el handoff · P75 ' . $p75 . ' min',
            'breakdown' => [
                ['dot' => 'success', 'n' => $slaRate . '%', 'label' => 'SLA cumplido'],
                ['dot' => 'warning', 'n' => $p75,           'label' => 'P75 min'],
                ['dot' => 'info',    'n' => $handoffs,      'label' => 'Handoffs'],
            ],
        ],
        [
            'icon' => 'mdi-inbox-multiple-outline', 'tone' => $sevQueue,
            'label' => 'Cola activa ahora', 'value' => $queueTotal, 'unit' => '', 'live' => true,
            'badge' => ['sev' => $sevQueue, 'text' => $sevQueue === 'danger' ? 'Saturada' : ($sevQueue === 'warning' ? 'Atención' : 'Al día')],
            'trend' => $queued . ' sin asignar · ' . $abandonedReal . ' abandonadas con handoff',
            'breakdown' => [
                ['dot' => 'warning', 'n' => $queued,     'label' => 'Sin asignar'],
                ['dot' => 'info',    'n' => $windowOpen, 'label' => 'Ventana 24h'],
                ['dot' => 'danger',  'n' => $needsTpl,   'label' => 'Req. plantilla'],
            ],
        ],
        [
            'icon' => 'mdi-calendar-check-outline', 'tone' => 'primary',
            'label' => 'Citas agendadas', 'value' => $bookings, 'unit' => '',
            'badge' => ['sev' => 'success', 'text' => $bookingRate . '% conv.'],
            'trend' => 'Creadas en Sigcenter desde WhatsApp',
            'breakdown' => [
                ['dot' => 'success', 'n' => $bookingPats,        'label' => 'Pacientes'],
                ['dot' => 'danger',  'n' => $bookingFails,       'label' => 'Fallidas'],
                ['dot' => 'primary', 'n' => $bookingRate . '%',  'label' => 'Conversión'],
            ],
        ],
    ];

    $inbox = [
        ['icon' => 'mdi-account-clock-outline',       'tone' => 'warning', 'label' => 'Sin asignar en cola',            'sub' => 'Esperando que un agente la tome', 'n' => $queued,      'sev' => $queued > 5 ? 'warning' : 'success'],
        ['icon' => 'mdi-message-text-clock-outline',  'tone' => 'info',    'label' => 'Ventana 24h abierta',            'sub' => 'Se puede responder libremente',   'n' => $windowOpen,  'sev' => 'muted'],
        ['icon' => 'mdi-file-document-edit-outline',  'tone' => 'danger',  'label' => 'Requiere plantilla',             'sub' => 'Fuera de ventana — usar HSM',     'n' => $needsTpl,    'sev' => $needsTpl > 0 ? 'danger' : 'success'],
        ['icon' => 'mdi-timer-sand-paused',           'tone' => 'primary', 'label' => 'Esperando respuesta a plantilla','sub' => 'Plantilla enviada, sin réplica',  'n' => $awaitingTpl, 'sev' => 'muted'],
        ['icon' => 'mdi-account-alert-outline',       'tone' => 'danger',  'label' => 'Abandonadas con handoff +24h',   'sub' => 'Pidieron humano, nadie respondió', 'n' => $abandonedReal, 'sev' => $abandonedReal > 10 ? 'danger' : 'warning'],
    ];
    $maxInbox = max(1, $queued, $windowOpen, $needsTpl, $awaitingTpl, $abandonedReal);

    $funnel = [
        ['label' => 'Escribieron', 'value' => $peopleIn, 'color' => '#5156be'],
        ['label' => 'Atendidas',   'value' => $attended, 'color' => '#3596f7'],
        ['label' => 'Resueltas',   'value' => $resolved, 'color' => '#0863be'],
        ['label' => 'Agendadas',   'value' => $bookings, 'color' => '#05825f'],
    ];
    $funnelMax = max(1, $peopleIn);

    /* $analytics — origen de demanda, intención inicial, tipo de conversación */
    $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
    $sources   = array_slice(is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [], 0, 4);
    $intents   = array_slice(is_array($analytics['intents'] ?? null) ? $analytics['intents'] : [], 0, 3);
    $convTypes = array_slice(is_array($analytics['conversation_types'] ?? null) ? $analytics['conversation_types'] : [], 0, 3);
    $maxShare  = static function (array $rows): float {
        $m = 0.0;
        foreach ($rows as $r) { $m = max($m, (float) ($r['share'] ?? 0)); }
        return $m > 0 ? $m : 1.0;
    };
    $maxSource = $maxShare($sources);
    $maxIntent = $maxShare($intents);
    $maxType   = $maxShare($convTypes);
    $brkPalette = ['#5156be', '#3596f7', '#05825f', '#ffa800', '#0863be', '#7479d4'];

    $agents = array_slice(is_array($breakdowns['agent_live_status'] ?? null) ? $breakdowns['agent_live_status'] : [], 0, 8);
    $teams  = array_slice(is_array($breakdowns['handoffs_by_role'] ?? null) ? $breakdowns['handoffs_by_role'] : [], 0, 4);
    $teamTotals = ['queued' => 0, 'assigned' => 0, 'resolved' => 0];
    foreach ($teams as $tm) {
        $teamTotals['queued']   += (int) ($tm['queued'] ?? 0);
        $teamTotals['assigned'] += (int) ($tm['assigned'] ?? 0);
        $teamTotals['resolved'] += (int) ($tm['resolved'] ?? 0);
    }
    $initials = static function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));
        if (!$parts) { return '·'; }
        $a = mb_substr($parts[0], 0, 1);
        $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($a . $b);
    };
@endphp

@push('styles')
<style>
/* ===== WhatsApp Dashboard V3 — self-contained tokens + styles ===== */
body:has(.wad) .content-wrapper,
body:has(.wad) .content-wrapper > .content {
    height: 100vh; overflow: hidden; padding: 0; margin: 0;
}
body:has(.wad) .main-header, body:has(.wad) .main-footer { display: none !important; }
body:has(.wad) { overflow: hidden; }

.wad {
    --primary:#5156be; --primary-hover:#3c40a0; --primary-light:#c8c9ee; --primary-fade:#edf2ff;
    --info:#3596f7; --success:#05825f; --warning:#ffa800; --danger:#ee3158;
    --fg-1:#172b4c; --fg-2:#3f4254; --fg-3:#5e6278; --fg-mute:#7e8299; --fg-fade:#b5b5c3;
    --bg-soft:#f3f6f9; --border:#e4e6ef; --border-strong:#d1d3e0; --border-soft:#ebedf3;
    --shadow-xs:0 1px 2px rgba(16,24,40,.06); --shadow:0 4px 12px rgba(16,24,40,.08);
    --ease-out:cubic-bezier(0.16,1,0.3,1);
    --font-display:"Rubik","IBM Plex Sans",system-ui,sans-serif;
    --font-body:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    --wad-accent:var(--primary);
    height:100%; background:var(--bg-soft); padding:16px 22px 18px;
    display:grid; grid-template-rows:auto auto 1fr; gap:14px; min-height:0; color:var(--fg-1);
    font-family:var(--font-body);
}
.wad *, .wad *::before, .wad *::after { box-sizing:border-box; }

.wad-head { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; flex-wrap:wrap; }
.wad-crumb { display:inline-flex; align-items:center; gap:6px; font:500 11.5px var(--font-body); color:var(--fg-mute); text-transform:uppercase; letter-spacing:.06em; }
.wad-crumb i { font-size:14px; }
.wad-head h1 { font:500 22px/1.15 var(--font-display); letter-spacing:-.01em; margin:2px 0 0; display:inline-flex; align-items:center; gap:12px; color:var(--fg-1); }
.wad-live { display:inline-flex; align-items:center; gap:7px; padding:4px 10px 4px 9px; font:600 10.5px var(--font-body); text-transform:uppercase; letter-spacing:.08em; color:var(--success); background:#dff5ee; border-radius:999px; }
.wad-head-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.wad-period { display:inline-flex; padding:3px; border-radius:999px; background:#fff; border:1px solid var(--border); }
.wad-period a { appearance:none; border:0; cursor:pointer; padding:6px 14px; border-radius:999px; font:600 12px var(--font-body); color:var(--fg-3); background:transparent; text-decoration:none; transition:all .15s var(--ease-out); }
.wad-period a:hover { color:var(--wad-accent); }
.wad-period a.active { background:var(--wad-accent); color:#fff; box-shadow:0 4px 10px rgba(81,86,190,.25); }
.wad-ctx { display:inline-flex; gap:6px; }
.wad-ctx-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:999px; background:#fff; border:1px solid var(--border); font:500 12px var(--font-body); color:var(--fg-2); }
.wad-ctx-chip i { color:var(--wad-accent); font-size:14px; }
.wad-report-btn { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px; border:1px solid var(--border); background:#fff; font:600 12px var(--font-body); color:var(--fg-2); cursor:pointer; text-decoration:none; transition:all .15s var(--ease-out); }
.wad-report-btn:hover { color:var(--wad-accent); border-color:var(--primary-light); }
.wad-report-btn i { font-size:15px; }

.wad-pulse { width:8px; height:8px; border-radius:50%; background:var(--success); position:relative; display:inline-block; flex-shrink:0; }
.wad-pulse::after { content:""; position:absolute; inset:0; border-radius:50%; background:inherit; opacity:.6; animation:wad-pulse 1.6s var(--ease-out) infinite; }
.wad-pulse--danger { background:var(--danger); }
.wad-pulse--warning { background:var(--warning); }
@keyframes wad-pulse { 0%{transform:scale(1);opacity:.55;} 70%{transform:scale(2.6);opacity:0;} 100%{transform:scale(2.6);opacity:0;} }

.wad-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.wad-kpi { background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-xs); padding:13px 16px 12px; display:flex; flex-direction:column; gap:10px; position:relative; overflow:hidden; transition:box-shadow .18s var(--ease-out), transform .18s var(--ease-out); }
.wad-kpi:hover { box-shadow:var(--shadow); transform:translateY(-1px); }
.wad-kpi::before { content:""; position:absolute; left:0; right:0; top:0; height:3px; background:var(--kpi-fg); }
.wad-kpi-top { display:flex; align-items:center; gap:12px; }
.wad-kpi-tile { width:42px; height:42px; border-radius:10px; display:grid; place-items:center; font-size:22px; flex-shrink:0; }
.wad-kpi-main { flex:1; min-width:0; }
.wad-kpi-label { margin:0 0 2px; font:600 10.5px var(--font-body); color:var(--fg-mute); text-transform:uppercase; letter-spacing:.08em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wad-kpi-valrow { display:flex; align-items:baseline; gap:7px; }
.wad-kpi-value { margin:0; font:600 28px/1 var(--font-display); color:var(--fg-1); font-variant-numeric:tabular-nums; letter-spacing:-.01em; }
.wad-kpi-unit { font:600 13px var(--font-display); color:var(--fg-3); }
.wad-kpi-badge { margin-left:auto; white-space:nowrap; flex-shrink:0; display:inline-flex; align-items:center; gap:4px; padding:2px 7px; border-radius:999px; font:700 9.5px var(--font-body); text-transform:uppercase; letter-spacing:.03em; }
.wad-kpi-badge--success { background:#dff5ee; color:var(--success); }
.wad-kpi-badge--warning { background:#fff0d1; color:#8a5d0a; }
.wad-kpi-badge--danger { background:#fde2e7; color:var(--danger); }
.wad-kpi-trend { margin:3px 0 0; font:500 10.5px var(--font-body); color:var(--fg-mute); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wad-kpi-break { display:flex; gap:10px; flex-wrap:wrap; font:400 11px var(--font-body); color:var(--fg-3); border-top:1px dashed var(--border-soft); padding-top:9px; }
.wad-kpi-stat { display:inline-flex; align-items:center; gap:4px; }
.wad-kpi-stat strong { color:var(--fg-1); font-weight:700; font-variant-numeric:tabular-nums; }
.wad-kpi-stat-label { color:var(--fg-mute); }
.wad-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }
.wad-dot--primary { background:var(--primary); }
.wad-dot--success { background:var(--success); }
.wad-dot--warning { background:var(--warning); }
.wad-dot--danger { background:var(--danger); }
.wad-dot--info { background:var(--info); }

.wad-panels { display:grid; gap:12px; min-height:0; grid-template-columns:1.15fr 1fr 1fr; grid-template-rows:1fr 1fr; grid-template-areas:"bandeja embudo intencion" "agente handoffs origen"; }
.wad[data-layout="operacion"] .wad-panels { grid-template-columns:1.3fr 1fr 1fr 1fr; grid-template-areas:"bandeja embudo intencion intencion" "bandeja agente handoffs origen"; }
.wad-panel--bandeja { grid-area:bandeja; }
.wad-panel--embudo { grid-area:embudo; }
.wad-panel--intencion { grid-area:intencion; }
.wad-panel--agente { grid-area:agente; }
.wad-panel--handoffs { grid-area:handoffs; }
.wad-panel--origen { grid-area:origen; }

.wad-panel { background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-xs); display:flex; flex-direction:column; min-height:0; overflow:hidden; }
.wad-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 16px; border-bottom:1px solid var(--border-soft); background:linear-gradient(180deg,#fff 0%,#fafbfd 100%); }
.wad-panel-head h3 { margin:0; font:600 13.5px/1.15 var(--font-display); color:var(--fg-1); display:inline-flex; align-items:center; gap:8px; white-space:nowrap; }
.wad-panel-head h3 i { color:var(--wad-accent); font-size:17px; }
.wad-panel-link { display:inline-flex; align-items:center; gap:4px; font:600 11.5px var(--font-body); color:var(--wad-accent); text-decoration:none; }
.wad-panel-link:hover { filter:brightness(.85); }
.wad-panel-link i { font-size:15px; }
.wad-panel-body { flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; padding:10px 14px 12px; }
.wad-panel-body::-webkit-scrollbar { width:6px; }
.wad-panel-body::-webkit-scrollbar-thumb { background:var(--border-strong); border-radius:4px; }
.wad-empty { display:grid; place-items:center; height:100%; min-height:80px; color:var(--fg-mute); font:500 12px var(--font-body); text-align:center; padding:14px; }

.wad-chip { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font:700 10.5px var(--font-body); letter-spacing:.02em; }
.wad-chip--success { background:#dff5ee; color:var(--success); }
.wad-chip--muted { background:var(--bg-soft); color:var(--fg-mute); border:1px solid var(--border); }

.wad-inbox { display:flex; flex-direction:column; gap:4px; }
.wad-ib-row { display:grid; grid-template-columns:34px 1fr auto; align-items:center; gap:11px; padding:8px 8px; border-radius:9px; text-decoration:none; color:inherit; transition:background .15s var(--ease-out); }
.wad-ib-row:hover { background:var(--bg-soft); }
.wad-ib-tile { width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-size:17px; }
.wad-ib-main { min-width:0; }
.wad-ib-label { margin:0; font:600 12.5px var(--font-body); color:var(--fg-1); line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wad-ib-sub { margin:1px 0 0; font:400 10.5px var(--font-body); color:var(--fg-mute); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.25; }
.wad-ib-meter { width:64px; height:5px; border-radius:3px; background:var(--bg-soft); overflow:hidden; margin-top:5px; }
.wad-ib-meter-fill { height:100%; border-radius:3px; }
.wad-ib-num { text-align:right; display:flex; flex-direction:column; align-items:flex-end; }
.wad-ib-num strong { font:700 18px/1 var(--font-display); color:var(--fg-1); font-variant-numeric:tabular-nums; }
.wad-ib-num span { font:700 9px var(--font-body); text-transform:uppercase; letter-spacing:.05em; margin-top:3px; }
.wad-ib-num .sev--danger { color:var(--danger); }
.wad-ib-num .sev--warning { color:#8a5d0a; }
.wad-ib-num .sev--success { color:var(--success); }
.wad-ib-num .sev--muted { color:var(--fg-mute); }

.wad-funnel { display:flex; flex-direction:column; gap:9px; padding:14px 16px; height:100%; }
.wad-fn-row { display:grid; grid-template-columns:96px 1fr 46px; gap:12px; align-items:center; }
.wad-fn-label { font:500 11.5px var(--font-body); color:var(--fg-3); white-space:nowrap; }
.wad-fn-track { height:20px; background:var(--bg-soft); border-radius:5px; overflow:hidden; position:relative; }
.wad-fn-fill { height:100%; border-radius:5px; }
.wad-fn-value { font:700 14px var(--font-display); color:var(--fg-1); text-align:right; font-variant-numeric:tabular-nums; }
.wad-fn-foot { display:flex; justify-content:space-between; gap:10px; margin-top:auto; padding-top:11px; border-top:1px dashed var(--border-soft); font:400 11px var(--font-body); color:var(--fg-mute); }
.wad-fn-foot strong { color:var(--fg-1); font-weight:700; }
.wad-fn-foot .accent { color:var(--success); }

.wad-brk { display:flex; flex-direction:column; gap:10px; height:100%; }
.wad-brk-section { display:flex; flex-direction:column; gap:7px; }
.wad-brk-h { display:flex; align-items:center; gap:8px; font:700 9.5px var(--font-body); text-transform:uppercase; letter-spacing:.06em; color:var(--fg-mute); }
.wad-brk-h::after { content:""; flex:1; height:1px; background:var(--border-soft); }
.wad-brk-top { display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin-bottom:5px; }
.wad-brk-name { font:600 12px var(--font-body); color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.wad-brk-meta { font:500 10.5px var(--font-body); color:var(--fg-mute); white-space:nowrap; flex-shrink:0; }
.wad-brk-meta strong { color:var(--fg-1); font-weight:700; font-variant-numeric:tabular-nums; }
.wad-brk-track { height:11px; border-radius:4px; background:var(--bg-soft); overflow:hidden; }
.wad-brk-fill { height:100%; border-radius:4px; }
.wad-brk-foot { display:flex; justify-content:space-between; gap:10px; margin-top:auto; padding-top:11px; border-top:1px dashed var(--border-soft); font:400 11px var(--font-body); color:var(--fg-mute); }
.wad-brk-foot strong { color:var(--fg-1); font-weight:700; }

.wad-vol { display:flex; flex-direction:column; height:100%; padding:12px 14px; }
.wad-vol-legend { display:flex; gap:14px; }
.wad-vol-leg { display:inline-flex; align-items:center; gap:6px; font:500 11px var(--font-body); color:var(--fg-3); }
.wad-vol-leg b { width:10px; height:3px; border-radius:2px; display:inline-block; }
.wad-vol-chart { flex:1; min-height:0; position:relative; }
.wad-vol-chart svg { width:100%; height:100%; display:block; }
.wad-vol-foot { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin-top:8px; padding-top:10px; border-top:1px dashed var(--border-soft); }
.wad-vol-stat { display:flex; flex-direction:column; }
.wad-vol-stat strong { font:700 16px/1 var(--font-display); color:var(--fg-1); font-variant-numeric:tabular-nums; }
.wad-vol-stat span { font:500 9.5px var(--font-body); color:var(--fg-mute); text-transform:uppercase; letter-spacing:.04em; margin-top:3px; }

.wad-agents { display:flex; flex-direction:column; gap:2px; }
.wad-ag-row { display:grid; grid-template-columns:34px 1fr auto; align-items:center; gap:10px; padding:7px 6px; border-radius:8px; transition:background .15s var(--ease-out); }
.wad-ag-row:hover { background:var(--bg-soft); }
.wad-ag-avatar { width:34px; height:34px; border-radius:10px; background:var(--primary-light); color:var(--primary); display:grid; place-items:center; font:700 11.5px var(--font-body); position:relative; }
.wad-ag-rank { position:absolute; top:-4px; left:-4px; width:16px; height:16px; border-radius:50%; background:var(--wad-accent); color:#fff; font:700 9px var(--font-body); display:grid; place-items:center; border:1.5px solid #fff; }
.wad-ag-info { min-width:0; }
.wad-ag-name { margin:0; font:600 12.5px var(--font-body); color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wad-ag-role { margin:1px 0 0; font:400 10.5px var(--font-body); color:var(--fg-mute); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.wad-ag-stats { display:flex; align-items:center; gap:8px; }
.wad-ag-att { text-align:right; }
.wad-ag-att strong { font:700 14px/1 var(--font-display); color:var(--fg-1); font-variant-numeric:tabular-nums; }
.wad-ag-att span { display:block; font:500 9px var(--font-body); color:var(--fg-mute); text-transform:uppercase; letter-spacing:.04em; margin-top:2px; }
.wad-ag-unread { position:absolute; top:-5px; right:-5px; min-width:18px; height:18px; padding:0 4px; border-radius:999px; background:var(--warning); color:#fff; font:700 10px var(--font-body); display:grid; place-items:center; border:2px solid #fff; }
.wad-ag-unread--alarm { background:var(--danger); animation:wad-badge-alarm .7s ease-in-out infinite alternate; }
@keyframes wad-badge-alarm { from { transform:scale(1); } to { transform:scale(1.25); box-shadow:0 0 0 4px rgba(238,49,88,.25); } }
.wad-ag-resp { display:inline-flex; align-items:center; gap:4px; min-width:52px; justify-content:center; padding:4px 8px; border-radius:999px; font:700 11px var(--font-body); font-variant-numeric:tabular-nums; }
.wad-ag-resp i { font-size:12px; }
.wad-ag-resp--alarm { animation:wad-chip-alarm .7s ease-in-out infinite alternate; }
@keyframes wad-chip-alarm { from { opacity:1; } to { opacity:.65; box-shadow:0 0 0 3px rgba(238,49,88,.3); } }

.wad-teams { display:flex; flex-direction:column; gap:10px; }
.wad-tm-row { display:flex; flex-direction:column; gap:5px; }
.wad-tm-top { display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
.wad-tm-name { font:600 12px var(--font-body); color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.wad-tm-total { font:700 13px var(--font-display); color:var(--fg-1); font-variant-numeric:tabular-nums; }
.wad-tm-bar { height:9px; border-radius:5px; background:var(--bg-soft); overflow:hidden; display:flex; }
.wad-tm-seg { height:100%; }
.wad-tm-seg--queued { background:var(--warning); }
.wad-tm-seg--assigned { background:var(--info); }
.wad-tm-seg--resolved { background:var(--success); }
.wad-tm-legend { display:flex; gap:12px; flex-wrap:wrap; margin-top:2px; padding-top:9px; border-top:1px dashed var(--border-soft); }
.wad-tm-leg { display:inline-flex; align-items:center; gap:5px; font:500 10.5px var(--font-body); color:var(--fg-mute); }
.wad-tm-leg b { width:9px; height:9px; border-radius:3px; display:inline-block; }
.wad-tm-leg .n { color:var(--fg-1); font-weight:700; font-variant-numeric:tabular-nums; }

.wad-sede { display:flex; flex-direction:column; gap:12px; height:100%; }
.wad-sd-row { display:grid; grid-template-columns:1fr 36px; gap:10px; align-items:center; }
.wad-sd-top { display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin-bottom:6px; }
.wad-sd-name { font:600 12px var(--font-body); color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
.wad-sd-track { height:12px; background:var(--bg-soft); border-radius:4px; overflow:hidden; }
.wad-sd-fill { height:100%; border-radius:4px; background:var(--success); }
.wad-sd-val { font:700 14px var(--font-display); color:var(--fg-1); text-align:right; font-variant-numeric:tabular-nums; }
.wad-sd-foot { display:flex; justify-content:space-between; gap:10px; margin-top:auto; padding-top:11px; border-top:1px dashed var(--border-soft); font:400 11px var(--font-body); color:var(--fg-mute); }
.wad-sd-foot strong { color:var(--fg-1); font-weight:700; }

.wad[data-density="compacto"] { gap:10px; padding:12px 18px 14px; }
.wad[data-density="compacto"] .wad-kpi { padding:10px 13px; gap:7px; }
.wad[data-density="compacto"] .wad-kpi-value { font-size:23px; }
.wad[data-density="compacto"] .wad-kpi-tile { width:36px; height:36px; font-size:19px; }
.wad[data-density="compacto"] .wad-panels { gap:10px; }
.wad[data-density="compacto"] .wad-panel-head { padding:9px 14px; }
.wad[data-density="compacto"] .wad-panel-body { padding:8px 12px 10px; }

@media (max-height:880px) {
    .wad { padding:12px 18px 14px; gap:11px; }
    .wad-panel-head { padding:9px 14px; }
    .wad-kpi { padding:10px 14px; gap:8px; }
    .wad-kpi-value { font-size:24px; }
    .wad-kpi-tile { width:38px; height:38px; font-size:20px; }
    .wad-head h1 { font-size:20px; }
}

/* ── Mobile ── */
@media (max-width:768px) {
    body:has(.wad) .content-wrapper,
    body:has(.wad) .content-wrapper > .content {
        height: auto; overflow: auto;
    }
    body:has(.wad) { overflow: auto; }

    .wad {
        height: auto; min-height: 100vh;
        padding: 14px 14px 32px;
        grid-template-rows: auto auto auto;
        overflow: visible;
    }

    /* Header apilado y compacto */
    .wad-head { flex-direction: column; align-items: flex-start; gap: 10px; }
    .wad-head h1 { font-size: 18px; }
    .wad-head-right { width: 100%; flex-wrap: wrap; gap: 8px; }
    .wad-period a { padding: 5px 11px; font-size: 11px; }
    .wad-report-btn { width: 100%; justify-content: center; }

    /* KPIs 2×2 */
    .wad-kpis { grid-template-columns: repeat(2, 1fr); }
    .wad-kpi-value { font-size: 22px; }
    .wad-kpi-tile { width: 36px; height: 36px; font-size: 18px; }
    .wad-kpi-break { display: none; }

    /* Paneles columna única */
    .wad-panels {
        grid-template-columns: 1fr !important;
        grid-template-rows: auto !important;
        grid-template-areas:
            "bandeja"
            "embudo"
            "intencion"
            "agente"
            "handoffs"
            "origen" !important;
    }
    .wad-panel { min-height: 280px; }
}
</style>
@endpush

@section('content')
<div class="wad" data-layout="{{ $layout }}" data-density="{{ $density }}">

    {{-- Head --}}
    <header class="wad-head">
        <div class="wad-head-left">
            <div class="wad-crumb"><i class="mdi mdi-whatsapp"></i><span>WhatsApp · Contact center</span></div>
            <h1>Dashboard WhatsApp <span class="wad-live"><span class="wad-pulse"></span>en vivo</span></h1>
        </div>
        <div class="wad-head-right">
            <div class="wad-period">
                <a class="{{ $layout === 'ejecutivo' ? 'active' : '' }}" href="?{{ http_build_query(array_merge(request()->query(), ['layout' => 'ejecutivo'])) }}">Ejecutivo</a>
                <a class="{{ $layout === 'operacion' ? 'active' : '' }}" href="?{{ http_build_query(array_merge(request()->query(), ['layout' => 'operacion'])) }}">Operación</a>
            </div>
            <div class="wad-period">
                @foreach($quickRanges as $key => $r)
                    <a class="{{ $activeRange === $key ? 'active' : '' }}"
                       href="?{{ http_build_query(array_merge(request()->query(), ['date_from' => $r['from'], 'date_to' => $r['to']])) }}">{{ $r['label'] }}</a>
                @endforeach
            </div>
            <div class="wad-ctx">
                <span class="wad-ctx-chip"><i class="mdi mdi-timer-outline"></i>SLA {{ $slaMeta }} min</span>
            </div>
            <a class="wad-report-btn" href="/v2/whatsapp/dashboard{{ $exportQuery ? '?' . $exportQuery : '' }}" title="Abrir el reporte detallado con tablas y exportación">
                <i class="mdi mdi-table-large"></i>Ver reporte completo
            </a>
        </div>
    </header>

    {{-- Hero KPIs --}}
    <section class="wad-kpis">
        @foreach($heroKpis as $k)
            @php $t = $tone[$k['tone']] ?? $tone['primary']; @endphp
            <article class="wad-kpi" style="--kpi-fg:{{ $t['fg'] }}">
                <div class="wad-kpi-top">
                    <span class="wad-kpi-tile" style="background:{{ $t['bg'] }};color:{{ $t['fg'] }}"><i class="mdi {{ $k['icon'] }}"></i></span>
                    <div class="wad-kpi-main">
                        <p class="wad-kpi-label">{{ $k['label'] }}@if(!empty($k['live'])) &nbsp;<span class="wad-pulse wad-pulse--{{ $sevQueue === 'danger' ? 'danger' : ($sevQueue === 'warning' ? 'warning' : '') }}" style="width:6px;height:6px;vertical-align:middle;margin-bottom:1px;"></span>@endif</p>
                        <div class="wad-kpi-valrow">
                            <p class="wad-kpi-value">{{ $k['value'] }}@if($k['unit'])<span class="wad-kpi-unit">{{ $k['unit'] }}</span>@endif</p>
                            @if(!empty($k['badge']))<span class="wad-kpi-badge wad-kpi-badge--{{ $k['badge']['sev'] }}">{{ $k['badge']['text'] }}</span>@endif
                        </div>
                        <p class="wad-kpi-trend">{{ $k['trend'] }}</p>
                    </div>
                </div>
                <div class="wad-kpi-break">
                    @foreach($k['breakdown'] as $b)
                        <span class="wad-kpi-stat"><span class="wad-dot wad-dot--{{ $b['dot'] }}"></span><strong>{{ $b['n'] }}</strong><span class="wad-kpi-stat-label">{{ $b['label'] }}</span></span>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    {{-- Panels --}}
    <section class="wad-panels">

        {{-- Bandeja en vivo --}}
        <article class="wad-panel wad-panel--bandeja">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-inbox-arrow-down-outline"></i>Bandeja en vivo</h3>
                <a class="wad-panel-link" href="/v2/whatsapp/chat">Ir al chat <i class="mdi mdi-arrow-right-thin"></i></a>
            </header>
            <div class="wad-panel-body wad-inbox">
                @foreach($inbox as $b)
                    @php $t = $tone[$b['tone']] ?? $tone['primary']; $sevTxt = $b['sev'] === 'danger' ? 'actuar' : ($b['sev'] === 'warning' ? 'vigilar' : 'ok'); @endphp
                    <a class="wad-ib-row" href="/v2/whatsapp/chat">
                        <span class="wad-ib-tile" style="background:{{ $t['bg'] }};color:{{ $t['fg'] }}"><i class="mdi {{ $b['icon'] }}"></i></span>
                        <div class="wad-ib-main">
                            <p class="wad-ib-label">{{ $b['label'] }}</p>
                            <p class="wad-ib-sub">{{ $b['sub'] }}</p>
                            <div class="wad-ib-meter"><div class="wad-ib-meter-fill" style="width:{{ ($b['n'] / $maxInbox) * 100 }}%;background:{{ $sevHex[$b['sev']] }}"></div></div>
                        </div>
                        <div class="wad-ib-num"><strong>{{ $b['n'] }}</strong><span class="sev--{{ $b['sev'] }}">{{ $sevTxt }}</span></div>
                    </a>
                @endforeach
            </div>
        </article>

        {{-- Embudo de servicio --}}
        <article class="wad-panel wad-panel--embudo">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-filter-variant"></i>Embudo de servicio</h3>
                <span class="wad-chip wad-chip--success">{{ $bookingRate }}% a cita</span>
            </header>
            <div class="wad-panel-body wad-funnel">
                @foreach($funnel as $f)
                    <div class="wad-fn-row">
                        <span class="wad-fn-label">{{ $f['label'] }}</span>
                        <div class="wad-fn-track"><div class="wad-fn-fill" style="width:{{ ($f['value'] / $funnelMax) * 100 }}%;background:{{ $f['color'] }}"></div></div>
                        <span class="wad-fn-value">{{ $f['value'] }}</span>
                    </div>
                @endforeach
                <div class="wad-fn-foot">
                    <span>Cobertura <strong class="accent">{{ $attention }}%</strong></span>
                    <span>Resolución <strong>{{ $resolRate }}%</strong></span>
                    <span>Conversión <strong>{{ $bookingRate }}%</strong></span>
                </div>
            </div>
        </article>

        {{-- Intención y tipo de conversación --}}
        <article class="wad-panel wad-panel--intencion">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-tag-text-outline"></i>Intención y tipo</h3>
                <span class="wad-chip wad-chip--muted">{{ number_format($n('conversations_new'), 0, ',', '.') }} convs.</span>
            </header>
            <div class="wad-panel-body wad-brk">
                <div class="wad-brk-section">
                    <div class="wad-brk-h">Intención inicial</div>
                    @forelse($intents as $i => $it)
                        <div>
                            <div class="wad-brk-top">
                                <span class="wad-brk-name">{{ $it['intent_label'] ?? ($it['initial_intent'] ?? '—') }}</span>
                                <span class="wad-brk-meta"><strong>{{ (int) ($it['total'] ?? 0) }}</strong> · {{ (float) ($it['share'] ?? 0) }}%</span>
                            </div>
                            <div class="wad-brk-track"><div class="wad-brk-fill" style="width:{{ ((float) ($it['share'] ?? 0) / $maxIntent) * 100 }}%;background:{{ $brkPalette[$i % count($brkPalette)] }}"></div></div>
                        </div>
                    @empty
                        <div class="wad-empty">Sin intención clasificada en el periodo.</div>
                    @endforelse
                </div>
                <div class="wad-brk-section">
                    <div class="wad-brk-h">Tipo de conversación</div>
                    @forelse($convTypes as $i => $ct)
                        <div>
                            <div class="wad-brk-top">
                                <span class="wad-brk-name">{{ $ct['type_label'] ?? ($ct['conversation_type'] ?? '—') }}</span>
                                <span class="wad-brk-meta"><strong>{{ (int) ($ct['total'] ?? 0) }}</strong> · {{ (float) ($ct['share'] ?? 0) }}%</span>
                            </div>
                            <div class="wad-brk-track"><div class="wad-brk-fill" style="width:{{ ((float) ($ct['share'] ?? 0) / $maxType) * 100 }}%;background:{{ $brkPalette[($i + 2) % count($brkPalette)] }}"></div></div>
                        </div>
                    @empty
                        <div class="wad-empty">Sin tipo clasificado en el periodo.</div>
                    @endforelse
                </div>
            </div>
        </article>

        {{-- Estado en vivo por agente --}}
        <article class="wad-panel wad-panel--agente">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-account-supervisor-outline"></i>Agentes en vivo</h3>
                <span class="wad-chip wad-chip--muted">{{ count($agents) }} activos</span>
            </header>
            <div class="wad-panel-body wad-agents">
                @forelse($agents as $a)
                    @php
                        $name    = (string) ($a['agent_name'] ?? 'Agente');
                        $unread  = (int) ($a['unread_conversations'] ?? 0);
                        $active  = (int) ($a['active_conversations'] ?? 0);
                        $wait    = (int) ($a['max_unread_wait_minutes'] ?? 0);
                        $waitSev = $wait >= $slaMeta * 2 ? 'danger' : ($wait >= $slaMeta ? 'warning' : 'success');
                        $waitBg  = $tone[$waitSev];
                        $alarm   = $waitSev === 'danger';
                    @endphp
                    <div class="wad-ag-row">
                        <span class="wad-ag-avatar">
                            {{ $initials($name) }}
                            @if($unread > 0)
                                <span class="wad-ag-unread {{ $alarm ? 'wad-ag-unread--alarm' : '' }}">{{ $unread }}</span>
                            @endif
                        </span>
                        <div class="wad-ag-info">
                            <p class="wad-ag-name">{{ $name }}</p>
                            <p class="wad-ag-role">{{ $active }} asignada{{ $active !== 1 ? 's' : '' }} · {{ $unread }} sin leer</p>
                        </div>
                        <div class="wad-ag-stats">
                            @if($unread > 0)
                                <span class="wad-ag-resp {{ $alarm ? 'wad-ag-resp--alarm' : '' }}"
                                      style="background:{{ $waitBg['bg'] }};color:{{ $waitBg['fg'] }}">
                                    <i class="mdi mdi-timer-outline"></i>{{ $wait > 0 ? $wait . 'm' : '<1m' }}
                                </span>
                            @else
                                <span class="wad-ag-resp" style="background:#dff5ee;color:#05825f">
                                    <i class="mdi mdi-check-circle-outline"></i>Al día
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="wad-empty">No hay agentes con conversaciones activas.</div>
                @endforelse
            </div>
        </article>

        {{-- Handoffs por equipo --}}
        <article class="wad-panel wad-panel--handoffs">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-swap-horizontal-bold"></i>Handoffs por equipo</h3>
                <span class="wad-chip wad-chip--muted">{{ $handoffs }} total</span>
            </header>
            <div class="wad-panel-body">
                <div class="wad-teams">
                    @forelse($teams as $tm)
                        @php
                            $q = (int) ($tm['queued'] ?? 0); $as = (int) ($tm['assigned'] ?? 0); $rs = (int) ($tm['resolved'] ?? 0);
                            $tot = max(1, $q + $as + $rs);
                        @endphp
                        <div class="wad-tm-row">
                            <div class="wad-tm-top"><span class="wad-tm-name">{{ $tm['role_name'] ?? 'Sin rol' }}</span><span class="wad-tm-total">{{ (int) ($tm['total'] ?? $tot) }}</span></div>
                            <div class="wad-tm-bar">
                                <div class="wad-tm-seg wad-tm-seg--queued" style="width:{{ ($q / $tot) * 100 }}%"></div>
                                <div class="wad-tm-seg wad-tm-seg--assigned" style="width:{{ ($as / $tot) * 100 }}%"></div>
                                <div class="wad-tm-seg wad-tm-seg--resolved" style="width:{{ ($rs / $tot) * 100 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="wad-empty">Sin handoffs registrados en el periodo.</div>
                    @endforelse
                </div>
                @if(count($teams))
                    <div class="wad-tm-legend">
                        <span class="wad-tm-leg"><b style="background:var(--warning)"></b>En cola <span class="n">{{ $teamTotals['queued'] }}</span></span>
                        <span class="wad-tm-leg"><b style="background:var(--info)"></b>Asignadas <span class="n">{{ $teamTotals['assigned'] }}</span></span>
                        <span class="wad-tm-leg"><b style="background:var(--success)"></b>Resueltas <span class="n">{{ $teamTotals['resolved'] }}</span></span>
                    </div>
                @endif
            </div>
        </article>

        {{-- Origen de demanda --}}
        <article class="wad-panel wad-panel--origen">
            <header class="wad-panel-head">
                <h3><i class="mdi mdi-bullhorn-outline"></i>Origen de demanda</h3>
                <span class="wad-chip wad-chip--muted">{{ count($sources) }} fuentes</span>
            </header>
            <div class="wad-panel-body wad-brk">
                <div class="wad-brk-section">
                    @forelse($sources as $i => $sc)
                        <div>
                            <div class="wad-brk-top">
                                <span class="wad-brk-name">{{ $sc['source_label'] ?? ($sc['source_category'] ?? '—') }}</span>
                                <span class="wad-brk-meta"><strong>{{ (int) ($sc['total'] ?? 0) }}</strong> · {{ (float) ($sc['booking_rate'] ?? 0) }}% cita</span>
                            </div>
                            <div class="wad-brk-track"><div class="wad-brk-fill" style="width:{{ ((float) ($sc['share'] ?? 0) / $maxSource) * 100 }}%;background:{{ $brkPalette[$i % count($brkPalette)] }}"></div></div>
                        </div>
                    @empty
                        <div class="wad-empty">Sin origen registrado en el periodo.</div>
                    @endforelse
                </div>
                @if(count($sources))
                    <div class="wad-brk-foot">
                        <span>Conversión a cita</span>
                        <span><strong>{{ $bookingRate }}%</strong> global</span>
                    </div>
                @endif
            </div>
        </article>

    </section>
</div>
@endsection
