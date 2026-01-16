<?php

use Helpers\OpenAIHelper;

// Inicializa el helper de OpenAI (requiere que el autoload/bootstrapping ya esté cargado antes)
$ai = null;
if (class_exists(OpenAIHelper::class)) {
    $ai = new OpenAIHelper();
}
$AI_DEBUG = isset($_GET['debug_ai']) && $_GET['debug_ai'] === '1';

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

$doctorFirstName = trim((string)($solicitud['doctor_first_name'] ?? ''));
$doctorMiddleName = trim((string)($solicitud['doctor_middle_name'] ?? ''));
$doctorLastName = trim((string)($solicitud['doctor_last_name'] ?? ''));
$doctorSecondLastName = trim((string)($solicitud['doctor_second_last_name'] ?? ''));

if ($doctorFirstName === '' && $doctorMiddleName === '' && $doctorLastName === '' && $doctorSecondLastName === '') {
    $doctorFullName = trim((string)($solicitud['doctor_full_name'] ?? $solicitud['doctor'] ?? ''));
    $doctorFirstName = $doctorFullName;
}

$doctorFirstNameDisplay = trim($doctorFirstName . ' ' . $doctorMiddleName);

ob_start();
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
                echo wordwrap($reason, 165, "</td>
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
        $diagnoses = getMedicalProblems($pid);

        if (!empty($diagnoses)) {
            foreach ($diagnoses as $diagnosis) {
                $problem = lookup_code_short_descriptions($diagnosis);
                $cie10 = substr($diagnosis, 6);
                echo "<tr><td colspan=\"20\" class=\"blanco_left\">$problem CIE10: $cie10<td></td>";
            }
        } else {
            echo "<tr><td colspan=\"20\" class=\"blanco_left\">Niega</td>></tr>";
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
            <td colspan="20" class="blanco_left"></td>
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
                if ($formdir === 'eye_mag') {
                    $encounter_data = getEyeMagEncounterData($form_encounter, $pid);
                    if ($encounter_data) {
                        extract($encounter_data);
                        $examOutput = ExamOftal($val, $CC1 ?? '', $RBROW ?? '', $LBROW ?? '', $RUL ?? '', $LUL ?? '', $RLL ?? '', $LLL ?? '', $RMCT ?? '', $LMCT ?? '', $RADNEXA ?? '', $LADNEXA ?? '', $EXT_COMMENTS ?? '',
                            $SCODVA ?? '', $SCOSVA ?? '', $ODVA ?? '', $OSVA ?? '', $ODIOPAP ?? '', $OSIOPAP ?? '', $ODCONJ ?? '', $OSCONJ ?? '', $ODCORNEA ?? '', $OSCORNEA ?? '', $ODAC ?? '', $OSAC ?? '', $ODLENS ?? '', $OSLENS ?? '', $ODIRIS ?? '', $OSIRIS ?? '',
                            $ODDISC ?? '', $OSDISC ?? '', $ODCUP ?? '', $OSCUP ?? '', $ODMACULA ?? '', $OSMACULA ?? '', $ODVESSELS ?? '', $OSVESSELS ?? '', $ODPERIPH ?? '', $OSPERIPH ?? '', $ODVITREOUS ?? '', $OSVITREOUS ?? '');
                        if (!empty($examOutput)) {
                            $enfermedadActual = generateEnfermedadProblemaActual($reason, $examOutput);
                            echo wordwrap($enfermedadActual, 165, "</td></tr><tr><td colspan='2' class='blanco_left'>", true);
                        }
                    }
                }
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
            <td class="blanco"><?php echo $fecha007; ?></td>
            <td class="blanco"><?php echo date("H:i", $time007); ?></td>
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
            <td colspan="15" class="blanco"></td>
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
                if ($formdir === 'eye_mag') {
                    $encounter_data = getEyeMagEncounterData($form_encounter, $pid);
                    if ($encounter_data) {
                        extract($encounter_data);
                        $examOutput = ExamOftal($val, $CC1 ?? '', $RBROW ?? '', $LBROW ?? '', $RUL ?? '', $LUL ?? '', $RLL ?? '', $LLL ?? '', $RMCT ?? '', $LMCT ?? '', $RADNEXA ?? '', $LADNEXA ?? '', $EXT_COMMENTS ?? '',
                            $SCODVA ?? '', $SCOSVA ?? '', $ODVA ?? '', $OSVA ?? '', $ODIOPAP ?? '', $OSIOPAP ?? '', $ODCONJ ?? '', $OSCONJ ?? '', $ODCORNEA ?? '', $OSCORNEA ?? '', $ODAC ?? '', $OSAC ?? '', $ODLENS ?? '', $OSLENS ?? '', $ODIRIS ?? '', $OSIRIS ?? '',
                            $ODDISC ?? '', $OSDISC ?? '', $ODCUP ?? '', $OSCUP ?? '', $ODMACULA ?? '', $OSMACULA ?? '', $ODVESSELS ?? '', $OSVESSELS ?? '', $ODPERIPH ?? '', $OSPERIPH ?? '', $ODVITREOUS ?? '', $OSVITREOUS ?? '');
                        if (!empty($examOutput)) {
                            echo wordwrap($examOutput, 165, "</td></tr><tr><td colspan='15' class='blanco_left'>", true);
                        }
                    }
                }
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
        <TR>
            <td class="verde">1.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "0"); ?></td>
            <td class="blanco"><?php echo getDXoftalmoCIE10($form_id, $pid, "0"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "0")) {
                    echo "x";
                } ?></td>
            <td class="verde">4.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "3"); ?></td>
            <td class=" blanco
        "><?php echo getDXoftalmoCIE10($form_id, $pid, "3"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "3")) {
                    echo "x";
                } ?></td>
        </TR>
        <TR>
            <td class="verde">2.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "1"); ?></td>
            <td class="blanco"><?php echo getDXoftalmoCIE10($form_id, $pid, "1"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "1")) {
                    echo "x";
                } ?></td>
            <td class="verde">5.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "4"); ?></td>
            <td class=" blanco
        "><?php echo getDXoftalmoCIE10($form_id, $pid, "4"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "4")) {
                    echo "x";
                } ?></td>
        </TR>
        <TR>
            <td class="verde">3.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "2"); ?></td>
            <td class="blanco"><?php echo getDXoftalmoCIE10($form_id, $pid, "2"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "2")) {
                    echo "x";
                } ?></td>
            <td class="verde">6.</td>
            <td colspan="2" class="blanco"
                style="text-align: left"><?php echo getDXoftalmo($form_id, $pid, "5"); ?></td>
            <td class=" blanco
        "><?php echo getDXoftalmoCIE10($form_id, $pid, "5"); ?></td>
            <td class="amarillo"></td>
            <td class="amarillo"><?php if (getDXoftalmo($form_id, $pid, "5")) {
                    echo "x";
                } ?></td>
        </TR>
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
                echo getPlanTerapeuticoOD($form_id, $pid);
                echo getPlanTerapeuticoOI($form_id, $pid);
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
                class="blanco"><?php echo $fecha007; ?></td>
            <td colspan="7" class="blanco"><?php echo date("H:i", $time007); ?></td>
            <td colspan="21" class="blanco"><?php echo $doc['fname'] . " " . $doc['mname']; ?></td>
            <td colspan="19" class="blanco"><?php echo $doc['apellido_1']; ?></td>
            <td colspan="16" class="blanco"><?php echo $doc['apellido_2']; ?></td>
        </tr>
        <tr>
            <td colspan="15" class="verde">NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
            <td colspan="26" class="verde">FIRMA</td>
            <td colspan="30" class="verde">SELLO</td>
        </tr>
        <tr>
            <td colspan="15" class="blanco" style="height: 40px"><?php echo getProviderRegistro($providerID); ?></td>
            <td colspan="26" class="blanco">&nbsp;</td>
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
