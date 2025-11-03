<?php

use Modules\Pacientes\Support\ViewHelper as PacientesHelper;

/** @var array $patientData */
/** @var string $hc_number */
/** @var array $afiliacionesDisponibles */
/** @var array $diagnosticos */
/** @var array $medicos */
/** @var array $timelineItems */
/** @var array $eventos */
/** @var array $documentos */
/** @var array $estadisticas */
/** @var int|null $patientAge */

$diagnosticos = $diagnosticos ?? [];
$medicos = $medicos ?? [];
$timelineItems = $timelineItems ?? [];
$eventos = $eventos ?? [];
$documentos = $documentos ?? [];
$estadisticas = $estadisticas ?? [];
$patientAge = $patientAge ?? null;
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'assets/vendor_components/horizontal-timeline/js/horizontal-timeline.js',
    'js/pages/patient-detail.js',
]);

$nombrePaciente = trim(($patientData['fname'] ?? '') . ' ' . ($patientData['mname'] ?? '') . ' ' . ($patientData['lname'] ?? '') . ' ' . ($patientData['lname2'] ?? ''));
$timelineColorMap = [
    'solicitud' => 'bg-primary',
    'prefactura' => 'bg-info',
    'cirugia' => 'bg-danger',
    'interconsulta' => 'bg-warning',
];
$solicitudPdfBaseUrl = rtrim(BASE_URL, '/') . '/views/reports/solicitud_quirurgica/solicitud_qx_pdf.php';
$solicitudPdfBaseUrlEscaped = htmlspecialchars($solicitudPdfBaseUrl, ENT_QUOTES, 'UTF-8');
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Detalles del paciente</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/pacientes">Pacientes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            HC <?= PacientesHelper::safe($hc_number) ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-xl-4 col-12">
            <div class="box">
                <div class="box-body box-profile">
                    <div class="row">
                        <div class="col-12">
                            <div>
                                <p>Nombre completo:<span
                                            class="text-gray ps-10"><?= PacientesHelper::safe($nombrePaciente) ?></span>
                                </p>
                                <p>Fecha de Nacimiento:<span
                                            class="text-gray ps-10"><?= PacientesHelper::formatDateSafe($patientData['fecha_nacimiento'] ?? null) ?></span>
                                </p>
                                <p>Edad:<span
                                            class="text-gray ps-10"><?= $patientAge !== null ? PacientesHelper::safe((string)$patientAge . ' años') : '—' ?></span>
                                </p>
                                <p>Celular:<span
                                            class="text-gray ps-10"><?= PacientesHelper::safe($patientData['celular'] ?? '—') ?></span>
                                </p>
                                <p>Dirección:<span
                                            class="text-gray ps-10"><?= PacientesHelper::safe($patientData['ciudad'] ?? '—') ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="col-12 mt-20">
                            <div class="pb-15">
                                <p class="mb-10">Social Profile</p>
                                <div class="user-social-acount">
                                    <button class="btn btn-circle btn-social-icon btn-facebook"><i
                                                class="fa fa-facebook"></i></button>
                                    <button class="btn btn-circle btn-social-icon btn-twitter"><i
                                                class="fa fa-twitter"></i></button>
                                    <button class="btn btn-circle btn-social-icon btn-instagram"><i
                                                class="fa fa-instagram"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="map-box">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2805244.1745767146!2d-86.32675167439648!3d29.383165774894163!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x88c1766591562abf%3A0xf72e13d35bc74ed0!2sFlorida%2C+USA!5e0!3m2!1sen!2sin!4v1501665415329"
                                        width="100%" height="175" frameborder="0" style="border:0"
                                        allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header border-0 pb-0">
                    <h4 class="box-title">Antecedentes Patológicos</h4>
                </div>
                <div class="box-body">
                    <div class="widget-timeline-icon">
                        <ul>
                            <?php if (!empty($diagnosticos)): ?>
                                <?php foreach ($diagnosticos as $diagnosis): ?>
                                    <li>
                                        <div class="icon bg-primary fa fa-heart-o"></div>
                                        <a class="timeline-panel text-muted" href="#">
                                            <h4 class="mb-2 mt-1"><?= PacientesHelper::safe($diagnosis['idDiagnostico'] ?? '') ?></h4>
                                            <p class="fs-15 mb-0 "><?= PacientesHelper::safe($diagnosis['fecha'] ?? '') ?></p>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="text-muted">Sin diagnósticos registrados.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Solicitudes</h4>
                    <ul class="box-controls pull-right d-md-flex d-none">
                        <li class="dropdown">
                            <button class="btn btn-primary dropdown-toggle px-10" data-bs-toggle="dropdown" href="#">
                                Crear
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="#"><i class="ti-import"></i> Import</a>
                                <a class="dropdown-item" href="#"><i class="ti-export"></i> Export</a>
                                <a class="dropdown-item" href="#"><i class="ti-printer"></i> Print</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#"><i class="ti-settings"></i> Settings</a>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="box-body">
                    <?php foreach ($timelineItems as $procedimientoData):
                        $bulletColor = $timelineColorMap[$procedimientoData['tipo'] ?? '']
                            ?? $timelineColorMap[strtolower($procedimientoData['origen'] ?? '')] ?? 'bg-secondary';
                        $checkboxId = 'md_checkbox_' . uniqid();
                        ?>
                        <div class="d-flex align-items-center mb-25">
                            <span class="bullet bullet-bar <?= $bulletColor ?> align-self-stretch"></span>
                            <div class="h-20 mx-20 flex-shrink-0">
                                <input type="checkbox" id="<?= $checkboxId ?>"
                                       class="filled-in chk-col-<?= $bulletColor ?>">
                                <label for="<?= $checkboxId ?>" class="h-20 p-10 mb-0"></label>
                            </div>
                            <div class="d-flex flex-column flex-grow-1">
                                <a href="#" class="text-dark fw-500 fs-16">
                                    <?= nl2br(PacientesHelper::safe($procedimientoData['nombre'] ?? '')) ?>
                                </a>
                                <span class="text-fade fw-500">
                                    <?= ucfirst(strtolower($procedimientoData['origen'] ?? '')) ?> creado el <?= PacientesHelper::formatDateSafe($procedimientoData['fecha'] ?? '') ?>
                                </span>
                            </div>
                            <?php if (($procedimientoData['origen'] ?? '') === 'Solicitud'): ?>
                                <div class="dropdown">
                                    <a class="px-10 pt-5" href="#" data-bs-toggle="dropdown"><i class="ti-more-alt"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item flexbox" href="#" data-bs-toggle="modal"
                                           data-bs-target="#modalSolicitud"
                                           data-form-id="<?= PacientesHelper::safe((string)($procedimientoData['form_id'] ?? '')) ?>"
                                           data-hc="<?= PacientesHelper::safe($hc_number) ?>">
                                            <span>Ver Detalles</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($timelineItems)): ?>
                        <p class="text-muted mb-0">Sin solicitudes registradas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-12">
            <?php include __DIR__ . '/components/tarjeta_paciente.php'; ?>

            <div class="row">
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h4 class="box-title">Descargar Archivos</h4>
                            <div class="dropdown pull-right">
                                <h6 class="dropdown-toggle mb-0" data-bs-toggle="dropdown">Filtro</h6>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" onclick="filterDocuments('todos'); return false;">Todos</a>
                                    <a class="dropdown-item" href="#"
                                       onclick="filterDocuments('ultimo_mes'); return false;">Último Mes</a>
                                    <a class="dropdown-item" href="#"
                                       onclick="filterDocuments('ultimos_3_meses'); return false;">Últimos 3 Meses</a>
                                    <a class="dropdown-item" href="#"
                                       onclick="filterDocuments('ultimos_6_meses'); return false;">Últimos 6 Meses</a>
                                </div>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="media-list media-list-divided">
                                <?php foreach ($documentos as $documento): ?>
                                    <?php $isProtocolo = isset($documento['membrete']); ?>
                                    <div class="media media-single px-0">
                                        <div class="ms-0 me-15 bg-<?= $isProtocolo ? 'success' : 'primary' ?>-light h-50 w-50 l-h-50 rounded text-center d-flex align-items-center justify-content-center">
                                            <span class="fs-24 text-<?= $isProtocolo ? 'success' : 'primary' ?>">
                                                <i class="fa fa-file-<?= $isProtocolo ? 'pdf' : 'text' ?>-o"></i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1">
                                            <span class="title fw-500 fs-16 text-truncate" style="max-width: 200px;">
                                                <?= PacientesHelper::safe($documento['membrete'] ?? $documento['procedimiento'] ?? 'Documento') ?>
                                            </span>
                                            <span class="text-fade fw-500 fs-12">
                                                <?= PacientesHelper::formatDateSafe($documento['fecha_inicio'] ?? ($documento['created_at'] ?? '')) ?>
                                            </span>
                                        </div>
                                        <?php if ($isProtocolo): ?>
                                            <a class="fs-18 text-gray hover-info" href="#"
                                               onclick="window.descargarPDFsSeparados('<?= PacientesHelper::safe((string)($documento['form_id'] ?? '')) ?>', '<?= PacientesHelper::safe($documento['hc_number'] ?? '') ?>'); return false;">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <?php
                                            $hcQuery = htmlspecialchars(urlencode($documento['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $formIdQuery = htmlspecialchars(urlencode((string)($documento['form_id'] ?? '')), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <a class="fs-18 text-gray hover-info"
                                               href="<?= $solicitudPdfBaseUrlEscaped ?>?hc_number=<?= $hcQuery ?>&amp;form_id=<?= $formIdQuery ?>"
                                               target="_blank" rel="noopener noreferrer">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($documentos)): ?>
                                    <p class="text-muted mb-0">No hay documentos disponibles.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header no-border">
                            <h4 class="box-title">Estadísticas de Citas</h4>
                        </div>
                        <div class="box-body">
                            <div id="chart123"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalSolicitud" tabindex="-1" aria-labelledby="modalSolicitudLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSolicitudLabel">Detalle de la Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 rounded" id="solicitudContainer" style="background-color: #e9f5ff;">
                    <p class="mb-1"><strong>Fecha:</strong> <span id="modalFecha"
                                                                  class="float-end badge bg-light text-dark"></span></p>
                    <p class="mb-1"><strong>Procedimiento:</strong>
                        <span id="modalProcedimiento"></span></p>
                    <p class="mb-1"><strong>Ojo:</strong>
                        <span id="modalOjo"></span></p>
                    <p class="mb-1"><strong>Diagnóstico:</strong>
                        <span id="modalDiagnostico"></span></p>
                    <p class="mb-1"><strong>Doctor:</strong>
                        <span id="modalDoctor"></span>
                    </p>
                    <p class="mb-1"><strong>Estado:</strong>
                        <span id="modalEstado" class="float-end badge bg-secondary"></span>
                        <span id="modalSemaforo" class="float-end me-2 badge"
                              style="width: 16px; height: 16px; border-radius: 50%;"></span>
                    </p>
                </div>
                <p><strong>Motivo:</strong> <span id="modalMotivo"></span></p>
                <p><strong>Enfermedad Actual:</strong> <span id="modalEnfermedad"></span></p>
                <p><strong>Plan:</strong> <span id="modalDescripcion"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/components/modal_editar_paciente.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartContainer = document.querySelector('#chart123');
        if (!chartContainer) {
            return;
        }

        const series = <?= json_encode(array_values($estadisticas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const labels = <?= json_encode(array_keys($estadisticas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        if (typeof ApexCharts === 'undefined' || !Array.isArray(series) || series.length === 0) {
            chartContainer.innerHTML = '<p class="text-muted mb-0">Sin datos suficientes para mostrar estadísticas.</p>';
            return;
        }

        const options = {
            series: series,
            chart: {
                type: 'donut'
            },
            colors: ['#3246D3', '#00D0FF', '#ee3158', '#ffa800', '#05825f'],
            legend: {
                position: 'bottom'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '45%'
                    }
                }
            },
            labels: labels,
            responsive: [
                {
                    breakpoint: 1600,
                    options: {
                        chart: {
                            width: 330
                        }
                    }
                },
                {
                    breakpoint: 500,
                    options: {
                        chart: {
                            width: 280
                        }
                    }
                }
            ]
        };

        const chart = new ApexCharts(chartContainer, options);
        chart.render();
    });
</script>
