<?php
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 5) . '/bootstrap.php';
}

$form_id = $_POST['form_id'] ?? null;
$hc_number = $_POST['hc_number'] ?? null;

if (!$form_id || !$hc_number) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$script = BASE_PATH . '/scrapping/scrape_log_admision.py';
$command = sprintf(
    '/usr/bin/python3 %s %s %s',
    escapeshellarg($script),
    escapeshellarg((string)$form_id),
    escapeshellarg((string)$hc_number)
);
$output = shell_exec($command);

echo json_encode(['success' => true, 'output' => $output]);