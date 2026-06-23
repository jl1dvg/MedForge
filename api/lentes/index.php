<?php
require_once __DIR__ . '/../../bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function listarLentes(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, marca, modelo, nombre, poder, observacion,
                rango_desde, rango_hasta, rango_paso, rango_inicio_incremento,
                rango_texto, constante_a, constante_a_us, tipo_optico
         FROM lentes_catalogo
         ORDER BY marca, modelo, nombre'
    );

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function decimalOrNull(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = str_replace(',', '.', (string)$value);

    return is_numeric($normalized) ? (float)$normalized : null;
}

function guardarLente(PDO $pdo, array $payload): array
{
    $marca = trim((string)($payload['marca'] ?? ''));
    $modelo = trim((string)($payload['modelo'] ?? ''));
    $nombre = trim((string)($payload['nombre'] ?? ''));

    if ($marca === '' || $modelo === '' || $nombre === '') {
        return [
            'success' => false,
            'message' => 'Marca, modelo y nombre son obligatorios',
        ];
    }

    $tipoOptico = trim((string)($payload['tipo_optico'] ?? ''));
    if ($tipoOptico !== '' && !in_array($tipoOptico, ['una_pieza', 'multipieza'], true)) {
        $tipoOptico = '';
    }

    $row = [
        ':marca' => $marca,
        ':modelo' => $modelo,
        ':nombre' => $nombre,
        ':poder' => trim((string)($payload['poder'] ?? '')) ?: null,
        ':observacion' => trim((string)($payload['observacion'] ?? '')) ?: null,
        ':rango_desde' => decimalOrNull($payload['rango_desde'] ?? null),
        ':rango_hasta' => decimalOrNull($payload['rango_hasta'] ?? null),
        ':rango_paso' => decimalOrNull($payload['rango_paso'] ?? null),
        ':rango_inicio_incremento' => decimalOrNull($payload['rango_inicio_incremento'] ?? null),
        ':rango_texto' => trim((string)($payload['rango_texto'] ?? '')) ?: null,
        ':constante_a' => decimalOrNull($payload['constante_a'] ?? null),
        ':constante_a_us' => decimalOrNull($payload['constante_a_us'] ?? null),
        ':tipo_optico' => $tipoOptico !== '' ? $tipoOptico : null,
    ];

    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE lentes_catalogo
             SET marca = :marca, modelo = :modelo, nombre = :nombre, poder = :poder,
                 observacion = :observacion, rango_desde = :rango_desde,
                 rango_hasta = :rango_hasta, rango_paso = :rango_paso,
                 rango_inicio_incremento = :rango_inicio_incremento,
                 rango_texto = :rango_texto, constante_a = :constante_a,
                 constante_a_us = :constante_a_us, tipo_optico = :tipo_optico
             WHERE id = :id'
        );
        $stmt->execute($row + [':id' => $id]);

        return ['success' => true, 'id' => $id];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO lentes_catalogo
            (marca, modelo, nombre, poder, observacion, rango_desde, rango_hasta,
             rango_paso, rango_inicio_incremento, rango_texto, constante_a,
             constante_a_us, tipo_optico)
         VALUES
            (:marca, :modelo, :nombre, :poder, :observacion, :rango_desde,
             :rango_hasta, :rango_paso, :rango_inicio_incremento, :rango_texto,
             :constante_a, :constante_a_us, :tipo_optico)'
    );
    $stmt->execute($row);

    return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
}

function eliminarLente(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM lentes_catalogo WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

try {
    if ($method === 'GET') {
        echo json_encode(['success' => true, 'lentes' => listarLentes($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if ($method === 'POST') {
        $resultado = guardarLente($pdo, $payload);
        if (($resultado['success'] ?? false) === false) {
            http_response_code(422);
        }
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE') {
        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ok = eliminarLente($pdo, $id);
        echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en API de lentes',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
