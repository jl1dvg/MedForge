<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Services\Definitions\PdfTemplateRegistry;
use App\Modules\Reporting\Services\Definitions\SolicitudTemplateRegistry;
use App\Modules\Reporting\Support\SolicitudDataFormatter;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;

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
    public function generateInforme012BPdf(string $formId, string $hcNumber): ?array
    {
        $data = $this->imagenesDataService->buildInforme012BData($formId, $hcNumber);
        if ($data === []) {
            return null;
        }

        $reportPayload = is_array($data['report'] ?? null) ? $data['report'] : [];
        if ($reportPayload === []) {
            return null;
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
        array $selectedItems = []
    ): ?array {
        $data = $this->imagenesDataService->buildCobertura012AData($formId, $hcNumber, $examenId, $selectedItems);
        if ($data === []) {
            return null;
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
