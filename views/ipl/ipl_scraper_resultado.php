<?php
ob_start();

function renderBotonGuardarDerivacion($item)
{
    ?>
    <button
            class="btn btn-warning btn-sm guardar-derivacion"
            data-form-id="<?= $item['form_id'] ?>"
            data-hc="<?= $item['hc_number'] ?>"
            data-codigo="<?= $item['codigo_derivacion'] ?>"
            data-fecha-registro="<?= $item['fecha_registro'] ?>"
            data-fecha-vigencia="<?= $item['fecha_vigencia'] ?>"
            data-diagnostico="<?= htmlspecialchars($item['diagnostico'], ENT_QUOTES) ?>"
            data-guardar-derivacion="<?= $item['guardar_derivacion'] ?>"
            data-guardar-planificacion="<?= $item['guardar_planificacion'] ?>"
            data-nro-sesion="<?= $item['nro_sesion'] ?>"
            data-fecha-ficticia="<?= htmlspecialchars($item['fecha_ficticia']) ?>"
            data-estado="<?= htmlspecialchars($item['estado'], ENT_QUOTES) ?>"
            data-doctor="<?= htmlspecialchars($item['doctor'], ENT_QUOTES) ?>"
            data-procedimiento="<?= htmlspecialchars($item['procedimiento'], ENT_QUOTES) ?>"
    >
        💾 Guardar
        <?php
        if ($item['guardar_derivacion'] && $item['guardar_planificacion']) {
            echo ' (📄 Derivación + 📆 Planificación)';
        } elseif ($item['guardar_derivacion']) {
            echo ' (📄 Derivación)';
        } elseif ($item['guardar_planificacion']) {
            echo ' (📆 Planificación)';
        }
        ?>
    </button>
    <?php
    return ob_get_clean();
}

?>
<?php
require_once __DIR__ . '/../../bootstrap.php';

use Helpers\IplHelper;
use Controllers\IplPlanificadorController;

$controller = new IplPlanificadorController($pdo);

if (isset($_POST['scrape_derivacion']) && !empty($_POST['form_id_scrape']) && !empty($_POST['hc_number_scrape'])) {
    $form_id = escapeshellarg($_POST['form_id_scrape']);
    $hc_number = escapeshellarg($_POST['hc_number_scrape']);

    $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py $form_id $hc_number";
    $output = shell_exec($command);
    // Mostrar resultado en pantalla
    echo "<div class='box' font-family: monospace;'>";
    echo "<div class='box-header with-border'>";
    echo "<strong>📋 Resultado del Scraper:</strong><br></div>";
    echo "<div class='box-body' style='background: #f8f9fa; border: 1px solid #ccc; padding: 10px; border-radius: 5px;'>";

    // Extraer fechas, código y diagnóstico en un solo array optimizado
    $scraperResponse = [
        'codigo_derivacion' => '',
        'fecha_registro' => '',
        'fecha_vigencia' => '',
        'diagnostico' => '',
        'hc_number' => ''
    ];

    if (preg_match('/Código Derivación:\s*([^\n]+)/', $output, $matchCodigo)) {
        $scraperResponse['codigo_derivacion'] = trim($matchCodigo[1]);
    }
    if (preg_match('/"hc_number":\s*"([^"]+)"/', $output, $matchhcnumber)) {
        $scraperResponse['hc_number'] = trim($matchhcnumber[1]);
    }
    if (preg_match('/Fecha de registro:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchRegistro)) {
        $scraperResponse['fecha_registro'] = $matchRegistro[1];
    }
    if (preg_match('/Fecha de Vigencia:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchVigencia)) {
        $scraperResponse['fecha_vigencia'] = $matchVigencia[1];
    }
    if (preg_match('/"diagnostico":\s*([^\n]+)/', $output, $matchDiagnostico)) {
        $scraperResponse['diagnostico'] = trim($matchDiagnostico[1], '", ');
    }

    $form_id = trim($_POST['form_id_scrape'], "'");
    $hc_number = trim($_POST['hc_number_scrape'], "'");
    $controller->verificarDerivacion($form_id, $hc_number, $scraperResponse);

    $partes = explode("📋 Procedimientos proyectados:", $output);
    //echo '<pre>';
    //print_r($partes);
    //echo '</pre>';

    if (count($partes) > 1) {
        $lineas = array_filter(array_map('trim', explode("\n", trim($partes[1]))));
        $grupos = [];

        for ($i = 0; $i < count($lineas); $i += 5) {
            $idLinea = $lineas[$i] ?? '';
            $procedimiento = $lineas[$i + 1] ?? '';
            $fecha = $lineas[$i + 2] ?? '';
            $doctor = $lineas[$i + 3] ?? '';
            $estado = $lineas[$i + 4] ?? '';
            $color = str_contains($estado, '✅') ? 'success' : 'danger';

            // Solo incluimos procedimientos que contienen "IPL"
            if (stripos($procedimiento, 'IPL') !== false) {
                $grupos[] = [
                    'form_id' => trim($idLinea),
                    'procedimiento' => trim($procedimiento),
                    'fecha' => trim($fecha),
                    'doctor' => trim($doctor),
                    'estado' => trim($estado),
                    'color' => $color,
                    'codigo_derivacion' => $scraperResponse['codigo_derivacion'] ?? '',
                    'hc_number' => trim($scraperResponse['hc_number']) ?? '',
                    'fecha_registro' => $scraperResponse['fecha_registro'] ?? '',
                    'fecha_vigencia' => $scraperResponse['fecha_vigencia'] ?? '',
                    'diagnostico' => $scraperResponse['diagnostico'] ?? ''
                ];
                //echo '<pre>';
                //print_r($grupos);
                //echo '</pre>';
            }
        }

        // Mostrar los datos en una tabla
        if (!empty($grupos)) {
            echo '<div class="box shadow border border-primary p-3 mb-4">';
            echo '<h5 class="text-primary">📋 Procedimientos IPL proyectados:</h5>';


            // === BLOQUE NUEVO: Resumen del Planificador IPL ===
            // Calcular fechas reales de IPL y vigencia
            $fechas_realizadas = array_filter(array_map(function ($item) {
                return str_contains($item['procedimiento'], 'IPL') ? $item['fecha'] : null;
            }, $grupos));
            $fechas_realizadas = array_values(array_filter($fechas_realizadas));
            sort($fechas_realizadas);

            // Asignación segura para las variables faltantes
            $fechaRegistro = $scraperResponse['fecha_registro'] ?? null;
            $fechaVigencia = $scraperResponse['fecha_vigencia'] ?? null;

            $fecha_inicio = isset($fechas_realizadas[0]) ? new DateTime($fechas_realizadas[0]) : null;
            $fecha_fin = null;

            // Extraer fecha de vigencia del bloque JSON
            if (isset($partes[0]) && preg_match('/"fecha_vigencia"\s*:\s*"([^"]+)"/', $partes[0], $match)) {
                $fecha_fin = new DateTime($match[1]);
            }

            $fecha_inicio = ($fechaRegistro instanceof DateTime) ? clone $fechaRegistro : new DateTime($fechaRegistro);
            $fechaRegistro = IplHelper::toDateTime($fechaRegistro);
            $fechaVigencia = IplHelper::toDateTime($fechaVigencia);
            $fechas_ideales = $controller->generarFechasIdeales($fecha_inicio, $fechaVigencia);

            echo '<div class="alert alert-info mb-3">';
            echo '<strong>Resumen del Planificador IPL:</strong><br>';
            echo '📆 Sesiones realizadas: ' . count($fechas_realizadas) . '<br>';
            echo '📅 Fecha de Registro: ' . ($fecha_inicio ? $fecha_inicio->format('d/m/Y') : '-') . '<br>';
            echo '📅 Fecha de Vigencia: ' . ($fecha_fin ? $fecha_fin->format('d/m/Y') : '-') . '<br>';
            $totalSesiones = count($fechas_ideales);
            $faltantes = IplHelper::calcularSesionesFaltantes($fechas_ideales, $fechas_realizadas);
            echo '📌 Sesiones faltantes: ' . $faltantes . '<br>';

            echo '</div>';

            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped table-sm">';
            echo '<thead class="bg-primary text-white">
                    <tr>
                        <th>#</th>
                        <th>Cod. Der.</th>
                        <th>Form ID</th>
                        <th>Procedimiento</th>
                        <th>Fecha Real</th>
                        <th>Fecha Ideal</th>
                        <th>Doctor</th>
                        <th>Estado</th>
                        <th>🖨️ PDF</th>
                    </tr>
                  </thead>';
            echo '<tbody>';

            // === BLOQUE NUEVO: Generar fechas ideales y asociar sesiones reales secuencialmente ===
            // Asegurar que $fechaRegistro y $fechaVigencia sean objetos DateTime válidos
            $fechaRegistro = IplHelper::toDateTime($fechaRegistro);
            $fechaVigencia = IplHelper::toDateTime($fechaVigencia);

            $fecha_inicio = ($fechaRegistro instanceof DateTime) ? clone $fechaRegistro : new DateTime($fechaRegistro);
            $fechas_ideales = $controller->generarFechasIdeales($fecha_inicio, $fechaVigencia);
            // Clonar grupos para no modificar el original
            $grupos_restantes = $grupos;

            // Emparejar sesiones reales a fechas ideales de forma secuencial
            foreach ($fechas_ideales as $index => $fechaIdeal) {
                $contador = $index + 1;
                $match = null;

                if (!empty($grupos_restantes)) {
                    $match = array_shift($grupos_restantes); // asignar la siguiente sesión real
                }

                if ($match) {
                    //print_r($match);
                    $formIdCheck = trim($match['form_id']);
                    echo "<!-- Verificando existencia en BD: form_id = '{$formIdCheck}', hc_number = '{$hc_number}' -->";
                    $existeBD = $controller->existePlanificacionYDerivacion($formIdCheck, $hc_number);
                    //print_r($existeBD);
                    echo '<tr class="table-' . IplHelper::claseFilaEstado($match['estado']) . '">';
                    echo '<td>' . $contador . '</td>';
                    echo '<td>' . htmlspecialchars($match['form_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($match['codigo_derivacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($match['procedimiento']) . '</td>';
                    echo '<td>' . IplHelper::formatearFecha($match['fecha']) . '</td>';
                    $fechaIdealGuardada = $controller->obtenerFechaIdeal($formIdCheck, $hc_number);
                    $fechaFicticia = $fechaIdealGuardada ?: (is_array($fechaIdeal) && isset($fechaIdeal['fecha']) ? $fechaIdeal['fecha'] : $fechaIdeal);
                    echo '<td>' . IplHelper::formatearFecha($fechaFicticia) . '</td>';
                    echo '<td>' . htmlspecialchars($match['doctor']) . '</td>';
                    echo '<td>' . IplHelper::estadoTexto($match['estado']) . '</td>';
                    echo '<td><button class="btn btn-outline-primary btn-sm" onclick="imprimirPDF(\'' . addslashes(htmlspecialchars($match['form_id'], ENT_QUOTES)) . '\', \'' . addslashes(htmlspecialchars($match['hc_number'], ENT_QUOTES)) . '\')">🖨️ Print</button></td>';
                    $item = [
                        'form_id' => $formIdCheck,
                        'hc_number' => $hc_number,
                        'codigo_derivacion' => $match['codigo_derivacion'],
                        'fecha_registro' => $match['fecha_registro'],
                        'fecha_vigencia' => $match['fecha_vigencia'],
                        'diagnostico' => $match['diagnostico'],
                        'guardar_derivacion' => empty($existeBD['derivacion']) ? "1" : "0",
                        'guardar_planificacion' => empty($existeBD['planificacion']) ? "1" : "0",
                        'nro_sesion' => $contador,
                        'fecha_ficticia' => $fechaFicticia,
                        'estado' => $match['estado'],
                        'doctor' => $match['doctor'],
                        'procedimiento' => $match['procedimiento'],
                    ];
                    if ($item['guardar_derivacion'] || $item['guardar_planificacion']) {
                        echo renderBotonGuardarDerivacion($item);
                    }

                    echo '</td>';
                    echo '</tr>';
                } else {
                    echo '<tr class="table-warning">';
                    echo '<td>' . $contador . '</td>';
                    echo '<td>-</td>';
                    echo '<td></td>';
                    echo '<td>IPL Pendiente</td>';
                    echo '<td>-</td>';
                    echo '<td>' . IplHelper::formatearFecha(is_array($fechaIdeal) && isset($fechaIdeal['fecha']) ? $fechaIdeal['fecha'] : $fechaIdeal) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>⚠️ Pendiente</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table></div></div>';
        }
    }

    //echo "<h2 class='mt-3'>📋 Procedimientos partes:</h2>";

    //print_r($partes);

    // Generar y mostrar fechas propuestas de sesiones IPL si hay fechas válidas

    //include __DIR__ . '/ipl_planificador_lista_data.php';
}

?>
<script>
    function imprimirPDF(form_id, hc_number) {
        window.open('/public/ajax/generate_protocolo_pdf.php?form_id=' + form_id + '&hc_number=' + hc_number, '_blank');
    }

    // Delegación de eventos para .guardar-derivacion
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('guardar-derivacion')) {
                e.preventDefault();
                const boton = e.target;

                const formId = boton.dataset.formId;
                const hc = boton.dataset.hc;
                const codigo = boton.dataset.codigo;
                const fechaRegistro = boton.dataset.fechaRegistro;
                const fechaVigencia = boton.dataset.fechaVigencia;
                const diagnostico = boton.dataset.diagnostico;
                const guardarDerivacion = boton.dataset.guardarDerivacion;
                const guardarPlanificacion = boton.dataset.guardarPlanificacion;
                const nroSesion = boton.dataset.nroSesion;
                const fechaFicticia = boton.dataset.fechaFicticia;
                const estado = boton.dataset.estado;
                const doctor = boton.dataset.doctor;
                const procedimiento = boton.dataset.procedimiento;

                // Opcional: Log para depuración
                console.log("Guardando sesión:", nroSesion, "para HC:", hc, "con Form ID:", formId);

                fetch('guardar_derivacion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `form_id=${formId}&hc_number=${hc}&codigo=${encodeURIComponent(codigo)}&fecha_registro=${encodeURIComponent(fechaRegistro)}&fecha_vigencia=${encodeURIComponent(fechaVigencia)}&diagnostico=${encodeURIComponent(diagnostico)}&guardar_derivacion=${guardarDerivacion}&guardar_planificacion=${guardarPlanificacion}&nro_sesion=${nroSesion}&fecha_ficticia=${encodeURIComponent(fechaFicticia)}&estado=${encodeURIComponent(estado)}&doctor=${encodeURIComponent(doctor)}&procedimiento=${encodeURIComponent(procedimiento)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            boton.outerHTML = "<span class='badge bg-success'>✅ BD</span>";
                        } else {
                            alert("Error: " + data.message);
                        }
                    })
                    .catch(error => {
                        console.error("Error al parsear JSON:", error);
                    });
            }
        });
    });
</script>
