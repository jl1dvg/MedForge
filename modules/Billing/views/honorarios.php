<?php
/** @var string $pageTitle */
/** @var array $cirujanos */

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
    'js/pages/billing/honorarios.js'
);

$cirujanos = $cirujanos ?? [];
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Honorarios médicos</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/billing">Billing</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Honorarios</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .honorarios-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .honorarios-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .honorarios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: #ffffff;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .metric-card h6 {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0ea5e9;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #0f172a;
        }

        .metric-subtext {
            font-size: 0.85rem;
            color: #64748b;
        }

        .chart-container {
            min-height: 280px;
        }

        .chart-empty {
            height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
            font-size: 0.95rem;
        }

        .rule-card {
            border-radius: 14px;
            border: 1px dashed rgba(148, 163, 184, 0.6);
            padding: 1rem;
            background: #f8fafc;
        }

        .rule-card h6 {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }

        .rule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
        }

        .rule-grid label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .table-sticky th {
            position: sticky;
            top: 0;
            background: #f8fafc;
        }
    </style>

    <div class="honorarios-header">
        <div>
            <h4 class="mb-1">Producción quirúrgica por cirujano</h4>
            <div class="text-muted">Solo procedimientos quirúrgicos (sin insumos).</div>
        </div>
        <div class="ms-auto honorarios-filters">
            <input type="text" class="form-control" id="honorarios-range-input" placeholder="Selecciona rango" autocomplete="off">
            <select class="form-select" id="honorarios-cirujano">
                <option value="">Todos los cirujanos</option>
                <?php foreach ($cirujanos as $cirujano): ?>
                    <option value="<?= htmlspecialchars($cirujano) ?>"><?= htmlspecialchars($cirujano) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" id="honorarios-afiliacion">
                <option value="">Todas las afiliaciones</option>
                <option value="IESS">IESS</option>
                <option value="ISSFA">ISSFA</option>
                <option value="ISSPOL">ISSPOL</option>
                <option value="Particular">Particular</option>
                <option value="Sin afiliación">Sin afiliación</option>
            </select>
            <button type="button" class="btn btn-primary" id="honorarios-refresh">
                <i class="mdi mdi-refresh"></i> Actualizar
            </button>
        </div>
    </div>

    <div class="rule-card mb-3">
        <h6>Reglas de honorarios por afiliación</h6>
        <div class="rule-grid">
            <div>
                <label for="rule-iess">IESS (%)</label>
                <input type="number" class="form-control" id="rule-iess" value="30" min="0" max="100" step="0.5" data-rule-key="IESS">
            </div>
            <div>
                <label for="rule-issfa">ISSFA (%)</label>
                <input type="number" class="form-control" id="rule-issfa" value="35" min="0" max="100" step="0.5" data-rule-key="ISSFA">
            </div>
            <div>
                <label for="rule-isspol">ISSPOL (%)</label>
                <input type="number" class="form-control" id="rule-isspol" value="35" min="0" max="100" step="0.5" data-rule-key="ISSPOL">
            </div>
            <div>
                <label for="rule-default">Particular / otros (%)</label>
                <input type="number" class="form-control" id="rule-default" value="30" min="0" max="100" step="0.5" data-rule-key="DEFAULT">
            </div>
        </div>
    </div>

    <div class="honorarios-grid">
        <div class="metric-card">
            <h6>Total casos</h6>
            <div class="metric-value" id="metric-casos">—</div>
            <div class="metric-subtext">Cirugías con procedimientos facturados</div>
        </div>
        <div class="metric-card">
            <h6>Procedimientos</h6>
            <div class="metric-value" id="metric-procedimientos">—</div>
            <div class="metric-subtext">Cantidad total de procedimientos</div>
        </div>
        <div class="metric-card">
            <h6>Producción quirúrgica</h6>
            <div class="metric-value" id="metric-produccion">—</div>
            <div class="metric-subtext">Total facturado solo procedimientos</div>
        </div>
        <div class="metric-card">
            <h6>Honorarios estimados</h6>
            <div class="metric-value" id="metric-honorarios">—</div>
            <div class="metric-subtext">Aplicando reglas por afiliación</div>
        </div>
        <div class="metric-card">
            <h6>Ticket promedio</h6>
            <div class="metric-value" id="metric-ticket">—</div>
            <div class="metric-subtext">Producción promedio por caso</div>
        </div>
        <div class="metric-card">
            <h6>Honorario promedio</h6>
            <div class="metric-value" id="metric-honorario-promedio">—</div>
            <div class="metric-subtext">Honorarios promedio por caso</div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title">Producción por afiliación</h4>
                </div>
                <div class="box-body">
                    <div id="chart-honorarios-afiliacion" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title">Producción por cirujano</h4>
                </div>
                <div class="box-body">
                    <div id="chart-honorarios-cirujano" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-12">
            <div class="box chart-card">
                <div class="box-header with-border">
                    <h4 class="box-title">Top procedimientos quirúrgicos</h4>
                </div>
                <div class="box-body">
                    <div id="chart-honorarios-procedimientos" class="chart-container"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Detalle por cirujano</h4>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sticky">
                            <thead class="bg-primary-light">
                            <tr>
                                <th>Cirujano</th>
                                <th class="text-end">Casos</th>
                                <th class="text-end">Procedimientos</th>
                                <th class="text-end">Producción</th>
                                <th class="text-end">Honorarios</th>
                            </tr>
                            </thead>
                            <tbody id="table-honorarios">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Sin datos</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
