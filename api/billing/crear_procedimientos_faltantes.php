<?php
require_once __DIR__ . '/../../bootstrap.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Controllers\DerivacionController;

header('Content-Type: application/json');

$controller = new DerivacionController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    // file_put_contents('php://stderr', print_r($input, true)); // Descomenta para debug

    if (!is_array($input)) {
        echo json_encode(['success' => false, 'error' => 'Formato JSON no válido.']);
        exit;
    }

    if (!isset($input['procedimientos']) || !is_array($input['procedimientos'])) {
        echo json_encode(['success' => false, 'error' => 'No se enviaron procedimientos.']);
        exit;
    }
    $procedimientos = $input['procedimientos'];

    $form_ids_grouped = [];

    foreach ($procedimientos as $proc) {
        if (!isset($proc['form_id'], $proc['hc_number']) || empty($proc['hc_number'])) {
            echo json_encode(['success' => false, 'error' => 'Cada procedimiento debe tener form_id y hc_number válidos.']);
            exit;
        }

        if (!is_numeric($proc['form_id'])) {
            echo json_encode(['success' => false, 'error' => 'form_id inválido: debe ser numérico.']);
            exit;
        }

        if (!empty($proc['form_id'])) {
            $form_ids_grouped[$proc['hc_number']][] = $proc['form_id'];
        }
    }

    $resultado_total = [];

    foreach ($form_ids_grouped as $hc_number => $form_ids) {
        $form_ids = array_filter($form_ids); // remove any empty values
        if (empty($form_ids)) {
            continue;
        }
        try {
            $procedimientos_filtrados = array_filter($procedimientos, fn($p) => $p['hc_number'] === $hc_number);
            $resultado = $controller->crearFormIdsFaltantes($procedimientos_filtrados);
            $resultado_total[$hc_number] = $resultado;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error procesando hc_number ' . $hc_number . ': ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => true, 'resultados' => $resultado_total]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido. Solo POST es aceptado.']);
}