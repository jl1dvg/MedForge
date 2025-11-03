<?php
require_once __DIR__ . '/../../../bootstrap.php';

$form_id = $_POST['form_id'] ?? null;
$hc_number = $_POST['hc_number'] ?? null;

if (!$form_id || !$hc_number) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py " . escapeshellarg($form_id) . " " . escapeshellarg($hc_number);
$output = shell_exec($command);

echo json_encode(['success' => true, 'output' => $output]);