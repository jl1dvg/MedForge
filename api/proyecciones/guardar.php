<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

try {
    $logDir = __DIR__ . '/../../storage/logs';
    $logFile = $logDir . '/index_admisiones_sync.log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    $log = function (string $msg) use ($logFile): void {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        file_put_contents($logFile, $line, FILE_APPEND);
    };

    $rawInput = file_get_contents('php://input');
    $log("RAW INPUT: " . $rawInput);
    error_log("🧪 Contenido bruto recibido: " . $rawInput);
    $data = json_decode($rawInput, true);
    $normalizeDateValue = function (?string $value): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $invalidValues = [
            '0000-00-00', '00-00-0000', 'N/A', 'NA', 'null', 'NULL', '-', '—', '(no definido)', 'NO DEFINIDO',
        ];
        if (in_array($value, $invalidValues, true)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('d-m-Y', $value);
            return $date ? $date->format('Y-m-d') : null;
        }

        return null;
    };

    // Asignar "estado" a "AGENDADO" solo si el form_id no existe en la base de datos
    foreach ($data as &$item) {
        if (!is_array($item)) {
            continue;
        }

        foreach (['fecha', 'fechaCaducidad', 'fecha_caducidad', 'fecha_nacimiento', 'fecha_nac'] as $dateKey) {
            if (!array_key_exists($dateKey, $item)) {
                continue;
            }

            if (!is_string($item[$dateKey])) {
                continue;
            }

            $normalized = $normalizeDateValue($item[$dateKey]);
            if ($normalized === null) {
                unset($item[$dateKey]);
                continue;
            }

            $item[$dateKey] = $normalized;
        }

        if (!isset($item['form_id']) || empty($item['form_id'])) {
            continue;
        }

        // Verificar si el form_id ya existe en la base de datos
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM procedimiento_proyectado WHERE form_id = ?");
        $stmtCheck->execute([$item['form_id']]);
        $formExiste = $stmtCheck->fetchColumn() > 0;

        if (!$formExiste) {
            $item['estado'] = 'AGENDADO';
        }
    }
    unset($item);

    if ($data === null) {
        error_log("❌ JSON mal formado o vacío: " . $rawInput);
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "JSON mal formado o vacío"]);
        exit;
    }

    foreach ($data as $index => $item) {
        if (
            !is_array($item) ||
            !array_key_exists('hcNumber', $item) ||
            !array_key_exists('form_id', $item) ||
            !array_key_exists('procedimiento_proyectado', $item) ||
            empty($item['hcNumber']) ||
            empty($item['form_id']) ||
            empty($item['procedimiento_proyectado'])
        ) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Datos faltantes o incompletos en el índice $index: " .
                    (!array_key_exists('hcNumber', $item) || empty($item['hcNumber']) ? "hcNumber " : "") .
                    (!array_key_exists('form_id', $item) || empty($item['form_id']) ? "form_id " : "") .
                    (!array_key_exists('procedimiento_proyectado', $item) || empty($item['procedimiento_proyectado']) ? "procedimiento_proyectado" : "")
            ]);
            exit;
        }
    }

    $controller = new GuardarProyeccionController($pdo);
    $respuestas = [];
    $erroresBatch = [];

    foreach ($data as $index => $item) {
        try {
            if (count(array_filter($item, function ($v) {
                    return $v === null;
                })) > 0) {
                throw new Exception("Parámetros con valor nulo detectados en el índice $index: " . json_encode($item));
            }
            $respuesta = $controller->guardar($item);
            if (!isset($respuesta['success']) || $respuesta['success'] === false) {
                error_log("❌ Error en el índice $index al guardar: " . print_r($respuesta, true));
            }
            $respuestas[] = [
                'index' => $index,
                'success' => $respuesta['success'],
                'message' => $respuesta['message'],
                'id' => $respuesta['id'] ?? $item['form_id'],
                'form_id' => $respuesta['form_id'] ?? $item['form_id'],
                'afiliacion' => $respuesta['afiliacion'] ?? ($item['afiliacion'] ?? null),
                'estado' => $respuesta['estado'] ?? null,
                'hc_number' => $respuesta['hc_number'] ?? ($item['hcNumber'] ?? null),
                'visita_id' => $respuesta['visita_id'] ?? null,
            ];
        } catch (Throwable $e) {
            $formId = $item['form_id'] ?? null;
            $hcNumber = $item['hcNumber'] ?? null;
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => $formId,
                'hcNumber' => $hcNumber,
                'message' => $e->getMessage(),
            ];
            $log("ERROR index={$index} form_id={$formId} hcNumber={$hcNumber} message=" . $e->getMessage());
            $log("PAYLOAD index={$index}: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $log("FECHAS index={$index}: " . json_encode([
                'fecha' => $item['fecha'] ?? null,
                'fechaCaducidad' => $item['fechaCaducidad'] ?? null,
                'fecha_caducidad' => $item['fecha_caducidad'] ?? null,
                'fecha_nacimiento' => $item['fecha_nacimiento'] ?? null,
                'fecha_nac' => $item['fecha_nac'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    if (!empty($erroresBatch)) {
        http_response_code(207);
        echo json_encode([
            "success" => false,
            "detalles" => $respuestas,
            "errores" => $erroresBatch,
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "detalles" => $respuestas
    ]);
    exit;

} catch (Throwable $e) {
    error_log("🛑 Excepción atrapada en guardar.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error interno: " . $e->getMessage()
    ]);
    exit;
}
