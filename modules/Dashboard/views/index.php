<?php
/** @var array $scripts */
/** @var array $inlineScripts */
/** @var string $fechas_json */
/** @var string $procedimientos_dia_json */
/** @var string $membretes_json */
/** @var string $procedimientos_membrete_json */
/** @var array $estadisticas_afiliacion */
/** @var array $revision_estados */
/** @var array $solicitudes_funnel */
/** @var string $solicitudes_funnel_json */
/** @var array $crm_backlog */
/** @var string $crm_backlog_json */
/** @var array $date_range */
/** @var array $kpi_cards */
/** @var int|string $total_cirugias_periodo */
/** @var array $ai_summary */

$afiliaciones_json = json_encode($estadisticas_afiliacion['afiliaciones'], JSON_UNESCAPED_UNICODE);
$procedimientos_por_afiliacion_json = json_encode($estadisticas_afiliacion['totales'], JSON_UNESCAPED_UNICODE);
$solicitudes_funnel_json = $solicitudes_funnel_json ?? json_encode($solicitudes_funnel, JSON_UNESCAPED_UNICODE);
$crm_backlog_json = $crm_backlog_json ?? json_encode($crm_backlog, JSON_UNESCAPED_UNICODE);
$revision_estados_json = $revision_estados_json ?? json_encode($revision_estados, JSON_UNESCAPED_UNICODE);
$date_range_json = json_encode($date_range, JSON_UNESCAPED_UNICODE);

$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'assets/vendor_components/OwlCarousel2/dist/owl.carousel.js',
    'js/pages/dashboard.js',
    'js/pages/dashboard3.js?v=' . time(),
]);

$inlineScripts = array_merge($inlineScripts ?? [], [
    <<<JS
var dashboardDateRange = {$date_range_json};
var fechas = {$fechas_json};
var procedimientos_dia = {$procedimientos_dia_json};
var membretes = {$membretes_json};
var procedimientos_membrete = {$procedimientos_membrete_json};
var afiliaciones = {$afiliaciones_json};
var procedimientos_por_afiliacion = {$procedimientos_por_afiliacion_json};
var solicitudesFunnel = {$solicitudes_funnel_json};
var crmBacklog = {$crm_backlog_json};
var revisionEstados = {$revision_estados_json};
JS,
    <<<'JS'
document.addEventListener("DOMContentLoaded", function () {
    const filterButtons = document.querySelectorAll('.btn-filter');
    const cards = document.querySelectorAll('.plantilla-card');
    const countSpan = document.getElementById('plantilla-count');

    function updateCount() {
        const visibles = [...cards].filter(c => c.style.display !== 'none').length;
        if (countSpan) {
            countSpan.textContent = `Mostrando ${visibles} plantilla${visibles !== 1 ? 's' : ''}`;
        }
    }

    function filterCards(type) {
        cards.forEach(card => {
            const tipo = (card.dataset.tipo || '').toLowerCase();
            if (type === 'all' || tipo === type) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
        updateCount();
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterType = this.dataset.filter;
            filterCards(filterType);
        });
    });

    filterCards('all');
});
JS,
]);
?>

<!-- Main content -->
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
                            <a href="/dashboard" class="btn btn-light w-100">
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

        <div class="col-xxxl-9 col-xl-8 col-12">
            <div class="row g-3">
                <?php include __DIR__ . '/../components/dashboard_top.php'; ?>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Embudo de solicitudes quirúrgicas</h4>
                            <span class="badge bg-light text-primary">Conversión <?= htmlspecialchars($solicitudes_funnel['totales']['conversion_agendada'] ?? 0) ?>%</span>
                        </div>
                        <div class="box-body">
                            <div id="solicitudes_funnel_chart"></div>
                            <div class="d-flex flex-wrap gap-3 justify-content-between text-muted fs-12 mt-20">
                                <span><strong><?= number_format($solicitudes_funnel['totales']['registradas'] ?? 0) ?></strong> solicitudes registradas</span>
                                <span><strong><?= number_format($solicitudes_funnel['totales']['agendadas'] ?? 0) ?></strong> con turno</span>
                                <span><strong><?= number_format($solicitudes_funnel['totales']['con_cirugia'] ?? 0) ?></strong> con cirugía</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Backlog CRM</h4>
                            <span class="badge bg-light text-primary">Avance <?= htmlspecialchars($crm_backlog['avance'] ?? 0) ?>%</span>
                        </div>
                        <div class="box-body">
                            <div id="crm_backlog_chart"></div>
                            <ul class="list-inline mt-20 mb-0 text-muted fs-12">
                                <li class="list-inline-item me-4"><strong><?= number_format($crm_backlog['pendientes'] ?? 0) ?></strong> pendientes</li>
                                <li class="list-inline-item me-4"><strong><?= number_format($crm_backlog['vencidas'] ?? 0) ?></strong> vencidas</li>
                                <li class="list-inline-item"><strong><?= number_format($crm_backlog['vencen_hoy'] ?? 0) ?></strong> vencen hoy</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Procedimientos más realizados</h4>
                        </div>
                        <div class="box-body">
                            <div id="patient_statistics"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Estado de protocolos</h4>
                        </div>
                        <div class="box-body">
                            <div id="revision_estado_chart"></div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h4 class="box-title">Cirugías recientes</h4>
                            <div class="box-controls pull-right">
                                <div class="lookup lookup-circle lookup-right">
                                    <input type="text" name="s" placeholder="Buscar paciente">
                                </div>
                            </div>
                        </div>
                        <div class="box-body no-padding">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <tbody>
                                    <tr class="bg-info-light">
                                        <th>No</th>
                                        <th>Fecha</th>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Edad</th>
                                        <th>Procedimiento</th>
                                        <th>Afiliación</th>
                                        <th>Opciones</th>
                                    </tr>
                                    <?php if (!empty($cirugias_recientes)): ?>
                                        <?php foreach ($cirugias_recientes as $index => $patient): ?>
                                            <tr>
                                                <td><?= $index + 1; ?></td>
                                                <td><?= date('d/m/Y', strtotime($patient['fecha_inicio'])); ?></td>
                                                <td><?= htmlspecialchars($patient['hc_number']); ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars(trim($patient['fname'] . ' ' . $patient['lname'] . ' ' . $patient['lname2'])); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $birthDate = new DateTime($patient['fecha_nacimiento']);
                                                    $today = new DateTime($patient['fecha_inicio']);
                                                    echo $today->diff($birthDate)->y;
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($patient['membrete']); ?></td>
                                                <td><?= htmlspecialchars($patient['afiliacion']); ?></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <a href="edit_protocol.php?form_id=<?= urlencode($patient['form_id']); ?>&hc_number=<?= urlencode($patient['hc_number']); ?>"
                                                           class="waves-effect waves-circle btn btn-circle btn-success btn-xs me-5"><i class="fa fa-pencil"></i></a>
                                                        <a href="../generate_pdf.php?form_id=<?= urlencode($patient['form_id']); ?>&hc_number=<?= urlencode($patient['hc_number']); ?>"
                                                           class="waves-effect waves-circle btn btn-circle btn-secondary btn-xs"><i class="fa fa-print"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No hay cirugías registradas en el periodo.</td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="box-footer bg-light py-10 with-border">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-10">
                                <p class="mb-0">Total <?= number_format($total_cirugias_periodo); ?> cirugías (<?= htmlspecialchars($date_range['label'] ?? '') ?>)</p>
                                <a href="solicitudes/qx_reports.php" class="waves-effect waves-light btn btn-primary">Ver todos</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-body px-0 pb-0">
                            <div class="px-20 bb-1 pb-15 d-flex align-items-center justify-content-between flex-wrap gap-10">
                                <h4 class="mb-0">Plantillas recientes</h4>
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <button type="button" class="waves-effect waves-light btn btn-sm btn-primary-light btn-filter active" data-filter="all">Todas</button>
                                    <button type="button" class="waves-effect waves-light btn btn-sm btn-primary-light btn-filter" data-filter="creado">Creado</button>
                                    <button type="button" class="waves-effect waves-light btn btn-sm btn-primary-light btn-filter" data-filter="modificado">Modificado</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="inner-user-div4" id="plantilla-container">
                                    <?php if (!empty($plantillas)): ?>
                                        <?php foreach ($plantillas as $row): ?>
                                            <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom plantilla-card" data-tipo="<?= htmlspecialchars(strtolower($row['tipo'])); ?>">
                                                <div class="pe-20">
                                                    <p class="fs-12 text-fade mb-1"><?= date('d M Y', strtotime($row['fecha'])) ?> <span class="mx-10">/</span> <?= htmlspecialchars($row['tipo']); ?></p>
                                                    <h4 class="mb-5"><?= htmlspecialchars($row['membrete']); ?></h4>
                                                    <p class="text-fade mb-10"><?= htmlspecialchars($row['cirugia']); ?></p>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <a href="/protocolos/editar?id=<?= urlencode($row['id']); ?>" class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">Ver</a>
                                                        <a href="../generate_pdf.php?id=<?= urlencode($row['id']); ?>" class="waves-effect waves-light btn btn-xs btn-primary-light">PDF</a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <a href="/protocolos/editar?id=<?= urlencode($row['id']); ?>" class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i class="fa fa-pencil"></i></a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No hay protocolos recientes.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end mt-2 fs-12 text-fade">
                                    <span id="plantilla-count">Mostrando <?= count($plantillas) ?> plantillas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Diagnósticos más frecuentes</h4>
                        </div>
                        <div class="box-body">
                            <div class="news-slider owl-carousel owl-sl">
                                <?php if (!empty($diagnosticos_frecuentes)): ?>
                                    <?php
                                    $totalPacientesCount = array_sum($diagnosticos_frecuentes);
                                    foreach ($diagnosticos_frecuentes as $key => $cantidad):
                                        $porcentaje = $totalPacientesCount > 0 ? round(($cantidad / $totalPacientesCount) * 100, 1) : 0;
                                        ?>
                                        <div>
                                            <div class="d-flex align-items-center mb-10">
                                                <div class="d-flex flex-column flex-grow-1 fw-500">
                                                    <p class="hover-primary text-fade mb-1 fs-14"><i class="fa fa-stethoscope"></i> Diagnóstico</p>
                                                    <span class="text-dark fs-16"><?= htmlspecialchars($key); ?></span>
                                                    <p class="mb-0 fs-14"><?= $porcentaje ?>% de pacientes <span class="badge badge-dot badge-primary"></span></p>
                                                </div>
                                            </div>
                                            <div class="progress progress-xs mt-5">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentaje ?>%" aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No hay diagnósticos registrados.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxxl-3 col-xl-4 col-12">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías por día</h4>
                </div>
                <div class="box-body">
                    <div id="total_patient"></div>
                </div>
            </div>
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Últimas solicitudes quirúrgicas</h4>
                </div>
                <div class="box-body">
                    <?php if (!empty($solicitudes_quirurgicas['solicitudes'])): ?>
                        <?php foreach ($solicitudes_quirurgicas['solicitudes'] as $row): ?>
                            <div class="pb-10 mb-10 border-bottom">
                                <strong><?= htmlspecialchars(trim($row['fname'] . ' ' . $row['lname'])); ?></strong><br>
                                <?php $fechaSolicitud = !empty($row['fecha']) ? date('d/m/Y', strtotime($row['fecha'])) : 'Sin fecha'; ?>
                                <span class="text-fade fs-12 d-block mb-5"><?= htmlspecialchars($row['procedimiento']); ?> · <?= $fechaSolicitud; ?></span>
                                <div class="d-flex flex-wrap gap-2 fs-12">
                                    <span class="badge bg-primary-light text-primary">Estado: <?= htmlspecialchars($row['estado'] ?? 'Sin estado'); ?></span>
                                    <span class="badge bg-info-light text-info">Prioridad: <?= htmlspecialchars($row['prioridad'] ?? 'Normal'); ?></span>
                                    <?php if (!empty($row['turno'])): ?>
                                        <span class="badge bg-success-light text-success">Turno <?= htmlspecialchars($row['turno']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-light text-warning">Sin turno</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted fs-12 mt-5">
                                    <?php if (!empty($row['crm_pipeline_stage'])): ?>
                                        <span class="me-10"><i class="fa fa-share-alt me-5"></i><?= htmlspecialchars($row['crm_pipeline_stage']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['responsable_nombre'])): ?>
                                        <span><i class="fa fa-user-md me-5"></i><?= htmlspecialchars($row['responsable_nombre']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-10">
                                    <a href="ver_solicitud.php?id=<?= urlencode($row['id']); ?>" class="btn btn-xs btn-primary-light">Ver detalles</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No hay solicitudes registradas en el periodo.</p>
                    <?php endif; ?>

                    <hr>
                    <p class="mb-0 text-end"><strong>Total:</strong> <?= number_format($solicitudes_quirurgicas['total'] ?? 0); ?> solicitud(es)</p>
                </div>
            </div>
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Equipo quirúrgico destacado</h4>
                    <p class="mb-0 pull-right fs-12 text-muted">Periodo <?= htmlspecialchars($date_range['label'] ?? '') ?></p>
                </div>
                <div class="box-body">
                    <div class="inner-user-div3">
                        <?php if (!empty($doctores_top)): ?>
                            <?php foreach ($doctores_top as $row): ?>
                                <?php
                                $doctorNombre = $row['cirujano_1'] ?? '';
                                $doctorAvatar = $row['avatar'] ?? null;

                                if (!function_exists('medf_dashboard_initials')) {
                                    function medf_dashboard_initials(string $name): string
                                    {
                                        $trimmed = trim($name);
                                        if ($trimmed === '') {
                                            return 'DR';
                                        }

                                        $parts = preg_split('/\s+/u', $trimmed) ?: [];
                                        if (count($parts) === 1) {
                                            return mb_strtoupper(mb_substr($parts[0], 0, 2));
                                        }

                                        $first = mb_substr($parts[0], 0, 1);
                                        $last = mb_substr($parts[count($parts) - 1], 0, 1);

                                        return mb_strtoupper($first . $last);
                                    }
                                }

                                $doctorInitials = medf_dashboard_initials((string) $doctorNombre);
                                ?>
                                <div class="d-flex align-items-center mb-30">
                                    <div class="me-15">
                                        <?php if ($doctorAvatar): ?>
                                            <img src="<?= htmlspecialchars($doctorAvatar, ENT_QUOTES, 'UTF-8'); ?>"
                                                 class="avatar avatar-lg rounded10"
                                                 style="object-fit: cover;"
                                                 alt="<?= htmlspecialchars((string) $doctorNombre, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php else: ?>
                                            <span class="avatar avatar-lg rounded10 bg-primary-light d-inline-flex align-items-center justify-content-center text-primary fw-bold">
                                                <?= htmlspecialchars($doctorInitials, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-column flex-grow-1 fw-500">
                                        <span class="text-dark mb-1 fs-16"><?= htmlspecialchars((string) $doctorNombre, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-fade">Cirugías: <?= number_format((float) ($row['total'] ?? 0)); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay datos disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Asistente IA</h4>
                </div>
                <div class="box-body">
                    <p class="mb-5">Estado: <strong><?= $ai_summary['provider_configured'] ? 'Activo' : 'Inactivo'; ?></strong></p>
                    <p class="text-muted mb-10">Proveedor: <?= $ai_summary['provider_configured'] ? strtoupper($ai_summary['provider']) : 'Sin configurar'; ?></p>
                    <ul class="list-unstyled mb-0 fs-12 text-muted">
                        <li><i class="fa fa-check-circle me-5 text-success"></i> Consultas por enfermedad: <?= $ai_summary['features']['consultas_enfermedad'] ? 'Habilitado' : 'Deshabilitado'; ?></li>
                        <li><i class="fa fa-check-circle me-5 text-success"></i> Consultas de plan: <?= $ai_summary['features']['consultas_plan'] ? 'Habilitado' : 'Deshabilitado'; ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
