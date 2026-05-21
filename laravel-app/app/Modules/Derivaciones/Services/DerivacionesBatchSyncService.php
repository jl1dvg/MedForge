<?php

namespace App\Modules\Derivaciones\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Handles batch scraping of missing derivaciones from billing_main.
 * Ported from legacy modules/Derivaciones/Services/DerivacionesSyncService.php
 * (only the scrapeMissingDerivationsBatch flow and its dependencies).
 */
class DerivacionesBatchSyncService
{
    /** @var array<string, array<string, bool>> */
    private array $tableColumnCache = [];

    public function __construct(
        private readonly PDO $db,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @return array{status:string,message:string,details?:array<string,int>}
     */
    public function scrapeMissingDerivationsBatch(int $batchSize = 200, int $maxAttempts = 3, int $cooldownHours = 6): array
    {
        $cursor = $this->getLastCursor('iess-derivaciones-scrape-missing');
        $this->startSyncRun('iess-derivaciones-scrape-missing', $cursor);

        $candidates = $this->fetchPendingBilledForms($batchSize, $maxAttempts, $cooldownHours);

        if (empty($candidates)) {
            $this->finishSyncRun('iess-derivaciones-scrape-missing', 'skipped', 0, $cursor, 'No hay formularios pendientes de scraping.');

            return [
                'status' => 'skipped',
                'message' => 'No hay formularios pendientes de scraping.',
            ];
        }

        $queueMeta = [];
        foreach ($candidates as $candidate) {
            $formId = (string) ($candidate['form_id'] ?? '');
            $hcNumber = $this->normalizeNullable($candidate['hc_number'] ?? null);
            $queueId = $this->upsertScrapeQueueEntry($formId, $hcNumber);
            $queueMeta[$formId] = [
                'queue_id' => $queueId,
                'attempts' => (int) ($candidate['attempts'] ?? 0),
                'hc_number' => $hcNumber,
            ];
        }

        $scriptPayload = array_values(array_map(
            fn (array $row): array => [
                'form_id' => (string) ($row['form_id'] ?? ''),
                'hc_number' => $this->normalizeNullable($row['hc_number'] ?? null),
            ],
            $candidates
        ));

        $results = $this->runBatchScraper($scriptPayload);

        $codesByFormId = [];
        $failedForms = [];
        $resultsByFormId = [];
        foreach ($results as $result) {
            $formId = (string) ($result['form_id'] ?? '');
            if ($formId !== '') {
                $resultsByFormId[$formId] = $result;
            }
            $ok = (bool) ($result['ok'] ?? false);
            $codigo = trim((string) ($result['cod_derivacion'] ?? ''));
            $error = $result['error'] ?? null;

            if (!$ok || $codigo === '') {
                $failedForms[$formId] = $error ?: 'No se obtuvo código de derivación.';
                continue;
            }

            $codesByFormId[$formId] = $codigo;
        }

        $upsertRows = [];
        $processed = 0;
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $lastId = $cursor;

        foreach ($candidates as $candidate) {
            $formId = (string) ($candidate['form_id'] ?? '');
            $hcNumber = $this->normalizeNullable($candidate['hc_number'] ?? null);
            $billingId = (int) ($candidate['billing_id'] ?? 0);

            $codigo = $codesByFormId[$formId] ?? '';
            $resultRow = $resultsByFormId[$formId] ?? [];

            if ($codigo !== '') {
                $upsertRows[] = [
                    'cod_derivacion' => $codigo,
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'fecha_registro' => $this->normalizeNullable($resultRow['fecha_registro'] ?? null),
                    'fecha_vigencia' => $this->normalizeNullable($resultRow['fecha_vigencia'] ?? null),
                    'referido' => $this->normalizeNullable($resultRow['referido'] ?? null),
                    'diagnostico' => $this->normalizeNullable($resultRow['diagnostico'] ?? null),
                    'sede' => $this->normalizeNullable($resultRow['sede'] ?? null),
                    'parentesco' => $this->normalizeNullable($resultRow['parentesco'] ?? null),
                ];
                $success++;
                $this->markScrapeAttempt($queueMeta[$formId]['queue_id'], 'success', null, $queueMeta[$formId]['attempts'], $maxAttempts);
            } else {
                $error = $failedForms[$formId] ?? 'No se obtuvo código para el form.';
                $status = array_key_exists($formId, $failedForms) ? 'error' : 'not_found';
                $this->markScrapeAttempt($queueMeta[$formId]['queue_id'], $status, $error, $queueMeta[$formId]['attempts'], $maxAttempts);
                if ($status === 'error') {
                    $failed++;
                } else {
                    $skipped++;
                }
            }

            $processed++;
            $lastId = $billingId > 0 ? $billingId : $lastId;
        }

        if ($upsertRows !== []) {
            $this->bulkUpsertLegacyDerivations($upsertRows);
        }

        $details = [
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
        ];

        $this->finishSyncRun('iess-derivaciones-scrape-missing', 'success', $processed, $lastId, 'Scraping batch de derivaciones completado.');

        return [
            'status' => 'success',
            'message' => sprintf('Scraping batch completado. Exitosos: %d, fallidos: %d, omitidos: %d', $success, $failed, $skipped),
            'details' => $details,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingBilledForms(int $limit, int $maxAttempts, int $cooldownHours): array
    {
        $sql = 'SELECT bm.id AS billing_id, bm.form_id, bm.hc_number, q.id AS queue_id, q.attempts, q.status, q.last_attempt_at
                FROM billing_main bm
                LEFT JOIN derivaciones_form_id dfi ON dfi.form_id = bm.form_id
                LEFT JOIN derivaciones_scrape_queue q ON q.form_id = bm.form_id
                WHERE bm.form_id IS NOT NULL AND bm.form_id <> ""
                  AND bm.hc_number IS NOT NULL AND bm.hc_number <> ""
                  AND (dfi.cod_derivacion IS NULL OR dfi.cod_derivacion = "")
                  AND (q.attempts IS NULL OR q.attempts < :maxAttempts)
                  AND (q.last_attempt_at IS NULL OR q.last_attempt_at <= DATE_SUB(NOW(), INTERVAL :cooldown HOUR))
                ORDER BY bm.id ASC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':maxAttempts', $maxAttempts, PDO::PARAM_INT);
        $stmt->bindValue(':cooldown', $cooldownHours, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function upsertScrapeQueueEntry(string $formId, ?string $hcNumber): int
    {
        $queueHasUpdatedAt = $this->tableHasColumn('derivaciones_scrape_queue', 'updated_at');

        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_scrape_queue (form_id, hc_number, status, attempts, last_error, next_retry_at, last_attempt_at)
             VALUES (:form_id, :hc_number, "pending", 0, NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE
                hc_number = COALESCE(VALUES(hc_number), hc_number)' . ($queueHasUpdatedAt ? ',
                updated_at = CURRENT_TIMESTAMP' : '')
        );

        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare('SELECT id FROM derivaciones_scrape_queue WHERE form_id = :form_id LIMIT 1');
        $lookup->execute([':form_id' => $formId]);

        return (int) $lookup->fetchColumn();
    }

    /**
     * @param array<int, array{form_id:string,hc_number:?string}> $payload
     * @return array<int, array<string, mixed>>
     */
    private function runBatchScraper(array $payload): array
    {
        $scriptPath = $this->projectRoot . '/scrapping/scrape_derivaciones_batch.py';

        if (!is_file($scriptPath)) {
            return $this->buildBatchFailureResults($payload, 'No se encontró el script de scraping batch.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'derivaciones_batch_');
        if ($tempFile === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para el batch.');
        }

        file_put_contents($tempFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $python = is_file('/usr/bin/python3') ? '/usr/bin/python3' : 'python3';
        $command = sprintf(
            '%s %s %s --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($scriptPath),
            escapeshellarg($tempFile)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        @unlink($tempFile);

        $joined = trim(implode("\n", $output));
        if ($exitCode !== 0) {
            return $this->buildBatchFailureResults(
                $payload,
                $joined !== '' ? $joined : 'Fallo al ejecutar el scraper batch.'
            );
        }

        $decoded = json_decode($joined, true);
        if (!is_array($decoded)) {
            return $this->buildBatchFailureResults($payload, 'Respuesta inválida del scraper batch.');
        }

        return $decoded;
    }

    /**
     * @param array<int,array<string,mixed>> $payload
     * @return array<int,array<string,mixed>>
     */
    private function buildBatchFailureResults(array $payload, string $error): array
    {
        return array_values(array_map(
            static fn (array $row): array => [
                'form_id' => (string) ($row['form_id'] ?? ''),
                'hc_number' => $row['hc_number'] ?? null,
                'ok' => false,
                'error' => $error,
            ],
            $payload
        ));
    }

    /**
     * @param array<int, array{
     *   cod_derivacion:string,
     *   form_id:string,
     *   hc_number:?string,
     *   fecha_registro:?string,
     *   fecha_vigencia:?string,
     *   referido:?string,
     *   diagnostico:?string,
     *   sede:?string,
     *   parentesco:?string
     * }> $rows
     */
    private function bulkUpsertLegacyDerivations(array $rows, int $chunkSize = 200): void
    {
        $chunks = array_chunk($rows, $chunkSize);
        $legacyHasUpdatedAt = $this->tableHasColumn('derivaciones_form_id', 'updated_at');

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)';
                $values[] = $row['cod_derivacion'];
                $values[] = $row['form_id'];
                $values[] = $row['hc_number'];
                $values[] = $row['fecha_registro'] ?? null;
                $values[] = $row['fecha_vigencia'] ?? null;
                $values[] = $row['referido'] ?? null;
                $values[] = $row['diagnostico'] ?? null;
                $values[] = $row['sede'] ?? null;
                $values[] = $row['parentesco'] ?? null;
            }

            $sql = 'INSERT INTO derivaciones_form_id (
                        cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, referido, diagnostico, sede, parentesco, archivo_derivacion_path
                    ) VALUES ' . implode(', ', $placeholders) . '
                    ON DUPLICATE KEY UPDATE
                        cod_derivacion = VALUES(cod_derivacion),
                        hc_number = VALUES(hc_number),
                        fecha_registro = VALUES(fecha_registro),
                        fecha_vigencia = VALUES(fecha_vigencia),
                        referido = VALUES(referido),
                        diagnostico = VALUES(diagnostico),
                        sede = VALUES(sede),
                        parentesco = VALUES(parentesco)' . ($legacyHasUpdatedAt ? ',
                        updated_at = CURRENT_TIMESTAMP' : '');

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
        }
    }

    private function markScrapeAttempt(int $queueId, string $status, ?string $error, int $previousAttempts, int $maxAttempts): void
    {
        $attempts = $previousAttempts + 1;
        $nextRetry = null;
        $queueHasUpdatedAt = $this->tableHasColumn('derivaciones_scrape_queue', 'updated_at');

        if ($status !== 'success' && $attempts < $maxAttempts) {
            $nextRetry = $this->buildRetryTimestamp($status, $attempts);
        }

        $stmt = $this->db->prepare(
            'UPDATE derivaciones_scrape_queue
             SET status = :status,
                 attempts = :attempts,
                 last_error = :last_error,
                 next_retry_at = :next_retry,
                 last_attempt_at = NOW()' . ($queueHasUpdatedAt ? ',
                 updated_at = CURRENT_TIMESTAMP' : '') . '
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => $status,
            ':attempts' => $attempts,
            ':last_error' => $error,
            ':next_retry' => $nextRetry,
            ':id' => $queueId,
        ]);
    }

    private function buildRetryTimestamp(string $status, int $attempts): string
    {
        if ($status === 'not_found') {
            return date('Y-m-d H:i:s', strtotime('+6 hours'));
        }

        return date('Y-m-d H:i:s', strtotime(sprintf('+%d minutes', max(15, $attempts * 10))));
    }

    private function getLastCursor(string $jobName): ?int
    {
        $stmt = $this->db->prepare('SELECT last_cursor FROM derivaciones_sync_runs WHERE job_name = :job LIMIT 1');
        $stmt->execute([':job' => $jobName]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : null;
    }

    private function startSyncRun(string $jobName, ?int $lastCursor): void
    {
        $syncRunsHasUpdatedAt = $this->tableHasColumn('derivaciones_sync_runs', 'updated_at');

        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_sync_runs (job_name, started_at, finished_at, status, items_processed, last_cursor, message)
             VALUES (:job, NOW(), NULL, "running", 0, :last_cursor, "En ejecución")
             ON DUPLICATE KEY UPDATE
                started_at = VALUES(started_at),
                finished_at = NULL,
                status = "running",
                items_processed = 0,
                last_cursor = COALESCE(VALUES(last_cursor), last_cursor),
                message = "En ejecución"' . ($syncRunsHasUpdatedAt ? ',
                updated_at = CURRENT_TIMESTAMP' : '')
        );

        $stmt->execute([
            ':job' => $jobName,
            ':last_cursor' => $lastCursor,
        ]);
    }

    private function finishSyncRun(string $jobName, string $status, int $processed, ?int $lastCursor, string $message): void
    {
        $syncRunsHasUpdatedAt = $this->tableHasColumn('derivaciones_sync_runs', 'updated_at');

        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_sync_runs (job_name, started_at, finished_at, status, items_processed, last_cursor, message)
             VALUES (:job, NOW(), NOW(), :status, :items_processed, :last_cursor, :message)
             ON DUPLICATE KEY UPDATE
                finished_at = VALUES(finished_at),
                status = VALUES(status),
                items_processed = VALUES(items_processed),
                last_cursor = VALUES(last_cursor),
                message = VALUES(message)' . ($syncRunsHasUpdatedAt ? ',
                updated_at = CURRENT_TIMESTAMP' : '')
        );

        $stmt->execute([
            ':job' => $jobName,
            ':status' => $status,
            ':items_processed' => $processed,
            ':last_cursor' => $lastCursor,
            ':message' => $message,
        ]);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (isset($this->tableColumnCache[$table][$column])) {
            return $this->tableColumnCache[$table][$column];
        }

        $stmt = $this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $columns = $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

        $this->tableColumnCache[$table] = [];
        foreach ($columns as $name) {
            $this->tableColumnCache[$table][(string) $name] = true;
        }

        return isset($this->tableColumnCache[$table][$column]);
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
