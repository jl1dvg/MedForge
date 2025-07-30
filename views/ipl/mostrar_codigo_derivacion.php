<?php
$form_id = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
$hc_number = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;

if (!$form_id || !$hc_number) {
    echo "❌ Faltan parámetros para ejecutar el scraping.";
    exit;
}

$command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py " . escapeshellarg($form_id) . " " . escapeshellarg($hc_number);
$output = shell_exec($command);

echo "<h3>Resultado del scraping:</h3>";
echo "<pre style='background:#f8f9fa;border:1px solid #ccc;padding:10px;border-radius:5px;'>" . htmlspecialchars($output) . "</pre>";