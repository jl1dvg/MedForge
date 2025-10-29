<?php
require_once __DIR__ . '/../bootstrap.php';

try {
    // Preparar la tabla destino
    $pdo->exec("CREATE TABLE IF NOT EXISTS diagnosticos_asignados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_id INT NOT NULL,
        fuente ENUM('consulta', 'protocolo') NOT NULL,
        dx_code VARCHAR(10),
        descripcion TEXT,
        definitivo TINYINT(1),
        lateralidad VARCHAR(20),
        selector VARCHAR(255),
        FOREIGN KEY (form_id) REFERENCES procedimiento_proyectado(form_id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "✅ Tabla diagnosticos_asignados verificada o creada.\n";

    // Preparar inserción
    $insertStmt = $pdo->prepare("INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                                 VALUES (:form_id, :fuente, :dx_code, :descripcion, :definitivo, :lateralidad, :selector)");

    $total = 0;

    // Función de desnormalización
    function desnormalizar($pdo, $table, $fuente, $insertStmt, &$total)
    {
        $query = "SELECT form_id, diagnosticos FROM $table WHERE diagnosticos IS NOT NULL";
        $result = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM procedimiento_proyectado WHERE form_id = ?");
        foreach ($result as $row) {
            $checkStmt->execute([$row['form_id']]);
            if ($checkStmt->fetchColumn() == 0) continue;

            $diagnosticos = json_decode($row['diagnosticos'], true);
            if (!is_array($diagnosticos)) continue;

            foreach ($diagnosticos as $dx) {
                $insertStmt->execute([
                    ':form_id' => $row['form_id'],
                    ':fuente' => $fuente,
                    ':dx_code' => isset($dx['idDiagnostico']) ? explode(' - ', $dx['idDiagnostico'])[0] : null,
                    ':descripcion' => isset($dx['idDiagnostico']) ? explode(' - ', $dx['idDiagnostico'])[1] ?? null : null,
                    ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                    ':lateralidad' => $dx['ojo'] ?? null,
                    ':selector' => $dx['selector'] ?? null
                ]);
                $total++;
            }
        }
    }

    desnormalizar($pdo, 'consulta_data', 'consulta', $insertStmt, $total);
    desnormalizar($pdo, 'protocolo_data', 'protocolo', $insertStmt, $total);

    echo "✅ Diagnósticos desnormalizados: $total filas insertadas.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}