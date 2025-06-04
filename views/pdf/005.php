<body>
<TABLE>
    <tr>
        <td colspan='71' class='morado'>A. DATOS DEL ESTABLECIMIENTO
            Y USUARIO / PACIENTE
        </td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='verde'>INSTITUCIÓN DEL SISTEMA</td>
        <td colspan='6' class='verde'>UNICÓDIGO</td>
        <td colspan='18' class='verde'>ESTABLECIMIENTO DE SALUD</td>
        <td colspan='18' class='verde'>NÚMERO DE HISTORIA CLÍNICA ÚNICA</td>
        <td colspan='14' class='verde' style='border-right: none'>NÚMERO DE ARCHIVO</td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='blanco'><?php echo $afiliacion; ?></td>
        <td colspan='6' class='blanco'>&nbsp;</td>
        <td colspan='18' class='blanco'>CIVE</td>
        <td colspan='18' class='blanco'><?php echo $hc_number; ?></td>
        <td colspan='14' class='blanco' style='border-right: none'><?php echo $hc_number; ?></td>
    </tr>
    <tr>
        <td colspan='15' rowspan='2' height='41' class='verde' style='height:31.0pt;'>PRIMER APELLIDO</td>
        <td colspan='13' rowspan='2' class='verde'>SEGUNDO APELLIDO</td>
        <td colspan='13' rowspan='2' class='verde'>PRIMER NOMBRE</td>
        <td colspan='10' rowspan='2' class='verde'>SEGUNDO NOMBRE</td>
        <td colspan='3' rowspan='2' class='verde'>SEXO</td>
        <td colspan='6' rowspan='2' class='verde'>FECHA NACIMIENTO</td>
        <td colspan='3' rowspan='2' class='verde'>EDAD</td>
        <td colspan='8' class='verde' style='border-right: none; border-bottom: none'>CONDICIÓN EDAD <font
                    class='font7'>(MARCAR)</font></td>
    </tr>
    <tr>
        <td colspan='2' height='17' class='verde'>H</td>
        <td colspan='2' class='verde'>D</td>
        <td colspan='2' class='verde'>M</td>
        <td colspan='2' class='verde' style='border-right: none'>A</td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='blanco'><?php echo $lname; ?></td>
        <td colspan='13' class='blanco'><?php echo $lname2; ?></td>
        <td colspan='13' class='blanco'><?php echo $fname; ?></td>
        <td colspan='10' class='blanco'><?php echo $mname; ?></td>
        <td colspan='3' class='blanco'><?php echo $sexo; ?></td>
        <td colspan='6' class='blanco'><?php echo $fecha_nacimiento; ?></td>
        <td colspan='3' class='blanco'><?php echo $edadPaciente; ?></td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco' style='border-right: none'>&nbsp;</td>
    </tr>
</TABLE>
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

    use Helpers\ProtocoloHelper;

    // ✅ Primero obtenemos signos vitales una sola vez
    $signos = ProtocoloHelper::obtenerSignosVitalesYEdad($edadPaciente);

    $preEvolucion = [];
    $preIndicacion = [];
    $postEvolucion = [];
    $postIndicacion = [];
    $altaEvolucion = [];
    $altaIndicacion = [];

    if (!empty($datos['evolucion005'])) {
        $preEvolucion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['pre_evolucion'], 70, $signos);
        $preIndicacion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['pre_indicacion'], 80, $signos);
        $postEvolucion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['post_evolucion'], 70, $signos);
        $postIndicacion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['post_indicacion'], 80, $signos);
        $altaEvolucion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['alta_evolucion'], 70, $signos);
        $altaIndicacion = ProtocoloHelper::procesarEvolucionConVariables($datos['evolucion005']['alta_indicacion'], 80, $signos);
    }

    $maxLines = max(count($preEvolucion), count($preIndicacion), count($postEvolucion), count($postIndicacion), 7); // mínimo 10 líneas

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

    <?php for ($i = 0; $i < $maxLines; $i++): ?>
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
    $maxLinesAlta = max(count($altaEvolucion), count($altaIndicacion), 7);

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
</body>
</file>
