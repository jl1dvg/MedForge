<?php

namespace Controllers;

require_once dirname(__DIR__) . '/modules/Reporting/Support/LegacyLoader.php';

use Controllers\ExamenesController;
use Helpers\PdfGenerator;
use Models\ProtocoloModel;
use Modules\Reporting\Controllers\ReportController as ReportingReportController;
use Modules\Reporting\Services\ProtocolReportService;
use Modules\Reporting\Services\ReportService;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use PDO;
use RuntimeException;

reporting_bootstrap_legacy();

class PdfController
{
    private PDO $db;
    private ProtocoloModel $protocoloModel;
    private ExamenesController $solicitudController; // ✅ nueva propiedad
    private ReportingReportController $reportController;
    private ProtocolReportService $protocolReportService;
    private ReportService $reportService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->solicitudController = new ExamenesController($this->db);
        $this->reportService = new ReportService();
        $this->reportController = new ReportingReportController($this->db, $this->reportService);
        $this->protocolReportService = new ProtocolReportService(
            $this->db,
            $this->reportController,
            $this->protocoloModel,
            $this->solicitudController
        );
    }

    public function generarProtocolo(string $form_id, string $hc_number, bool $soloDatos = false, string $modo = 'completo')
    {
        if ($soloDatos) {
            return $this->protocolReportService->buildProtocolData($form_id, $hc_number);
        }

        if ($modo === 'separado') {
            $paginaSolicitada = $_GET['pagina'] ?? null;

            if ($paginaSolicitada) {
                $documento = $this->protocolReportService->renderProtocolPage($paginaSolicitada, $form_id, $hc_number);

                if ($documento === null) {
                    http_response_code(404);
                    echo 'Plantilla no encontrada';
                    return;
                }

                PdfGenerator::generarDesdeHtml(
                    $documento['html'],
                    $documento['filename'],
                    $documento['css'],
                    'D',
                    $documento['orientation']
                );
                return;
            }
        }

        $documento = $this->protocolReportService->generateProtocolDocument($form_id, $hc_number);

        PdfGenerator::generarDesdeHtml(
            $documento['html'],
            $documento['filename'],
            $documento['css']
        );
    }

    public function generateCobertura(string $form_id, string $hc_number, ?string $variantOverride = null)
    {
        $documento = $this->protocolReportService->generateCoberturaDocument($form_id, $hc_number);

        if (($documento['mode'] ?? null) === 'report') {
            $variant = $this->resolveCoberturaVariant($variantOverride);
            $options = isset($documento['options']) && is_array($documento['options'])
                ? $documento['options']
                : [];

            $options['finalName'] = $documento['filename'];
            $options['modoSalida'] = PdfGenerator::normalizarModoSalida($options['modoSalida'] ?? 'I');

            if ($variant === 'template') {
                PdfGenerator::generarReporte(
                    (string) $documento['slug'],
                    isset($documento['data']) && is_array($documento['data']) ? $documento['data'] : [],
                    $options
                );

                return;
            }

            $data = isset($documento['data']) && is_array($documento['data']) ? $documento['data'] : [];
            $appendixDocument = $this->resolveCoberturaAppendix(
                $variant,
                $documento,
                $form_id,
                $hc_number,
                $data
            );

            if ($variant === 'appendix') {
                if ($appendixDocument === null) {
                    PdfGenerator::generarReporte(
                        (string) $documento['slug'],
                        $data,
                        $options
                    );

                    return;
                }

                PdfGenerator::generarDesdeHtml(
                    $appendixDocument['html'],
                    $this->buildCoberturaAppendixFilename($appendixDocument['filename']),
                    $appendixDocument['css'],
                    $options['modoSalida'],
                    $appendixDocument['orientation'],
                    $appendixDocument['mpdf']
                );

                return;
            }

            if ($appendixDocument !== null) {
                $baseDocument = $this->reportService->renderDocument(
                    (string) $documento['slug'],
                    $data,
                    [
                        'filename' => $documento['filename'],
                        'destination' => 'S',
                        'font_family' => $options['font_family'] ?? null,
                        'font_size' => $options['font_size'] ?? null,
                        'line_height' => $options['line_height'] ?? null,
                        'text_color' => $options['text_color'] ?? null,
                        'overrides' => $options['overrides'] ?? null,
                    ]
                );

                if (($baseDocument['type'] ?? null) === 'template') {
                    $mergedPdf = $this->appendHtmlToPdf(
                        (string) $baseDocument['content'],
                        $appendixDocument['html'],
                        [
                            'css' => $appendixDocument['css'],
                            'orientation' => $appendixDocument['orientation'],
                            'mpdf' => $appendixDocument['mpdf'],
                        ]
                    );

                    $this->emitPdf(
                        $mergedPdf,
                        $documento['filename'],
                        $options['modoSalida'],
                        isset($options['filePath']) && is_string($options['filePath']) ? $options['filePath'] : null
                    );

                    return;
                }
            }

            PdfGenerator::generarReporte(
                (string) $documento['slug'],
                isset($documento['data']) && is_array($documento['data']) ? $documento['data'] : [],
                $options
            );

            return;
        }

        $orientation = isset($documento['orientation']) ? (string) $documento['orientation'] : 'P';
        $mpdfOptions = isset($documento['mpdf']) && is_array($documento['mpdf']) ? $documento['mpdf'] : [];

        if (!isset($mpdfOptions['orientation'])) {
            $mpdfOptions['orientation'] = $orientation;
        }

        PdfGenerator::generarDesdeHtml(
            $documento['html'],
            $documento['filename'],
            $documento['css'],
            'I',
            $orientation,
            $mpdfOptions
        );
    }

    /**
     * @param array<string, mixed> $options
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

        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        return $mpdf->Output('', 'S');
    }

    private function emitPdf(string $content, string $filename, string $mode, ?string $filePath = null): void
    {
        $mode = strtoupper(PdfGenerator::normalizarModoSalida($mode));

        if ($mode === 'F') {
            $target = $filePath ?? $filename;
            file_put_contents($target, $content);
            return;
        }

        if ($mode === 'S') {
            echo $content;
            return;
        }

        $disposition = $mode === 'D' ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $filename));
        header('Content-Length: ' . strlen($content));
        echo $content;
    }

    /**
     * @param array<string, mixed> $documento
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function resolveCoberturaAppendix(
        string $variant,
        array $documento,
        string $formId,
        string $hcNumber,
        array $data
    ): ?array {
        if ($variant === 'template') {
            return null;
        }

        $appendixSource = isset($documento['append']) && is_array($documento['append'])
            ? $documento['append']
            : null;

        if (!is_array($appendixSource)
            || !isset($appendixSource['html'])
            || !is_string($appendixSource['html'])
            || $appendixSource['html'] === ''
        ) {
            $appendixSource = $this->protocolReportService->generateCoberturaAppendixDocument(
                $formId,
                $hcNumber,
                $data
            );
        }

        if (!is_array($appendixSource)
            || !isset($appendixSource['html'])
            || !is_string($appendixSource['html'])
            || $appendixSource['html'] === ''
        ) {
            return null;
        }

        $orientation = strtoupper((string) ($appendixSource['orientation'] ?? 'P'));
        if ($orientation !== 'P' && $orientation !== 'L') {
            $orientation = 'P';
        }

        return [
            'html' => $appendixSource['html'],
            'css' => isset($appendixSource['css']) && is_string($appendixSource['css']) ? $appendixSource['css'] : null,
            'orientation' => $orientation,
            'mpdf' => isset($appendixSource['mpdf']) && is_array($appendixSource['mpdf']) ? $appendixSource['mpdf'] : [],
            'filename' => isset($appendixSource['filename']) && is_string($appendixSource['filename'])
                ? $appendixSource['filename']
                : $documento['filename'],
        ];
    }

    private function resolveCoberturaVariant(?string $override = null): string
    {
        if ($override !== null) {
            return $this->normalizeCoberturaVariant($override);
        }

        $raw = $_GET['variant'] ?? $_GET['document'] ?? $_GET['tipo'] ?? null;

        return $this->normalizeCoberturaVariant($raw);
    }

    /**
     * @param mixed $value
     */
    private function normalizeCoberturaVariant($value): string
    {
        $normalized = is_string($value) ? strtolower(trim($value)) : '';

        return match ($normalized) {
            'template', 'form', 'fijo', 'plantilla' => 'template',
            'appendix', 'html', 'classic', 'anexo', '007' => 'appendix',
            'combined', 'merge', 'todo', 'ambos' => 'combined',
            default => 'combined',
        };
    }

    private function buildCoberturaAppendixFilename(string $filename): string
    {
        if ($filename === '') {
            return 'cobertura_appendix.pdf';
        }

        if (preg_match('/\.pdf$/i', $filename) === 1) {
            return preg_replace('/\.pdf$/i', '_anexo.pdf', $filename) ?? ($filename . '_anexo.pdf');
        }

        return $filename . '_anexo.pdf';
    }

}


