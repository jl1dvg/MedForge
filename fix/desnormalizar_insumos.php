<?php
require_once __DIR__ . '/../bootstrap.php';

try {
    // Crear tabla protocolo_insumos si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS protocolo_insumos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        protocolo_id INT NOT NULL,
        insumo_id VARCHAR(50) NOT NULL,
        nombre TEXT,
        cantidad INT,
        categoria ENUM('anestesia','quirurgicos','equipos'),
        FOREIGN KEY (protocolo_id) REFERENCES protocolo_data(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "✅ Tabla protocolo_insumos creada o ya existía.\n";

    // Obtener insumos desde protocolo_data
    $stmt = $pdo->query("SELECT id, insumos FROM protocolo_data WHERE insumos IS NOT NULL");
    $protocolos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $pdo->prepare("INSERT INTO protocolo_insumos (protocolo_id, insumo_id, nombre, cantidad, categoria)
                                 VALUES (:protocolo_id, :insumo_id, :nombre, :cantidad, :categoria)");

    $registrosInsertados = 0;
    foreach ($protocolos as $protocolo) {
        $categorias = json_decode($protocolo['insumos'], true);
        if (is_array($categorias)) {
            foreach ($categorias as $categoria => $insumos) {
                if (is_array($insumos)) {
                    foreach ($insumos as $insumo) {
                        $insertStmt->execute([
                            ':protocolo_id' => $protocolo['id'],
                            ':insumo_id' => $insumo['id'] ?? '',
                            ':nombre' => $insumo['nombre'] ?? '',
                            ':cantidad' => $insumo['cantidad'] ?? 1,
                            ':categoria' => $categoria
                        ]);
                        $registrosInsertados++;
                    }
                }
            }
        }
    }

    echo "✅ Total de insumos insertados: $registrosInsertados\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}