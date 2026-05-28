@extends('layouts.medforge')

@push('scripts')
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v3/imagenes-dashboard.js')
    @else
        <script src="/assets/vendor_components/chart.js/chart.umd.js"></script>
    @endif
@endpush

@push('styles')
<style>
    .mf-v3-dashboard {
        --mf-blue: #2563eb;
        --mf-green: #15803d;
        --mf-amber: #b45309;
        --mf-red: #b91c1c;
        --mf-gray: #64748b;
        --mf-border: #dbe3ef;
        --mf-surface: #ffffff;
        --mf-soft: #f8fafc;
        color: #0f172a;
        padding: 18px;
    }
    .mf-v3-header,
    .mf-v3-section {
        background: var(--mf-surface);
        border: 1px solid var(--mf-border);
        border-radius: 8px;
        margin-bottom: 16px;
        padding: 16px;
    }
    .mf-v3-header h1 {
        font-size: 24px;
        line-height: 1.25;
        margin: 0 0 4px;
        font-weight: 700;
    }
    .mf-v3-header p,
    .mf-v3-note {
        color: #475569;
        margin: 0;
    }
    .mf-v3-filters {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(6, minmax(130px, 1fr));
        margin-top: 16px;
    }
    .mf-v3-field label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 4px;
    }
    .mf-v3-field input {
        min-height: 44px;
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 8px 10px;
    }
    .mf-v3-actions {
        align-items: end;
        display: flex;
        gap: 8px;
    }
    .mf-v3-button {
        align-items: center;
        border-radius: 6px;
        border: 1px solid transparent;
        cursor: pointer;
        display: inline-flex;
        font-weight: 700;
        justify-content: center;
        min-height: 44px;
        padding: 8px 14px;
    }
    .mf-v3-button-primary {
        background: #1d4ed8;
        color: #ffffff;
    }
    .mf-v3-button-secondary {
        background: #ffffff;
        border-color: #cbd5e1;
        color: #0f172a;
    }
    .mf-v3-section-title {
        align-items: baseline;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
    }
    .mf-v3-section-title h2 {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
    }
    .mf-v3-grid {
        display: grid;
        gap: 12px;
    }
    .mf-v3-grid.executive {
        grid-template-columns: repeat(6, minmax(140px, 1fr));
    }
    .mf-v3-grid.kpis {
        grid-template-columns: repeat(4, minmax(150px, 1fr));
    }
    .mf-v3-card {
        background: var(--mf-soft);
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--mf-gray);
        border-radius: 8px;
        min-height: 104px;
        padding: 12px;
    }
    .mf-v3-card.blue { border-left-color: var(--mf-blue); }
    .mf-v3-card.green { border-left-color: var(--mf-green); }
    .mf-v3-card.amber { border-left-color: var(--mf-amber); }
    .mf-v3-card.red { border-left-color: var(--mf-red); }
    .mf-v3-card span {
        color: #475569;
        display: block;
        font-size: 12px;
        font-weight: 700;
        min-height: 34px;
    }
    .mf-v3-card strong {
        display: block;
        font-size: 24px;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
        margin-top: 8px;
    }
    .mf-v3-card small {
        color: #64748b;
        display: block;
        font-size: 12px;
        margin-top: 6px;
    }
    .mf-v3-chart-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }
    .mf-v3-chart {
        height: 280px;
        position: relative;
    }
    .mf-v3-top-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .mf-v3-top-list li {
        align-items: center;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        min-height: 40px;
        padding: 8px 0;
    }
    .mf-v3-table-wrap {
        overflow-x: auto;
    }
    .mf-v3-table {
        border-collapse: collapse;
        min-width: 980px;
        width: 100%;
    }
    .mf-v3-table th,
    .mf-v3-table td {
        border-bottom: 1px solid #e2e8f0;
        padding: 10px;
        text-align: left;
        vertical-align: top;
    }
    .mf-v3-table th {
        background: #f1f5f9;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
    }
    .mf-v3-empty {
        color: #64748b;
    }
    .mf-v3-dashboard.is-loading {
        opacity: .72;
        pointer-events: none;
    }
    @media (max-width: 1200px) {
        .mf-v3-grid.executive,
        .mf-v3-grid.kpis {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
        .mf-v3-filters {
            grid-template-columns: repeat(3, minmax(130px, 1fr));
        }
    }
    @media (max-width: 720px) {
        .mf-v3-dashboard {
            padding: 10px;
        }
        .mf-v3-grid.executive,
        .mf-v3-grid.kpis,
        .mf-v3-chart-grid,
        .mf-v3-filters {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $initialPayload = is_array($initialPayload ?? null) ? $initialPayload : [];
@endphp

<main class="mf-v3-dashboard"
      data-imagenes-v3-dashboard
      data-endpoints='@json([
        'data' => route('imagenes.dashboard.v3.data'),
        'detail' => route('imagenes.dashboard.v3.detail'),
        'export' => route('imagenes.dashboard.v3.export'),
      ])'>
    <script type="application/json" data-initial-payload>@json($initialPayload)</script>

    <section class="mf-v3-header">
        <h1>Dashboard gerencial de imágenes V3</h1>
        <p>Resumen para decidir: lo solicitado, lo realizado, lo facturado, lo pendiente y lo recuperable.</p>
        <form class="mf-v3-filters" data-filter-form>
            <div class="mf-v3-field">
                <label for="fecha_inicio">Fecha inicio</label>
                <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ e((string)($filters['fecha_inicio'] ?? '')) }}" required>
            </div>
            <div class="mf-v3-field">
                <label for="fecha_fin">Fecha fin</label>
                <input id="fecha_fin" name="fecha_fin" type="date" value="{{ e((string)($filters['fecha_fin'] ?? '')) }}" required>
            </div>
            <div class="mf-v3-field">
                <label for="sede">Sede</label>
                <input id="sede" name="sede" type="text" value="{{ e((string)($filters['sede'] ?? '')) }}" placeholder="Todas">
            </div>
            <div class="mf-v3-field">
                <label for="afiliacion">Afiliación</label>
                <input id="afiliacion" name="afiliacion" type="text" value="{{ e((string)($filters['afiliacion'] ?? '')) }}" placeholder="Todas">
            </div>
            <div class="mf-v3-field">
                <label for="tipo_examen">Examen</label>
                <input id="tipo_examen" name="tipo_examen" type="text" value="{{ e((string)($filters['tipo_examen'] ?? '')) }}" placeholder="Todos">
            </div>
            <div class="mf-v3-actions">
                <button class="mf-v3-button mf-v3-button-primary" type="submit" data-refresh-button>Actualizar</button>
                <a class="mf-v3-button mf-v3-button-secondary" href="{{ route('imagenes.dashboard.v3.export', $filters) }}" data-export-link>Exportar</a>
            </div>
        </form>
        <p class="mf-v3-note" data-summary-mode></p>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-section-title">
            <h2>Resumen ejecutivo</h2>
            <span class="mf-v3-note">Actualizado: <span data-generated-at></span></span>
        </div>
        <div class="mf-v3-grid executive">
            <div class="mf-v3-card green"><span>Facturado real</span><strong data-kpi="facturado_real">$0.00</strong><small>Dinero emitido en facturación real.</small></div>
            <div class="mf-v3-card green"><span>Honorario real</span><strong data-kpi="honorario_real">$0.00</strong><small>Valor económico real del estudio.</small></div>
            <div class="mf-v3-card amber"><span>Pendiente de facturar</span><strong data-kpi="pendiente_facturar">0</strong><small>Realizado, pero sin factura real.</small></div>
            <div class="mf-v3-card amber"><span>Pendiente de cobrar</span><strong data-kpi="pendiente_cobrar">0</strong><small>Ya facturado, pero en cartera.</small></div>
            <div class="mf-v3-card red"><span>Pérdida estimada</span><strong data-kpi="perdida_estimada">$0.00</strong><small>Ausentes, cancelados o no agendados.</small></div>
            <div class="mf-v3-card blue"><span>Oportunidad de recuperación</span><strong data-kpi="oportunidad_recuperacion">$0.00</strong><small>Trabajo recuperable o por facturar.</small></div>
        </div>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-section-title"><h2>Solicitudes y operación</h2></div>
        <div class="mf-v3-grid kpis">
            <div class="mf-v3-card blue"><span>Solicitudes recibidas</span><strong data-kpi="solicitudes_recibidas">0</strong></div>
            <div class="mf-v3-card amber"><span>Solicitudes sin agenda</span><strong data-kpi="solicitudes_sin_agenda">0</strong></div>
            <div class="mf-v3-card green"><span>Realizadas al corte</span><strong data-kpi="solicitudes_realizadas_corte">0</strong></div>
            <div class="mf-v3-card blue"><span>Agendas del periodo</span><strong data-kpi="agendas_periodo">0</strong></div>
            <div class="mf-v3-card green"><span>Atendidas</span><strong data-kpi="atendidas">0</strong></div>
            <div class="mf-v3-card red"><span>No atendidas</span><strong data-kpi="no_atendidas">0</strong></div>
            <div class="mf-v3-card amber"><span>Sin cierre operativo</span><strong data-kpi="sin_cierre">0</strong></div>
            <div class="mf-v3-card green"><span>Con archivos NAS</span><strong data-kpi="nas">0</strong></div>
            <div class="mf-v3-card green"><span>Con informe</span><strong data-kpi="informes">0</strong></div>
            <div class="mf-v3-card amber"><span>Pendientes de informar</span><strong data-kpi="pendientes_informar">0</strong></div>
            <div class="mf-v3-card green"><span>Estudios con billing real</span><strong data-kpi="billing_real">0</strong></div>
            <div class="mf-v3-card amber"><span>Realizados sin factura</span><strong data-kpi="realizados_sin_billing">0</strong></div>
        </div>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-chart-grid">
            <div>
                <div class="mf-v3-section-title"><h2>Embudo de conversión</h2></div>
                <div class="mf-v3-chart"><canvas data-chart="funnel" aria-label="Embudo de solicitudes a facturación"></canvas></div>
            </div>
            <div>
                <div class="mf-v3-section-title"><h2>Dinero y oportunidad</h2></div>
                <div class="mf-v3-chart"><canvas data-chart="money" aria-label="Comparativo de facturado, pendiente y pérdida"></canvas></div>
            </div>
        </div>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-section-title"><h2>Top oportunidades</h2></div>
        <div class="mf-v3-chart-grid">
            <div><h3>Exámenes</h3><ul class="mf-v3-top-list" data-top="examenes"></ul></div>
            <div><h3>Sedes</h3><ul class="mf-v3-top-list" data-top="sedes"></ul></div>
        </div>
        <div class="mf-v3-chart-grid" style="margin-top: 12px;">
            <div><h3>Seguros / afiliaciones</h3><ul class="mf-v3-top-list" data-top="seguros"></ul></div>
            <div><h3>Doctores solicitantes</h3><ul class="mf-v3-top-list" data-top="doctores"></ul></div>
        </div>
        <div style="margin-top: 12px;"><h3>Causas de pérdida u oportunidad</h3><ul class="mf-v3-top-list" data-top="causas"></ul></div>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-section-title">
            <h2>Detalle operativo paginado</h2>
            <span class="mf-v3-note" data-detail-count></span>
        </div>
        <div class="mf-v3-table-wrap">
            <table class="mf-v3-table" data-detail-table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Form ID</th>
                        <th>Historia</th>
                        <th>Examen</th>
                        <th>Sede</th>
                        <th>Realización</th>
                        <th>Facturación</th>
                        <th>Monto facturado</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="8">Cargando detalle...</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="mf-v3-section">
        <div class="mf-v3-section-title"><h2>Cómo leer este dashboard</h2></div>
        <p class="mf-v3-note">Pendiente de facturar significa que el estudio ya tiene evidencia técnica, pero todavía no aparece en facturación real. Pendiente de cobrar significa que ya existe factura, pero su estado indica cartera, crédito o pago pendiente. Pérdida estimada agrupa ausentes, cancelados y solicitudes que nunca llegaron a agenda.</p>
    </section>
</main>
@endsection
