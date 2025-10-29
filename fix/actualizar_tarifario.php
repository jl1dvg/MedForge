<?php
require_once __DIR__ . '/../bootstrap.php'; // Ajusta la ruta si es necesario

$csvFile = __DIR__ . '/../public/uploads/billing_procedimientos.csv'; // Ajusta si está en otro lado

if (!file_exists($csvFile)) {
    die("Archivo CSV no encontrado.");
}

$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("No se pudo abrir el archivo.");
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("UPDATE tarifario_2014 SET valor_facturar_nivel1 = ? WHERE codigo = ?");

    $linea = 0;
    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        $linea++;
        if (count($data) < 3) continue; // Evita filas incompletas

        $codigo = trim($data[0]);
        $precio = (float)str_replace(',', '.', $data[2]); // Por si el CSV usa coma decimal

        if ($codigo && $precio > 0) {
            $stmt->execute([$precio, $codigo]);
        }
    }

    $pdo->commit();
    fclose($handle);

    echo "✅ Tarifario actualizado correctamente.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error al actualizar: " . $e->getMessage();
}