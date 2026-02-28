<?php

namespace Controllers;

use PDO;
use Modules\Shared\Services\SchemaInspector;

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
    private SchemaInspector $schemaInspector;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->schemaInspector = new SchemaInspector($pdo);
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

        // Normalizar nombres sin reparsear nombre_completo para evitar inversiones inconsistentes.
        $data['fname'] = trim((string) ($data['fname'] ?? ''));
        $data['mname'] = trim((string) ($data['mname'] ?? ''));
        $data['lname2'] = trim((string) ($data['lname2'] ?? ''));
        $data['lname'] = trim((string) ($data['lname'] ?? ''));
        if ($data['lname'] === '') {
            $data['lname'] = 'DESCONOCIDO';
        }

        // Guardar datos del paciente SIEMPRE antes de crear o actualizar la visita
        $this->upsertPatientData($hcNumber, $data);

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

        $fecha_normalizada = $this->normalizeDateField($data['fecha'] ?? null);

        // Lógica defensiva para la fecha de visita
        if (!empty($fecha_normalizada) && $this->fechaValida($fecha_normalizada)) {
            $fecha_visita = $fecha_normalizada;
        } else {
            $fecha_visita = date('Y-m-d'); // fallback seguro
            error_log("❗ Fecha inválida recibida para visita. Se usó la fecha actual: $fecha_visita");
        }
        $hc_number = $data['hcNumber'];
        $data['fecha'] = $fecha_normalizada ?: $fecha_visita;
        if (!$this->fechaValida($data['fecha'] ?? '')) {
            $data['fecha'] = $fecha_visita;
        }

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
            ':fecha' => $data['fecha'],
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

        // Registrar en historial solo si el último estado registrado es diferente.
        $this->registrarEstadoHistorialSiCambio((string) $form_id, $data['estado_agenda'] ?? null);

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

    private function upsertPatientData(string $hcNumber, array $data): void
    {
        $fields = $this->buildPatientFields($data);

        $columns = ['hc_number'];
        $placeholders = [':hc_number'];
        $params = [':hc_number' => $hcNumber];

        foreach ($fields as $column => $value) {
            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $value;
        }

        $audit = $this->resolveAuditActor();

        if ($this->schemaInspector->tableHasColumn('patient_data', 'created_by_type')) {
            $columns[] = 'created_by_type';
            $placeholders[] = ':created_by_type';
            $params[':created_by_type'] = $audit['type'];
        }

        if ($this->schemaInspector->tableHasColumn('patient_data', 'created_by_identifier')) {
            $columns[] = 'created_by_identifier';
            $placeholders[] = ':created_by_identifier';
            $params[':created_by_identifier'] = $audit['identifier'];
        }

        if ($this->schemaInspector->tableHasColumn('patient_data', 'updated_by_type')) {
            $columns[] = 'updated_by_type';
            $placeholders[] = ':updated_by_type';
            $params[':updated_by_type'] = $audit['type'];
        }

        if ($this->schemaInspector->tableHasColumn('patient_data', 'updated_by_identifier')) {
            $columns[] = 'updated_by_identifier';
            $placeholders[] = ':updated_by_identifier';
            $params[':updated_by_identifier'] = $audit['identifier'];
        }

        if (count($columns) === 1) {
            return;
        }

        $updates = [];
        $dateColumns = ['fecha_nacimiento', 'fecha_caducidad'];
        foreach (array_keys($fields) as $column) {
            if (in_array($column, $dateColumns, true)) {
                $updates[] = sprintf(
                    '%1$s = IF(VALUES(%1$s) IS NULL, %1$s, VALUES(%1$s))',
                    $column
                );
                continue;
            }

            $updates[] = sprintf(
                '%1$s = IF(VALUES(%1$s) IS NULL OR VALUES(%1$s) = "", %1$s, VALUES(%1$s))',
                $column
            );
        }

        if ($this->schemaInspector->tableHasColumn('patient_data', 'updated_at')) {
            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        }
        if ($this->schemaInspector->tableHasColumn('patient_data', 'updated_by_type')) {
            $updates[] = 'updated_by_type = VALUES(updated_by_type)';
        }
        if ($this->schemaInspector->tableHasColumn('patient_data', 'updated_by_identifier')) {
            $updates[] = 'updated_by_identifier = VALUES(updated_by_identifier)';
        }

        $sql = sprintf(
            'INSERT INTO patient_data (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPatientFields(array $data): array
    {
        $baseFields = [
            'lname' => (($lname = trim((string) ($data['lname'] ?? ''))) !== '') ? $lname : 'DESCONOCIDO',
            'lname2' => trim((string) ($data['lname2'] ?? '')),
            'fname' => trim((string) ($data['fname'] ?? '')),
            'mname' => trim((string) ($data['mname'] ?? '')),
        ];

        $optionalFields = [
            'afiliacion' => $data['afiliacion'] ?? null,
            'fecha_caducidad' => $this->normalizeDateField($data['fechaCaducidad'] ?? null),
            'fecha_nacimiento' => $this->normalizeDateField($data['fecha_nacimiento'] ?? $data['fecha_nac'] ?? null),
            'sexo' => $data['sexo'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'email' => $data['email'] ?? null,
            'celular' => $data['celular'] ?? $data['telefono'] ?? null,
        ];

        $dateColumns = ['fecha_nacimiento', 'fecha_caducidad'];
        $fields = [];
        foreach (array_merge($baseFields, $optionalFields) as $column => $value) {
            if (!$this->schemaInspector->tableHasColumn('patient_data', $column)) {
                continue;
            }

            if ($value === null) {
                if (in_array($column, $dateColumns, true)) {
                    $fields[$column] = null;
                }
                continue;
            }

            if ($value === '' && !array_key_exists($column, $baseFields)) {
                continue;
            }

            $fields[$column] = $value;
        }

        return $fields;
    }

    /**
     * @return array{type:string, identifier:string}
     */
    private function resolveAuditActor(): array
    {
        $sessionUserId = $_SESSION['user_id'] ?? null;
        if (is_numeric($sessionUserId) && (int) $sessionUserId > 0) {
            return [
                'type' => 'user',
                'identifier' => 'user:' . (string) (int) $sessionUserId,
            ];
        }

        if (PHP_SAPI === 'cli') {
            $script = $_SERVER['argv'][0] ?? 'unknown_script';

            return [
                'type' => 'cron',
                'identifier' => 'cron:' . basename((string) $script),
            ];
        }

        $requestUri = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));

        return [
            'type' => 'api',
            'identifier' => 'api:' . ($requestUri !== '' ? $requestUri : 'unknown_endpoint'),
        ];
    }


    private function normalizeDateField(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $normalized = date('Y-m-d', $timestamp);
        [$year, $month, $day] = array_map('intval', explode('-', $normalized));
        if ($year < 1900 || !checkdate($month, $day, $year)) {
            return null;
        }

        return $normalized;
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

    private function normalizarEstado(?string $estado): ?string
    {
        if ($estado === null) {
            return null;
        }

        $estadoNormalizado = strtoupper(trim($estado));
        return $estadoNormalizado !== '' ? $estadoNormalizado : null;
    }

    private function obtenerUltimoEstadoRegistrado(string $formId): ?string
    {
        $historialStmt = $this->db->prepare(
            "SELECT estado
             FROM procedimiento_proyectado_estado
             WHERE form_id = :form_id
             ORDER BY fecha_hora_cambio DESC
             LIMIT 1"
        );
        $historialStmt->execute([':form_id' => $formId]);
        $estadoHistorial = $historialStmt->fetchColumn();
        $estadoHistorialNormalizado = $estadoHistorial !== false
            ? $this->normalizarEstado((string) $estadoHistorial)
            : null;
        if ($estadoHistorialNormalizado !== null) {
            return $estadoHistorialNormalizado;
        }

        $actualStmt = $this->db->prepare(
            "SELECT estado_agenda
             FROM procedimiento_proyectado
             WHERE form_id = :form_id
             LIMIT 1"
        );
        $actualStmt->execute([':form_id' => $formId]);
        $estadoActual = $actualStmt->fetchColumn();

        return $estadoActual !== false
            ? $this->normalizarEstado((string) $estadoActual)
            : null;
    }

    private function registrarEstadoHistorialSiCambio(string $formId, ?string $nuevoEstado): bool
    {
        $formId = trim($formId);
        $estadoNormalizado = $this->normalizarEstado($nuevoEstado);
        if ($formId === '' || $estadoNormalizado === null) {
            return false;
        }

        $ultimoEstado = $this->obtenerUltimoEstadoRegistrado($formId);
        if ($ultimoEstado !== null && $ultimoEstado === $estadoNormalizado) {
            error_log("⚪ Estado repetido omitido para form_id {$formId}: {$estadoNormalizado}");
            return false;
        }

        $stmtHistorial = $this->db->prepare(
            "INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)
             VALUES (:form_id, :estado, NOW())"
        );
        $stmtHistorial->execute([
            ':form_id' => $formId,
            ':estado' => $estadoNormalizado,
        ]);

        return true;
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
        try {
            error_log("🟣 Intentando actualizar estado: form_id=$formId, nuevoEstado=$nuevoEstado");
            $resolvedFormId = (string) $formId;
            $select = $this->db->prepare("SELECT estado_agenda FROM procedimiento_proyectado WHERE form_id = :form_id LIMIT 1");
            $select->execute([':form_id' => $resolvedFormId]);
            $estadoActual = $select->fetchColumn();

            if ($estadoActual === false) {
                $bootstrap = $this->bootstrapProcedimientoDesdeSolicitud($resolvedFormId, $nuevoEstado);
                if (!empty($bootstrap['form_id'])) {
                    $resolvedFormId = (string) $bootstrap['form_id'];
                }

                $select->execute([':form_id' => $resolvedFormId]);
                $estadoActual = $select->fetchColumn();

                if ($estadoActual === false) {
                    error_log("🔴 El form_id $formId NO existe en procedimiento_proyectado");
                    return ['success' => false, 'message' => 'El form_id no existe en la tabla procedimiento_proyectado'];
                }
            }

            if ($estadoActual === $nuevoEstado) {
                error_log("🟠 El estado solicitado ya estaba registrado. No se realizan cambios adicionales.");
                return ['success' => true, 'message' => 'Estado ya estaba registrado'];
            }

            $sql = "UPDATE procedimiento_proyectado SET estado_agenda = :estado_set WHERE form_id = :form_id AND estado_agenda <> :estado_compare";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':estado_set' => $nuevoEstado,
                ':estado_compare' => $nuevoEstado,
                ':form_id' => $resolvedFormId
            ]);
            error_log("🔵 UPDATE ejecutado. Filas afectadas: " . $stmt->rowCount());
            if ($stmt->rowCount() > 0) {
                $this->registrarEstadoHistorialSiCambio((string) $resolvedFormId, $nuevoEstado);
                return ['success' => true, 'message' => 'Estado actualizado'];
            }

            error_log("🟤 No se registraron cambios de estado para form_id $formId");
            return ['success' => false, 'message' => 'No se pudo actualizar el estado.'];
        } catch (\Throwable $e) {
            error_log('🔴 actualizarEstado error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno al actualizar estado: '
                    . $e->getMessage()
                    . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ];
        }
    }

    /**
     * Intenta crear el registro mínimo en procedimiento_proyectado cuando no existe.
     * También soporta que llegue id de solicitud en lugar de form_id.
     *
     * @return array{form_id:string}|array{}
     */
    private function bootstrapProcedimientoDesdeSolicitud(string $formIdOrId, string $estado): array
    {
        $formIdOrId = trim($formIdOrId);
        if ($formIdOrId === '') {
            return [];
        }

        $selectColumns = ['form_id', 'hc_number', 'procedimiento'];
        if ($this->schemaInspector->tableHasColumn('solicitud_procedimiento', 'doctor')) {
            $selectColumns[] = 'doctor';
        }
        if ($this->schemaInspector->tableHasColumn('solicitud_procedimiento', 'afiliacion')) {
            $selectColumns[] = 'afiliacion';
        }
        if ($this->schemaInspector->tableHasColumn('solicitud_procedimiento', 'fecha')) {
            $selectColumns[] = 'fecha';
        }

        $orderParts = [];
        if ($this->schemaInspector->tableHasColumn('solicitud_procedimiento', 'id')) {
            $orderParts[] = 'id DESC';
        }
        if ($this->schemaInspector->tableHasColumn('solicitud_procedimiento', 'created_at')) {
            $orderParts[] = 'created_at DESC';
        }
        if (empty($orderParts)) {
            $orderParts[] = 'form_id DESC';
        }

        $sql = sprintf(
            "SELECT %s FROM solicitud_procedimiento WHERE form_id = :form_id ORDER BY %s LIMIT 1",
            implode(', ', $selectColumns),
            implode(', ', $orderParts)
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':form_id' => $formIdOrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Fallback: si llegó id numérico de solicitud, resolver su form_id real.
        if (!$row && ctype_digit($formIdOrId)) {
            $sqlById = sprintf(
                "SELECT %s FROM solicitud_procedimiento WHERE id = :id LIMIT 1",
                implode(', ', $selectColumns)
            );
            $stmtById = $this->db->prepare($sqlById);
            $stmtById->execute([':id' => (int) $formIdOrId]);
            $row = $stmtById->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$row) {
            return [];
        }

        $resolvedFormId = trim((string) ($row['form_id'] ?? ''));
        $hcNumber = trim((string) ($row['hc_number'] ?? ''));
        $procedimiento = trim((string) ($row['procedimiento'] ?? ''));
        $doctor = isset($row['doctor']) ? trim((string) $row['doctor']) : null;
        $afiliacion = isset($row['afiliacion']) ? trim((string) $row['afiliacion']) : null;
        $fecha = isset($row['fecha']) ? trim((string) $row['fecha']) : null;

        if ($resolvedFormId === '' || $hcNumber === '' || $procedimiento === '') {
            return [];
        }

        $fechaNormalizada = $this->normalizeDateField($fecha);
        if ($fechaNormalizada === null) {
            $fechaNormalizada = date('Y-m-d');
        }

        $insertValues = [
            'form_id' => $resolvedFormId,
            'hc_number' => $hcNumber,
            'procedimiento_proyectado' => $procedimiento,
        ];

        if ($this->schemaInspector->tableHasColumn('procedimiento_proyectado', 'doctor')) {
            $insertValues['doctor'] = ($doctor !== null && $doctor !== '') ? $doctor : null;
        }
        if ($this->schemaInspector->tableHasColumn('procedimiento_proyectado', 'afiliacion')) {
            $insertValues['afiliacion'] = ($afiliacion !== null && $afiliacion !== '') ? $afiliacion : null;
        }
        if ($this->schemaInspector->tableHasColumn('procedimiento_proyectado', 'fecha')) {
            $insertValues['fecha'] = $fechaNormalizada;
        }
        if ($this->schemaInspector->tableHasColumn('procedimiento_proyectado', 'estado_agenda')) {
            $insertValues['estado_agenda'] = $estado !== '' ? $estado : 'CONSULTA';
        }

        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($insertValues as $column => $value) {
            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $value;
        }

        $updates = [
            "procedimiento_proyectado = IF(VALUES(procedimiento_proyectado) IS NULL OR VALUES(procedimiento_proyectado) = '', procedimiento_proyectado, VALUES(procedimiento_proyectado))",
        ];
        if (array_key_exists('doctor', $insertValues)) {
            $updates[] = "doctor = IF(VALUES(doctor) IS NULL OR VALUES(doctor) = '', doctor, VALUES(doctor))";
        }
        if (array_key_exists('afiliacion', $insertValues)) {
            $updates[] = "afiliacion = IF(VALUES(afiliacion) IS NULL OR VALUES(afiliacion) = '', afiliacion, VALUES(afiliacion))";
        }
        if (array_key_exists('fecha', $insertValues)) {
            $updates[] = "fecha = IF(VALUES(fecha) IS NULL, fecha, VALUES(fecha))";
        }
        if (array_key_exists('estado_agenda', $insertValues)) {
            $updates[] = "estado_agenda = IF(VALUES(estado_agenda) IS NULL OR VALUES(estado_agenda) = '', estado_agenda, VALUES(estado_agenda))";
        }

        $sqlBootstrap = sprintf(
            'INSERT INTO procedimiento_proyectado (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );

        $stmtBootstrap = $this->db->prepare($sqlBootstrap);
        $stmtBootstrap->execute($params);

        error_log("🛠️ Bootstrap procedimiento_proyectado ejecutado para form_id={$resolvedFormId} vía upsert mínimo");

        return ['form_id' => $resolvedFormId];
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
