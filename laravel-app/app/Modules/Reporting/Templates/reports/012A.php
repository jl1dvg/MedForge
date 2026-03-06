<?php

use Helpers\OpenAIHelper;

// Inicializa el helper de OpenAI (requiere que el autoload/bootstrapping ya esté cargado antes)
$ai = null;
if (class_exists(OpenAIHelper::class)) {
    $ai = new OpenAIHelper();
}
$AI_DEBUG = isset($_GET['debug_ai']) && $_GET['debug_ai'] === '1';

$layout = __DIR__ . '/../layouts/base.php';
// Normalizar inputs (preferir $data del ReportService)
$data = is_array($data ?? null) ? $data : [];
$paciente = $data['paciente'] ?? ($paciente ?? []);
$consulta = $data['consulta'] ?? ($consulta ?? []);
$diagnostico = $data['diagnostico'] ?? ($diagnostico ?? []);
$dx_derivacion = $data['dx_derivacion'] ?? ($dx_derivacion ?? []);
$solicitud = $data['solicitud'] ?? ($solicitud ?? []);
$patient = [
        'afiliacion' => $paciente['afiliacion'] ?? '',
        'hc_number' => $paciente['hc_number'] ?? '',
        'archive_number' => $paciente['hc_number'] ?? '',
        'lname' => $paciente['lname'] ?? '',
        'lname2' => $paciente['lname2'] ?? '',
        'fname' => $paciente['fname'] ?? '',
        'mname' => $paciente['mname'] ?? '',
        'sexo' => $paciente['sexo'] ?? '',
        'fecha_nacimiento' => $paciente['fecha_nacimiento'] ?? '',
        'edad' => $edadPaciente ?? '',
];

ob_start();
include __DIR__ . '/../partials/patient_header.php';
$header = ob_get_clean();

$doctorFirstName = trim((string)($consulta['doctor_fname'] ?? ''));
$doctorMiddleName = trim((string)($consulta['doctor_mname'] ?? ''));
$doctorLastName = trim((string)($consulta['doctor_lname'] ?? ''));
$doctorSecondLastName = trim((string)($consulta['doctor_lname2'] ?? ''));

$motivoConsulta = trim((string)($consulta['motivo_consulta'] ?? $consulta['motivo'] ?? ''));
$enfermedadActual = trim((string)($consulta['enfermedad_actual'] ?? ''));
$reason = trim($motivoConsulta . ' ' . $enfermedadActual);

// Fecha/Hora de la consulta (preferir datos del propio registro $consulta)
$fechaConsulta = trim((string)($consulta['fecha'] ?? ''));
$horaConsulta = '';

// Si $consulta['fecha'] viene vacío, intentar inferir desde created_at (consulta o solicitud)
$createdAtRaw = trim((string)($consulta['created_at'] ?? $solicitud['created_at'] ?? ''));

// Si tenemos fecha pero no hora, intentar extraer la hora desde created_at
if ($fechaConsulta !== '' && $createdAtRaw !== '') {
    $createdTs = strtotime($createdAtRaw);
    if ($createdTs) {
        $horaConsulta = date('H:i', $createdTs);
    }
}

// Si no hay fecha, intentar calcularla desde created_at
if ($fechaConsulta === '' && $createdAtRaw !== '') {
    $createdTs = strtotime($createdAtRaw);
    if ($createdTs) {
        $fechaConsulta = date('Y-m-d', $createdTs);
        $horaConsulta = date('H:i', $createdTs);
    }
}

// Fallbacks legacy
if ($fechaConsulta === '') {
    $fechaConsulta = (string)($solicitud['created_at_date'] ?? '');
}
if ($horaConsulta === '') {
    $horaConsulta = (string)($solicitud['created_at_time'] ?? '');
}

// Edad paciente (fallbacks)
$fechaNacimientoRaw = trim((string)($paciente['fecha_nacimiento'] ?? ($paciente['dob'] ?? ($paciente['DOB'] ?? ''))));
$edadPaciente = trim((string)($paciente['edad'] ?? ''));
if ($edadPaciente === '' && $fechaNacimientoRaw !== '') {
    try {
        $fechaRef = $fechaConsulta !== '' ? $fechaConsulta : 'now';
        $edadPaciente = (string)(new DateTime($fechaNacimientoRaw))->diff(new DateTime($fechaRef))->y;
    } catch (Exception $e) {
        $edadPaciente = '';
    }
}
$patient['edad'] = $edadPaciente;

$examenesRelacionados = $data['examenes_relacionados'] ?? ($data['examenes'] ?? []);
$imagenesSolicitadas = $data['imagenes_solicitadas'] ?? ($data['imagenes'] ?? []);
$estudiosPrecomputados012A = is_array($data['estudios_012a'] ?? null) ? $data['estudios_012a'] : [];

// --- DIAGNÓSTICOS ---
// Regla solicitada:
// 1) Primero diagnósticos desde DERIVACIÓN ($dx_derivacion: string con ';')
// 2) Luego completar con diagnósticos normalizados ($diagnostico: array con dx_code/descripcion)
// 3) No repetir (dedupe por code+desc)
// 4) Máximo 6 (el formato 002 solo muestra 6)

$diagnosticoItems = [];
$seenDx = [];

$pushDx = function (string $code, string $desc) use (&$diagnosticoItems, &$seenDx) {
    $code = trim($code);
    $desc = trim($desc);
    if ($code === '' && $desc === '') {
        return;
    }
    $k = mb_strtoupper($code . '|' . $desc);
    if (isset($seenDx[$k])) {
        return;
    }
    $seenDx[$k] = true;
    $diagnosticoItems[] = [
            'dx_code' => $code,
            'descripcion' => $desc,
    ];
};

$parseDxLine = function (string $line): array {
    $line = trim($line);
    if ($line === '') {
        return ['', ''];
    }
    $code = '';
    $desc = $line;
    // Formato típico: "H25 - CATARATA SENIL"
    if (preg_match('/^\s*([A-Z][0-9A-Z\.]+)\s*[-–]\s*(.+)\s*$/u', $line, $m)) {
        $code = trim($m[1]);
        $desc = trim($m[2]);
    }
    return [$code, $desc];
};

// 1) Diagnósticos desde DERIVACIÓN (string separada por ';')
$dxDerivacionStr = '';
if (is_array($dx_derivacion) && !empty($dx_derivacion[0]['diagnostico'])) {
    $dxDerivacionStr = (string)$dx_derivacion[0]['diagnostico'];
}
$dxDerivacionStr = trim($dxDerivacionStr);
if ($dxDerivacionStr !== '') {
    $dxDerivacionStr = preg_replace("/\r\n|\r/", "\n", $dxDerivacionStr);
    $parts = array_filter(array_map('trim', preg_split('/\s*;\s*/', $dxDerivacionStr)));
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        [$code, $desc] = $parseDxLine($p);
        $pushDx($code, $desc);
        if (count($diagnosticoItems) >= 6) {
            break;
        }
    }
}

// 2) Completar con diagnósticos normalizados ($diagnostico)
if (is_array($diagnostico) && count($diagnosticoItems) < 6) {
    foreach ($diagnostico as $dx) {
        if (!is_array($dx)) {
            continue;
        }
        $code = (string)($dx['dx_code'] ?? $dx['codigo'] ?? '');
        $desc = (string)($dx['descripcion'] ?? $dx['descripcion_dx'] ?? $dx['nombre'] ?? '');
        $pushDx($code, $desc);
        if (count($diagnosticoItems) >= 6) {
            break;
        }
    }
}
$diagnosticoTexto = [];
foreach ($diagnosticoItems as $item) {
    $codigo = trim((string)($item['dx_code'] ?? $item['codigo'] ?? ''));
    $descripcion = trim((string)($item['descripcion'] ?? $item['descripcion_dx'] ?? $item['nombre'] ?? ''));

    if ($codigo === '' && $descripcion === '') {
        continue;
    }

    $diagnosticoTexto[] = trim($descripcion . ($codigo !== '' ? ' CIE10: ' . $codigo : ''));
}

$consultaPlan = trim((string)($consulta['plan'] ?? ''));
$consultaDiagnosticoPlan = trim((string)($consulta['diagnostico_plan'] ?? ''));
$consultaRecomendaciones = trim((string)($consulta['recomen_no_farmaco'] ?? ''));
$consultaSignosAlarma = trim((string)($consulta['signos_alarma'] ?? ''));
$consultaVigenciaReceta = trim((string)($consulta['vigencia_receta'] ?? ''));

$planLineas = array_values(array_filter([
        $consultaDiagnosticoPlan !== '' ? 'Diagnóstico/Plan: ' . $consultaDiagnosticoPlan : '',
        $consultaPlan !== '' ? 'Plan terapéutico: ' . $consultaPlan : '',
        $consultaRecomendaciones !== '' ? 'Recomendaciones: ' . $consultaRecomendaciones : '',
        $consultaSignosAlarma !== '' ? 'Signos de alarma: ' . $consultaSignosAlarma : '',
        $consultaVigenciaReceta !== '' ? 'Vigencia receta: ' . $consultaVigenciaReceta : '',
], static fn($line) => trim($line) !== ''));
$planTratamiento = implode(PHP_EOL, $planLineas);

$normalizarTexto = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/[^a-z0-9 ]/u', '', $value);
    return trim($value ?? '');
};

$estudiosRaw = [];
if (is_array($examenesRelacionados)) {
    foreach ($examenesRelacionados as $rel) {
        if (!is_array($rel)) {
            continue;
        }
        $nombre = trim((string)($rel['examen_nombre'] ?? $rel['nombre'] ?? $rel['examen'] ?? ''));
        if ($nombre === '') {
            continue;
        }
        $estudiosRaw[] = [
                'nombre' => $nombre,
                'codigo' => trim((string)($rel['examen_codigo'] ?? $rel['codigo'] ?? '')),
                'estado' => trim((string)($rel['kanban_estado'] ?? $rel['estado'] ?? '')),
        ];
    }
}
if (is_array($imagenesSolicitadas)) {
    foreach ($imagenesSolicitadas as $item) {
        if (!is_array($item)) {
            continue;
        }
        $nombre = trim((string)($item['nombre'] ?? $item['examen'] ?? $item['descripcion'] ?? ''));
        if ($nombre === '') {
            continue;
        }
        $estudiosRaw[] = [
                'nombre' => $nombre,
                'codigo' => trim((string)($item['codigo'] ?? $item['id'] ?? '')),
                'estado' => trim((string)($item['estado'] ?? '')),
        ];
    }
}

$estudios = [];
$seenEstudios = [];
foreach ($estudiosRaw as $estudio) {
    $key = $normalizarTexto(($estudio['codigo'] ?? '') . '|' . ($estudio['nombre'] ?? ''));
    if ($key === '' || isset($seenEstudios[$key])) {
        continue;
    }
    $seenEstudios[$key] = true;
    $estudios[] = $estudio;
}

$estudiosPendientes = [];
$aprobados = ['listo-para-agenda', 'completado', 'atendido'];
foreach ($estudios as $estudio) {
    $estado = trim((string)($estudio['estado'] ?? ''));
    $slug = $normalizarTexto($estado);
    $slug = str_replace(' ', '-', $slug);
    $isApproved = in_array($slug, $aprobados, true) || str_contains($slug, 'aprob');
    if (!$isApproved) {
        $estudiosPendientes[] = $estudio;
    }
}
if ($estudiosPendientes === []) {
    $estudiosPendientes = $estudios;
}

$estudiosLineas = [];
foreach ($estudiosPendientes as $estudio) {
    $nombre = trim((string)($estudio['nombre'] ?? ''));
    if ($nombre === '') {
        continue;
    }
    $codigo = trim((string)($estudio['codigo'] ?? ''));
    $linea = $codigo !== '' ? ($codigo . ' - ' . $nombre) : $nombre;
    $estudiosLineas[] = $linea;
}
$estudiosTexto = $estudiosLineas !== [] ? implode(PHP_EOL, $estudiosLineas) : 'Sin estudios pendientes registrados.';
if ($estudiosPrecomputados012A !== []) {
    $lineasPre = [];
    foreach ($estudiosPrecomputados012A as $estudioPre) {
        if (!is_array($estudioPre)) {
            continue;
        }
        $linea = trim((string)($estudioPre['linea'] ?? $estudioPre['nombre'] ?? ''));
        if ($linea === '') {
            continue;
        }
        $lineasPre[] = $linea;
    }
    if ($lineasPre !== []) {
        $estudiosTexto = implode(PHP_EOL, $lineasPre);
    }
}

$motivoSolicitud = trim((string)($consulta['motivo_solicitud'] ?? ''));
if ($motivoSolicitud === '') {
    $motivoSolicitud = $planTratamiento !== '' ? $planTratamiento : ($reason !== '' ? $reason : 'SE SOLICITA EXÁMENES PARA CONTINUAR TRATAMIENTO');
}

$resumenLineas = [];
if ($motivoConsulta !== '') {
    $resumenLineas[] = 'Motivo: ' . $motivoConsulta;
}
if ($enfermedadActual !== '') {
    $resumenLineas[] = 'Enfermedad actual: ' . $enfermedadActual;
}
$examenFisico = trim((string)($consulta['examen_fisico'] ?? $consulta['examenFisico'] ?? ''));
if ($examenFisico !== '') {
    $resumenLineas[] = 'Examen físico: ' . $examenFisico;
}
if ($consultaPlan !== '') {
    $resumenLineas[] = 'Plan: ' . $consultaPlan;
}
$resumenClinico = $resumenLineas !== [] ? implode(PHP_EOL, $resumenLineas) : ($planTratamiento !== '' ? $planTratamiento : 'Sin resumen clínico registrado.');

$doctorDocumento = trim((string)($consulta['doctor_documento'] ?? $consulta['doctor_cedula'] ?? $consulta['doctor_ci'] ?? $consulta['doctor_identificacion'] ?? ''));

$fechaProfesional = $fechaConsulta !== '' ? $fechaConsulta : date('Y-m-d');
$horaProfesional = $horaConsulta !== '' ? $horaConsulta : date('H:i');

$dxSlots = array_pad($diagnosticoItems, 6, ['dx_code' => '', 'descripcion' => '']);
ob_start();
//echo '<pre>';
//var_dump($data);
//echo '</pre>';
?>
    <table>
        <colgroup>
            <col class="xl76" span="71">
        </colgroup>
        <tr>
            <td colspan="71" class="morado">B. SERVICIO Y PRIORIDAD DE ATENCIÓN</td>
        </tr>
        <tr>
            <td colspan="25" class="verde" style="width: 30%">SERVICIO</td>
            <td colspan="17" class="verde" style="width: 25%">ESPECIALIDAD</td>
            <td colspan="6" class="verde" style="width: 10%">CAMA</td>
            <td colspan="6" class="verde" style="width: 10%">SALA</td>
            <td colspan="17" class="verde" style="border-right: none">PRIORIDAD</td>
        </tr>
        <tr>
            <td colspan="5" class="verde">EMERGENCIA</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="7" class="verde">CONSULTA EXTERNA</td>
            <td colspan="2" class="blanco"> X</td>
            <td colspan="7" class="verde">HOSPITALIZACIÓN</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="17" class="blanco">OFTALMOLOGIA</td>
            <td colspan="6" class="blanco">&nbsp;</td>
            <td colspan="6" class="blanco">&nbsp;</td>
            <td colspan="4" class="verde">URGENTE</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="3" class="verde">RUTINA</td>
            <td colspan="2" class="blanco">X</td>
            <td colspan="4" class="verde">CONTROL</td>
            <td colspan="2" class="blanco" style="border-right: none"></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="71" class="morado">C. ESTUDIO DE IMAGENOLOGÍA SOLICITADO</td>
        </tr>
        <tr>
            <td colspan="6" class="verde">RX<br>CONVENCIONAL</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="4" class="verde">RX<br>PORTÁTIL</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="6" class="verde">TOMOGRAFÍA</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="5" class="verde">RESONANCIA</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="5" class="verde">ECOGRAFÍA</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="6" class="verde">MAMOGRAFÍA</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="7" class="verde">PROCEDIMIENTO</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="3" class="verde">OTRO</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="5" class="verde">SEDACIÓN</td>
            <td colspan="2" class="verde">SI</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="2" class="verde">NO</td>
            <td colspan="2" class="blanco" style="border-right: none">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="8" class="verde">DESCRIPCIÓN</td>
            <td colspan="63" class="blanco" style="border-right: none; text-align: left">
                <?php
                $estudiosSafe = htmlspecialchars($estudiosTexto, ENT_QUOTES, 'UTF-8');
                $estudiosSafe = str_replace(["\r\n", "\n", "\r"], '<br>', $estudiosSafe);
                echo $estudiosSafe;
                ?>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="40" class="morado">D. MOTIVO DE LA SOLICITUD</td>
            <td colspan="31" class="morado" style="font-weight: normal; font-size: 6pt; text-align: right">
                REGISTRAR
                LAS
                RAZONES PARA SOLICITAR
                EL ESTUDIO
            </td>
        </tr>
        <tr>
            <td colspan="10" class="verde"><font class="font6">FUM</font><font
                        class="font5"><br>(aaaa-mm-dd)</font>
            </td>
            <td colspan="12" class="blanco">&nbsp;</td>
            <td colspan="14" class="verde">PACIENTE CONTAMINADO</td>
            <td colspan="2" class="verde">SI</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="2" class="verde">NO</td>
            <td colspan="2" class="blanco">X</td>
            <td colspan="27" class="blanco">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="71" class="blanco" style="text-align: left;">
                Se solicitan estudios de imagenología para continuar con el manejo del paciente.
            </td>
        </tr>
        <tr>
            <td colspan="71" class="blanco">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="71" class="blanco">&nbsp;</td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="morado">E. RESUMEN CLÍNICO ACTUAL
                <span style="font-weight: normal; font-size: 6pt; text-align: right">
                REGISTRAR DE MANERA OBLIGATORIA EL CUADRO CLÍNICO ACTUAL DEL PACIENTE
            </span>
            </td>
        </tr>
        <tr>
            <td class="blanco_left">
                <?php
                // H. EXAMEN FÍSICO (texto libre + síntesis opcional por IA)
                $examenFisicoTextoRaw = trim((string)($consultaExamenFisico ?? ($consulta['examen_fisico'] ?? '')));
                $examenFisicoTexto = $examenFisicoTextoRaw !== '' ? $examenFisicoTextoRaw : '';

                $examenFisicoAI = '';
                $examenFisicoAI_error = null;

                // Solo invocar IA si hay texto real
                if ($examenFisicoTexto !== '' && isset($ai)) {
                    try {
                        // Nuevo método especializado para examen físico
                        $examenFisicoAI = $ai->generateExamenFisicoOftalmologico($examenFisicoTexto);
                    } catch (\Throwable $e) {
                        $examenFisicoAI_error = $e->getMessage();
                        error_log('OpenAI generateExamenFisicoOftalmologico error: ' . $examenFisicoAI_error);
                    }
                }

                // Preferir salida IA si existe; si no, mostrar el texto original; si no hay nada, mostrar mensaje estándar.
                $examenFisicoSalida = '';
                if (trim($examenFisicoAI) !== '') {
                    $examenFisicoSalida = $examenFisicoAI;
                } elseif ($examenFisicoTexto !== '') {
                    $examenFisicoSalida = $examenFisicoTexto;
                } else {
                    $examenFisicoSalida = 'Sin datos registrados.';
                }

                echo wordwrap($examenFisicoSalida, 160, "</td></tr><tr><td class='blanco_left'>", true);

                if (!empty($AI_DEBUG)) {
                    echo "<div style='border:1px dashed #c00; margin:6px 0; padding:6px; font-size:8pt; color:#900;'>
            <b>AI DEBUG — Examen Físico</b><br>
            <pre style='white-space:pre-wrap;'>" . htmlspecialchars(json_encode([
                                    'has_ai' => isset($ai),
                                    'input_preview' => mb_substr((string)$examenFisicoTexto, 0, 400),
                                    'output_len' => mb_strlen((string)$examenFisicoAI),
                                    'error' => $examenFisicoAI_error
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "</pre>
          </div>";
                }
                ?>
            </td>
        </tr>
    </table>
    <table>
        <TR>
            <TD class="morado" width="2%">F.</TD>
            <TD class="morado" width="17.5%">DIAGN&Oacute;STICOS</TD>
            <TD class="morado" width="17.5%" style="font-weight: normal; font-size: 6pt">PRE= PRESUNTIVO
                DEF=
                DEFINITIVO
            </TD>
            <TD class="morado" width="6%" style="font-size: 6pt; text-align: center">CIE</TD>
            <TD class="morado" width="3.5%" style="font-size: 6pt; text-align: center">PRE</TD>
            <TD class="morado" width="3.5%" style="font-size: 6pt; text-align: center">DEF</TD>
            <TD class="morado" width="2%"><BR></TD>
            <TD class="morado" width="17.5%"><BR></TD>
            <TD class="morado" width="17.5%"><BR></TD>
            <TD class="morado" width="6%" style="font-size: 6pt; text-align: center">CIE</TD>
            <TD class="morado" width="3.5%" style="font-size: 6pt; text-align: center">PRE</TD>
            <TD class="morado" width="3.5%" style="font-size: 6pt; text-align: center">DEF</TD>
        </TR>
        <?php
        $dx0 = $dxSlots[0] ?? [];
        $dx1 = $dxSlots[1] ?? [];
        $dx2 = $dxSlots[2] ?? [];
        $dx3 = $dxSlots[3] ?? [];
        $dx4 = $dxSlots[4] ?? [];
        $dx5 = $dxSlots[5] ?? [];

        $dxDesc0 = trim((string)($dx0['descripcion'] ?? ''));
        $dxDesc1 = trim((string)($dx1['descripcion'] ?? ''));
        $dxDesc2 = trim((string)($dx2['descripcion'] ?? ''));
        $dxDesc3 = trim((string)($dx3['descripcion'] ?? ''));
        $dxDesc4 = trim((string)($dx4['descripcion'] ?? ''));
        $dxDesc5 = trim((string)($dx5['descripcion'] ?? ''));

        $dxCode0 = trim((string)($dx0['dx_code'] ?? ''));
        $dxCode1 = trim((string)($dx1['dx_code'] ?? ''));
        $dxCode2 = trim((string)($dx2['dx_code'] ?? ''));
        $dxCode3 = trim((string)($dx3['dx_code'] ?? ''));
        $dxCode4 = trim((string)($dx4['dx_code'] ?? ''));
        $dxCode5 = trim((string)($dx5['dx_code'] ?? ''));
        ?>
        <TR>
            <td class="verde">1.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc0, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode0, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc0 !== '' ? 'x' : '' ?></td>
            <td class="verde">4.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc3, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode3, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc3 !== '' ? 'x' : '' ?></td>
        </TR>
        <TR>
            <td class="verde">2.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc1, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode1, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc1 !== '' ? 'x' : '' ?></td>
            <td class="verde">5.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc4, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode4, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc4 !== '' ? 'x' : '' ?></td>
        </TR>
        <TR>
            <td class="verde">3.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc2, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode2, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc2 !== '' ? 'x' : '' ?></td>
            <td class="verde">6.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?= htmlspecialchars($dxDesc5, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="blanco"><?= htmlspecialchars($dxCode5, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?= $dxDesc5 !== '' ? 'x' : '' ?></td>
        </TR>
    </table>
    <table>
        <tr>
            <td colspan="71" class="morado">G. DATOS DEL PROFESIONAL RESPONSABLE</td>
        </tr>
        <tr class="xl78">
            <td colspan="8" class="verde">FECHA<br>
                <font class="font5">(aaaa-mm-dd)</font>
            </td>
            <td colspan="7" class="verde">HORA<br>
                <font class="font5">(hh:mm)</font>
            </td>
            <td colspan="21" class="verde">NOMBRES</td>
            <td colspan="19" class="verde">PRIMER APELLIDO</td>
            <td colspan="16" class="verde">SEGUNDO APELLIDO</td>
        </tr>
        <tr>
            <td colspan="8"
                class="blanco"><?= htmlspecialchars($fechaProfesional, ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="7" class="blanco"><?= htmlspecialchars($horaProfesional, ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="21"
                class="blanco"><?php echo htmlspecialchars($doctorFirstName) . ' ' . htmlspecialchars($doctorMiddleName); ?></td>
            <td colspan="19" class="blanco"><?php echo htmlspecialchars($doctorLastName); ?></td>
            <td colspan="16" class="blanco"><?php echo htmlspecialchars($doctorSecondLastName); ?></td>
        </tr>
        <tr>
            <td colspan="15" class="verde">NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
            <td colspan="26" class="verde">FIRMA</td>
            <td colspan="30" class="verde">SELLO</td>
        </tr>
        <tr>
            <td colspan="15" class="blanco"
                style="height: 40px"><?php echo htmlspecialchars((string)($consulta['doctor_cedula'] ?? '')); ?></td>
            <td colspan="26"
                class="blanco"><?php echo "<img src='" . htmlspecialchars((string)($consulta['doctor_signature_path'] ?? '')) . "' alt='Imagen de la firma' style='max-height: 70px;'>"; ?></td>
            <td colspan="30"
                class="blanco"><?php echo "<img src='" . htmlspecialchars((string)($consulta['doctor_firma'] ?? '')) . "' alt='Imagen de la firma' style='max-height: 70px;'>"; ?></td>
        </tr>
    </table>
    <table style="border: none">
        <TR>
            <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=TOP><B><FONT SIZE=1 COLOR="#000000">SNS-MSP /
                        HCU-form.012A
                        /
                        2008</FONT></B>
            </TD>
            <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">IMAGENOLOGIA
                        SOLICITUD</FONT></B>
            </TD>
        </TR>
    </TABLE>
<?php
$content = ob_get_clean();
$title = 'Formulario 012A - Imagenes';

include $layout;
