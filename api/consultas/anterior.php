<?php
require_once __DIR__ . '/../../bootstrap.php';

$allowedOrigins = [
    'http://cive.ddns.net',
    'https://cive.ddns.net',
    'http://cive.ddns.net:8085',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
    'http://localhost:8085',
    'http://127.0.0.1:8085',
    'https://asistentecive.consulmed.me',
    'https://cive.consulmed.me',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origen no permitido']);
    exit;
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$hcNumber     = $_GET['hcNumber']     ?? null;
$formIdActual = $_GET['form_id']      ?? null;
$procedimientoActual = $_GET['procedimiento'] ?? null;

    if (!$hcNumber) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetro hcNumber requerido'
    ]);
        exit;
    }

// Sacar la "base" del procedimiento, antes del código
// Ej: "SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-001"
// base = "SERVICIOS OFTALMOLOGICOS GENERALES"
$baseProcedimiento = null;
if ($procedimientoActual) {
    $partes = explode(' - ', $procedimientoActual);
    $baseProcedimiento = trim($partes[0]); // "SERVICIOS OFTALMOLOGICOS GENERALES"
    }

try {
    // Construir SQL
    $sql  = "SELECT cd.form_id,
                    cd.hc_number,
                    cd.examen_fisico,
                    cd.plan,
                    cd.fecha
             FROM procedimiento_proyectado AS pp
             LEFT JOIN consulta_data AS cd
                    ON pp.form_id = cd.form_id
             WHERE pp.hc_number = :hcNumber";

    $params = [':hcNumber' => $hcNumber];

    // Filtrar por familia de procedimiento si la tenemos
    if ($baseProcedimiento) {
        $sql .= " AND pp.procedimiento_proyectado LIKE :baseProc";
        $params[':baseProc'] = $baseProcedimiento . '%';

        // Excluir el procedimiento exacto actual (ej. SER-OFT-001)
        $sql .= " AND pp.procedimiento_proyectado <> :procActual";
        $params[':procActual'] = $procedimientoActual;
    }

    // Solo consultas ANTERIORES a la actual, si mandas form_id
    if ($formIdActual) {
        $sql .= " AND pp.form_id < :formIdActual";
        $params[':formIdActual'] = $formIdActual;
    }

    // No queremos consultas sin examen físico
    $sql .= " AND cd.examen_fisico IS NOT NULL
              AND cd.examen_fisico <> ''";

    // Tomar la más reciente dentro de esas
    $sql .= " ORDER BY cd.fecha DESC, cd.form_id DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
    echo json_encode([
        'success' => true,
        'data' => [
            'form_id' => $row['form_id'],
            'hc_number' => $row['hc_number'],
                'examen_fisico' => $row['examen_fisico'],
                'plan'          => $row['plan'],
                'fecha'         => $row['fecha'],
            ]
    ]);
    } else {
        echo json_encode([
            'success' => true,
            'data'    => null,
            'message' => 'No se encontró consulta anterior con examen físico.'
        ]);
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar consulta anterior',
        'error'   => $e->getMessage()
    ]);
}
