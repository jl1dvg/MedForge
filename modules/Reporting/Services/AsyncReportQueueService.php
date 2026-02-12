<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Controllers\SolicitudController;
use Helpers\PdfGenerator;
use Models\ProtocoloModel;
use Modules\Reporting\Controllers\ReportController;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use PDO;
use RuntimeException;
use Throwable;

class AsyncReportQueueService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';

    public function __construct(private PDO $pdo)
    {
    }

    public function enqueueCobertura(string $formId, string $hcNumber, string $variant = 'template', int $maxAttempts = 3): int
    {
        $this->ensureTable();

        $payload = [
            'type' => 'cobertura_pdf',
            'form_id' => trim($formId),
            'hc_number' => trim($hcNumber),
            'variant' => trim($variant) !== '' ? trim($variant) : 'template',
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO medforge_report_queue (report_type, payload_json, status, max_attempts, available_at, created_at, updated_at)
             VALUES (:report_type, :payload_json, :status, :max_attempts, NOW(), NOW(), NOW())'
        );

        $stmt->execute([
            ':report_type' => 'cobertura_pdf',
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => self::STATUS_PENDING,
            ':max_attempts' => max(1, $maxAttempts),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(int $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM medforge_report_queue WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $row['payload'] = $this->decodePayload($row['payload_json'] ?? null);
        unset($row['payload_json']);

        return $row;
    }

    /**
     * @return array{processed:int,completed:int,failed:int,skipped:int,jobs:array<int,array<string,mixed>>}
     */
    public function processPending(int $limit = 3): array
    {
        $this->ensureTable();

        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM medforge_report_queue
             WHERE status = :status
               AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT ' . $limit
        );
        $stmt->execute([':status' => self::STATUS_PENDING]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [
                'processed' => 0,
                'completed' => 0,
                'failed' => 0,
                'skipped' => 1,
                'jobs' => [],
            ];
        }

        $result = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'jobs' => [],
        ];

        foreach ($rows as $row) {
            $jobId = (int) ($row['id'] ?? 0);
            if ($jobId <= 0 || !$this->lockJob($jobId)) {
                $result['skipped']++;
                continue;
            }

            $result['processed']++;
            try {
                $outputPath = $this->processCoberturaJob($row);
                $this->markCompleted($jobId, $outputPath);
                $result['completed']++;
                $result['jobs'][] = [
                    'id' => $jobId,
                    'status' => self::STATUS_COMPLETED,
                    'file_path' => $outputPath,
                ];
            } catch (Throwable $exception) {
                $failedPermanently = $this->markFailedOrRetry($row, $exception->getMessage());
                if ($failedPermanently) {
                    $result['failed']++;
                }

                $result['jobs'][] = [
                    'id' => $jobId,
                    'status' => $failedPermanently ? self::STATUS_FAILED : self::STATUS_PENDING,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function ensureTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `medforge_report_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_type` VARCHAR(80) NOT NULL,
  `payload_json` JSON NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `available_at` DATETIME NOT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_queue_status_available` (`status`, `available_at`),
  KEY `idx_report_queue_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $this->pdo->exec($sql);
    }

    private function lockJob(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_report_queue
             SET status = :processing,
                 attempts = attempts + 1,
                 started_at = NOW(),
                 updated_at = NOW(),
                 error_message = NULL
             WHERE id = :id
               AND status = :pending'
        );

        $stmt->execute([
            ':processing' => self::STATUS_PROCESSING,
            ':pending' => self::STATUS_PENDING,
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function processCoberturaJob(array $row): string
    {
        $payload = $this->decodePayload($row['payload_json'] ?? null);
        $type = (string) ($payload['type'] ?? '');
        if ($type !== 'cobertura_pdf') {
            throw new RuntimeException('Tipo de job no soportado: ' . $type);
        }

        $formId = trim((string) ($payload['form_id'] ?? ''));
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $variant = trim((string) ($payload['variant'] ?? 'template'));

        if ($formId === '' || $hcNumber === '') {
            throw new RuntimeException('El job no contiene form_id o hc_number válidos.');
        }

        $reportController = new ReportController($this->pdo, new ReportService());
        $protocolService = new ProtocolReportService(
            $this->pdo,
            $reportController,
            new ProtocoloModel($this->pdo),
            new SolicitudController($this->pdo)
        );

        $documento = $protocolService->generateCoberturaDocument($formId, $hcNumber);

        $dir = BASE_PATH . '/storage/reports/async';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de salida para reportes asíncronos.');
        }

        $safeVariant = preg_replace('/[^a-z0-9_\-]/i', '_', $variant) ?: 'template';
        $filename = sprintf('cobertura_%s_%s_%d_%s.pdf', $formId, $hcNumber, (int) ($row['id'] ?? 0), $safeVariant);
        $filePath = $dir . '/' . $filename;

        if (($documento['mode'] ?? '') === 'report') {
            $options = isset($documento['options']) && is_array($documento['options']) ? $documento['options'] : [];
            $data = isset($documento['data']) && is_array($documento['data']) ? $documento['data'] : [];
            $slug = (string) ($documento['slug'] ?? '');

            if ($slug === '') {
                throw new RuntimeException('No se pudo resolver el slug del reporte de cobertura.');
            }

            if ($variant === 'appendix') {
                $appendix = $protocolService->generateCoberturaAppendixDocument($formId, $hcNumber, $data, null);
                if (!is_array($appendix)) {
                    throw new RuntimeException('No se pudo construir el apéndice de cobertura.');
                }

                PdfGenerator::generarDesdeHtml(
                    (string) ($appendix['html'] ?? ''),
                    $filename,
                    isset($appendix['css']) ? (string) $appendix['css'] : null,
                    'F',
                    (string) ($appendix['orientation'] ?? 'P'),
                    isset($appendix['mpdf']) && is_array($appendix['mpdf']) ? $appendix['mpdf'] : []
                );

                if (!is_file($filename) && !is_file($filePath)) {
                    // generarDesdeHtml con modo F escribe según nombre recibido relativo al cwd.
                    // Si cayó en cwd, mover a destino final.
                    if (is_file($filename)) {
                        @rename($filename, $filePath);
                    }
                }

                if (!is_file($filePath) && is_file($filename)) {
                    @rename($filename, $filePath);
                }
            } elseif ($variant === 'template') {
                $options['finalName'] = $filename;
                $options['modoSalida'] = 'F';
                $options['filePath'] = $filePath;

                PdfGenerator::generarReporte($slug, $data, $options);
            } else {
                $basePdf = (new ReportService())->renderDocument($slug, $data, [
                    'filename' => $filename,
                    'destination' => 'S',
                    'font_family' => $options['font_family'] ?? null,
                    'font_size' => $options['font_size'] ?? null,
                    'line_height' => $options['line_height'] ?? null,
                    'text_color' => $options['text_color'] ?? null,
                    'overrides' => $options['overrides'] ?? null,
                ])['content'];

                $append = isset($documento['append']) && is_array($documento['append']) ? $documento['append'] : null;
                if ($append !== null && trim((string) ($append['html'] ?? '')) !== '') {
                    $basePdf = $this->appendHtmlToPdf($basePdf, (string) $append['html'], [
                        'orientation' => (string) ($append['orientation'] ?? 'P'),
                        'css' => (string) ($append['css'] ?? ''),
                        'mpdf' => isset($append['mpdf']) && is_array($append['mpdf']) ? $append['mpdf'] : [],
                    ]);
                }

                file_put_contents($filePath, $basePdf);
            }
        } else {
            PdfGenerator::generarDesdeHtml(
                (string) ($documento['html'] ?? ''),
                $filename,
                isset($documento['css']) ? (string) $documento['css'] : null,
                'F',
                (string) ($documento['orientation'] ?? 'P'),
                isset($documento['mpdf']) && is_array($documento['mpdf']) ? $documento['mpdf'] : []
            );

            if (is_file($filename) && !is_file($filePath)) {
                @rename($filename, $filePath);
            }
        }

        if (!is_file($filePath)) {
            throw new RuntimeException('El PDF no fue generado en disco.');
        }

        return $filePath;
    }

    private function markCompleted(int $id, string $path): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_report_queue
             SET status = :status,
                 file_path = :file_path,
                 finished_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => self::STATUS_COMPLETED,
            ':file_path' => $path,
            ':id' => $id,
        ]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function markFailedOrRetry(array $row, string $error): bool
    {
        $id = (int) ($row['id'] ?? 0);
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $maxAttempts = (int) ($row['max_attempts'] ?? 3);

        if ($attempts >= $maxAttempts) {
            $stmt = $this->pdo->prepare(
                'UPDATE medforge_report_queue
                 SET status = :failed,
                     error_message = :error,
                     finished_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute([
                ':failed' => self::STATUS_FAILED,
                ':error' => mb_substr($error, 0, 4000),
                ':id' => $id,
            ]);

            return true;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE medforge_report_queue
             SET status = :pending,
                 error_message = :error,
                 available_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            ':pending' => self::STATUS_PENDING,
            ':error' => mb_substr($error, 0, 4000),
            ':id' => $id,
        ]);

        return false;
    }

    /**
     * @param mixed $json
     * @return array<string,mixed>
     */
    private function decodePayload(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $options
     */
    private function appendHtmlToPdf(string $basePdf, string $html, array $options): string
    {
        $orientation = strtoupper((string) ($options['orientation'] ?? 'P'));
        if ($orientation !== 'P' && $orientation !== 'L') {
            $orientation = 'P';
        }

        $defaultOptions = [
            'default_font_size' => 8,
            'default_font' => 'dejavusans',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'orientation' => $orientation,
            'shrink_tables_to_fit' => 1,
            'use_kwt' => true,
            'autoScriptToLang' => true,
            'keep_table_proportions' => true,
            'allow_url_fopen' => true,
            'curlAllowUnsafeSslRequests' => true,
        ];

        if (isset($options['mpdf']) && is_array($options['mpdf'])) {
            $defaultOptions = array_merge($defaultOptions, $options['mpdf']);
        }

        $mpdf = new Mpdf($defaultOptions);

        $tempFile = tempnam(sys_get_temp_dir(), 'cov');
        if ($tempFile === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal para combinar el PDF.');
        }

        file_put_contents($tempFile, $basePdf);

        try {
            $pageCount = $mpdf->SetSourceFile($tempFile);
            for ($page = 1; $page <= $pageCount; $page++) {
                $templateId = $mpdf->ImportPage($page);
                $size = $mpdf->GetTemplateSize($templateId);
                $pageOrientation = $size['orientation'] ?? ($size['width'] > $size['height'] ? 'L' : 'P');
                $mpdf->AddPage($pageOrientation, [$size['width'], $size['height']]);
                $mpdf->UseTemplate($templateId);
            }
        } finally {
            @unlink($tempFile);
        }

        $cssPath = isset($options['css']) && is_string($options['css']) ? trim($options['css']) : '';
        if ($cssPath !== '' && is_file($cssPath)) {
            $css = file_get_contents($cssPath);
            if ($css !== false) {
                $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
            }
        }

        PdfGenerator::writeHtmlInChunks($mpdf, $html, HTMLParserMode::HTML_BODY);

        return $mpdf->Output('', 'S');
    }
}
