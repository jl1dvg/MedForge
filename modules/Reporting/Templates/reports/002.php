<?php
$layout = __DIR__ . '/../layouts/base.php';
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

$diagnosticoItems = is_array($diagnostico ?? null) ? array_values(array_filter($diagnostico, 'is_array')) : [];
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
ob_start();
//echo '<pre>';
//var_dump($data);
//echo '</pre>';
?>
    <table>
        <tr>
            <td class="morado" WIDTH="50%">B. MOTIVO DE CONSULTA</td>
            <td class="morado_right" width="20%" style="border-right: 1px solid #808080">PRIMERA</td>
            <td class="blanco" width="5%"></td>
            <td class="morado_right" width="20%" style="border-right: 1px solid #808080">SUBSECUENTE</td>
            <td class="blanco" width="5%"></td>
        </tr>
        <tr>
            <td colspan="5" class="blanco_left"><?php
                $motivoTexto = $reason !== '' ? $reason : 'Sin datos registrados.';
                echo wordwrap($motivoTexto, 135, "</td>
    </tr>
    <tr>
        <td colspan=\"5\" class=\"blanco_left\">"); ?></td>
        </tr>
        <tr>
            <td colspan="5" class="blanco_left"></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="13" class="morado">C. ANTECEDENTES PATOLÓGICOS PERSONALES</td>
            <td colspan="7" class="morado" style="font-weight: normal; font-size: 4pt">DATOS CLÍNICO - QUIRÚRGICOS,
                OBSTÉTRICOS, ALÉRGICOS RELEVANTES
            </td>
        </tr>
        <tr>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">1. CARDIOPATÍA</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">2. HIPERTENSIÓN</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">3. ENF. C. VASCULAR</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">4. ENDÓCRINO METABÓLICO</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">5. CÁNCER</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">6. TUBERCULOSIS</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">7. ENF. MENTAL</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">8. ENF. INFECCIOSA</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">9. MAL FORMACIÓN</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">10. OTRO</td>
            <td class="blanco" width="2%"></td>
        </tr>
        <?php
        if ($diagnosticoTexto !== []) {
            foreach ($diagnosticoTexto as $detalle) {
                echo '<tr><td colspan="20" class="blanco_left">' . htmlspecialchars($detalle) . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="20" class="blanco_left">Niega</td></tr>';
        }
        ?>
    </table>
    <table>
        <tr>
            <td colspan="20" class="morado">D. ANTECEDENTES PATOLÓGICOS FAMILIARES</td>
        </tr>
        <tr>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">1. CARDIOPATÍA</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">2. HIPERTENSIÓN</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">3. ENF. C. VASCULAR</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">4. ENDÓCRINO METABÓLICO</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">5. CÁNCER</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">6. TUBERCULOSIS</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">7. ENF. MENTAL</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">8. ENF. INFECCIOSA</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">9. MAL FORMACIÓN</td>
            <td class="blanco" width="2%"></td>
            <td class="verde_left" width="8%" style="font-weight: normal; font-size: 4pt">10. OTRO</td>
            <td class="blanco" width="2%"></td>
        </tr>
        <tr>
            <td colspan="20" class="blanco_left"></td>
        </tr>
        <tr>
            <td colspan="20" class="blanco_left">Sin datos registrados.</td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="morado">E. ENFERMEDAD O PROBLEMA ACTUAL</td>
            <td class="morado" style="font-weight: normal; font-size: 4pt">CRONOLOGÍA - LOCALIZACIÓN -
                CARACTERÍSTICAS - INTENSIDAD - FRECUENCIA - FACTORES AGRAVANTES
            </td>
        </tr>
        <tr>
            <td colspan="2" class="blanco_left">
                <?php
                // E. ENFERMEDAD O PROBLEMA ACTUAL
                // Regla correcta:
                // 1) usar enfermedad_actual
                // 2) si está vacío, usar motivo_consulta
                $enfermedadActualTexto = trim((string)($consulta['enfermedad_actual'] ?? ''));

                if ($enfermedadActualTexto === '') {
                    $enfermedadActualTexto = trim((string)($consulta['motivo_consulta'] ?? ''));
                }

                $enfermedadActualTexto = $enfermedadActualTexto !== ''
                        ? $enfermedadActualTexto
                        : 'Sin datos registrados.';

                echo wordwrap(
                        $enfermedadActualTexto,
                        135,
                        "</td></tr><tr><td colspan='2' class='blanco_left'>",
                        true
                );
                ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="blanco_left"></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="13" class="morado">F. CONSTANTES VITALES Y ANTROPOMETRÍA</td>
        </tr>
        <tr>
            <td class="verde" width="7.69%">FECHA</td>
            <td class="verde" width="7.69%">HORA</td>
            <td class="verde" width="7.69%">Temperatura (°C)</td>
            <td class="verde" width="7.69%">Presión Arterial (mmHg)</td>
            <td class="verde" width="7.69%">Pulso / min</td>
            <td class="verde" width="7.69%">Frecuencia Respiratoria/min</td>
            <td class="verde" width="7.69%">Peso (Kg)</td>
            <td class="verde" width="7.69%">Talla (cm)</td>
            <td class="verde" width="7.69%">IMC (Kg / m 2)</td>
            <td class="verde" width="7.69%">Perímetro Abdominal (cm)</td>
            <td class="verde" width="7.69%">Hemoglobina capilar (g/dl)</td>
            <td class="verde" width="7.69%">Glucosa capilar (mg/ dl)</td>
            <td class="verde" width="7.69%">Pulsioximetría (%)</td>
        </tr>
        <tr>
            <td class="blanco"><?php echo $fechaConsulta; ?></td>
            <td class="blanco"><?php echo $horaConsulta; ?></td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
            <td class="blanco">N/A</td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="9" class="morado">G. REVISIÓN ACTUAL DE ÓRGANOS Y SISTEMAS</td>
            <td colspan="6" class="morado" style="font-weight: normal; font-size: 4pt">MARCAR "X" CUANDO PRESENTE
                PATOLOGÍA Y DESCRIBA
            </td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">1</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">PIEL - ANEXOS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">3</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">RESPIRATORIO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">5</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">DIGESTIVO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">7</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">MÚSCULO - ESQUELÉTICO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">9</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">HEMO - LINFÁTICO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">2</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">ÓRGANOS DE LOS SENTIDOS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">4</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">CARDIO - VASCULAR</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">6</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">GENITO - URINARIO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">8</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">ENDOCRINO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">10</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">NERVIOSO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td colspan="15" class="blanco_left"><br></td>
        </tr>
        <tr>
            <td colspan="15" class="blanco_left"><br></td>
        </tr>
        <tr>
            <td colspan="15" class="blanco_left"><br></td>
        </tr>
    </table>
    <table style="border: none">
        <TR>
            <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=TOP><B><FONT SIZE=1
                                                                     COLOR="#000000">SNS-MSP/HCU-form.002/2021</FONT></B>
            </TD>
            <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">CONSULTA EXTERNA - ANAMNESIS
                        (1) </FONT></B>
            </TD>
        </TR>
    </TABLE>
    <pagebreak>
    <table>
        <tr>
            <td colspan="9" class="morado">H. EXAMEN FÍSICO</td>
            <td colspan="6" class="morado" style="font-weight: normal; font-size: 4pt">MARCAR "X" CUANDO PRESENTE
                PATOLOGÍA Y DESCRIBA
            </td>
        </tr>
        <tr>
            <td colspan="9" class="verde" style="background-color: #0ba1b5">REGIONAL</td>
            <td colspan="6" class="verde" style="background-color: #0ba1b5">SISTÉMICO</td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">1R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">PIEL - FANERAS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">2R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">BOCA</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">11R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">ABDOMEN</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">1S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">ÓRGANOS DE LOS SENTIDOS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">6S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">URINARIO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">2R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">CABEZA</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">7R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">OROFARINGE</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">12R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">COLUMNA VERTEBRAL</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">2S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">RESPIRATORIO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">7S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">MÚSCULO - ESQUELÉTICO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">3R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">OJOS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt">X</td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">8R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">CUELLO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">13R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">INGLE-PERINÉ</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">3S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">CARDIO - VASCULAR</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">8S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">ENDÓCRINO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">4R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">OÍDOS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">9R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">AXILAS - MAMAS</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">14R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">MIEMBROS SUPERIORES</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">4S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">DIGESTIVO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">9S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">HEMO - LINFÁTICO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">5R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">NARIZ</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">10R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">TÓRAX</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">15R</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">MIEMBROS INFERIORES</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">5S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">GENITAL</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
            <td class="verde" width="1%" style="font-weight: normal; font-size: 5pt">10S</td>
            <td class="verde" width="7%" style="font-weight: normal; font-size: 5pt">NEUROLÓGICO</td>
            <td class="blanco" width="2%" style="font-weight: normal; font-size: 5pt"></td>
        </tr>
        <tr>
            <td colspan="15" class="blanco_left">
                <?php
                $examenFisicoTexto = trim((string)($consultaExamenFisico ?? $consulta['examen_fisico'] ?? ''));
                $examenFisicoTexto = $examenFisicoTexto !== '' ? $examenFisicoTexto : 'Sin datos registrados.';
                echo wordwrap($examenFisicoTexto, 135, "</td></tr><tr><td colspan='15' class='blanco_left'>", true);
                ?>
            </td>
        </tr>
        <tr>
            <td colspan="15" class="blanco"></td>
        </tr>
    </table>
    <table>
        <TR>
            <TD class="morado" width="2%">I.</TD>
            <TD class="morado" width="17.5%">DIAGN&Oacute;STICOS</TD>
            <TD class="morado" width="17.5%" style="font-weight: normal; font-size: 6pt">PRE= PRESUNTIVO DEF= DEFINITIVO
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
        $totalDiagnosticos = count($diagnosticoItems);
        for ($row = 0; $row < 3; $row++):
            $leftIndex = $row * 2;
            $rightIndex = $leftIndex + 1;
            ?>
            <tr>
                <?php
                $leftDiag = $leftIndex < $totalDiagnosticos ? $diagnosticoItems[$leftIndex] : null;
                $leftDesc = $leftDiag['descripcion'] ?? $leftDiag['descripcion_dx'] ?? '';
                $leftCode = $leftDiag['dx_code'] ?? $leftDiag['codigo'] ?? '';
                ?>
                <td class="verde"><?= $leftIndex + 1 ?>.</td>
                <td colspan="2" class="blanco" style="text-align: left"><?= htmlspecialchars((string)$leftDesc) ?></td>
                <td class="blanco"><?= htmlspecialchars((string)$leftCode) ?></td>
                <td class="amarillo"></td>
                <td class="amarillo"><?= ($leftDiag !== null && ($leftDesc !== '' || $leftCode !== '')) ? 'x' : '' ?></td>

                <?php
                $rightDiag = $rightIndex < $totalDiagnosticos ? $diagnosticoItems[$rightIndex] : null;
                $rightDesc = $rightDiag['descripcion'] ?? $rightDiag['descripcion_dx'] ?? '';
                $rightCode = $rightDiag['dx_code'] ?? $rightDiag['codigo'] ?? '';
                ?>
                <td class="verde"><?= $rightIndex + 1 ?>.</td>
                <td colspan="2" class="blanco" style="text-align: left"><?= htmlspecialchars((string)$rightDesc) ?></td>
                <td class="blanco"><?= htmlspecialchars((string)$rightCode) ?></td>
                <td class="amarillo"></td>
                <td class="amarillo"><?= ($rightDiag !== null && ($rightDesc !== '' || $rightCode !== '')) ? 'x' : '' ?></td>
            </tr>
        <?php endfor; ?>
    </table>
    <table>
        <tr>
            <td colspan="41" class="morado">J. PLAN DE TRATAMIENTO</td>
            <td colspan="30" class="morado" style="font-weight: normal; font-size: 4pt">DIAGNOSTICO, TERAPÉUTICO Y
                EDUCACIONAL
            </td>
        </tr>
        <tr>
            <td colspan="71" class="blanco_left">
                <?php
                if ($planTratamiento !== '') {
                    echo wordwrap($planTratamiento, 135, "</td></tr><tr><td colspan='71' class='blanco_left'>", true);
                } else {
                    echo 'Sin datos registrados.';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td colspan="71" class="blanco" style="border-right: none; text-align: left"></td>
        </tr>
        <tr>
            <td colspan="71" class="blanco" style="border-right: none; text-align: left"></td>
        </tr>
        <tr>
            <td colspan="71" class="blanco" style="border-right: none; text-align: left"></td>
        </tr>
        <tr>
            <td colspan="71" class="blanco" style="border-right: none; text-align: left"></td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan="71" class="morado">K. DATOS DEL PROFESIONAL RESPONSABLE</td>
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
                class="blanco"><?php echo $fechaConsulta; ?></td>
            <td colspan="7" class="blanco"><?php echo $horaConsulta; ?></td>
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
            <td colspan="26" class="blanco">
                <?php if (!empty($consulta['doctor_firma'] ?? '')): ?>
                    <div style="margin-bottom: -25px;">
                        <img src="<?= htmlspecialchars((string)$consulta['doctor_firma'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="Firma del profesional" style="max-height: 60px;">
                    </div>
                <?php endif; ?>
            </td>
            <td colspan="30" class="blanco">&nbsp;</td>
        </tr>
    </table>
    <table style="border: none">
        <TR>
            <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=TOP><B><FONT SIZE=1
                                                                     COLOR="#000000">SNS-MSP/HCU-form.002/2021</FONT></B>
            </TD>
            <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">CONSULTA EXTERNA - EXAMEN FÍSICO Y
                        PRESCRIPCIONES (2) </FONT></B>
            </TD>
        </TR>
    </TABLE>
<?php
$content = ob_get_clean();
$title = 'Formulario 002 - Consulta Externa - Examen Físico y Prescripciones';

include $layout;
