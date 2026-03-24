<?php

namespace App\Modules\Agenda\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class IndexAdmisionesSyncService
{
    private ?string $dbConnection;
    private ?string $dbHost;
    private int $dbPort;
    private ?string $dbDatabase;
    private ?string $dbUsername;
    private ?string $dbPassword;
    private ?string $sshHost;
    private int $sshPort;
    private ?string $sshUser;
    private ?string $sshPass;
    private ?string $lastError = null;
    private ?\phpseclib3\Net\SSH2 $ssh = null;
    private ?bool $directDbAvailable = null;

    /** @var array<string,string> */
    private array $patientColumns = [];

    /** @var array<string,string> */
    private array $procedimientoColumns = [];

    public function __construct()
    {
        $this->dbConnection = $this->readEnv('SIGCENTER_DB_CONNECTION_NAME') ?: 'sigcenter';
        $this->dbHost = $this->readEnv('SIGCENTER_DB_HOST') ?: '127.0.0.1';
        $this->dbPort = (int) ($this->readEnv('SIGCENTER_DB_PORT') ?: 3306);
        $this->dbDatabase = $this->readEnv('SIGCENTER_DB_DATABASE') ?: 'inmicrocsa';
        $this->dbUsername = $this->readEnv('SIGCENTER_DB_USERNAME');
        $this->dbPassword = $this->readEnv('SIGCENTER_DB_PASSWORD');
        $this->sshHost = $this->readEnv('SIGCENTER_FILES_SSH_HOST');
        $this->sshPort = (int) ($this->readEnv('SIGCENTER_FILES_SSH_PORT') ?: 22);
        $this->sshUser = $this->readEnv('SIGCENTER_FILES_SSH_USER');
        $this->sshPass = $this->readEnv('SIGCENTER_FILES_SSH_PASS');
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param array{
     *   lookback?:int,
     *   lookahead?:int,
     *   from_date?:?string,
     *   to_date?:?string
     * } $options
     * @param callable(string,array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function sync(array $options = [], ?callable $onProgress = null): array
    {
        $this->lastError = null;
        $startedAt = microtime(true);
        [$fromDate, $toDate] = $this->resolveDateRange($options);

        $rows = $this->fetchRows($fromDate, $toDate);
        if ($rows === [] && $this->lastError !== null) {
            return [
                'success' => false,
                'error' => $this->lastError,
                'from' => $fromDate,
                'to' => $toDate,
            ];
        }

        $this->patientColumns = $this->fetchTableColumns('patient_data');
        $this->procedimientoColumns = $this->fetchTableColumns('procedimiento_proyectado');

        $stats = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($rows as $row) {
            $stats['processed']++;
            try {
                $normalized = $this->normalizeRow($row);
                if (
                    ($normalized['hcNumber'] ?? '') === ''
                    || ($normalized['form_id'] ?? '') === ''
                    || ($normalized['procedimiento_proyectado'] ?? '') === ''
                    || ($normalized['fecha'] ?? '') === ''
                ) {
                    $stats['skipped']++;
                    $onProgress && $onProgress('skip', [
                        'form_id' => $normalized['form_id'] ?? '',
                        'hc_number' => $normalized['hcNumber'] ?? '',
                        'reason' => 'missing_required_fields',
                    ]);
                    continue;
                }

                DB::transaction(function () use ($normalized): void {
                    $this->upsertPatientData($normalized);
                    $this->upsertProcedimientoProyectado($normalized);
                });

                $stats['sent']++;
                $onProgress && $onProgress('row', [
                    'form_id' => $normalized['form_id'],
                    'hc_number' => $normalized['hcNumber'],
                    'estado' => $normalized['estado_agenda'] ?? null,
                    'fecha' => $normalized['fecha'] ?? null,
                ]);
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->lastError = $e->getMessage();
                $onProgress && $onProgress('error', [
                    'form_id' => $row['pedido_id'] ?? '',
                    'hc_number' => $row['hc_number'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'from' => $fromDate,
            'to' => $toDate,
            'total_rows' => count($rows),
            'processed_rows' => $stats['processed'],
            'sent_rows' => $stats['sent'],
            'skipped_rows' => $stats['skipped'],
            'error_rows' => $stats['errors'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => $this->isDirectDbAvailable() ? 'sigcenter-db' : 'sigcenter-ssh',
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(array $options): array
    {
        $fromOption = trim((string) ($options['from_date'] ?? ''));
        $toOption = trim((string) ($options['to_date'] ?? ''));
        if ($fromOption !== '' || $toOption !== '') {
            if ($fromOption === '' || $toOption === '') {
                throw new \InvalidArgumentException('from_date y to_date deben enviarse juntos.');
            }

            $fromDate = $this->parseDate($fromOption)->format('Y-m-d');
            $toDate = $this->parseDate($toOption)->format('Y-m-d');
            if ($fromDate > $toDate) {
                throw new \InvalidArgumentException('Rango inválido: from_date es mayor que to_date.');
            }

            return [$fromDate, $toDate];
        }

        $lookback = max(0, (int) ($options['lookback'] ?? 14));
        $lookahead = max(0, (int) ($options['lookahead'] ?? 14));
        $today = new DateTimeImmutable('today');

        return [
            $today->modify(sprintf('-%d days', $lookback))->format('Y-m-d'),
            $today->modify(sprintf('+%d days', $lookahead))->format('Y-m-d'),
        ];
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException(sprintf('Fecha inválida: %s', $value));
        }

        return $date;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(string $fromDate, string $toDate): array
    {
        if ($this->isDirectDbAvailable()) {
            $rows = DB::connection($this->dbConnection)
                ->select($this->baseSql(), [
                    'start_date' => $fromDate,
                    'end_date' => $toDate,
                ]);

            return array_map(static fn ($row): array => (array) $row, $rows);
        }

        return $this->fetchRowsViaSsh($fromDate, $toDate);
    }

    private function baseSql(): string
    {
        return <<<'SQL'
SELECT
    CAST(dsp.id AS CHAR) AS pedido_id,
    DATE_FORMAT(COALESCE(ad.FECHA_INICIO, dsp.fecha_registro), '%d-%m-%Y') AS fecha_grupo,
    COALESCE(ad.FECHA_INICIO, dsp.fecha_registro) AS fecha_evento,
    TRIM(COALESCE(p.numero_historia_clinica, '')) AS hc_number,
    TRIM(COALESCE(oe.codigo_pedido, '')) AS codigo_examen,
    TRIM(COALESCE(p.APELLIDOS, '')) AS apellidos,
    TRIM(COALESCE(p.NOMBRES, '')) AS nombres,
    TRIM(CONCAT_WS(' ', p.APELLIDOS, p.NOMBRES)) AS paciente_full,
    TRIM(COALESCE(p.EMAIL, '')) AS email,
    p.FECHA_NAC AS fecha_nac,
    TRIM(COALESCE(p.SEXO, '')) AS sexo,
    TRIM(COALESCE(ca.nombre, '')) AS ciudad,
    TRIM(COALESCE(af.NOMBRE, '')) AS afiliacion,
    TRIM(COALESCE(p.TELEFONO, '')) AS telefono,
    TRIM(CONCAT_WS(
        '',
        tp.NOMBRE,
        ' - ',
        prd.codigo,
        ' - ',
        proc.NOMBRE,
        CASE WHEN ojo.NOMBRE IS NOT NULL THEN CONCAT(' - ', ojo.NOMBRE) ELSE '' END
    )) AS procedimiento,
    TRIM(CONCAT_WS(' ', trab.APELLIDOS, trab.NOMBRES)) AS doctor_agenda,
    TRIM(COALESCE(dptoAgenda.NOMBRE, '')) AS agenda_dpto,
    CASE
        WHEN UPPER(COALESCE(dptoAgenda.NOMBRE, '')) LIKE '%CEIBOS%' THEN 'CEIBOS'
        WHEN UPPER(COALESCE(dptoAgenda.NOMBRE, '')) LIKE '%MATRIZ%' THEN 'MATRIZ'
        ELSE ''
    END AS sede_departamento,
    TRIM(COALESCE(cie.CIE10, '')) AS cie10,
    TRIM(COALESCE(et.NOMBRE, '')) AS estado_agenda,
    CASE dsp.estado_id
        WHEN 1 THEN 'GENERADAS'
        WHEN 2 THEN 'ATENDIDAS'
        WHEN 3 THEN 'REVISADAS'
        WHEN 4 THEN 'ENVIADAS'
        ELSE CAST(dsp.estado_id AS CHAR)
    END AS estado,
    TRIM(COALESCE(procd.NOMBRE, '')) AS referido_prefactura_por,
    TRIM(COALESCE(ref.nombre, '')) AS especificar_referido_prefactura,
    TRIM(COALESCE(dspac.cod_derivacion, '')) AS codigo_derivacion,
    TRIM(COALESCE(dspac.num_secuencial_derivacion, '')) AS num_secuencial_derivacion,
    TRIM(COALESCE(dm.nroOda, '')) AS prefactura
FROM doc_solicitud_procedimientos dsp
INNER JOIN doc_solicitud_paciente dspac
    ON dspac.id = dsp.doc_solicitud_pacienteId
INNER JOIN paciente p
    ON p.ID_PACIENTE = dspac.pacienteId
INNER JOIN procedimiento proc
    ON proc.ID_PROCEDIMIENTO = dsp.procedimientoId
INNER JOIN tipo_procedimiento tp
    ON tp.ID_TIPO_PROCEDIMIENTO = proc.ID_TIPO_PROCEDIMIENTO
LEFT JOIN ciudad_aux ca
    ON ca.id = p.ciudad_id
LEFT JOIN afiliacion af
    ON af.ID_AFILIACION = dspac.afiliacionId
LEFT JOIN agenda_doctor ad
    ON ad.ID_AGENDA_DOCTOR = dsp.agenda_doctorId
LEFT JOIN paciente_procedimiento pp
    ON pp.ID_PACIENTE_PROCEDIMIENTO = ad.ID_PACIENTE_PROCEDIMIENTO
LEFT JOIN estado_turno et
    ON et.ID_ESTADO_TURNO = pp.ID_ESTADO_TURNO
LEFT JOIN sede_departamento sd
    ON sd.ID_SEDE_DEPARTAMENTO = ad.ID_SEDE_DEPARTAMENTO
LEFT JOIN departamento dptoAgenda
    ON dptoAgenda.ID_DEPARTAMENTO = sd.ID_DEPARTAMENTO
LEFT JOIN trabajador trab
    ON trab.ID_TRABAJADOR = ad.ID_TRABAJADOR
LEFT JOIN procedencia procd
    ON procd.ID_PROCEDENCIA = dspac.procedencia_id
LEFT JOIN referido ref
    ON ref.id = dspac.referido_id
LEFT JOIN doc_motivo dm
    ON dm.id = dspac.motivo_id
LEFT JOIN orden_examen oe
    ON oe.docSolicitudProcedimiento_id = dsp.id
LEFT JOIN ojo
    ON ojo.ID_OJO = dsp.ojo_id
LEFT JOIN productos prd
    ON prd.procedimiento_id = dsp.procedimientoId
LEFT JOIN (
    SELECT
        dr.solicitud_id,
        GROUP_CONCAT(DISTINCT CONCAT_WS(' - ', enf.codigo, enf.nombre, oj.descripcion) SEPARATOR ', ') AS CIE10
    FROM diagnostico_reporte dr
    INNER JOIN enfermedades enf
        ON dr.diagnostico_id = enf.idEnfermedades
    LEFT JOIN ojo oj
        ON dr.ojo_id = oj.ID_OJO
    GROUP BY dr.solicitud_id
) cie
    ON cie.solicitud_id = dsp.id
WHERE DATE(COALESCE(ad.FECHA_INICIO, dsp.fecha_registro))
      BETWEEN :start_date AND :end_date
GROUP BY dsp.id
ORDER BY COALESCE(ad.FECHA_INICIO, dsp.fecha_registro), dsp.id
SQL;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRowsViaSsh(string $fromDate, string $toDate): array
    {
        $ssh = $this->ssh();
        if ($ssh === null) {
            return [];
        }

        $sql = str_replace(
            [':start_date', ':end_date'],
            ["'" . str_replace("'", "''", $fromDate) . "'", "'" . str_replace("'", "''", $toDate) . "'"],
            $this->baseSql()
        );

        $mysqlCommand = sprintf(
            'mysql --batch --raw --skip-column-names -h %s -P %d -u %s -p%s %s -e %s',
            escapeshellarg((string) $this->dbHost),
            $this->dbPort,
            escapeshellarg((string) $this->dbUsername),
            escapeshellarg((string) $this->dbPassword),
            escapeshellarg((string) $this->dbDatabase),
            escapeshellarg($sql)
        );

        $output = $ssh->exec($mysqlCommand);
        $exitStatus = $ssh->getExitStatus();
        if ($exitStatus !== 0 && trim((string) $output) === '') {
            $this->lastError = 'Consulta remota Sigcenter por SSH falló.';
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r?\n/', trim((string) $output)) as $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);
            $rows[] = [
                'pedido_id' => trim((string) ($parts[0] ?? '')),
                'fecha_grupo' => trim((string) ($parts[1] ?? '')),
                'fecha_evento' => trim((string) ($parts[2] ?? '')),
                'hc_number' => trim((string) ($parts[3] ?? '')),
                'codigo_examen' => trim((string) ($parts[4] ?? '')),
                'apellidos' => trim((string) ($parts[5] ?? '')),
                'nombres' => trim((string) ($parts[6] ?? '')),
                'paciente_full' => trim((string) ($parts[7] ?? '')),
                'email' => trim((string) ($parts[8] ?? '')),
                'fecha_nac' => trim((string) ($parts[9] ?? '')),
                'sexo' => trim((string) ($parts[10] ?? '')),
                'ciudad' => trim((string) ($parts[11] ?? '')),
                'afiliacion' => trim((string) ($parts[12] ?? '')),
                'telefono' => trim((string) ($parts[13] ?? '')),
                'procedimiento' => trim((string) ($parts[14] ?? '')),
                'doctor_agenda' => trim((string) ($parts[15] ?? '')),
                'agenda_dpto' => trim((string) ($parts[16] ?? '')),
                'sede_departamento' => trim((string) ($parts[17] ?? '')),
                'cie10' => trim((string) ($parts[18] ?? '')),
                'estado_agenda' => trim((string) ($parts[19] ?? '')),
                'estado' => trim((string) ($parts[20] ?? '')),
                'referido_prefactura_por' => trim((string) ($parts[21] ?? '')),
                'especificar_referido_prefactura' => trim((string) ($parts[22] ?? '')),
                'codigo_derivacion' => trim((string) ($parts[23] ?? '')),
                'num_secuencial_derivacion' => trim((string) ($parts[24] ?? '')),
                'prefactura' => trim((string) ($parts[25] ?? '')),
            ];
        }

        $this->lastError = null;
        return $rows;
    }

    private function isDirectDbAvailable(): bool
    {
        if ($this->directDbAvailable !== null) {
            return $this->directDbAvailable;
        }

        try {
            DB::connection($this->dbConnection)->getPdo();
            $this->directDbAvailable = true;
            return true;
        } catch (Throwable $e) {
            $this->directDbAvailable = false;
            $this->lastError = 'Conexión Sigcenter no disponible: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<string,string>
     */
    private function fetchTableColumns(string $table): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        $columns = [];
        foreach ($rows as $row) {
            $column = trim((string) ($row->COLUMN_NAME ?? ''));
            if ($column === '') {
                continue;
            }
            $columns[strtolower($column)] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row): array
    {
        $surnames = $this->splitSurnames($row['apellidos'] ?? '');
        $names = $this->splitGivenNames($row['nombres'] ?? '');
        $fechaHora = $this->normalizeFechaHora($row['fecha_evento'] ?? '');
        $sede = $this->normalizeWhitespace($row['sede_departamento'] ?? '');
        if ($sede === '') {
            $sede = $this->inferSedeFromAgendaDpto($row['agenda_dpto'] ?? '');
        }

        return [
            'hcNumber' => $this->normalizeWhitespace($row['hc_number'] ?? ''),
            'form_id' => $this->normalizeWhitespace($row['pedido_id'] ?? ''),
            'procedimiento_proyectado' => $this->normalizeWhitespace($row['procedimiento'] ?? ''),
            'doctor' => $this->normalizeWhitespace($row['doctor_agenda'] ?? ''),
            'cie10' => $this->normalizeWhitespace($row['cie10'] ?? ''),
            'estado_agenda' => $this->normalizeWhitespace($row['estado_agenda'] ?? ''),
            'estado' => $this->normalizeWhitespace($row['estado'] ?? ''),
            'codigo_derivacion' => $this->normalizeWhitespace($row['codigo_derivacion'] ?? ''),
            'num_secuencial_derivacion' => $this->normalizeWhitespace($row['num_secuencial_derivacion'] ?? ''),
            'referido_prefactura_por' => $this->normalizeWhitespace($row['referido_prefactura_por'] ?? ''),
            'especificar_referido_prefactura' => $this->normalizeWhitespace($row['especificar_referido_prefactura'] ?? ''),
            'prefactura' => $this->normalizeWhitespace($row['prefactura'] ?? ''),
            'fname' => $names['fname'],
            'mname' => $names['mname'],
            'lname' => $surnames['lname'],
            'lname2' => $surnames['lname2'],
            'email' => $this->normalizeWhitespace($row['email'] ?? ''),
            'fecha_nacimiento' => $this->normalizeDateOnly($row['fecha_nac'] ?? ''),
            'sexo' => $this->normalizeWhitespace($row['sexo'] ?? ''),
            'ciudad' => $this->normalizeWhitespace($row['ciudad'] ?? ''),
            'afiliacion' => $this->normalizeWhitespace($row['afiliacion'] ?? ''),
            'telefono' => $this->normalizeWhitespace($row['telefono'] ?? ''),
            'fecha' => $fechaHora['fecha'],
            'hora' => $fechaHora['hora'],
            'nombre_completo' => $this->normalizeWhitespace($row['paciente_full'] ?? ''),
            'sede_departamento' => $sede,
            'has_apellidos_source' => $this->normalizeWhitespace($row['apellidos'] ?? '') !== '',
            'has_nombres_source' => $this->normalizeWhitespace($row['nombres'] ?? '') !== '',
        ];
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function upsertPatientData(array $normalized): void
    {
        $payload = [
            'hc_number' => $normalized['hcNumber'],
            'lname' => $normalized['lname'] ?: 'DESCONOCIDO',
            'lname2' => $this->nullableString($normalized['lname2'] ?? null),
            'fname' => $this->nullableString($normalized['fname'] ?? null),
            'mname' => $this->nullableString($normalized['mname'] ?? null),
            'email' => $this->nullableString($normalized['email'] ?? null),
            'fecha_nacimiento' => $normalized['fecha_nacimiento'] ?: null,
            'sexo' => $this->nullableString($normalized['sexo'] ?? null),
            'ciudad' => $this->nullableString($normalized['ciudad'] ?? null),
            'afiliacion' => $this->nullableString($normalized['afiliacion'] ?? null),
            'celular' => $this->nullableString($normalized['telefono'] ?? null),
            'created_by_type' => 'cron',
            'created_by_identifier' => 'artisan:index-admisiones:sync',
            'updated_by_type' => 'cron',
            'updated_by_identifier' => 'artisan:index-admisiones:sync',
        ];

        $insert = [];
        foreach ($payload as $key => $value) {
            if (isset($this->patientColumns[strtolower($key)])) {
                $insert[$this->patientColumns[strtolower($key)]] = $value;
            }
        }

        if ($insert === [] || !isset($insert['hc_number'])) {
            return;
        }

        $columns = array_keys($insert);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $params = [];
        foreach ($insert as $column => $value) {
            $params[':' . $column] = $value;
        }

        $updates = [];
        foreach (['email', 'sexo', 'ciudad', 'afiliacion', 'celular'] as $column) {
            if (!array_key_exists($column, $insert)) {
                continue;
            }
            $updates[] = sprintf(
                "%s = IF(VALUES(%s) = '' OR VALUES(%s) IS NULL, %s, VALUES(%s))",
                $column,
                $column,
                $column,
                $column,
                $column
            );
        }
        $hasApellidosSource = (bool) ($normalized['has_apellidos_source'] ?? false);
        foreach (['lname', 'lname2'] as $column) {
            if (!array_key_exists($column, $insert)) {
                continue;
            }
            if ($hasApellidosSource) {
                $updates[] = sprintf('%s = VALUES(%s)', $column, $column);
                continue;
            }
            $updates[] = sprintf(
                "%s = IF(VALUES(%s) = '' OR VALUES(%s) IS NULL, %s, VALUES(%s))",
                $column,
                $column,
                $column,
                $column,
                $column
            );
        }
        $hasNombresSource = (bool) ($normalized['has_nombres_source'] ?? false);
        foreach (['fname', 'mname'] as $column) {
            if (!array_key_exists($column, $insert)) {
                continue;
            }
            if ($hasNombresSource) {
                $updates[] = sprintf('%s = VALUES(%s)', $column, $column);
                continue;
            }
            $updates[] = sprintf(
                "%s = IF(VALUES(%s) = '' OR VALUES(%s) IS NULL, %s, VALUES(%s))",
                $column,
                $column,
                $column,
                $column,
                $column
            );
        }
        if (isset($insert['fecha_nacimiento'])) {
            $updates[] = 'fecha_nacimiento = IF(VALUES(fecha_nacimiento) IS NULL, fecha_nacimiento, VALUES(fecha_nacimiento))';
        }
        if (isset($this->patientColumns['updated_by_type'])) {
            $updates[] = 'updated_by_type = VALUES(updated_by_type)';
        }
        if (isset($this->patientColumns['updated_by_identifier'])) {
            $updates[] = 'updated_by_identifier = VALUES(updated_by_identifier)';
        }
        if (isset($this->patientColumns['updated_at'])) {
            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        DB::statement(
            sprintf(
                'INSERT INTO patient_data (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(', ', $columns),
                implode(', ', $placeholders),
                implode(', ', $updates)
            ),
            $params
        );
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function upsertProcedimientoProyectado(array $normalized): void
    {
        $insert = [];
        $base = [
            'form_id' => is_numeric($normalized['form_id'] ?? null) ? (int) $normalized['form_id'] : null,
            'procedimiento_proyectado' => $normalized['procedimiento_proyectado'] ?? null,
            'doctor' => $this->nullableString($normalized['doctor'] ?? null),
            'hc_number' => $normalized['hcNumber'] ?? null,
            'sede_departamento' => $this->nullableString($normalized['sede_departamento'] ?? null),
            'estado_agenda' => $this->nullableString(($normalized['estado_agenda'] ?? '') ?: ($normalized['estado'] ?? '')),
            'afiliacion' => $this->nullableString($normalized['afiliacion'] ?? null),
            'fecha' => $normalized['fecha'] ?: null,
            'hora' => $normalized['hora'] ?: null,
        ];

        foreach ($base as $key => $value) {
            if (isset($this->procedimientoColumns[strtolower($key)])) {
                $insert[$this->procedimientoColumns[strtolower($key)]] = $value;
            }
        }

        $referidoColumn = $this->resolveProcedimientoColumn(['referido_prefactura_por', 'id_procedencia']);
        if ($referidoColumn !== null) {
            $insert[$referidoColumn] = $this->nullableString($normalized['referido_prefactura_por'] ?? null);
        }

        $especificarColumn = $this->resolveProcedimientoColumn(['especificar_referido_prefactura', 'especificar_por', 'especificarpor']);
        if ($especificarColumn !== null) {
            $insert[$especificarColumn] = $this->nullableString($normalized['especificar_referido_prefactura'] ?? null);
        }

        if ($insert === [] || !isset($insert['form_id']) || !isset($insert['hc_number'])) {
            return;
        }

        $columns = array_keys($insert);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $params = [];
        foreach ($insert as $column => $value) {
            $params[':' . $column] = $value;
        }

        $updates = [];
        if (isset($insert['procedimiento_proyectado'])) {
            $updates[] = 'procedimiento_proyectado = VALUES(procedimiento_proyectado)';
        }
        if (isset($insert['doctor'])) {
            $updates[] = 'doctor = VALUES(doctor)';
        }
        foreach (['sede_departamento', 'id_sede', 'estado_agenda', 'afiliacion'] as $column) {
            if (!isset($insert[$column])) {
                continue;
            }
            $updates[] = sprintf(
                "%s = IF(VALUES(%s) IS NULL OR VALUES(%s) = '', %s, VALUES(%s))",
                $column,
                $column,
                $column,
                $column,
                $column
            );
        }
        foreach ([$referidoColumn, $especificarColumn] as $column) {
            if ($column === null || !isset($insert[$column])) {
                continue;
            }
            $updates[] = sprintf(
                "%s = IF(VALUES(%s) IS NULL OR VALUES(%s) = '', %s, VALUES(%s))",
                $column,
                $column,
                $column,
                $column,
                $column
            );
        }
        if (isset($insert['fecha'])) {
            $updates[] = 'fecha = VALUES(fecha)';
        }
        if (isset($insert['hora'])) {
            $updates[] = 'hora = VALUES(hora)';
        }
        if (isset($this->procedimientoColumns['updated_at'])) {
            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        DB::statement(
            sprintf(
                'INSERT INTO procedimiento_proyectado (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                implode(', ', $columns),
                implode(', ', $placeholders),
                implode(', ', $updates)
            ),
            $params
        );
    }

    private function resolveProcedimientoColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $lookup = strtolower(trim((string) $candidate));
            if ($lookup !== '' && isset($this->procedimientoColumns[$lookup])) {
                return $this->procedimientoColumns[$lookup];
            }
        }

        return null;
    }

    /**
     * @return array{fecha:string,hora:string}
     */
    private function normalizeFechaHora(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['fecha' => '', 'hora' => ''];
        }

        try {
            $date = new DateTimeImmutable($value);
            return [
                'fecha' => $date->format('Y-m-d'),
                'hora' => $date->format('H:i:s'),
            ];
        } catch (Throwable) {
            return ['fecha' => '', 'hora' => ''];
        }
    }

    private function normalizeDateOnly(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeWhitespace(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }

    private function nullableString(?string $value): ?string
    {
        $value = $this->normalizeWhitespace($value);
        return $value !== '' ? $value : null;
    }

    private function inferSedeFromAgendaDpto(?string $value): string
    {
        $value = mb_strtoupper($this->normalizeWhitespace($value), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $posCeibos = mb_strrpos($value, 'CEIBOS', 0, 'UTF-8');
        $posMatriz = mb_strrpos($value, 'MATRIZ', 0, 'UTF-8');
        if ($posCeibos === false && $posMatriz === false) {
            return '';
        }

        if ($posCeibos !== false && ($posMatriz === false || $posCeibos > $posMatriz)) {
            return 'CEIBOS';
        }

        return 'MATRIZ';
    }

    /**
     * @return array{lname:string,lname2:string}
     */
    private function splitSurnames(?string $value): array
    {
        $value = $this->normalizeWhitespace($value);
        if ($value === '') {
            return ['lname' => '', 'lname2' => ''];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];
        [$lname, $nextIndex] = $this->takeNameSegment($tokens, 0);
        $remaining = array_slice($tokens, $nextIndex);

        return [
            'lname' => $lname,
            'lname2' => $this->normalizeWhitespace(implode(' ', $remaining)),
        ];
    }

    /**
     * @return array{fname:string,mname:string}
     */
    private function splitGivenNames(?string $value): array
    {
        $value = $this->normalizeWhitespace($value);
        if ($value === '') {
            return ['fname' => '', 'mname' => ''];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];

        return [
            'fname' => (string) ($tokens[0] ?? ''),
            'mname' => $this->normalizeWhitespace(implode(' ', array_slice($tokens, 1))),
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{0:string,1:int}
     */
    private function takeNameSegment(array $tokens, int $index): array
    {
        if (!isset($tokens[$index])) {
            return ['', $index];
        }

        $upper = static fn (string $value): string => mb_strtoupper($value, 'UTF-8');

        if (isset($tokens[$index + 2])) {
            $first = $upper($tokens[$index]);
            $second = $upper($tokens[$index + 1]);
            if ($first === 'DE' && in_array($second, ['LA', 'LAS', 'LOS'], true)) {
                return [implode(' ', array_slice($tokens, $index, 3)), $index + 3];
            }
        }

        if (isset($tokens[$index + 1])) {
            $first = $upper($tokens[$index]);
            if (in_array($first, ['DE', 'DEL'], true)) {
                return [implode(' ', array_slice($tokens, $index, 2)), $index + 2];
            }
        }

        return [$tokens[$index], $index + 1];
    }

    private function ssh(): ?\phpseclib3\Net\SSH2
    {
        if ($this->ssh instanceof \phpseclib3\Net\SSH2) {
            return $this->ssh;
        }

        if (!$this->canQueryViaSsh()) {
            $this->lastError = 'Consulta por SSH a Sigcenter no configurada.';
            return null;
        }

        $ssh = new \phpseclib3\Net\SSH2((string) $this->sshHost, $this->sshPort, 20);
        if (!$ssh->login((string) $this->sshUser, (string) $this->sshPass)) {
            $this->lastError = 'No se pudo autenticar por SSH contra Sigcenter.';
            return null;
        }

        $this->ssh = $ssh;
        return $this->ssh;
    }

    private function canQueryViaSsh(): bool
    {
        return $this->sshHost !== null
            && $this->sshUser !== null
            && $this->sshPass !== null
            && $this->dbDatabase !== null
            && $this->dbUsername !== null
            && class_exists('\\phpseclib3\\Net\\SSH2');
    }

    private function readEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
