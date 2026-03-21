<?php

namespace App\Modules\Farmacia\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecetasConciliacionSyncService
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
     * @param array{from_date:string,to_date:string} $options
     * @param callable(string,array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function sync(array $options, ?callable $onProgress = null): array
    {
        $this->lastError = null;
        $startedAt = microtime(true);

        $fromDate = $this->parseDate((string) ($options['from_date'] ?? ''));
        $toDate = $this->parseDate((string) ($options['to_date'] ?? ''));
        if ($fromDate > $toDate) {
            throw new \InvalidArgumentException('Rango inválido: from_date es mayor que to_date.');
        }

        $rows = $this->fetchRows($fromDate, $toDate);
        if ($rows === [] && $this->lastError !== null) {
            return [
                'success' => false,
                'error' => $this->lastError,
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
                'source' => $this->isDirectDbAvailable() ? 'sigcenter-db' : 'sigcenter-ssh',
            ];
        }

        $sentRows = 0;
        $errorRows = 0;

        foreach ($rows as $row) {
            try {
                $this->upsertRow($row, $fromDate, $toDate);
                $sentRows++;
                $onProgress && $onProgress('row', [
                    'receta_id' => $row['receta_id'] ?? '',
                    'pedido_id' => $row['pedido_id'] ?? '',
                    'tipo_match' => $row['tipo_match'] ?? '',
                    'fecha_receta' => $row['fecha_receta'] ?? '',
                    'departamento_factura' => $row['departamento_factura'] ?? '',
                    'monto_linea_neto' => $row['monto_linea_neto'] ?? null,
                ]);
            } catch (Throwable $e) {
                $errorRows++;
                $this->lastError = $e->getMessage();
                $onProgress && $onProgress('error', [
                    'receta_id' => $row['receta_id'] ?? '',
                    'pedido_id' => $row['pedido_id'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d'),
            'total_rows' => count($rows),
            'sent_rows' => $sentRows,
            'error_rows' => $errorRows,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => $this->isDirectDbAvailable() ? 'sigcenter-db' : 'sigcenter-ssh',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(DateTimeImmutable $fromDate, DateTimeImmutable $toDate): array
    {
        $bindings = [
            'from_date' => $fromDate->format('Y-m-d'),
            'to_date' => $toDate->format('Y-m-d'),
        ];

        if ($this->isDirectDbAvailable()) {
            $rows = DB::connection($this->dbConnection)->select($this->baseSql(), $bindings);
            return array_map(static fn ($row): array => (array) $row, $rows);
        }

        return $this->fetchRowsViaSsh($bindings['from_date'], $bindings['to_date']);
    }

    private function baseSql(): string
    {
        return <<<'SQL'
WITH recetas_base AS (
    SELECT
        r.idRecetas AS receta_id,
        r.doc_sol_proc_id AS pedido_id,
        dspac.pacienteId AS paciente_id_match,
        p.IDENTIFICACION AS cedula_paciente,
        CONCAT_WS(' ', p.APELLIDOS, p.NOMBRES) AS paciente,
        COALESCE(r.productoDespachado_id, r.producto_id) AS producto_receta_id,
        pr.codigo AS codigo_producto_receta,
        pr.nombre AS producto_receta,
        DATE(COALESCE(r.fecha_despacho, r.fecha_inicio, r.fecha_ingreso)) AS fecha_receta,
        COALESCE(r.total_farmacia, 0) AS total_farmacia
    FROM recetas r
    INNER JOIN doc_solicitud_procedimientos dsp
        ON dsp.id = r.doc_sol_proc_id
    INNER JOIN doc_solicitud_paciente dspac
        ON dspac.id = dsp.doc_solicitud_pacienteId
    INNER JOIN paciente p
        ON p.ID_PACIENTE = dspac.pacienteId
    LEFT JOIN productos pr
        ON pr.id = COALESCE(r.productoDespachado_id, r.producto_id)
    WHERE DATE(COALESCE(r.fecha_despacho, r.fecha_inicio, r.fecha_ingreso))
          BETWEEN :from_date AND :to_date
),
facturas_base AS (
    SELECT
        df.id AS detalle_factura_id,
        df.facturas_id AS factura_id,
        f.clientes_id AS paciente_id_match,
        cli.IDENTIFICACION AS cedula_cliente_factura,
        DATE(f.fecha_facturacion) AS fecha_factura,
        f.fecha_facturacion,
        f.almacen_nombre AS departamento_factura,
        df.productos_id AS producto_factura_id,
        pf.codigo AS codigo_producto_factura,
        pf.nombre AS producto_factura,
        df.cantidad AS cantidad_facturada,
        df.precio AS precio_unitario_facturado,
        COALESCE(df.descuento_total, 0) AS descuento_total_linea,
        COALESCE(df.descuento_bos, 0) AS descuento_bos_linea,
        ROUND(
            COALESCE(df.cantidad * df.precio, 0)
            - COALESCE(df.descuento_total, 0)
            - COALESCE(df.descuento_bos, 0),
            2
        ) AS monto_linea_neto,
        ROUND(
            (
                COALESCE(df.cantidad * df.precio, 0)
                - COALESCE(df.descuento_total, 0)
                - COALESCE(df.descuento_bos, 0)
            ) / NULLIF(df.cantidad, 0),
            4
        ) AS monto_linea_unitario_neto
    FROM detalles_facturas df
    INNER JOIN facturas f
        ON f.id = df.facturas_id
    LEFT JOIN paciente cli
        ON cli.ID_PACIENTE = f.clientes_id
    LEFT JOIN productos pf
        ON pf.id = df.productos_id
    WHERE f.estado_id <> 5
      AND UPPER(COALESCE(f.almacen_nombre, '')) LIKE '%FARMACIA%'
      AND DATE(f.fecha_facturacion) BETWEEN DATE_SUB(:from_date, INTERVAL 7 DAY)
                                        AND DATE_ADD(:to_date, INTERVAL 7 DAY)
),
candidatos AS (
    SELECT
        r.receta_id,
        r.pedido_id,
        r.cedula_paciente,
        r.paciente,
        r.producto_receta_id,
        r.codigo_producto_receta,
        r.producto_receta,
        r.fecha_receta,
        r.total_farmacia,
        f.factura_id,
        f.detalle_factura_id,
        f.fecha_factura,
        f.fecha_facturacion,
        f.departamento_factura,
        f.cedula_cliente_factura,
        f.producto_factura_id,
        f.codigo_producto_factura,
        f.producto_factura,
        f.cantidad_facturada,
        f.precio_unitario_facturado,
        f.descuento_total_linea,
        f.descuento_bos_linea,
        f.monto_linea_neto,
        f.monto_linea_unitario_neto,
        ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)) AS diff_dias,
        CASE
            WHEN f.factura_id IS NULL THEN 'sin_match'
            WHEN f.producto_factura_id = r.producto_receta_id
                 AND ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)) <= 1 THEN 'exacto'
            WHEN f.producto_factura_id = r.producto_receta_id
                 AND ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)) BETWEEN 2 AND 7 THEN 'cercano'
            ELSE 'solo_paciente'
        END AS tipo_match,
        ROW_NUMBER() OVER (
            PARTITION BY r.receta_id
            ORDER BY
                CASE
                    WHEN f.factura_id IS NULL THEN 4
                    WHEN f.producto_factura_id = r.producto_receta_id
                         AND ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)) <= 1 THEN 1
                    WHEN f.producto_factura_id = r.producto_receta_id
                         AND ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)) BETWEEN 2 AND 7 THEN 2
                    ELSE 3
                END,
                ABS(DATEDIFF(f.fecha_factura, r.fecha_receta)),
                f.factura_id,
                f.detalle_factura_id
        ) AS rn
    FROM recetas_base r
    LEFT JOIN facturas_base f
        ON f.paciente_id_match = r.paciente_id_match
       AND f.fecha_factura BETWEEN DATE_SUB(r.fecha_receta, INTERVAL 7 DAY)
                               AND DATE_ADD(r.fecha_receta, INTERVAL 7 DAY)
)
SELECT
    receta_id,
    pedido_id,
    cedula_paciente,
    paciente,
    fecha_receta,
    producto_receta_id,
    codigo_producto_receta,
    producto_receta,
    factura_id,
    detalle_factura_id,
    fecha_factura,
    fecha_facturacion,
    departamento_factura,
    cedula_cliente_factura,
    producto_factura_id,
    codigo_producto_factura,
    producto_factura,
    cantidad_facturada,
    precio_unitario_facturado,
    descuento_total_linea,
    descuento_bos_linea,
    monto_linea_neto,
    monto_linea_unitario_neto,
    diff_dias,
    tipo_match,
    total_farmacia
FROM candidatos
WHERE rn = 1
ORDER BY fecha_receta, paciente, receta_id
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
            [':from_date', ':to_date'],
            [
                "'" . addslashes($fromDate) . "'",
                "'" . addslashes($toDate) . "'",
            ],
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
            $this->lastError = 'Consulta remota de conciliación de recetas por SSH falló.';
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r?\n/', trim((string) $output)) as $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);
            $rows[] = [
                'receta_id' => trim((string) ($parts[0] ?? '')),
                'pedido_id' => trim((string) ($parts[1] ?? '')),
                'cedula_paciente' => trim((string) ($parts[2] ?? '')),
                'paciente' => trim((string) ($parts[3] ?? '')),
                'fecha_receta' => trim((string) ($parts[4] ?? '')),
                'producto_receta_id' => trim((string) ($parts[5] ?? '')),
                'codigo_producto_receta' => trim((string) ($parts[6] ?? '')),
                'producto_receta' => trim((string) ($parts[7] ?? '')),
                'factura_id' => trim((string) ($parts[8] ?? '')),
                'detalle_factura_id' => trim((string) ($parts[9] ?? '')),
                'fecha_factura' => trim((string) ($parts[10] ?? '')),
                'fecha_facturacion' => trim((string) ($parts[11] ?? '')),
                'departamento_factura' => trim((string) ($parts[12] ?? '')),
                'cedula_cliente_factura' => trim((string) ($parts[13] ?? '')),
                'producto_factura_id' => trim((string) ($parts[14] ?? '')),
                'codigo_producto_factura' => trim((string) ($parts[15] ?? '')),
                'producto_factura' => trim((string) ($parts[16] ?? '')),
                'cantidad_facturada' => trim((string) ($parts[17] ?? '')),
                'precio_unitario_facturado' => trim((string) ($parts[18] ?? '')),
                'descuento_total_linea' => trim((string) ($parts[19] ?? '')),
                'descuento_bos_linea' => trim((string) ($parts[20] ?? '')),
                'monto_linea_neto' => trim((string) ($parts[21] ?? '')),
                'monto_linea_unitario_neto' => trim((string) ($parts[22] ?? '')),
                'diff_dias' => trim((string) ($parts[23] ?? '')),
                'tipo_match' => trim((string) ($parts[24] ?? '')),
                'total_farmacia' => trim((string) ($parts[25] ?? '')),
            ];
        }

        $this->lastError = null;
        return $rows;
    }

    private function upsertRow(array $row, DateTimeImmutable $fromDate, DateTimeImmutable $toDate): void
    {
        $recetaId = trim((string) ($row['receta_id'] ?? ''));
        if ($recetaId === '') {
            return;
        }

        DB::table('farmacia_recetas_conciliacion')->updateOrInsert(
            ['receta_id' => $recetaId],
            [
                'pedido_id' => $this->nullableTrim($row['pedido_id'] ?? null, 50),
                'cedula_paciente' => $this->nullableTrim($row['cedula_paciente'] ?? null, 20),
                'paciente' => $this->nullableTrim($row['paciente'] ?? null, 255),
                'fecha_receta' => $this->normalizeDate($row['fecha_receta'] ?? null),
                'producto_receta_id' => $this->nullableTrim($row['producto_receta_id'] ?? null, 50),
                'codigo_producto_receta' => $this->nullableTrim($row['codigo_producto_receta'] ?? null, 100),
                'producto_receta' => $this->nullableTrim($row['producto_receta'] ?? null, 255),
                'factura_id' => $this->nullableTrim($row['factura_id'] ?? null, 50),
                'detalle_factura_id' => $this->nullableTrim($row['detalle_factura_id'] ?? null, 50),
                'fecha_factura' => $this->normalizeDate($row['fecha_factura'] ?? null),
                'fecha_facturacion' => $this->normalizeDateTime($row['fecha_facturacion'] ?? null),
                'departamento_factura' => $this->nullableTrim($row['departamento_factura'] ?? null, 255),
                'cedula_cliente_factura' => $this->nullableTrim($row['cedula_cliente_factura'] ?? null, 20),
                'producto_factura_id' => $this->nullableTrim($row['producto_factura_id'] ?? null, 50),
                'codigo_producto_factura' => $this->nullableTrim($row['codigo_producto_factura'] ?? null, 100),
                'producto_factura' => $this->nullableTrim($row['producto_factura'] ?? null, 255),
                'cantidad_facturada' => $this->parseAmount($row['cantidad_facturada'] ?? null),
                'precio_unitario_facturado' => $this->parseAmount($row['precio_unitario_facturado'] ?? null),
                'descuento_total_linea' => $this->parseAmount($row['descuento_total_linea'] ?? null),
                'descuento_bos_linea' => $this->parseAmount($row['descuento_bos_linea'] ?? null),
                'monto_linea_neto' => $this->parseAmount($row['monto_linea_neto'] ?? null),
                'monto_linea_unitario_neto' => $this->parseAmount($row['monto_linea_unitario_neto'] ?? null),
                'diff_dias' => $this->parseInt($row['diff_dias'] ?? null),
                'tipo_match' => $this->nullableTrim($row['tipo_match'] ?? null, 30) ?? 'sin_match',
                'total_farmacia' => $this->parseAmount($row['total_farmacia'] ?? null),
                'source_from' => $fromDate->format('Y-m-d'),
                'source_to' => $toDate->format('Y-m-d'),
                'raw_payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]
        );
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (!$date || $date->format('Y-m-d') !== trim($value)) {
            throw new \InvalidArgumentException(sprintf('Fecha inválida: %s', $value));
        }

        return $date;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
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

    private function parseInt(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' && is_numeric($value) ? (int) $value : null;
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

        if ($value === null || $value === '') {
            $value = env($key);
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
