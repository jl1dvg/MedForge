@extends('layouts.medforge')

@php
    $flowmaker = is_array($flowmaker ?? null) ? $flowmaker : [];
    $flow = is_array($flowmaker['flow'] ?? null) ? $flowmaker['flow'] : null;
    $activeVersion = is_array($flowmaker['active_version'] ?? null) ? $flowmaker['active_version'] : null;
    $versions = is_array($flowmaker['versions'] ?? null) ? $flowmaker['versions'] : [];
    $stats = is_array($flowmaker['stats'] ?? null) ? $flowmaker['stats'] : [];
    $sessions = is_array($flowmaker['sessions'] ?? null) ? $flowmaker['sessions'] : [];
    $contract = is_array($contract ?? null) ? $contract : [];
    $schema = is_array($contract['schema'] ?? null) ? $contract['schema'] : [];
    $scenarios = is_array($schema['scenarios'] ?? null) ? array_values(array_filter($schema['scenarios'], 'is_array')) : [];
@endphp

@push('styles')
<style>
    .wa-flow-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(16, 185, 129, .16), transparent 34%),
            radial-gradient(circle at top right, rgba(14, 165, 233, .14), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 48%, #115e59 100%);
        color: #f8fafc;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
    }
    .wa-flow-pagebar__top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }
    .wa-flow-pagebar__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.03em;
    }
    .wa-flow-pagebar__subtitle {
        margin-top: 8px;
        color: rgba(248, 250, 252, .82);
        max-width: 780px;
        font-size: 14px;
        line-height: 1.6;
    }
    .wa-flow-pagebar__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .wa-flow-hero-pill {
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
    .wa-flow-shell {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr) 360px;
        gap: 18px;
        align-items: start;
    }
    .wa-flow-panel {
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .wa-flow-panel__head {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background:
            radial-gradient(circle at top right, rgba(15, 118, 110, .08), transparent 40%),
            #fff;
    }
    .wa-flow-panel__body {
        padding: 18px 20px;
    }
    .wa-flow-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .wa-flow-kpi {
        padding: 16px;
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        border: 1px solid rgba(148, 163, 184, .16);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-flow-kpi__label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-flow-kpi__value {
        margin-top: .45rem;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
        line-height: 1;
    }
    .wa-flow-kpi__sub {
        margin-top: .45rem;
        font-size: 12px;
        color: #64748b;
    }
    .wa-flow-sideheading__title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }
    .wa-flow-sideheading__meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }
    .wa-flow-search {
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: #fff;
        width: 100%;
        padding: .8rem .9rem;
        font-size: .92rem;
    }
    .wa-flow-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 740px;
        overflow: auto;
    }
    .wa-flow-item {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 18px;
        padding: 14px;
        background: #fff;
        cursor: pointer;
        text-align: left;
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .wa-flow-item:hover {
        transform: translateY(-1px);
        border-color: rgba(15, 118, 110, .35);
        box-shadow: 0 12px 24px rgba(15, 23, 42, .06);
    }
    .wa-flow-item.is-active {
        border-color: #0f766e;
        background: linear-gradient(180deg, rgba(15, 118, 110, .08), rgba(255, 255, 255, 1));
        box-shadow: 0 14px 28px rgba(15, 118, 110, .12);
    }
    .wa-flow-item__top {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        align-items: start;
    }
    .wa-flow-item__name {
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
        margin-bottom: .25rem;
    }
    .wa-flow-item__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .45rem;
    }
    .wa-flow-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        border-radius: 999px;
        padding: .22rem .6rem;
        font-size: 11px;
        font-weight: 700;
    }
    .wa-flow-badge--stage {
        background: rgba(37, 99, 235, .10);
        color: #1d4ed8;
    }
    .wa-flow-badge--menu {
        background: rgba(15, 118, 110, .10);
        color: #0f766e;
    }
    .wa-flow-badge--count {
        background: rgba(71, 85, 105, .10);
        color: #475569;
    }
    .wa-flow-canvas {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .wa-flow-stage {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, .10);
        color: #1d4ed8;
        padding: .3rem .7rem;
        font-size: .74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .wa-flow-block {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 20px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-flow-block__head {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        background: radial-gradient(circle at top left, rgba(14, 165, 233, .05), transparent 32%), #f8fafc;
    }
    .wa-flow-block__body {
        padding: 16px;
    }
    .wa-flow-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .wa-flow-chip {
        border-radius: 14px;
        padding: .6rem .8rem;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, .14);
        font-size: 12px;
        color: #0f172a;
    }
    .wa-flow-action {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, #ffffff, #fbfdff);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .wa-flow-action__top {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        align-items: start;
    }
    .wa-flow-action__label {
        font-weight: 800;
        color: #0f172a;
    }
    .wa-flow-code {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 16px;
        padding: 1rem;
        font-size: .78rem;
        line-height: 1.5;
        max-height: 360px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .wa-flow-preview {
        background: radial-gradient(circle at top right, rgba(14,165,233,.05), transparent 24%), #f8fafc;
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 16px;
        padding: 1rem;
        min-height: 200px;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: .82rem;
        color: #0f172a;
    }
    .wa-flow-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .wa-flow-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .75rem;
    }
    .wa-flow-timeline {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        max-height: 240px;
        overflow: auto;
    }
    .wa-flow-timeline__item {
        border-left: 3px solid rgba(15, 118, 110, .35);
        padding-left: .75rem;
    }
    .wa-flow-table td, .wa-flow-table th {
        font-size: .84rem;
        vertical-align: middle;
    }
    .wa-flow-stack {
        display: grid;
        gap: 18px;
    }
    .wa-flow-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }
    .wa-flow-inline-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .6rem;
    }
    .wa-flow-editor-field label {
        display: block;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        margin-bottom: .35rem;
    }
    .wa-flow-editor-field input,
    .wa-flow-editor-field select,
    .wa-flow-editor-field textarea {
        width: 100%;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, .12);
        padding: .65rem .75rem;
        font-size: .88rem;
        background: #fff;
    }
    .wa-flow-editor-field textarea {
        min-height: 96px;
        resize: vertical;
    }
    @media (max-width: 1400px) {
        .wa-flow-shell {
            grid-template-columns: 280px minmax(0, 1fr);
        }
        .wa-flow-shell > .wa-flow-panel:last-child {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 992px) {
        .wa-flow-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .wa-flow-shell {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 767px) {
        .wa-flow-pagebar {
            padding: 20px 18px;
            border-radius: 24px;
        }
        .wa-flow-pagebar__top {
            flex-direction: column;
        }
        .wa-flow-panel__head,
        .wa-flow-panel__body {
            padding: 16px;
        }
        .wa-flow-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="wa-flow-pagebar">
                <div class="wa-flow-pagebar__top">
                    <div>
                        <div class="wa-flow-pagebar__title">Flowmaker y automatización</div>
                        <div class="wa-flow-pagebar__subtitle">
                            Consola operativa para revisar escenarios, editar condiciones y acciones, publicar versiones y medir paridad con legacy antes del corte real.
                        </div>
                    </div>
                    <div class="wa-flow-pagebar__meta">
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-graph-outline"></i> {{ $flow['status'] ?? 'sin-configurar' }}</span>
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-source-branch"></i> versión {{ $activeVersion['version'] ?? '—' }}</span>
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-lightning-bolt-outline"></i> shadow listo</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-10 align-items-center mt-18">
                    <a href="/v2/whatsapp/api/flowmaker/contract" target="_blank" rel="noopener" class="btn btn-primary">Ver contrato JSON</a>
                    <button type="button" class="btn btn-success" id="wa-flow-publish-btn">Publicar JSON</button>
                    <span id="wa-flow-status" class="text-light" style="font-size:.84rem;"></span>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-flow-kpis">
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Versión activa</div>
                    <div class="wa-flow-kpi__value">{{ $activeVersion['version'] ?? '—' }}</div>
                    <div class="wa-flow-kpi__sub">{{ $activeVersion['published_at'] ?? 'Sin publicación' }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Escenarios</div>
                    <div class="wa-flow-kpi__value">{{ count($scenarios) }}</div>
                    <div class="wa-flow-kpi__sub">Steps {{ $stats['steps'] ?? 0 }} · Acciones {{ $stats['actions'] ?? 0 }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Sesiones activas</div>
                    <div class="wa-flow-kpi__value">{{ $stats['active_sessions'] ?? 0 }}</div>
                    <div class="wa-flow-kpi__sub">Input {{ $stats['sessions_waiting_input'] ?? 0 }} · Response {{ $stats['sessions_waiting_response'] ?? 0 }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Filtros y horarios</div>
                    <div class="wa-flow-kpi__value">{{ $stats['filters'] ?? 0 }}</div>
                    <div class="wa-flow-kpi__sub">Schedules {{ $stats['schedules'] ?? 0 }} · Transiciones {{ $stats['transitions'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-flow-shell">
                <div class="wa-flow-panel">
                    <div class="wa-flow-panel__head">
                        <div class="d-flex justify-content-between align-items-center gap-10">
                            <div>
                                <div class="wa-flow-sideheading__title">Escenarios</div>
                                <div class="wa-flow-sideheading__meta">Selector lateral con la lógica publicada y acceso rápido al editor.</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-scenario-btn">Nuevo</button>
                        </div>
                    </div>
                    <div class="wa-flow-panel__body">
                        <input type="text" class="wa-flow-search mb-15" id="wa-flow-search" placeholder="Buscar escenario, stage o acción">
                        <div class="wa-flow-list" id="wa-flow-scenario-list"></div>
                    </div>
                </div>

                <div class="wa-flow-panel">
                    <div class="wa-flow-panel__head d-flex justify-content-between align-items-center gap-10">
                        <div>
                            <div class="wa-flow-sideheading__title" id="wa-flow-canvas-title">Escenario</div>
                            <div class="wa-flow-sideheading__meta" id="wa-flow-canvas-subtitle">Selecciona un escenario para revisar condiciones y acciones.</div>
                        </div>
                        <span class="wa-flow-stage" id="wa-flow-stage-badge">Sin stage</span>
                    </div>
                    <div class="wa-flow-panel__body">
                        <div class="wa-flow-canvas" id="wa-flow-canvas">
                            <div class="wa-flow-empty">No hay escenarios configurados.</div>
                        </div>
                    </div>
                </div>

                <div class="wa-flow-panel">
                    <div class="wa-flow-panel__head">
                        <div class="wa-flow-sideheading__title">Inspector</div>
                        <div class="wa-flow-sideheading__meta">Payload, simulación, shadow compare y readiness en un solo bloque operativo.</div>
                    </div>
                    <div class="wa-flow-panel__body">
                        <div class="wa-flow-stack">
                            <div>
                                <div class="wa-flow-section-title">Resumen</div>
                                <div class="wa-flow-chip-row" id="wa-flow-inspector-summary">
                                    <div class="wa-flow-chip">Sin selección</div>
                                </div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Payload a publicar</div>
                                <textarea id="wa-flow-payload" class="form-control" rows="12">{{ json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Simular mensaje</div>
                                <div class="mb-10">
                                    <label class="form-label">Número</label>
                                    <input id="wa-flow-sim-number" class="form-control" value="{{ $sessions[0]['wa_number'] ?? '593999111222' }}">
                                </div>
                                <div class="mb-10">
                                    <label class="form-label">Mensaje</label>
                                    <input id="wa-flow-sim-text" class="form-control" value="hola">
                                </div>
                                <div class="mb-10">
                                    <label class="form-label">Contexto JSON opcional</label>
                                    <textarea id="wa-flow-sim-context" class="form-control" rows="5">{}</textarea>
                                </div>
                                <div class="d-flex flex-wrap gap-10">
                                    <button type="button" class="btn btn-primary" id="wa-flow-sim-btn">Simular mensaje</button>
                                    <button type="button" class="btn btn-outline-dark" id="wa-flow-compare-btn">Comparar con legacy</button>
                                </div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Resultado de simulación</div>
                                <div class="wa-flow-preview" id="wa-flow-sim-output">Ejecuta una simulación para ver escenario matcheado, facts y acciones disparadas sin tocar el webhook real.</div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Shadow compare</div>
                                <div class="wa-flow-preview" id="wa-flow-compare-output">Comparar con legacy ayuda a validar paridad antes de mover el runtime del webhook.</div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title d-flex justify-content-between align-items-center">
                                    <span>Fase 6 está lista para cierre</span>
                                    <button type="button" class="btn btn-xs btn-outline-dark" id="wa-flow-shadow-refresh-btn">Actualizar</button>
                                </div>
                                <div class="wa-flow-preview" id="wa-flow-readiness-output">Todavía no se evaluó si Fase 6 está lista para cierre.</div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Paridad del shadow runtime</div>
                                <div class="wa-flow-preview" id="wa-flow-shadow-summary-output">Todavía no se cargó el resumen de paridad del shadow runtime.</div>
                            </div>

                            <div>
                                <div class="wa-flow-section-title">Shadow runs recientes</div>
                                <div class="wa-flow-preview" id="wa-flow-shadow-runs-output">Todavía no se cargan runs del webhook en modo sombra.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-12">
            <div class="wa-flow-panel">
                <div class="wa-flow-panel__head">
                    <div class="wa-flow-sideheading__title">Versiones recientes</div>
                    <div class="wa-flow-sideheading__meta">Historial rápido de publicación y estado.</div>
                </div>
                <div class="wa-flow-panel__body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped wa-flow-table mb-0">
                            <thead>
                            <tr>
                                <th>Versión</th>
                                <th>Estado</th>
                                <th>Publicada</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($versions as $version)
                                <tr>
                                    <td>{{ $version['version'] ?? '—' }}</td>
                                    <td>{{ $version['status'] ?? '—' }}</td>
                                    <td>{{ $version['published_at'] ?? $version['created_at'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-20">Aún no hay versiones publicadas.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-12">
            <div class="wa-flow-panel">
                <div class="wa-flow-panel__head">
                    <div class="wa-flow-sideheading__title">Sesiones activas</div>
                    <div class="wa-flow-sideheading__meta">Conversaciones en curso y punto actual del runtime.</div>
                </div>
                <div class="wa-flow-panel__body">
                    <div class="wa-flow-timeline">
                        @forelse($sessions as $session)
                            <div class="wa-flow-timeline__item">
                                <div class="fw-700">{{ $session['wa_number'] }}</div>
                                <div class="small text-muted">{{ $session['scenario_id'] ?? '—' }} · {{ $session['awaiting'] ?? '—' }}</div>
                                <div class="small text-muted">{{ $session['last_interaction_at'] ?? '—' }}</div>
                            </div>
                        @empty
                            <div class="wa-flow-empty">No hay sesiones activas.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialSchema = @json($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const publishButton = document.getElementById('wa-flow-publish-btn');
    const payloadField = document.getElementById('wa-flow-payload');
    const statusNode = document.getElementById('wa-flow-status');
    const scenarioList = document.getElementById('wa-flow-scenario-list');
    const searchInput = document.getElementById('wa-flow-search');
    const addScenarioButton = document.getElementById('wa-flow-add-scenario-btn');
    const canvas = document.getElementById('wa-flow-canvas');
    const canvasTitle = document.getElementById('wa-flow-canvas-title');
    const canvasSubtitle = document.getElementById('wa-flow-canvas-subtitle');
    const stageBadge = document.getElementById('wa-flow-stage-badge');
    const inspectorSummary = document.getElementById('wa-flow-inspector-summary');
    const simButton = document.getElementById('wa-flow-sim-btn');
    const compareButton = document.getElementById('wa-flow-compare-btn');
    const shadowRefreshButton = document.getElementById('wa-flow-shadow-refresh-btn');
    const simNumber = document.getElementById('wa-flow-sim-number');
    const simText = document.getElementById('wa-flow-sim-text');
    const simContext = document.getElementById('wa-flow-sim-context');
    const simOutput = document.getElementById('wa-flow-sim-output');
    const compareOutput = document.getElementById('wa-flow-compare-output');
    const shadowRunsOutput = document.getElementById('wa-flow-shadow-runs-output');
    const shadowSummaryOutput = document.getElementById('wa-flow-shadow-summary-output');
    const readinessOutput = document.getElementById('wa-flow-readiness-output');

    let editorSchema = JSON.parse(JSON.stringify(initialSchema || {}));
    if (!Array.isArray(editorSchema.scenarios)) {
        editorSchema.scenarios = [];
    }
    let selectedScenarioId = editorSchema.scenarios[0]?.id || null;

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const pretty = (value) => JSON.stringify(value ?? {}, null, 2);
    const getScenarios = () => Array.isArray(editorSchema.scenarios) ? editorSchema.scenarios : [];
    const selectedScenario = () => getScenarios().find((item) => String(item?.id) === String(selectedScenarioId)) || null;
    const safeId = () => 'scenario_' + Math.random().toString(36).slice(2, 8);

    const actionLabel = (action) => {
        const type = String(action?.type || 'accion');
        return type.replaceAll('_', ' ');
    };

    const syncPayloadField = () => {
        if (payloadField) {
            payloadField.value = JSON.stringify(editorSchema, null, 2);
        }
    };

    const ensureScenarioShape = (scenario) => {
        if (!Array.isArray(scenario.conditions)) {
            scenario.conditions = [];
        }
        if (!Array.isArray(scenario.actions)) {
            scenario.actions = [];
        }
        if (!Array.isArray(scenario.transitions)) {
            scenario.transitions = [];
        }
    };

    const addScenario = () => {
        const scenario = {
            id: safeId(),
            name: 'Nuevo escenario',
            description: '',
            stage: 'custom',
            intercept_menu: false,
            conditions: [],
            actions: [],
            transitions: [],
        };
        getScenarios().push(scenario);
        selectedScenarioId = scenario.id;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const duplicateScenario = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }

        const clone = JSON.parse(JSON.stringify(scenario));
        clone.id = safeId();
        clone.name = `${clone.name || 'Escenario'} copia`;
        getScenarios().push(clone);
        selectedScenarioId = clone.id;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const removeScenario = () => {
        const index = getScenarios().findIndex((item) => String(item?.id) === String(selectedScenarioId));
        if (index === -1) {
            return;
        }
        getScenarios().splice(index, 1);
        selectedScenarioId = getScenarios()[Math.max(0, index - 1)]?.id || getScenarios()[0]?.id || null;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const addCondition = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        ensureScenarioShape(scenario);
        scenario.conditions.push({type: 'always'});
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const addAction = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        ensureScenarioShape(scenario);
        scenario.actions.push({
            type: 'send_message',
            message: {type: 'text', body: 'Nuevo mensaje'},
        });
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const removeCondition = (index) => {
        const scenario = selectedScenario();
        if (!scenario || !Array.isArray(scenario.conditions)) {
            return;
        }
        scenario.conditions.splice(index, 1);
        syncPayloadField();
        renderScenarioCanvas();
    };

    const removeAction = (index) => {
        const scenario = selectedScenario();
        if (!scenario || !Array.isArray(scenario.actions)) {
            return;
        }
        scenario.actions.splice(index, 1);
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const renderScenarioList = () => {
        if (!scenarioList) {
            return;
        }

        const term = (searchInput?.value || '').trim().toLowerCase();
        const rows = getScenarios().filter((scenario) => {
            if (!term) {
                return true;
            }

            const haystack = [
                scenario.id,
                scenario.name,
                scenario.stage,
                ...(Array.isArray(scenario.actions) ? scenario.actions.map((item) => item?.type) : []),
            ].join(' ').toLowerCase();

            return haystack.includes(term);
        });

        if (!rows.length) {
            scenarioList.innerHTML = '<div class="wa-flow-empty">No se encontraron escenarios para ese filtro.</div>';
            return;
        }

        scenarioList.innerHTML = rows.map((scenario) => {
            const isActive = scenario.id === selectedScenarioId;
            const actionCount = Array.isArray(scenario.actions) ? scenario.actions.length : 0;
            const conditionCount = Array.isArray(scenario.conditions) ? scenario.conditions.length : 0;

            return `
                <button type="button" class="wa-flow-item ${isActive ? 'is-active' : ''}" data-scenario-id="${escapeHtml(scenario.id)}">
                    <div class="wa-flow-item__top">
                        <div>
                            <div class="wa-flow-item__name">${escapeHtml(scenario.name || scenario.id || 'Escenario')}</div>
                            <div class="small text-muted">${escapeHtml(scenario.description || 'Sin descripción')}</div>
                        </div>
                    </div>
                    <div class="wa-flow-item__meta">
                        <span class="wa-flow-badge wa-flow-badge--stage">${escapeHtml(scenario.stage || 'custom')}</span>
                        ${scenario.intercept_menu ? '<span class="wa-flow-badge wa-flow-badge--menu">menu</span>' : ''}
                        <span class="wa-flow-badge wa-flow-badge--count">${actionCount} acciones</span>
                        <span class="wa-flow-badge wa-flow-badge--count">${conditionCount} condiciones</span>
                    </div>
                </button>
            `;
        }).join('');

        scenarioList.querySelectorAll('[data-scenario-id]').forEach((node) => {
            node.addEventListener('click', () => {
                selectedScenarioId = node.getAttribute('data-scenario-id');
                renderScenarioList();
                renderScenarioCanvas();
            });
        });
    };

    const renderScenarioCanvas = () => {
        if (!canvas || !canvasTitle || !canvasSubtitle || !stageBadge || !inspectorSummary) {
            return;
        }

        const scenario = selectedScenario();
        if (!scenario) {
            canvasTitle.textContent = 'Escenario';
            canvasSubtitle.textContent = 'Selecciona un escenario para revisar condiciones y acciones.';
            stageBadge.textContent = 'Sin stage';
            inspectorSummary.innerHTML = '<div class="wa-flow-chip">Sin selección</div>';
            canvas.innerHTML = '<div class="wa-flow-empty">Selecciona un escenario del listado lateral.</div>';
            return;
        }

        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
        const transitions = Array.isArray(scenario.transitions) ? scenario.transitions : [];

        canvasTitle.textContent = scenario.name || scenario.id || 'Escenario';
        canvasSubtitle.textContent = scenario.description || 'Sin descripción adicional.';
        stageBadge.textContent = scenario.stage || 'custom';

        inspectorSummary.innerHTML = [
            `<div class="wa-flow-chip"><strong>ID:</strong> ${escapeHtml(scenario.id || '—')}</div>`,
            `<div class="wa-flow-chip"><strong>Stage:</strong> ${escapeHtml(scenario.stage || 'custom')}</div>`,
            `<div class="wa-flow-chip"><strong>Acciones:</strong> ${actions.length}</div>`,
            `<div class="wa-flow-chip"><strong>Condiciones:</strong> ${conditions.length}</div>`,
            scenario.intercept_menu ? '<div class="wa-flow-chip"><strong>Intercepta menú:</strong> sí</div>' : '',
        ].filter(Boolean).join('');

        const conditionHtml = conditions.length
            ? `<div class="d-flex flex-column gap-10">${conditions.map((condition, index) => `
                <div class="wa-flow-action">
                    <div class="wa-flow-action__top">
                        <div class="wa-flow-action__label">Condición ${index + 1}</div>
                        <button type="button" class="btn btn-xs btn-outline-danger" data-remove-condition="${index}">Quitar</button>
                    </div>
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Tipo</label>
                            <select data-condition-field="${index}" data-field="type">
                                ${['always','is_first_time','has_consent','state_is','awaiting_is','message_in','message_contains','message_matches','last_interaction_gt','patient_found','context_flag'].map((type) => `
                                    <option value="${type}" ${condition.type === type ? 'selected' : ''}>${type}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Valor</label>
                            <input type="text" data-condition-field="${index}" data-field="value" value="${escapeHtml(condition.value ?? '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Minutos</label>
                            <input type="number" min="0" data-condition-field="${index}" data-field="minutes" value="${escapeHtml(condition.minutes ?? '')}">
                        </div>
                    </div>
                </div>
            `).join('')}</div>`
            : '<div class="text-muted">Este escenario no tiene condiciones explícitas. Funciona como regla directa.</div>';

        const actionHtml = actions.length
            ? actions.map((action, index) => {
                const messageBody = action?.message?.body ?? action?.message ?? '';
                const templateName = action?.template?.name ?? action?.template ?? '';
                const actionValue = messageBody || templateName || action?.state || '';
                return `
                <div class="wa-flow-action">
                    <div class="wa-flow-action__top">
                        <div class="wa-flow-action__label">${index + 1}. ${escapeHtml(actionLabel(action))}</div>
                        <div class="wa-flow-inline-actions">
                            <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(action.type || 'accion')}</span>
                            <button type="button" class="btn btn-xs btn-outline-danger" data-remove-action="${index}">Quitar</button>
                        </div>
                    </div>
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Tipo</label>
                            <select data-action-field="${index}" data-field="type">
                                ${['send_message','send_buttons','send_list','send_template','send_sequence','set_state','set_context','store_consent','handoff_agent'].map((type) => `
                                    <option value="${type}" ${action.type === type ? 'selected' : ''}>${type}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Valor principal</label>
                            <textarea data-action-field="${index}" data-field="value">${escapeHtml(actionValue)}</textarea>
                        </div>
                    </div>
                    <div class="wa-flow-code">${escapeHtml(pretty(action))}</div>
                </div>
            `; }).join('')
            : '<div class="text-muted">Este escenario todavía no tiene acciones publicadas.</div>';

        const transitionHtml = transitions.length
            ? `<div class="wa-flow-chip-row">${transitions.map((transition) => `
                <div class="wa-flow-chip">
                    <strong>${escapeHtml(transition.target || transition.to || 'siguiente')}</strong>
                    ${transition.condition ? `<div>${escapeHtml(pretty(transition.condition))}</div>` : ''}
                </div>
            `).join('')}</div>`
            : '<div class="text-muted">Las transiciones visibles todavía se derivan del contrato publicado.</div>';

        canvas.innerHTML = `
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div class="fw-700">Configuración del escenario</div>
                    <div class="wa-flow-inline-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-duplicate-scenario-btn">Duplicar</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="wa-flow-remove-scenario-btn">Eliminar</button>
                    </div>
                </div>
                <div class="wa-flow-block__body">
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>ID</label>
                            <input type="text" id="wa-flow-edit-id" value="${escapeHtml(scenario.id || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Nombre</label>
                            <input type="text" id="wa-flow-edit-name" value="${escapeHtml(scenario.name || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Stage</label>
                            <select id="wa-flow-edit-stage">
                                ${['arrival','validation','consent','menu','scheduling','results','post','custom'].map((stage) => `
                                    <option value="${stage}" ${scenario.stage === stage ? 'selected' : ''}>${stage}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Intercepta menú</label>
                            <select id="wa-flow-edit-intercept">
                                <option value="0" ${!scenario.intercept_menu ? 'selected' : ''}>No</option>
                                <option value="1" ${scenario.intercept_menu ? 'selected' : ''}>Sí</option>
                            </select>
                        </div>
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Descripción</label>
                            <textarea id="wa-flow-edit-description">${escapeHtml(scenario.description || '')}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div class="fw-700">Condiciones de entrada</div>
                    <div class="wa-flow-inline-actions">
                        <span class="wa-flow-badge wa-flow-badge--count">${conditions.length}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-condition-btn">Agregar condición</button>
                    </div>
                </div>
                <div class="wa-flow-block__body">${conditionHtml}</div>
            </div>
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div class="fw-700">Secuencia de acciones</div>
                    <div class="wa-flow-inline-actions">
                        <span class="wa-flow-badge wa-flow-badge--count">${actions.length}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-action-btn">Agregar acción</button>
                    </div>
                </div>
                <div class="wa-flow-block__body"><div class="d-flex flex-column gap-10">${actionHtml}</div></div>
            </div>
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div class="fw-700">Transiciones y handoff</div>
                    <span class="wa-flow-badge wa-flow-badge--count">${transitions.length}</span>
                </div>
                <div class="wa-flow-block__body">${transitionHtml}</div>
            </div>
        `;

        canvas.querySelector('#wa-flow-edit-id')?.addEventListener('input', (event) => {
            scenario.id = event.target.value.trim();
            selectedScenarioId = scenario.id;
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-name')?.addEventListener('input', (event) => {
            scenario.name = event.target.value;
            syncPayloadField();
            renderScenarioList();
        });
        canvas.querySelector('#wa-flow-edit-stage')?.addEventListener('change', (event) => {
            scenario.stage = event.target.value;
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-intercept')?.addEventListener('change', (event) => {
            scenario.intercept_menu = event.target.value === '1';
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-description')?.addEventListener('input', (event) => {
            scenario.description = event.target.value;
            syncPayloadField();
            renderScenarioList();
        });
        canvas.querySelector('#wa-flow-duplicate-scenario-btn')?.addEventListener('click', duplicateScenario);
        canvas.querySelector('#wa-flow-remove-scenario-btn')?.addEventListener('click', removeScenario);
        canvas.querySelector('#wa-flow-add-condition-btn')?.addEventListener('click', addCondition);
        canvas.querySelector('#wa-flow-add-action-btn')?.addEventListener('click', addAction);

        canvas.querySelectorAll('[data-condition-field]').forEach((node) => {
            node.addEventListener('input', () => {
                const index = Number(node.getAttribute('data-condition-field'));
                const field = node.getAttribute('data-field');
                if (!Number.isInteger(index) || !field || !scenario.conditions[index]) {
                    return;
                }
                const value = node.value;
                if (field === 'minutes') {
                    scenario.conditions[index][field] = value === '' ? null : Number(value);
                } else {
                    scenario.conditions[index][field] = value;
                }
                syncPayloadField();
                renderScenarioList();
            });
        });
        canvas.querySelectorAll('[data-remove-condition]').forEach((node) => {
            node.addEventListener('click', () => removeCondition(Number(node.getAttribute('data-remove-condition'))));
        });

        canvas.querySelectorAll('[data-action-field]').forEach((node) => {
            node.addEventListener('input', () => {
                const index = Number(node.getAttribute('data-action-field'));
                const field = node.getAttribute('data-field');
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !field || !action) {
                    return;
                }

                if (field === 'type') {
                    action.type = node.value;
                }

                if (field === 'value') {
                    const raw = node.value;
                    if (action.type === 'send_template') {
                        action.template = {name: raw};
                    } else if (action.type === 'set_state') {
                        action.state = raw;
                    } else {
                        action.message = typeof action.message === 'object' && action.message !== null
                            ? {...action.message, type: action.message.type || 'text', body: raw}
                            : {type: 'text', body: raw};
                    }
                }

                syncPayloadField();
                renderScenarioList();
            });
        });
        canvas.querySelectorAll('[data-remove-action]').forEach((node) => {
            node.addEventListener('click', () => removeAction(Number(node.getAttribute('data-remove-action'))));
        });
    };

    publishButton?.addEventListener('click', async function () {
        statusNode.textContent = 'Publicando...';
        publishButton.disabled = true;

        let payload;
        try {
            payload = JSON.parse(payloadField.value);
        } catch (error) {
            statusNode.textContent = 'JSON inválido. Revisa el payload.';
            publishButton.disabled = false;
            return;
        }

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/publish', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({flow: payload}),
                credentials: 'same-origin',
            });
            const data = await response.json();
            statusNode.textContent = data.message || (data.status === 'ok' ? 'Publicado.' : 'Error al publicar.');
            if (response.ok) {
                window.setTimeout(function () { window.location.reload(); }, 800);
            }
        } catch (error) {
            statusNode.textContent = 'No fue posible publicar el flujo desde Laravel.';
        } finally {
            publishButton.disabled = false;
        }
    });

    simButton?.addEventListener('click', async function () {
        simOutput.textContent = 'Simulando...';

        const params = new URLSearchParams({
            wa_number: simNumber?.value || '',
            text: simText?.value || '',
            context: simContext?.value || '{}'
        });

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/simulate?' + params.toString(), {
                credentials: 'same-origin'
            });
            const data = await response.json();
            simOutput.textContent = JSON.stringify(data, null, 2);
        } catch (error) {
            simOutput.textContent = 'No fue posible ejecutar la simulación.';
        }
    });

    compareButton?.addEventListener('click', async function () {
        compareOutput.textContent = 'Comparando...';

        const params = new URLSearchParams({
            wa_number: simNumber?.value || '',
            text: simText?.value || '',
            context: simContext?.value || '{}'
        });

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/compare?' + params.toString(), {
                credentials: 'same-origin'
            });
            const data = await response.json();
            compareOutput.textContent = JSON.stringify(data, null, 2);
        } catch (error) {
            compareOutput.textContent = 'No fue posible comparar Laravel con legacy.';
        }
    });

    const loadShadowRuns = async function () {
        if (!shadowRunsOutput || !shadowSummaryOutput || !readinessOutput) {
            return;
        }

        shadowRunsOutput.textContent = 'Cargando shadow runs...';
        shadowSummaryOutput.textContent = 'Cargando resumen de shadow runtime...';
        readinessOutput.textContent = 'Evaluando readiness de Fase 6...';

        try {
            const [readinessResponse, summaryResponse, runsResponse] = await Promise.all([
                fetch('/v2/whatsapp/api/flowmaker/readiness?limit=100', {credentials: 'same-origin'}),
                fetch('/v2/whatsapp/api/flowmaker/shadow-summary?limit=100', {credentials: 'same-origin'}),
                fetch('/v2/whatsapp/api/flowmaker/shadow-runs?limit=8&mismatches_only=1', {credentials: 'same-origin'}),
            ]);
            const readinessData = await readinessResponse.json();
            const summaryData = await summaryResponse.json();
            const data = await runsResponse.json();
            const readiness = readinessData?.data || {};
            const summary = summaryData?.data || {};
            const rows = Array.isArray(data?.data) ? data.data : [];

            readinessOutput.textContent = [
                `ready_for_phase_7=${readiness.ready_for_phase_7 ? 'true' : 'false'}`,
                Array.isArray(readiness.blocking_checks) && readiness.blocking_checks.length ? `blocking=${readiness.blocking_checks.join(', ')}` : 'blocking=none',
                '',
                ...((readiness.checks || []).map((check) => `- ${check.label}: expected ${check.expected} · actual ${check.actual} · ${check.passed ? 'ok' : 'fail'}`)),
            ].filter(Boolean).join('\n');

            shadowSummaryOutput.textContent = [
                `runs=${summary.total_runs || 0} · mismatches=${summary.mismatch_runs || 0} · dry_run=${summary.dry_run_runs || 0}`,
                '',
                'Motivos principales:',
                ...((summary.top_mismatch_reasons || []).map((row) => `- ${row.reason}: ${row.count}`)),
                '',
                'Brechas de escenario:',
                ...((summary.top_scenario_gaps || []).map((row) => `- ${row.pair}: ${row.count}`)),
            ].filter(Boolean).join('\n');

            if (!rows.length) {
                shadowRunsOutput.textContent = 'No hay mismatches recientes registrados por el webhook shadow.';
                return;
            }

            shadowRunsOutput.textContent = rows.map((row) => {
                return [
                    `#${row.id} · ${row.created_at || '-'}`,
                    `${row.wa_number || '-'} · mode=${row.execution_mode || '-'} · laravel=${row.laravel_scenario || '-'} · legacy=${row.legacy_scenario || '-'}`,
                    `match=${row.parity?.same_match ? 'ok' : 'diff'} · scenario=${row.parity?.same_scenario ? 'ok' : 'diff'} · handoff=${row.parity?.same_handoff ? 'ok' : 'diff'} · actions=${row.parity?.same_action_types ? 'ok' : 'diff'}`,
                    Array.isArray(row.parity?.mismatch_reasons) && row.parity.mismatch_reasons.length ? `reasons=${row.parity.mismatch_reasons.join(', ')}` : '',
                    row.execution_preview?.action_types?.length ? `would=${row.execution_preview.action_types.join(', ')}` : '',
                    row.message_text || '',
                ].filter(Boolean).join('\n');
            }).join('\n\n');
        } catch (error) {
            readinessOutput.textContent = 'No fue posible evaluar el readiness de Fase 6.';
            shadowSummaryOutput.textContent = 'No fue posible cargar el resumen del shadow runtime.';
            shadowRunsOutput.textContent = 'No fue posible cargar los runs recientes del shadow webhook.';
        }
    };

    payloadField?.addEventListener('change', function () {
        try {
            const parsed = JSON.parse(payloadField.value || '{}');
            editorSchema = parsed && typeof parsed === 'object' ? parsed : {};
            if (!Array.isArray(editorSchema.scenarios)) {
                editorSchema.scenarios = [];
            }
            if (!selectedScenario() && getScenarios().length > 0) {
                selectedScenarioId = getScenarios()[0].id;
            }
            renderScenarioList();
            renderScenarioCanvas();
            statusNode.textContent = 'Payload sincronizado desde el editor JSON.';
        } catch (error) {
            statusNode.textContent = 'El payload JSON no se pudo interpretar.';
        }
    });

    searchInput?.addEventListener('input', renderScenarioList);
    addScenarioButton?.addEventListener('click', addScenario);
    shadowRefreshButton?.addEventListener('click', loadShadowRuns);

    syncPayloadField();
    renderScenarioList();
    renderScenarioCanvas();
    loadShadowRuns();
});
</script>
@endpush
