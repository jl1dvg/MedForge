<?php
/** @var array $date_range */
/** @var int $total_cirugias */
/** @var int $cirugias_sin_facturar */
/** @var string $duracion_promedio */
/** @var array $estado_protocolos */
/** @var array $cirugias_por_mes */
/** @var array $top_procedimientos */
/** @var array $top_cirujanos */
/** @var array $cirugias_por_convenio */

$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'js/pages/cirugias_dashboard.js?v=' . time(),
]);

$dashboardPayload = json_encode([
    'cirugiasPorMes' => $cirugias_por_mes,
    'topProcedimientos' => $top_procedimientos,
    'topCirujanos' => $top_cirujanos,
    'estadoProtocolos' => $estado_protocolos,
    'cirugiasPorConvenio' => $cirugias_por_convenio,
], JSON_UNESCAPED_UNICODE);

$inlineScripts = array_merge($inlineScripts ?? [], [
    "window.cirugiasDashboardData = {$dashboardPayload};",
    "if (window.initCirugiasDashboard) { window.initCirugiasDashboard(window.cirugiasDashboardData); }",
]);
?>

<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="box mb-15">
                <div class="box-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-4 col-lg-3">
                            <label for="start_date" class="form-label">Desde</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="<?= htmlspecialchars($date_range['start'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="end_date" class="form-label">Hasta</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="<?= htmlspecialchars($date_range['end'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa fa-filter me-5"></i>Aplicar filtros
                            </button>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label d-block">&nbsp;</label>
                            <a href="/cirugias/dashboard" class="btn btn-light w-100">
                                <i class="fa fa-undo me-5"></i>Limpiar
                            </a>
                        </div>
                    </form>
                    <p class="text-muted fs-12 mb-0 mt-10">
                        Mostrando datos del periodo: <strong><?= htmlspecialchars($date_range['label'] ?? '') ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="box">
                        <div class="box-body">
                            <p class="text-muted mb-5">Cirugías en el periodo</p>
                            <h3 class="fw-600 mb-0"><?= (int) $total_cirugias ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="box">
                        <div class="box-body">
                            <p class="text-muted mb-5">Protocolos revisados</p>
                            <h3 class="fw-600 mb-0"><?= (int) ($estado_protocolos['revisado'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="box">
                        <div class="box-body">
                            <p class="text-muted mb-5">Cirugías sin facturar</p>
                            <h3 class="fw-600 mb-0"><?= (int) $cirugias_sin_facturar ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="box">
                        <div class="box-body">
                            <p class="text-muted mb-5">Duración promedio</p>
                            <h3 class="fw-600 mb-0"><?= htmlspecialchars($duracion_promedio) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías por mes</h4>
                </div>
                <div class="box-body">
                    <div id="cirugias-por-mes" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Estado de protocolos</h4>
                </div>
                <div class="box-body">
                    <div id="estado-protocolos" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Top procedimientos</h4>
                </div>
                <div class="box-body">
                    <div id="top-procedimientos" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Top cirujanos</h4>
                </div>
                <div class="box-body">
                    <div id="top-cirujanos" style="height: 320px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías por convenio</h4>
                </div>
                <div class="box-body">
                    <div id="cirugias-por-convenio" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>
</section>
