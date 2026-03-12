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

    $normalizeAffiliationKey = static function (?string $value): string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $value
        );
        $value = str_replace([' ', '-'], '_', $value);
        $value = preg_replace('/_+/u', '_', $value) ?: $value;

        return trim($value, '_');
    };

    $splitProcedureSegments = static function (string $procedimiento): array {
        $parts = preg_split('/\s+-\s+/u', trim($procedimiento));
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $parts
        ), static fn(string $value): bool => $value !== ''));
    };

    $extractProcedureCode = static function (?string $procedimiento) use ($splitProcedureSegments): ?string {
        $procedimiento = trim((string) ($procedimiento ?? ''));
        if ($procedimiento === '') {
            return null;
        }

        $parts = $splitProcedureSegments($procedimiento);
        if (count($parts) < 2) {
            return null;
        }

        $normalizeCandidate = static function (?string $value): string {
            return strtoupper(trim((string) ($value ?? '')));
        };

        $isLikelyCode = static function (string $value): bool {
            if ($value === '' || str_contains($value, ' ')) {
                return false;
            }
            return (bool) preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $value);
        };

        $tipoAtencion = $normalizeCandidate($parts[0] ?? '');

        if (str_contains($tipoAtencion, 'IMAGEN') && array_key_exists(2, $parts)) {
            $thirdRaw = trim((string) ($parts[2] ?? ''));
            $thirdNorm = $normalizeCandidate($thirdRaw);

            if (preg_match('/^([A-Z0-9]{3,})\s*-\s*(.+)$/u', $thirdNorm, $matches)) {
                return trim((string) ($matches[1] ?? '')) ?: null;
            }

            if ($isLikelyCode($thirdNorm)) {
                return $thirdNorm;
            }
        }

        $second = $normalizeCandidate($parts[1] ?? '');
        if ($isLikelyCode($second)) {
            return $second;
        }

        if (array_key_exists(2, $parts)) {
            $third = $normalizeCandidate($parts[2] ?? '');
            if (preg_match('/^([A-Z0-9][A-Z0-9._-]*)\s*-\s*/u', $third, $matches)) {
                return trim((string) ($matches[1] ?? '')) ?: null;
            }
            if ($isLikelyCode($third)) {
                return $third;
            }
        }

        return null;
    };

    $extractProcedureDetail = static function (?string $procedimiento, ?string $codigo) use ($splitProcedureSegments): ?string {
        $procedimiento = trim((string) ($procedimiento ?? ''));
        $codigo = strtoupper(trim((string) ($codigo ?? '')));

        if ($procedimiento === '' || $codigo === '') {
            return null;
        }

        $parts = $splitProcedureSegments($procedimiento);
        if (empty($parts)) {
            return null;
        }

        $tipoAtencion = strtoupper((string) ($parts[0] ?? ''));
        $detailParts = [];

        if (str_contains($tipoAtencion, 'IMAGEN') && array_key_exists(2, $parts)) {
            $thirdRaw = trim((string) ($parts[2] ?? ''));
            $thirdNorm = strtoupper($thirdRaw);

            if (preg_match('/^([A-Z0-9]{3,})\s*-\s*(.+)$/u', $thirdNorm, $matches)) {
                $imageCode = trim((string) ($matches[1] ?? ''));
                if ($imageCode === $codigo) {
                    $firstDetailChunk = trim((string) ($matches[2] ?? ''));
                    if ($firstDetailChunk !== '') {
                        $detailParts[] = $firstDetailChunk;
                    }
                    $detailParts = array_merge($detailParts, array_slice($parts, 3));
                }
            } elseif ($thirdNorm === $codigo) {
                $detailParts = array_slice($parts, 3);
            }
        }

        if (empty($detailParts)) {
            foreach ($parts as $index => $part) {
                if (strtoupper(trim((string) $part)) !== $codigo) {
                    continue;
                }
                $detailParts = array_slice($parts, $index + 1);
                break;
            }
        }

        $detailParts = array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $detailParts
        ), static fn(string $value): bool => $value !== ''));

        if (!empty($detailParts)) {
            return implode(' - ', $detailParts);
        }

        $escapedCode = preg_quote($codigo, '/');
        if (preg_match('/\b' . $escapedCode . '\b\s*-\s*(.+)$/u', $procedimiento, $matches)) {
            $detail = trim((string) ($matches[1] ?? ''));
            return $detail !== '' ? $detail : null;
        }

        return null;
    };

    $parsePriceValue = static function ($value): ?float {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/u', '', $raw);
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }

        $hasComma = strpos($normalized, ',') !== false;
        $hasDot = strpos($normalized, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $commaCount = substr_count($normalized, ',');
            if (
                $commaCount === 1
                && (
                    preg_match('/^-?\d+,\d+$/', $normalized)
                    || preg_match('/^-?\d{1,3}(?:\.\d{3})+,\d+$/', $normalized)
                )
            ) {
                // Formato esperado del scrape: 32,0000 / 1.234,5000
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasDot && substr_count($normalized, '.') > 1) {
            $lastDot = strrpos($normalized, '.');
            if ($lastDot !== false) {
                $decimals = strlen($normalized) - $lastDot - 1;
                if ($decimals > 0 && $decimals <= 2) {
                    $intPart = str_replace('.', '', substr($normalized, 0, $lastDot));
                    $decPart = substr($normalized, $lastDot + 1);
                    $normalized = $intPart . '.' . $decPart;
                } else {
                    $normalized = str_replace('.', '', $normalized);
                }
            }
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    };

    $quoteIdentifier = static function (string $identifier): string {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException("Identificador SQL inválido: {$identifier}");
        }
        return "`{$identifier}`";
    };

    $fetchTableColumns = static function (PDO $pdo, string $table): array {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table"
        );
        $stmt->execute([':table' => $table]);
        $columns = [];
        while (($column = $stmt->fetchColumn()) !== false) {
            $name = (string) $column;
            $columns[strtolower($name)] = $name;
        }
        return $columns;
    };

    $procTableColumns = [];
    try {
        $procTableColumns = $fetchTableColumns($pdo, 'procedimiento_proyectado');
    } catch (Throwable $schemaError) {
        $log('WARN no se pudo inspeccionar schema procedimiento_proyectado: ' . $schemaError->getMessage());
    }

    $resolveColumn = static function (array $candidates) use ($procTableColumns): ?string {
        foreach ($candidates as $candidate) {
            $lookup = strtolower((string) $candidate);
            if (isset($procTableColumns[$lookup])) {
                return $procTableColumns[$lookup];
            }
        }
        return null;
    };

    $afiliacionMapColumns = [];
    try {
        $afiliacionMapColumns = $fetchTableColumns($pdo, 'afiliacion_categoria_map');
    } catch (Throwable $schemaError) {
        $log('WARN no se pudo inspeccionar schema afiliacion_categoria_map: ' . $schemaError->getMessage());
    }

    $hasAfiliacionMap = isset($afiliacionMapColumns['afiliacion_norm'], $afiliacionMapColumns['categoria']);
    $stmtCategoriaAfiliacion = null;
    if ($hasAfiliacionMap) {
        $stmtCategoriaAfiliacion = $pdo->prepare(
            "SELECT LOWER(TRIM(categoria))
               FROM afiliacion_categoria_map
              WHERE afiliacion_norm = :afiliacion_norm
              LIMIT 1"
        );
    }

    $resolveClienteCategory = static function (?string $afiliacionRaw) use ($normalizeAffiliationKey, $hasAfiliacionMap, $stmtCategoriaAfiliacion): string {
        static $categoryCache = [];

        $afiliacionNorm = $normalizeAffiliationKey($afiliacionRaw);
        if (isset($categoryCache[$afiliacionNorm])) {
            return $categoryCache[$afiliacionNorm];
        }

        if ($hasAfiliacionMap && $stmtCategoriaAfiliacion !== null && $afiliacionNorm !== '') {
            $stmtCategoriaAfiliacion->execute([':afiliacion_norm' => $afiliacionNorm]);
            $categoriaMap = $stmtCategoriaAfiliacion->fetchColumn();
            if ($categoriaMap !== false) {
                $categoriaMap = trim((string) $categoriaMap);
                if ($categoriaMap !== '') {
                    $categoryCache[$afiliacionNorm] = strtolower($categoriaMap);
                    return $categoryCache[$afiliacionNorm];
                }
            }
        }

        if ($afiliacionNorm === '') {
            $categoryCache[$afiliacionNorm] = 'otros';
            return $categoryCache[$afiliacionNorm];
        }
        if (str_contains($afiliacionNorm, 'particular')) {
            $categoryCache[$afiliacionNorm] = 'particular';
            return $categoryCache[$afiliacionNorm];
        }
        if (str_contains($afiliacionNorm, 'fundacion') || str_contains($afiliacionNorm, 'fundacional')) {
            $categoryCache[$afiliacionNorm] = 'fundacional';
            return $categoryCache[$afiliacionNorm];
        }
        if (preg_match('/iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario/', $afiliacionNorm)) {
            $categoryCache[$afiliacionNorm] = 'publico';
            return $categoryCache[$afiliacionNorm];
        }

        $categoryCache[$afiliacionNorm] = 'privado';
        return $categoryCache[$afiliacionNorm];
    };

    $referidoPrefacturaColumn = $resolveColumn(['referido_prefactura_por', 'id_procedencia']);
    $especificarReferidoColumn = $resolveColumn(['especificar_referido_prefactura', 'especificar_por', 'especificarpor']);

    $allowedKeys = [
        'hcNumber', 'form_id', 'procedimiento_proyectado', 'doctor', 'cie10', 'estado_agenda', 'estado',
        'codigo_derivacion', 'num_secuencial_derivacion', 'fname', 'mname', 'lname', 'lname2', 'email',
        'fecha_nacimiento', 'sexo', 'ciudad', 'afiliacion', 'telefono', 'fecha', 'hora', 'nombre_completo',
        'sede_departamento', 'id_sede', 'referido_prefactura_por', 'especificar_referido_prefactura', 'precio',
    ];
    $dateKeys = ['fecha', 'fecha_nacimiento', 'fechaCaducidad', 'fecha_caducidad', 'fecha_nac'];

    $procedimientoInsertColumns = [
        'form_id', 'procedimiento_proyectado', 'doctor', 'hc_number',
        'sede_departamento', 'id_sede', 'estado_agenda', 'afiliacion', 'fecha', 'hora',
    ];
    $procedimientoValuePlaceholders = [
        ':form_id', ':procedimiento', ':doctor', ':hc_number',
        ':sede_departamento', ':id_sede', ':estado_agenda', ':afiliacion', ':fecha', ':hora',
    ];
    $procedimientoUpdateClauses = [
        'procedimiento_proyectado = VALUES(procedimiento_proyectado)',
        'doctor = VALUES(doctor)',
        "sede_departamento = IF(VALUES(sede_departamento) IS NULL OR VALUES(sede_departamento) = '', sede_departamento, VALUES(sede_departamento))",
        "id_sede = IF(VALUES(id_sede) IS NULL OR VALUES(id_sede) = '', id_sede, VALUES(id_sede))",
        "estado_agenda = IF(VALUES(estado_agenda) IS NULL OR VALUES(estado_agenda) = '', estado_agenda, VALUES(estado_agenda))",
        "afiliacion = IF(VALUES(afiliacion) IS NULL OR VALUES(afiliacion) = '', afiliacion, VALUES(afiliacion))",
        'fecha = VALUES(fecha)',
        'hora = VALUES(hora)',
    ];

    if ($referidoPrefacturaColumn !== null) {
        $quoted = $quoteIdentifier($referidoPrefacturaColumn);
        $procedimientoInsertColumns[] = $referidoPrefacturaColumn;
        $procedimientoValuePlaceholders[] = ':referido_prefactura_por';
        $procedimientoUpdateClauses[] = "{$quoted} = IF(VALUES({$quoted}) IS NULL OR VALUES({$quoted}) = '', {$quoted}, VALUES({$quoted}))";
    }

    if ($especificarReferidoColumn !== null) {
        $quoted = $quoteIdentifier($especificarReferidoColumn);
        $procedimientoInsertColumns[] = $especificarReferidoColumn;
        $procedimientoValuePlaceholders[] = ':especificar_referido_prefactura';
        $procedimientoUpdateClauses[] = "{$quoted} = IF(VALUES({$quoted}) IS NULL OR VALUES({$quoted}) = '', {$quoted}, VALUES({$quoted}))";
    }

    $sqlProcedimiento = sprintf(
        "INSERT INTO procedimiento_proyectado (%s)\n                VALUES (%s)\n                ON DUPLICATE KEY UPDATE\n                    %s",
        implode(', ', array_map($quoteIdentifier, $procedimientoInsertColumns)),
        implode(', ', $procedimientoValuePlaceholders),
        implode(",\n                    ", $procedimientoUpdateClauses)
    );
    $stmtProc = $pdo->prepare($sqlProcedimiento);

    $stmtBillingMainByForm = $pdo->prepare("SELECT id, hc_number FROM billing_main WHERE form_id = :form_id LIMIT 1");
    $stmtInsertBillingMain = $pdo->prepare(
        "INSERT INTO billing_main (hc_number, form_id) VALUES (:hc_number, :form_id)"
    );
    $stmtUpdateBillingMainHc = $pdo->prepare(
        "UPDATE billing_main SET hc_number = :hc_number, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
    );
    $stmtBillingProcByCode = $pdo->prepare(
        "SELECT id FROM billing_procedimientos WHERE billing_id = :billing_id AND proc_codigo = :proc_codigo LIMIT 1"
    );
    $stmtInsertBillingProc = $pdo->prepare(
        "INSERT INTO billing_procedimientos (billing_id, proc_codigo, proc_detalle, proc_precio)
              VALUES (:billing_id, :proc_codigo, :proc_detalle, :proc_precio)"
    );
    $stmtUpdateBillingProc = $pdo->prepare(
        "UPDATE billing_procedimientos
            SET proc_detalle = :proc_detalle,
                proc_precio = :proc_precio
          WHERE id = :id"
    );

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

            $referidoPrefacturaPor = trim((string) ($item['referido_prefactura_por'] ?? ''));
            $especificarReferidoPrefactura = trim((string) ($item['especificar_referido_prefactura'] ?? ''));

            $procedimientoParams = [
                ':form_id' => $item['form_id'],
                ':procedimiento' => $item['procedimiento_proyectado'],
                ':doctor' => $item['doctor'] ?? null,
                ':hc_number' => $item['hcNumber'],
                ':sede_departamento' => $item['sede_departamento'] ?? null,
                ':id_sede' => $item['id_sede'] ?? null,
                ':estado_agenda' => $item['estado_agenda'] ?? $item['estado'] ?? null,
                ':afiliacion' => $item['afiliacion'] ?? null,
                ':fecha' => $item['fecha'],
                ':hora' => $item['hora'] ?? null,
            ];

            if ($referidoPrefacturaColumn !== null) {
                $procedimientoParams[':referido_prefactura_por'] = $referidoPrefacturaPor !== '' ? $referidoPrefacturaPor : null;
            }
            if ($especificarReferidoColumn !== null) {
                $procedimientoParams[':especificar_referido_prefactura'] = $especificarReferidoPrefactura !== '' ? $especificarReferidoPrefactura : null;
            }

            $stmtProc->execute($procedimientoParams);

            $categoriaCliente = $resolveClienteCategory($item['afiliacion'] ?? null);
            $esCategoriaNoPublica = $categoriaCliente !== 'publico';
            $precioScrape = $parsePriceValue($item['precio'] ?? null);
            $codigoProcedimiento = $extractProcedureCode($item['procedimiento_proyectado'] ?? null);
            $billingSincronizado = false;

            if ($esCategoriaNoPublica && $precioScrape !== null && $precioScrape > 0 && $codigoProcedimiento !== null) {
                $stmtBillingMainByForm->execute([':form_id' => $item['form_id']]);
                $billingMain = $stmtBillingMainByForm->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($billingMain) {
                    $billingId = (int) ($billingMain['id'] ?? 0);
                    $hcNumberActual = trim((string) ($billingMain['hc_number'] ?? ''));
                    if ($billingId > 0 && $item['hcNumber'] !== '' && $hcNumberActual !== (string) $item['hcNumber']) {
                        $stmtUpdateBillingMainHc->execute([
                            ':hc_number' => $item['hcNumber'],
                            ':id' => $billingId,
                        ]);
                    }
                } else {
                    $stmtInsertBillingMain->execute([
                        ':hc_number' => $item['hcNumber'],
                        ':form_id' => $item['form_id'],
                    ]);
                    $billingId = (int) $pdo->lastInsertId();
                }

                if (!empty($billingId)) {
                    $detalleProcedimiento = $extractProcedureDetail(
                        $item['procedimiento_proyectado'] ?? null,
                        $codigoProcedimiento
                    );
                    if ($detalleProcedimiento === null || $detalleProcedimiento === '') {
                        $detalleProcedimiento = $codigoProcedimiento;
                    }
                    $stmtBillingProcByCode->execute([
                        ':billing_id' => $billingId,
                        ':proc_codigo' => $codigoProcedimiento,
                    ]);
                    $billingProcId = $stmtBillingProcByCode->fetchColumn();

                    if ($billingProcId !== false) {
                        $stmtUpdateBillingProc->execute([
                            ':proc_detalle' => $detalleProcedimiento,
                            ':proc_precio' => $precioScrape,
                            ':id' => (int) $billingProcId,
                        ]);
                    } else {
                        $stmtInsertBillingProc->execute([
                            ':billing_id' => $billingId,
                            ':proc_codigo' => $codigoProcedimiento,
                            ':proc_detalle' => $detalleProcedimiento,
                            ':proc_precio' => $precioScrape,
                        ]);
                    }
                    $billingSincronizado = true;
                }
            } elseif ($esCategoriaNoPublica && $precioScrape !== null && $precioScrape > 0 && $codigoProcedimiento === null) {
                $log("WARN index={$index} form_id={$item['form_id']} no se pudo extraer codigo de procedimiento para facturación");
            }

            $respuesta = [
                'success' => true,
                'message' => 'Registro actualizado o insertado.',
                'id' => $item['form_id'],
                'form_id' => $item['form_id'],
                'afiliacion' => $item['afiliacion'] ?? null,
                'categoria_cliente' => $categoriaCliente,
                'estado' => $procedimientoParams[':estado_agenda'] ?? null,
                'hc_number' => $item['hcNumber'],
                'precio_scrape' => $precioScrape,
                'codigo_procedimiento' => $codigoProcedimiento,
                'billing_sync' => $billingSincronizado,
                'visita_id' => null,
            ];
            $respuestas[] = [
                'index' => $index,
                'success' => $respuesta['success'],
                'message' => $respuesta['message'],
                'id' => $respuesta['id'] ?? $item['form_id'],
                'form_id' => $respuesta['form_id'] ?? $item['form_id'],
                'afiliacion' => $respuesta['afiliacion'] ?? ($item['afiliacion'] ?? null),
                'categoria_cliente' => $respuesta['categoria_cliente'] ?? null,
                'estado' => $respuesta['estado'] ?? null,
                'hc_number' => $respuesta['hc_number'] ?? ($item['hcNumber'] ?? null),
                'precio_scrape' => $respuesta['precio_scrape'] ?? null,
                'codigo_procedimiento' => $respuesta['codigo_procedimiento'] ?? null,
                'billing_sync' => $respuesta['billing_sync'] ?? false,
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
