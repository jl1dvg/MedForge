<?php

namespace App\Modules\Pacientes\Services;

use DateTime;
use PDO;
use PDOException;

class PacientesParityService
{
    private ?bool $prefacturaTableExists = null;

    /** @var array<string, bool> */
    private array $tablaDisponibleCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function obtenerPacientesPaginados(
        int $start,
        int $length,
        string $search = '',
        string $orderColumn = 'hc_number',
        string $orderDir = 'ASC'
    ): array {
        $start = max(0, $start);
        $length = max(1, $length);

        $columns = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
        $orderBy = in_array($orderColumn, $columns, true) ? $orderColumn : 'hc_number';
        $orderableMap = [
            'hc_number' => 'p.hc_number',
            'ultima_fecha' => 'ultima.ultima_fecha',
            'full_name' => 'full_name',
            'afiliacion' => 'p.afiliacion',
        ];
        $orderBySql = $orderableMap[$orderBy] ?? 'p.hc_number';
        $orderDirection = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $searchSql = '';
        $params = [];
        if ($search !== '') {
            $searchSql = "WHERE (p.hc_number LIKE :search1 OR p.fname LIKE :search2 OR p.lname LIKE :search3 OR p.afiliacion LIKE :search4)";
            $params[':search1'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
            $params[':search4'] = "%$search%";
        }

        $countTotal = (int) $this->db->query('SELECT COUNT(*) FROM patient_data')->fetchColumn();

        if ($searchSql === '') {
            $countFiltered = $countTotal;
        } else {
            $stmtFiltered = $this->db->prepare(
                "SELECT COUNT(*) FROM patient_data p $searchSql"
            );
            $stmtFiltered->execute($params);
            $countFiltered = (int) $stmtFiltered->fetchColumn();
        }

        $hasPrefactura = $this->hasPrefacturaTable();

        $estadoSelect = $hasPrefactura
            ? "CASE
                WHEN cobertura.fecha_vigencia IS NULL THEN 'N/A'
                WHEN cobertura.fecha_vigencia >= CURRENT_DATE THEN 'Con Cobertura'
                ELSE 'Sin Cobertura'
            END AS estado_cobertura"
            : "'N/A' AS estado_cobertura";

        $coberturaJoin = $hasPrefactura
            ? "LEFT JOIN (
                SELECT base.hc_number, base.cod_derivacion, base.fecha_vigencia
                FROM prefactura_paciente base
                INNER JOIN (
                    SELECT hc_number, MAX(fecha_vigencia) AS max_fecha
                    FROM prefactura_paciente
                    WHERE cod_derivacion IS NOT NULL AND cod_derivacion != ''
                    GROUP BY hc_number
                ) AS ult ON ult.hc_number = base.hc_number AND ult.max_fecha = base.fecha_vigencia
                WHERE base.cod_derivacion IS NOT NULL AND base.cod_derivacion != ''
            ) AS cobertura ON cobertura.hc_number = p.hc_number"
            : '';

        $sql = <<<SQL
            SELECT
                p.hc_number,
                CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name,
                ultima.ultima_fecha,
                p.afiliacion,
                $estadoSelect
            FROM patient_data p
            LEFT JOIN (
                SELECT hc_number, MAX(fecha) AS ultima_fecha
                FROM consulta_data
                GROUP BY hc_number
            ) AS ultima ON ultima.hc_number = p.hc_number
            $coberturaJoin
            $searchSql
            ORDER BY $orderBySql $orderDirection
            LIMIT $start, $length
        SQL;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ultimaFecha = $row['ultima_fecha'] ? date('d/m/Y', strtotime((string) $row['ultima_fecha'])) : '';
            $estado = $row['estado_cobertura'] ?? 'N/A';
            if ($estado === 'Con Cobertura') {
                $badgeClass = 'bg-success';
            } elseif ($estado === 'Sin Cobertura') {
                $badgeClass = 'bg-danger';
            } else {
                $badgeClass = 'bg-secondary';
            }

            $data[] = [
                'hc_number' => $row['hc_number'],
                'ultima_fecha' => $ultimaFecha,
                'full_name' => $row['full_name'],
                'afiliacion' => $row['afiliacion'],
                'estado_html' => sprintf("<span class='badge %s'>%s</span>", $badgeClass, htmlspecialchars((string) $estado, ENT_QUOTES, 'UTF-8')),
                'acciones_html' => "<a href='/pacientes/detalles?hc_number=" . urlencode((string) $row['hc_number']) . "' class='btn btn-sm btn-primary'>Ver</a>",
            ];
        }

        return [
            'recordsTotal' => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data' => $data,
        ];
    }

    public function obtenerContextoPaciente(string $hcNumber): array
    {
        $patientData = $this->getPatientDetails($hcNumber);

        if (empty($patientData)) {
            return [];
        }

        $timelineLimit = 100;
        $solicitudes = $this->getSolicitudesPorPaciente($hcNumber, $timelineLimit);
        $prefacturas = $this->getPrefacturasPorPaciente($hcNumber, $timelineLimit);

        return [
            'patientData' => $patientData,
            'afiliacionesDisponibles' => $this->getAfiliacionesDisponibles(),
            'diagnosticos' => $this->getDiagnosticosPorPaciente($hcNumber),
            'medicos' => $this->getDoctoresAsignados($hcNumber),
            'timelineItems' => $this->ordenarTimeline(array_merge($solicitudes, $prefacturas)),
            'eventos' => $this->getEventosTimeline($hcNumber),
            'documentos' => $this->getDocumentosDescargables($hcNumber),
            'estadisticas' => $this->getEstadisticasProcedimientos($hcNumber),
            'patientAge' => $this->calcularEdad($patientData['fecha_nacimiento'] ?? null),
            'coverageStatus' => $this->verificarCoberturaPaciente($hcNumber),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function actualizarPaciente(string $hcNumber, array $input, ?int $sessionUserId): void
    {
        $fname = trim((string) ($input['fname'] ?? ''));
        $mname = trim((string) ($input['mname'] ?? ''));
        $lname = trim((string) ($input['lname'] ?? ''));
        $lname2 = trim((string) ($input['lname2'] ?? ''));
        $afiliacion = trim((string) ($input['afiliacion'] ?? ''));
        $fechaNacimiento = trim((string) ($input['fecha_nacimiento'] ?? ''));
        $sexo = trim((string) ($input['sexo'] ?? ''));
        $celular = trim((string) ($input['celular'] ?? ''));

        $auditType = $sessionUserId !== null ? 'user' : 'api';
        $auditIdentifier = $sessionUserId !== null ? ('user:' . (string) $sessionUserId) : 'api:/v2/pacientes/detalles';

        $stmt = $this->db->prepare(
            <<<'SQL'
            UPDATE patient_data
            SET fname = COALESCE(NULLIF(:fname, ''), fname),
                mname = :mname,
                lname = COALESCE(NULLIF(:lname, ''), lname),
                lname2 = :lname2,
                afiliacion = :afiliacion,
                fecha_nacimiento = :fecha_nacimiento,
                sexo = :sexo,
                celular = :celular,
                updated_at = CURRENT_TIMESTAMP,
                updated_by_type = :updated_by_type,
                updated_by_identifier = :updated_by_identifier
            WHERE hc_number = :hc_number
            SQL
        );

        $stmt->execute([
            ':fname' => $fname,
            ':mname' => $mname,
            ':lname' => $lname,
            ':lname2' => $lname2,
            ':afiliacion' => $afiliacion,
            ':fecha_nacimiento' => $fechaNacimiento,
            ':sexo' => $sexo,
            ':celular' => $celular,
            ':updated_by_type' => $auditType,
            ':updated_by_identifier' => $auditIdentifier,
            ':hc_number' => $hcNumber,
        ]);
    }

    private function getDiagnosticosPorPaciente(string $hcNumber): array
    {
        $uniqueDiagnoses = [];

        if ($this->tablaDisponible('prefactura_detalle_diagnosticos')) {
            $stmtPref = $this->db->prepare(
                <<<'SQL'
                SELECT
                    d.diagnostico_codigo,
                    d.descripcion,
                    pp.fecha_creacion,
                    pp.fecha_registro
                FROM prefactura_detalle_diagnosticos d
                INNER JOIN prefactura_paciente pp ON pp.id = d.prefactura_id
                WHERE pp.hc_number = ?
                ORDER BY pp.fecha_creacion DESC, d.posicion ASC
                SQL
            );
            $stmtPref->execute([$hcNumber]);

            while ($row = $stmtPref->fetch(PDO::FETCH_ASSOC)) {
                $codigo = $row['diagnostico_codigo'] ?: ($row['descripcion'] ?? null);
                if (!$codigo) {
                    continue;
                }

                if (!isset($uniqueDiagnoses[$codigo])) {
                    $fechaEvento = $row['fecha_creacion'] ?? $row['fecha_registro'] ?? null;
                    $timestamp = $fechaEvento ? strtotime((string) $fechaEvento) : false;
                    $uniqueDiagnoses[$codigo] = [
                        'idDiagnostico' => $row['diagnostico_codigo'] ?: $codigo,
                        'fecha' => $timestamp ? date('d M Y', $timestamp) : null,
                    ];
                }
            }
        }

        $stmt = $this->db->prepare(
            'SELECT fecha, diagnosticos FROM consulta_data WHERE hc_number = ? ORDER BY fecha DESC'
        );
        $stmt->execute([$hcNumber]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $diagnosticos = json_decode((string) $row['diagnosticos'], true) ?: [];
            $timestamp = strtotime((string) $row['fecha']);
            $fecha = $timestamp ? date('d M Y', $timestamp) : null;

            foreach ($diagnosticos as $diagnostico) {
                $id = $diagnostico['idDiagnostico'] ?? null;
                if ($id && !isset($uniqueDiagnoses[$id])) {
                    $uniqueDiagnoses[$id] = [
                        'idDiagnostico' => $id,
                        'fecha' => $fecha,
                    ];
                }
            }
        }

        return $uniqueDiagnoses;
    }

    private function getDoctoresAsignados(string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            "SELECT doctor, form_id FROM procedimiento_proyectado WHERE hc_number = ? AND doctor IS NOT NULL AND doctor != '' AND doctor NOT LIKE '%optometría%' ORDER BY form_id DESC"
        );
        $stmt->execute([$hcNumber]);

        $doctores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $doctor = $row['doctor'];
            if (!isset($doctores[$doctor])) {
                $doctores[$doctor] = [
                    'doctor' => $doctor,
                    'form_id' => $row['form_id'],
                ];
            }
        }

        return $doctores;
    }

    private function getSolicitudesPorPaciente(string $hcNumber, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT procedimiento, created_at, tipo, form_id FROM solicitud_procedimiento WHERE hc_number = ? AND procedimiento != '' AND procedimiento != 'SELECCIONE' ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, $hcNumber);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $solicitudes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $solicitudes[] = [
                'nombre' => $row['procedimiento'],
                'fecha' => $row['created_at'],
                'tipo' => strtolower((string) ($row['tipo'] ?? 'otro')),
                'form_id' => $row['form_id'],
                'origen' => 'Solicitud',
            ];
        }

        return $solicitudes;
    }

    private function getDocumentosDescargables(string $hcNumber): array
    {
        $stmt1 = $this->db->prepare(
            'SELECT form_id, hc_number, membrete, fecha_inicio FROM protocolo_data WHERE hc_number = ? AND status = 1'
        );
        $stmt1->execute([$hcNumber]);
        $protocolos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $this->db->prepare(
            <<<'SQL'
            SELECT form_id, hc_number, procedimiento, created_at
            FROM solicitud_procedimiento
            WHERE hc_number = ?
              AND procedimiento IS NOT NULL
              AND procedimiento != ''
              AND procedimiento != 'SELECCIONE'
            SQL
        );
        $stmt2->execute([$hcNumber]);
        $solicitudes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $documentos = array_merge($protocolos, $solicitudes);
        usort($documentos, static function (array $a, array $b): int {
            $fechaA = $a['fecha_inicio'] ?? $a['created_at'] ?? null;
            $fechaB = $b['fecha_inicio'] ?? $b['created_at'] ?? null;
            return strtotime((string) ($fechaB ?? 'now')) <=> strtotime((string) ($fechaA ?? 'now'));
        });

        return $documentos;
    }

    private function getPatientDetails(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ?');
        $stmt->execute([$hcNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function getEventosTimeline(string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT pp.procedimiento_proyectado, pp.form_id, pp.hc_number,
                   COALESCE(cd.fecha, pr.fecha_inicio) AS fecha,
                   COALESCE(cd.examen_fisico, pr.membrete) AS contenido
            FROM procedimiento_proyectado pp
            LEFT JOIN consulta_data cd ON pp.hc_number = cd.hc_number AND pp.form_id = cd.form_id
            LEFT JOIN protocolo_data pr ON pp.hc_number = pr.hc_number AND pp.form_id = pr.form_id
            WHERE pp.hc_number = ? AND pp.procedimiento_proyectado NOT LIKE '%optometría%'
            ORDER BY fecha ASC
            SQL
        );
        $stmt->execute([$hcNumber]);

        $eventos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['fecha']) && strtotime((string) $row['fecha'])) {
                $eventos[] = $row;
            }
        }

        return $eventos;
    }

    private function getEstadisticasProcedimientos(string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            'SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE hc_number = ?'
        );
        $stmt->execute([$hcNumber]);

        $procedimientos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = explode(' - ', (string) $row['procedimiento_proyectado']);
            $categoria = strtoupper((string) ($parts[0] ?? ''));
            if (in_array($categoria, ['CIRUGIAS', 'PNI', 'IMAGENES'], true)) {
                $nombre = $categoria;
            } else {
                $nombre = $parts[2] ?? $categoria;
            }

            if (!isset($procedimientos[$nombre])) {
                $procedimientos[$nombre] = 0;
            }
            $procedimientos[$nombre]++;
        }

        $total = array_sum($procedimientos);
        if ($total === 0) {
            return [];
        }

        $porcentajes = [];
        foreach ($procedimientos as $nombre => $cantidad) {
            $porcentajes[$nombre] = ($cantidad / $total) * 100;
        }

        return $porcentajes;
    }

    private function calcularEdad(?string $fechaNacimiento): ?int
    {
        if (!$fechaNacimiento) {
            return null;
        }

        try {
            $fechaNacimientoDt = new DateTime($fechaNacimiento);
            $fechaActualDt = new DateTime();

            return $fechaActualDt->diff($fechaNacimientoDt)->y;
        } catch (\Exception) {
            return null;
        }
    }

    private function verificarCoberturaPaciente(string $hcNumber): string
    {
        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                SELECT cod_derivacion, fecha_vigencia
                FROM prefactura_paciente
                WHERE hc_number = ?
                  AND cod_derivacion IS NOT NULL AND cod_derivacion != ''
                ORDER BY fecha_vigencia DESC
                LIMIT 1
                SQL
            );
            $stmt->execute([$hcNumber]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return 'N/A';
        }

        if (!$row) {
            return 'N/A';
        }

        $fechaVigencia = strtotime((string) $row['fecha_vigencia']);
        $fechaActual = time();

        return $fechaVigencia >= $fechaActual ? 'Con Cobertura' : 'Sin Cobertura';
    }

    private function getPrefacturasPorPaciente(string $hcNumber, int $limit = 50): array
    {
        $stmt = $this->db->prepare(<<<'SQL'
            SELECT *
            FROM prefactura_paciente
            WHERE hc_number = ?
              AND cod_derivacion IS NOT NULL
              AND cod_derivacion != ''
            ORDER BY fecha_creacion DESC
            LIMIT ?
        SQL);
        $stmt->bindValue(1, $hcNumber);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $prefacturas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $detalles = $this->obtenerProcedimientosNormalizados((int) ($row['id'] ?? 0));
            $procedimientos = [];

            if ($detalles !== null) {
                $procedimientos = $detalles;
                $row['procedimientos_detalle'] = $detalles;
            } elseif (!empty($row['procedimientos']) && is_string($row['procedimientos'])) {
                $procedimientos = json_decode($row['procedimientos'], true) ?: [];
            }

            $nombreProcedimientos = '';
            if (is_array($procedimientos) && $procedimientos !== []) {
                foreach ($procedimientos as $index => $proc) {
                    $linea = ($index + 1) . '. ';
                    $descripcion = $proc['descripcion'] ?? $proc['procedimiento'] ?? $proc['procInterno'] ?? $proc['procDetalle'] ?? $proc['codigo'] ?? 'Procedimiento';
                    $linea .= $descripcion;

                    $lateralidad = $proc['lateralidad'] ?? $proc['ojoId'] ?? null;
                    if (!empty($lateralidad)) {
                        $linea .= ' - Ojo: ' . $lateralidad;
                    }

                    if (!empty($proc['observaciones'])) {
                        $linea .= ' (' . $proc['observaciones'] . ')';
                    }

                    $nombreProcedimientos .= $linea . "\n";
                }
            } else {
                $nombreProcedimientos = 'Procedimientos no disponibles';
            }

            $prefacturas[] = [
                'nombre' => "Prefactura\n" . $nombreProcedimientos,
                'fecha' => $row['fecha_creacion'],
                'tipo' => 'prefactura',
                'form_id' => $row['form_id'] ?? null,
                'detalle' => $row,
                'origen' => 'Prefactura',
            ];
        }

        return $prefacturas;
    }

    private function ordenarTimeline(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            return strtotime((string) ($b['fecha'] ?? '')) <=> strtotime((string) ($a['fecha'] ?? ''));
        });

        return $items;
    }

    private function getAfiliacionesDisponibles(): array
    {
        $driver = null;

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            // keep generic query fallback
        }

        if ($driver === 'sqlite') {
            $sql = <<<'SQL'
                SELECT DISTINCT afiliacion
                FROM patient_data
                WHERE afiliacion IS NOT NULL
                  AND afiliacion != ''
                  AND SUBSTR(afiliacion, 1, 1) GLOB '[A-Za-z]'
                ORDER BY afiliacion ASC
            SQL;
        } else {
            $sql = <<<'SQL'
                SELECT DISTINCT afiliacion
                FROM patient_data
                WHERE afiliacion IS NOT NULL
                  AND afiliacion != ''
                  AND afiliacion REGEXP '^[A-Za-z]'
                ORDER BY afiliacion ASC
            SQL;
        }

        $stmt = $this->db->query($sql);
        if ($stmt === false) {
            return [];
        }

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'afiliacion');
    }

    private function hasPrefacturaTable(): bool
    {
        if ($this->prefacturaTableExists !== null) {
            return $this->prefacturaTableExists;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'prefactura_paciente'");
            $this->prefacturaTableExists = $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (PDOException) {
            $this->prefacturaTableExists = false;
        }

        return $this->prefacturaTableExists;
    }

    private function obtenerProcedimientosNormalizados(int $prefacturaId): ?array
    {
        if ($prefacturaId === 0 || !$this->tablaDisponible('prefactura_detalle_procedimientos')) {
            return null;
        }

        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT
                posicion,
                external_id,
                proc_interno,
                codigo,
                descripcion,
                lateralidad,
                observaciones,
                precio_base,
                precio_tarifado
            FROM prefactura_detalle_procedimientos
            WHERE prefactura_id = ?
            ORDER BY posicion ASC
            SQL
        );
        $stmt->execute([$prefacturaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tablaDisponible(string $table): bool
    {
        if (isset($this->tablaDisponibleCache[$table])) {
            return $this->tablaDisponibleCache[$table];
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                $exists = (bool) $stmt->fetchColumn();
            } else {
                $stmt = $this->db->prepare(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
                );
                $stmt->execute([$table]);
                $exists = (bool) $stmt->fetchColumn();
            }
        } catch (PDOException) {
            $exists = false;
        }

        return $this->tablaDisponibleCache[$table] = $exists;
    }
}

