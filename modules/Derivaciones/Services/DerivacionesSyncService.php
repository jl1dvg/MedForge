<?php

namespace Modules\Derivaciones\Services;

use PDO;
use PDOException;
use RuntimeException;

class DerivacionesSyncService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Inserta o actualiza la derivación y su relación con un form_id tanto en el esquema nuevo
     * como en la tabla legacy para mantener compatibilidad.
     *
     * @param array<string, mixed> $payload
     */
    public function upsertDerivation(array $payload, bool $syncLegacyTable = true): ?int
    {
        $referralCode = (string) ($payload['cod_derivacion'] ?? '');
        $formId = (string) ($payload['form_id'] ?? '');

        if ($referralCode === '' || $formId === '') {
            return null;
        }

        $hcNumber = $this->normalizeNullable($payload['hc_number'] ?? null);
        $fechaRegistro = $this->normalizeNullable($payload['fecha_registro'] ?? null);
        $fechaVigencia = $this->normalizeNullable($payload['fecha_vigencia'] ?? null);
        $fechaCreacion = $this->normalizeNullable($payload['fecha_creacion'] ?? null);
        $referido = $this->normalizeNullable($payload['referido'] ?? null);
        $diagnostico = $this->normalizeNullable($payload['diagnostico'] ?? null);
        $sede = $this->normalizeNullable($payload['sede'] ?? null);
        $parentesco = $this->normalizeNullable($payload['parentesco'] ?? null);
        $archivoPath = $this->normalizeNullable($payload['archivo_derivacion_path'] ?? null);
        $status = $this->normalizeNullable($payload['status'] ?? null);
        $afiliacionRaw = $this->normalizeNullable($payload['afiliacion'] ?? null);
        $payer = $this->detectPayer($payload['payer'] ?? null, $afiliacionRaw, $hcNumber);

        try {
            $this->db->beginTransaction();

            $referralId = $this->upsertReferral($referralCode, $fechaVigencia, $payload['issued_at'] ?? null, $payer);
            $formRowId = $this->upsertForm([
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'fecha_creacion' => $fechaCreacion,
                'fecha_registro' => $fechaRegistro,
                'fecha_vigencia' => $fechaVigencia,
                'referido' => $referido,
                'diagnostico' => $diagnostico,
                'sede' => $sede,
                'parentesco' => $parentesco,
                'archivo_derivacion_path' => $archivoPath,
                'payer' => $payer,
                'afiliacion_raw' => $afiliacionRaw,
            ]);

            $pivotId = $this->linkReferralForm($referralId, $formRowId, $status, $fechaRegistro);

            if ($syncLegacyTable) {
                $this->persistLegacyTable([
                    'cod_derivacion' => $referralCode,
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'fecha_registro' => $fechaRegistro,
                    'fecha_vigencia' => $fechaVigencia,
                    'referido' => $referido,
                    'diagnostico' => $diagnostico,
                    'sede' => $sede,
                    'parentesco' => $parentesco,
                    'archivo_derivacion_path' => $archivoPath,
                ]);
            }

            $this->db->commit();

            return $pivotId;
        } catch (PDOException $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function syncFromLegacyDerivaciones(int $batchSize = 200): array
    {
        $cursor = $this->getLastCursor('iess-referrals-sync');
        $this->startSyncRun('iess-referrals-sync', $cursor);

        $sql = 'SELECT * FROM derivaciones_form_id WHERE 1=1';
        $params = [];

        if ($cursor !== null) {
            $sql .= ' AND id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql .= ' ORDER BY id ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        if (array_key_exists(':cursor', $params)) {
            $stmt->bindValue(':cursor', (int) $params[':cursor'], PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            $this->finishSyncRun('iess-referrals-sync', 'skipped', 0, $cursor, 'No hay derivaciones nuevas para sincronizar.');
            return [
                'status' => 'skipped',
                'message' => 'No hay derivaciones nuevas para sincronizar.',
            ];
        }

        $processed = 0;
        $lastId = $cursor;

        foreach ($rows as $row) {
            $this->upsertDerivation([
                'cod_derivacion' => $row['cod_derivacion'] ?? null,
                'form_id' => $row['form_id'] ?? null,
                'hc_number' => $row['hc_number'] ?? null,
                'fecha_creacion' => $row['fecha_creacion'] ?? null,
                'fecha_registro' => $row['fecha_registro'] ?? null,
                'fecha_vigencia' => $row['fecha_vigencia'] ?? null,
                'referido' => $row['referido'] ?? null,
                'diagnostico' => $row['diagnostico'] ?? null,
                'sede' => $row['sede'] ?? null,
                'parentesco' => $row['parentesco'] ?? null,
                'archivo_derivacion_path' => $row['archivo_derivacion_path'] ?? null,
            ], false);

            $processed++;
            $lastId = (int) ($row['id'] ?? $lastId);
        }

        $this->finishSyncRun('iess-referrals-sync', 'success', $processed, $lastId, 'Derivaciones sincronizadas desde tabla legacy.');

        return [
            'status' => 'success',
            'message' => sprintf('Se sincronizaron %d derivaciones.', $processed),
            'details' => [
                'processed' => $processed,
                'last_cursor' => $lastId,
            ],
        ];
    }

    public function syncInvoicesFromBilling(int $batchSize = 200): array
    {
        $cursor = $this->getLastCursor('iess-billing-sync');
        $this->startSyncRun('iess-billing-sync', $cursor);

        $sql = 'SELECT bm.id, bm.form_id, bm.hc_number, d.cod_derivacion
                FROM billing_main bm
                LEFT JOIN derivaciones_form_id d ON d.form_id = bm.form_id
                WHERE 1=1';
        $params = [];

        if ($cursor !== null) {
            $sql .= ' AND bm.id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql .= ' ORDER BY bm.id ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        if (array_key_exists(':cursor', $params)) {
            $stmt->bindValue(':cursor', (int) $params[':cursor'], PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            $this->finishSyncRun('iess-billing-sync', 'skipped', 0, $cursor, 'No hay facturas nuevas para sincronizar.');
            return [
                'status' => 'skipped',
                'message' => 'No hay facturas nuevas para sincronizar.',
            ];
        }

        $processed = 0;
        $lastId = $cursor;

        foreach ($rows as $row) {
            $formId = (string) ($row['form_id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $hcNumber = $this->normalizeNullable($row['hc_number'] ?? null);
            $referralCode = $this->normalizeNullable($row['cod_derivacion'] ?? null);
            $afiliacionRaw = $this->fetchPatientAffiliation($hcNumber);
            $payer = $this->detectPayer(null, $afiliacionRaw, null);

            $referralId = null;
            if (!empty($referralCode)) {
                $referralId = $this->upsertReferral($referralCode, null, null, $payer);
            }

            $formRowId = $this->upsertForm([
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'payer' => $payer,
                'afiliacion_raw' => $afiliacionRaw,
            ]);

            $invoiceNumber = sprintf('BILL-%d', (int) ($row['id'] ?? 0));

            $this->persistInvoice([
                'invoice_number' => $invoiceNumber,
                'referral_id' => $referralId,
                'form_row_id' => $formRowId,
                'hc_number' => $hcNumber,
                'source' => 'MEDFORGE',
            ]);

            $processed++;
            $lastId = (int) ($row['id'] ?? $lastId);
        }

        $this->finishSyncRun('iess-billing-sync', 'success', $processed, $lastId, 'Facturas sincronizadas desde billing_main.');

        return [
            'status' => 'success',
            'message' => sprintf('Se sincronizaron %d facturas vinculadas a derivaciones.', $processed),
            'details' => [
                'processed' => $processed,
                'last_cursor' => $lastId,
            ],
        ];
    }

    public function scrapeMissingDerivations(int $batchSize = 20, int $timeoutSeconds = 60, int $maxAttempts = 3): array
    {
        $cursor = $this->getLastCursor('iess-derivaciones-scrape-missing');
        $this->startSyncRun('iess-derivaciones-scrape-missing', $cursor);

        $candidates = $this->fetchFormsWithoutDerivation($batchSize, $maxAttempts);

        if (empty($candidates)) {
            $this->finishSyncRun('iess-derivaciones-scrape-missing', 'skipped', 0, $cursor, 'No hay formularios pendientes de scraping.');

            return [
                'status' => 'skipped',
                'message' => 'No hay formularios pendientes de scraping.',
            ];
        }

        $processed = 0;
        $lastId = $cursor;
        $success = 0;
        $notFound = 0;
        $failed = 0;

        foreach ($candidates as $candidate) {
            $formId = (string) ($candidate['form_id'] ?? '');
            $hcNumber = $this->normalizeNullable($candidate['hc_number'] ?? null);
            $billingId = (int) ($candidate['billing_id'] ?? 0);
            $queueId = $this->upsertScrapeQueueEntry($formId, $hcNumber);

            if ((int) ($candidate['attempts'] ?? 0) >= $maxAttempts) {
                continue;
            }

            try {
                $payload = $this->runScraperForForm($formId, $hcNumber, $timeoutSeconds);

                if ($payload === null || empty($payload['codigo_derivacion'])) {
                    $notFound++;
                    $this->markScrapeAttempt($queueId, 'not_found', 'El scraper no devolvió código de derivación.', $candidate['attempts'] ?? 0, $maxAttempts);
                    continue;
                }

                $this->upsertDerivation([
                    'cod_derivacion' => $payload['codigo_derivacion'] ?? null,
                    'form_id' => $payload['form_id'] ?? $formId,
                    'hc_number' => $payload['hc_number'] ?? $hcNumber,
                    'fecha_registro' => $payload['fecha_registro'] ?? null,
                    'fecha_vigencia' => $payload['fecha_vigencia'] ?? null,
                    'referido' => $payload['referido'] ?? null,
                    'diagnostico' => $payload['diagnostico'] ?? null,
                    'sede' => $payload['sede'] ?? null,
                    'parentesco' => $payload['parentesco'] ?? null,
                    'afiliacion' => $payload['afiliacion'] ?? null,
                ]);

                $this->markScrapeAttempt($queueId, 'success', null, $candidate['attempts'] ?? 0, $maxAttempts);
                $success++;
            } catch (RuntimeException|PDOException $exception) {
                $failed++;
                $this->markScrapeAttempt($queueId, 'error', $exception->getMessage(), $candidate['attempts'] ?? 0, $maxAttempts);
            }

            $processed++;
            $lastId = $billingId > 0 ? $billingId : $lastId;
        }

        $details = [
            'processed' => $processed,
            'success' => $success,
            'not_found' => $notFound,
            'failed' => $failed,
        ];

        $this->finishSyncRun('iess-derivaciones-scrape-missing', 'success', $processed, $lastId, 'Scraping de derivaciones completado.');

        return [
            'status' => 'success',
            'message' => sprintf('Scraping completado. Exitosos: %d, no encontrados: %d, fallidos: %d', $success, $notFound, $failed),
            'details' => $details,
        ];
    }

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
        foreach ($results as $result) {
            $formId = (string) ($result['form_id'] ?? '');
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

            if ($codigo !== '') {
                $upsertRows[] = [
                    'cod_derivacion' => $codigo,
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
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

    private function upsertReferral(string $referralCode, ?string $validUntil, ?string $issuedAt, ?string $source): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_referrals (referral_code, valid_until, issued_at, source, source_updated_at)
             VALUES (:referral_code, :valid_until, :issued_at, :source, NOW())
             ON DUPLICATE KEY UPDATE
                valid_until = COALESCE(VALUES(valid_until), valid_until),
                issued_at = COALESCE(VALUES(issued_at), issued_at),
                source = COALESCE(VALUES(source), source),
                source_updated_at = VALUES(source_updated_at),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':referral_code' => $referralCode,
            ':valid_until' => $validUntil,
            ':issued_at' => $issuedAt,
            ':source' => $source ?? 'IESS',
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare('SELECT id FROM derivaciones_referrals WHERE referral_code = :referral_code LIMIT 1');
        $lookup->execute([':referral_code' => $referralCode]);

        return (int) $lookup->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertForm(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_forms (
                iess_form_id, hc_number, fecha_creacion, fecha_registro, fecha_vigencia,
                referido, diagnostico, sede, parentesco, archivo_derivacion_path,
                payer, afiliacion_raw, source_updated_at
            ) VALUES (
                :form_id, :hc_number, :fecha_creacion, :fecha_registro, :fecha_vigencia,
                :referido, :diagnostico, :sede, :parentesco, :archivo_derivacion_path,
                :payer, :afiliacion_raw, NOW()
            )
            ON DUPLICATE KEY UPDATE
                hc_number = COALESCE(VALUES(hc_number), hc_number),
                fecha_creacion = COALESCE(VALUES(fecha_creacion), fecha_creacion),
                fecha_registro = COALESCE(VALUES(fecha_registro), fecha_registro),
                fecha_vigencia = COALESCE(VALUES(fecha_vigencia), fecha_vigencia),
                referido = COALESCE(VALUES(referido), referido),
                diagnostico = COALESCE(VALUES(diagnostico), diagnostico),
                sede = COALESCE(VALUES(sede), sede),
                parentesco = COALESCE(VALUES(parentesco), parentesco),
                archivo_derivacion_path = COALESCE(VALUES(archivo_derivacion_path), archivo_derivacion_path),
                payer = COALESCE(VALUES(payer), payer),
                afiliacion_raw = COALESCE(VALUES(afiliacion_raw), afiliacion_raw),
                source_updated_at = VALUES(source_updated_at),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':form_id' => $data['form_id'],
            ':hc_number' => $data['hc_number'] ?? null,
            ':fecha_creacion' => $data['fecha_creacion'] ?? null,
            ':fecha_registro' => $data['fecha_registro'] ?? null,
            ':fecha_vigencia' => $data['fecha_vigencia'] ?? null,
            ':referido' => $data['referido'] ?? null,
            ':diagnostico' => $data['diagnostico'] ?? null,
            ':sede' => $data['sede'] ?? null,
            ':parentesco' => $data['parentesco'] ?? null,
            ':archivo_derivacion_path' => $data['archivo_derivacion_path'] ?? null,
            ':payer' => $data['payer'] ?? null,
            ':afiliacion_raw' => $data['afiliacion_raw'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare('SELECT id FROM derivaciones_forms WHERE iess_form_id = :form_id LIMIT 1');
        $lookup->execute([':form_id' => $data['form_id']]);

        return (int) $lookup->fetchColumn();
    }

    private function linkReferralForm(int $referralId, int $formId, ?string $status, ?string $linkedAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_referral_forms (referral_id, form_id, status, linked_at)
             VALUES (:referral_id, :form_id, :status, :linked_at)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                linked_at = VALUES(linked_at),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':referral_id' => $referralId,
            ':form_id' => $formId,
            ':status' => $status,
            ':linked_at' => $linkedAt,
        ]);

        $id = (int) $this->db->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare('SELECT id FROM derivaciones_referral_forms WHERE referral_id = :referral_id AND form_id = :form_id LIMIT 1');
        $lookup->execute([
            ':referral_id' => $referralId,
            ':form_id' => $formId,
        ]);

        return (int) $lookup->fetchColumn();
    }

    /**
     * @param array{invoice_number:string,referral_id:?int,form_row_id:int,hc_number:?string,source?:string} $data
     */
    private function persistInvoice(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_invoices (invoice_number, referral_id, form_id, hc_number, status, source, source_updated_at)
             VALUES (:invoice_number, :referral_id, :form_id, :hc_number, :status, :source, NOW())
             ON DUPLICATE KEY UPDATE
                referral_id = VALUES(referral_id),
                form_id = VALUES(form_id),
                hc_number = VALUES(hc_number),
                status = VALUES(status),
                source = VALUES(source),
                source_updated_at = VALUES(source_updated_at),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':invoice_number' => $data['invoice_number'],
            ':referral_id' => $data['referral_id'],
            ':form_id' => $data['form_row_id'],
            ':hc_number' => $data['hc_number'],
            ':status' => 'pending',
            ':source' => $data['source'] ?? 'IESS',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistLegacyTable(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_form_id (
                cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, referido, diagnostico, sede, parentesco, archivo_derivacion_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                fecha_registro = VALUES(fecha_registro),
                fecha_vigencia = VALUES(fecha_vigencia),
                cod_derivacion = VALUES(cod_derivacion),
                referido = VALUES(referido),
                diagnostico = VALUES(diagnostico),
                sede = VALUES(sede),
                parentesco = VALUES(parentesco),
                archivo_derivacion_path = VALUES(archivo_derivacion_path)'
        );

        $stmt->execute([
            $data['cod_derivacion'],
            $data['form_id'],
            $data['hc_number'],
            $data['fecha_registro'],
            $data['fecha_vigencia'],
            $data['referido'],
            $data['diagnostico'],
            $data['sede'],
            $data['parentesco'],
            $data['archivo_derivacion_path'],
        ]);
    }

    /**
     * @param array<int, array{cod_derivacion:string,form_id:string,hc_number:?string}> $rows
     */
    private function bulkUpsertLegacyDerivations(array $rows, int $chunkSize = 200): void
    {
        $chunks = array_chunk($rows, $chunkSize);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL)';
                $values[] = $row['cod_derivacion'];
                $values[] = $row['form_id'];
                $values[] = $row['hc_number'];
            }

            $sql = 'INSERT INTO derivaciones_form_id (
                        cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, referido, diagnostico, sede, parentesco, archivo_derivacion_path
                    ) VALUES ' . implode(', ', $placeholders) . '
                    ON DUPLICATE KEY UPDATE
                        cod_derivacion = VALUES(cod_derivacion),
                        hc_number = VALUES(hc_number),
                        updated_at = CURRENT_TIMESTAMP';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
        }
    }

    /**
     * @param array<int, array{form_id:string,hc_number:?string}> $payload
     * @return array<int, array<string, mixed>>
     */
    private function runBatchScraper(array $payload): array
    {
        $scriptPath = BASE_PATH . '/scrapping/scrape_derivaciones_batch.py';

        if (!is_file($scriptPath)) {
            throw new RuntimeException('No se encontró el script de scraping batch.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'derivaciones_batch_');
        if ($tempFile === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para el batch.');
        }

        file_put_contents($tempFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $command = sprintf(
            '/usr/bin/python3 %s %s --quiet 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($tempFile)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        @unlink($tempFile);

        $joined = trim(implode("\n", $output));
        if ($exitCode !== 0) {
            throw new RuntimeException($joined !== '' ? $joined : 'Fallo al ejecutar el scraper batch.');
        }

        $decoded = json_decode($joined, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta inválida del scraper batch.');
        }

        return $decoded;
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
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_sync_runs (job_name, started_at, finished_at, status, items_processed, last_cursor, message)
             VALUES (:job, NOW(), NULL, "running", 0, :last_cursor, "En ejecución")
             ON DUPLICATE KEY UPDATE
                started_at = VALUES(started_at),
                finished_at = NULL,
                status = "running",
                items_processed = 0,
                last_cursor = COALESCE(VALUES(last_cursor), last_cursor),
                message = "En ejecución",
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':job' => $jobName,
            ':last_cursor' => $lastCursor,
        ]);
    }

    private function finishSyncRun(string $jobName, string $status, int $processed, ?int $lastCursor, string $message): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_sync_runs (job_name, started_at, finished_at, status, items_processed, last_cursor, message)
             VALUES (:job, NOW(), NOW(), :status, :items_processed, :last_cursor, :message)
             ON DUPLICATE KEY UPDATE
                finished_at = VALUES(finished_at),
                status = VALUES(status),
                items_processed = VALUES(items_processed),
                last_cursor = VALUES(last_cursor),
                message = VALUES(message),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':job' => $jobName,
            ':status' => $status,
            ':items_processed' => $processed,
            ':last_cursor' => $lastCursor,
            ':message' => $message,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFormsWithoutDerivation(int $limit, int $maxAttempts): array
    {
        $sql = 'SELECT bm.id AS billing_id, bm.form_id, bm.hc_number, q.id AS queue_id, q.attempts, q.status
                FROM billing_main bm
                LEFT JOIN derivaciones_forms df ON df.iess_form_id = bm.form_id
                LEFT JOIN derivaciones_referral_forms drf ON drf.form_id = df.id
                LEFT JOIN derivaciones_form_id legacy ON legacy.form_id = bm.form_id
                LEFT JOIN derivaciones_scrape_queue q ON q.form_id = bm.form_id
                WHERE bm.form_id IS NOT NULL AND bm.form_id <> ""
                  AND bm.hc_number IS NOT NULL AND bm.hc_number <> ""
                  AND legacy.id IS NULL
                  AND drf.id IS NULL
                  AND (q.id IS NULL OR q.status <> "success")
                  AND (q.attempts IS NULL OR q.attempts < :maxAttempts)
                  AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
                ORDER BY bm.id ASC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':maxAttempts', $maxAttempts, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $stmt = $this->db->prepare(
            'INSERT INTO derivaciones_scrape_queue (form_id, hc_number, status, attempts, last_error, next_retry_at, last_attempt_at)
             VALUES (:form_id, :hc_number, "pending", 0, NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE
                hc_number = COALESCE(VALUES(hc_number), hc_number),
                updated_at = CURRENT_TIMESTAMP'
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

    private function markScrapeAttempt(int $queueId, string $status, ?string $error, int $previousAttempts, int $maxAttempts): void
    {
        $attempts = $previousAttempts + 1;
        $nextRetry = null;

        if ($status !== 'success' && $attempts < $maxAttempts) {
            $nextRetry = $this->buildRetryTimestamp($status, $attempts);
        }

        $stmt = $this->db->prepare(
            'UPDATE derivaciones_scrape_queue
             SET status = :status,
                 attempts = :attempts,
                 last_error = :last_error,
                 next_retry_at = :next_retry,
                 last_attempt_at = NOW(),
                 updated_at = CURRENT_TIMESTAMP
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

    private function buildRetryTimestamp(string $status, int $attempts): ?string
    {
        if ($status === 'not_found') {
            return date('Y-m-d H:i:s', strtotime('+6 hours'));
        }

        return date('Y-m-d H:i:s', strtotime(sprintf('+%d minutes', max(15, $attempts * 10))));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runScraperForForm(string $formId, ?string $hcNumber, int $timeoutSeconds): ?array
    {
        $scriptPath = BASE_PATH . '/scrapping/scrape_log_admision.py';

        if (!is_file($scriptPath)) {
            throw new RuntimeException('No se encontró el script de scraping.');
        }

        $hc = $hcNumber ?? '';
        $command = sprintf(
            'timeout %d /usr/bin/python3 %s %s %s --quiet 2>&1',
            $timeoutSeconds,
            escapeshellarg($scriptPath),
            escapeshellarg($formId),
            escapeshellarg($hc)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $joined = trim(implode("\n", $output));

        if ($exitCode === 124) {
            throw new RuntimeException('Timeout al ejecutar el scraper.');
        }

        if ($exitCode !== 0) {
            throw new RuntimeException($joined !== '' ? $joined : 'Fallo al ejecutar el scraper.');
        }

        $decoded = null;
        foreach ($output as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                break;
            }
        }

        if (!is_array($decoded)) {
            $decoded = json_decode($joined, true);
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $this->extractDerivationPayload($decoded, $formId, $hcNumber);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function extractDerivationPayload(array $decoded, string $formId, ?string $hcNumber): array
    {
        return [
            'codigo_derivacion' => $decoded['codigo_derivacion'] ?? $decoded['cod_derivacion'] ?? null,
            'form_id' => $decoded['form_id'] ?? $formId,
            'hc_number' => $decoded['hc_number'] ?? $decoded['identificacion'] ?? $hcNumber,
            'fecha_registro' => $decoded['fecha_registro'] ?? null,
            'fecha_vigencia' => $decoded['fecha_vigencia'] ?? null,
            'referido' => $decoded['referido'] ?? null,
            'diagnostico' => $decoded['diagnostico'] ?? null,
            'sede' => $decoded['sede'] ?? null,
            'parentesco' => $decoded['parentesco'] ?? null,
            'afiliacion' => $decoded['afiliacion'] ?? null,
        ];
    }

    private function detectPayer(?string $explicitPayer, ?string $afiliacionRaw, ?string $hcNumberForLookup): string
    {
        $payer = $this->normalizeNullable($explicitPayer);
        if ($payer !== null) {
            return strtoupper($payer);
        }

        $normalizedAffiliation = $this->normalizeAffiliation($afiliacionRaw);

        if ($normalizedAffiliation === null && $hcNumberForLookup !== null) {
            $normalizedAffiliation = $this->normalizeAffiliation($this->fetchPatientAffiliation($hcNumberForLookup));
        }

        if ($normalizedAffiliation === null) {
            return 'OTROS';
        }

        return $this->mapAffiliationToPayer($normalizedAffiliation);
    }

    private function normalizeAffiliation(?string $affiliation): ?string
    {
        if ($affiliation === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($affiliation)));

        return $normalized === '' ? null : $normalized;
    }

    private function mapAffiliationToPayer(string $normalizedAffiliation): string
    {
        $iessAffiliations = [
            'contribuyente voluntario',
            'conyuge',
            'conyuge pensionista',
            'seguro campesino',
            'seguro campesino jubilado',
            'seguro general',
            'seguro general jubilado',
            'seguro general por montepio',
            'seguro general tiempo parcial',
            'iess',
            'hijos dependientes',
        ];

        if (in_array($normalizedAffiliation, $iessAffiliations, true)) {
            return 'IESS';
        }

        if ($normalizedAffiliation === 'issfa') {
            return 'ISSFA';
        }

        if ($normalizedAffiliation === 'isspol') {
            return 'ISSPOL';
        }

        if ($normalizedAffiliation === 'msp') {
            return 'MSP';
        }

        return 'OTROS';
    }

    private function fetchPatientAffiliation(?string $hcNumber): ?string
    {
        if ($hcNumber === null) {
            return null;
        }

        try {
            $stmt = $this->db->prepare('SELECT afiliacion FROM patient_data WHERE hc_number = :hc LIMIT 1');
            $stmt->execute([':hc' => $hcNumber]);

            $value = $stmt->fetchColumn();

            return $value !== false ? (string) $value : null;
        } catch (PDOException) {
            return null;
        }
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
