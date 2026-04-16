@extends('layouts.medforge')

@php
    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
    $trends = is_array($dashboard['trends'] ?? null) ? $dashboard['trends'] : [];
    $breakdowns = is_array($dashboard['breakdowns'] ?? null) ? $dashboard['breakdowns'] : [];
    $options = is_array($dashboard['options'] ?? null) ? $dashboard['options'] : ['roles' => [], 'agents' => []];
    $filters = is_array($filters ?? null) ? $filters : [];
    $exportQuery = http_build_query(array_filter([
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'role_id' => $filters['role_id'] ?? null,
        'agent_id' => $filters['agent_id'] ?? null,
        'sla_target_minutes' => $filters['sla_target_minutes'] ?? null,
    ], static fn ($value) => $value !== null && $value !== ''));
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
                        <div class="wa-dashboard-pagebar__title">KPI y reportes</div>
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
                        </div>
                    </form>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 col-12">
            <div class="wa-kpi-band">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Supervisor</div>
                <h5>Salud operativa</h5>
                <div class="muted">Atención {{ $summary['attention_rate'] ?? 0 }}% · Pérdida {{ $summary['loss_rate'] ?? 0 }}%</div>
                <div class="mt-10 fw-600">Cola {{ $summary['live_queue_total'] ?? 0 }} · SLA {{ $summary['sla_assignments_rate'] ?? 0 }}%</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 col-12">
            <div class="wa-kpi-band" style="background:linear-gradient(135deg, #14532d 0%, #166534 100%);">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Ventana</div>
                <h5>Conversaciones fuera de 24h</h5>
                <div class="muted">Requieren plantilla {{ $summary['queue_needs_template'] ?? 0 }}</div>
                <div class="mt-10 fw-600">Esperando respuesta a plantilla {{ $summary['queue_awaiting_template_reply'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12 col-12">
            <div class="wa-kpi-band" style="background:linear-gradient(135deg, #7c2d12 0%, #9a3412 100%);">
                <div class="text-uppercase muted" style="letter-spacing:.08em;">Acciones rápidas</div>
                <h5>Drilldown API</h5>
                <div class="wa-kpi-link-list mt-10">
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_lost&{{ $exportQuery }}" target="_blank" rel="noopener">Perdidas</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=conversations_attended_human&{{ $exportQuery }}" target="_blank" rel="noopener">Atendidas</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=queue_needs_template&{{ $exportQuery }}" target="_blank" rel="noopener">Plantillas</a>
                    <a class="btn btn-sm btn-light" href="/v2/whatsapp/api/kpis/drilldown?metric=sla_assignments_total&{{ $exportQuery }}" target="_blank" rel="noopener">SLA</a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-kpi-grid">
                @php
                    $cards = [
                        ['label' => 'Personas que escribieron', 'value' => $summary['people_inbound'] ?? 0, 'sub' => 'Números únicos inbound'],
                        ['label' => 'Conversaciones atendidas', 'value' => $summary['conversations_attended_human'] ?? 0, 'sub' => ($summary['people_attended_human'] ?? 0) . ' personas atendidas'],
                        ['label' => 'Conversaciones perdidas', 'value' => $summary['conversations_lost'] ?? 0, 'sub' => ($summary['people_lost'] ?? 0) . ' personas · ' . ($summary['loss_rate'] ?? 0) . '%'],
                        ['label' => 'Tasa de atención', 'value' => ($summary['attention_rate'] ?? 0) . '%', 'sub' => 'Atendidas / personas inbound'],
                        ['label' => '1ra respuesta humana', 'value' => isset($summary['avg_first_human_response_minutes']) ? $summary['avg_first_human_response_minutes'] . ' min' : '—', 'sub' => 'Promedio'],
                        ['label' => 'Conversaciones abandonadas', 'value' => $summary['conversations_abandoned'] ?? 0, 'sub' => ($summary['abandonment_rate'] ?? 0) . '%'],
                        ['label' => 'Conversaciones resueltas', 'value' => $summary['conversations_resolved'] ?? 0, 'sub' => 'Sin actividad inbound 24h'],
                        ['label' => 'Pico simultáneo', 'value' => $summary['peak_open_conversations'] ?? 0, 'sub' => $summary['peak_open_at'] ?? 'Sin dato'],
                        ['label' => 'Mensajes inbound', 'value' => $summary['messages_inbound'] ?? 0, 'sub' => 'Recibidos'],
                        ['label' => 'Mensajes outbound', 'value' => $summary['messages_outbound'] ?? 0, 'sub' => 'Enviados'],
                        ['label' => 'SLA asignación', 'value' => ($summary['sla_assignments_rate'] ?? 0) . '%', 'sub' => ($summary['sla_assignments_in_target'] ?? 0) . '/' . ($summary['sla_assignments_total'] ?? 0) . ' en meta'],
                        ['label' => 'Cola activa', 'value' => $summary['live_queue_total'] ?? 0, 'sub' => 'Cola ' . ($summary['live_queue_queued'] ?? 0) . ' · Asignadas ' . ($summary['live_queue_assigned'] ?? 0)],
                        ['label' => 'Ventana 24h abierta', 'value' => $summary['queue_window_open'] ?? 0, 'sub' => ($summary['queue_window_open_rate'] ?? 0) . '% del total'],
                        ['label' => 'Requiere plantilla', 'value' => $summary['queue_needs_template'] ?? 0, 'sub' => ($summary['queue_needs_template_rate'] ?? 0) . '% del total'],
                        ['label' => 'Esperando plantilla', 'value' => $summary['queue_awaiting_template_reply'] ?? 0, 'sub' => ($summary['queue_awaiting_template_reply_rate'] ?? 0) . '% fuera de ventana'],
                        ['label' => 'Transferencias', 'value' => $summary['handoff_transfers'] ?? 0, 'sub' => 'Entre agentes'],
                    ];
                @endphp
                @foreach($cards as $card)
                    <div class="wa-kpi-card">
                        <div class="wa-kpi-label">{{ $card['label'] }}</div>
                        <div class="wa-kpi-value">{{ $card['value'] }}</div>
                        <div class="wa-kpi-sub">{{ $card['sub'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-xl-6 col-12">
            <div class="wa-kpi-panel">
                <div class="wa-kpi-panel__head">
                    <div class="wa-kpi-sideheading__title">Series del periodo</div>
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
                        Inbound {{ $summary['messages_inbound'] ?? 0 }} · Outbound {{ $summary['messages_outbound'] ?? 0 }} · Transferencias {{ $summary['handoff_transfers'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-12">
            <div class="wa-kpi-panel">
                <div class="wa-kpi-panel__head">
                    <div class="wa-kpi-sideheading__title">Atención humana por agente</div>
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
                    <div class="wa-kpi-sideheading__title">Handoffs por equipo</div>
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
                    <div class="wa-kpi-sideheading__title">Carga por agente</div>
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
