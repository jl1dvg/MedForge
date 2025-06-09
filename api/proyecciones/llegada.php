<?php
require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json; charset=UTF-8');

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
        // Enviar mensaje de WhatsApp usando WhatsApp Cloud API de Meta
        $token = 'EAAmvsuKU778BOZCuf5tUt6So6rrzDZBt4y5F6VlgQNdSLTac6M4qIropdeZA6k3Ufs9oV95N13J69mB3B9PlaP10fFZAZBwgRrPZCGGBgoNLS8ZAjU098QmJuBdIXMxhs6OphA3llRlUteuBu83d8gddQOkN1fWw7DR5NsflA0x45aUvGEP37OVEo27xmM1CnxJVgZDZD'; // Reemplaza con tu token real de acceso a la API
        $phone_number_id = '228119940383390'; // ID del número de teléfono configurado en WhatsApp Business
        $recipient_phone = '593997190401'; // Número de teléfono del destinatario en formato internacional

        $template_name = 'alerta_llegada_paciente'; // Nombre de la plantilla aprobada
        $template_params = [
            ["type" => "text", "text" => $frase] // Parámetro para el {{1}} de la plantilla
        ];

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

        $ch = curl_init("https://graph.facebook.com/v23.0/{$phone_number_id}/messages");
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

        // Registrar respuesta en un archivo de log para depuración
        file_put_contents(__DIR__ . '/log_whatsapp.txt', "HTTP Code: $httpCode\nResponse:\n$response\n\n", FILE_APPEND);
    }
    echo json_encode($resultado);
} else {
    echo json_encode(["success" => false, "message" => "No se pudo actualizar el estado."]);
}