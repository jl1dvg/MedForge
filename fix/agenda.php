<?php
if (php_sapi_name() !== 'cli' && (!isset($_GET['start']) && !isset($_GET['end']) && (!isset($_GET['fecha']) || $_GET['fecha'] === ''))) {
    // Mostrar interfaz web si no hay ?start y ?end y no es desde CLI
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Consulta Agenda</title>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    </head>
    <body class="p-4">
    <h4>Consultar agenda por rango de fechas</h4>
    <div class="form-group">
        <label for="rangoFechas">Rango de fechas:</label>
        <input type="text" id="rangoFechas" class="form-control"/>
    </div>
    <button id="consultar" class="btn btn-primary">Consultar y Guardar</button>

    <hr>
    <h5>Resultados</h5>
    <pre id="output" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>

    <script>
        $(function () {
            $('#rangoFechas').daterangepicker({
                locale: {format: 'YYYY-MM-DD'},
                startDate: moment().startOf('day'),
                endDate: moment().startOf('day')
            });

            $('#consultar').click(function () {
                const [inicio, fin] = $('#rangoFechas').val().split(' - ');
                let fechaActual = moment(inicio);
                const fechaFinal = moment(fin);
                $('#output').html("Procesando...\n");

                function consultarFecha(fecha) {
                    $.get('agenda.php?fecha=' + fecha, function (data) {
                        $('#output').append(`✅ ${fecha}: ${JSON.stringify(data)}\n`);
                        siguienteFecha();
                    }).fail(function (jqXHR, textStatus, errorThrown) {
                        const errorMsg = jqXHR.responseText || errorThrown || textStatus;
                        $('#output').append(`❌ Error en fecha ${fecha}: ${errorMsg}\n`);
                        siguienteFecha();
                    });
                }

                function siguienteFecha() {
                    fechaActual.add(1, 'days');
                    if (fechaActual.isSameOrBefore(fechaFinal)) {
                        consultarFecha(fechaActual.format('YYYY-MM-DD'));
                    } else {
                        $('#output').append("\n🎉 Finalizado.");
                    }
                }

                consultarFecha(fechaActual.format('YYYY-MM-DD'));
            });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}
ini_set('display_errors', 1);
// Soporte para pasar argumentos desde CLI como: php agenda.php start=YYYY-MM-DD end=YYYY-MM-DD
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $_GET[$key] = $value;
        }
    }
}
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$fechaInicio = $_GET['start'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
$fechaFin = $_GET['end'] ?? $fechaInicio;

$fechas = [];
$start = strtotime($fechaInicio);
$end = strtotime($fechaFin);
while ($start <= $end) {
    $fechas[] = date('Y-m-d', $start);
    $start = strtotime('+1 day', $start);
}

$resultadoFinal = [];

foreach ($fechas as $fecha) {
    if (!file_exists('simple_html_dom.php')) {
        die('No se pudo cargar simple_html_dom.php');
    }
    require_once 'simple_html_dom.php';

    $loginUrl = 'http://cive.ddns.net:8085/site/login';
    $cookieFile = __DIR__ . '/cookie.txt';

    // 1. Obtener el CSRF token
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => "Mozilla/5.0",
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $loginPage = curl_exec($ch);
    if (!$loginPage) {
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        die("Error al cargar la página de login:\n\nCurl error: $error\n\nCurl info:\n" . print_r($info, true));
    }
    if (!$loginPage) {
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        die("Error al cargar la página de login:\n\nCurl error: $error\n\nCurl info:\n" . print_r($info, true));
    }
    curl_close($ch);

    // Extraer el token CSRF desde el HTML
    $html = str_get_html($loginPage);
    $csrfToken = '';
    $csrfInput = $html->find('input[name=_csrf-frontend]', 0);
    if ($csrfInput) {
        $csrfToken = $csrfInput->value;
    } else {
        die('No se encontró el token CSRF');
    }

    // 2. Enviar POST con usuario, contraseña y token CSRF
    $postFields = http_build_query([
        '_csrf-frontend' => $csrfToken,
        'LoginForm[username]' => 'jdevera',
        'LoginForm[password]' => '0925619736',
        'LoginForm[rememberMe]' => '1'
    ]);

    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $responseLogin = curl_exec($ch);
    curl_close($ch);

    // 3. Acceder a la página protegida ya logueado
    $url = "http://cive.ddns.net:8085/documentacion/doc-solicitud-procedimientos/index-doctor?DocSolicitudProcedimientosDoctorSearch%5BfechaBusqueda%5D={$fecha}&DocSolicitudProcedimientosDoctorSearch%5BtipoProcedimiento%5D=&DocSolicitudProcedimientosDoctorSearch%5Bsede%5D=&DocSolicitudProcedimientosDoctorSearch%5Bpagado%5D=&DocSolicitudProcedimientosDoctorSearch%5BconsultaPrevia%5D=&DocSolicitudProcedimientosDoctorSearch%5Bid%5D=&DocSolicitudProcedimientosDoctorSearch%5Bdoctor%5D=&DocSolicitudProcedimientosDoctorSearch%5Bhora%5D=&DocSolicitudProcedimientosDoctorSearch%5Bpaciente%5D=&DocSolicitudProcedimientosDoctorSearch%5BpacienteIdentificacion%5D=&DocSolicitudProcedimientosDoctorSearch%5Bciudad%5D=&DocSolicitudProcedimientosDoctorSearch%5BafiliacionId%5D=&DocSolicitudProcedimientosDoctorSearch%5Btelefono%5D=&DocSolicitudProcedimientosDoctorSearch%5BprocedimientoId%5D=&DocSolicitudProcedimientosDoctorSearch%5BanestesiaVal%5D=&DocSolicitudProcedimientosDoctorSearch%5BestadoAgenda%5D=&DocSolicitudProcedimientosDoctorSearch%5Bfecha_vigencia%5D=&DocSolicitudProcedimientosDoctorSearch%5Bnota_previa_doctor%5D=&_tog3213ef16=all";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $response = curl_exec($ch);
    if (!$response) {
        die('Error al cargar la página de agenda.');
    }
    curl_close($ch);

    // 4. Parsear la tabla de resultados
    $html = null;
    if (!$response) {
        file_put_contents("debug_response_empty.html", $response);
        die("Respuesta vacía del servidor.");
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($response);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $resultado = [];
    $rows = $xpath->query('//table//tbody//tr');
    foreach ($rows as $row) {
        $cols = $xpath->query('./td', $row);
        if ($cols->length >= 17) {
            $nombre = trim($cols->item(8)->textContent);
            $partes = preg_split('/\s+/', $nombre);
            // Validación segura para la fecha principal
            $timestamp = strtotime($fecha);
            $fechaFormateada = $timestamp ? date('Y-m-d', $timestamp) : null;
            // Validación segura para fechaCaducidad
            $fechaCaducidadTexto = trim($cols->item(16)->textContent);
            $timestampCaducidad = strtotime($fechaCaducidadTexto);
            $fechaCaducidad = ($timestampCaducidad && $fechaCaducidadTexto !== '(no definido)') ? date('Y-m-d', $timestampCaducidad) : null;
            $resultado[] = [
                'form_id' => trim($cols->item(5)->textContent),
                'hcNumber' => trim($cols->item(9)->textContent),
                'nombre_completo' => $nombre,
                'hora' => trim($cols->item(7)->textContent),
                'doctor' => trim($cols->item(6)->textContent),
                'afiliacion' => trim($cols->item(11)->textContent),
                'procedimiento_proyectado' => trim($cols->item(13)->textContent),
                // 'anestesia' => trim($cols->item(14)->textContent), // Eliminada según instrucción
                'estado' => trim($cols->item(15)->textContent),
                'fecha' => $fechaFormateada,
                'fechaCaducidad' => $fechaCaducidad,
                'fname' => $partes[0] ?? '',
                'mname' => $partes[1] ?? '',
                'lname' => $partes[2] ?? '',
                'lname2' => isset($partes[3]) ? implode(' ', array_slice($partes, 3)) : '',
            ];
            error_log("🧪 Parseado: " . json_encode($resultado[count($resultado) - 1]));
        }
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    // echo json_encode($resultado);

    // Quitar campos con valor null antes de enviar a la API
    function quitarCamposNulos($array) {
        return array_filter($array, function ($v) {
            return $v !== null;
        });
    }
    $resultado = array_map('quitarCamposNulos', $resultado);

    // Enviar datos a la API que guarda en la base de datos
    $apiUrl = 'https://asistentecive.consulmed.me/api/proyecciones/guardar.php'; // Ajusta a tu URL real
    error_log("📦 Enviando payload con " . count($resultado) . " registros: " . json_encode($resultado));
    $payload = json_encode($resultado);

    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    file_put_contents(__DIR__ . '/debug_payload.json', $payload);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $apiResponse = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $httpCode >= 400) {
        $err = curl_error($ch) ?: "HTTP $httpCode - $apiResponse";
        file_put_contents("error_guardar_api.log", $err);
        die("❌ Error al guardar en API: " . $err);
    }
    curl_close($ch);

    $resultadoFinal = array_merge($resultadoFinal, $resultado);
}

echo json_encode([
    'rango' => [$fechaInicio, $fechaFin],
    'total' => count($resultadoFinal),
    'detalles' => $resultadoFinal
]);
// Fin de ejecución manual o por cron

if (isset($_GET['auto']) && $_GET['auto'] === '1') {
    $hoy = date('Y-m-d');
    $hasta = date('Y-m-d', strtotime('+15 days'));

    $cmd = "php " . __FILE__ . " start=$hoy end=$hasta > /dev/null 2>&1 &";
    shell_exec($cmd);

    // Registro para verificar que se disparó correctamente
    $logFile = __DIR__ . '/log_auto.txt';
    $mensaje = "✅ agenda.php fue ejecutado automáticamente a las " . date('Y-m-d H:i:s') . " con rango $hoy → $hasta\n";
    file_put_contents($logFile, $mensaje, FILE_APPEND);
}
