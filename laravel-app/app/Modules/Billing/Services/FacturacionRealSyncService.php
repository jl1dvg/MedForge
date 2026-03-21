<?php

namespace App\Modules\Billing\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class FacturacionRealSyncService
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
     * @param array{start:string,end:string} $options
     * @param callable(string,array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function sync(array $options, ?callable $onProgress = null): array
    {
        $this->lastError = null;
        $startedAt = microtime(true);
        $start = $this->parseMonth((string) ($options['start'] ?? ''));
        $end = $this->parseMonth((string) ($options['end'] ?? ''));
        if ($start > $end) {
            throw new \InvalidArgumentException('Rango inválido: start es mayor que end.');
        }

        $months = [];
        $stats = [
            'total_rows' => 0,
            'sent_rows' => 0,
            'error_rows' => 0,
        ];

        foreach ($this->monthIter($start, $end) as $monthKey) {
            $rows = $this->fetchRowsForMonth($monthKey);
            if ($rows === [] && $this->lastError !== null) {
                return [
                    'success' => false,
                    'error' => $this->lastError,
                    'from' => $start->format('Y-m'),
                    'to' => $end->format('Y-m'),
                    'source' => $this->isDirectDbAvailable() ? 'sigcenter-db' : 'sigcenter-ssh',
                ];
            }

            $months[] = $monthKey;
            $stats['total_rows'] += count($rows);

            foreach ($rows as $row) {
                try {
                    $this->upsertRow($row, $monthKey);
                    $stats['sent_rows']++;
                    $onProgress && $onProgress('row', [
                        'form_id' => $row['form_id'] ?? '',
                        'factura_id' => $row['factura_id'] ?? '',
                        'numero_factura' => $row['numero_factura'] ?? '',
                        'monto_honorario' => $row['monto_honorario'] ?? null,
                        'source_month' => $monthKey,
                    ]);
                } catch (Throwable $e) {
                    $stats['error_rows']++;
                    $this->lastError = $e->getMessage();
                    $onProgress && $onProgress('error', [
                        'form_id' => $row['form_id'] ?? '',
                        'factura_id' => $row['factura_id'] ?? '',
                        'error' => $e->getMessage(),
                        'source_month' => $monthKey,
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'from' => $start->format('Y-m'),
            'to' => $end->format('Y-m'),
            'months' => $months,
            'total_rows' => $stats['total_rows'],
            'sent_rows' => $stats['sent_rows'],
            'error_rows' => $stats['error_rows'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => $this->isDirectDbAvailable() ? 'sigcenter-db' : 'sigcenter-ssh',
        ];
    }

    private function parseMonth(string $value): DateTimeImmutable
    {
        $month = DateTimeImmutable::createFromFormat('Y-m', trim($value));
        if (!$month || $month->format('Y-m') !== trim($value)) {
            throw new \InvalidArgumentException(sprintf('Mes inválido: %s', $value));
        }

        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), 1);
    }

    /**
     * @return iterable<string>
     */
    private function monthIter(DateTimeImmutable $start, DateTimeImmutable $end): iterable
    {
        $current = $start;
        while ($current <= $end) {
            yield $current->format('Y-m');
            $current = $current->modify('+1 month');
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRowsForMonth(string $monthKey): array
    {
        [$year, $month] = array_map('intval', explode('-', $monthKey, 2));

        if ($this->isDirectDbAvailable()) {
            $rows = DB::connection($this->dbConnection)->select($this->baseSql(), [
                'mes' => $month,
                'anio' => $year,
            ]);

            return array_map(static fn ($row): array => (array) $row, $rows);
        }

        return $this->fetchRowsViaSsh($month, $year);
    }

    private function baseSql(): string
    {
        return <<<'SQL'
SELECT
    base.form_id,
    GROUP_CONCAT(DISTINCT NULLIF(base.detalle_factura_id, '') ORDER BY base.detalle_factura_id SEPARATOR ' | ') AS detalle_factura_ids,
    GROUP_CONCAT(DISTINCT NULLIF(base.producto_id, '') ORDER BY base.producto_id SEPARATOR ' | ') AS producto_ids,
    GROUP_CONCAT(DISTINCT NULLIF(base.codigo_producto, '') ORDER BY base.codigo_producto SEPARATOR ' | ') AS codigos_producto,
    MAX(base.factura_id) AS factura_id,
    MAX(base.numero_factura) AS numero_factura,
    CASE
        WHEN COUNT(DISTINCT NULLIF(base.procedimiento, '')) <= 1 THEN MAX(base.procedimiento)
        ELSE GROUP_CONCAT(DISTINCT NULLIF(base.procedimiento, '') ORDER BY base.procedimiento SEPARATOR ' || ')
    END AS procedimiento,
    CASE
        WHEN COUNT(DISTINCT NULLIF(base.realizado_por, '')) <= 1 THEN MAX(base.realizado_por)
        ELSE GROUP_CONCAT(DISTINCT NULLIF(base.realizado_por, '') ORDER BY base.realizado_por SEPARATOR ' | ')
    END AS realizado_por,
    MAX(base.afiliacion) AS afiliacion,
    MAX(base.paciente) AS paciente,
    MAX(base.cliente) AS cliente,
    MAX(base.fecha_agenda) AS fecha_agenda,
    MAX(base.fecha_facturacion) AS fecha_facturacion,
    MAX(base.fecha_atencion) AS fecha_atencion,
    GROUP_CONCAT(DISTINCT NULLIF(base.formas_pago, '') ORDER BY base.formas_pago SEPARATOR ' | ') AS formas_pago,
    GROUP_CONCAT(DISTINCT NULLIF(base.codigo_nota, '') ORDER BY base.codigo_nota SEPARATOR ' | ') AS codigo_nota,
    ROUND(COALESCE(SUM(base.monto_honorario), 0), 4) AS monto_honorario,
    MAX(base.monto_facturado) AS monto_facturado,
    MAX(base.area) AS area,
    MAX(base.departamento_factura) AS departamento_factura,
    MAX(base.estado) AS estado
FROM (
    SELECT
        CAST(cspdf.docSolicitudProcedimiento_id AS CHAR) AS form_id,
        CAST(cspdf.detalleFactura_id AS CHAR) AS detalle_factura_id,
        CAST(df.productos_id AS CHAR) AS producto_id,
        TRIM(COALESCE(prod.codigo, '')) AS codigo_producto,
        CONCAT_WS(' | ', prod.codigo, prod.nombre) AS procedimiento,
        CASE
            WHEN hct.id IS NULL AND oe.radiologo_id IS NULL
                THEN CONCAT_WS(' ', agendaTrabajador.NOMBRES, agendaTrabajador.APELLIDOS)
            WHEN hct.id IS NULL AND oe.radiologo_id IS NOT NULL
                THEN CONCAT_WS(' ', radiologoTrabajador.NOMBRES, radiologoTrabajador.APELLIDOS)
            ELSE
                CONCAT_WS(' ', cirujanoTrabajador.NOMBRES, cirujanoTrabajador.APELLIDOS)
        END AS realizado_por,
        af.NOMBRE AS afiliacion,
        CONCAT_WS(' ', p.NOMBRES, p.APELLIDOS) AS paciente,
        CONCAT_WS(' ', cli.NOMBRES, cli.APELLIDOS) AS cliente,
        ad.FECHA_INICIO AS fecha_agenda,
        f.fecha_facturacion AS fecha_facturacion,
        CASE
            WHEN ad.ID_AGENDA_DOCTOR IS NOT NULL THEN dsp.fechaUltimoGuardar
            WHEN oe.id IS NOT NULL THEN oe.fechaFin
            ELSE dsp.fecha_atencion
        END AS fecha_atencion,
        CONCAT_WS('-', f.codigoEstablecimiento, f.codigo_punto_emision, f.codigo_factura) AS numero_factura,
        CAST(f.id AS CHAR) AS factura_id,
        GROUP_CONCAT(DISTINCT fp.nombre SEPARATOR ', ') AS formas_pago,
        nc.codigo_nota AS codigo_nota,
        ROUND(
            (
                COALESCE(ROUND(df.cantidad * df.precio, 2), 0)
                - COALESCE(df.descuento_total, 0)
                - COALESCE(df.descuento_bos, 0)
            ) / NULLIF(df.cantidad, 0),
            2
        ) AS monto_honorario,
        f.total_factura AS monto_facturado,
        dep.NOMBRE AS area,
        f.almacen_nombre AS departamento_factura,
        CASE dsp.estado_id
            WHEN 1 THEN 'GENERADAS'
            WHEN 2 THEN 'ATENDIDAS'
            WHEN 3 THEN 'REVISADAS'
            WHEN 4 THEN 'ENVIADAS'
            ELSE 'GENERADA'
        END AS estado
    FROM conv_solicitud_procedimiento_detalle_factura cspdf
    INNER JOIN detalles_facturas df
        ON cspdf.detalleFactura_id = df.id
    INNER JOIN facturas f
        ON df.facturas_id = f.id
    INNER JOIN doc_solicitud_procedimientos dsp
        ON cspdf.docSolicitudProcedimiento_id = dsp.id
    LEFT JOIN departamento dep
        ON dep.ID_DEPARTAMENTO = dsp.externa_hospitalizacion
    LEFT JOIN agenda_doctor ad
        ON dsp.agenda_doctorId = ad.ID_AGENDA_DOCTOR
    LEFT JOIN trabajador agendaTrabajador
        ON agendaTrabajador.ID_TRABAJADOR = ad.ID_TRABAJADOR
    LEFT JOIN hc_cirugia_trabajador hct
        ON cspdf.hcCirugiaTrabajador_id = hct.id
    LEFT JOIN trabajador cirujanoTrabajador
        ON cirujanoTrabajador.ID_TRABAJADOR = hct.cirujano_id
    LEFT JOIN orden_examen oe
        ON oe.docSolicitudProcedimiento_id = dsp.id
    LEFT JOIN trabajador radiologoTrabajador
        ON radiologoTrabajador.ID_TRABAJADOR = oe.radiologo_id
    INNER JOIN doc_solicitud_paciente dspac
        ON dsp.doc_solicitud_pacienteId = dspac.id
    INNER JOIN afiliacion af
        ON af.ID_AFILIACION = dspac.afiliacionId
    INNER JOIN paciente p
        ON p.ID_PACIENTE = dspac.pacienteId
    INNER JOIN paciente cli
        ON cli.ID_PACIENTE = f.clientes_id
    INNER JOIN productos prod
        ON df.productos_id = prod.id
    INNER JOIN procedimiento proc
        ON dsp.procedimientoId = proc.ID_PROCEDIMIENTO
    INNER JOIN tipo_procedimiento tp
        ON tp.ID_TIPO_PROCEDIMIENTO = proc.ID_TIPO_PROCEDIMIENTO
    LEFT JOIN factura_formapago ffp
        ON ffp.factura_id = f.id
    LEFT JOIN formas_pagos fp
        ON ffp.formaPago_id = fp.id
    LEFT JOIN nota_credito nc
        ON nc.factura_id = f.id
    WHERE f.estado_id <> 5
      AND (
          ad.ID_TRABAJADOR IS NOT NULL
          OR hct.cirujano_id IS NOT NULL
          OR oe.radiologo_id IS NOT NULL
      )
      AND (
          (MONTH(ad.FECHA_INICIO) = :mes AND YEAR(ad.FECHA_INICIO) = :anio)
          OR (MONTH(oe.fechaFin) = :mes AND YEAR(oe.fechaFin) = :anio)
          OR (MONTH(dsp.fecha_atencion) = :mes AND YEAR(dsp.fecha_atencion) = :anio)
      )
    GROUP BY cspdf.id
) AS base
GROUP BY base.form_id
ORDER BY MAX(base.fecha_agenda) ASC, base.form_id ASC
SQL;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRowsViaSsh(int $month, int $year): array
    {
        $ssh = $this->ssh();
        if ($ssh === null) {
            return [];
        }

        $sql = str_replace(
            [':mes', ':anio'],
            [(string) $month, (string) $year],
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
            $this->lastError = 'Consulta remota de facturación real por SSH falló.';
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r?\n/', trim((string) $output)) as $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);
            $rows[] = [
                'form_id' => trim((string) ($parts[0] ?? '')),
                'detalle_factura_ids' => trim((string) ($parts[1] ?? '')),
                'producto_ids' => trim((string) ($parts[2] ?? '')),
                'codigos_producto' => trim((string) ($parts[3] ?? '')),
                'factura_id' => trim((string) ($parts[4] ?? '')),
                'numero_factura' => trim((string) ($parts[5] ?? '')),
                'procedimiento' => trim((string) ($parts[6] ?? '')),
                'realizado_por' => trim((string) ($parts[7] ?? '')),
                'afiliacion' => trim((string) ($parts[8] ?? '')),
                'paciente' => trim((string) ($parts[9] ?? '')),
                'cliente' => trim((string) ($parts[10] ?? '')),
                'fecha_agenda' => trim((string) ($parts[11] ?? '')),
                'fecha_facturacion' => trim((string) ($parts[12] ?? '')),
                'fecha_atencion' => trim((string) ($parts[13] ?? '')),
                'formas_pago' => trim((string) ($parts[14] ?? '')),
                'codigo_nota' => trim((string) ($parts[15] ?? '')),
                'monto_honorario' => trim((string) ($parts[16] ?? '')),
                'monto_facturado' => trim((string) ($parts[17] ?? '')),
                'area' => trim((string) ($parts[18] ?? '')),
                'departamento_factura' => trim((string) ($parts[19] ?? '')),
                'estado' => trim((string) ($parts[20] ?? '')),
            ];
        }

        $this->lastError = null;
        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function upsertRow(array $row, string $monthKey): void
    {
        $formId = trim((string) ($row['form_id'] ?? ''));
        if ($formId === '') {
            return;
        }

        $payloadForHash = [
            'form_id' => $formId,
            'factura_id' => trim((string) ($row['factura_id'] ?? '')),
            'numero_factura' => trim((string) ($row['numero_factura'] ?? '')),
            'procedimiento' => trim((string) ($row['procedimiento'] ?? '')),
            'fecha_facturacion' => $this->normalizeDateTime($row['fecha_facturacion'] ?? null) ?? '',
            'monto_honorario' => $this->parseAmount($row['monto_honorario'] ?? null) ?? '',
        ];

        $params = [
            'dedupe_key' => md5((string) json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'form_id' => $this->nullableTrim($formId, 50),
            'procedimiento' => $this->nullableTrim($row['procedimiento'] ?? null, 255),
            'realizado_por' => $this->nullableTrim($row['realizado_por'] ?? null, 255),
            'afiliacion' => $this->nullableTrim($row['afiliacion'] ?? null, 255),
            'paciente' => $this->nullableTrim($row['paciente'] ?? null, 255),
            'cliente' => $this->nullableTrim($row['cliente'] ?? null, 255),
            'fecha_agenda' => $this->normalizeDateTime($row['fecha_agenda'] ?? null),
            'fecha_facturacion' => $this->normalizeDateTime($row['fecha_facturacion'] ?? null),
            'fecha_atencion' => $this->normalizeDateTime($row['fecha_atencion'] ?? null),
            'numero_factura' => $this->nullableTrim($row['numero_factura'] ?? null, 50),
            'factura_id' => $this->nullableTrim($row['factura_id'] ?? null, 50),
            'formas_pago' => $this->nullableTrim($row['formas_pago'] ?? null, 255),
            'codigo_nota' => $this->nullableTrim($row['codigo_nota'] ?? null, 50),
            'monto_honorario' => $this->parseAmount($row['monto_honorario'] ?? null),
            'monto_facturado' => $this->parseAmount($row['monto_facturado'] ?? null),
            'area' => $this->nullableTrim($row['area'] ?? null, 255),
            'estado' => $this->nullableTrim($row['estado'] ?? null, 100),
            'source_month' => $this->nullableTrim($monthKey, 7),
            'raw_payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        DB::table('billing_facturacion_real')->updateOrInsert(
            ['form_id' => $formId],
            $params + ['updated_at' => now()]
        );
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseAmount(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/u', '', $raw);
        if ($normalized === null || in_array($normalized, ['', '-', '.', ','], true)) {
            return null;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 4) : null;
    }

    private function nullableTrim(mixed $value, ?int $maxLength = null): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if ($maxLength !== null && $maxLength > 0) {
            $value = function_exists('mb_substr')
                ? mb_substr($value, 0, $maxLength)
                : substr($value, 0, $maxLength);
        }

        return $value;
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

    private function canQueryViaSsh(): bool
    {
        return $this->sshHost !== null
            && $this->sshUser !== null
            && $this->sshPass !== null
            && $this->dbDatabase !== null
            && $this->dbUsername !== null
            && class_exists('\\phpseclib3\\Net\\SSH2');
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
