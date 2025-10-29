<?php
require_once __DIR__ . '/../bootstrap.php';

use Models\DiagnosticoModel;

try {
    $modelo = new DiagnosticoModel($pdo); // $pdo ya viene desde bootstrap
    echo "✅ Modelo DiagnosticoModel cargado correctamente.";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage();
}