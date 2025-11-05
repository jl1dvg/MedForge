<?php
$layout = __DIR__ . '/../layouts/base.php';
$patient = [
    'afiliacion' => $afiliacion ?? '',
    'hc_number' => $hc_number ?? '',
    'archive_number' => $hc_number ?? '',
    'lname' => $lname ?? '',
    'lname2' => $lname2 ?? '',
    'fname' => $fname ?? '',
    'mname' => $mname ?? '',
    'sexo' => $sexo ?? '',
    'fecha_nacimiento' => $fecha_nacimiento ?? '',
    'edad' => $edadPaciente ?? '',
];

ob_start();
include __DIR__ . '/../partials/patient_header.php';
$header = ob_get_clean();

ob_start();
?>
    <table>
        <tr>
            <td class='morado' colspan='26' style='border-bottom: 1px solid #808080;'>B. EVOLUCIÓN Y
                PRESCRIPCIONES
            </td>
            <td class='morado' colspan='20'
                style='font-size: 4pt; font-weight: lighter; border-bottom: 1px solid #808080;'>
                FIRMAR AL PIE DE CADA EVOLUCIÓN Y PRESCRIPCIÓN
            </td>
            <td class='morado' colspan='21'
                style='font-size: 4pt; font-weight: lighter; text-align: right; border-bottom: 1px solid #808080;'>
                REGISTRAR CON ROJO LA ADMINISTRACIÓN DE FÁRMACOS Y COLOCACIÓN DE DISPOSITIVOS MÉDICOS
            </td>
        </tr>
        <tr>
            <td class='morado' colspan='38' style='text-align: center'>1. EVOLUCIÓN</td>
            <td class='blanco_break'></td>
            <td class='morado' colspan='28' style='text-align: center'>2. PRESCRIPCIONES</td>
        </tr>
        <tr>
            <td class='verde' colspan='6' width="8%">FECHA<br><span
                        style='font-size:6pt;font-family:Arial;font-weight:normal;'>(aaaa-mm-dd)</span>
            </td>
            <td class='verde' colspan='3'>HORA<br><span
                        style='font-size:6pt;font-family:Arial;font-weight:normal;'>(hh:mm)</span></td>
            <td class='verde' colspan='29' width="40%">NOTAS DE EVOLUCIÓN</td>
            <td class='blanco_break'></td>
            <td class='verde' colspan='23' width="35%">FARMACOTERAPIA E INDICACIONES<span
                        style='font-size:6pt;font-family:Arial;font-weight:normal;'><br>(Para enfermería y otro profesional de salud)</span>
            </td>
            <td class='verde' colspan='5' width="8%"><span
                        style='font-size:6pt;font-family:Arial;font-weight:normal;'>ADMINISTR. <br>FÁRMACOS<br>DISPOSITIVO</span>
            </td>
        </tr>
        <?php
        $maxLines = max(
            count($preEvolucion ?? []),
            count($preIndicacion ?? []),
            count($postEvolucion ?? []),
            count($postIndicacion ?? []),
            7
        );
        ?>
        <tr>
            <td colspan="6" class="blanco_left"><?= $fechaDia . '/' . $fechaMes . '/' . $fechaAno ?></td>
            <td colspan="3" class="blanco_left"><?= $horaInicioModificada ?></td>
            <td colspan="29" class="blanco_left" style="text-align: center;"><b>PRE-OPERATORIO</b></td>
            <td class="blanco_break"></td>
            <td colspan="23" class="blanco_left" style="text-align: center;"><b>PRE-OPERATORIO</b></td>
            <td colspan="5" class="blanco_left"></td>
        </tr>

        <?php for ($i = 0; $i < $maxLines; $i++): ?>

            <tr>
                <td colspan="6" class="blanco_left"></td>
                <td colspan="3" class="blanco_left"></td>
                <td colspan="29" class="blanco_left"><?= $preEvolucion[$i] ?? '' ?></td>
                <td class="blanco_break"></td>
                <td colspan="23" class="blanco_left"><?= $preIndicacion[$i] ?? '' ?></td>
                <td colspan="5" class="blanco_left"></td>
            </tr>
        <?php endfor; ?>

        <tr>
            <td colspan="6" class="blanco_left"></td>
            <td colspan="3" class="blanco_left"><?= $hora_fin ?></td>
            <td colspan="29" class="blanco_left" style="text-align: center;"><b>POST-OPERATORIO</b></td>
            <td class="blanco_break"></td>
            <td colspan="23" class="blanco_left" style="text-align: center;"><b>POST-OPERATORIO</b></td>
            <td colspan="5" class="blanco_left"></td>
        </tr>

        <?php
        $maxLinesPost = max(
            count($postEvolucion ?? []),
            count($postIndicacion ?? []),
            6
        );
        for ($i = 0; $i < $maxLinesPost; $i++): ?>
            <tr>
                <td colspan="6" class="blanco_left"></td>
                <td colspan="3" class="blanco_left"></td>
                <td colspan="29" class="blanco_left"><?= $postEvolucion[$i] ?? '' ?></td>
                <td class="blanco_break"></td>
                <td colspan="23" class="blanco_left"><?= $postIndicacion[$i] ?? '' ?></td>
                <td colspan="5" class="blanco_left"></td>
            </tr>
        <?php endfor; ?>
        <tr>
            <td colspan="6" class="blanco_left"></td>
            <td colspan="3" class="blanco_left"></td>
            <td colspan="29" class="blanco_left" style="text-align: left;">
                <?php if (!empty($anestesiologo_data['firma'])): ?>
                    <div style="margin-bottom: -25px;">
                        <img src="<?= htmlspecialchars($anestesiologo_data['firma']) ?>" alt="Firma del cirujano"
                             style="max-height: 60px;">
                    </div>
                <?php endif; ?>
                <?= strtoupper($anestesiologo_data['nombre']) ?>
            <td class="blanco_break"></td>
            <td colspan="23" class="blanco_left"><?= $postIndicacion[$i] ?? '' ?></td>
            <td colspan="5" class="blanco_left"></td>
        </tr>

        <tr>
            <td colspan="6" class="blanco_left"></td>
            <td colspan="3" class="blanco_left"><?= $horaFinModificada ?></td>
            <td colspan="29" class="blanco_left" style="text-align: center;"><b>ALTA MÉDICA</b></td>
            <td class="blanco_break"></td>
            <td colspan="23" class="blanco_left" style="text-align: center;"><b>ALTA MÉDICA</b></td>
            <td colspan="5" class="blanco_left"></td>
        </tr>

        <?php
        // Calcular el número máximo de líneas para la sección de alta
        $maxLinesAlta = max(count($altaEvolucion), count($altaIndicacion), 6);

        for ($i = 0; $i < $maxLinesAlta; $i++): ?>
            <tr>
                <td colspan="6" class="blanco_left"></td>
                <td colspan="3" class="blanco_left"></td>
                <td colspan="29" class="blanco_left"><?= $altaEvolucion[$i] ?? '' ?></td>
                <td class="blanco_break"></td>
                <td colspan="23" class="blanco_left"><?= $altaIndicacion[$i] ?? '' ?></td>
                <td colspan="5" class="blanco_left"></td>
            </tr>
        <?php endfor; ?>
        <tr>
            <td colspan="6" class="blanco_left"></td>
            <td colspan="3" class="blanco_left"></td>
            <td colspan="29" class="blanco_left" style="text-align: left;">
                <?php if (!empty($cirujano_data['firma'])): ?>
                    <div style="margin-bottom: -25px;">
                        <img src="<?= htmlspecialchars($cirujano_data['firma']) ?>" alt="Firma del cirujano"
                             style="max-height: 60px;">
                    </div>
                <?php endif; ?>
                <?= strtoupper($cirujano_data['nombre']) ?>
            </td>
            <td class="blanco_break"></td>
            <td colspan="23" class="blanco_left"></td>
            <td colspan="5" class="blanco_left"></td>
        </tr>
    </table>


    <table style="border: none">
        <TR>
            <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                                   COLOR="#000000">SNS-MSP/HCU-form.005/2021</FONT></B>
            </TD>
            <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">EVOLUCIÓN Y PRESCRIPCIONES
                        (1)</FONT></B>
            </TD>
        </TR>
    </table>
<?php
$content = ob_get_clean();
$title = 'Formulario 005 - Evolución y Prescripciones';

include $layout;
