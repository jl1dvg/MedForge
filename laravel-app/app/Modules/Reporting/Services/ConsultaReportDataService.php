<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use PDO;

class ConsultaReportDataService
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, array<string, bool>> */
    private array $tableColumnsCache = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConsultaReportData(string $formId, string $hcNumber): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);

        if ($formId === '' || $hcNumber === '') {
            return [];
        }

        $contexto = $this->resolverContexto($formId, $hcNumber);
        $resolvedFormId = $contexto['form_id'];
        $resolvedHc = $contexto['hc_number'];

        $paciente = $this->fetchPaciente($resolvedHc);
        if ($paciente === [] && $resolvedHc !== $hcNumber) {
            $paciente = $this->fetchPaciente($hcNumber);
        }

        $dxDerivacion = $this->fetchDxDerivacion($resolvedFormId);

        $consulta = $this->fetchConsultaConProcedimiento($resolvedFormId, $resolvedHc);
        if ($consulta === []) {
            $consulta = $this->fetchConsultaPorFormId($resolvedFormId);
        }
        if ($consulta === []) {
            $consulta = $this->buildConsultaFallback($resolvedFormId, $resolvedHc, $contexto, $dxDerivacion);
        }

        $diagnostico = $this->fetchDiagnostico($resolvedFormId);
        if ($diagnostico === []) {
            $diagnostico = $this->buildDiagnosticoFallback($contexto, $dxDerivacion);
        }

        return [
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
            'dx_derivacion' => $dxDerivacion,
        ];
    }

    /**
     * @return array{form_id:string,hc_number:string,procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null}
     */
    private function resolverContexto(string $formId, string $hcNumber): array
    {
        $resolvedFormId = trim($formId);
        $resolvedHc = trim($hcNumber);

        $procedimiento = $this->fetchProcedimientoProyectadoByFormHc($resolvedFormId, $resolvedHc);
        if ($procedimiento === null) {
            $procedimiento = $this->fetchProcedimientoProyectadoByFormId($resolvedFormId);
        }

        if (is_array($procedimiento)) {
            $hcProc = trim((string) ($procedimiento['hc_number'] ?? ''));
            if ($hcProc !== '') {
                $resolvedHc = $hcProc;
            }
        }

        $protocolo = $this->fetchProtocoloByFormHc($resolvedFormId, $resolvedHc);
        if ($protocolo === null) {
            $protocolo = $this->fetchProtocoloByFormId($resolvedFormId);
            if (is_array($protocolo)) {
                $hcProtocolo = trim((string) ($protocolo['hc_number'] ?? ''));
                if ($hcProtocolo !== '') {
                    $resolvedHc = $hcProtocolo;
                }
            }
        }

        return [
            'form_id' => $resolvedFormId,
            'hc_number' => $resolvedHc,
            'procedimiento' => $procedimiento,
            'protocolo' => $protocolo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPaciente(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ? LIMIT 1');
        $stmt->execute([$hcNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchConsultaConProcedimiento(string $formId, string $hcNumber): array
    {
        $doctorJoin = $this->buildDoctorJoinCondition('pp.doctor', 'u');
        $signatureExpr = $this->buildDoctorSignaturePathExpression('u');
        $firmaExpr = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                pp.procedimiento_proyectado AS procedimiento_nombre,
                u.id AS doctor_user_id,
                u.first_name AS doctor_fname,
                u.middle_name AS doctor_mname,
                u.last_name AS doctor_lname,
                u.second_last_name AS doctor_lname2,
                u.cedula AS doctor_cedula,
                {$signatureExpr} AS doctor_signature_path,
                {$firmaExpr} AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = cd.form_id AND pp.hc_number = cd.hc_number
            LEFT JOIN users u
                ON {$doctorJoin}
            WHERE cd.form_id = ? AND cd.hc_number = ?
            ORDER BY COALESCE(cd.fecha, cd.created_at) DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId, $hcNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }

        return $this->normalizarConsultaDoctor($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchConsultaPorFormId(string $formId): array
    {
        $doctorJoin = $this->buildDoctorJoinCondition('pp.doctor', 'u');
        $signatureExpr = $this->buildDoctorSignaturePathExpression('u');
        $firmaExpr = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                pp.procedimiento_proyectado AS procedimiento_nombre,
                u.id AS doctor_user_id,
                u.first_name AS doctor_fname,
                u.middle_name AS doctor_mname,
                u.last_name AS doctor_lname,
                u.second_last_name AS doctor_lname2,
                u.cedula AS doctor_cedula,
                {$signatureExpr} AS doctor_signature_path,
                {$firmaExpr} AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = cd.form_id AND pp.hc_number = cd.hc_number
            LEFT JOIN users u
                ON {$doctorJoin}
            WHERE cd.form_id = ?
            ORDER BY COALESCE(cd.fecha, cd.created_at) DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }

        return $this->normalizarConsultaDoctor($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchConsultaUltimaPorHc(string $hcNumber): array
    {
        $doctorJoin = $this->buildDoctorJoinCondition('pp.doctor', 'u');
        $signatureExpr = $this->buildDoctorSignaturePathExpression('u');
        $firmaExpr = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                pp.procedimiento_proyectado AS procedimiento_nombre,
                u.id AS doctor_user_id,
                u.first_name AS doctor_fname,
                u.middle_name AS doctor_mname,
                u.last_name AS doctor_lname,
                u.second_last_name AS doctor_lname2,
                u.cedula AS doctor_cedula,
                {$signatureExpr} AS doctor_signature_path,
                {$firmaExpr} AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = cd.form_id AND pp.hc_number = cd.hc_number
            LEFT JOIN users u
                ON {$doctorJoin}
            WHERE cd.hc_number = ?
            ORDER BY COALESCE(cd.fecha, cd.created_at) DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hcNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }

        return $this->normalizarConsultaDoctor($row);
    }

    /**
     * @param array<string, mixed> $consulta
     * @return array<string, mixed>
     */
    private function normalizarConsultaDoctor(array $consulta): array
    {
        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $doctorFromJoin = trim((string) ($consulta['doctor_full_name'] ?? ($consulta['procedimiento_doctor'] ?? '')));
            if ($doctorFromJoin !== '') {
                $consulta['doctor'] = $doctorFromJoin;
            }
        }

        return $consulta;
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     * @param array<int, array<string, mixed>> $dxDerivacion
     * @return array<string, mixed>
     */
    private function buildConsultaFallback(string $formId, string $hcNumber, array $contexto, array $dxDerivacion): array
    {
        $doctor = $this->resolverDoctorDesdeContexto($contexto);
        $procedimiento = $this->resolverProcedimientoDesdeContexto($contexto);
        $createdAt = $this->resolverCreatedAtDesdeContexto($contexto);
        $fecha = $createdAt !== '' ? substr($createdAt, 0, 10) : '';

        $motivoDx = '';
        if ($dxDerivacion !== []) {
            $motivoDx = trim((string) ($dxDerivacion[0]['diagnostico'] ?? ''));
        }

        $consulta = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha' => $fecha,
            'created_at' => $createdAt,
            'motivo_consulta' => $motivoDx,
            'enfermedad_actual' => $motivoDx,
            'examen_fisico' => '',
            'plan' => $procedimiento,
            'diagnostico_plan' => $procedimiento,
            'doctor' => $doctor,
            'procedimiento_doctor' => $doctor,
        ];

        return $this->enriquecerConsultaConUsuarioDoctor($consulta, $doctor);
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     * @param array<int, array<string, mixed>> $dxDerivacion
     * @return array<int, array<string, mixed>>
     */
    private function buildDiagnosticoFallback(array $contexto, array $dxDerivacion): array
    {
        $diagnostico = [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];
        $items = $this->decodeJsonArray($protocolo['diagnosticos'] ?? null);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dxCode = trim((string) ($item['dx_code'] ?? ($item['cie10'] ?? '')));
            $descripcion = trim((string) ($item['descripcion'] ?? ''));
            $idDiagnostico = trim((string) ($item['idDiagnostico'] ?? ''));

            if ($descripcion === '' && $idDiagnostico !== '') {
                [$dxParsed, $descParsed] = $this->parseDiagnosticoTexto($idDiagnostico);
                if ($dxCode === '') {
                    $dxCode = $dxParsed;
                }
                if ($descripcion === '') {
                    $descripcion = $descParsed;
                }
            }

            if ($dxCode === '' && $descripcion === '') {
                continue;
            }

            $diagnostico[] = [
                'dx_code' => $dxCode,
                'descripcion' => $descripcion,
                'fuente' => 'protocolo',
            ];

            if (count($diagnostico) >= 6) {
                break;
            }
        }

        if ($diagnostico !== []) {
            return $diagnostico;
        }

        foreach ($dxDerivacion as $item) {
            $texto = trim((string) ($item['diagnostico'] ?? ''));
            if ($texto === '') {
                continue;
            }

            $partes = array_values(array_filter(array_map(
                static fn(string $v): string => trim($v),
                preg_split('/\s*;\s*/', $texto) ?: []
            ), static fn(string $v): bool => $v !== ''));

            foreach ($partes as $parte) {
                [$dxCode, $descripcion] = $this->parseDiagnosticoTexto($parte);
                if ($dxCode === '' && $descripcion === '') {
                    continue;
                }

                $diagnostico[] = [
                    'dx_code' => $dxCode,
                    'descripcion' => $descripcion,
                    'fuente' => 'derivacion',
                ];

                if (count($diagnostico) >= 6) {
                    break 2;
                }
            }
        }

        return $diagnostico;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDiagnostico(string $formId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM diagnosticos_asignados WHERE form_id = ?');
        $stmt->execute([$formId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDxDerivacion(string $formId): array
    {
        $rows = [];

        try {
            $stmt = $this->db->prepare(
                'SELECT diagnostico FROM derivaciones_forms WHERE iess_form_id = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$formId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $rows = [];
        }

        if ($rows !== []) {
            return $rows;
        }

        $stmt = $this->db->prepare('SELECT diagnostico FROM derivaciones_form_id WHERE form_id = ?');
        $stmt->execute([$formId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverDoctorDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $doctor = trim((string) ($procedimiento['doctor'] ?? ''));
        if ($doctor !== '') {
            return $doctor;
        }

        return trim((string) ($protocolo['cirujano_1'] ?? ''));
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverProcedimientoDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $texto = trim((string) ($procedimiento['procedimiento_proyectado'] ?? ''));
        if ($texto !== '') {
            return $texto;
        }

        return trim((string) ($protocolo['membrete'] ?? ''));
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverCreatedAtDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $fechaProc = trim((string) ($procedimiento['fecha'] ?? ''));
        $horaProc = trim((string) ($procedimiento['hora'] ?? ''));
        if ($fechaProc !== '') {
            return $fechaProc . ($horaProc !== '' ? (' ' . $horaProc) : '');
        }

        return trim((string) ($protocolo['fecha_inicio'] ?? ''));
    }

    /**
     * @param array<string, mixed> $consulta
     * @return array<string, mixed>
     */
    private function enriquecerConsultaConUsuarioDoctor(array $consulta, string $doctorNombre): array
    {
        $usuario = $this->obtenerUsuarioPorDoctorNombre($doctorNombre);
        if ($usuario === []) {
            return $consulta;
        }

        $signaturePath = $this->resolverSignaturePathUsuario($usuario);
        $fullName = trim((string) ($usuario['full_name'] ?? $usuario['nombre'] ?? ''));

        if (trim((string) ($consulta['doctor_fname'] ?? '')) === '') {
            $consulta['doctor_fname'] = (string) ($usuario['first_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_mname'] ?? '')) === '') {
            $consulta['doctor_mname'] = (string) ($usuario['middle_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname'] ?? '')) === '') {
            $consulta['doctor_lname'] = (string) ($usuario['last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname2'] ?? '')) === '') {
            $consulta['doctor_lname2'] = (string) ($usuario['second_last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_cedula'] ?? '')) === '') {
            $consulta['doctor_cedula'] = (string) ($usuario['cedula'] ?? '');
        }
        if (trim((string) ($consulta['doctor_signature_path'] ?? '')) === '' && $signaturePath !== '') {
            $consulta['doctor_signature_path'] = $signaturePath;
        }
        if (trim((string) ($consulta['doctor_firma'] ?? '')) === '') {
            $consulta['doctor_firma'] = (string) ($usuario['firma'] ?? '');
        }
        if (trim((string) ($consulta['doctor_full_name'] ?? '')) === '' && $fullName !== '') {
            $consulta['doctor_full_name'] = $fullName;
        }
        if (trim((string) ($consulta['doctor'] ?? '')) === '' && $fullName !== '') {
            $consulta['doctor'] = $fullName;
        }
        if ((int) ($consulta['doctor_user_id'] ?? 0) <= 0 && isset($usuario['id'])) {
            $consulta['doctor_user_id'] = (int) $usuario['id'];
        }

        return $consulta;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerUsuarioPorDoctorNombre(string $doctorNombre): array
    {
        $doctorNombre = trim($doctorNombre);
        if ($doctorNombre === '') {
            return [];
        }

        $conditions = ['UPPER(TRIM(u.nombre)) = UPPER(TRIM(?))'];
        $params = [$doctorNombre];

        if ($this->hasColumnInTable('users', 'full_name')) {
            $conditions[] = 'UPPER(TRIM(u.full_name)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        if ($this->hasColumnInTable('users', 'nombre_norm')) {
            $conditions[] = 'u.nombre_norm = TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(?)), " "), " SNS ", " "), "  ", " "), "  ", " "))';
            $params[] = $doctorNombre;
            $conditions[] = 'UPPER(TRIM(u.nombre_norm)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        if ($this->hasColumnInTable('users', 'nombre_norm_rev')) {
            $conditions[] = 'u.nombre_norm_rev = TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(?)), " "), " SNS ", " "), "  ", " "), "  ", " "))';
            $params[] = $doctorNombre;
            $conditions[] = 'UPPER(TRIM(u.nombre_norm_rev)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        $conditions[] = 'u.nombre LIKE ?';
        $params[] = '%' . $doctorNombre . '%';

        $sql = 'SELECT u.* FROM users u WHERE (' . implode(' OR ', $conditions) . ') ORDER BY u.id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string, mixed> $usuario
     */
    private function resolverSignaturePathUsuario(array $usuario): string
    {
        $signaturePath = trim((string) ($usuario['signature_path'] ?? ''));
        if ($signaturePath !== '') {
            return $signaturePath;
        }

        return trim((string) ($usuario['seal_signature_path'] ?? ''));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseDiagnosticoTexto(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^\s*([A-Z][0-9A-Z\.]+)\s*[-–:]\s*(.+)\s*$/u', $value, $m) === 1) {
            return [trim((string) ($m[1] ?? '')), trim((string) ($m[2] ?? ''))];
        }

        return ['', $value];
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, afiliacion
             FROM procedimiento_proyectado
             WHERE form_id = ? AND hc_number = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$formId, $hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, afiliacion
             FROM procedimiento_proyectado
             WHERE form_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProtocoloByFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT form_id, hc_number, fecha_inicio, membrete, cirujano_1, diagnosticos, diagnosticos_previos
             FROM protocolo_data
             WHERE form_id = ? AND hc_number = ?
             ORDER BY fecha_inicio DESC
             LIMIT 1'
        );
        $stmt->execute([$formId, $hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProtocoloByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT form_id, hc_number, fecha_inicio, membrete, cirujano_1, diagnosticos, diagnosticos_previos
             FROM protocolo_data
             WHERE form_id = ?
             ORDER BY fecha_inicio DESC
             LIMIT 1'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function buildDoctorJoinCondition(string $doctorExpression, string $userAlias = 'u'): string
    {
        $conditions = [
            'UPPER(TRIM(' . $doctorExpression . ')) = UPPER(TRIM(' . $userAlias . '.nombre))',
        ];

        if ($this->hasColumnInTable('users', 'full_name')) {
            $conditions[] = 'UPPER(TRIM(' . $doctorExpression . ')) = UPPER(TRIM(' . $userAlias . '.full_name))';
        }

        if ($this->hasColumnInTable('users', 'nombre_norm')) {
            $conditions[] = 'UPPER(TRIM(' . $doctorExpression . ')) = ' . $userAlias . '.nombre_norm';
            $conditions[] = 'TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(' . $doctorExpression . ')), " "), " SNS ", " "), "  ", " "), "  ", " ")) = ' . $userAlias . '.nombre_norm';
        }

        if ($this->hasColumnInTable('users', 'nombre_norm_rev')) {
            $conditions[] = 'UPPER(TRIM(' . $doctorExpression . ')) = ' . $userAlias . '.nombre_norm_rev';
            $conditions[] = 'TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(' . $doctorExpression . ')), " "), " SNS ", " "), "  ", " "), "  ", " ")) = ' . $userAlias . '.nombre_norm_rev';
        }

        return '(' . implode(' OR ', array_unique($conditions)) . ')';
    }

    private function buildDoctorSignaturePathExpression(string $userAlias = 'u'): string
    {
        $candidates = [];

        if ($this->hasColumnInTable('users', 'signature_path')) {
            $candidates[] = "NULLIF(TRIM({$userAlias}.signature_path), '')";
        }

        if ($this->hasColumnInTable('users', 'seal_signature_path')) {
            $candidates[] = "NULLIF(TRIM({$userAlias}.seal_signature_path), '')";
        }

        if ($candidates === []) {
            return 'NULL';
        }

        return 'COALESCE(' . implode(', ', $candidates) . ')';
    }

    private function buildDoctorFirmaExpression(string $userAlias = 'u'): string
    {
        if (!$this->hasColumnInTable('users', 'firma')) {
            return 'NULL';
        }

        return "NULLIF(TRIM({$userAlias}.firma), '')";
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = false;
        try {
            $safeTable = str_replace('`', '', $table);
            $this->db->query("SELECT 1 FROM `{$safeTable}` LIMIT 1");
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function hasColumnInTable(string $table, string $column): bool
    {
        if (isset($this->tableColumnsCache[$table][$column])) {
            return $this->tableColumnsCache[$table][$column];
        }

        $exists = false;

        if ($this->hasTable($table)) {
            try {
                $safeTable = str_replace('`', '', $table);
                $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE :column");
                $stmt->bindValue(':column', $column, PDO::PARAM_STR);
                $stmt->execute();
                $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $exists = false;
            }

            if (!$exists) {
                try {
                    $safeTable = str_replace('`', '', $table);
                    $safeColumn = str_replace('`', '', $column);
                    $this->db->query("SELECT `{$safeColumn}` FROM `{$safeTable}` LIMIT 0");
                    $exists = true;
                } catch (\Throwable) {
                    $exists = false;
                }
            }
        }

        if (!isset($this->tableColumnsCache[$table])) {
            $this->tableColumnsCache[$table] = [];
        }
        $this->tableColumnsCache[$table][$column] = $exists;

        return $exists;
    }
}
