<?php
require_once __DIR__ . '/../bootstrap.php';

$archivo = __DIR__ . '/data/tarifario_2014_limpio.csv';

if (!file_exists($archivo)) {
    die('Archivo no encontrado.');
}

$pdo->beginTransaction();
$insertados = 0;

if (($handle = fopen($archivo, 'r')) !== false) {
    $encabezado = fgetcsv($handle); // omitir encabezado
    while (($datos = fgetcsv($handle)) !== false) {
        if (count($datos) !== 11) continue;

        $stmt = $pdo->prepare("
            INSERT INTO tarifario_2014 (
                codigo, descripcion, uvr1, uvr2, uvr3,
                valor_facturar_nivel1, valor_facturar_nivel2, valor_facturar_nivel3,
                anestesia_nivel1, anestesia_nivel2, anestesia_nivel3
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute($datos)) {
            $insertados++;
        }
    }
    fclose($handle);
    $pdo->commit();
    echo "Importación completada. Total registros insertados: $insertados";
} else {
    echo "No se pudo abrir el archivo CSV.";
}