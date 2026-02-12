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

try {
    $logDir = __DIR__ . '/../../storage/logs';
    $logFile = $logDir . '/index_admisiones_sync.log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    $log = function (string $msg) use ($logFile): void {
        $line = sprintf("[%s] [index-admisiones] %s\n", date('Y-m-d H:i:s'), $msg);
        file_put_contents($logFile, $line, FILE_APPEND);
    };

    $rawInput = file_get_contents('php://input');
    $log("RAW INPUT: " . $rawInput);
    $data = json_decode($rawInput, true);

    if ($data === null) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "JSON mal formado o vacío"]);
        exit;
    }

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

    $allowedKeys = [
        'hcNumber', 'form_id', 'procedimiento_proyectado', 'doctor', 'cie10', 'estado_agenda', 'estado',
        'codigo_derivacion', 'num_secuencial_derivacion', 'fname', 'mname', 'lname', 'lname2', 'email',
        'fecha_nacimiento', 'sexo', 'ciudad', 'afiliacion', 'telefono', 'fecha', 'hora', 'nombre_completo',
    ];
    $dateKeys = ['fecha', 'fecha_nacimiento', 'fechaCaducidad', 'fecha_caducidad', 'fecha_nac'];

    $respuestas = [];
    $erroresBatch = [];

    foreach ($data as $index => $item) {
        if (!is_array($item)) {
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => null,
                'hcNumber' => null,
                'message' => 'Payload no es un objeto válido.',
            ];
            continue;
        }

        $item = array_intersect_key($item, array_flip($allowedKeys));

        $invalidDates = [];
        foreach ($dateKeys as $dateKey) {
            if (!array_key_exists($dateKey, $item)) {
                continue;
            }

            $normalized = $normalizeDateValue($item[$dateKey]);
            if ($normalized === null) {
                $invalidDates[$dateKey] = $item[$dateKey];
                unset($item[$dateKey]);
                continue;
            }

            if (in_array($dateKey, ['fechaCaducidad', 'fecha_caducidad', 'fecha_nac'], true)) {
                unset($item[$dateKey]);
                continue;
            }

            $item[$dateKey] = $normalized;
        }

        if (empty($item['hcNumber']) || empty($item['form_id']) || empty($item['procedimiento_proyectado'])) {
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => $item['form_id'] ?? null,
                'hcNumber' => $item['hcNumber'] ?? null,
                'message' => 'Datos faltantes: hcNumber, form_id o procedimiento_proyectado.',
            ];
            continue;
        }

        if (!empty($invalidDates)) {
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => $item['form_id'] ?? null,
                'hcNumber' => $item['hcNumber'] ?? null,
                'message' => 'Fechas inválidas en el payload.',
            ];
            $log("ERROR index={$index} form_id={$item['form_id']} hcNumber={$item['hcNumber']} message=Fechas inválidas");
            $log("PAYLOAD index={$index}: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $log("FECHAS index={$index}: " . json_encode($invalidDates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            continue;
        }

        if (empty($item['fecha'])) {
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => $item['form_id'] ?? null,
                'hcNumber' => $item['hcNumber'] ?? null,
                'message' => 'Fecha inválida o vacía.',
            ];
            $log("ERROR index={$index} form_id={$item['form_id']} hcNumber={$item['hcNumber']} message=Fecha inválida");
            $log("PAYLOAD index={$index}: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $log("FECHAS index={$index}: " . json_encode([
                'fecha' => $item['fecha'] ?? null,
                'fecha_nacimiento' => $item['fecha_nacimiento'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            continue;
        }

        try {
            $auditType = PHP_SAPI === 'cli' ? 'cron' : 'api';
            $auditIdentifier = PHP_SAPI === 'cli'
                ? 'cron:' . basename((string) ($_SERVER['argv'][0] ?? 'unknown_script'))
                : 'api:' . trim((string) ($_SERVER['REQUEST_URI'] ?? '/api/proyecciones/guardar_index_admisiones.php'));

            $patientParams = [
                ':hc_number' => $item['hcNumber'],
                ':fname' => $item['fname'] ?? '',
                ':mname' => $item['mname'] ?? '',
                ':lname' => $item['lname'] ?? 'DESCONOCIDO',
                ':lname2' => $item['lname2'] ?? '',
                ':email' => $item['email'] ?? null,
                ':fecha_nacimiento' => $item['fecha_nacimiento'] ?? null,
                ':sexo' => $item['sexo'] ?? null,
                ':ciudad' => $item['ciudad'] ?? null,
                ':afiliacion' => $item['afiliacion'] ?? null,
                ':celular' => $item['telefono'] ?? null,
                ':fecha_caducidad' => null,
                ':created_by_type' => $auditType,
                ':created_by_identifier' => $auditIdentifier,
                ':updated_by_type' => $auditType,
                ':updated_by_identifier' => $auditIdentifier,
            ];

            $sqlPatient = "
                INSERT INTO patient_data
                    (hc_number, fname, mname, lname, lname2, email, fecha_nacimiento, sexo, ciudad, afiliacion, celular, fecha_caducidad, created_by_type, created_by_identifier, updated_by_type, updated_by_identifier)
                VALUES
                    (:hc_number, :fname, :mname, :lname, :lname2, :email, :fecha_nacimiento, :sexo, :ciudad, :afiliacion, :celular, :fecha_caducidad, :created_by_type, :created_by_identifier, :updated_by_type, :updated_by_identifier)
                ON DUPLICATE KEY UPDATE
                    fname = IF(VALUES(fname) = '' OR VALUES(fname) IS NULL, fname, VALUES(fname)),
                    mname = IF(VALUES(mname) = '' OR VALUES(mname) IS NULL, mname, VALUES(mname)),
                    lname = IF(VALUES(lname) = '' OR VALUES(lname) IS NULL, lname, VALUES(lname)),
                    lname2 = IF(VALUES(lname2) = '' OR VALUES(lname2) IS NULL, lname2, VALUES(lname2)),
                    email = IF(VALUES(email) = '' OR VALUES(email) IS NULL, email, VALUES(email)),
                    fecha_nacimiento = IF(VALUES(fecha_nacimiento) IS NULL, fecha_nacimiento, VALUES(fecha_nacimiento)),
                    sexo = IF(VALUES(sexo) = '' OR VALUES(sexo) IS NULL, sexo, VALUES(sexo)),
                    ciudad = IF(VALUES(ciudad) = '' OR VALUES(ciudad) IS NULL, ciudad, VALUES(ciudad)),
                    afiliacion = IF(VALUES(afiliacion) = '' OR VALUES(afiliacion) IS NULL, afiliacion, VALUES(afiliacion)),
                    celular = IF(VALUES(celular) = '' OR VALUES(celular) IS NULL, celular, VALUES(celular)),
                    fecha_caducidad = IF(VALUES(fecha_caducidad) IS NULL, fecha_caducidad, VALUES(fecha_caducidad)),
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by_type = VALUES(updated_by_type),
                    updated_by_identifier = VALUES(updated_by_identifier)
            ";
            $stmtPatient = $pdo->prepare($sqlPatient);
            $stmtPatient->execute($patientParams);

            $procedimientoParams = [
                ':form_id' => $item['form_id'],
                ':procedimiento' => $item['procedimiento_proyectado'],
                ':doctor' => $item['doctor'] ?? null,
                ':hc_number' => $item['hcNumber'],
                ':estado_agenda' => $item['estado_agenda'] ?? $item['estado'] ?? null,
                ':afiliacion' => $item['afiliacion'] ?? null,
                ':fecha' => $item['fecha'],
                ':hora' => $item['hora'] ?? null,
            ];

            $sqlProcedimiento = "
                INSERT INTO procedimiento_proyectado
                    (form_id, procedimiento_proyectado, doctor, hc_number, estado_agenda, afiliacion, fecha, hora)
                VALUES
                    (:form_id, :procedimiento, :doctor, :hc_number, :estado_agenda, :afiliacion, :fecha, :hora)
                ON DUPLICATE KEY UPDATE
                    procedimiento_proyectado = VALUES(procedimiento_proyectado),
                    doctor = VALUES(doctor),
                    estado_agenda = IF(VALUES(estado_agenda) IS NULL OR VALUES(estado_agenda) = '', estado_agenda, VALUES(estado_agenda)),
                    afiliacion = IF(VALUES(afiliacion) IS NULL OR VALUES(afiliacion) = '', afiliacion, VALUES(afiliacion)),
                    fecha = VALUES(fecha),
                    hora = VALUES(hora)
            ";
            $stmtProc = $pdo->prepare($sqlProcedimiento);
            $stmtProc->execute($procedimientoParams);

            $respuesta = [
                'success' => true,
                'message' => 'Registro actualizado o insertado.',
                'id' => $item['form_id'],
                'form_id' => $item['form_id'],
                'afiliacion' => $item['afiliacion'] ?? null,
                'estado' => $procedimientoParams[':estado_agenda'] ?? null,
                'hc_number' => $item['hcNumber'],
                'visita_id' => null,
            ];
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
            $erroresBatch[] = [
                'index' => $index,
                'form_id' => $item['form_id'] ?? null,
                'hcNumber' => $item['hcNumber'] ?? null,
                'message' => $e->getMessage(),
            ];
            $log("ERROR index={$index} form_id={$item['form_id']} hcNumber={$item['hcNumber']} message=" . $e->getMessage());
            $log("PAYLOAD index={$index}: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $log("FECHAS index={$index}: " . json_encode([
                'fecha' => $item['fecha'] ?? null,
                'fecha_nacimiento' => $item['fecha_nacimiento'] ?? null,
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
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error interno: " . $e->getMessage()
    ]);
    exit;
}
