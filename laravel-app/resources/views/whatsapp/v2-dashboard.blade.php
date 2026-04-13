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
    .wa-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: .75rem;
    }
    .wa-kpi-card {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 16px;
        padding: 1rem;
    }
    .wa-kpi-label {
        font-size: .75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em;
    }
    .wa-kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
        margin-top: .35rem;
    }
    .wa-kpi-sub {
        margin-top: .35rem;
        font-size: .82rem;
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
        border-radius: 18px;
        padding: 1rem 1.1rem;
        min-height: 100%;
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
</style>
@endpush

@section('content')
<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="box mb-0">
                <div class="box-body d-flex flex-wrap justify-content-between align-items-start gap-15">
                    <div>
                        <div class="text-uppercase text-muted" style="font-size:12px; letter-spacing:.08em;">WhatsApp V2</div>
                        <h2 class="mb-5">KPI y reportes</h2>
                        <div class="text-muted">Primera paridad del dashboard legacy sobre Laravel, priorizando atención humana, SLA, cola y transferencias.</div>
                    </div>
                </div>
                <div class="box-footer bg-transparent">
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
            <div class="box mb-0">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Series del periodo</h4>
                </div>
                <div class="box-body">
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
            <div class="box mb-0">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Atención humana por agente</h4>
                </div>
                <div class="box-body p-0">
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
            <div class="box mb-0">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Handoffs por equipo</h4>
                </div>
                <div class="box-body p-0">
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
            <div class="box mb-0">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Carga por agente</h4>
                </div>
                <div class="box-body p-0">
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
