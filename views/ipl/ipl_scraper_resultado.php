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

    // Extraer fechas de registro y vigencia
    $fechaRegistro = '';
    $fechaVigencia = '';
    if (preg_match('/Fecha de registro:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchRegistro)) {
        $fechaRegistro = $matchRegistro[1];
    }
    if (preg_match('/Fecha de Vigencia:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchVigencia)) {
        $fechaVigencia = $matchVigencia[1];
    }

    $codigoDerivacion = '';
    if (preg_match('/Código Derivación:\s*([^\n]+)/', $output, $matchCodigo)) {
        $codigoDerivacion = trim($matchCodigo[1]);
    }
    $diagnostico = '';
    if (preg_match('/"diagnostico":\s*([^\n]+)/', $output, $matchDiagnostico)) {
        $diagnostico = trim($matchDiagnostico[1], '", ');
    }

    if ($fechaRegistro || $fechaVigencia) {
        if ($fechaRegistro) echo "<strong>📅 Fecha de Registro:</strong> " . htmlspecialchars($fechaRegistro) . "<br>";
        if ($fechaVigencia) echo "<strong>📅 Fecha de Vigencia:</strong> " . htmlspecialchars($fechaVigencia);
    }

    // Decodificar respuesta del scraper si es posible (simulando un array asociativo)
    $scraperResponse = [
        'codigo_derivacion' => $codigoDerivacion,
        'fecha_registro' => $fechaRegistro,
        'fecha_vigencia' => $fechaVigencia,
        'diagnostico' => $diagnostico
    ];

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

            $fecha_inicio = isset($fechas_realizadas[0]) ? new DateTime($fechas_realizadas[0]) : null;
            $fecha_fin = null;

            // Extraer fecha de vigencia del bloque JSON
            if (isset($partes[0]) && preg_match('/"fecha_vigencia"\s*:\s*"([^"]+)"/', $partes[0], $match)) {
                $fecha_fin = new DateTime($match[1]);
            }

            // Calcular fechas ideales (una por mes sin contar fines de semana ni duplicar fechas reales)
            $fechas_ideales = [];
            if ($fecha_inicio) {
                $iter = clone $fecha_inicio;
                $contador = 0;

                while ($contador < 4) {
                    $fecha_str = $iter->format('Y-m-d');
                    $weekday = $iter->format('N');

                    if ($weekday < 6 && !in_array($fecha_str, array_map(fn($f) => substr($f, 0, 10), $fechas_realizadas))) {
                        $fechas_ideales[] = $fecha_str;
                        $contador++;
                    }

                    $iter->modify('+1 month');
                }
            }

            echo '<div class="alert alert-info mb-3">';
            echo '<strong>Resumen del Planificador IPL:</strong><br>';
            echo '📆 Sesiones realizadas: ' . count($fechas_realizadas) . '<br>';
            echo '🕐 Primera sesión: ' . ($fecha_inicio ? $fecha_inicio->format('d/m/Y') : '-') . '<br>';
            echo '🕐 Vigencia: ' . ($fecha_fin ? $fecha_fin->format('d/m/Y') : '-') . '<br>';
            echo '📌 Sesiones faltantes: ' . count($fechas_ideales) . '<br>';
            if (!empty($fechas_ideales)) {
                echo '🗓 Fechas ideales pendientes:<ul>';
                foreach ($fechas_ideales as $f) {
                    echo '<li>' . IplHelper::formatearFecha($f) . '</li>';
                }
                echo '</ul>';
            }
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
                                            </tr>
                                          </thead>';
            echo '<tbody>';

            // === BLOQUE NUEVO: Generar fechas ideales y asociar sesiones reales secuencialmente ===
            // Asegurar que $fechaRegistro y $fechaVigencia sean objetos DateTime válidos
            if (!empty($fechaRegistro) && !($fechaRegistro instanceof DateTime)) {
                $fechaRegistro = new DateTime($fechaRegistro);
            }
            if (!empty($fechaVigencia) && !($fechaVigencia instanceof DateTime)) {
                $fechaVigencia = new DateTime($fechaVigencia);
            }
            // Generar fechas ideales desde fecha_registro (una por mes, variando el día de manera natural y evitando fines de semana)
            $fechas_ideales_final = [];
            if ($fechaRegistro && $fechaVigencia) {
                $fecha_base = clone $fechaRegistro;
                $dia_base = (int)$fecha_base->format('d');
                $desplazamientos = [0, -6, -3, 2, 4, 6]; // Variaciones naturales desde el día base

                $contador = 1;
                while ($fecha_base <= $fechaVigencia) {
                    $desplazar = $desplazamientos[array_rand($desplazamientos)];
                    $fecha_ideal = DateTime::createFromFormat('Y-m-d', $fecha_base->format('Y-m-01'));
                    $fecha_ideal->modify("+{$dia_base} days")->modify("{$desplazar} days");

                    // Evitar sábado o domingo
                    while ((int)$fecha_ideal->format('N') >= 6) {
                        $fecha_ideal->modify('+1 day');
                    }

                    // Validación estricta: ninguna fecha ideal debe pasar del día exacto de vigencia
                    if ($fecha_ideal > $fechaVigencia) {
                        break;
                    }

                    $fechas_ideales_final[] = [
                        'contador' => $contador++,
                        'fecha' => $fecha_ideal->format('Y-m-d')
                    ];

                    $fecha_base->modify('first day of next month');
                }
            }

            // Clonar grupos para no modificar el original
            $grupos_restantes = $grupos;

            // Emparejar sesiones reales a fechas ideales de forma secuencial
            foreach ($fechas_ideales_final as $sesion) {
                $fechaIdeal = $sesion['fecha'];
                $match = null;

                if (!empty($grupos_restantes)) {
                    $match = array_shift($grupos_restantes); // asignar la siguiente sesión real
                }

                if ($match) {
                    //print_r($match);
                    $formIdCheck = trim($match['form_id']);
                    echo "<!-- Verificando existencia en BD: form_id = '{$formIdCheck}', hc_number = '{$hc_number}' -->";
                    $existeBD = $controller->existeDerivacionEnBD($formIdCheck, $hc_number);
                    echo '<tr class="table-' . IplHelper::claseFilaEstado($match['estado']) . '">';
                    echo '<td>' . $sesion['contador'] . '</td>';
                    echo '<td>' . htmlspecialchars($match['form_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($match['codigo_derivacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($match['procedimiento']) . '</td>';
                    echo '<td>' . htmlspecialchars($match['fecha']) . '</td>';
                    echo '<td>' . IplHelper::formatearFecha($fechaIdeal) . '</td>';
                    echo '<td>' . htmlspecialchars($match['doctor']) . '</td>';
                    echo '<td>' . IplHelper::estadoTexto($match['estado']);

                    // Mostramos confirmación si existe en BD
                    if ($existeBD) {
                        echo ' <span class="badge bg-success">✅ BD</span>';
                    } else {
                        echo " <button class='btn btn-warning btn-sm guardar-derivacion' 
                            data-form-id='{$formIdCheck}' 
                            data-hc='{$hc_number}'
                            data-codigo='{$match['codigo_derivacion']}'
                            data-fecha-registro='{$match['fecha_registro']}'
                            data-fecha-vigencia='{$match['fecha_vigencia']}'
                            data-diagnostico='" . htmlspecialchars($match['diagnostico'], ENT_QUOTES) . "'>
                            ⚠️ Guardar BD</button>";
                    }

                    echo '</td>';
                    echo '</tr>';
                } else {
                    echo '<tr class="table-warning">';
                    echo '<td>' . $sesion['contador'] . '</td>';
                    echo '<td>-</td>';
                    echo '<td></td>';
                    echo '<td>IPL Pendiente</td>';
                    echo '<td>-</td>';
                    echo '<td>' . IplHelper::formatearFecha($fechaIdeal) . '</td>';
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

    include __DIR__ . '/ipl_planificador_lista_data.php';
}

?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.guardar-derivacion').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const formId = this.dataset.formId;
            const hc = this.dataset.hc;
            const codigo = this.dataset.codigo;
            const fechaRegistro = this.dataset.fechaRegistro;
            const fechaVigencia = this.dataset.fechaVigencia;
            const diagnostico = this.dataset.diagnostico;

            fetch('guardar_derivacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `form_id=${formId}&hc_number=${hc}&codigo=${encodeURIComponent(codigo)}&fecha_registro=${encodeURIComponent(fechaRegistro)}&fecha_vigencia=${encodeURIComponent(fechaVigencia)}&diagnostico=${encodeURIComponent(diagnostico)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.outerHTML = "<span class='badge bg-success'>✅ BD</span>";
                } else {
                    alert("Error: " + data.message);
                }
            });
        });
    });
});
</script>

