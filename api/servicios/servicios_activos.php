<?php
require_once '../../bootstrap.php'; // Ajusta ruta según estructura
header('Content-Type: application/json');

use PDO;

$extension_id = $_GET['extension_id'] ?? 'CIVE';

// Buscar ID del cliente
$stmt = $pdo->prepare("SELECT id FROM clientes WHERE nombre = ?");
$stmt->execute([$extension_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    http_response_code(404);
    echo json_encode(['error' => 'Cliente no encontrado']);
    exit;
}

// Obtener servicios
$stmt = $pdo->prepare("SELECT servicio_nombre, activo FROM servicios_cliente WHERE cliente_id = ?");
$stmt->execute([$cliente['id']]);
$servicios = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo json_encode([
    'cliente' => $extension_id,
    'servicios' => array_map(fn($v) => boolval($v), $servicios)
]);