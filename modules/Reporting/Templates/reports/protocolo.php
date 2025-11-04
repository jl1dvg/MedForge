<?php
// Este archivo espera que todas las variables estén disponibles desde extract($datos)
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
        <td colspan='10' class='morado'>B. DIAGNÓSTICOS</td>
        <td colspan='2' class='morado' style='text-align: center'>CIE</td>
    </tr>
    <tr>
        <td colspan='2' width='18%' rowspan='3' class='verde_left'>Pre Operatorio:</td>
        <td class='verde_left' width='2%'>1.</td>
        <td class='blanco_left' colspan='7'><?php echo strtoupper(substr($diagnosticos_previos[0], 6)); ?></td>
        <td class='blanco' width='20%' colspan='2'><?php echo substr($diagnosticos_previos[0], 0, 4); ?></td>
    </tr>
    <tr>
        <td class='verde_left' width='2%'>2.</td>
        <td class='blanco_left' colspan='7'><?php echo strtoupper(substr($diagnosticos_previos[1], 6)); ?></td>
        <td class='blanco' width='20%' colspan='2'><?php echo substr($diagnosticos_previos[1], 0, 4); ?></td>
    </tr>
    <tr>
        <td class='verde_left' width='2%'>3.</td>
        <td class='blanco_left' colspan='7'><?php echo strtoupper(substr($diagnosticos_previos[2], 6)); ?></td>
        <td class='blanco' width='20%' colspan='2'><?php echo substr($diagnosticos_previos[2], 0, 4); ?></td>
    </tr>
    <tr>
        <td colspan='2' rowspan='3' class='verde_left'>Post Operatorio:</td>
        <td class='verde_left'>1.</td>
        <td class='blanco_left' colspan='7'><?php echo substr($diagnostic1, 6); ?></td>
        <td class='blanco' colspan='2'><?php echo substr($diagnostic1, 0, 4); ?></td>
    </tr>
    <tr>
        <td class='verde_left'>2.</td>
        <td class='blanco_left' colspan='7'><?php echo substr($diagnostic2, 6); ?></td>
        <td class='blanco' colspan='2'><?php echo substr($diagnostic2, 0, 4); ?></td>
    </tr>
    <tr>
        <td class='verde_left'>3.</td>
        <td class='blanco_left' colspan='7'><?php echo substr($diagnostic3, 6); ?></td>
        <td class='blanco' colspan='2'><?php echo substr($diagnostic3, 0, 4); ?></td>
    </tr>
</table>
<table>
    <tr>
        <td colspan='11' class='morado'>C. PROCEDIMIENTO</td>
        <td colspan='2' class='verde_left' style='text-align: center'>Electiva</td>
        <td colspan='1' class='blanco' style='text-align: center'>X</td>
        <td colspan='2' class='verde_left' style='text-align: center'>Emergencia</td>
        <td colspan='1' class='blanco' style='text-align: center'></td>
        <td colspan='2' class='verde_left' style='text-align: center'>Urgencia</td>
        <td colspan='1' class='blanco' style='text-align: center'></td>
    </tr>
    <tr>
        <td colspan='2' class='verde_left'>Proyectado:</td>
        <td class='blanco_left' colspan='18'>
            <?php
            echo strtoupper(
                $nombre_procedimiento_proyectado
                    ? $nombre_procedimiento_proyectado . ' ' . $lateralidad
                    : $realizedProcedure
            );
            ?>
        </td>
    </tr>
    <tr>
        <td colspan='2' class='verde_left'>Realizado:</td>
        <td class='blanco_left'
            colspan='18'><?php echo strtoupper($realizedProcedure) . ' ' . $codes_concatenados; ?></td>
    </tr>
</table>
<table>
    <tr>
        <td class='morado' colspan='20'>D. INTEGRANTES DEL EQUIPO QUIRÚRGICO</td>
    </tr>
    <tr>
        <td class='verde_left' colspan='3'>Cirujano 1:</td>
        <td class='blanco' colspan='7'><?php echo $mainSurgeon; ?></td>
        <td class='verde_left' colspan='3'>Instrumentista:</td>
        <td class='blanco' colspan='7'><?php echo $instrumentista; ?></td>
    </tr>
    <tr>
        <td class='verde_left' colspan='3'>Cirujano 2:</td>
        <td class='blanco' colspan='7'><?php echo $assistantSurgeon1; ?></td>
        <td class='verde_left' colspan='3'>Circulante:</td>
        <td class='blanco' colspan='7'><?php echo $circulante; ?></td>
    </tr>
    <tr>
        <td class='verde_left' colspan='3'>Primer Ayudante:</td>
        <td class='blanco' colspan='7'><?php echo $ayudante; ?></td>
        <td class='verde_left' colspan='3'>Anestesiologo/a:</td>
        <td class='blanco' colspan='7'><?php echo $anestesiologo; ?></td>
    </tr>
    <tr>
        <td class='verde_left' colspan='3'>Segundo Ayudante:</td>
        <td class='blanco' colspan='7'></td>
        <td class='verde_left' colspan='3'>Ayudante Anestesia:</td>
        <td class='blanco' colspan='7'><?php echo $ayudante_anestesia; ?></td>
    </tr>
    <tr>
        <td class='verde_left' colspan='3'>Tercer Ayudante:</td>
        <td class='blanco' colspan='7'></td>
        <td class='verde_left' colspan='3'>Otros:</td>
        <td class='blanco' colspan='7'></td>
    </tr>
</table>
<table>
    <tr>
        <td colspan='20' class='morado'>E. TIPO ANESTESIA</td>
    </tr>
    <?php
    // Tipo de anestesia
    echo "<tr>";
    echo "<td class='verde_left' colspan='3'>General:</td>";
    echo "<td class='blanco' colspan='1'>" . ($tipo_anestesia == 'GENERAL' ? 'x' : '') . "</td>";
    echo "<td class='verde_left' colspan='3'>Local:</td>";
    echo "<td class='blanco' colspan='1'>" . ($tipo_anestesia == 'LOCAL' ? 'x' : '') . "</td>";
    echo "<td class='verde_left' colspan='3'>Otros:</td>";
    echo "<td class='blanco' colspan='1'>" . ($tipo_anestesia == 'OTROS' ? 'TOPICA' : '') . "</td>";
    echo "<td class='verde_left' colspan='3'>Regional:</td>";
    echo "<td class='blanco' colspan='1'>" . ($tipo_anestesia == 'REGIONAL' ? 'x' : '') . "</td>";
    echo "<td class='verde_left' colspan='3'>Sedación:</td>";
    echo "<td class='blanco' colspan='1'>" . ($tipo_anestesia == 'SEDACION' ? 'x' : '') . "</td>";
    echo "</tr>";
    ?>
</table>
<table>
    <tr>
        <td colspan='70' class='morado'>F. TIEMPOS QUIRÚRGICOS</td>
    </tr>
    <tr>
        <td colspan='19' rowspan='2' class='verde'>FECHA DE OPERACIÓN</td>
        <td colspan='5' class='verde'>DIA</td>
        <td colspan='5' class='verde'>MES</td>
        <td colspan='5' class='verde'>AÑO</td>
        <td colspan='18' class='verde'>HORA DE INICIO</td>
        <td colspan='18' class='verde'>HORA DE TERMINACIÓN</td>
    </tr>
    <tr>
        <td colspan='5' class='blanco'><?php echo $fechaDia; ?></td>
        <td colspan='5' class='blanco'><?php echo $fechaMes; ?></td>
        <td colspan='5' class='blanco'><?php echo $fechaAno; ?></td>
        <td colspan='18' class='blanco'><?php echo $hora_inicio; ?></td>
        <td colspan='18' class='blanco'><?php echo $hora_fin; ?></td>
    </tr>
    <tr>
        <td colspan='15' class='verde_left'>Dieresis:</td>
        <td colspan='55' class='blanco_left'><?php echo $dieresis; ?></td>
    </tr>
    <tr>
        <td colspan='15' class='verde_left'>Exposición y Exploración:</td>
        <td colspan='55' class='blanco_left'><?php echo $exposicion; ?></td>
    </tr>
    <tr>
        <td colspan='15' class='verde_left'>Hallazgos Quirúrgicos:</td>
        <td colspan='55' class='blanco_left'><?php echo $hallazgo; ?></td>
    </tr>
    <tr>
        <td colspan='15' class='verde_left'>Procedimiento Quirúrgicos:</td>
        <td colspan='55' class='blanco_left'><?php echo nl2br($operatorio); ?></td>
    </tr>
</table>
<table style='border: none'>
    <TR>
        <TD colspan='6 ' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                                COLOR='#000000 '>SNS-MSP/HCU-form. 017/2021</FONT></B>
        </TD>
        <TD colspan='3 ' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000 '>PROTOCOLO QUIRÚRGICO (1)</FONT></B>
        </TD>
    </TR>
</TABLE>
<pagebreak>
    <table>
        <tr>
            <td colspan='15' class='verde_left'>Procedimiento Quirúrgicos:</td>
            <td colspan='55' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan='70' class='morado'>G. COMPLICACIONES DEL PROCEDIMIENTO QUIRÚRGICO</td>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco_left'></td>
        </tr>
        </tr>
        <tr>
            <td colspan='10' class='verde'>Pérdida Sanguínea total:</td>
            <td colspan='10' class='blanco'></td>
            <td colspan='5' class='blanco'>ml</td>
            <td colspan='10' class='verde'>Sangrado aproximado:</td>
            <td colspan='10' class='blanco'></td>
            <td colspan='5' class='blanco'>ml</td>
            <td colspan='10' class='verde'>Uso de Material Protésico:</td>
            <td colspan='3' class='blanco'>SI</td>
            <td colspan='2' class='blanco'></td>
            <td colspan='3' class='blanco'>NO</td>
            <td colspan='2' class='blanco'></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan='70' class='morado'>H. EXÁMENES HISTOPATOLÓGICOS</td>
        </tr>
        <tr>
            <td colspan='10' class='verde'>Transquirúrgico:</td>
            <td colspan='60' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='10' class='verde'>Biopsia por congelación:</td>
            <td colspan='3' class='blanco'>SI</td>
            <td colspan='2' class='blanco'></td>
            <td colspan='3' class='blanco'>NO</td>
            <td colspan='2' class='blanco'>X</td>
            <td colspan='10' class='verde'>Resultado:</td>
            <td colspan='40' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='13' class='blanco_left'></td>
            <td colspan='57' class='blanco_left'>Patólogo que reporta:</td>
        </tr>
        <tr>
            <td colspan='10' class='verde'>Histopatológico:</td>
            <td colspan='3' class='blanco'>SI</td>
            <td colspan='2' class='blanco'></td>
            <td colspan='3' class='blanco'>NO</td>
            <td colspan='2' class='blanco'>X</td>
            <td colspan='10' class='verde'>Muestra:</td>
            <td colspan='40' class='blanco_left'></td>
        </tr>
        <tr>
            <td colspan='70' class='blanco'></td>
        </tr>
    </table>
    <table>
        <tr>
            <td class='morado'>I. DIAGRAMA DEL PROCEDIMIENTO</td>
        </tr>
        <tr>
            <td class='blanco' height='100px'>
                <?php
                echo "<img src='" . htmlspecialchars($imagen_link) . "' alt='Imagen del Procedimiento' style='max-height: 140px;'>";
                ?>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td class='morado' colspan='20'>J. DATOS DEL PROFESIONAL RESPONSABLE</td>
        </tr>
        <tr>
            <td class='verde' style='width: 100' colspan='5'>NOMBRE Y APELLIDOS</td>
            <td class='verde' style='width: 100' colspan='5'>ESPECIALIDAD</td>
            <td class='verde' style='width: 100' colspan='5'>FIRMA</td>
            <td class='verde' style='width: 100' colspan='5'>SELLO Y NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
        </tr>
        <tr>
            <td class='blanco' style='height: 75' colspan='5'><?php echo strtoupper($cirujano_data['nombre']); ?></td>
            <td class='blanco' colspan='5'><?php echo strtoupper($cirujano_data['especialidad']); ?></td>
            <td class='blanco' colspan='5'><?php echo $cirujano_data['cedula']; ?></td>
            <td class='blanco'
                colspan='5'><?php echo "<img src='" . htmlspecialchars($cirujano_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
                ?></td>
        </tr>
        <tr>
            <td class='blanco' style='height: 75' colspan='5'><?php
                if (!empty($cirujano2_data) && !empty($cirujano2_data['nombre'])) {
                    echo strtoupper($cirujano2_data['nombre']);
                } elseif (!empty($ayudante_data['nombre'])) {
                    echo strtoupper($ayudante_data['nombre']);
                } else {
                    echo '';
                }
                ?></td>
            <td class='blanco' colspan='5'><?php
                if (!empty($cirujano2_data) && !empty($cirujano2_data['especialidad'])) {
                    echo strtoupper($cirujano2_data['especialidad']);
                } elseif (!empty($ayudante_data['especialidad'])) {
                    echo strtoupper($ayudante_data['especialidad']);
                } else {
                    echo '';
                }
                ?></td>
            <td class='blanco' colspan='5'><?php
                if (!empty($cirujano2_data) && !empty($cirujano2_data['cedula'])) {
                    echo $cirujano2_data['cedula'];
                } elseif (!empty($ayudante_data['cedula'])) {
                    echo $ayudante_data['cedula'];
                } else {
                    echo '';
                }
                ?></td>
            <td class='blanco' colspan='5'><?php
                if (!empty($cirujano2_data) && !empty($cirujano2_data['firma'])) {
                    echo "<img src='" . htmlspecialchars($cirujano2_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
                } elseif (!empty($ayudante_data['firma'])) {
                    echo "<img src='" . htmlspecialchars($ayudante_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
                } else {
                    echo ' ';
                }
                ?></td>
        </tr>
        <tr>
            <td class='blanco' style='height: 75'
                colspan='5'><?php echo strtoupper($anestesiologo_data['nombre']); ?></td>
            <td class='blanco' colspan='5'><?php echo strtoupper($anestesiologo_data['especialidad']); ?></td>
            <td class='blanco' colspan='5'><?php echo $anestesiologo_data['cedula']; ?></td>
            <td class='blanco'
                colspan='5'><?php echo "<img src='" . htmlspecialchars($anestesiologo_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
                ?></td>
        </tr>
    </table>
    <table style='border: none'>
        <TR>
            <TD colspan='6' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                                   COLOR='#000000'>SNS-MSP/HCU-form. 017/2021</FONT></B>
            </TD>
            <TD colspan='3' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000'>PROTOCOLO QUIRÚRGICO (2)</FONT></B>
            </TD>
        </TR>
    </TABLE>

<?php
$content = ob_get_clean();
$title = 'Protocolo Quirúrgico';

include $layout;
