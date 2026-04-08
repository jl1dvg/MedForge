<?php

namespace App\Modules\Reporting\Services;

use App\Models\ImagenSigcenterIndex;
use App\Modules\Examenes\Services\ImagenesSigcenterIndexService;
use App\Modules\Examenes\Services\SigcenterImagenesService;
use App\Modules\Reporting\Services\Definitions\PdfTemplateRegistry;
use App\Modules\Reporting\Services\Definitions\SolicitudTemplateRegistry;
use App\Modules\Reporting\Support\SolicitudDataFormatter;
use Illuminate\Support\Facades\Log;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

class ReportPdfService
{
    private const PROTOCOL_PAGES = [
        'protocolo',
        '005',
        'medicamentos',
        'signos_vitales',
        'insumos',
        'saveqx',
    ];

    private const PROTOCOL_LANDSCAPE_PAGE = 'transanestesico';

    private ProtocolReportDataService $protocolDataService;
    private CoberturaReportDataService $coberturaDataService;
    private ConsultaReportDataService $consultaDataService;
    private PostSurgeryRestReportDataService $postSurgeryRestDataService;
    private ImagenesReportDataService $imagenesDataService;

    private string $moduleBasePath;
    private ?ReportService $reportService = null;
    private ?SolicitudTemplateRegistry $solicitudTemplateRegistry = null;
    private ?SigcenterImagenesService $sigcenterImagenesService = null;
    private ?ImagenesSigcenterIndexService $imagenesSigcenterIndexService = null;

    public function __construct()
    {
        $this->protocolDataService = new ProtocolReportDataService();
        $this->coberturaDataService = new CoberturaReportDataService();
        $this->consultaDataService = new ConsultaReportDataService();
        $this->postSurgeryRestDataService = new PostSurgeryRestReportDataService();
        $this->imagenesDataService = new ImagenesReportDataService();
        $this->moduleBasePath = dirname(__DIR__);
    }

    /**
     * @return array{content:string,filename:string}|null
     */
    public function generateProtocolPdf(
        string $formId,
        string $hcNumber,
        string $mode = 'completo',
        ?string $requestedPage = null
    ): ?array {
        $data = $this->protocolDataService->buildProtocolData($formId, $hcNumber);
        if ($data === []) {
            return null;
        }

        $safeFormId = $this->safeFilePart($formId);
        $safeHcNumber = $this->safeFilePart($hcNumber);
        $cssPath = $this->moduleBasePath() . '/Templates/assets/pdf.css';

        $mode = strtolower(trim($mode));
        if ($mode === 'separado' && is_string($requestedPage) && trim($requestedPage) !== '') {
            $slug = $this->normalizeIdentifier($requestedPage);
            $html = $this->reportService()->renderIfExists($slug, $data);
            if (!is_string($html) || trim($html) === '') {
                return null;
            }

            $orientation = $slug === self::PROTOCOL_LANDSCAPE_PAGE ? 'L' : 'P';
            $filename = sprintf('%s_%s_%s.pdf', $slug, $safeFormId, $safeHcNumber);
            $content = $this->renderHtmlPdf($html, $filename, $cssPath, $orientation);

            return [
                'content' => $content,
                'filename' => $filename,
            ];
        }

        $segments = array_merge(self::PROTOCOL_PAGES, [self::PROTOCOL_LANDSCAPE_PAGE]);
        $html = $this->renderSegments($segments, $data, [
            self::PROTOCOL_LANDSCAPE_PAGE => 'L',
        ]);

        if (trim($html) === '') {
            return null;
        }

        $filename = sprintf('protocolo_%s_%s.pdf', $safeFormId, $safeHcNumber);
        $content = $this->renderHtmlPdf($html, $filename, $cssPath, 'P');

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @param array<int, string>|null $segmentsOverride
     * @return array{content:string,filename:string}|null
     */
    public function generateCoberturaPdf(
        string $formId,
        string $hcNumber,
        string $variant = 'combined',
        ?array $segmentsOverride = null
    ): ?array {
        $data = $this->coberturaDataService->buildCoberturaData($formId, $hcNumber);
        if ($data === []) {
            return null;
        }

        $data = $this->enrichSolicitudData($data, $formId, $hcNumber);
        $variant = $this->normalizeCoberturaVariant($variant);

        return $this->renderCobertura($formId, $hcNumber, $data, $variant, $segmentsOverride);
    }

    /**
     * @return array{content:string,filename:string}|null
     */
    public function generateConsultaPdf(string $formId, string $hcNumber): ?array
    {
        $data = $this->consultaDataService->buildConsultaReportData($formId, $hcNumber);
        if ($data === []) {
            return null;
        }

        $data = $this->enrichSolicitudData($data, $formId, $hcNumber);

        $safeFormId = $this->safeFilePart($formId);
        $safeHcNumber = $this->safeFilePart($hcNumber);
        $filename = sprintf('consulta_iess_%s_%s.pdf', $safeFormId, $safeHcNumber);

        $content = (string) $this->reportService()->renderPdf('002', $data, [
            'filename' => $filename,
        ]);
        $this->assertPdf($content, 'consulta');

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{content:string,filename:string}|null
     */
    public function generatePostSurgeryRestPdf(string $formId, string $hcNumber, array $options = []): ?array
    {
        $data = $this->postSurgeryRestDataService->buildData($formId, $hcNumber, $options);
        if (!is_array($data) || $data === []) {
            return null;
        }

        $safeFormId = $this->safeFilePart($formId);
        $safeHcNumber = $this->safeFilePart($hcNumber);
        $filename = sprintf('certificado_descanso_%s_%s.pdf', $safeFormId, $safeHcNumber);

        $content = (string) $this->reportService()->renderPdf('certificado_descanso_postquirurgico', $data, [
            'filename' => $filename,
        ]);
        $this->assertPdf($content, 'certificado_descanso');

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @return array{content:string,filename:string}|null
     */
    public function generateInforme012BPdf(string $formId, string $hcNumber, ?string $fechaDocumento = null): ?array
    {
        $data = $this->imagenesDataService->buildInforme012BData($formId, $hcNumber);
        if ($data === []) {
            return null;
        }

        $reportPayload = is_array($data['report'] ?? null) ? $data['report'] : [];
        if ($reportPayload === []) {
            return null;
        }

        if ($fechaDocumento !== null && $fechaDocumento !== '') {
            $reportPayload = $this->applyFechaDocumentoOverrideTo012BPayload($reportPayload, $fechaDocumento);
        }

        $resolvedHc = trim((string) ($data['hc_number'] ?? $hcNumber));
        if ($resolvedHc === '') {
            $resolvedHc = $hcNumber;
        }

        $filename = '012B_' . $this->safeFilePart($resolvedHc) . '_' . date('Ymd_His') . '.pdf';

        $content = (string) $this->reportService()->renderPdf('012B', $reportPayload, [
            'filename' => $filename,
        ]);
        $this->assertPdf($content, '012B');

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     * @return array{content:string,filename:string}|null
     */
    public function generateCobertura012APdf(
        string $formId,
        string $hcNumber,
        ?int $examenId = null,
        array $selectedItems = [],
        bool $preserveBaseContext = false,
        ?string $fechaDocumento = null
    ): ?array {
        $data = $this->imagenesDataService->buildCobertura012AData(
            $formId,
            $hcNumber,
            $examenId,
            $selectedItems,
            $preserveBaseContext
        );
        if ($data === []) {
            return null;
        }

        if ($fechaDocumento !== null && $fechaDocumento !== '') {
            $data = $this->applyFechaDocumentoOverrideTo012AData($data, $fechaDocumento);
        }

        $data = $this->enrichSolicitudData($data, $formId, $hcNumber);
        $filename = '012A_' . $this->safeFilePart($hcNumber) . '_' . date('Ymd_His') . '.pdf';

        $content = (string) $this->reportService()->renderPdf('012A', $data, [
            'filename' => $filename,
        ]);
        $this->assertPdf($content, '012A');

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{content:string,filename:string}|null
     */
    public function generateInforme012BPackagePdf(array $items, ?string $fechaDocumento = null): ?array
    {
        $normalized = $this->normalizePackageItems($items);
        if ($normalized === []) {
            return null;
        }

        $hcBase = (string) ($normalized[0]['hc_number'] ?? '');
        if ($hcBase === '') {
            return null;
        }

        foreach ($normalized as $item) {
            if (($item['hc_number'] ?? '') !== $hcBase) {
                throw new RuntimeException('Los exámenes seleccionados deben pertenecer al mismo paciente.');
            }
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $tempFiles = [];
        $hasPages = false;

        try {
            $baseContext = $this->imagenesDataService->resolveCobertura012ABaseContext($normalized);
            $baseFormId = trim((string) ($baseContext['form_id'] ?? ($normalized[0]['form_id'] ?? '')));
            $baseHcNumber = trim((string) ($baseContext['hc_number'] ?? $hcBase));
            $baseExamenId = isset($baseContext['examen_id']) ? (int) $baseContext['examen_id'] : null;

            foreach ($this->groupPackageItemsByFecha($normalized) as $groupedItems) {
                if ($groupedItems === []) {
                    continue;
                }

                $cobertura012A = $this->generateCobertura012APdf(
                    $baseFormId,
                    $baseHcNumber !== '' ? $baseHcNumber : $hcBase,
                    $baseExamenId,
                    $groupedItems,
                    true,
                    $fechaDocumento
                );
                if (is_array($cobertura012A) && isset($cobertura012A['content'])) {
                    $tmp012A = $this->writeTempFile((string) $cobertura012A['content'], 'pdf');
                    $tempFiles[] = $tmp012A;
                    if ($this->safeAppendPdfFile($pdf, $tmp012A, [
                        'source' => '012A',
                        'hc_number' => $hcBase,
                        'fecha_examen' => (string) ($groupedItems[0]['fecha_examen'] ?? ''),
                    ])) {
                        $hasPages = true;
                    }
                }

                foreach ($groupedItems as $item) {
                    $formId = (string) ($item['form_id'] ?? '');
                    $hcNumber = (string) ($item['hc_number'] ?? '');
                    if ($formId === '' || $hcNumber === '') {
                        continue;
                    }

                    $informe012B = $this->generateInforme012BPdf($formId, $hcNumber, $fechaDocumento);
                    if (is_array($informe012B) && isset($informe012B['content'])) {
                        $tmp012B = $this->writeTempFile((string) $informe012B['content'], 'pdf');
                        $tempFiles[] = $tmp012B;
                        if ($this->safeAppendPdfFile($pdf, $tmp012B, [
                            'source' => '012B',
                            'form_id' => $formId,
                            'hc_number' => $hcNumber,
                            'fecha_examen' => (string) ($item['fecha_examen'] ?? ''),
                        ])) {
                            $hasPages = true;
                        }
                    }

                    $files = $this->getSigcenterPackageFiles($formId, $hcNumber);
                    if ($this->isAngiografiaRetinal((string) ($item['tipo_examen'] ?? null)) && count($files) > 2) {
                        $files = array_slice($files, 0, 2);
                    }

                    foreach ($files as $file) {
                        if (!is_array($file)) {
                            continue;
                        }

                        $relativePath = trim((string) ($file['relative_path'] ?? ''));
                        if ($relativePath === '') {
                            continue;
                        }

                        $prepared = $this->prepareSigcenterFileForPackage($relativePath, [
                            'tipo_examen' => (string) ($item['tipo_examen'] ?? ''),
                            'form_id' => $formId,
                            'hc_number' => $hcNumber,
                        ], $fechaDocumento);
                        if (!is_array($prepared)) {
                            continue;
                        }

                        $tmpPath = (string) ($prepared['path'] ?? '');
                        $ext = strtolower((string) ($prepared['ext'] ?? ''));
                        if ($tmpPath === '' || !is_file($tmpPath)) {
                            continue;
                        }

                        $tempFiles[] = $tmpPath;

                        if ($ext === 'pdf') {
                            if ($this->safeAppendPdfFile($pdf, $tmpPath, [
                                'source' => 'sigcenter_file',
                                'form_id' => $formId,
                                'hc_number' => $hcNumber,
                                'relative_path' => $relativePath,
                                'fecha_examen' => (string) ($item['fecha_examen'] ?? ''),
                            ])) {
                                $hasPages = true;
                            }
                            continue;
                        }

                        $this->appendImageFile($pdf, $tmpPath);
                        $hasPages = true;
                    }
                }
            }

            if (!$hasPages) {
                throw new RuntimeException('No se encontraron documentos para generar el paquete 012B.');
            }

            $filename = $this->buildPaqueteFilename($hcBase);
            $content = (string) $pdf->Output($filename, 'S');
            $this->assertPdf($content, '012B_package');

            return [
                'content' => $content,
                'filename' => $filename,
            ];
        } finally {
            foreach ($tempFiles as $tmp) {
                if (is_string($tmp) && $tmp !== '' && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    private function reportService(): ReportService
    {
        if ($this->reportService !== null) {
            return $this->reportService;
        }

        $templatesPath = $this->moduleBasePath() . '/Templates/reports';
        $pdfTemplatesPath = $this->storageTemplatesPath();
        $pdfTemplateConfigPath = $this->moduleBasePath() . '/Services/Definitions/pdf-templates.php';

        $pdfRenderer = new PdfRenderer($this->appBasePath());
        $pdfTemplateRegistry = PdfTemplateRegistry::fromConfig($pdfTemplateConfigPath);
        $pdfTemplateRenderer = new PdfTemplateRenderer($pdfTemplatesPath);

        $this->reportService = new ReportService(
            $templatesPath,
            $pdfRenderer,
            $pdfTemplateRegistry,
            $pdfTemplateRenderer
        );

        return $this->reportService;
    }

    private function solicitudTemplateRegistry(): SolicitudTemplateRegistry
    {
        if ($this->solicitudTemplateRegistry !== null) {
            return $this->solicitudTemplateRegistry;
        }

        $configPath = $this->moduleBasePath() . '/Services/Definitions/solicitud-templates.php';
        $this->solicitudTemplateRegistry = SolicitudTemplateRegistry::fromConfig($configPath);

        return $this->solicitudTemplateRegistry;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function enrichSolicitudData(array $data, string $formId, string $hcNumber): array
    {
        return SolicitudDataFormatter::enrich($data, $formId, $hcNumber);
    }

    private function normalizeCoberturaVariant(string $variant): string
    {
        $normalized = strtolower(trim($variant));

        return match ($normalized) {
            'template', 'form', 'fijo', 'plantilla' => 'template',
            'appendix', 'html', 'classic', 'anexo', '007' => 'appendix',
            'combined', 'merge', 'todo', 'ambos' => 'combined',
            default => 'combined',
        };
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|null $segmentsOverride
     * @return array{content:string,filename:string}
     */
    private function renderCobertura(
        string $formId,
        string $hcNumber,
        array $data,
        string $variant,
        ?array $segmentsOverride
    ): array {
        $registry = $this->solicitudTemplateRegistry();
        $definition = $registry->resolve($data);
        if ($definition === null) {
            $definition = $registry->get('cobertura');
        }

        if ($definition === null) {
            throw new RuntimeException('No existe una plantilla configurada para cobertura.');
        }

        $filename = $definition->buildFilename($formId, $hcNumber);
        $appendix = $variant !== 'template'
            ? $this->buildCoberturaAppendixDocument($registry, $definition, $formId, $hcNumber, $data, $segmentsOverride)
            : null;

        $reportSlug = $definition->getReportSlug();
        if (is_string($reportSlug) && trim($reportSlug) !== '') {
            $reportOptions = $definition->getReportOptions();
            $renderOptions = $this->extractReportRenderOptions($reportOptions, $filename);

            if ($variant === 'template') {
                $content = (string) $this->reportService()->renderPdf($reportSlug, $data, $renderOptions);
                $this->assertPdf($content, 'cobertura_template');

                return ['content' => $content, 'filename' => $filename];
            }

            if ($variant === 'appendix') {
                if ($appendix === null) {
                    $content = (string) $this->reportService()->renderPdf($reportSlug, $data, $renderOptions);
                    $this->assertPdf($content, 'cobertura_appendix_fallback');

                    return ['content' => $content, 'filename' => $filename];
                }

                $appendixFilename = $this->buildCoberturaAppendixFilename($appendix['filename']);
                $content = $this->renderHtmlPdf(
                    $appendix['html'],
                    $appendixFilename,
                    $appendix['css'],
                    $appendix['orientation'],
                    $appendix['mpdf']
                );

                return ['content' => $content, 'filename' => $appendixFilename];
            }

            $document = $this->reportService()->renderDocument($reportSlug, $data, array_merge($renderOptions, [
                'destination' => 'S',
            ]));
            $content = (string) ($document['content'] ?? '');
            $this->assertPdf($content, 'cobertura_base');

            if ($appendix !== null) {
                $content = $this->appendHtmlToPdf($content, $appendix['html'], [
                    'css' => $appendix['css'],
                    'orientation' => $appendix['orientation'],
                    'mpdf' => $appendix['mpdf'],
                ]);
                $this->assertPdf($content, 'cobertura_combined');
            }

            return ['content' => $content, 'filename' => $filename];
        }

        $full = $this->buildCoberturaHtmlDocument($definition, $formId, $hcNumber, $data);
        $target = ($variant === 'appendix' && $appendix !== null) ? $appendix : $full;

        $content = $this->renderHtmlPdf(
            $target['html'],
            $target['filename'],
            $target['css'],
            $target['orientation'],
            $target['mpdf']
        );

        return [
            'content' => $content,
            'filename' => $target['filename'],
        ];
    }

    /**
     * @param array<string, mixed> $reportOptions
     * @return array<string, mixed>
     */
    private function extractReportRenderOptions(array $reportOptions, string $filename): array
    {
        $options = ['filename' => $filename];

        $optional = ['font_family', 'font_size', 'line_height', 'text_color'];
        foreach ($optional as $key) {
            if (array_key_exists($key, $reportOptions) && $reportOptions[$key] !== null && $reportOptions[$key] !== '') {
                $options[$key] = $reportOptions[$key];
            }
        }

        if (isset($reportOptions['overrides']) && is_array($reportOptions['overrides'])) {
            $options['overrides'] = $reportOptions['overrides'];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{html:string,filename:string,css:?string,orientation:string,mpdf:array<string,mixed>}
     */
    private function buildCoberturaHtmlDocument(
        mixed $definition,
        string $formId,
        string $hcNumber,
        array $data
    ): array {
        $pages = $definition->getPages();
        if (!is_array($pages) || $pages === []) {
            throw new RuntimeException(sprintf('La plantilla "%s" no tiene páginas configuradas.', $definition->getIdentifier()));
        }

        $html = $this->renderSegments($pages, $data, $definition->getOrientations());

        return [
            'html' => $html,
            'filename' => $definition->buildFilename($formId, $hcNumber),
            'css' => $definition->getCss(),
            'orientation' => $definition->getDefaultOrientation(),
            'mpdf' => $definition->getMpdfOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|null $segmentsOverride
     * @return array{html:string,filename:string,css:?string,orientation:string,mpdf:array<string,mixed>}|null
     */
    private function buildCoberturaAppendixDocument(
        mixed $registry,
        mixed $definition,
        string $formId,
        string $hcNumber,
        array $data,
        ?array $segmentsOverride
    ): ?array {
        $segments = [];
        if (is_array($segmentsOverride)) {
            foreach ($segmentsOverride as $segment) {
                if (!is_string($segment)) {
                    continue;
                }
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                $segments[] = $segment;
            }
        }

        if ($segments === []) {
            $segments = $definition->getAppendViews();
            if ($segments === []) {
                $segments = $definition->getPages();
            }
        }

        if ($segments === []) {
            $fallback = $registry->get('cobertura');
            if ($fallback !== null) {
                $definition = $fallback;
                $segments = $definition->getPages();
            }
        }

        if ($segments === []) {
            return null;
        }

        $html = $this->renderSegments($segments, $data, $definition->getOrientations());
        if ($html === '') {
            return null;
        }

        return [
            'html' => $html,
            'filename' => $definition->buildFilename($formId, $hcNumber),
            'css' => $definition->getCss(),
            'orientation' => $definition->getDefaultOrientation(),
            'mpdf' => $definition->getMpdfOptions(),
        ];
    }

    /**
     * @param array<int, string> $identifiers
     * @param array<string, mixed> $data
     * @param array<string, string> $orientations
     */
    private function renderSegments(array $identifiers, array $data, array $orientations = []): string
    {
        $html = '';

        foreach ($identifiers as $identifier) {
            if (!is_string($identifier)) {
                continue;
            }

            $slug = trim($identifier);
            if ($slug === '') {
                continue;
            }

            $segment = $this->reportService()->renderIfExists($slug, $data);
            if (!is_string($segment) || $segment === '') {
                continue;
            }

            if ($html !== '') {
                $orientation = strtoupper((string) ($orientations[$slug] ?? ''));
                if ($orientation === 'P' || $orientation === 'L') {
                    $html .= '<pagebreak orientation="' . $orientation . '">';
                } else {
                    $html .= '<pagebreak>';
                }
            }

            $html .= $segment;
        }

        return $html;
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

        $mpdfOptions = [
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
            $mpdfOptions = array_replace($mpdfOptions, $options['mpdf']);
        }

        $mpdf = new Mpdf($mpdfOptions);

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
            if (is_string($css) && $css !== '') {
                $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
            }
        }

        $this->writeHtmlInChunks($mpdf, $html, HTMLParserMode::HTML_BODY);

        return $mpdf->Output('', 'S');
    }

    /**
     * @param array<string, mixed> $mpdfOptions
     */
    private function renderHtmlPdf(
        string $html,
        string $filename,
        ?string $cssPath,
        string $orientation = 'P',
        array $mpdfOptions = []
    ): string {
        $options = $mpdfOptions;

        $orientation = strtoupper(trim($orientation));
        if ($orientation !== 'P' && $orientation !== 'L') {
            $orientation = 'P';
        }
        if (!isset($options['orientation'])) {
            $options['orientation'] = $orientation;
        }

        $content = (string) (new PdfRenderer($this->appBasePath()))
            ->renderHtml($html, [
                'css' => $cssPath,
                'mpdf' => $options,
                'filename' => $filename,
                'destination' => 'S',
            ]);

        $this->assertPdf($content, 'html_render');

        return $content;
    }

    private function moduleBasePath(): string
    {
        return rtrim($this->moduleBasePath, '/\\');
    }

    private function storageTemplatesPath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('reporting/templates');
        }

        return $this->appBasePath() . '/storage/reporting/templates';
    }

    private function appBasePath(): string
    {
        if (function_exists('base_path')) {
            return rtrim(base_path(), '/\\');
        }

        return dirname(__DIR__, 4);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizePackageItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $key = $formId . '|' . $hcNumber;
            $normalized[$key] = [
                'id' => isset($item['id']) ? (int) $item['id'] : null,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'fecha_examen' => trim((string) ($item['fecha_examen'] ?? '')),
                'estado_agenda' => trim((string) ($item['estado_agenda'] ?? '')),
                'tipo_examen' => trim((string) ($item['tipo_examen'] ?? $item['examen_nombre'] ?? '')),
                'codigo' => trim((string) ($item['codigo'] ?? $item['examen_codigo'] ?? '')),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyFechaDocumentoOverrideTo012AData(array $data, string $fechaDocumento): array
    {
        if ($fechaDocumento === '') {
            return $data;
        }

        if (!isset($data['consulta']) || !is_array($data['consulta'])) {
            $data['consulta'] = [];
        }
        $consultaCreatedAt = trim((string) ($data['consulta']['created_at'] ?? ''));
        $consultaTime = $this->extractTimePart($consultaCreatedAt);
        $data['consulta']['fecha'] = $fechaDocumento;
        if ($consultaTime !== '') {
            $data['consulta']['created_at'] = $fechaDocumento . ' ' . $consultaTime;
        } else {
            $data['consulta']['created_at'] = $fechaDocumento;
        }

        if (!isset($data['solicitud']) || !is_array($data['solicitud'])) {
            $data['solicitud'] = [];
        }
        $solicitudTime = trim((string) ($data['solicitud']['created_at_time'] ?? ''));
        $data['solicitud']['created_at_date'] = $fechaDocumento;
        if ($solicitudTime !== '') {
            $data['solicitud']['created_at'] = $fechaDocumento . ' ' . $solicitudTime;
        } else {
            $data['solicitud']['created_at'] = $fechaDocumento;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $reportPayload
     * @return array<string, mixed>
     */
    private function applyFechaDocumentoOverrideTo012BPayload(array $reportPayload, string $fechaDocumento): array
    {
        if ($fechaDocumento === '') {
            return $reportPayload;
        }

        if (!isset($reportPayload['informe']) || !is_array($reportPayload['informe'])) {
            $reportPayload['informe'] = [];
        }
        $reportPayload['informe']['fecha'] = $fechaDocumento;

        return $reportPayload;
    }

    private function extractTimePart(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('H:i', $timestamp);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupPackageItemsByFecha(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $fechaRaw = trim((string) ($item['fecha_examen'] ?? ''));
            $fechaKey = preg_match('/^\d{4}-\d{2}-\d{2}/', $fechaRaw) === 1
                ? substr($fechaRaw, 0, 10)
                : '__sin_fecha__';

            if (!isset($grouped[$fechaKey])) {
                $grouped[$fechaKey] = [];
            }

            $grouped[$fechaKey][] = $item;
        }

        return array_values($grouped);
    }

    private function sigcenterImagenesService(): SigcenterImagenesService
    {
        if ($this->sigcenterImagenesService === null) {
            $this->sigcenterImagenesService = new SigcenterImagenesService();
        }

        return $this->sigcenterImagenesService;
    }

    private function imagenesSigcenterIndexService(): ImagenesSigcenterIndexService
    {
        if ($this->imagenesSigcenterIndexService === null) {
            $this->imagenesSigcenterIndexService = app(ImagenesSigcenterIndexService::class);
        }

        return $this->imagenesSigcenterIndexService;
    }

    /**
     * @return array<int,array{name:string,size:int,mtime:int,ext:string,type:string,source:string,relative_path:string}>
     */
    private function getSigcenterPackageFiles(string $formId, string $hcNumber): array
    {
        $index = ImagenSigcenterIndex::query()
            ->where('form_id', $formId)
            ->where('hc_number', $hcNumber)
            ->first();

        if (!$index instanceof ImagenSigcenterIndex) {
            $this->imagenesSigcenterIndexService()->scan([
                'form_id' => $formId,
                'force' => true,
            ]);

            $index = ImagenSigcenterIndex::query()
                ->where('form_id', $formId)
                ->where('hc_number', $hcNumber)
                ->first();
        }

        if (!$index instanceof ImagenSigcenterIndex) {
            return [];
        }

        $filesMeta = is_array($index->files_meta) ? $index->files_meta : [];
        if ($filesMeta === []) {
            return [];
        }

        $files = [];
        foreach ($filesMeta as $file) {
            if (!is_array($file)) {
                continue;
            }

            $relativePath = trim((string) ($file['relative_path'] ?? ''));
            $name = basename($relativePath !== '' ? $relativePath : (string) ($file['foto'] ?? ''));
            if ($name === '') {
                continue;
            }

            $mtime = trim((string) ($file['mtime'] ?? ''));
            $mtimeTs = $mtime !== '' ? (int) (strtotime($mtime) ?: 0) : 0;
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            $files[] = [
                'name' => $name,
                'size' => (int) ($file['size'] ?? 0),
                'mtime' => $mtimeTs,
                'ext' => $ext,
                'type' => ((string) ($file['tipo'] ?? '')) === 'pdf' ? 'application/pdf' : $this->resolveMimeByFilename($name),
                'source' => 'sigcenter',
                'relative_path' => $relativePath,
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return [$b['mtime'] ?? 0, $a['name'] ?? ''] <=> [$a['mtime'] ?? 0, $b['name'] ?? ''];
        });

        return array_values($files);
    }

    /**
     * @return array{path:string,ext:string}|null
     */
    private function prepareSigcenterFileForPackage(
        string $relativePath,
        array $context = [],
        ?string $fechaDocumento = null
    ): ?array
    {
        $ext = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'bin';
        }

        $tmpPath = $this->createTempPath($ext);
        if (!$this->sigcenterImagenesService()->downloadRelativeFileToPath($relativePath, $tmpPath)) {
            @unlink($tmpPath);
            return null;
        }

        if ($ext === 'pdf' && $fechaDocumento !== null && $fechaDocumento !== '') {
            $tipoExamen = (string) ($context['tipo_examen'] ?? '');
            $maskContext = $context + [
                'relative_path' => $relativePath,
            ];

            if ($this->isTopografiaCorneal($tipoExamen)) {
                $this->maskTopografiaPdfDateInPlace($tmpPath, $maskContext);
            } elseif ($this->isMicroespecular($tipoExamen)) {
                $this->maskMicroespecularPdfDateInPlace($tmpPath, $maskContext);
            }
        }

        return [
            'path' => $tmpPath,
            'ext' => $ext,
        ];
    }

    private function createTempPath(string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'imgpdf_');
        if ($base === false) {
            throw new RuntimeException('No se pudo crear archivo temporal.');
        }

        $ext = strtolower(trim($ext));
        if ($ext === '' || preg_match('/^[a-z0-9]+$/', $ext) !== 1) {
            $ext = 'bin';
        }

        $path = $base . '.' . $ext;
        @rename($base, $path);

        return $path;
    }

    private function writeTempFile(string $content, string $ext): string
    {
        $path = $this->createTempPath($ext);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param resource $stream
     */
    private function writeTempStream($stream, string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'imgpdf_');
        if ($base === false) {
            throw new RuntimeException('No se pudo crear archivo temporal.');
        }

        $ext = strtolower(trim($ext));
        if ($ext === '' || preg_match('/^[a-z0-9]+$/', $ext) !== 1) {
            $ext = 'bin';
        }

        $path = $base . '.' . $ext;
        @rename($base, $path);

        $dest = fopen($path, 'wb');
        if ($dest === false) {
            throw new RuntimeException('No se pudo escribir archivo temporal.');
        }

        stream_copy_to_stream($stream, $dest);
        fclose($dest);

        return $path;
    }

    private function appendPdfFile(Fpdi $pdf, string $path): void
    {
        $pageCount = $pdf->setSourceFile($path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function maskTopografiaPdfDateInPlace(string $path, array $context = []): void
    {
        try {
            $masked = new Fpdi();
            $masked->SetAutoPageBreak(false, 0);
            $masked->setPrintHeader(false);
            $masked->setPrintFooter(false);

            $pageCount = $masked->setSourceFile($path);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $masked->importPage($pageNo);
                $size = $masked->getTemplateSize($tplId);
                $width = (float) ($size['width'] ?? 0.0);
                $height = (float) ($size['height'] ?? 0.0);

                if ($width <= 0.0 || $height <= 0.0) {
                    continue;
                }

                $masked->AddPage($size['orientation'], [$width, $height]);
                $masked->useTemplate($tplId);
                $this->drawTopografiaDateMasks($masked, $width, $height);
            }

            $content = (string) $masked->Output('', 'S');
            if ($this->isPdfContent($content)) {
                file_put_contents($path, $content);
                return;
            }

            Log::warning('reporting.pdf.topografia_mask_skipped', $context + [
                'path' => $path,
                'reason' => 'invalid_masked_pdf',
            ]);
        } catch (\Throwable $e) {
            Log::warning('reporting.pdf.topografia_mask_skipped', $context + [
                'path' => $path,
                'reason' => 'mask_failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function drawTopografiaDateMasks(Fpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $pdf->SetFillColor(255, 255, 255);

        // Header date below "Mapeo corneal por Scheimpflug".
        $pdf->Rect(
            $pageWidth * 0.342,
            $pageHeight * 0.041,
            $pageWidth * 0.205,
            $pageHeight * 0.038,
            'F'
        );

        // Left sidebar date shown under "Mapeo corneal por Scheimpflug".
        $pdf->Rect(
            $pageWidth * 0.021,
            $pageHeight * 0.27,
            $pageWidth * 0.125,
            $pageHeight * 0.030,
            'F'
        );

        // Safety mask for the same left block when the export shifts a few pixels.
        //$pdf->Rect(
        //    $pageWidth * 0.014,
        //    $pageHeight * 0.218,
        //    $pageWidth * 0.135,
        //    $pageHeight * 0.042,
        //    'F'
        //);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function maskMicroespecularPdfDateInPlace(string $path, array $context = []): void
    {
        try {
            $masked = new Fpdi();
            $masked->SetAutoPageBreak(false, 0);
            $masked->setPrintHeader(false);
            $masked->setPrintFooter(false);

            $pageCount = $masked->setSourceFile($path);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $masked->importPage($pageNo);
                $size = $masked->getTemplateSize($tplId);
                $width = (float) ($size['width'] ?? 0.0);
                $height = (float) ($size['height'] ?? 0.0);

                if ($width <= 0.0 || $height <= 0.0) {
                    continue;
                }

                $masked->AddPage($size['orientation'], [$width, $height]);
                $masked->useTemplate($tplId);
                $this->drawMicroespecularDateMasks($masked, $width, $height);
            }

            $content = (string) $masked->Output('', 'S');
            if ($this->isPdfContent($content)) {
                file_put_contents($path, $content);
                return;
            }

            Log::warning('reporting.pdf.microespecular_mask_skipped', $context + [
                'path' => $path,
                'reason' => 'invalid_masked_pdf',
            ]);
        } catch (\Throwable $e) {
            Log::warning('reporting.pdf.microespecular_mask_skipped', $context + [
                'path' => $path,
                'reason' => 'mask_failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function drawMicroespecularDateMasks(Fpdi $pdf, float $pageWidth, float $pageHeight): void
    {
        $pdf->SetFillColor(255, 255, 255);

        // Header date/time in NIDEK endothelial microscopy reports.
        $pdf->Rect(
            $pageWidth * 0.72,
            $pageHeight * 0.01,
            $pageWidth * 0.22,
            $pageHeight * 0.055,
            'F'
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function safeAppendPdfFile(Fpdi $pdf, string $path, array $context = []): bool
    {
        if (!$this->isPdfFile($path)) {
            Log::warning('reporting.pdf.skip_invalid_pdf', $context + [
                'path' => $path,
                'reason' => 'missing_pdf_header',
            ]);

            return false;
        }

        try {
            $this->appendPdfFile($pdf, $path);
            return true;
        } catch (\Throwable $e) {
            Log::warning('reporting.pdf.skip_invalid_pdf', $context + [
                'path' => $path,
                'reason' => 'append_failed',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function appendImageFile(Fpdi $pdf, string $path): void
    {
        $info = @getimagesize($path);
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $orientation = ($width > $height) ? 'L' : 'P';

        $pdf->AddPage($orientation);
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();

        if ($width > 0 && $height > 0) {
            $ratio = min($pageWidth / $width, $pageHeight / $height);
            $renderWidth = $width * $ratio;
            $renderHeight = $height * $ratio;
            $x = ($pageWidth - $renderWidth) / 2;
            $y = ($pageHeight - $renderHeight) / 2;
            $pdf->Image($path, $x, $y, $renderWidth, $renderHeight);
            return;
        }

        $pdf->Image($path, 0, 0, $pageWidth, $pageHeight);
    }

    private function resolveMimeByFilename(string $filename): string
    {
        return match (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    private function isAngiografiaRetinal(?string $tipoExamen): bool
    {
        $texto = $this->normalizeSearchText((string) ($tipoExamen ?? ''));
        if ($texto === '') {
            return false;
        }

        if (preg_match('/\b281021\b/', $texto) === 1) {
            return true;
        }

        return str_contains($texto, 'angiografia retinal');
    }

    private function isTopografiaCorneal(?string $tipoExamen): bool
    {
        $texto = $this->normalizeSearchText((string) ($tipoExamen ?? ''));
        if ($texto === '') {
            return false;
        }

        if (preg_match('/\b281186\b/', $texto) === 1) {
            return true;
        }

        return str_contains($texto, 'topografia corneal');
    }

    private function isMicroespecular(?string $tipoExamen): bool
    {
        $texto = $this->normalizeSearchText((string) ($tipoExamen ?? ''));
        if ($texto === '') {
            return false;
        }

        if (preg_match('/\b281197\b/', $texto) === 1) {
            return true;
        }

        return str_contains($texto, 'microscopia especular')
            || str_contains($texto, 'recuento de celulas endoteliales')
            || str_contains($texto, 'celulas endoteliales');
    }

    private function normalizeSearchText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function buildPaqueteFilename(string $hcNumber): string
    {
        $safeHc = $this->safeFilePart($hcNumber);

        return 'IMAGENES_' . $safeHc . '_' . date('m-Y') . '.pdf';
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

    private function safeFilePart(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'na';
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';

        return $safe !== '' ? $safe : 'na';
    }

    private function assertPdf(string $content, string $context): void
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new RuntimeException('No se generó un PDF válido para ' . $context . '.');
        }
    }

    private function isPdfContent(string $content): bool
    {
        return str_starts_with($content, '%PDF-');
    }

    private function isPdfFile(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $identifier = str_replace('\\', '/', $identifier);
        $identifier = basename($identifier);

        if (str_ends_with($identifier, '.php')) {
            $identifier = substr($identifier, 0, -4);
        }

        return trim($identifier);
    }

    private function writeHtmlInChunks(Mpdf $mpdf, string $html, int $mode, int $chunkSize = 200000): void
    {
        if (strlen($html) <= $chunkSize) {
            $mpdf->WriteHTML($html, $mode);
            return;
        }

        $parts = preg_split('/(<pagebreak[^>]*>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $this->writeSplitChunks($mpdf, $html, $mode, $chunkSize);
            return;
        }

        $buffer = '';
        foreach ($parts as $part) {
            $partLength = strlen($part);

            if ($partLength > $chunkSize) {
                if ($buffer !== '') {
                    $mpdf->WriteHTML($buffer, $mode);
                    $buffer = '';
                }
                $this->writeSplitChunks($mpdf, $part, $mode, $chunkSize);
                continue;
            }

            if (strlen($buffer) + $partLength > $chunkSize && $buffer !== '') {
                $mpdf->WriteHTML($buffer, $mode);
                $buffer = '';
            }

            $buffer .= $part;
        }

        if ($buffer !== '') {
            $mpdf->WriteHTML($buffer, $mode);
        }
    }

    private function writeSplitChunks(Mpdf $mpdf, string $html, int $mode, int $chunkSize): void
    {
        $remaining = $html;
        while ($remaining !== '') {
            if (strlen($remaining) <= $chunkSize) {
                $mpdf->WriteHTML($remaining, $mode);
                break;
            }

            $slice = substr($remaining, 0, $chunkSize);
            $cutPosition = strrpos($slice, '>');
            if ($cutPosition === false || $cutPosition < ($chunkSize * 0.5)) {
                $cutPosition = $chunkSize;
            } else {
                $cutPosition += 1;
            }

            $mpdf->WriteHTML(substr($remaining, 0, $cutPosition), $mode);
            $remaining = substr($remaining, $cutPosition);
        }
    }
}
