<?php
// Datos para depuración AI
$layout = __DIR__ . '/../layouts/base.php';

ob_start();
?>
<table>
    <TR>
        <TD class="morado" colspan="12">l. DATOS DEL USARIO</TD>
    </TR>
    <TR>
        <TD class="verde" COLSPAN=2 rowspan="2">APELLIDO PATERNO</TD>
        <TD class="verde" COLSPAN=2 rowspan="2">APELLIDO MATERNO</TD>
        <TD class="verde" COLSPAN=3 rowspan="2">NOMBRES</TD>
        <TD class="verde" COLSPAN=3>Fecha de Nacimiento</TD>
        <TD class="verde" rowspan="2">EDAD</TD>
        <TD class="verde">SEXO</TD>
    </TR>
    <TR>
        <TD class="verde">Dia</TD>
        <TD class="verde">Mes</TD>
        <TD class="verde">A&ntilde;o</TD>
        <TD class="verde">H/M</TD>
    </TR>
    <TR>
        <TD class="blanco" colspan="2"><?= htmlspecialchars($paciente['lname']) ?></TD>
        <TD class="blanco" COLSPAN=2><?= htmlspecialchars($paciente['lname2']) ?></TD>
        <TD class="blanco" colspan="3"><?= htmlspecialchars($paciente['fname'] . " " . $paciente['mname']) ?>
        </TD>
        <TD class="blanco"><?= date("d", strtotime($paciente['fecha_nacimiento'])); ?>
        </TD>
        <TD class="blanco"><?= date("m", strtotime($paciente['fecha_nacimiento'])); ?>
        </TD>
        <TD class="blanco"><?= date("Y", strtotime($paciente['fecha_nacimiento'])); ?>
        </TD>
        <TD class="blanco">
            <?php
            echo $edadPaciente;
            ?>
        </TD>
        <TD class="blanco"><?= htmlspecialchars($paciente['sexo']) ?></TD>
    </TR>
    <TR>
        <TD class="verde" colspan="2" rowspan="2">NACIONALIDAD</TD>
        <TD class="verde" COLSPAN=2 rowspan="2">PAIS</TD>
        <TD class="verde" COLSPAN=2 rowspan="2">CEDULA O PASAPORTE</TD>
        <TD class="verde" COLSPAN=3>LUGAR DE RESIDENCIA</TD>
        <TD class="verde" COLSPAN=3 rowspan="2">DIRECCION DE DOMICILIO</TD>
    </TR>
    <TR>
        <TD class="verde">Prov.</TD>
        <TD class="verde">Canton</TD>
        <TD class="verde">Parroq.</TD>
    </TR>
    <TR>
        <TD class="blanco" colspan="2">ECUATORIANA</TD>
        <TD class="blanco" COLSPAN=2>ECUADOR</TD>
        <TD class="blanco" COLSPAN=2><?= htmlspecialchars($paciente['hc_number']) ?></TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
    </TR>
    <TR>
        <TD class="verde">E-MAIL:</TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
        <TD class="verde">TELEFONO:</TD>
        <TD class="blanco" COLSPAN=3></TD>
        <TD class="verde">FECHA:</TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
    </TR>
</table>
<table>
    <TR>
        <TD class="verde" width="40%">ll. REFERENCIA 1</TD>
        <TD class="blanco" width="10%"><BR></TD>
        <TD class="verde" width="40%"> DERIVACION 2</TD>
        <TD class="blanco" width="10%">X</TD>
    </TR>
</table>
<table>
    <TR>
        <TD COLSPAN=12 CLASS="morado">1 DATOS INSTITUCIONALES</TD>
    </TR>
    <TR>
        <TD class="verde" colspan="2">ENTIDAD DEL SISTEMA</TD>
        <TD class="verde" colspan="2">HISTORIA CLINICA</TD>
        <TD class="verde" COLSPAN=3>ESTABLECIMIENTO DE SALUD</TD>
        <TD class="verde" COLSPAN=2>TIPO</TD>
        <TD class="verde" COLSPAN=3>DISTRITO /AREA</TD>
    </TR>
    <TR>
        <TD class="blanco" COLSPAN=2></TD>
        <TD class="blanco" COLSPAN=2></TD>
        <TD class="blanco" COLSPAN=3></TD>
        <TD class="blanco" COLSPAN=2><BR></TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
    </TR>
    <TR>
        <TD class="verde" COLSPAN=8>REFIERE O DERIVA A:</TD>
        <TD class="verde" COLSPAN=4>FECHA</TD>
    </TR>
    <TR>
        <TD class="verde" COLSPAN=2>Entidad del Sistema</TD>
        <TD class="verde" COLSPAN=2>Establecimiento de Salud</TD>
        <TD class="verde" colspan="2">Servico</TD>
        <TD class="verde" COLSPAN=2>Especialidad</TD>
        <TD class="verde">Dia</TD>
        <TD class="verde">Mes</TD>
        <TD class="verde" colspan="2">A&ntilde;o</TD>
    </tr>
    <TR>
        <TD class="blanco" COLSPAN=2></TD>
        <TD class="blanco" COLSPAN=2></TD>
        <TD class="blanco" colspan="2">AMBULATORIO</TD>
        <TD class="blanco" COLSPAN=2></TD>
        <TD class="blanco">
        </TD>
        <TD class="blanco">
        </TD>
        <TD class="blanco" colspan="2">
        </TD>
    </TR>
</table>
<table>
    <TR>
        <TD COLSPAN=12 class="morado">2. MOTIVO DE LA REFERENCIA O DERIVACION</TD>
    </TR>
    <TR>
        <TD class="blanco" COLSPAN=4 width="40%">LIMITADA CAPACIDAD RESOLUTIVA</TD>
        <TD class="blanco" width="5%">1</TD>
        <TD class="blanco" width="5%"><BR></TD>
        <TD class="blanco" COLSPAN=4 width="40%">SATURACION DE CAPACIDAD INSTALADA</TD>
        <TD class="blanco" width="5%">4</TD>
        <TD class="blanco" width="5%"><BR></TD>
    </TR>
    <TR>
        <TD class="blanco" COLSPAN=4>AUSENCIA DEL PROFESIONAL</TD>
        <TD class="blanco">2</TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco" COLSPAN=4>CONTINUAR TRATAMIENTO</TD>
        <TD class="blanco">5</TD>
        <TD class="blanco"></TD>
    </TR>
    <tr>
        <TD class="blanco" COLSPAN=4>FALTA DEL PROFESIONAL</TD>
        <TD class="blanco">3</TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco" COLSPAN=4>OTROS ESPECIFIQUE</TD>
        <TD class="blanco"><br></TD>
        <TD class="blanco"><BR></TD>
    </tr>
</table>
<table>
    <TR>
        <TD class="morado">3. RESUMEN DEL CUADRO CLINICO</TD>
    </TR>
    <tr>
        <td class='blanco_left'></td>
    </tr>
</table>
<table>
    <TR>
        <TD class="morado">4. HALLAZGOS RELEVANTES DE EXAMENES Y PROCEDIMIENTOS DIAGNOSTICOS</TD>
    </TR>
    <TR>
        <TD class="blanco_left"></TD>
    </TR>
</table>
<table>
    <TR>
        <TD class="morado" width="55%">5. DIAGNOSTICO</TD>
        <TD class="morado" width="15%">CIE- 10</TD>
        <TD class="morado" width="15%">PRE</TD>
        <TD class="morado" width="15%">DEF</TD>
    </TR>
    <tr>
        <td class="blanco"></td>
        <td class="blanco"></td>
        <td class="blanco"></td>
        <td class="blanco"></td>
    </tr>
</table>
<table>
    <TR>
        <TD class="morado" width="80%">6. EXAMENES / PROCEDIMIENTOS SOLICITADOS</TD>
        <TD class="morado" width="20%">CODIGO TARIFARIO</TD>
    </TR>
    <tr>
        <td class="blanco_left"></td>
        <td class="blanco_left"></td>
    </tr>
</table>
<table>
    <TR>
        <TD class="blanco" width="28%"></TD>
        <TD class="blanco" width="16%"></TD>
        <TD class="blanco" width="28%"></TD>
        <TD class="blanco" width="28%"></TD>
    </TR>
    <TR>
        <TD class="verde">NOMBRE</TD>
        <TD class="verde">COD. MSP. PROF.</TD>
        <TD class="verde">DIRECTOR MEDICO</TD>
        <TD class="verde">MEDICO VERIFICADOR</TD>
    </TR>
</table>
<TABLE>
    <TR>
        <TD COLSPAN=12 class="morado">1. DATOS INSTITUCIONALES</TD>
    </TR>
    <TR>
        <TD class="verde" colspan="2">ENTIDAD DEL SISTEMA</TD>
        <TD class="verde" COLSPAN=2>HIST, CLINICA #</TD>
        <TD class="verde" COLSPAN=2>ESTABLECIMIENTO</TD>
        <TD class="verde">TIPO</TD>
        <TD class="verde" COLSPAN=2>SERVICIO</TD>
        <TD class="verde" COLSPAN=3>ESPECIALIDAD</TD>
    </TR>
    <TR>
        <TD class="blanco" COLSPAN=2><BR></TD>
        <TD class="blanco" COLSPAN=2><?= htmlspecialchars($paciente['hc_number']) ?></TD>
        <TD class="blanco" COLSPAN=2>CLINICA INTERNACIONAL DE LA VISION DE ECUADOR</TD>
        <TD class="blanco">III</TD>
        <TD class="blanco" COLSPAN=2><BR></TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
    </TR>
    <TR>
        <TD class="verde" colspan="4">lll. CONTRAREFERENCIA 3</TD>
        <TD class="blanco">X</TD>
        <TD class="verde" colspan="3">REFERENCIA INVERSA 4</TD>
        <TD class="blanco"><BR></TD>
        <TD class="verde" COLSPAN=3>FECHA
        </TD>
    </TR>
    <TR>
        <TD class="blanco" colspan="2"><?= htmlspecialchars($paciente['afiliacion']) ?></TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
        <TD class="blanco"><BR></TD>
        <TD class="blanco" COLSPAN=3><BR></TD>
        <TD class="blanco"><?php
            echo date("d", strtotime($solicitud['created_at']));
            ?></TD>
        <TD class="blanco"><?php
            echo date("m", strtotime($solicitud['created_at']));
            ?></TD>
        <TD class="blanco"><?php
            echo date("Y", strtotime($solicitud['created_at']));
            ?></TD>
    </TR>
    <TR>
        <TD class="verde" COLSPAN=2>Entidad del Sistema</TD>
        <TD class="verde" COLSPAN=3>Establecimiento de Salud</TD>
        <TD class="verde">Tipo</TD>
        <TD class="verde" COLSPAN=3>Districto/Area</TD>
        <TD class="verde">Dia</TD>
        <TD class="verde">Mes</TD>
        <TD class="verde">A&ntilde;o</TD>
    </TR>
</TABLE>
<table>
    <TR>
        <TD COLSPAN=12 class="morado">2. RESUMEN DEL CUADRO CLINICO</TD>
    </TR>
    <TR>
        <TD colspan="12" class="blanco_left">
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
                echo wordwrap('[AI sin salida para criterio clínico]', 150, "</TD></TR><TR><TD colspan=12 class='blanco_left'>");
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
<table>
    <TR>
        <TD COLSPAN=12 class="morado">3. HALLAZGOS RELEVANTES DE EXAMENES Y PROCEDIMIENTOS DIAGNOSTICOS</TD>
    </TR>
    <TR>
        <TD colspan="12" class="blanco_left">
        </TD>
    </TR>
</table>
<table>
    <TR>
        <TD COLSPAN=12 class="morado">4. TRATAMIENTOS Y PROCEDIMIENTOS TERAPEUTICOS REALIZADOS</TD>
    </TR>
    <TR>
        <TD colspan="12" class="blanco_left">
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

            echo wordwrap($planAI, 150, "</TD></TR><TR><TD colspan=12 class='blanco_left'>");

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
        </TD>
    </TR>
</table>
<table>
    <TR>
        <TD class="morado">5. DIAGNOSTICO</TD>
        <TD class="morado">CIE-10</TD>
        <TD class="morado">PRE</TD>
        <TD class="morado">DEF</TD>
    </TR>
    <?php
    foreach ($diagnostico as $dx) {
        $cie10 = $dx['dx_code'] ?? '';
        $detalle = $dx['descripcion'] ?? '';

        echo "<tr>
        <TD CLASS='blanco_left'>" . htmlspecialchars($detalle) . "</TD>
        <TD CLASS='blanco'>" . htmlspecialchars($cie10) . "</TD>
            <TD CLASS='blanco'><BR></TD>
            <TD CLASS='blanco'>X</TD>
          </tr>";
    }
    ?>
</table>
<table>
    <TR>
        <TD class="morado">6.
            TRATAMIENTO RECOMENDADO A SEGUIR EN EL ESTABLECIMIENTO DE SALUD DE MENOR NIVEL DE COMPLEJIDAD
        </TD>
    </TR>
    <TR>
        <TD class="blanco_left"></TD>
    </TR>
</TABLE>
<table>
    <TR>
        <TD class="blanco" COLSPAN=4><?php echo strtoupper($solicitud['doctor']); ?></TD>
        <TD class="blanco" COLSPAN=4><?php echo strtoupper($solicitud['cedula']); ?></TD>
        <TD class="blanco"
            COLSPAN=4><?php echo "<img src='" . htmlspecialchars($solicitud['firma']) . "' alt='Imagen de la firma' style='max-height: 40px;'>";
            ?></TD>
    </TR>
    <TR>
        <TD class="verde" COLSPAN=4>NOMBRE</TD>
        <TD class="verde" COLSPAN=4>COD. MSP. PROF.</TD>
        <TD class="verde" COLSPAN=4>FIRMA</TD>
    </TR>
</TABLE>
<table style='border: none'>
    <TR>
        <TD colspan='6' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                               COLOR='#000000'>SNS-MSP/HCU-form. 053/2021</FONT></B>
        </TD>
        <TD colspan='3' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000'>REFERENCIA - DERIVACIÓN- CONTRAREFERENCIA
                    - REFERENCIA INVERSA</FONT></B>
        </TD>
    </TR>
</TABLE>
<?php
$content = ob_get_clean();
$title = 'Formulario de Referencia';
$header = '';

include $layout;
