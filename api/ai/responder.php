<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Helpers\CorsHelper;
use Helpers\OpenAIHelper;

header('Content-Type: application/json; charset=UTF-8');

if (!CorsHelper::prepare('AI_ALLOWED_ORIGINS')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Origen no permitido para este recurso.'
    ]);
    return;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: 'null', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Formato de solicitud inválido.'
    ]);
    return;
}

$prompt = trim((string)($data['prompt'] ?? ''));
$maxTokens = $data['max_output_tokens'] ?? null;

if ($prompt === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El campo "prompt" es obligatorio.'
    ]);
    return;
}

try {
    $helper = new OpenAIHelper();
    $text = $helper->respond($prompt, is_numeric($maxTokens) ? (int)$maxTokens : null);

    echo json_encode([
        'success' => true,
        'text' => $text
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('Error al procesar solicitud OpenAI: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible procesar la solicitud en este momento.'
    ]);
}
