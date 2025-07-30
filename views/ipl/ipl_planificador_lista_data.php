<?php

require_once __DIR__ . '/../../bootstrap.php';

header("Content-Type: application/json");
try {
    $stmt = $pdo->prepare("
        SELECT 
            hc_number,
            COUNT(*) AS sesiones_planificadas,
            SUM(CASE WHEN estado = 'realizada' THEN 1 ELSE 0 END) AS sesiones_realizadas
        FROM ipl_planificador
        GROUP BY hc_number
        ORDER BY hc_number
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Error al obtener los datos',
        'details' => $e->getMessage()
    ]);
}
?>