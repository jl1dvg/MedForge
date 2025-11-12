<?php

namespace Controllers;

use PDO;

class GuardarProyeccionController
{
    /**
     * Verifica si una fecha es válida para una visita.
     * No acepta fechas nulas, menores al 2000, "0000-00-00", ni 1969, etc.
     */
    private function fechaValida($fecha)
    {
        if (empty($fecha)) return false;
        $ts = strtotime($fecha);
        // Rechazar fechas menores al año 2000, o null, o claramente inválidas
        return ($ts && $ts > strtotime('2000-01-01'));
    }

    private function minutosEntreFechas(?string $inicio, ?string $fin): ?float
    {
        if (!$inicio || !$fin) {
            return null;
        }

        $inicioTs = strtotime($inicio);
        $finTs = strtotime($fin);

        if (!$inicioTs || !$finTs || $finTs < $inicioTs) {
            return null;
        }

        return round(($finTs - $inicioTs) / 60, 2);
    }

    private function construirLineaTiempo(array $historial, ?string $citaProgramada, ?string $horaLlegada): array
    {
        $lineaTiempo = [];
        $primerasMarcas = [];
        $referenciaAnterior = $horaLlegada ?? $citaProgramada;

        foreach ($historial as $evento) {
            $marca = $evento['fecha_hora_cambio'] ?? null;
            if (!$marca) {
                continue;
            }

            $lineaTiempo[] = [
                'estado' => $evento['estado'],
                'fecha_hora_cambio' => $marca,
                'minutos_desde_cita' => $this->minutosEntreFechas($citaProgramada, $marca),
                'minutos_desde_llegada' => $this->minutosEntreFechas($horaLlegada, $marca),
                'minutos_desde_anterior' => $this->minutosEntreFechas($referenciaAnterior, $marca),
            ];

            if (!isset($primerasMarcas[$evento['estado']])) {
                $primerasMarcas[$evento['estado']] = $marca;
            }

            $referenciaAnterior = $marca;
        }

        $metricas = [
            'espera_desde_cita' => $this->minutosEntreFechas(
                $citaProgramada,
                $primerasMarcas['LLEGADO'] ?? $primerasMarcas['OPTOMETRIA'] ?? null
            ),
            'espera_hasta_optometria' => $this->minutosEntreFechas(
                $horaLlegada ?? $primerasMarcas['LLEGADO'] ?? null,
                $primerasMarcas['OPTOMETRIA'] ?? null
            ),
            'duracion_optometria' => $this->minutosEntreFechas(
                $primerasMarcas['OPTOMETRIA'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? $primerasMarcas['DILATAR'] ?? null
            ),
            'tiempo_total' => $this->minutosEntreFechas(
                $horaLlegada ?? $primerasMarcas['LLEGADO'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? $primerasMarcas['DILATAR'] ?? null
            ),
            'duracion_dilatacion' => $this->minutosEntreFechas(
                $primerasMarcas['DILATAR'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? null
            ),
        ];

        return [
            'linea_tiempo' => $lineaTiempo,
            'metricas' => array_filter($metricas, static fn($valor) => $valor !== null),
            'primeras_marcas' => $primerasMarcas,
        ];
    }

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

        // Proteger campos de nombre para evitar nulos
        $data['lname'] = $data['lname'] ?? 'DESCONOCIDO';
        $data['fname'] = $data['fname'] ?? '';
        $data['mname'] = $data['mname'] ?? '';
        $data['lname2'] = $data['lname2'] ?? '';

        // Guardar datos del paciente SIEMPRE antes de crear o actualizar la visita
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
            ':lname' => $data['lname'],
            ':lname2' => $data['lname2'],
            ':fname' => $data['fname'],
            ':mname' => $data['mname'],
            ':afiliacion' => $data['afiliacion'] ?? null,
            ':caducidad' => $data['fechaCaducidad'] ?? null,
        ]);

        // 1. Verifica si form_id ya existe y tiene visita_id asignado
        $stmtCheckVisita = $this->db->prepare("SELECT visita_id FROM procedimiento_proyectado WHERE form_id = ?");
        $stmtCheckVisita->execute([$form_id]);
        $visita_id_db = $stmtCheckVisita->fetchColumn();
        $visita_id = null;
        $usando_visita_existente = false;
        if ($visita_id_db) {
            $visita_id = $visita_id_db;
            $usando_visita_existente = true;
            error_log("🟢 Usando visita_id ya existente para form_id $form_id: $visita_id");
        }

        // Lógica defensiva para la fecha de visita
        if (!empty($data['fecha']) && $this->fechaValida($data['fecha'])) {
            $fecha_visita = date('Y-m-d', strtotime($data['fecha']));
        } else {
            $fecha_visita = date('Y-m-d'); // fallback seguro
            error_log("❗ Fecha inválida recibida para visita. Se usó la fecha actual: $fecha_visita");
        }
        $hc_number = $data['hcNumber'];

        // Antes de crear o actualizar visita, si la fecha es inválida, abortar
        if (!$this->fechaValida($fecha_visita)) {
            error_log("❌ No se puede crear/actualizar visita con fecha inválida: $fecha_visita");
            throw new \Exception("❌ No se puede crear/actualizar visita con fecha inválida: $fecha_visita");
        }

        // Solo crear/actualizar visita si no se está usando un visita_id ya existente
        if (!$usando_visita_existente) {
            // Buscar la hora más temprana para esa fecha y paciente
            $sqlHora = "SELECT MIN(hora) FROM procedimiento_proyectado WHERE hc_number = ? AND fecha = ?";
            $stmtHora = $this->db->prepare($sqlHora);
            $stmtHora->execute([$hc_number, $fecha_visita]);
            $hora_llegada = $stmtHora->fetchColumn() ?: '08:00:00'; // Valor por defecto si no hay hora
            $hora_llegada_completa = $fecha_visita . ' ' . $hora_llegada;

            // Busca si ya existe visita hoy
            $stmt = $this->db->prepare("SELECT id FROM visitas WHERE hc_number = ? AND fecha_visita = ?");
            $stmt->execute([$hc_number, $fecha_visita]);
            $visita_id_encontrada = $stmt->fetchColumn();

            if (!$visita_id_encontrada) {
                // Crea la visita si no existe, con la hora más temprana
                $usuario = $data['usuario'] ?? 'sistema';
                $stmt = $this->db->prepare("INSERT INTO visitas (hc_number, fecha_visita, hora_llegada, usuario_registro) VALUES (?, ?, ?, ?)");
                $stmt->execute([$hc_number, $fecha_visita, $hora_llegada_completa, $usuario]);
                $visita_id = $this->db->lastInsertId();
                error_log("🆕 Visita creada para paciente $hc_number en fecha $fecha_visita con id $visita_id");
            } else {
                // Si ya existe, actualizar la hora_llegada si es necesario (siempre ponemos la más temprana)
                $stmt = $this->db->prepare("UPDATE visitas SET hora_llegada = ? WHERE id = ?");
                $stmt->execute([$hora_llegada_completa, $visita_id_encontrada]);
                $visita_id = $visita_id_encontrada;
                error_log("♻️ Visita existente actualizada para paciente $hc_number en fecha $fecha_visita, id $visita_id");
            }
        } else {
            error_log("⛔ No se crea ni actualiza visita porque form_id $form_id ya tiene visita_id $visita_id asignado.");
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

        // Guardar procedimiento proyectado con más campos (incluye visita_id)
        // No se debe sobreescribir visita_id si ya tenía uno
        // Si el registro existe y ya tiene visita_id, NO lo cambiamos
        $sql = "
            INSERT INTO procedimiento_proyectado 
                (form_id, procedimiento_proyectado, doctor, hc_number, sede_departamento, id_sede, estado_agenda, afiliacion, fecha, hora, visita_id)
            VALUES 
                (:form_id, :procedimiento, :doctor, :hc, :sede_departamento, :id_sede, :estado_agenda, :afiliacion, :fecha, :hora, :visita_id)
            ON DUPLICATE KEY UPDATE 
                procedimiento_proyectado = VALUES(procedimiento_proyectado),
                doctor = VALUES(doctor),
                sede_departamento = VALUES(sede_departamento),
                id_sede = VALUES(id_sede),
                estado_agenda = IFNULL(VALUES(estado_agenda), estado_agenda),
                afiliacion = IF(VALUES(afiliacion) != 'SIN COBERTURA', VALUES(afiliacion), afiliacion),
                fecha = VALUES(fecha),
                hora = VALUES(hora)
                -- visita_id NO se actualiza si ya existía
        ";

        error_log("📤 Datos enviados a procedimiento_proyectado: " . json_encode([
                'form_id' => $form_id,
                'procedimiento' => $procedimiento,
                'doctor' => $doctor,
                'hc' => $hcNumber,
                'sede_departamento' => $data['sede_departamento'] ?? null,
                'id_sede' => $data['id_sede'] ?? null,
                'estado_agenda' => $exists ? null : 'AGENDADO',
                'afiliacion' => $data['afiliacion'] ?? null,
                'fecha' => $data['fecha'] ?? null,
                'hora' => $data['hora'] ?? null,
                'visita_id' => $visita_id
            ]));

        $stmt2 = $this->db->prepare($sql);
        $stmt2->execute([
            ':form_id' => $form_id,
            ':procedimiento' => $procedimiento,
            ':doctor' => $doctor,
            ':hc' => $hcNumber,
            ':sede_departamento' => $data['sede_departamento'] ?? null,
            ':id_sede' => $data['id_sede'] ?? null,
            // Cambia la lógica de estado_agenda según si existe el form_id
            ':estado_agenda' => $exists ? null : 'AGENDADO',
            ':afiliacion' => $data['afiliacion'] ?? null,
            ':fecha' => $data['fecha'] ?? null,
            ':hora' => $data['hora'] ?? null,
            ':visita_id' => $visita_id
        ]);

        if ($exists && $visita_id_db) {
            error_log("🛡️ visita_id NO modificado para form_id $form_id porque ya tenía asignado: $visita_id_db");
        } elseif ($exists && !$visita_id_db) {
            error_log("⚠️ Registro existente SIN visita_id previo, se asignó: $visita_id");
        } elseif (!$exists) {
            error_log("🆕 Nuevo registro creado en procedimiento_proyectado con visita_id: $visita_id");
        }

        $ejecutado = $stmt2->rowCount();
        error_log("📌 Registros afectados en procedimiento_proyectado: $ejecutado");

        // Registrar en el historial si se ha insertado o actualizado
        if ($exists && !empty($data['estado_agenda'])) {
            // Ver estado anterior
            $stmtEstado = $this->db->prepare("SELECT estado_agenda FROM procedimiento_proyectado WHERE form_id = ?");
            $stmtEstado->execute([$form_id]);
            $estadoActual = $stmtEstado->fetchColumn();

            if ($estadoActual !== $data['estado_agenda']) {
                $stmtHistorial = $this->db->prepare("
                    INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)
                    VALUES (?, ?, NOW())
                ");
                $stmtHistorial->execute([
                    $form_id,
                    $data['estado_agenda']
                ]);
            }
        } elseif (!$exists && !empty($data['estado_agenda'])) {
            // Insertar directamente para nuevos registros
            $stmtHistorial = $this->db->prepare("
                INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)
                VALUES (?, ?, NOW())
            ");
            $stmtHistorial->execute([
                $form_id,
                $data['estado_agenda']
            ]);
        }

        // Nueva lógica de éxito/error según existencia y filas afectadas
        $estadoActual = $this->obtenerEstado($form_id);

        if (!$exists && $ejecutado === 0) {
            return [
                "success" => false,
                "message" => "No se insertó ningún nuevo registro en procedimiento_proyectado.",
                "id" => $form_id,
                "form_id" => $form_id,
                "afiliacion" => $data['afiliacion'] ?? null,
                "estado" => $estadoActual,
                "hc_number" => $hcNumber,
                "visita_id" => $visita_id,
            ];
        }

        return [
            "success" => true,
            "message" => $exists ? "Registro actualizado o ya existente sin cambios" : "Nuevo registro insertado",
            "id" => $form_id,
            "form_id" => $form_id,
            "afiliacion" => $data['afiliacion'] ?? null,
            "estado" => $estadoActual,
            "hc_number" => $hcNumber,
            "visita_id" => $visita_id,
        ];
    }

    public function obtenerEstado($formId): ?string
    {
        if (!$formId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT estado_agenda FROM procedimiento_proyectado WHERE form_id = :form_id LIMIT 1");
        $stmt->execute([':form_id' => $formId]);
        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual !== false && $estadoActual !== null && trim($estadoActual) !== '') {
            return $estadoActual;
        }

        $historialStmt = $this->db->prepare("SELECT estado FROM procedimiento_proyectado_estado WHERE form_id = :form_id ORDER BY fecha_hora_cambio DESC LIMIT 1");
        $historialStmt->execute([':form_id' => $formId]);
        $estadoHistorial = $historialStmt->fetchColumn();

        return $estadoHistorial !== false ? $estadoHistorial : null;
    }

    public function obtenerFlujoPacientesPorVisita($fecha = null): array
    {
        // 1. Saca todas las visitas del día (con info de paciente)
        $sql = "SELECT 
                v.id AS visita_id,
                v.hc_number,
                v.fecha_visita,
                v.hora_llegada,
                v.usuario_registro,
                v.observaciones,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2
            FROM visitas v
            INNER JOIN patient_data pd ON v.hc_number = pd.hc_number
            WHERE 1";
        $params = [];
        if ($fecha) {
            $sql .= " AND v.fecha_visita = ?";
            $params[] = $fecha;
        }
        $sql .= " ORDER BY v.hora_llegada ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Saca TODOS los procedimientos/trayectos para esas visitas
        $visitaIds = array_column($visitas, 'visita_id');
        if (!$visitaIds) return $visitas;
        $placeholders = implode(',', array_fill(0, count($visitaIds), '?'));
        $sqlTray = "SELECT 
                    pp.id,
                    pp.form_id,
                    pp.visita_id,
                    pp.procedimiento_proyectado AS procedimiento,
                    pp.estado_agenda AS estado,
                    pp.fecha AS fecha_cambio,
                    pp.hora AS hora,
                    pp.doctor AS doctor,
                    pp.afiliacion AS afiliacion
                FROM procedimiento_proyectado pp
                WHERE pp.visita_id IN ($placeholders)
                ORDER BY pp.hora ASC";
        $stmtTray = $this->db->prepare($sqlTray);
        $stmtTray->execute($visitaIds);
        $trayectos = $stmtTray->fetchAll(PDO::FETCH_ASSOC);

        // 3. Saca todos los historiales de esos form_id
        $formIds = array_column($trayectos, 'form_id');
        $historiales = [];
        if ($formIds) {
            $ph = implode(',', array_fill(0, count($formIds), '?'));
            $histStmt = $this->db->prepare(
                "SELECT form_id, estado, fecha_hora_cambio
             FROM procedimiento_proyectado_estado
             WHERE form_id IN ($ph)
             ORDER BY form_id ASC, fecha_hora_cambio ASC"
            );
            $histStmt->execute($formIds);
            while ($row = $histStmt->fetch(PDO::FETCH_ASSOC)) {
                $historiales[$row['form_id']][] = [
                    'estado' => $row['estado'],
                    'fecha_hora_cambio' => $row['fecha_hora_cambio']
                ];
            }
        }

        // 4. Agrupa los trayectos/procedimientos en la visita
        $trayectosPorVisita = [];
        foreach ($trayectos as $t) {
            $t['historial_estados'] = $historiales[$t['form_id']] ?? [];
            $trayectosPorVisita[$t['visita_id']][] = $t;
        }

        // 5. Inserta los trayectos en cada visita
        foreach ($visitas as &$v) {
            $trayectosVisita = $trayectosPorVisita[$v['visita_id']] ?? [];
            $horaLlegada = $v['hora_llegada'] ?? null;

            foreach ($trayectosVisita as &$trayecto) {
                $historialTrayecto = $trayecto['historial_estados'] ?? [];
                $citaProgramada = (!empty($trayecto['fecha_cambio']) && !empty($trayecto['hora']))
                    ? $trayecto['fecha_cambio'] . ' ' . $trayecto['hora']
                    : null;

                $detalle = $this->construirLineaTiempo($historialTrayecto, $citaProgramada, $horaLlegada);
                $trayecto['linea_tiempo'] = $detalle['linea_tiempo'];
                $trayecto['metricas'] = $detalle['metricas'];
                $trayecto['primeras_marcas'] = $detalle['primeras_marcas'];
            }
            unset($trayecto);

            $v['trayectos'] = $trayectosVisita;
        }
        unset($v);

        return $visitas;
    }

    public function obtenerFlujoPacientes($fecha = null): array
    {
        $sql = "SELECT
                pp.id,
                pp.form_id,
                pp.hc_number,
                pp.procedimiento_proyectado AS procedimiento,
                pp.estado_agenda AS estado,
                pp.fecha AS fecha_cambio,
                pp.hora AS hora,
                pp.doctor AS doctor,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2,
                pp.afiliacion,
                v.id AS visita_id,
                v.fecha_visita,
                v.hora_llegada
            FROM procedimiento_proyectado pp
            INNER JOIN patient_data pd ON pp.hc_number = pd.hc_number
            LEFT JOIN visitas v ON pp.visita_id = v.id
            WHERE 1 ";
        $params = [];
        if ($fecha) {
            $sql .= " AND v.fecha_visita = ? ";
            $params[] = $fecha;
        }
        $sql .= " ORDER BY pp.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Optimización: Consulta única para todos los historiales
        $formIds = array_column($solicitudes, 'form_id');
        if (!$formIds) return $solicitudes;

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $histStmt = $this->db->prepare(
            "SELECT form_id, estado, fecha_hora_cambio
             FROM procedimiento_proyectado_estado
             WHERE form_id IN ($placeholders)
             ORDER BY form_id ASC, fecha_hora_cambio ASC"
        );
        $histStmt->execute($formIds);

        // Agrupa los historiales por form_id
        $historiales = [];
        while ($row = $histStmt->fetch(PDO::FETCH_ASSOC)) {
            $historiales[$row['form_id']][] = [
                'estado' => $row['estado'],
                'fecha_hora_cambio' => $row['fecha_hora_cambio']
            ];
        }

        // Asocia el historial a cada solicitud
        foreach ($solicitudes as &$sol) {
            $historial = $historiales[$sol['form_id']] ?? [];
            $sol['historial_estados'] = $historial;

            $citaProgramada = (!empty($sol['fecha_cambio']) && !empty($sol['hora']))
                ? $sol['fecha_cambio'] . ' ' . $sol['hora']
                : null;
            $detalle = $this->construirLineaTiempo($historial, $citaProgramada, $sol['hora_llegada'] ?? null);

            $sol['linea_tiempo'] = $detalle['linea_tiempo'];
            $sol['metricas'] = $detalle['metricas'];
            $sol['primeras_marcas'] = $detalle['primeras_marcas'];
        }
        unset($sol);

        return $solicitudes;
    }

    public function obtenerLineaTiempoAtencion($formId): array
    {
        if (!$formId) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, estado_agenda, afiliacion, visita_id
             FROM procedimiento_proyectado
             WHERE form_id = :form_id
             LIMIT 1"
        );
        $stmt->execute([':form_id' => $formId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            return [];
        }

        $visita = null;
        if (!empty($info['visita_id'])) {
            $stmtVisita = $this->db->prepare(
                "SELECT fecha_visita, hora_llegada
                 FROM visitas
                 WHERE id = :visita_id
                 LIMIT 1"
            );
            $stmtVisita->execute([':visita_id' => $info['visita_id']]);
            $visita = $stmtVisita->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $histStmt = $this->db->prepare(
            "SELECT estado, fecha_hora_cambio
             FROM procedimiento_proyectado_estado
             WHERE form_id = :form_id
             ORDER BY fecha_hora_cambio ASC"
        );
        $histStmt->execute([':form_id' => $formId]);
        $historial = $histStmt->fetchAll(PDO::FETCH_ASSOC);

        $citaProgramada = (!empty($info['fecha']) && !empty($info['hora']))
            ? $info['fecha'] . ' ' . $info['hora']
            : null;
        $horaLlegada = $visita['hora_llegada'] ?? null;

        $detalle = $this->construirLineaTiempo($historial, $citaProgramada, $horaLlegada);

        return [
            'form_id' => (int) $info['form_id'],
            'visita_id' => $info['visita_id'] ? (int) $info['visita_id'] : null,
            'hc_number' => $info['hc_number'],
            'afiliacion' => $info['afiliacion'] ?? null,
            'procedimiento' => $info['procedimiento_proyectado'],
            'doctor' => $info['doctor'],
            'cita_programada' => $citaProgramada,
            'hora_llegada' => $horaLlegada,
            'estado_actual' => $this->obtenerEstado($formId) ?? $info['estado_agenda'],
            'linea_tiempo' => $detalle['linea_tiempo'],
            'metricas' => $detalle['metricas'],
            'primeras_marcas' => $detalle['primeras_marcas'],
            'historial' => $historial,
        ];
    }

    public function actualizarEstado($formId, $nuevoEstado): array
    {
        error_log("🟣 Intentando actualizar estado: form_id=$formId, nuevoEstado=$nuevoEstado");
        $select = $this->db->prepare("SELECT estado_agenda FROM procedimiento_proyectado WHERE form_id = :form_id LIMIT 1");
        $select->execute([':form_id' => $formId]);
        $estadoActual = $select->fetchColumn();

        if ($estadoActual === false) {
            error_log("🔴 El form_id $formId NO existe en procedimiento_proyectado");
            return ['success' => false, 'message' => 'El form_id no existe en la tabla procedimiento_proyectado'];
        }

        if ($estadoActual === $nuevoEstado) {
            error_log("🟠 El estado solicitado ya estaba registrado. No se realizan cambios adicionales.");
            return ['success' => true, 'message' => 'Estado ya estaba registrado'];
        }

        $sql = "UPDATE procedimiento_proyectado SET estado_agenda = :estado WHERE form_id = :form_id AND estado_agenda <> :estado";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':estado' => $nuevoEstado,
            ':form_id' => $formId
        ]);
        error_log("🔵 UPDATE ejecutado. Filas afectadas: " . $stmt->rowCount());
        if ($stmt->rowCount() > 0) {
            $sql2 = "INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)"
                     . " VALUES (?, ?, NOW())";
            $this->db->prepare($sql2)->execute([$formId, $nuevoEstado]);
            return ['success' => true, 'message' => 'Estado actualizado'];
        }

        error_log("🟤 No se registraron cambios de estado para form_id $formId");
        return ['success' => false, 'message' => 'No se pudo actualizar el estado.'];
    }

    public function getCambiosRecientes()
    {
        $ultimoTimestamp = $_GET['desde'] ?? null;

        $query = "SELECT * FROM procedimiento_proyectado";
        if ($ultimoTimestamp) {
            $query .= " WHERE updated_at > ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$ultimoTimestamp]);
        } else {
            $stmt = $this->db->query($query);
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'pacientes' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function obtenerDatosPacientePorFormId($formId): ?array
    {
        $sql = "
        SELECT 
            pp.procedimiento_proyectado AS procedimiento,
            pp.doctor AS doctor,
            pp.fecha AS fecha,
            pd.fname, pd.mname, pd.lname, pd.lname2
        FROM procedimiento_proyectado pp
        INNER JOIN patient_data pd ON pp.hc_number = pd.hc_number
        WHERE pp.form_id = ?
        LIMIT 1
    ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $nombreCompleto = trim("{$row['fname']} {$row['mname']} {$row['lname']} {$row['lname2']}");
        return [
            'nombre' => $nombreCompleto,
            'procedimiento' => $row['procedimiento'],
            'doctor' => $row['doctor'],
            'fecha' => $row['fecha'],
        ];
    }

    public function obtenerPalabrasClaveProcedimientos(): array
    {
        $sql = "SELECT DISTINCT procedimiento_proyectado FROM procedimiento_proyectado WHERE procedimiento_proyectado IS NOT NULL";
        $stmt = $this->db->query($sql);
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $palabras = [];
        foreach ($resultados as $texto) {
            // Separar por espacios y caracteres especiales comunes
            $tokens = preg_split('/[\s,;.()\-]+/', strtoupper($texto));
            foreach ($tokens as $token) {
                $token = trim($token);
                if (strlen($token) >= 4 && !is_numeric($token)) {
                    $palabras[] = $token;
                }
            }
        }

        // Contar ocurrencias
        $frecuencia = array_count_values($palabras);
        arsort($frecuencia);

        // Opcional: devolver solo las 100 más frecuentes
        return array_slice($frecuencia, 0, 100, true);
    }

    public function obtenerPacientesPorEstado(string $estado, ?string $fecha = null)
    {
        // Usa fecha actual como predeterminada si no se proporciona
        $fecha = $fecha ?? date('Y-m-d');

        $sql = "SELECT form_id 
            FROM procedimiento_proyectado 
            WHERE estado_agenda = :estado 
            AND fecha = :fecha";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'estado' => $estado,
            'fecha' => $fecha
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}