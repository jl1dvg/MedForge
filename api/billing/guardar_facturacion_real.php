<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

ini_set('display_errors', '0');
error_reporting(0);

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput ?: 'null', true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'JSON mal formado o vacio.',
        ]);
        exit;
    }

    $normalizeDateTime = static function ($value): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    };

    $parseAmount = static function ($value): ?float {
        $raw = trim((string) ($value ?? ''));
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
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 4);
    };

    $allowedKeys = [
        'form_id',
        'procedimiento',
        'realizado_por',
        'afiliacion',
        'paciente',
        'cliente',
        'fecha_agenda',
        'fecha_facturacion',
        'fecha_atencion',
        'numero_factura',
        'factura_id',
        'formas_pago',
        'codigo_nota',
        'monto_honorario',
        'monto_facturado',
        'area',
        'estado',
        'source_month',
    ];

    $sql = <<<SQL
        INSERT INTO billing_facturacion_real (
            dedupe_key,
            form_id,
            procedimiento,
            realizado_por,
            afiliacion,
            paciente,
            cliente,
            fecha_agenda,
            fecha_facturacion,
            fecha_atencion,
            numero_factura,
            factura_id,
            formas_pago,
            codigo_nota,
            monto_honorario,
            monto_facturado,
            area,
            estado,
            source_month,
            raw_payload
        ) VALUES (
            :dedupe_key,
            :form_id,
            :procedimiento,
            :realizado_por,
            :afiliacion,
            :paciente,
            :cliente,
            :fecha_agenda,
            :fecha_facturacion,
            :fecha_atencion,
            :numero_factura,
            :factura_id,
            :formas_pago,
            :codigo_nota,
            :monto_honorario,
            :monto_facturado,
            :area,
            :estado,
            :source_month,
            :raw_payload
        )
        ON DUPLICATE KEY UPDATE
            procedimiento = VALUES(procedimiento),
            realizado_por = VALUES(realizado_por),
            afiliacion = VALUES(afiliacion),
            paciente = VALUES(paciente),
            cliente = VALUES(cliente),
            fecha_agenda = VALUES(fecha_agenda),
            fecha_facturacion = VALUES(fecha_facturacion),
            fecha_atencion = VALUES(fecha_atencion),
            numero_factura = VALUES(numero_factura),
            factura_id = VALUES(factura_id),
            formas_pago = VALUES(formas_pago),
            codigo_nota = VALUES(codigo_nota),
            monto_honorario = VALUES(monto_honorario),
            monto_facturado = VALUES(monto_facturado),
            area = VALUES(area),
            estado = VALUES(estado),
            source_month = VALUES(source_month),
            raw_payload = VALUES(raw_payload),
            updated_at = CURRENT_TIMESTAMP
    SQL;

    $stmt = $pdo->prepare($sql);
    $responses = [];
    $errors = [];

    foreach ($data as $index => $item) {
        if (!is_array($item)) {
            $errors[] = [
                'index' => $index,
                'message' => 'Payload no es un objeto valido.',
            ];
            continue;
        }

        $item = array_intersect_key($item, array_flip($allowedKeys));
        $formId = trim((string) ($item['form_id'] ?? ''));
        if ($formId === '') {
            $errors[] = [
                'index' => $index,
                'message' => 'form_id es obligatorio.',
            ];
            continue;
        }

        $payloadForHash = [
            'form_id' => $formId,
            'factura_id' => trim((string) ($item['factura_id'] ?? '')),
            'numero_factura' => trim((string) ($item['numero_factura'] ?? '')),
            'procedimiento' => trim((string) ($item['procedimiento'] ?? '')),
            'fecha_facturacion' => $normalizeDateTime($item['fecha_facturacion'] ?? null) ?? '',
            'monto_honorario' => $parseAmount($item['monto_honorario'] ?? null) ?? '',
        ];

        $params = [
            ':dedupe_key' => md5(json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ':form_id' => $formId,
            ':procedimiento' => trim((string) ($item['procedimiento'] ?? '')) ?: null,
            ':realizado_por' => trim((string) ($item['realizado_por'] ?? '')) ?: null,
            ':afiliacion' => trim((string) ($item['afiliacion'] ?? '')) ?: null,
            ':paciente' => trim((string) ($item['paciente'] ?? '')) ?: null,
            ':cliente' => trim((string) ($item['cliente'] ?? '')) ?: null,
            ':fecha_agenda' => $normalizeDateTime($item['fecha_agenda'] ?? null),
            ':fecha_facturacion' => $normalizeDateTime($item['fecha_facturacion'] ?? null),
            ':fecha_atencion' => $normalizeDateTime($item['fecha_atencion'] ?? null),
            ':numero_factura' => trim((string) ($item['numero_factura'] ?? '')) ?: null,
            ':factura_id' => trim((string) ($item['factura_id'] ?? '')) ?: null,
            ':formas_pago' => trim((string) ($item['formas_pago'] ?? '')) ?: null,
            ':codigo_nota' => trim((string) ($item['codigo_nota'] ?? '')) ?: null,
            ':monto_honorario' => $parseAmount($item['monto_honorario'] ?? null),
            ':monto_facturado' => $parseAmount($item['monto_facturado'] ?? null),
            ':area' => trim((string) ($item['area'] ?? '')) ?: null,
            ':estado' => trim((string) ($item['estado'] ?? '')) ?: null,
            ':source_month' => trim((string) ($item['source_month'] ?? '')) ?: null,
            ':raw_payload' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        try {
            $stmt->execute($params);
            $responses[] = [
                'index' => $index,
                'success' => true,
                'form_id' => $formId,
                'factura_id' => $params[':factura_id'],
            ];
        } catch (\Throwable $e) {
            $errors[] = [
                'index' => $index,
                'form_id' => $formId,
                'message' => $e->getMessage(),
            ];
        }
    }

    if (!empty($errors)) {
        http_response_code(207);
        echo json_encode([
            'success' => false,
            'detalles' => $responses,
            'errores' => $errors,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'detalles' => $responses,
    ]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage(),
    ]);
    exit;
}
