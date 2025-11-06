<?php

namespace Controllers;

require_once dirname(__DIR__) . '/modules/Reporting/Support/LegacyLoader.php';

use Controllers\SolicitudController;
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
    private SolicitudController $solicitudController; // ✅ nueva propiedad
    private ReportingReportController $reportController;
    private ProtocolReportService $protocolReportService;
    private ReportService $reportService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->solicitudController = new SolicitudController($this->db);
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

    public function generateCobertura(string $form_id, string $hc_number)
    {
        $documento = $this->protocolReportService->generateCoberturaDocument($form_id, $hc_number);

        if (($documento['mode'] ?? null) === 'report') {
            $options = isset($documento['options']) && is_array($documento['options'])
                ? $documento['options']
                : [];

            $options['finalName'] = $documento['filename'];
            $options['modoSalida'] = $options['modoSalida'] ?? 'I';

            $appendix = isset($documento['append']) && is_array($documento['append'])
                ? $documento['append']
                : null;

            if ($appendix !== null && isset($appendix['html']) && is_string($appendix['html']) && $appendix['html'] !== '') {
                $baseDocument = $this->reportService->renderDocument(
                    (string) $documento['slug'],
                    isset($documento['data']) && is_array($documento['data']) ? $documento['data'] : [],
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
                        $appendix['html'],
                        [
                            'css' => $appendix['css'] ?? null,
                            'orientation' => $appendix['orientation'] ?? 'P',
                            'mpdf' => $appendix['mpdf'] ?? [],
                        ]
                    );

                    $this->emitPdf(
                        $mergedPdf,
                        $documento['filename'],
                        (string) $options['modoSalida'],
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
        $mpdf->SetImportUse();

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
        $mode = strtoupper($mode);

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

}


