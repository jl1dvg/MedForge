<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarProyeccionController;
use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

if (!CorsHelper::prepare('PROYECCIONES_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.consulmed.me/*',
])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Origen no permitido para este recurso.'
    ]);
    return;
}

ini_set('display_errors', 0);
error_reporting(0);

$controller = new GuardarProyeccionController($pdo);

$formId = $_POST['form_id'] ?? null;

if ($formId) {
    $resultado = $controller->actualizarEstado($formId, 'LLEGADO');
    $datos = $controller->obtenerDatosPacientePorFormId($formId);
    $nombre = $datos['nombre'] ?? 'Paciente desconocido';
    $procedimiento = $datos['procedimiento'] ?? 'Procedimiento no definido';
    $doctor = $datos['doctor'] ?? '';
    $frase = "$nombre ha llegado para $procedimiento con $doctor, ";
    if ($resultado['success']) {
        $token = $_ENV['WHATSAPP_API_TOKEN'] ?? getenv('WHATSAPP_API_TOKEN') ?: '';
        $phone_number_id = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';
        $recipientList = $_ENV['WHATSAPP_RECIPIENT_PHONE'] ?? getenv('WHATSAPP_RECIPIENT_PHONE') ?: '';

        $recipients = array_filter(array_map('trim', preg_split('/\s*,\s*/', $recipientList) ?: []));

        if ($token && $phone_number_id && $recipients) {
            $template_name = 'alerta_llegada_paciente';
            $template_params = [
                ["type" => "text", "text" => $frase]
            ];

            foreach ($recipients as $recipient_phone) {
                $payload = [
                    "messaging_product" => "whatsapp",
                    "to" => $recipient_phone,
                    "type" => "template",
                    "template" => [
                        "name" => $template_name,
                        "language" => ["code" => "es_MX"],
                        "components" => [
                            [
                                "type" => "body",
                                "parameters" => $template_params
                            ]
                        ]
                    ]
                ];

                $endpoint = sprintf('https://graph.facebook.com/v23.0/%s/messages', $phone_number_id);
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $token",
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                file_put_contents(
                    __DIR__ . '/log_whatsapp.txt',
                    sprintf("Destino: %s\nHTTP Code: %s\nResponse:\n%s\n\n", $recipient_phone, $httpCode, $response ?: '[sin respuesta]'),
                    FILE_APPEND
                );
            }
        } else {
            error_log('Notificación de llegada omitida: faltan credenciales o destinatarios de WhatsApp.');
        }
    }
    echo json_encode($resultado);
} else {
    echo json_encode(["success" => false, "message" => "No se pudo actualizar el estado."]);
}