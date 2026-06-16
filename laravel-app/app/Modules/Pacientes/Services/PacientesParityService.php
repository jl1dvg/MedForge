<?php

namespace App\Modules\Pacientes\Services;

use DateTime;
use PDO;
use PDOException;

class PacientesParityService
{
    /** @var array<string, bool> */
    private array $tablaDisponibleCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array<string,int|null>}
     */
    public function obtenerPacientesReact(?int $limit = null, int $offset = 0): array
    {
        $limit = $limit !== null ? max(1, min(2000, $limit)) : null;
        $offset = max(0, $offset);

        $total = (int) $this->db->query('SELECT COUNT(*) FROM patient_data')->fetchColumn();
        $paginationSql = $limit !== null ? 'LIMIT :limit OFFSET :offset' : '';

        $sql = <<<SQL
            SELECT
                p.hc_number,
                TRIM(CONCAT_WS(' ', p.lname, p.lname2, p.fname, p.mname)) AS full_name,
                TRIM(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2)) AS display_name,
                NULL AS cedula,
                COALESCE(p.celular, '') AS telefono,
                COALESCE(p.email, '') AS email,
                COALESCE(p.afiliacion, '') AS afiliacion,
                p.fecha_nacimiento,
                p.sexo,
                COALESCE(p.direccion, '') AS direccion,
                COALESCE(p.ciudad, '') AS ciudad,
                p.created_at,
                (
                    SELECT pp.doctor
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                    ORDER BY pp.id DESC
                    LIMIT 1
                ) AS medico,
                (
                    SELECT COALESCE(NULLIF(pp.id_sede, ''), NULLIF(pp.sede_departamento, ''))
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                    ORDER BY pp.id DESC
                    LIMIT 1
                ) AS sede,
                (
                    SELECT MAX(cd.fecha)
                    FROM consulta_data cd
                    WHERE cd.hc_number = p.hc_number
                ) AS ultima_visita,
                (
                    SELECT pp.fecha
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                      AND pp.fecha IS NOT NULL
                      AND pp.fecha >= CURDATE()
                    ORDER BY pp.fecha ASC, pp.hora ASC, pp.id ASC
                    LIMIT 1
                ) AS proxima_fecha,
                (
                    SELECT pp.hora
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                      AND pp.fecha IS NOT NULL
                      AND pp.fecha >= CURDATE()
                    ORDER BY pp.fecha ASC, pp.hora ASC, pp.id ASC
                    LIMIT 1
                ) AS proxima_hora,
                (
                    SELECT pp.procedimiento_proyectado
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                      AND pp.fecha IS NOT NULL
                      AND pp.fecha >= CURDATE()
                    ORDER BY pp.fecha ASC, pp.hora ASC, pp.id ASC
                    LIMIT 1
                ) AS proxima_tipo,
                (
                    SELECT pp.doctor
                    FROM procedimiento_proyectado pp
                    WHERE pp.hc_number = p.hc_number
                      AND COALESCE(pp.sigcenter_present, 1) = 1
                      AND pp.fecha IS NOT NULL
                      AND pp.fecha >= CURDATE()
                    ORDER BY pp.fecha ASC, pp.hora ASC, pp.id ASC
                    LIMIT 1
                ) AS proxima_doctor,
                (
                    SELECT COUNT(*)
                    FROM solicitud_procedimiento sp
                    WHERE sp.hc_number = p.hc_number
                      AND LOWER(COALESCE(sp.estado, '')) IN ('ingresada', 'cotizacion', 'cotización', 'en_proceso', 'en proceso', 'autorizada')
                ) AS solicitud_activa,
                NULL AS deuda,
                (
                    SELECT NULLIF(TRIM(cd.antecedente_alergico), '')
                    FROM consulta_data cd
                    WHERE cd.hc_number = p.hc_number
                      AND cd.antecedente_alergico IS NOT NULL
                      AND TRIM(cd.antecedente_alergico) <> ''
                    ORDER BY cd.id DESC
                    LIMIT 1
                ) AS alerta
            FROM (
                SELECT *
                FROM patient_data
                ORDER BY CAST(hc_number AS UNSIGNED) DESC, hc_number DESC
                {$paginationSql}
            ) p
            ORDER BY CAST(p.hc_number AS UNSIGNED) DESC, p.hc_number DESC
        SQL;

        $stmt = $this->db->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $proximaCita = null;
            if (!empty($row['proxima_fecha'])) {
                $proximaCita = [
                    'fecha' => (string) $row['proxima_fecha'],
                    'hora' => (string) ($row['proxima_hora'] ?? ''),
                    'tipo' => (string) ($row['proxima_tipo'] ?? ''),
                    'medico' => (string) ($row['proxima_doctor'] ?? ''),
                ];
            }

            $data[] = [
                'hc_number' => (string) ($row['hc_number'] ?? ''),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'cedula' => null,
                'telefono' => (string) ($row['telefono'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'afiliacion' => (string) ($row['afiliacion'] ?? ''),
                'fecha_nacimiento' => (string) ($row['fecha_nacimiento'] ?? ''),
                'sexo' => (string) ($row['sexo'] ?? ''),
                'direccion' => (string) ($row['direccion'] ?? ''),
                'ciudad' => (string) ($row['ciudad'] ?? ''),
                'medico' => (string) ($row['medico'] ?? ''),
                'sede' => (string) ($row['sede'] ?? ''),
                'ultima_visita' => (string) ($row['ultima_visita'] ?? ''),
                'proxima_cita' => $proximaCita,
                'solicitud_activa' => (int) ($row['solicitud_activa'] ?? 0),
                'sol_activa' => (int) ($row['solicitud_activa'] ?? 0),
                'deuda' => null,
                'alerta' => $row['alerta'] !== null ? (string) $row['alerta'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'count' => count($data),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
    }

    /**
     * @return array{total_pacientes:int,pacientes_nuevos:int,citas_hoy:int,solicitudes_activas:int}
     */
    public function obtenerKpisReact(): array
    {
        return [
            'total_pacientes' => (int) $this->db->query('SELECT COUNT(*) FROM patient_data')->fetchColumn(),
            'pacientes_nuevos' => $this->safeCount(
                'SELECT COUNT(*) FROM patient_data WHERE created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
            ),
            'citas_hoy' => $this->safeCount(
                'SELECT COUNT(*) FROM procedimiento_proyectado WHERE COALESCE(sigcenter_present, 1) = 1 AND fecha = CURDATE()'
            ),
            'solicitudes_activas' => $this->safeCount(
                "SELECT COUNT(*) FROM solicitud_procedimiento WHERE LOWER(COALESCE(estado, '')) IN ('ingresada', 'cotizacion', 'cotización', 'en_proceso', 'en proceso', 'autorizada')"
            ),
        ];
    }

    /**
     * @return array{medicos:array<int,array<string,string>>,sedes:array<int,array<string,string>>,afiliaciones:array<int,array<string,string>>,aseguradoras:array<int,array<string,string>>}
     */
    public function obtenerCatalogosReact(): array
    {
        return [
            'medicos' => $this->catalogoMedicos(),
            'sedes' => $this->catalogoSedes(),
            'afiliaciones' => $this->catalogoAfiliaciones(),
            'aseguradoras' => [],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function crearPaciente(array $input, ?int $sessionUserId): array
    {
        $nombres = $this->splitWords((string) ($input['nombres'] ?? ''));
        $apellidos = $this->splitWords((string) ($input['apellidos'] ?? ''));
        $fname = $nombres[0] ?? '';
        $mname = trim(implode(' ', array_slice($nombres, 1)));
        $lname = $apellidos[0] ?? '';
        $lname2 = trim(implode(' ', array_slice($apellidos, 1)));

        if ($fname === '' || $lname === '') {
            throw new \InvalidArgumentException('nombres y apellidos son requeridos.');
        }

        $hcNumber = $this->nextHcNumber();
        $auditType = $sessionUserId !== null ? 'user' : 'api';
        $auditIdentifier = $sessionUserId !== null ? ('user:' . (string) $sessionUserId) : 'api:/v2/pacientes/crear';
        $fechaNacimiento = trim((string) ($input['fecha_nac'] ?? $input['fecha_nacimiento'] ?? ''));

        $stmt = $this->db->prepare(<<<'SQL'
            INSERT INTO patient_data (
                hc_number, fname, mname, lname, lname2, afiliacion, fecha_nacimiento,
                sexo, celular, ciudad, email, direccion, created_at, updated_at,
                created_by_type, created_by_identifier, updated_by_type, updated_by_identifier
            ) VALUES (
                :hc_number, :fname, :mname, :lname, :lname2, :afiliacion, :fecha_nacimiento,
                :sexo, :celular, :ciudad, :email, :direccion, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
                :created_by_type, :created_by_identifier, :updated_by_type, :updated_by_identifier
            )
        SQL);

        $stmt->execute([
            ':hc_number' => $hcNumber,
            ':fname' => $fname,
            ':mname' => $mname,
            ':lname' => $lname,
            ':lname2' => $lname2,
            ':afiliacion' => trim((string) ($input['afiliacion'] ?? '')),
            ':fecha_nacimiento' => $fechaNacimiento !== '' ? $fechaNacimiento : null,
            ':sexo' => trim((string) ($input['sexo'] ?? '')),
            ':celular' => trim((string) ($input['telefono'] ?? $input['celular'] ?? '')),
            ':ciudad' => trim((string) ($input['ciudad'] ?? '')),
            ':email' => trim((string) ($input['email'] ?? '')),
            ':direccion' => trim((string) ($input['direccion'] ?? '')),
            ':created_by_type' => $auditType,
            ':created_by_identifier' => $auditIdentifier,
            ':updated_by_type' => $auditType,
            ':updated_by_identifier' => $auditIdentifier,
        ]);

        $warnings = [];
        if (trim((string) ($input['cedula'] ?? '')) !== '') {
            $warnings[] = 'patient_data no tiene columna cedula; el documento no fue persistido en esta fase.';
        }

        return [
            'hc_number' => $hcNumber,
            'warnings' => $warnings,
        ];
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

        $sql = <<<SQL
            SELECT
                p.hc_number,
                CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name,
                ultima.ultima_fecha,
                p.afiliacion
            FROM patient_data p
            LEFT JOIN (
                SELECT hc_number, MAX(fecha) AS ultima_fecha
                FROM consulta_data
                GROUP BY hc_number
            ) AS ultima ON ultima.hc_number = p.hc_number
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

            $data[] = [
                'hc_number' => $row['hc_number'],
                'ultima_fecha' => $ultimaFecha,
                'full_name' => $row['full_name'],
                'afiliacion' => $row['afiliacion'],
                'acciones_html' => "<a href='/v2/pacientes/detalles?hc_number=" . urlencode((string) $row['hc_number']) . "' class='btn btn-sm btn-primary'>Ver</a>",
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

        try {
            $solicitudes = $this->getSolicitudesPorPaciente($hcNumber, $timelineLimit);
        } catch (PDOException) {
            $solicitudes = [];
        }

        try {
            $prefacturas = $this->getPrefacturasPorPaciente($hcNumber, $timelineLimit);
        } catch (PDOException) {
            $prefacturas = [];
        }

        try {
            $medicos = $this->getDoctoresAsignados($hcNumber);
        } catch (PDOException) {
            $medicos = [];
        }

        try {
            $eventos = $this->getEventosTimeline($hcNumber);
        } catch (PDOException) {
            $eventos = [];
        }

        try {
            $documentos = $this->getDocumentosDescargables($hcNumber);
        } catch (PDOException) {
            $documentos = [];
        }

        try {
            $estadisticas = $this->getEstadisticasProcedimientos($hcNumber);
        } catch (PDOException) {
            $estadisticas = [];
        }

        try {
            $diagnosticos = $this->getDiagnosticosPorPaciente($hcNumber);
        } catch (PDOException) {
            $diagnosticos = [];
        }

        return [
            'patientData' => $patientData,
            'afiliacionesDisponibles' => $this->getAfiliacionesDisponibles(),
            'diagnosticos' => $diagnosticos,
            'medicos' => $medicos,
            'timelineItems' => $this->ordenarTimeline(array_merge($solicitudes, $prefacturas)),
            'eventos' => $eventos,
            'documentos' => $documentos,
            'estadisticas' => $estadisticas,
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
            "SELECT doctor, form_id FROM procedimiento_proyectado WHERE hc_number = ? AND COALESCE(sigcenter_present, 1) = 1 AND doctor IS NOT NULL AND doctor != '' AND doctor NOT LIKE '%optometría%' ORDER BY form_id DESC"
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
            <<<'SQL'
            SELECT
                pd.form_id,
                pd.hc_number,
                COALESCE(NULLIF(pd.membrete, ''), pp.procedimiento_proyectado, 'Procedimiento quirúrgico') AS membrete,
                COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_inicio
            FROM protocolo_data pd
            LEFT JOIN (
                SELECT
                    hc_number,
                    form_id,
                    MAX(procedimiento_proyectado) AS procedimiento_proyectado,
                    MAX(fecha) AS fecha
                FROM procedimiento_proyectado
                WHERE COALESCE(sigcenter_present, 1) = 1
                GROUP BY hc_number, form_id
            ) pp
              ON pp.hc_number = pd.hc_number
             AND pp.form_id = pd.form_id
            WHERE pd.hc_number = ?
            SQL
        );
        $stmt1->execute([$hcNumber]);
        $protocolos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $this->db->prepare(
            <<<'SQL'
            SELECT
                pp.form_id,
                pp.hc_number,
                MAX(pp.procedimiento_proyectado) AS procedimiento,
                MAX(pp.fecha) AS created_at
            FROM procedimiento_proyectado pp
            WHERE pp.hc_number = ?
              AND COALESCE(pp.sigcenter_present, 1) = 1
              AND pp.form_id IS NOT NULL
              AND TRIM(pp.form_id) <> ''
              AND pp.procedimiento_proyectado IS NOT NULL
              AND TRIM(pp.procedimiento_proyectado) <> ''
              AND UPPER(TRIM(pp.procedimiento_proyectado)) <> 'SELECCIONE'
              AND (
                  UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'PNI%'
                  OR UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'CIRUGIAS%'
              )
            GROUP BY pp.hc_number, pp.form_id
            SQL
        );
        $stmt2->execute([$hcNumber]);
        $procedimientos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $documentosByForm = [];
        foreach ($procedimientos as $documento) {
            if (!$this->esProcedimientoPniOCirugia($documento['procedimiento'] ?? null)) {
                continue;
            }
            $formId = trim((string) ($documento['form_id'] ?? ''));
            $key = $formId !== '' ? 'form:' . $formId : 'proc:' . md5(json_encode($documento));
            $documentosByForm[$key] = $documento;
        }

        foreach ($protocolos as $documento) {
            $formId = trim((string) ($documento['form_id'] ?? ''));
            $key = $formId !== '' ? 'form:' . $formId : 'proto:' . md5(json_encode($documento));
            // Si existe mismo form_id, priorizar protocolo.
            $documentosByForm[$key] = $documento;
        }

        $documentos = array_values($documentosByForm);
        usort($documentos, static function (array $a, array $b): int {
            $fechaA = $a['fecha_inicio'] ?? $a['created_at'] ?? null;
            $fechaB = $b['fecha_inicio'] ?? $b['created_at'] ?? null;
            return strtotime((string) ($fechaB ?? 'now')) <=> strtotime((string) ($fechaA ?? 'now'));
        });

        return $documentos;
    }

    private function esProcedimientoPniOCirugia(mixed $procedimiento): bool
    {
        $texto = strtoupper(trim((string) $procedimiento));
        if ($texto === '') {
            return false;
        }

        return str_starts_with($texto, 'PNI') || str_starts_with($texto, 'CIRUGIAS');
    }

    public function getPatientDetails(string $hcNumber): array
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
                   cd.motivo_consulta,
                   cd.enfermedad_actual,
                   cd.plan,
                   cd.examen_fisico,
                   pr.membrete
            FROM procedimiento_proyectado pp
            LEFT JOIN consulta_data cd ON pp.hc_number = cd.hc_number AND pp.form_id = cd.form_id
            LEFT JOIN protocolo_data pr ON pp.hc_number = pr.hc_number AND pp.form_id = pr.form_id
            WHERE pp.hc_number = ? AND COALESCE(pp.sigcenter_present, 1) = 1 AND pp.procedimiento_proyectado NOT LIKE '%optometría%'
            ORDER BY fecha ASC
            SQL
        );
        $stmt->execute([$hcNumber]);

        $eventos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['fecha']) && strtotime((string) $row['fecha'])) {
                $motivo = trim((string) ($row['motivo_consulta'] ?? ''));
                $enfermedad = trim((string) ($row['enfermedad_actual'] ?? ''));
                $plan = trim((string) ($row['plan'] ?? ''));
                $examenFisico = trim((string) ($row['examen_fisico'] ?? ''));
                $fallback = $examenFisico;
                if ($fallback === '') {
                    $fallback = trim((string) ($row['membrete'] ?? ''));
                }

                $contenido = $fallback;
                if ($motivo !== '' || $enfermedad !== '' || $plan !== '' || $examenFisico !== '') {
                    $contenido = trim(implode("\n\n", [
                        'Motivo: ' . ($motivo !== '' ? $motivo : '—'),
                        'Enfermedad Actual: ' . ($enfermedad !== '' ? $enfermedad : '—'),
                        'Examen Físico: ' . ($examenFisico !== '' ? $examenFisico : '—'),
                        'Plan: ' . ($plan !== '' ? $plan : '—'),
                    ]));
                }

                $row['motivo_consulta'] = $motivo;
                $row['enfermedad_actual'] = $enfermedad;
                $row['examen_fisico'] = $examenFisico;
                $row['plan'] = $plan;
                $row['contenido'] = $contenido;
                $eventos[] = $row;
            }
        }

        return $eventos;
    }

    private function getEstadisticasProcedimientos(string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            'SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE hc_number = ? AND COALESCE(sigcenter_present, 1) = 1'
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

        try {
            $stmt = $this->db->query($sql);
        } catch (PDOException) {
            return [];
        }

        if ($stmt === false) {
            return [];
        }

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'afiliacion');
    }

    private function safeCount(string $sql): int
    {
        try {
            $stmt = $this->db->query($sql);
            if ($stmt === false) {
                return 0;
            }

            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoMedicos(): array
    {
        $items = [];

        try {
            $stmt = $this->db->query(<<<'SQL'
                SELECT id, nombre, full_name, subespecialidad, especialidad, sede, id_trabajador
                FROM users
                WHERE ((nombre IS NOT NULL AND TRIM(nombre) <> '')
                    OR (full_name IS NOT NULL AND TRIM(full_name) <> ''))
                  AND (
                    (subespecialidad IS NOT NULL AND TRIM(subespecialidad) <> '')
                    OR (especialidad IS NOT NULL AND TRIM(especialidad) <> '')
                    OR id_trabajador IS NOT NULL
                  )
                ORDER BY COALESCE(nombre, full_name) ASC
            SQL);

            foreach (($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $nombre = trim((string) ($row['nombre'] ?: ($row['full_name'] ?? '')));
                if ($nombre === '') {
                    continue;
                }

                $key = (string) ($row['id'] ?? $this->catalogKey($nombre));
                $items[$key] = [
                    'id' => $key,
                    'full' => $nombre,
                    'nombre' => $nombre,
                    'esp' => trim((string) ($row['subespecialidad'] ?: ($row['especialidad'] ?? ''))),
                    'especialidad' => trim((string) ($row['subespecialidad'] ?: ($row['especialidad'] ?? ''))),
                    'sede' => trim((string) ($row['sede'] ?? '')),
                    'id_trabajador' => (string) ($row['id_trabajador'] ?? ''),
                ];
            }
        } catch (PDOException) {
            // no-op
        }

        return array_values($items);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoSedes(): array
    {
        $items = [];

        try {
            $stmt = $this->db->query(<<<'SQL'
                SELECT DISTINCT COALESCE(NULLIF(TRIM(id_sede), ''), NULLIF(TRIM(sede_departamento), '')) AS nombre
                FROM procedimiento_proyectado
                WHERE COALESCE(NULLIF(TRIM(id_sede), ''), NULLIF(TRIM(sede_departamento), '')) IS NOT NULL
                ORDER BY nombre ASC
            SQL);

            foreach (($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $nombre = trim((string) ($row['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                $items[$this->catalogKey($nombre)] = [
                    'id' => $this->catalogKey($nombre),
                    'label' => $nombre,
                    'nombre' => $nombre,
                ];
            }
        } catch (PDOException) {
            // keep user fallback below
        }

        try {
            $stmt = $this->db->query(<<<'SQL'
                SELECT DISTINCT TRIM(sede) AS nombre
                FROM users
                WHERE sede IS NOT NULL
                  AND TRIM(sede) <> ''
                ORDER BY nombre ASC
            SQL);

            foreach (($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $nombre = trim((string) ($row['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                $items[$this->catalogKey($nombre)] = [
                    'id' => $this->catalogKey($nombre),
                    'label' => $nombre,
                    'nombre' => $nombre,
                ];
            }
        } catch (PDOException) {
            // no-op
        }

        return array_values($items);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoAfiliaciones(): array
    {
        return array_map(
            fn(string $afiliacion): array => [
                'id' => $this->catalogKey($afiliacion),
                'label' => $afiliacion,
                'nombre' => $afiliacion,
            ],
            $this->getAfiliacionesDisponibles()
        );
    }

    private function catalogKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : md5($value);
    }

    /**
     * @return array<int,string>
     */
    private function splitWords(string $value): array
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
        if ($value === '') {
            return [];
        }

        return explode(' ', $value);
    }

    private function nextHcNumber(): string
    {
        $stmt = $this->db->query('SELECT MAX(CAST(hc_number AS UNSIGNED)) FROM patient_data');
        $next = ((int) ($stmt ? $stmt->fetchColumn() : 0)) + 1;

        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
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
