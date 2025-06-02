<?php

namespace Controllers;

use PDO;

class GuardarProyeccionController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        error_log("🧪 Payload recibido en el controlador: " . json_encode($data));
        error_log("🛠️ Datos completos recibidos: " . json_encode($data));
        $errores = [];

        // Mapear identificacion a hcNumber si no está definido
        if (!isset($data['hcNumber']) && isset($data['identificacion'])) {
            $data['hcNumber'] = $data['identificacion'];
        }

        // Mapear id a form_id si no está definido
        if (!isset($data['form_id']) && isset($data['id'])) {
            $data['form_id'] = $data['id'];
        }

        if (!isset($data['procedimiento_proyectado']) && isset($data['procedimiento'])) {
            $data['procedimiento_proyectado'] = $data['procedimiento'];
        }

        error_log("📦 Valores después de mapeo: hcNumber={$data['hcNumber']}, form_id={$data['form_id']}, procedimiento_proyectado={$data['procedimiento_proyectado']}");

        // Mapear estado a estado_agenda si no está definido
        if (!isset($data['estado_agenda']) && isset($data['estado'])) {
            $data['estado_agenda'] = $data['estado'];
        }

        $campos = ['hcNumber', 'form_id', 'procedimiento_proyectado'];
        foreach ($campos as $campo) {
            if (empty($data[$campo])) {
                error_log("⚠️ Campo vacío detectado: $campo, valor actual: " . json_encode($data[$campo]));
                $errores[] = $campo;
            }
        }

        if (!empty($errores)) {
            error_log("🔍 Datos inspeccionados: " . json_encode($data));
            error_log("⚠️ Faltan los siguientes campos obligatorios: " . implode(', ', $errores));
            return ["success" => false, "message" => "Datos faltantes o incompletos: " . implode(', ', $errores)];
        }

        $hcNumber = $data['hcNumber'];
        $form_id = $data['form_id'];
        $procedimiento = $data['procedimiento_proyectado'];
        $doctor = $data['doctor'] ?? null;

        // Descomponer nombre completo si faltan campos descompuestos
        if (
            (!isset($data['fname']) || empty($data['fname'])) ||
            (!isset($data['lname']) || empty($data['lname'])) ||
            (!isset($data['mname']) || empty($data['mname'])) ||
            (!isset($data['lname2']) || empty($data['lname2']))
        ) {
            if (isset($data['nombre_completo'])) {
                $partes = explode(' ', trim($data['nombre_completo']));
                $data['fname'] = $partes[0] ?? null;
                $data['mname'] = $partes[1] ?? null;
                $data['lname'] = $partes[2] ?? null;
                $data['lname2'] = isset($partes[3]) ? implode(' ', array_slice($partes, 3)) : null;
            } else {
                error_log("❌ Faltan nombres descompuestos y tampoco se recibió 'nombre_completo'. Datos: " . json_encode($data));
            }
        }

        // Guardar datos del paciente si hay nombres o afiliación
        if (isset($data['lname'], $data['fname'])) {
            $sqlPatient = "
                INSERT INTO patient_data (hc_number, lname, lname2, fname, mname, afiliacion, fecha_caducidad)
                VALUES (:hc, :lname, :lname2, :fname, :mname, :afiliacion, :caducidad)
                ON DUPLICATE KEY UPDATE 
                    lname = VALUES(lname),
                    lname2 = VALUES(lname2),
                    fname = VALUES(fname),
                    mname = VALUES(mname),
                    afiliacion = VALUES(afiliacion),
                    fecha_caducidad = VALUES(fecha_caducidad)
            ";

            $stmt = $this->db->prepare($sqlPatient);
            $stmt->execute([
                ':hc' => $hcNumber,
                ':lname' => $data['lname'] ?? null,
                ':lname2' => $data['lname2'] ?? null,
                ':fname' => $data['fname'] ?? null,
                ':mname' => $data['mname'] ?? null,
                ':afiliacion' => $data['afiliacion'] ?? null,
                ':caducidad' => $data['fechaCaducidad'] ?? null,
            ]);
        }

        // Verificar si form_id ya existe
        $checkSql = "SELECT COUNT(*) FROM procedimiento_proyectado WHERE form_id = :form_id";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([':form_id' => $form_id]);
        $exists = $checkStmt->fetchColumn() > 0;

        if ($exists) {
            error_log("🔄 form_id $form_id ya existe. Se actualizará.");
        } else {
            error_log("➕ form_id $form_id no existe. Se insertará nuevo registro.");
        }

        // Guardar procedimiento proyectado con más campos
        $sql = "
            INSERT INTO procedimiento_proyectado 
                (form_id, procedimiento_proyectado, doctor, hc_number, sede_departamento, id_sede, estado_agenda, afiliacion, fecha, hora)
            VALUES 
                (:form_id, :procedimiento, :doctor, :hc, :sede_departamento, :id_sede, :estado_agenda, :afiliacion, :fecha, :hora)
            ON DUPLICATE KEY UPDATE 
                procedimiento_proyectado = VALUES(procedimiento_proyectado),
                doctor = VALUES(doctor),
                sede_departamento = VALUES(sede_departamento),
                id_sede = VALUES(id_sede),
                estado_agenda = VALUES(estado_agenda),
                afiliacion = VALUES(afiliacion),
                fecha = VALUES(fecha),
                hora = VALUES(hora)
        ";

        $stmt2 = $this->db->prepare($sql);
        $stmt2->execute([
            ':form_id' => $form_id,
            ':procedimiento' => $procedimiento,
            ':doctor' => $doctor,
            ':hc' => $hcNumber,
            ':sede_departamento' => $data['sede_departamento'] ?? null,
            ':id_sede' => $data['id_sede'] ?? null,
            ':estado_agenda' => $data['estado_agenda'] ?? null,
            ':afiliacion' => $data['afiliacion'] ?? null,
            ':fecha' => $data['fecha'] ?? null,
            ':hora' => $data['hora'] ?? null
        ]);

        $ejecutado = $stmt2->rowCount();
        error_log("📌 Registros afectados en procedimiento_proyectado: $ejecutado");

        if ($ejecutado === 0) {
            return ["success" => false, "message" => "No se insertó ni actualizó ningún registro en procedimiento_proyectado."];
        } else {
            return ["success" => true, "message" => "Datos guardados correctamente"];
        }
    }
}