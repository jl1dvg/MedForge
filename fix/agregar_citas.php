<?php
require_once __DIR__ . '/../bootstrap.php'; // Ajusta la ruta según tu estructura

$pdo->exec("
    CREATE TABLE IF NOT EXISTS log_sincronizacion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        total_citas INT DEFAULT 0,
        nuevas INT DEFAULT 0,
        actualizadas INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

if (!isset($_GET['fecha_inicio']) || !isset($_GET['fecha_fin'])) {
    echo '<form method="get">
            <label>Fecha inicio: <input type="date" name="fecha_inicio" value="' . date('Y-m-01') . '"></label>
            <label>Fecha fin: <input type="date" name="fecha_fin" value="' . date('Y-m-d') . '"></label>
            <button type="submit">Consultar rango</button>
          </form><hr>';
}

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

function sincronizarCitas($pdo, $fecha)
{
    $url = "http://cive.ddns.net:8085/restful/api-agenda/citas-agendadas";

    $data = [
        "company_id" => "113",
        "fecha" => $fecha,
        "flag" => "3"
    ];

    $options = [
        "http" => [
            "method" => "GET",
            "header" => "Content-Type: application/json\r\n",
            "content" => json_encode($data),
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $resultado = json_decode($response, true);

    if (!isset($resultado['citas']) || empty($resultado['citas'])) {
        echo "⚠️ No hay citas para hoy.\n";
        return;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO procedimiento_proyectado 
        (form_id, procedimiento_proyectado, doctor, hc_number, sede_departamento, id_sede, estado_agenda, afiliacion, fecha, hora)
        VALUES (:form_id, :procedimiento, :doctor, :hc_number, :sede_departamento, :id_sede, :estado_agenda, :afiliacion, :fecha, :hora)
    ");

    $updateStmt = $pdo->prepare("
        UPDATE procedimiento_proyectado SET 
            procedimiento_proyectado = :procedimiento,
            doctor = :doctor,
            hc_number = :hc_number,
            sede_departamento = :sede_departamento,
            id_sede = :id_sede,
            estado_agenda = :estado_agenda,
            afiliacion = :afiliacion,
            fecha = :fecha,
            hora = :hora
        WHERE form_id = :form_id
    ");

    $created = 0;
    $updated = 0;

    foreach ($resultado['citas'] as $cita) {
        $formId = $cita['agenda_id'];

        $checkPaciente = $pdo->prepare("SELECT 1 FROM patient_data WHERE hc_number = ?");
        $checkPaciente->execute([$cita['IDENTIFICACION']]);

        if ($checkPaciente->rowCount() === 0) {
            $insertPaciente = $pdo->prepare("
                INSERT INTO patient_data (hc_number, fname, mname, lname, lname2, afiliacion, celular)
                VALUES (:hc, :fname, :mname, :lname, :lname2, :afiliacion, :celular)
            ");

            $nombres = explode(' ', $cita['NOMBRES'] ?? '');
            $apellidos = explode(' ', $cita['APELLIDOS'] ?? '');

            $insertPaciente->execute([
                ':hc' => $cita['IDENTIFICACION'],
                ':fname' => $nombres[0] ?? null,
                ':mname' => $nombres[1] ?? null,
                ':lname' => $apellidos[0] ?? null,
                ':lname2' => $apellidos[1] ?? null,
                ':afiliacion' => $cita['afiliacion'] ?? null,
                ':celular' => $cita['CELULAR'] ?? null
            ]);
        }

        $checkStmt = $pdo->prepare("SELECT id FROM procedimiento_proyectado WHERE form_id = ?");
        $checkStmt->execute([$formId]);

        $params = [
            ':form_id' => $formId,
            ':procedimiento' => $cita['PROCEDIMIENTO'],
            ':doctor' => $cita['DOCTOR'] ?? null,
            ':hc_number' => $cita['IDENTIFICACION'],
            ':sede_departamento' => $cita['SEDE:DEPARTAMENTO'] ?? null,
            ':id_sede' => $cita['ID_SEDE'] ?? null,
            ':estado_agenda' => $cita['AGENDA-ESTADO'] ?? null,
            ':afiliacion' => $cita['afiliacion'] ?? null,
            ':fecha' => $cita['FECHA'],
            ':hora' => $cita['HORA']
        ];

        if ($checkStmt->rowCount() > 0) {
            $updateStmt->execute($params);
            echo "🔄 Actualizado: form_id {$formId} | HC: {$cita['IDENTIFICACION']} | Procedimiento: {$cita['PROCEDIMIENTO']}\n";
            $updated++;
        } else {
            $insertStmt->execute($params);
            echo "🆕 Insertado: form_id {$formId} | HC: {$cita['IDENTIFICACION']} | Procedimiento: {$cita['PROCEDIMIENTO']}\n";
            $created++;
        }
    }
    $total = $created + $updated;
    echo "✅ Citas sincronizadas.\n";
    echo "🟢 Nuevas insertadas: $created\n";
    echo "🟡 Actualizadas: $updated\n";

    $logStmt = $pdo->prepare("INSERT INTO log_sincronizacion (fecha, total_citas, nuevas, actualizadas) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$fecha, $total, $created, $updated]);
}

$yaSincronizado = $pdo->prepare("SELECT 1 FROM log_sincronizacion WHERE fecha = ?");
$yaSincronizado->execute([date('Y-m-d')]);

if ($yaSincronizado->rowCount() > 0) {
    echo "🔁 Ya se sincronizó hoy. Saliendo...\n";
    return;
}

$start = new DateTime($fechaInicio);
$end = new DateTime($fechaFin);

while ($start <= $end) {
    sincronizarCitas($pdo, $start->format('Y-m-d'));
    $start->modify('+1 day');
}