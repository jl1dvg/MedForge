@extends('layouts.medforge')

@php
    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
    $trends = is_array($dashboard['trends'] ?? null) ? $dashboard['trends'] : [];
    $breakdowns = is_array($dashboard['breakdowns'] ?? null) ? $dashboard['breakdowns'] : [];
    $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
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
        'series' => 'Serie diaria del periodo para leer volumen general del canal y sus principales eventos.',
        'human_by_agent' => 'Qué agente absorbió más conversaciones y en cuánto tiempo respondió por primera vez tras el handoff.',
        'handoffs_by_role' => 'Distribución de handoffs por equipo para medir entrada, asignación y cierre operativo.',
        'agent_load' => 'Carga por agente para detectar saturación, reparto desigual o capacidad ociosa.',
    ];
@endphp

@push('styles')
<style>
    .wa-dashboard-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(14, 165, 233, .16), transparent 34%),
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
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
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
        color: rgba(255,255,255,.72);
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
        background: radial-gradient(circle at top left, rgba(14,165,233,.06), transparent 34%), #fff;
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
                            <div class="wa-dashboard-pagebar__title">KPI y reportes</div>
                            <button type="button" class="wa-kpi-help wa-kpi-help--light" aria-label="Ver ayuda de KPI y reportes">
                                ?
                                <span class="wa-kpi-help__tooltip">{{ $sectionHelp['dashboard_title'] }}</span>
                            </button>
                        </div>
                        <div class="wa-dashboard-pagebar__subtitle">
                            Vista operativa sobre Laravel para atención humana, SLA, cola, ventana de 24 horas y transferencias.
                            El objetivo aquí es leer salud operativa rápido, no navegar tablas sueltas.
                        </div>
                    </div>
                    <div class="wa-dashboard-pagebar__meta">
                        <span class="wa-dashboard-hero-pill"><i class="mdi mdi-chart-line"></i> atención {{ $summary['attention_rate'] ?? 0 }}%</span>
                        <span class="wa-dashboard-hero-pill"><i class="mdi mdi-timer-outline"></i> SLA {{ $summary['sla_assignments_rate'] ?? 0 }}%</span>
                        <span class="wa-dashboard-hero-pill"><i class="mdi mdi-tray-arrow-down"></i> cola {{ $summary['live_queue_total'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-dashboard-filter-shell">
                <form method="GET" action="/v2/whatsapp/dashboard" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Desde</label>
                            <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasta</label>
                            <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Equipo</label>
                            <select class="form-select" name="role_id">
                                <option value="">Todos</option>
                                @foreach(($options['roles'] ?? []) as $role)
                                    <option value="{{ $role['id'] }}" {{ (int) ($filters['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' }}>{{ $role['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Agente</label>
                            <select class="form-select" name="agent_id">
                                <option value="">Todos</option>
                                @foreach(($options['agents'] ?? []) as $agent)
                                    <option value="{{ $agent['id'] }}" {{ (int) ($filters['agent_id'] ?? 0) === (int) $agent['id'] ? 'selected' : '' }}>{{ $agent['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SLA (min)</label>
                            <input type="number" min="1" max="1440" class="form-control" name="sla_target_minutes" value="{{ $filters['sla_target_minutes'] ?? 15 }}">
                        </div>
                        <div class="col-12 d-flex gap-10">
                            <button type="submit" class="btn btn-primary">Actualizar</button>
                            <a href="/v2/whatsapp/dashboard" class="btn btn-light">Limpiar</a>
                            <a href="/v2/whatsapp/api/kpis/export?{{ $exportQuery }}" class="btn btn-success">Exportar CSV</a>
                            <a href="/v2/whatsapp/api/kpis/export/pdf?{{ $exportQuery }}" class="btn btn-dark">Resumen PDF</a>
                        </div>
                    </form>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 col-12">
            <div class="wa-kpi-band">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Supervisor</div>
                <div class="wa-kpi-title-row">
                    <h5>Salud operativa</h5>
                    <button type="button" class="wa-kpi-help wa-kpi-help--light" aria-label="Ver ayuda de Salud operativa">
                        ?
                        <span class="wa-kpi-help__tooltip">{{ $sectionHelp['supervisor_band'] }}</span>
                    </button>
                </div>
                <div class="muted">Cobertura {{ $summary['attention_rate'] ?? 0 }}% · Sin respuesta {{ $summary['loss_rate'] ?? 0 }}%</div>
                <div class="mt-10 fw-600">Cola {{ $summary['live_queue_total'] ?? 0 }} · SLA {{ $summary['sla_assignments_rate'] ?? 0 }}%</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 col-12">
            <div class="wa-kpi-band" style="background:linear-gradient(135deg, #14532d 0%, #166534 100%);">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Ventana</div>
                <div class="wa-kpi-title-row">
                    <h5>Conversaciones fuera de 24h</h5>
                    <button type="button" class="wa-kpi-help wa-kpi-help--light" aria-label="Ver ayuda de Conversaciones fuera de 24h">
                        ?
                        <span class="wa-kpi-help__tooltip">{{ $sectionHelp['window_band'] }}</span>
                    </button>
                </div>
                <div class="muted">Requieren plantilla {{ $summary['queue_needs_template'] ?? 0 }}</div>
                <div class="mt-10 fw-600">Esperando respuesta a plantilla {{ $summary['queue_awaiting_template_reply'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12 col-12">
            <div class="wa-kpi-band" style="background:linear-gradient(135deg, #7c2d12 0%, #9a3412 100%);">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Acciones rápidas</div>
                <div class="wa-kpi-title-row">
                    <h5>Drilldown API</h5>
                    <button type="button" class="wa-kpi-help wa-kpi-help--light" aria-label="Ver ayuda de Drilldown API">
                        ?
                        <span class="wa-kpi-help__tooltip">{{ $sectionHelp['drilldown_band'] }}</span>
                    </button>
                </div>
                <div class="wa-kpi-link-list mt-10">
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_lost&{{ $exportQuery }}" target="_blank" rel="noopener">Sin respuesta</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_attended_human&{{ $exportQuery }}" target="_blank" rel="noopener">Atendidas</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=queue_needs_template&{{ $exportQuery }}" target="_blank" rel="noopener">Plantillas</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=sla_assignments_total&{{ $exportQuery }}" target="_blank" rel="noopener">SLA</a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-kpi-grid">
                @php
                    $slaTargetMinutes = (int) ($summary['sla_target_minutes'] ?? ($filters['sla_target_minutes'] ?? 15));
                    $firstHumanAvg = isset($summary['avg_first_human_response_minutes']) ? $summary['avg_first_human_response_minutes'] . ' min' : '—';
                    $firstHumanMedian = isset($summary['median_first_human_response_minutes']) ? $summary['median_first_human_response_minutes'] . ' min' : '—';
                    $cards = [
                        ['label' => 'Personas que escribieron', 'value' => $summary['people_inbound'] ?? 0, 'sub' => 'Números únicos inbound', 'help' => 'Cantidad de números únicos que enviaron al menos un mensaje inbound en el periodo.'],
                        ['label' => 'Conversaciones atendidas', 'value' => $summary['conversations_attended_human'] ?? 0, 'sub' => ($summary['people_attended_human'] ?? 0) . ' personas atendidas', 'help' => 'Conversaciones que recibieron al menos una respuesta humana.'],
                        ['label' => 'Conversaciones sin respuesta humana', 'value' => $summary['conversations_lost'] ?? 0, 'sub' => ($summary['people_lost'] ?? 0) . ' personas · ' . ($summary['loss_rate'] ?? 0) . '% · ' . ($summary['conversations_lost_with_handoff'] ?? 0) . ' con handoff', 'help' => 'Conversaciones inbound que no registraron respuesta humana. Puede incluir casos con handoff pendiente y casos que nunca llegaron a handoff.'],
                        ['label' => 'Cobertura humana', 'value' => ($summary['attention_rate'] ?? 0) . '%', 'sub' => 'Personas atendidas / personas inbound', 'help' => 'Porcentaje de números únicos inbound que sí recibieron respuesta humana.'],
                        ['label' => 'Tiempo a primera respuesta humana', 'value' => $firstHumanAvg, 'sub' => 'Desde handoff · mediana ' . $firstHumanMedian, 'help' => 'Promedio medido desde la solicitud de ayuda o ingreso a handoff hasta la primera respuesta humana. No usa el primer mensaje del bot como punto de partida.'],
                        ['label' => 'Conversaciones inactivas >24h sin respuesta humana', 'value' => $summary['conversations_abandoned'] ?? 0, 'sub' => ($summary['abandonment_rate'] ?? 0) . '% del inbound único', 'help' => 'Conversaciones sin respuesta humana cuyo último inbound ocurrió hace más de 24 horas. No implica necesariamente falla operativa: puede incluir cierres naturales del paciente como ok, gracias o adiós.'],
                        ['label' => 'Sin respuesta humana con handoff >24h', 'value' => $summary['conversations_abandoned_with_handoff'] ?? 0, 'sub' => ($summary['conversations_lost_with_handoff'] ?? 0) . ' sin respuesta humana tras handoff', 'help' => 'Subset realmente accionable para operación: conversaciones que sí pidieron ayuda o cayeron en handoff y siguen sin respuesta humana después de 24 horas.'],
                        ['label' => 'Conversaciones resueltas', 'value' => $summary['conversations_resolved'] ?? 0, 'sub' => 'Sin actividad inbound 24h', 'help' => 'Conversaciones atendidas que no han recibido nuevos inbound en las últimas 24 horas.'],
                        ['label' => 'Pico simultáneo', 'value' => $summary['peak_open_conversations'] ?? 0, 'sub' => $summary['peak_open_at'] ?? 'Sin dato', 'help' => 'Máximo de conversaciones abiertas al mismo tiempo detectado dentro del rango analizado.'],
                        ['label' => 'Mensajes inbound', 'value' => $summary['messages_inbound'] ?? 0, 'sub' => 'Recibidos', 'help' => 'Total de mensajes recibidos desde pacientes o contactos en el periodo.'],
                        ['label' => 'Mensajes outbound', 'value' => $summary['messages_outbound'] ?? 0, 'sub' => 'Enviados', 'help' => 'Total de mensajes enviados desde el canal, incluyendo bot, humanos y plantillas.'],
                        ['label' => 'Citas desde WhatsApp', 'value' => $summary['sigcenter_bookings_created'] ?? 0, 'sub' => ($summary['sigcenter_booking_patients'] ?? 0) . ' pacientes · ' . ($summary['sigcenter_booking_failures'] ?? 0) . ' fallidas', 'help' => 'Citas creadas exitosamente desde WhatsApp según integración con Sigcenter, junto con pacientes únicos y fallos registrados.'],
                        ['label' => 'SLA asignación (objetivo: ' . $slaTargetMinutes . ' min)', 'value' => ($summary['sla_assignments_rate'] ?? 0) . '%', 'sub' => ($summary['sla_assignments_in_target'] ?? 0) . '/' . ($summary['sla_assignments_total'] ?? 0) . ' en meta', 'help' => 'Mide tiempo de asignación interna del handoff, no tiempo de respuesta efectiva al paciente.'],
                        ['label' => 'Cola activa', 'value' => $summary['live_queue_total'] ?? 0, 'sub' => 'Cola ' . ($summary['live_queue_queued'] ?? 0) . ' · Asignadas ' . ($summary['live_queue_assigned'] ?? 0), 'help' => 'Conversaciones actualmente en circuito humano: pendientes en cola o ya asignadas a un agente.'],
                        ['label' => 'Ventana 24h abierta', 'value' => $summary['queue_window_open'] ?? 0, 'sub' => ($summary['queue_window_open_rate'] ?? 0) . '% del total', 'help' => 'Conversaciones que todavía pueden recibir mensaje libre sin necesidad de plantilla porque su último inbound sigue dentro de 24 horas.'],
                        ['label' => 'Requiere plantilla', 'value' => $summary['queue_needs_template'] ?? 0, 'sub' => ($summary['queue_needs_template_rate'] ?? 0) . '% del total', 'help' => 'Conversaciones fuera de la ventana de 24 horas que solo pueden reabrirse con plantilla.'],
                        ['label' => 'Esperando plantilla', 'value' => $summary['queue_awaiting_template_reply'] ?? 0, 'sub' => ($summary['queue_awaiting_template_reply_rate'] ?? 0) . '% fuera de ventana', 'help' => 'Conversaciones fuera de ventana donde ya se envió plantilla y todavía no hay respuesta inbound.'],
                        ['label' => 'Transferencias', 'value' => $summary['handoff_transfers'] ?? 0, 'sub' => 'Entre agentes', 'help' => 'Cambios de ownership entre agentes o equipos dentro del proceso de atención humana.'],
                    ];
                @endphp
                @foreach($cards as $card)
                    <div class="wa-kpi-card">
                        <div class="wa-kpi-label-row">
                            <div class="wa-kpi-label">{{ $card['label'] }}</div>
                            @if(!empty($card['help']))
                                <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de {{ $card['label'] }}">
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

        <div class="col-12">
            <div class="wa-kpi-panel">
                <div class="wa-kpi-panel__head">
                    <div class="wa-kpi-title-row">
                        <div class="wa-kpi-sideheading__title">Vista ejecutiva del canal</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Vista ejecutiva del canal">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['executive_view'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Separación macro entre captación, operación, seguimiento clínico y reactivación.</div>
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
                                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de {{ $card['label'] }}">
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
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Mix ejecutivo del canal">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['executive_mix'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Comparativo entre las cuatro líneas principales del WhatsApp por volumen, booking y dependencia de humano.</div>
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
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Captación y conversión del canal">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['channel_capture'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Resumen gerencial de conversaciones nuevas: origen, identificación, handoff y conversión a cita.</div>
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
                        <div class="wa-kpi-sideheading__title">Origen de demanda</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Origen de demanda">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['source_demand'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Cómo se reparte la entrada del canal entre Ads, orgánico y conversaciones iniciadas desde el equipo.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead>
                            <tr>
                                <th>Fuente</th>
                                <th>Conversaciones</th>
                                <th>Participación</th>
                                <th>Identificadas</th>
                                <th>Citas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($analyticsSources as $row)
                                <tr>
                                    <td>{{ $row['source_label'] }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['share'] }}%</td>
                                    <td>{{ $row['identified'] }}</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Outcome de conversaciones</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Outcome de conversaciones">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['conversation_outcomes'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Resultado final más relevante para cada conversación nueva del periodo.</div>
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
                                    <td colspan="3" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Intención inicial</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Intención inicial">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['initial_intent'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Clasificación del primer mensaje útil de cada conversación nueva.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
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
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Tipo de conversación</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Tipo de conversación">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['conversation_type'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Clasificación operativa/comercial del contacto una vez interpretado el contexto de entrada.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
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
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Segmento del paciente</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Segmento del paciente">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['patient_segment'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Distribución entre paciente nuevo, recurrente y reactivado en las conversaciones nuevas.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
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
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Lead scoring</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Lead scoring">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['lead_scoring'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Prioridad comercial estimada por progreso, identificación y cierre efectivo.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
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
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Fricciones del flujo</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Fricciones del flujo">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['frictions'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Estados donde más se frenan conversaciones sin cierre efectivo.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
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
                                    <td colspan="3" class="text-center text-muted py-20">Sin fricciones relevantes en el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Embudo conversacional y comercial</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Embudo conversacional y comercial">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['funnel'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Avance de las conversaciones nuevas desde el inicio hasta la creación efectiva de cita.</div>
                </div>
                <div class="wa-kpi-panel__body">
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
            </div>
        </div>

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
                    <div class="wa-kpi-sideheading__meta">Lectura resumida para gerencia sobre origen, intención, calidad y fricción del canal.</div>
                </div>
                <div class="wa-kpi-panel__body">
                    <div class="wa-kpi-grid">
                        @forelse($analyticsInsights as $insight)
                            <div class="wa-kpi-card">
                                <div class="wa-kpi-label">{{ $insight['title'] ?? 'Insight' }}</div>
                                <div class="wa-kpi-sub" style="margin-top:.85rem; font-size:.92rem; color:#334155;">{{ $insight['body'] ?? '' }}</div>
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
                <div class="wa-kpi-panel__head">
                    <div class="wa-kpi-title-row">
                        <div class="wa-kpi-sideheading__title">Top Ads por citas</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Top Ads por citas">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['ads'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Ranking inicial de anuncios que más conversaciones y citas aportan al canal.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead>
                            <tr>
                                <th>Anuncio</th>
                                <th>Media</th>
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
                                        <div class="text-muted small">{{ $row['source_id'] ?? 'Sin source_id' }}</div>
                                    </td>
                                    <td>{{ $row['media_type'] }}</td>
                                    <td>{{ $row['conversations'] }}</td>
                                    <td>{{ $row['identified'] }}</td>
                                    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                                    <td>{{ $row['handoffs'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-20">No hubo conversaciones atribuibles a Ads en el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Series del periodo</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Series del periodo">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['series'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Lectura rápida del volumen de conversaciones en el rango seleccionado.</div>
                </div>
                <div class="wa-kpi-panel__body">
                    @php
                        $series = is_array($trends['conversations'] ?? null) ? $trends['conversations'] : [];
                        $labels = is_array($trends['labels'] ?? null) ? $trends['labels'] : [];
                        $maxValue = max(1, ...array_map('intval', $series ?: [1]));
                    @endphp
                    <div class="wa-kpi-series-bar">
                        @foreach($series as $value)
                            <span style="height: {{ max(8, (int) round(((int) $value / $maxValue) * 120)) }}px;" title="{{ $value }}"></span>
                        @endforeach
                    </div>
                    <div class="wa-kpi-series-labels">
                        @foreach($labels as $label)
                            <div>{{ \Illuminate\Support\Str::after($label, '2026-') ?: $label }}</div>
                        @endforeach
                    </div>
                    <div class="text-muted mt-10" style="font-size:.82rem;">
                        Inbound {{ $summary['messages_inbound'] ?? 0 }} · Outbound {{ $summary['messages_outbound'] ?? 0 }} · Citas {{ $summary['sigcenter_bookings_created'] ?? 0 }} · Transferencias {{ $summary['handoff_transfers'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-12">
            <div class="wa-kpi-panel">
                <div class="wa-kpi-panel__head">
                    <div class="wa-kpi-title-row">
                        <div class="wa-kpi-sideheading__title">Atención humana por agente</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Atención humana por agente">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['human_by_agent'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Quién absorbió más conversaciones y cómo respondió en primera intervención.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Atendidas</th>
                                <th>1ra respuesta</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($breakdowns['human_attention_by_agent'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['agent_name'] }}</td>
                                    <td>{{ $row['attended_conversations'] }}</td>
                                    <td>{{ $row['avg_first_response_minutes'] !== null ? $row['avg_first_response_minutes'] . ' min' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Handoffs por equipo</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Handoffs por equipo">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['handoffs_by_role'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Distribución de cola, asignación y resolución por equipo operativo.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Total</th>
                                <th>Cola</th>
                                <th>Asignadas</th>
                                <th>Resueltas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($breakdowns['handoffs_by_role'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['role_name'] }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['queued'] }}</td>
                                    <td>{{ $row['assigned'] }}</td>
                                    <td>{{ $row['resolved'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
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
                        <div class="wa-kpi-sideheading__title">Carga por agente</div>
                        <button type="button" class="wa-kpi-help" aria-label="Ver ayuda de Carga por agente">
                            ?
                            <span class="wa-kpi-help__tooltip">{{ $sectionHelp['agent_load'] }}</span>
                        </button>
                    </div>
                    <div class="wa-kpi-sideheading__meta">Lectura de workload para saber quién está tomado, activo o ya resolvió.</div>
                </div>
                <div class="wa-kpi-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-kpi-table mb-0">
                            <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Asignadas</th>
                                <th>Activas</th>
                                <th>Resueltas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($breakdowns['handoffs_by_agent'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['agent_name'] }}</td>
                                    <td>{{ $row['assigned_count'] }}</td>
                                    <td>{{ $row['active_count'] }}</td>
                                    <td>{{ $row['resolved_count'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-20">Sin datos para el rango actual.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
