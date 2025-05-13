<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\PacienteController;
use Controllers\ReporteCirugiasController;

$reporteCirugiasController = new ReporteCirugiasController($pdo);
$pacienteController = new PacienteController($pdo);
$dashboardController = new DashboardController($pdo);

$cirugias = $reporteCirugiasController->obtenerCirugias();
$form_id = $_GET['form_id'] ?? null;
$hc_number = $_GET['hc_number'] ?? null;
$cirugia = $reporteCirugiasController->obtenerCirugiaPorId($form_id, $hc_number);
$username = $dashboardController->getAuthenticatedUser();
$cirujanos = $pacienteController->obtenerStaffPorEspecialidad();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">

    <?php include __DIR__ . '/../../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <section class="content">

                <!-- vertical wizard -->
                <?php
                if (!$cirugia) {
                    die("No se encontró información para el form_id y hc_number proporcionados.");
                }
                ?>

                <!-- Formulario de modificación de información -->
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title">Modificar Información del Procedimiento</h4>
                    </div>
                    <div class="box-body wizard-content">
                        <form action="/views/reportes/wizard_cirugia/guardar.php" method="POST"
                              class="tab-wizard vertical wizard-circle">
                            <!-- Enviar form_id y hc_number ocultos para saber qué registro actualizar -->
                            <input type="hidden" name="form_id" value="<?php echo htmlspecialchars($form_id); ?>">
                            <input type="hidden" name="hc_number" value="<?php echo htmlspecialchars($hc_number); ?>">

                            <!-- Sección 1: Datos del Paciente -->
                            <h6>Datos del Paciente</h6>
                            <section>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="firstName1" class="form-label">Nombre :</label>
                                            <input type="text" class="form-control" id="firstName1" name="fname"
                                                   value="<?php echo htmlspecialchars($cirugia->fname); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="middleName2" class="form-label">Segundo Nombre:</label>
                                            <input type="text" class="form-control" id="middleName2" name="mname"
                                                   value="<?php echo htmlspecialchars($cirugia->mname ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lastName1" class="form-label">Primer Apellido:</label>
                                            <input type="text" class="form-control" id="lastName1" name="lname"
                                                   value="<?php echo htmlspecialchars($cirugia->lname); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lastName2" class="form-label">Segundo Apellido:</label>
                                            <input type="text" class="form-control" id="lastName2" name="lname2"
                                                   value="<?php echo htmlspecialchars($cirugia->lname2); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="birthDate" class="form-label">Fecha de Nacimiento :</label>
                                            <input type="date" class="form-control" id="birthDate"
                                                   name="fecha_nacimiento"
                                                   value="<?php echo htmlspecialchars($cirugia->fecha_nacimiento); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="afiliacion" class="form-label">Afiliación :</label>
                                            <input type="text" class="form-control" id="afiliacion" name="afiliacion"
                                                   value="<?php echo htmlspecialchars($cirugia->afiliacion); ?>"
                                                   readonly>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Sección 2: Procedimientos, Diagnósticos y Lateralidad -->
                            <h6>Procedimientos, Diagnósticos y Lateralidad</h6>
                            <section>
                                <!-- Procedimientos -->
                                <div class="form-group">
                                    <label for="procedimientos" class="form-label">Procedimientos :</label>
                                    <?php
                                    $procedimientosArray = json_decode($cirugia->procedimientos, true); // Decodificar el JSON

                                    // Si hay procedimientos, los mostramos en inputs separados
                                    if (!empty($procedimientosArray)) {
                                        foreach ($procedimientosArray as $index => $proc) {
                                            $codigo = isset($proc['procInterno']) ? $proc['procInterno'] : '';  // Código completo del procedimiento
                                            echo '<div class="row mb-2">';
                                            echo '<div class="col-md-12">';
                                            echo '<input type="text" class="form-control" name="procedimientos[' . $index . '][procInterno]" value="' . htmlspecialchars($codigo) . '" />';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<input type="text" class="form-control" name="procedimientos[0][procInterno]" placeholder="Agregar Procedimiento" />';
                                    }
                                    ?>
                                </div>

                                <!-- Diagnósticos -->
                                <div class="form-group">
                                    <label for="diagnosticos" class="form-label">Diagnósticos :</label>
                                    <?php
                                    $diagnosticosArray = json_decode($cirugia->diagnosticos, true); // Decodificar el JSON

                                    // Si hay diagnósticos, los mostramos en inputs separados
                                    if (!empty($diagnosticosArray)) {
                                        foreach ($diagnosticosArray as $index => $diag) {
                                            $ojo = isset($diag['ojo']) ? $diag['ojo'] : '';
                                            $evidencia = isset($diag['evidencia']) ? $diag['evidencia'] : '';
                                            $idDiagnostico = isset($diag['idDiagnostico']) ? $diag['idDiagnostico'] : '';
                                            $observaciones = isset($diag['observaciones']) ? $diag['observaciones'] : '';

                                            echo '<div class="row mb-2">';
                                            echo '<div class="col-md-2">';
                                            echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][ojo]" value="' . htmlspecialchars($ojo) . '" placeholder="Ojo" />';
                                            echo '</div>';
                                            echo '<div class="col-md-2">';
                                            echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][evidencia]" value="' . htmlspecialchars($evidencia) . '" placeholder="Evidencia" />';
                                            echo '</div>';
                                            echo '<div class="col-md-6">';
                                            echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][idDiagnostico]" value="' . htmlspecialchars($idDiagnostico) . '" placeholder="Código CIE-10" />';
                                            echo '</div>';
                                            echo '<div class="col-md-2">';
                                            echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][observaciones]" value="' . htmlspecialchars($observaciones) . '" placeholder="Observaciones" />';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<input type="text" class="form-control" name="diagnosticos[0][idDiagnostico]" placeholder="Agregar Diagnóstico" />';
                                    }
                                    ?>
                                </div>

                                <!-- Lateralidad -->
                                <div class="form-group">
                                    <label for="lateralidad" class="form-label">Lateralidad :</label>
                                    <select class="form-select" id="lateralidad" name="lateralidad">
                                        <option value="OD" <?= ($cirugia->lateralidad == 'OD') ? 'selected' : '' ?>>
                                            OD
                                        </option>
                                        <option value="OI" <?= ($cirugia->lateralidad == 'OI') ? 'selected' : '' ?>>
                                            OI
                                        </option>
                                        <option value="AO" <?= ($cirugia->lateralidad == 'AO') ? 'selected' : '' ?>>
                                            AO
                                        </option>
                                    </select>
                                </div>
                            </section>

                            <!-- Sección 3: Staff Quirúrgico -->
                            <h6>Staff Quirúrgico</h6>
                            <section>
                                <div class="row">
                                    <!-- Cirujano Principal -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="mainSurgeon" class="form-label">Cirujano Principal :</label>
                                            <select class="form-select" id="mainSurgeon" name="cirujano_1"
                                                    data-placeholder="Escoja el Cirujano Principal">
                                                <option value="" <?= empty($cirugia->cirujano_1) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->cirujano_1))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Cirujano Asistente -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="assistantSurgeon" class="form-label">Cirujano Asistente
                                                :</label>
                                            <select class="form-select" id="assistantSurgeon" name="cirujano_2"
                                                    data-placeholder="Escoja el Cirujano 2">
                                                <option value="" <?= empty($cirugia->cirujano_2) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->cirujano_2))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Primer Ayudante -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="primerAyudante" class="form-label">Primer Ayudante :</label>
                                            <select class="form-select" id="primerAyudante" name="primer_ayudante">
                                                <option value="" <?= empty($cirugia->primer_ayudante) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->primer_ayudante))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Segundo Ayudante -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="segundoAyudante" class="form-label">Segundo Ayudante :</label>
                                            <select class="form-select" id="segundoAyudante" name="segundo_ayudante">
                                                <option value="" <?= empty($cirugia->segundo_ayudante) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->segundo_ayudante))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Tercer Ayudante -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tercerAyudante" class="form-label">Tercer Ayudante :</label>
                                            <select class="form-select" id="tercerAyudante" name="tercer_ayudante">
                                                <option value="" <?= empty($cirugia->tercer_ayudante) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->tercer_ayudante))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Ayudante de Anestesia -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ayudanteAnestesia" class="form-label">Ayudante de Anestesia
                                                :</label>
                                            <select class="form-select" id="ayudanteAnestesia" name="ayudanteAnestesia">
                                                <option value="" <?= empty($cirugia->ayudante_anestesia) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->ayudante_anestesia))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Anestesiólogo -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="anesthesiologist" class="form-label">Anestesiólogo :</label>
                                            <select class="form-select" id="anesthesiologist"
                                                    name="anestesiologo"
                                                    data-placeholder="Escoja el anestesiologo">
                                                <option value="" <?= empty($cirugia->anestesiologo) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Anestesiologo'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->anestesiologo))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Instrumentista -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="instrumentista" class="form-label">Instrumentista :</label>
                                            <select class="form-select" id="instrumentista" name="instrumentista">
                                                <option value="" <?= empty($cirugia->instrumentista) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->instrumentista))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Enfermera Circulante -->
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="circulante" class="form-label">Enfermera Circulante :</label>
                                            <select class="form-select" id="circulante" name="circulante">
                                                <option value="" <?= empty($cirugia->circulante) ? 'selected' : '' ?>></option>
                                                <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                                                    <option value="<?= htmlspecialchars($nombre) ?>" <?= (trim(strtolower($nombre)) === trim(strtolower($cirugia->circulante))) ? 'selected' : '' ?>>
                                                        <?= strtoupper(htmlspecialchars($nombre)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <!-- Sección 4: Fechas, Horas y Tipo de Anestesia -->
                            <h6>Fechas, Horas y Tipo de Anestesia</h6>
                            <section>
                                <!-- Fecha de Inicio -->
                                <div class="form-group">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio :</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                                           value="<?php echo htmlspecialchars($cirugia->fecha_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <!-- Hora de Inicio -->
                                <div class="form-group">
                                    <label for="hora_inicio" class="form-label">Hora de Inicio :</label>
                                    <input type="time" class="form-control" id="hora_inicio" name="hora_inicio"
                                           value="<?php echo htmlspecialchars($cirugia->hora_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <!-- Fecha de Fin -->
                                <div class="form-group">
                                    <label for="fecha_fin" class="form-label">Fecha de Fin :</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                                           value="<?php echo htmlspecialchars($cirugia->fecha_fin ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <!-- Hora de Fin -->
                                <div class="form-group">
                                    <label for="hora_fin" class="form-label">Hora de Fin :</label>
                                    <input type="time" class="form-control" id="hora_fin" name="hora_fin"
                                           value="<?php echo htmlspecialchars($cirugia->hora_fin ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <!-- Tipo de Anestesia -->
                                <div class="form-group">
                                    <label for="tipo_anestesia" class="form-label">Tipo de Anestesia :</label>
                                    <select class="form-select" id="tipo_anestesia" name="tipo_anestesia">
                                        <option value="GENERAL" <?= ($cirugia->tipo_anestesia == 'GENERAL') ? 'selected' : '' ?>>
                                            GENERAL
                                        </option>
                                        <option value="LOCAL" <?= ($cirugia->tipo_anestesia == 'LOCAL') ? 'selected' : '' ?>>
                                            LOCAL
                                        </option>
                                        <option value="OTROS" <?= ($cirugia->tipo_anestesia == 'OTROS') ? 'selected' : '' ?>>
                                            OTROS
                                        </option>
                                        <option value="REGIONAL" <?= ($cirugia->tipo_anestesia == 'REGIONAL') ? 'selected' : '' ?>>
                                            REGIONAL
                                        </option>
                                        <option value="SEDACION" <?= ($cirugia->tipo_anestesia == 'SEDACION') ? 'selected' : '' ?>>
                                            SEDACION
                                        </option>
                                    </select>
                                </div>
                            </section>

                            <!-- Sección 5: Procedimiento -->
                            <h6>Procedimiento</h6>
                            <section>
                                <!-- Procedimiento Proyectado -->
                                <div class="form-group">
                                    <label for="procedimiento_proyectado" class="form-label">Procedimiento Proyectado
                                        :</label>
                                    <textarea name="procedimiento_proyectado" id="procedimiento_proyectado" rows="3"
                                              class="form-control"
                                              readonly><?php echo htmlspecialchars($cirugia->procedimiento_proyectado ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Procedimiento Realizado (Membrete) -->
                                <div class="form-group">
                                    <label for="membrete" class="form-label">Procedimiento Realizado (Cirugía Realizada)
                                        :</label>
                                    <textarea name="membrete" id="membrete" rows="4"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->membrete ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Dieresis -->
                                <div class="form-group">
                                    <label for="dieresis" class="form-label">Dieresis :</label>
                                    <textarea name="dieresis" id="dieresis" rows="2"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->dieresis ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Exposición -->
                                <div class="form-group">
                                    <label for="exposicion" class="form-label">Exposición :</label>
                                    <textarea name="exposicion" id="exposicion" rows="2"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->exposicion ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Hallazgo -->
                                <div class="form-group">
                                    <label for="hallazgo" class="form-label">Hallazgo :</label>
                                    <textarea name="hallazgo" id="hallazgo" rows="3"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->hallazgo ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Descripción Operatoria -->
                                <div class="form-group">
                                    <label for="operatorio" class="form-label">Descripción Operatoria :</label>
                                    <textarea name="operatorio" id="operatorio" rows="5"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->operatorio ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Complicaciones Operatorias -->
                                <div class="form-group">
                                    <label for="complicaciones_operatorio" class="form-label">Complicaciones Operatorias
                                        :</label>
                                    <textarea name="complicaciones_operatorio" id="complicaciones_operatorio" rows="3"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->complicaciones_operatorio ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>

                                <!-- Detalles de la Cirugía -->
                                <div class="form-group">
                                    <label for="datos_cirugia" class="form-label">Detalles de la Cirugía :</label>
                                    <textarea name="datos_cirugia" id="datos_cirugia" rows="5"
                                              class="form-control"><?php echo htmlspecialchars($cirugia->datos_cirugia ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </section>

                            <!-- Sección 5: Insumos -->
                            <h6>Insumos</h6>
                            <section>
                                <?php
                                $insumosDisponibles = $reporteCirugiasController->obtenerInsumosDisponibles($cirugia->afiliacion);
                                $jsonInsumos = trim($cirugia->insumos ?? '');
                                if ($jsonInsumos === '' || $jsonInsumos === '[]') {
                                    $insumos = $reporteCirugiasController->obtenerInsumosPorProtocolo($cirugia->procedimiento_id, null);
                                } else {
                                    $insumos = json_decode($jsonInsumos, true);
                                }
                                $categorias = array_keys($insumosDisponibles);
                                ?>
                                <div class="table-responsive">
                                    <table id="insumosTable" class="table editable-table mb-0">
                                        <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Nombre del Insumo</th>
                                            <th>Cantidad</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        foreach (['equipos', 'quirurgicos', 'anestesia'] as $categoriaOrdenada):
                                            if (!empty($insumos[$categoriaOrdenada])):
                                                foreach ($insumos[$categoriaOrdenada] as $item):
                                                    $idInsumo = $item['id'];
                                                    ?>
                                                    <tr class="categoria-<?= htmlspecialchars($categoriaOrdenada) ?>">
                                                        <td>
                                                            <select class="form-control categoria-select"
                                                                    name="categoria">
                                                                <?php foreach ($categorias as $cat): ?>
                                                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat == $categoriaOrdenada) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars(str_replace('_', ' ', $cat)) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select class="form-control nombre-select" name="id">
                                                                <?php foreach ($insumosDisponibles[$categoriaOrdenada] as $id => $insumo): ?>
                                                                    <option value="<?= htmlspecialchars($id) ?>" <?= ($id == $idInsumo) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($insumo['nombre']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td contenteditable="true"><?= htmlspecialchars($item['cantidad']) ?></td>
                                                        <td>
                                                            <button class="delete-btn btn btn-danger"><i
                                                                        class="fa fa-minus"></i></button>
                                                            <button class="add-row-btn btn btn-success"><i
                                                                        class="fa fa-plus"></i></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach;
                                            endif;
                                        endforeach; ?>
                                        </tbody>
                                    </table>
                                    <input type="hidden" id="insumosInput" name="insumos"
                                           value='<?= htmlspecialchars(json_encode($insumos)) ?>'>
                                </div>
                            </section>

                            <!-- Sección 6: Medicamentos (Kardex) -->
                            <h6>Medicamentos</h6>
                            <section>
                                <?php
                                // Obtener medicamentos desde protocolo_data o fallback desde kardex
                                $jsonMedicamentos = trim($cirugia->medicamentos ?? '');
                                if ($jsonMedicamentos === '' || $jsonMedicamentos === '[]') {
                                    $stmt = $pdo->prepare("SELECT medicamentos FROM kardex WHERE procedimiento_id = ?");
                                    $stmt->execute([$cirugia->procedimiento_id]);
                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $medicamentos = json_decode($row['medicamentos'] ?? '[]', true);
                                } else {
                                    $medicamentos = json_decode($jsonMedicamentos, true);
                                }

                                $opcionesMedicamentos = [];
                                $stmtOpciones = $pdo->query("SELECT id, medicamento FROM medicamentos ORDER BY medicamento");
                                while ($op = $stmtOpciones->fetch(PDO::FETCH_ASSOC)) {
                                    $opcionesMedicamentos[] = $op;
                                }

                                $vias = ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'];
                                $responsables = ['Asistente', 'Anestesiólogo', 'Cirujano Principal'];
                                ?>
                                <div class="table-responsive">
                                    <table id="medicamentosTable" class="table editable-table mb-0">
                                        <thead>
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Dosis</th>
                                            <th>Frecuencia</th>
                                            <th>Vía</th>
                                            <th>Responsable</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($medicamentos as $item): ?>
                                            <tr>
                                                <td>
                                                    <select class="form-control medicamento-select"
                                                            name="medicamento[]">
                                                        <?php foreach ($opcionesMedicamentos as $op): ?>
                                                            <option value="<?= $op['id'] ?>" <?= ($op['id'] == ($item['id'] ?? null)) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($op['medicamento']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td contenteditable="true"><?= htmlspecialchars($item['dosis'] ?? '') ?></td>
                                                <td contenteditable="true"><?= htmlspecialchars($item['frecuencia'] ?? '') ?></td>
                                                <td>
                                                    <select class="form-control via-select" name="via_administracion[]">
                                                        <?php foreach ($vias as $via): ?>
                                                            <option value="<?= $via ?>" <?= ($via === ($item['via_administracion'] ?? '')) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($via) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-control responsable-select"
                                                            name="responsable[]">
                                                        <?php foreach ($responsables as $resp): ?>
                                                            <option value="<?= $resp ?>" <?= ($resp === ($item['responsable'] ?? '')) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($resp) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button class="delete-btn btn btn-danger"><i
                                                                class="fa fa-minus"></i></button>
                                                    <button class="add-row-btn btn btn-success"><i
                                                                class="fa fa-plus"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <input type="hidden" id="medicamentosInput" name="medicamentos"
                                           value='<?= htmlspecialchars(json_encode($medicamentos)) ?>'>
                                </div>
                            </section>
                            <!-- Sección 7: Estado de Revisión -->
                            <h6>Estado de Revisión</h6>
                            <section>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="status"
                                               id="statusCheckbox" value="1" <?= ($cirugia->status == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="statusCheckbox">
                                            Marcar como revisado
                                        </label>
                                    </div>
                                </div>
                            </section>
                        </form>
                    </div>
                </div>                <!-- /.box -->

            </section>
            <!-- /.content -->
        </div>
    </div>
    <!-- /.content-wrapper -->

    <?php include __DIR__ . '/../../components/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Page Content overlay -->


<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/jquery-steps-master/build/jquery.steps.js"></script>
<script src="/public/assets/vendor_components/jquery-validation-1.17.0/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>

<script src="/public/js/pages/steps.js"></script>
<style>
    #insumosTable tbody tr.categoria-equipos {
        background-color: #d4edda !important;
    }

    #insumosTable tbody tr.categoria-anestesia {
        background-color: #fff3cd !important;
    }

    #insumosTable tbody tr.categoria-quirurgicos {
        background-color: #cce5ff !important;
    }
</style>
<script>
    const afiliacionCirugia = "<?php echo strtolower($cirugia->afiliacion); ?>";
    const insumosDisponiblesJSON = <?php echo json_encode($insumosDisponibles); ?>;
    const categoriasInsumos = <?php echo json_encode($categorias); ?>;
    const categoriaOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($cat) => "<option value='$cat'>" . ucfirst(str_replace('_', ' ', $cat)) . "</option>", $categorias))
    ) ?>`;
    const medicamentoOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($m) => "<option value='{$m['id']}'>" . htmlspecialchars($m['medicamento']) . "</option>", $opcionesMedicamentos))
    ) ?>`;
    const viaOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($v) => "<option value='$v'>" . htmlspecialchars($v) . "</option>", $vias))
    ) ?>`;
    const responsableOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($r) => "<option value='$r'>" . htmlspecialchars($r) . "</option>", $responsables))
    ) ?>`;
</script>
<script src="/public/js/modules/cirugias_wizard.js"></script>

</body>
</html>

