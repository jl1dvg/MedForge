<?php

$layout = __DIR__ . '/../layouts/base.php';
$data = is_array($data ?? null) ? $data : [];
$patientData = $data['patient'] ?? [];
$examen = $data['examen'] ?? [];
$informe = $data['informe'] ?? [];
$firmante = $data['firmante'] ?? [];

$patient = [
        'afiliacion' => $patientData['afiliacion'] ?? '',
        'hc_number' => $patientData['hc_number'] ?? '',
        'archive_number' => $patientData['archive_number'] ?? ($patientData['hc_number'] ?? ''),
        'lname' => $patientData['lname'] ?? '',
        'lname2' => $patientData['lname2'] ?? '',
        'fname' => $patientData['fname'] ?? '',
        'mname' => $patientData['mname'] ?? '',
        'sexo' => $patientData['sexo'] ?? '',
        'fecha_nacimiento' => $patientData['fecha_nacimiento'] ?? '',
        'edad' => $patientData['edad'] ?? '',
];

$descripcionExamen = trim((string)($examen['descripcion'] ?? ''));
$hallazgos = trim((string)($informe['hallazgos'] ?? ''));
$conclusiones = trim((string)($informe['conclusiones'] ?? ''));
$fechaInforme = trim((string)($informe['fecha'] ?? ''));
$horaInforme = trim((string)($informe['hora'] ?? ''));

$resolveAsset = static function (?string $path): string {
    $path = trim((string)($path ?? ''));
    if ($path === '') {
        return '';
    }
    if (function_exists('asset')) {
        return (string)asset($path);
    }
    return $path;
};

$firmaSrc = $resolveAsset($firmante['signature_path'] ?? '');
$selloSrc = $resolveAsset($firmante['firma'] ?? '');

if ($hallazgos === '') {
    $hallazgos = 'Sin hallazgos registrados.';
}

if ($conclusiones === '') {
    $conclusiones = "En resumen, es fundamental seguir las indicaciones del médico especialista. Si surgieran dudas o
preguntas, siempre debemos comunicarnos con el médico para obtener
claridad. Trabajar en colaboración con el médico nos permitirá obtener los mejores resultados y mantener
una buena salud a largo plazo.";
}

ob_start();
include __DIR__ . '/../partials/patient_header.php';
$header = ob_get_clean();

ob_start();
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
            <td colspan="2" class="blanco"> X&nbsp;</td>
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
            <td colspan="2" class="blanco">&nbsp;X</td>
            <td colspan="5" class="verde">SEDACIÓN</td>
            <td colspan="2" class="verde">SI</td>
            <td colspan="2" class="blanco">&nbsp;</td>
            <td colspan="2" class="verde">NO</td>
            <td colspan="2" class="blanco" style="border-right: none"> X </td>
        </tr>
        <tr>
            <td colspan="8" class="verde">DESCRIPCIÓN</td>
            <td colspan="63" class="blanco" style="border-right: none; text-align: left">
                Informe
                de <?= htmlspecialchars($descripcionExamen !== '' ? $descripcionExamen : 'Imágenes', ENT_QUOTES, 'UTF-8') ?>
        </tr>
    </table>
    <table>
        <tr>
            <td class="morado">D. HALLAZGOS POR IMAGENOLOGÍA</td>
        </tr>
        <tr>
            <td class="blanco" style="border-right: none; text-align: left">
                <?php
                $hallazgosSafe = htmlspecialchars($hallazgos, ENT_QUOTES, 'UTF-8');
                $hallazgosSafe = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $hallazgosSafe);
                $hallazgosSafe = str_replace(["\r\n", "\n", "\r"], "</td></tr><tr><td class='blanco_left'>", $hallazgosSafe);
                echo wordwrap($hallazgosSafe, 155, "</td></tr><tr><td class='blanco_left'>");
                ?>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="morado">E. CONCLUSIONES Y SUGERENCIAS</td>
        </tr>
        <tr>
            <td class="blanco" style="border-right: none; text-align: left">
                <?php echo wordwrap(htmlspecialchars($conclusiones, ENT_QUOTES, 'UTF-8'), 155, "</TD></TR><TR><td colspan=\"71\" class=\"blanco\" style=\"border-right: none; text-align: left\">"); ?>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="71" class="morado">F. DATOS DEL PROFESIONAL RESPONSABLE</td>
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
                class="blanco"><?= htmlspecialchars($fechaInforme !== '' ? $fechaInforme : date('Y/m/d'), ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="7" class="blanco"><?= htmlspecialchars($horaInforme, ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="21"
                class="blanco"><?= htmlspecialchars((string)($firmante['nombres'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="19"
                class="blanco"><?= htmlspecialchars((string)($firmante['apellido1'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="16"
                class="blanco"><?= htmlspecialchars((string)($firmante['apellido2'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td colspan="15" class="verde">NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
            <td colspan="26" class="verde">FIRMA</td>
            <td colspan="30" class="verde">SELLO</td>
        </tr>
        <tr>
            <td colspan="15" class="blanco"
                style="height: 40px"><?= htmlspecialchars((string)($firmante['documento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td colspan="26" class="blanco">
                <?php if ($firmaSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($firmaSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Firma"
                         style="max-height: 35px;">
                <?php endif; ?>
            </td>
            <td colspan="30" class="blanco">
                <?php if ($selloSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($selloSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Sello"
                         style="max-height: 35px;">
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <table style="border: none">
        <tr>
            <td colspan="9" style="text-align: justify; font-size: 6pt">
                La aproximación diagnóstica emitida en el presente informe, constituye tan solo una prueba
                complementaria al diagnóstico clínico definitivo, motivo por el cual se recomienda correlacionar con
                antecedentes clínicos/quirúrgicos, datos clínicos, exámenes de laboratorio complementarios, así como
                seguimiento imagenológico del paciente.
            </td>
        </tr>
        <TR>
            <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                                   COLOR="#000000">SNS-MSP/HCU-form.012B/2021</FONT></B>
            </TD>
            <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">IMAGENOLOGÍA - INFORME</FONT></B>
            </TD>
        </TR>
    </TABLE>
<?php
$content = ob_get_clean();
$title = 'Formulario 012B - Imagenes';

include $layout;
