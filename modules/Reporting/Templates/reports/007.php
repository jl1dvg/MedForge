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

ob_start();
?>
<table>
    <colgroup>
        <col class="xl76" span="71">
    </colgroup>
    <tr>
        <td colspan="71" class="morado">B. CUADRO CLÍNICO DE INTERCONSULTA</td>
    </tr>
    <tr>
        <td colspan="71" class="blanco_left"><?php
            $reason = $consulta['motivo_consulta'] . ' ' . $consulta['enfermedad_actual'];
            $reasonAI = '';
            $reasonAI_error = null;
            if (isset($ai)) {
                try {
                    $reasonAI = $ai->generateEnfermedadProblemaActual($reason ?? '');
                } catch (\Throwable $e) {
                    $reasonAI_error = $e->getMessage();
                    error_log('OpenAI generateEnfermedadProblemaActual error: ' . $reasonAI_error);
                }
            }
            if (trim($reasonAI) !== '') {
                echo wordwrap($reasonAI, 150, "</td></tr><tr><td colspan=\"71\" class=\"blanco_left\">");
            } else {
                // fallback: no AI output
                echo wordwrap('[AI sin salida para criterio clínico]', 150, "</td></tr><tr><td colspan=\"71\" class=\"blanco_left\">");
            }
            if (!empty($AI_DEBUG)) {
                echo "<div style='border:1px dashed #c00; margin:6px 0; padding:6px; font-size:8pt; color:#900;'>
            <b>AI DEBUG — Criterio Clínico</b><br>
            <pre style='white-space:pre-wrap;'>" . htmlspecialchars(json_encode([
                        'has_ai' => isset($ai),
                        'input_preview' => mb_substr((string)($reason ?? ''), 0, 400),
                        'output_len' => mb_strlen((string)$reasonAI),
                        'error' => $reasonAI_error
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "</pre>
          </div>";
            }
            ?></td>
    </tr>
    <tr>
        <td colspan="71" class="blanco_left"></td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado">C. RESUMEN DEL CRITERIO CLÍNICO</td>
    </tr>
    <tr>
        <td class="blanco_left">
            <?php
            $examenFisico = $consulta['examen_fisico'];
            $examenAI = '';
            $examenAI_error = null;
            if (isset($ai)) {
                try {
                    $examenAI = $ai->generateEnfermedadProblemaActual($examenFisico ?? '');
                } catch (\Throwable $e) {
                    $examenAI_error = $e->getMessage();
                    error_log('OpenAI generateEnfermedadProblemaActual error: ' . $examenAI_error);
                }
            }
            if (trim($examenAI) !== '') {
                echo wordwrap($examenAI, 150, "</TD></TR><TR><TD class='blanco_left'>");
            } else {
                // fallback: no AI output
                echo wordwrap('[AI sin salida para criterio clínico]', 150, "</TD></TR><TR><TD class='blanco_left'>");
            }
            if (!empty($AI_DEBUG)) {
                echo "<div style='border:1px dashed #c00; margin:6px 0; padding:6px; font-size:8pt; color:#900;'>
            <b>AI DEBUG — Criterio Clínico</b><br>
            <pre style='white-space:pre-wrap;'>" . htmlspecialchars(json_encode([
                        'has_ai' => isset($ai),
                        'input_preview' => mb_substr((string)($examenFisico ?? ''), 0, 400),
                        'output_len' => mb_strlen((string)$examenAI),
                        'error' => $examenAI_error
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "</pre>
          </div>";
            }
            ?>
        </td>
    </tr>
</table>
<?php
// Generar la tabla con el nuevo formato para imprimir diagnósticos
// Inicializar variables de control
$totalItems = count($diagnostico);
$rows = max(ceil($totalItems / 2), 3); // Asegurarse de que haya al menos 3 filas por columna

// Crear la tabla HTML
echo "<table>";
// Encabezado de la tabla
echo "<tr>
    <td class='morado' width='2%'>D.</td>
    <td class='morado' width='17.5%'>DIAGNÓSTICOS</td>
    <td class='morado' width='17.5%' style='font-weight: normal; font-size: 6pt'>PRE= PRESUNTIVO DEF= DEFINITIVO</td>
    <td class='morado' width='6%' style='font-size: 6pt; text-align: center'>CIE</td>
    <td class='morado' width='3.5%' style='font-size: 6pt; text-align: center'>PRE</td>
    <td class='morado' width='3.5%' style='font-size: 6pt; text-align: center'>DEF</td>
    <td class='morado' width='2%'><br></td>
    <td class='morado' width='17.5%'><br></td>
    <td class='morado' width='17.5%'><br></td>
    <td class='morado' width='6%' style='font-size: 6pt; text-align: center'>CIE</td>
    <td class='morado' width='3.5%' style='font-size: 6pt; text-align: center'>PRE</td>
    <td class='morado' width='3.5%' style='font-size: 6pt; text-align: center'>DEF</td>
</tr>";

// Generar filas para los diagnósticos
for ($i = 0; $i < $rows; $i++) {
    $leftIndex = $i * 2;
    $rightIndex = $leftIndex + 1;

    echo "<tr>";

    // Columna izquierda
    if ($leftIndex < $totalItems) {
        $cie10Left = $diagnostico[$leftIndex]['dx_code'] ?? '';
        $detalleLeft = $diagnostico[$leftIndex]['descripcion'] ?? '';

        echo "<td class='verde'>" . ($leftIndex + 1) . "</td>";
        echo "<td colspan='2' class='blanco' style='text-align: left'>" . htmlspecialchars($detalleLeft) . "</td>";
        echo "<td class='blanco'>" . htmlspecialchars($cie10Left) . "</td>";
        echo "<td class='amarillo'></td>";
        echo "<td class='amarillo'>x</td>";
    } else {
        echo "<td class='verde'>" . ($leftIndex + 1) . "</td><td colspan='2' class='blanco'></td><td class='blanco'></td><td class='amarillo'></td><td class='amarillo'></td>";
    }

    // Columna derecha
    if ($rightIndex < $totalItems) {
        $cie10Right = $diagnostico[$rightIndex]['dx_code'] ?? '';
        $detalleRight = $diagnostico[$rightIndex]['descripcion'] ?? '';

        echo "<td class='verde'>" . ($rightIndex + 1) . "</td>";
        echo "<td colspan='2' class='blanco' style='text-align: left'>" . htmlspecialchars($detalleRight) . "</td>";
        echo "<td class='blanco'>" . htmlspecialchars($cie10Right) . "</td>";
        echo "<td class='amarillo'></td>";
        echo "<td class='amarillo'>x</td>";
    } else {
        echo "<td class='verde'>" . ($rightIndex + 1) . "</td><td colspan='2' class='blanco'></td><td class='blanco'></td><td class='amarillo'></td><td class='amarillo'></td>";
    }

    echo "</tr>";
}

// Cerrar la tabla
echo "</table>";
?>
<table>
    <tr>
        <td class="morado">E. PLAN DE DIAGNÓSTICO PROPUESTO</td>
    </tr>
    <tr>
        <td class="blanco" style="border-right: none; text-align: left">
            <?php
            // Texto base para diagnóstico (prioriza un campo específico si existiera)
            $textoDiag = $consulta['diagnostico_plan'] ?? ($consulta['plan'] ?? '');

            $estudiosExplicitos = null;
            if (isset($solicitud['examenes_list']) && is_array($solicitud['examenes_list'])) {
                $estudiosExplicitos = array_values(array_filter(
                    array_map('strval', $solicitud['examenes_list']),
                    static fn($s) => trim($s) !== ''
                ));
            }

            if ($estudiosExplicitos === null) {
                $examenes = $solicitud['examenes'] ?? null; // retrocompatibilidad para datos sin normalizar
                if (is_array($examenes)) {
                    $estudiosExplicitos = array_values(array_filter(array_map('strval', $examenes), fn($s) => trim($s) !== ''));
                } elseif (is_string($examenes) && trim($examenes) !== '') {
                    $decoded = json_decode($examenes, true);
                    if (is_array($decoded)) {
                        $estudiosExplicitos = array_values(array_filter(array_map('strval', $decoded), fn($s) => trim($s) !== ''));
                    } else {
                        $parts = preg_split('/[,;\|\n]+/', $examenes);
                        $estudiosExplicitos = array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
                    }
                }
            }

            $planDiagAI = '';
            $planDiagAI_error = null;

            try {
                if (isset($ai)) {
                    $planDiagAI = $ai->generatePlanDiagnostico($textoDiag, $estudiosExplicitos);
                } else {
                    $planDiagAI_error = 'OpenAIHelper no está disponible (clase no cargada).';
                }
            } catch (\Throwable $e) {
                $planDiagAI_error = $e->getMessage();
                // Log del error para inspección en el servidor (no interrumpe el PDF)
                error_log('OpenAI generatePlanDiagnostico error: ' . $planDiagAI_error);
            }

            // Fallback: si no hay salida IA, usa el listado explícito o el texto base
            if (trim($planDiagAI) === '') {
                if (!empty($estudiosExplicitos)) {
                    $fallbackLines = ["Plan de diagnóstico propuesto:"];
                    foreach ($estudiosExplicitos as $item) {
                        $fallbackLines[] = '- ' . $item . '.';
                    }
                    $planDiagAI = implode("\n", $fallbackLines);
                } else {
                    $planDiagAI = trim($textoDiag);
                }
            }

            echo wordwrap($planDiagAI, 150, "</TD></TR><TR><TD class='blanco_left'>");

            // Bloque de depuración opcional en el propio PDF/HTML
            if (!empty($AI_DEBUG)) {
                $diag = [
                    'has_ai' => isset($ai),
                    'model_input_preview' => mb_substr($textoDiag, 0, 400),
                    'examenes_explicit' => $estudiosExplicitos,
                    'ai_output_len' => mb_strlen($planDiagAI),
                    'had_fallback' => (
                        empty($planDiagAI_error) && trim($planDiagAI) !== '' && (
                            (!empty($estudiosExplicitos) && strpos($planDiagAI, 'Plan de diagnóstico propuesto:') === 0)
                            || (empty($estudiosExplicitos) && trim($planDiagAI) === trim($textoDiag))
                        )
                    ),
                    'error' => $planDiagAI_error
                ];
                echo "<div style='border:1px dashed #06c; margin:6px 0; padding:6px; font-size:8pt; color:#036;'>
            <b>AI DEBUG — Plan Diagnóstico</b><br>
            <pre style='white-space:pre-wrap;'>" . htmlspecialchars(json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "</pre>
          </div>";
                // También al log del servidor
                error_log('AI DEBUG — Plan Diagnóstico: ' . json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            ?>
    </tr>
</table>
<table>
    <tr>
        <td colspan="71" class="morado">F. PLAN TERAPEÚTICO PROPUESTO</td>
    </tr>
    <tr>
        <td colspan="71" class="blanco_left">
            <?php
            $eye = $solicitud['ojo'] ?? '';
            // Normaliza a texto legible sin punto final para pasarlo a IA
            if ($eye === 'D') {
                $eye = 'ojo derecho';
            } elseif ($eye === 'I') {
                $eye = 'ojo izquierdo';
            } elseif ($eye === 'AO' || $eye === 'B') { // por si usas ambos ojos
                $eye = 'ambos ojos';
            }

            $procedimiento = $solicitud['procedimiento'] ?? '';
            $promptPlan = $consulta['plan'] ?? '';
            $insurance = $paciente['afiliacion'] ?? '';

            try {
                if (isset($ai)) {
                    $planAI = $ai->generatePlanTratamiento($promptPlan, $insurance, $procedimiento, $eye);
                } else {
                    $planAI_error = 'OpenAIHelper no está disponible (clase no cargada).';
                }
            } catch (\Throwable $e) {
                $planAI_error = $e->getMessage();
                // Log del error para inspección en el servidor (no interrumpe el PDF)
                error_log('OpenAI generatePlanTratamiento error: ' . $planAI_error);
            }

            // Fallback: si por cualquier motivo no se obtuvo texto de la IA, usamos el plan crudo
            if (trim($planAI) === '') {
                $planAI = trim($promptPlan);
            }

            echo wordwrap($planAI, 150, "</TD></TR><TR><TD colspan=71 class='blanco_left'>");

            // Bloque de depuración opcional en el propio PDF/HTML
            if (!empty($AI_DEBUG)) {
                $diag = [
                    'has_ai' => isset($ai),
                    'model_input_preview' => mb_substr($promptPlan, 0, 400),
                    'insurance' => $insurance,
                    'procedimiento' => $procedimiento,
                    'ojo' => $eye,
                    'ai_output_len' => mb_strlen($planAI),
                    'had_fallback' => trim($planAI) === trim($promptPlan),
                    'error' => $planAI_error
                ];
                echo "<div style='border:1px dashed #06c; margin:6px 0; padding:6px; font-size:8pt; color:#036;'>
            <b>AI DEBUG — Plan Terapéutico</b><br>
            <pre style='white-space:pre-wrap;'>" . htmlspecialchars(json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "</pre>
          </div>";
                // También al log del servidor
                error_log('AI DEBUG — Plan Terapéutico: ' . json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        <td colspan="71" class="morado">G. DATOS DEL PROFESIONAL RESPONSABLE</td>
    </tr>
    <tr class="xl78">
        <td colspan="8" class="verde">FECHA<br>
            <font class="font5">(aaaa-mm-dd)</font>
        </td>
        <td colspan="7" class="verde">HORA<br>
            <font class="font5">(hh:mm)</font>
        </td>
        <td colspan="21" class="verde">PRIMER NOMBRE</td>
        <td colspan="19" class="verde">PRIMER APELLIDO</td>
        <td colspan="16" class="verde">SEGUNDO APELLIDO</td>
    </tr>
    <tr>
        <td colspan="8" class="blanco"><?php
            $fechaCompleta = $solicitud['created_at'];
            $fecha = date('Y-m-d', strtotime($fechaCompleta));
            $hora = date('H:i', strtotime($fechaCompleta));
            echo $fecha ?></td>
        <td colspan="7" class="blanco"><?php echo $hora; ?></td>
        <td colspan="21" class="blanco"><?php echo htmlspecialchars($solicitud['doctor']); ?></td>
        <td colspan="19" class="blanco"></td>
        <td colspan="16" class="blanco"></td>
    </tr>
    <tr>
        <td colspan="15" class="verde">NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
        <td colspan="26" class="verde">FIRMA</td>
        <td colspan="30" class="verde">SELLO</td>
    </tr>
    <tr>
        <td colspan="15" class="blanco"
            style="height: 40px"><?php echo htmlspecialchars($solicitud['cedula']); ?></td>
        <td colspan="26" class="blanco"><?php if (!empty($solicitud['firma'])): ?>
                <div style="margin-bottom: -25px;">
                    <img src="<?= htmlspecialchars($solicitud['firma']) ?>" alt="Firma del cirujano"
                         style="max-height: 60px;">
                </div>
            <?php endif; ?>
        </td>
        <td colspan="30" class="blanco">&nbsp;</td>
    </tr>
</table>
<table style="border: none">
    <TR>
        <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=TOP><B><FONT SIZE=1
                                                                 COLOR="#000000">SNS-MSP/HCU-form.007/2021</FONT></B>
        </TD>
        <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">INTERCONSULTA -
                    INFORME</FONT></B>
        </TD>
    </TR>
</TABLE>
<?php
$content = ob_get_clean();
$title = 'Formulario 007 - Interconsulta';

include $layout;
