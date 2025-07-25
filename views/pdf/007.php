<?php
if (!function_exists('generateEnfermedadProblemaActual')) {
    function generateEnfermedadProblemaActual($examenFisico)
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$apiKey) {
            die('API key is missing. Please set your OpenAI API key.');
        }

        // Definir el prompt mejorado
        $prompt = "
    Examen físico oftalmológico: $examenFisico

Redacta los hallazgos del examen físico de manera profesional, clara y sintetizada. Sigue este esquema y considera las siguientes instrucciones:

1. Combina el Motivo de consulta y enfermedad actual en una sola frase concisa que describa de manera específica la razón de la consulta y la situación actual del paciente. Evita frases introductorias como 'Motivo de consulta:' o 'Enfermedad actual:'.
2. Biomicroscopia: Presenta los hallazgos separados por ojo con las siglas OD y OI exclusivamente. Si no se menciona un ojo, omítelo. Usa frases completas y bien estructuradas.
3. Fondo de Ojo: Incluye únicamente si hay detalles reportados. Si no se mencionan hallazgos, no lo incluyas.
4. PIO: Si está disponible, escribe la presión intraocular en el formato OD/OI (por ejemplo, 18/18.5). Si no está reportada, omítela.

Instrucciones adicionales:
- Usa mayúsculas y minúsculas correctamente; solo usa siglas para OD, OI y PIO.
- No incluyas secciones vacías ni detalles no reportados.
- Sintetiza la información eliminando redundancias y enfocándote en lo relevante.
- Evita líneas separadas para frases importantes; presenta la información de forma continua y bien organizada.
- No inventes datos; si algo no está claro, simplemente no lo incluyas.

Ejemplo de formato esperado:
[Frase que combine el motivo de consulta y la enfermedad actual.]
Biomicroscopia: OD: [detalles]. OI: [detalles]. 
Fondo de Ojo: OD: [detalles]. OI: [detalles]. 
PIO: [valor].

Utiliza este esquema para el análisis.
";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un médico oftalmólogo que está redactando una referencia detallada para un paciente que necesita cirugía.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 200
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true // Capturar errores HTTP
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents('https://api.openai.com/v1/chat/completions', false, $context);

        if ($response === FALSE) {
            $error = error_get_last();
            die('Error occurred: ' . $error['message']);
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            die('API Error: ' . $responseData['error']['message']);
        }

        return $responseData['choices'][0]['message']['content'];
    }
}
if (!function_exists('generatePlanTratamiento')) {
    function generatePlanTratamiento($plan, $insurance)
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$apiKey) {
            die('API key is missing. Please set your OpenAI API key.');
        }

        $prompt = "
Plan de tratamiento basado en la evaluación oftalmológica: $plan

Redacta un plan de tratamiento breve, claro y profesional, respetando el siguiente formato y estilo:

1. **Procedimientos:** Enumera exclusivamente los procedimientos quirúrgicos necesarios. Usa frases directas y justificaciones breves si es relevante y evita colocar fechas.
2. **Exámenes prequirúrgicos y valoración cardiológica:** Incluye esta sección con el siguiente contexto: 'Se solicita a $insurance autorización para valoración y tratamiento integral en cardiología y electrocardiograma.'

Instrucciones adicionales:
- Usa mayúsculas y minúsculas correctamente tipo oración. Esto significa que solo las iniciales de los nombres propios y términos específicos deben estar en mayúscula. No escribas todo en mayúsculas.
- Presenta la información de manera directa y estructurada en listas o frases cortas, sin introducir explicaciones extensas ni repeticiones.
- Evita incluir secciones vacías o inventar información; solo menciona datos presentes en el plan proporcionado.
- Omite encabezados si no hay contenido relevante en esa sección.

Ejemplo de formato esperado:
[Procedimiento 1].[Procedimiento 2].

Exámenes prequirúrgicos y valoración cardiológica:
- Se solicita a [aseguradora] autorización para valoración y tratamiento integral en cardiología y electrocardiograma.
";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un médico oftalmólogo redactando un plan de tratamiento profesional basado en un análisis clínico.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 300
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true // Capturar errores HTTP
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents('https://api.openai.com/v1/chat/completions', false, $context);

        if ($response === FALSE) {
            $error = error_get_last();
            die('Error occurred: ' . $error['message']);
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            die('API Error: ' . $responseData['error']['message']);
        }

        return $responseData['choices'][0]['message']['content'];
    }
}
?>
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
        <td colspan='15' height='27' class='blanco'><?= htmlspecialchars($paciente['afiliacion']) ?></td>
        <td colspan='6' class='blanco'>&nbsp;</td>
        <td colspan='18' class='blanco'>CIVE</td>
        <td colspan='18' class='blanco'><?= htmlspecialchars($paciente['hc_number']) ?></td>
        <td colspan='14' class='blanco' style='border-right: none'><?= htmlspecialchars($paciente['hc_number']) ?></td>
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
        <td colspan='15' height='27' class='blanco'><?= htmlspecialchars($paciente['lname']) ?></td>
        <td colspan='13' class='blanco'><?= htmlspecialchars($paciente['lname2']) ?></td>
        <td colspan='13' class='blanco'><?= htmlspecialchars($paciente['fname']) ?></td>
        <td colspan='10' class='blanco'><?= htmlspecialchars($paciente['mname']) ?></td>
        <td colspan='3' class='blanco'><?= htmlspecialchars($paciente['sexo']) ?></td>
        <td colspan='6' class='blanco'><?= htmlspecialchars($paciente['fecha_nacimiento']) ?></td>
        <td colspan='3' class='blanco'><?php echo $edadPaciente; ?></td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco' style='border-right: none'>&nbsp;</td>
    </tr>
</TABLE>
<table>
    <colgroup>
        <col class="xl76" span="71">
    </colgroup>
    <tr>
        <td colspan="71" class="morado">B. CUADRO CLÍNICO DE INTERCONSULTA</td>
    </tr>
    <tr>
        <td colspan="71" class="blanco_left"><?php
            //$reason = $motivoConsulta . ' ' . $enfermedadActual;
            echo "</td>
    </tr>
    <tr>
        <td colspan=\"71\" class=\"blanco_left\">"; ?></td>
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
            //$examenAI = generateEnfermedadProblemaActual($examenFisico);
            //echo wordwrap($examenAI, 150, "</TD></TR><TR><TD class='blanco_left'>");
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
            //echo wordwrap($plan, 140, "</TD></TR><TR><TD class='blanco_left'>");
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
            $eye = $solicitud['ojo'];

            if ($eye == 'D') {
                $eye = 'ojo derecho.';
            } elseif ($eye == 'I') {
                $eye = 'ojo izquierdo';
            }
            //$planAI = $nombre_procedimiento . ' en ' . $eye . '. Se solicita a' . $insurance . ' autorización para realización de exámenes prequirúrgicos, valoración y tratamiento integral en cardiología y electrocardiograma.';
            //echo wordwrap($planAI, 150, "</TD></TR><TR><TD colspan=71 class='blanco_left'>");
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
        <td colspan="8" class="blanco"><?php echo htmlspecialchars($solicitud['created_at']); ?></td>
        <td colspan="7" class="blanco"><?php //echo htmlspecialchars($createdAtTime); ?></td>
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
            style="height: 40px"><?php //echo htmlspecialchars($cirujano_data['cedula']); ?></td>
        <td colspan="26" class="blanco">&nbsp;</td>
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
    ]
</TABLE>
