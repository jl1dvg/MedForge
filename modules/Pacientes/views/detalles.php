<?php
use Helpers\PacientesHelper;

/** @var array $patientData */
/** @var string $hc_number */
/** @var array $afiliacionesDisponibles */
/** @var array $diagnosticos */
/** @var array $medicos */
/** @var array $timelineItems */
/** @var array $eventos */
/** @var array $documentos */
/** @var array $estadisticas */

$nombrePaciente = trim(($patientData['fname'] ?? '') . ' ' . ($patientData['lname'] ?? '') . ' ' . ($patientData['lname2'] ?? ''));
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Detalles del paciente</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/pacientes"><i class="mdi mdi-account-multiple"></i></a></li>
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
                                <p>Fecha de nacimiento:<span class="text-gray ps-10"><?= PacientesHelper::safe($patientData['fecha_nacimiento'] ?? '—') ?></span></p>
                                <p>Celular:<span class="text-gray ps-10"><?= PacientesHelper::safe($patientData['celular'] ?? '—') ?></span></p>
                                <p>Dirección:<span class="text-gray ps-10"><?= PacientesHelper::safe($patientData['ciudad'] ?? '—') ?></span></p>
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
                                        <div class="timeline-panel text-muted">
                                            <h4 class="mb-2 mt-1"><?= PacientesHelper::safe($diagnosis['idDiagnostico'] ?? '') ?></h4>
                                            <p class="fs-15 mb-0 "><?= PacientesHelper::safe($diagnosis['fecha'] ?? '') ?></p>
                                        </div>
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
                    <h4 class="box-title">Actualizar datos del paciente</h4>
                </div>
                <div class="box-body">
                    <form method="POST" action="/pacientes/detalles?hc_number=<?= urlencode($hc_number) ?>">
                        <input type="hidden" name="actualizar_paciente" value="1">
                        <div class="form-group">
                            <label for="fname">Nombre</label>
                            <input type="text" id="fname" name="fname" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['fname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="mname">Segundo nombre</label>
                            <input type="text" id="mname" name="mname" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['mname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="lname">Apellido</label>
                            <input type="text" id="lname" name="lname" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['lname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="lname2">Segundo apellido</label>
                            <input type="text" id="lname2" name="lname2" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['lname2'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="afiliacion">Afiliación</label>
                            <select id="afiliacion" name="afiliacion" class="form-control">
                                <?php foreach ($afiliacionesDisponibles as $afiliacion): ?>
                                    <option value="<?= PacientesHelper::safe($afiliacion) ?>"
                                        <?= (($patientData['afiliacion'] ?? '') === $afiliacion) ? 'selected' : '' ?>>
                                        <?= PacientesHelper::safe($afiliacion) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_nacimiento">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['fecha_nacimiento'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="sexo">Sexo</label>
                            <select id="sexo" name="sexo" class="form-control">
                                <option value="M" <?= (($patientData['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Masculino</option>
                                <option value="F" <?= (($patientData['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Femenino</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="celular">Celular</label>
                            <input type="text" id="celular" name="celular" class="form-control"
                                   value="<?= PacientesHelper::safe($patientData['celular'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Médicos asignados</h4>
                </div>
                <div class="box-body">
                    <div class="row">
                        <?php if (!empty($medicos)): ?>
                            <?php foreach ($medicos as $doctor => $info): ?>
                                <div class="col-md-6 col-12">
                                    <div class="box box-inverse box-primary">
                                        <div class="box-body">
                                            <div class="flexbox align-items-center">
                                                <div>
                                                    <h5 class="text-white mb-5"><?= PacientesHelper::safe($doctor) ?></h5>
                                                    <span class="badge bg-success-light">Form ID: <?= PacientesHelper::safe($info['form_id'] ?? '-') ?></span>
                                                </div>
                                                <div class="text-white-50"><i class="fa fa-user-md fa-2x"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay médicos asignados para este paciente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Historial de solicitudes y prefacturas</h4>
                </div>
                <div class="box-body">
                    <div class="timeline">
                        <?php if (!empty($timelineItems)): ?>
                            <?php foreach ($timelineItems as $item): ?>
                                <div class="timeline-item">
                                    <span class="time"><i class="fa fa-clock-o"></i> <?= PacientesHelper::formatDateSafe($item['fecha'] ?? '') ?></span>
                                    <h3 class="timeline-header">
                                        <span class="badge bg-info"><?= PacientesHelper::safe($item['origen'] ?? '') ?></span>
                                        <?= PacientesHelper::safe($item['nombre'] ?? '') ?>
                                    </h3>
                                    <div class="timeline-body">
                                        <?php if (!empty($item['form_id'])): ?>
                                            <a href="/modules/solicitudes/views/solicitudes.php?form_id=<?= urlencode($item['form_id']) ?>" class="btn btn-sm btn-primary">Ver detalle</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Sin registros en el historial.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Eventos del timeline</h4>
                </div>
                <div class="box-body">
                    <?php if (!empty($eventos)): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($eventos as $evento): ?>
                                <li class="mb-15">
                                    <strong><?= PacientesHelper::formatDateSafe($evento['fecha'] ?? '') ?>:</strong>
                                    <?= PacientesHelper::safe($evento['procedimiento_proyectado'] ?? ($evento['contenido'] ?? '')) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No hay eventos registrados.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Documentos disponibles</h4>
                </div>
                <div class="box-body">
                    <?php if (!empty($documentos)): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($documentos as $doc): ?>
                                <li class="mb-10">
                                    <strong><?= PacientesHelper::safe($doc['procedimiento'] ?? $doc['membrete'] ?? 'Documento') ?></strong>
                                    <span class="text-muted ms-5"><?= PacientesHelper::formatDateSafe($doc['fecha_inicio'] ?? ($doc['created_at'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No hay documentos para mostrar.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Estadísticas de procedimientos</h4>
                </div>
                <div class="box-body">
                    <?php if (!empty($estadisticas)): ?>
                        <div class="row">
                            <?php foreach ($estadisticas as $nombre => $cantidad): ?>
                                <div class="col-md-4 col-12">
                                    <div class="box pull-up">
                                        <div class="box-body text-center">
                                            <h5 class="fw-600"><?= PacientesHelper::safe($nombre) ?></h5>
                                            <p class="fs-24 mb-0 text-primary"><?= (int) $cantidad ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No hay estadísticas disponibles.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vendor JS -->
<script src="<?= asset('js/vendors.min.js') ?>"></script>
<script src="<?= asset('js/pages/chat-popup.js') ?>"></script>
<script src="<?= asset('assets/icons/feather-icons/feather.min.js') ?>"></script>

<!-- Doclinic App -->
<script src="<?= asset('js/jquery.smartmenus.js') ?>"></script>
<script src="<?= asset('js/menus.js') ?>"></script>
<script src="<?= asset('js/template.js') ?>"></script>
