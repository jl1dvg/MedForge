<?php
ob_start();
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\SolicitudController;

$hc_number = $_POST['hc_number'] ?? $_GET['hc_number'] ?? 'NO_HC';
$form_id = $_POST['form_id'] ?? $_GET['form_id'] ?? 'NO_FORM';

$controller = new SolicitudController($pdo);

// PRIMER INTENTO: obtener datos
$data = $controller->obtenerDatosParaVista($hc_number, $form_id);
$row = $data['derivacion'];
$solicitud = $data['solicitud'];
$paciente = $data['paciente'];
$diagnostico = $data['diagnostico'];

if (!empty($row)) {
    require_once 'components/prefactura_detalle.php';
    return;
}

// SI NO EXISTEN, ejecutamos el scrape
if (!empty($form_id) && !empty($hc_number)) {
    $form_id_esc = escapeshellarg($form_id);
    $hc_number_esc = escapeshellarg($hc_number);

    $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py $form_id_esc $hc_number_esc";
    $output = shell_exec($command);
    file_put_contents(__DIR__ . '/scraping_debug.log', $output ?? 'NO OUTPUT');
    if (!empty($output)) {
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

        if ($fechaRegistro || $fechaVigencia) {
            if ($fechaRegistro) echo "<strong>📅 Fecha de Registro:</strong> " . htmlspecialchars($fechaRegistro) . "<br>";
            if ($fechaVigencia) echo "<strong>📅 Fecha de Vigencia:</strong> " . htmlspecialchars($fechaVigencia);
        }

        // Decodificar respuesta del scraper si es posible (simulando un array asociativo)
        $scraperResponse = [
            'fecha_registro' => $fechaRegistro,
            'fecha_vigencia' => $fechaVigencia
        ];

        $partes = explode("📋 Procedimientos proyectados:", $output);

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
                        'color' => $color
                    ];
                }
            }

            // Mostrar los datos en una tabla
        }
        //echo "<h2 class='mt-3'>📋 Procedimientos partes:</h2>";

        if (count($partes) <= 1) {
            echo "<div class='alert alert-danger'>⚠️ Scraping ejecutado pero no se detectaron procedimientos.</div>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }

        //print_r($partes);

        // Generar y mostrar fechas propuestas de sesiones IPL si hay fechas válidas
    } else {
        echo "<p style='color:red;'>❌ El script Python no devolvió ninguna derivación.</p>";
    }
    // 🔁 Reconsultar datos tras scraping exitoso
    $data = $controller->obtenerDatosParaVista($hc_number, $form_id);
    $row = $data['derivacion'];
    $solicitud = $data['solicitud'];
    $paciente = $data['paciente'];
    $diagnostico = $data['diagnostico'];
    // Mostrar el detalle de prefactura después del scraping
    require_once 'components/prefactura_detalle.php';
    return;
}
?>