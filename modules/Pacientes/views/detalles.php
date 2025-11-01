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

$nombrePaciente = trim(($patientData['fname'] ?? '') . ' ' . ($patientData['mname'] ?? '') . ' ' . ($patientData['lname'] ?? '') . ' ' . ($patientData['lname2'] ?? ''));
$timelineColorMap = [
    'solicitud' => 'bg-primary',
    'prefactura' => 'bg-info',
    'cirugia' => 'bg-danger',
    'interconsulta' => 'bg-warning',
];
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
                        <li class="breadcrumb-item active" aria-current="page">HC <?= PacientesHelper::safe($hc_number) ?></li>
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
                                <p>Nombre completo:<span class="text-gray ps-10"><?= PacientesHelper::safe($nombrePaciente) ?></span></p>
                                <p>Fecha de Nacimiento:<span class="text-gray ps-10"><?= PacientesHelper::formatDateSafe($patientData['fecha_nacimiento'] ?? null) ?></span></p>
                                <p>Edad:<span class="text-gray ps-10"><?= $patientAge !== null ? PacientesHelper::safe((string) $patientAge . ' años') : '—' ?></span></p>
                                <p>Celular:<span class="text-gray ps-10"><?= PacientesHelper::safe($patientData['celular'] ?? '—') ?></span></p>
                                <p>Dirección:<span class="text-gray ps-10"><?= PacientesHelper::safe($patientData['ciudad'] ?? '—') ?></span></p>
                            </div>
                        </div>
                        <div class="col-12 mt-20">
                            <div class="pb-15">
                                <p class="mb-10">Social Profile</p>
                                <div class="user-social-acount">
                                    <button class="btn btn-circle btn-social-icon btn-facebook"><i class="fa fa-facebook"></i></button>
                                    <button class="btn btn-circle btn-social-icon btn-twitter"><i class="fa fa-twitter"></i></button>
                                    <button class="btn btn-circle btn-social-icon btn-instagram"><i class="fa fa-instagram"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="map-box">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2805244.1745767146!2d-86.32675167439648!3d29.383165774894163!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x88c1766591562abf%3A0xf72e13d35bc74ed0!2sFlorida%2C+USA!5e0!3m2!1sen!2sin!4v1501665415329"
                                        width="100%" height="175" frameborder="0" style="border:0" allowfullscreen></iframe>
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
                            <button class="btn btn-primary dropdown-toggle px-10" data-bs-toggle="dropdown" href="#">Crear</button>
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
                                <input type="checkbox" id="<?= $checkboxId ?>" class="filled-in chk-col-<?= $bulletColor ?>">
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
                                           data-form-id="<?= PacientesHelper::safe((string) ($procedimientoData['form_id'] ?? '')) ?>"
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

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Actualizar datos del paciente</h4>
                </div>
                <div class="box-body">
                    <form method="POST" action="/pacientes/detalles?hc_number=<?= urlencode($hc_number) ?>">
                        <input type="hidden" name="actualizar_paciente" value="1">
                        <div class="form-group">
                            <label for="fname">Primer nombre</label>
                            <input type="text" id="fname" name="fname" class="form-control" value="<?= PacientesHelper::safe($patientData['fname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="mname">Segundo nombre</label>
                            <input type="text" id="mname" name="mname" class="form-control" value="<?= PacientesHelper::safe($patientData['mname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="lname">Primer apellido</label>
                            <input type="text" id="lname" name="lname" class="form-control" value="<?= PacientesHelper::safe($patientData['lname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="lname2">Segundo apellido</label>
                            <input type="text" id="lname2" name="lname2" class="form-control" value="<?= PacientesHelper::safe($patientData['lname2'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="afiliacion">Afiliación</label>
                            <select id="afiliacion" name="afiliacion" class="form-control">
                                <?php foreach ($afiliacionesDisponibles as $afiliacion): ?>
                                    <option value="<?= PacientesHelper::safe($afiliacion) ?>" <?= strtolower($afiliacion) === strtolower($patientData['afiliacion'] ?? '') ? 'selected' : '' ?>>
                                        <?= PacientesHelper::safe($afiliacion) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_nacimiento">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control" value="<?= PacientesHelper::safe($patientData['fecha_nacimiento'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="sexo">Sexo</label>
                            <select id="sexo" name="sexo" class="form-control">
                                <option value="Masculino" <?= strtolower($patientData['sexo'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="Femenino" <?= strtolower($patientData['sexo'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="celular">Celular</label>
                            <input type="text" id="celular" name="celular" class="form-control" value="<?= PacientesHelper::safe($patientData['celular'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar cambios</button>
                    </form>
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
                                    <a class="dropdown-item" href="#" onclick="filterDocuments('ultimo_mes'); return false;">Último Mes</a>
                                    <a class="dropdown-item" href="#" onclick="filterDocuments('ultimos_3_meses'); return false;">Últimos 3 Meses</a>
                                    <a class="dropdown-item" href="#" onclick="filterDocuments('ultimos_6_meses'); return false;">Últimos 6 Meses</a>
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
                                        <a class="fs-18 text-gray hover-info" href="#"
                                           onclick="<?php if ($isProtocolo): ?>descargarPDFsSeparados('<?= PacientesHelper::safe((string) ($documento['form_id'] ?? '')) ?>', '<?= PacientesHelper::safe($documento['hc_number'] ?? '') ?>')<?php else: ?>window.open('../reports/solicitud_quirurgica/solicitud_qx_pdf.php?hc_number=<?= PacientesHelper::safe($documento['hc_number'] ?? '') ?>&form_id=<?= PacientesHelper::safe((string) ($documento['form_id'] ?? '')) ?>', '_blank')<?php endif; ?>">
                                            <i class="fa fa-download"></i>
                                        </a>
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

<?php include __DIR__ . '/components/modal_editar_paciente.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        filterDocuments('ultimos_3_meses');
    });

    function filterDocuments(filter) {
        const items = document.querySelectorAll('.media-list .media');
        const now = new Date();

        items.forEach(item => {
            const dateElement = item.querySelector('.text-fade');
            const dateText = dateElement ? dateElement.textContent.trim() : '';
            const itemDate = dateText ? new Date(dateText) : null;
            let showItem = true;

            if (itemDate instanceof Date && !isNaN(itemDate)) {
                switch (filter) {
                    case 'ultimo_mes':
                        const lastMonth = new Date(now);
                        lastMonth.setMonth(now.getMonth() - 1);
                        showItem = itemDate >= lastMonth;
                        break;
                    case 'ultimos_3_meses':
                        const last3Months = new Date(now);
                        last3Months.setMonth(now.getMonth() - 3);
                        showItem = itemDate >= last3Months;
                        break;
                    case 'ultimos_6_meses':
                        const last6Months = new Date(now);
                        last6Months.setMonth(now.getMonth() - 6);
                        showItem = itemDate >= last6Months;
                        break;
                    default:
                        showItem = true;
                }
            }

            item.style.display = showItem ? 'flex' : 'none';
        });
    }
</script>
