<?php
require_once __DIR__ . '/../bootstrap.php'; // Ajusta la ruta según tu estructura

echo "== Iniciando migración de visitas ==\n";
$pdo->beginTransaction();
$migrados = 0;
$errores = 0;

// 1. Buscar todas las combinaciones únicas de paciente + fecha
$stmt = $pdo->query("SELECT DISTINCT hc_number, DATE(fecha) as fecha_visita FROM procedimiento_proyectado WHERE fecha IS NOT NULL");
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    foreach ($registros as $r) {
        if (empty($r['fecha_visita'])) {
            echo "⛔ Saltando paciente {$r['hc_number']} por fecha_visita vacía o nula\n";
            $errores++;
            continue;
        }

        // Buscar la primera hora real para ese paciente y fecha
        $stmtHora = $pdo->prepare("SELECT MIN(hora) as hora_min FROM procedimiento_proyectado WHERE hc_number = ? AND DATE(fecha) = ?");
        $stmtHora->execute([$r['hc_number'], $r['fecha_visita']]);
        $hora_min = $stmtHora->fetchColumn();

        $hora_llegada = null;
        if (!empty($hora_min)) {
            // Combinar fecha y hora
            $hora_llegada = $r['fecha_visita'] . ' ' . $hora_min;
        }

        echo "Procesando paciente {$r['hc_number']} - Fecha: {$r['fecha_visita']}... ";

        // 2. Crear o buscar la visita correspondiente
        $stmt2 = $pdo->prepare("SELECT id FROM visitas WHERE hc_number = ? AND fecha_visita = ?");
        $stmt2->execute([$r['hc_number'], $r['fecha_visita']]);
        $visita_id = $stmt2->fetchColumn();

        if (!$visita_id) {
            $stmt3 = $pdo->prepare("INSERT INTO visitas (hc_number, fecha_visita, hora_llegada, usuario_registro) VALUES (?, ?, ?, 'migracion')");
            $stmt3->execute([$r['hc_number'], $r['fecha_visita'], $hora_llegada]);
            $visita_id = $pdo->lastInsertId();
            echo "Visita creada (ID $visita_id). ";
        } else {
            // Actualizar la hora de llegada si la visita ya existe
            $stmtUp = $pdo->prepare("UPDATE visitas SET hora_llegada = ? WHERE id = ?");
            $stmtUp->execute([$hora_llegada, $visita_id]);
            echo "Visita existente (ID $visita_id, hora_llegada actualizada). ";
        }

        // 3. Actualizar los trayectos antiguos para que apunten a la visita correcta
        $stmt4 = $pdo->prepare("UPDATE procedimiento_proyectado SET visita_id = ? WHERE hc_number = ? AND DATE(fecha) = ?");
        $stmt4->execute([$visita_id, $r['hc_number'], $r['fecha_visita']]);
        $filas = $stmt4->rowCount();
        echo "Trayectos actualizados: $filas\n";
        $migrados += $filas;
    }
    $pdo->commit();
    echo "\n== ¡Migración terminada con éxito! ==\n";
    echo "Total de trayectos migrados: $migrados\n";
    echo "Total de trayectos saltados por error: $errores\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n== Error en migración: " . $e->getMessage() . " ==\n";
}