<?php
/** @var string $pageTitle */

if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}

$styles[] = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css';

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

array_push(
    $scripts,
    'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js',
    'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'js/pages/whatsapp-kpis.js'
);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard WhatsApp</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/whatsapp/chat">WhatsApp</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content" id="whatsapp-kpi-root" data-endpoint-kpis="/whatsapp/api/kpis" data-endpoint-drilldown="/whatsapp/api/kpis/drilldown">
    <style>
        .wa-kpi-toolbar {
            display: grid;
            grid-template-columns: minmax(180px, 300px) minmax(180px, 260px) minmax(180px, 260px) auto;
            gap: .75rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .wa-kpi-toolbar .form-label {
            font-size: .78rem;
            color: #64748b;
            margin-bottom: .35rem;
        }

        .wa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .wa-kpi-card {
            border: 1px solid rgba(15, 23, 42, .08);
            background: #fff;
            border-radius: 12px;
            padding: .8rem .9rem;
            transition: all .18s ease;
            cursor: pointer;
        }

        .wa-kpi-card:hover {
            border-color: rgba(59, 130, 246, .35);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
            transform: translateY(-1px);
        }

        .wa-kpi-card__label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: .3rem;
        }

        .wa-kpi-card__value {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }

        .wa-kpi-card__sub {
            font-size: .78rem;
            color: #64748b;
            margin-top: .2rem;
            min-height: 1rem;
        }

        .wa-kpi-chart {
            min-height: 280px;
        }

        .wa-kpi-empty {
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: .9rem;
            text-align: center;
        }

        .wa-kpi-table td,
        .wa-kpi-table th {
            vertical-align: middle;
            font-size: .84rem;
        }

        .wa-kpi-badge {
            border-radius: 999px;
            padding: .2rem .55rem;
            font-size: .74rem;
            font-weight: 600;
        }

        .daterangepicker {
            z-index: 2105 !important;
            border: 1px solid rgba(15, 23, 42, .15);
            box-shadow: 0 18px 35px rgba(15, 23, 42, .2);
        }

        .daterangepicker .ranges {
            margin: 0;
            padding: .4rem;
        }

        .daterangepicker .ranges ul {
            width: 180px;
        }

        .daterangepicker .ranges li {
            border-radius: 8px;
            margin-bottom: .2rem;
        }

        .daterangepicker .drp-calendar {
            max-width: 300px;
        }

        .daterangepicker .drp-buttons {
            border-top: 1px solid rgba(15, 23, 42, .08);
        }

        @media (max-width: 991.98px) {
            .wa-kpi-toolbar {
                grid-template-columns: 1fr;
            }

            .daterangepicker.show-ranges .drp-calendar.left {
                border-left: 0;
            }
        }
    </style>

    <div class="box">
        <div class="box-body">
            <div class="wa-kpi-toolbar">
                <div>
                    <label class="form-label" for="wa-kpi-range">Periodo</label>
                    <input type="text" id="wa-kpi-range" class="form-control" autocomplete="off" placeholder="YYYY-MM-DD - YYYY-MM-DD">
                </div>
                <div>
                    <label class="form-label" for="wa-kpi-role">Equipo</label>
                    <select id="wa-kpi-role" class="form-select">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="wa-kpi-agent">Agente</label>
                    <select id="wa-kpi-agent" class="form-select">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="d-grid">
                    <button type="button" class="btn btn-primary" id="wa-kpi-refresh">
                        <i class="mdi mdi-refresh"></i> Actualizar
                    </button>
                </div>
            </div>

            <div class="wa-kpi-grid">
                <div class="wa-kpi-card" data-kpi-card="conversations_new">
                    <div class="wa-kpi-card__label">Conversaciones nuevas</div>
                    <div class="wa-kpi-card__value" data-kpi-value="conversations_new">—</div>
                    <div class="wa-kpi-card__sub">Periodo seleccionado</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="contacts_active">
                    <div class="wa-kpi-card__label">Contactos activos</div>
                    <div class="wa-kpi-card__value" data-kpi-value="contacts_active">—</div>
                    <div class="wa-kpi-card__sub">Con actividad</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="messages_inbound">
                    <div class="wa-kpi-card__label">Mensajes inbound</div>
                    <div class="wa-kpi-card__value" data-kpi-value="messages_inbound">—</div>
                    <div class="wa-kpi-card__sub">Recibidos</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="messages_outbound">
                    <div class="wa-kpi-card__label">Mensajes outbound</div>
                    <div class="wa-kpi-card__value" data-kpi-value="messages_outbound">—</div>
                    <div class="wa-kpi-card__sub">Enviados</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="avg_first_response">
                    <div class="wa-kpi-card__label">1ra respuesta</div>
                    <div class="wa-kpi-card__value" data-kpi-value="avg_first_response_minutes">—</div>
                    <div class="wa-kpi-card__sub">Promedio (min)</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="handoffs_total">
                    <div class="wa-kpi-card__label">Handoffs</div>
                    <div class="wa-kpi-card__value" data-kpi-value="handoffs_total">—</div>
                    <div class="wa-kpi-card__sub">Total escalados</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="handoff_rate">
                    <div class="wa-kpi-card__label">Tasa de handoff</div>
                    <div class="wa-kpi-card__value" data-kpi-value="handoff_rate">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="handoff_rate">Sobre conversaciones inbound</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="autoservice_rate">
                    <div class="wa-kpi-card__label">Autoservicio</div>
                    <div class="wa-kpi-card__value" data-kpi-value="autoservice_rate">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="autoservice_rate">Resuelto sin agente</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="fallback_rate">
                    <div class="wa-kpi-card__label">Fallback / No entendido</div>
                    <div class="wa-kpi-card__value" data-kpi-value="fallback_rate">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="fallback_rate">Mensajes de fallback</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="sla_assignments">
                    <div class="wa-kpi-card__label">SLA asignación</div>
                    <div class="wa-kpi-card__value" data-kpi-value="sla_assignments_rate">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="sla_assignments_rate">Meta SLA</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="live_queue">
                    <div class="wa-kpi-card__label">Cola activa</div>
                    <div class="wa-kpi-card__value" data-kpi-value="live_queue_total">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="live_queue_total">Pendientes ahora</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="handoff_transfers">
                    <div class="wa-kpi-card__label">Transferencias</div>
                    <div class="wa-kpi-card__value" data-kpi-value="handoff_transfers">—</div>
                    <div class="wa-kpi-card__sub">Entre agentes</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="reopened_24h">
                    <div class="wa-kpi-card__label">Reaperturas 24h</div>
                    <div class="wa-kpi-card__value" data-kpi-value="reopened_24h">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="reopened_24h">Sobre resueltos</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="reopened_72h">
                    <div class="wa-kpi-card__label">Reaperturas 72h</div>
                    <div class="wa-kpi-card__value" data-kpi-value="reopened_72h">—</div>
                    <div class="wa-kpi-card__sub" data-kpi-sub="reopened_72h">Sobre resueltos</div>
                </div>
                <div class="wa-kpi-card" data-kpi-card="avg_handoff_assignment">
                    <div class="wa-kpi-card__label">Asignación handoff</div>
                    <div class="wa-kpi-card__value" data-kpi-value="avg_handoff_assignment_minutes">—</div>
                    <div class="wa-kpi-card__sub">Promedio (min)</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-8 col-12">
                    <div class="box border mb-0">
                        <div class="box-header">
                            <h4 class="box-title mb-0">Volumen diario de mensajes</h4>
                        </div>
                        <div class="box-body">
                            <div id="wa-kpi-chart-volume" class="wa-kpi-chart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box border mb-0">
                        <div class="box-header">
                            <h4 class="box-title mb-0">Calidad de entrega (outbound)</h4>
                        </div>
                        <div class="box-body">
                            <div id="wa-kpi-chart-status" class="wa-kpi-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-xl-6 col-12">
                    <div class="box border mb-0">
                        <div class="box-header">
                            <h4 class="box-title mb-0">Handoffs por día</h4>
                        </div>
                        <div class="box-body">
                            <div id="wa-kpi-chart-handoffs" class="wa-kpi-chart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box border mb-0">
                        <div class="box-header">
                            <h4 class="box-title mb-0">Handoffs por equipo</h4>
                        </div>
                        <div class="box-body">
                            <div id="wa-kpi-chart-roles" class="wa-kpi-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="box border mb-0">
                        <div class="box-header">
                            <h4 class="box-title mb-0">Top opciones de menú</h4>
                        </div>
                        <div class="box-body">
                            <div id="wa-kpi-chart-menu" class="wa-kpi-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box border mt-3 mb-0">
                <div class="box-header">
                    <h4 class="box-title mb-0">Rendimiento por agente</h4>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 wa-kpi-table" id="wa-kpi-agent-table">
                            <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Asignadas</th>
                                <th>Activas</th>
                                <th>Resueltas</th>
                                <th>Resolución</th>
                                <th>Asignación prom.</th>
                                <th>Resolución prom.</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Cargando...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="waKpiDrilldownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="wa-kpi-drilldown-title">Detalle KPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0" id="wa-kpi-drilldown-table">
                            <thead></thead>
                            <tbody>
                            <tr>
                                <td class="text-center text-muted py-3">Selecciona un KPI.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted small" id="wa-kpi-drilldown-meta">—</div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="wa-kpi-drilldown-prev">Anterior</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="wa-kpi-drilldown-next">Siguiente</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
