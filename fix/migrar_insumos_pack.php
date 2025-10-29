<?php

require_once __DIR__ . '/../bootstrap.php';

// 1. Crear tabla relacional si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS insumos_protocolo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocolo_id VARCHAR(50) NOT NULL,
    insumo_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    FOREIGN KEY (protocolo_id) REFERENCES protocolo_data(form_id) ON DELETE CASCADE,
    FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE
)");

// 2. Obtener todos los registros de insumos_pack
$query = $pdo->query("SELECT procedimiento_id, insumos FROM insumos_pack");
$rows = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $protocoloId = $row['procedimiento_id'];
    $json = $row['insumos'];

    if (!$json) continue;

    $items = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Error de JSON en protocolo_id: $protocoloId - " . json_last_error_msg() . "\n";
        continue;
    }
    if (!is_array($items)) {
        echo "❌ JSON inválido en protocolo_id: $protocoloId\n";
        continue;
    }

    foreach ($items as $item) {
        $insumoId = $item['id'] ?? $item['codigo'] ?? null;
        $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 1;

        echo "Procesando insumo_id: $insumoId con cantidad: $cantidad para protocolo_id: $protocoloId\n";

        if (!$insumoId || $cantidad <= 0) {
            echo "⚠️ Insumo inválido o cantidad no válida.\n";
            continue;
        }

        $stmt = $pdo->prepare("SELECT id FROM insumos WHERE id = ?");
        $stmt->execute([$insumoId]);
        if (!$stmt->fetchColumn()) {
            echo "🚫 Insumo con ID $insumoId no existe en la tabla insumos.\n";
            continue;
        }

        $insert = $pdo->prepare("INSERT INTO insumos_protocolo (protocolo_id, insumo_id, cantidad)
                                 VALUES (?, ?, ?)");
        $insert->execute([$protocoloId, $insumoId, $cantidad]);

        echo "✅ Insertado insumo_id: $insumoId con cantidad: $cantidad\n";
    }
}

echo "\n✅ Migración completada correctamente. Puedes revisar la tabla insumos_protocolo.";