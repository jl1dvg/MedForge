<?php

namespace Modules\Pacientes\Services;

use DateTime;
use Modules\Shared\Services\PatientIdentityService;
use PDO;
use PDOException;

class PacienteService
{
    private PDO $db;
    private ?bool $prefacturaTableExists = null;
    /** @var array<string, bool> */
    private array $tablaDisponibleCache = [];
    private PatientIdentityService $identityService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->identityService = new PatientIdentityService($pdo);
    }

    public function obtenerPacientesConUltimaConsulta(): array
    {
        $sql = <<<'SQL'
            SELECT
                p.hc_number,
                CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name,
                MAX(cd.fecha) AS ultima_fecha,
                cd.diagnosticos,
                (
                    SELECT pp.doctor
                    FROM consulta_data cd2
                    INNER JOIN procedimiento_proyectado pp ON cd2.form_id = pp.form_id
                    WHERE cd2.hc_number = p.hc_number
                    ORDER BY cd2.fecha DESC
                    LIMIT 1
                ) AS doctor,
                p.fecha_caducidad,
                p.afiliacion
            FROM patient_data p
            INNER JOIN consulta_data cd ON p.hc_number = cd.hc_number
            GROUP BY p.hc_number
            ORDER BY ultima_fecha DESC
        SQL;

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiagnosticosPorPaciente(string $hcNumber): array
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
            $diagnosticos = json_decode($row['diagnosticos'], true) ?: [];
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

    public function getDoctoresAsignados(string $hcNumber): array
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

    public function getSolicitudesPorPaciente(string $hcNumber, int $limit = 50): array
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
                'tipo' => strtolower($row['tipo'] ?? 'otro'),
                'form_id' => $row['form_id'],
                'origen' => 'Solicitud',
            ];
        }

        return $solicitudes;
    }

    public function getDetalleSolicitud(string $hcNumber, string $formId): array
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT sp.*, cd.*
            FROM solicitud_procedimiento sp
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            WHERE sp.hc_number = ? AND sp.form_id = ?
            LIMIT 1
            SQL
        );
        $stmt->execute([$hcNumber, $formId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDocumentosDescargables(string $hcNumber): array
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
            return strtotime($fechaB ?? 'now') <=> strtotime($fechaA ?? 'now');
        });

        return $documentos;
    }

    public function getPatientDetails(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ?');
        $stmt->execute([$hcNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getEventosTimeline(string $hcNumber): array
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
            if (!empty($row['fecha']) && strtotime($row['fecha'])) {
                $eventos[] = $row;
            }
        }

        return $eventos;
    }

    public function getEstadisticasProcedimientos(string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            'SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE hc_number = ?'
        );
        $stmt->execute([$hcNumber]);

        $procedimientos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = explode(' - ', $row['procedimiento_proyectado']);
            $categoria = strtoupper($parts[0] ?? '');
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

    public function calcularEdad(?string $fechaNacimiento, ?string $fechaActual = null): ?int
    {
        if (!$fechaNacimiento) {
            return null;
        }

        try {
            $fechaNacimientoDt = new DateTime($fechaNacimiento);
            $fechaActualDt = $fechaActual ? new DateTime($fechaActual) : new DateTime();

            return $fechaActualDt->diff($fechaNacimientoDt)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verificarCoberturaPaciente(string $hcNumber): string
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

    public function getPrefacturasPorPaciente(string $hcNumber, int $limit = 50): array
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

    private function ordenarTimeline(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            return strtotime($b['fecha'] ?? '') <=> strtotime($a['fecha'] ?? '');
        });

        return $items;
    }

    public function obtenerStaffPorEspecialidad(): array
    {
        $especialidades = ['Cirujano Oftalmólogo', 'Anestesiologo', 'Asistente'];
        $staff = [];

        foreach ($especialidades as $especialidad) {
            $sql = 'SELECT nombre FROM users WHERE especialidad LIKE ? ORDER BY nombre';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$especialidad]);
            $staff[$especialidad] = array_map(
                static fn(array $row) => $row['nombre'],
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        }

        return $staff;
    }

    public function actualizarPaciente(
        string $hcNumber,
        string $fname,
        string $mname,
        string $lname,
        string $lname2,
        string $afiliacion,
        string $fechaNacimiento,
        string $sexo,
        string $celular
    ): void {
        $stmt = $this->db->prepare(
            <<<'SQL'
            UPDATE patient_data
            SET fname = :fname,
                mname = :mname,
                lname = :lname,
                lname2 = :lname2,
                afiliacion = :afiliacion,
                fecha_nacimiento = :fecha_nacimiento,
                sexo = :sexo,
                celular = :celular
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
            ':hc_number' => $hcNumber,
        ]);

        $fullName = trim(implode(' ', array_filter([$fname, $mname, $lname, $lname2], static fn($v) => $v !== '')));

        $identity = $this->identityService->ensureIdentity($hcNumber, [
            'customer' => [
                'name' => $fullName !== '' ? $fullName : null,
                'phone' => $celular !== '' ? $celular : null,
                'affiliation' => $afiliacion !== '' ? $afiliacion : null,
                'source' => 'clinical',
            ],
            'patient' => [
                'fname' => $fname,
                'mname' => $mname,
                'lname' => $lname,
                'lname2' => $lname2,
                'afiliacion' => $afiliacion,
                'fecha_nacimiento' => $fechaNacimiento,
                'sexo' => $sexo,
                'celular' => $celular,
            ],
        ]);

        $this->identityService->syncLead($hcNumber, [
            'name' => $fullName !== '' ? $fullName : ('Paciente ' . $this->identityService->normalizeHcNumber($hcNumber)),
            'phone' => $celular !== '' ? $celular : null,
            'source' => 'clinical',
            'customer_id' => $identity['customer_id'] ?? null,
        ], true);
    }

    public function getAfiliacionesDisponibles(): array
    {
        $driver = null;

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException) {
            // Si el driver no puede obtenerse seguimos con la consulta genérica
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

    public function getAtencionesParticularesPorSemana(string $fechaInicio, string $fechaFin): array
    {
        $sql = <<<'SQL'
            SELECT p.hc_number, CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS nombre_completo,
                   'consulta' AS tipo, cd.form_id, cd.fecha, p.afiliacion AS afiliacion,
                   pp.procedimiento_proyectado, pp.doctor
            FROM patient_data p
            JOIN consulta_data cd ON cd.hc_number = p.hc_number
            JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = cd.form_id
            WHERE cd.fecha BETWEEN :inicio1 AND :fin1
              AND p.afiliacion COLLATE utf8mb4_unicode_ci NOT IN (
                  'isspol', 'issfa', 'iess', 'msp',
                  'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
                  'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
                  'seguro general por montepío', 'seguro general tiempo parcial'
              )

            UNION ALL

            SELECT p.hc_number, CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS nombre_completo,
                   'protocolo' AS tipo, pd.form_id, pd.fecha_inicio AS fecha, p.afiliacion AS afiliacion,
                   pp.procedimiento_proyectado, pp.doctor
            FROM patient_data p
            JOIN protocolo_data pd ON pd.hc_number = p.hc_number
            JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = pd.form_id
            WHERE pd.fecha_inicio BETWEEN :inicio2 AND :fin2
              AND p.afiliacion COLLATE utf8mb4_unicode_ci NOT IN (
                  'isspol', 'issfa', 'iess', 'msp',
                  'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
                  'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
                  'seguro general por montepío', 'seguro general tiempo parcial'
              )
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio1' => $fechaInicio,
            ':fin1' => $fechaFin,
            ':inicio2' => $fechaInicio,
            ':fin2' => $fechaFin,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            ? "CASE\n                WHEN cobertura.fecha_vigencia IS NULL THEN 'N/A'\n                WHEN cobertura.fecha_vigencia >= CURRENT_DATE THEN 'Con Cobertura'\n                ELSE 'Sin Cobertura'\n            END AS estado_cobertura"
            : "'N/A' AS estado_cobertura";

        $coberturaJoin = $hasPrefactura
            ? "LEFT JOIN (\n                SELECT base.hc_number, base.cod_derivacion, base.fecha_vigencia\n                FROM prefactura_paciente base\n                INNER JOIN (\n                    SELECT hc_number, MAX(fecha_vigencia) AS max_fecha\n                    FROM prefactura_paciente\n                    WHERE cod_derivacion IS NOT NULL AND cod_derivacion != ''\n                    GROUP BY hc_number\n                ) AS ult ON ult.hc_number = base.hc_number AND ult.max_fecha = base.fecha_vigencia\n                WHERE base.cod_derivacion IS NOT NULL AND base.cod_derivacion != ''\n            ) AS cobertura ON cobertura.hc_number = p.hc_number"
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
            $ultimaFecha = $row['ultima_fecha'] ? date('d/m/Y', strtotime($row['ultima_fecha'])) : '';
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
                'estado_html' => sprintf("<span class='badge %s'>%s</span>", $badgeClass, htmlspecialchars($estado, ENT_QUOTES, 'UTF-8')),
                'acciones_html' => "<a href='/pacientes/detalles?hc_number=" . urlencode($row['hc_number']) . "' class='btn btn-sm btn-primary'>Ver</a>",
            ];
        }

        return [
            'recordsTotal' => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data' => $data,
        ];
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
