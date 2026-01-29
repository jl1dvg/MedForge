<?php

namespace Helpers;

require_once __DIR__ . '/../bootstrap.php';

use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Modules\Reporting\Services\ReportService;
use Stringable;

class PdfGenerator
{
    private static function cargarHTML($archivo)
    {
        $service = new ReportService();

        return $service->render($archivo);
    }

    /**
     * @param array<string, mixed> $mpdfOptions
     */
    public static function generarDesdeHtml(
        string $html,
        string $finalName = 'documento.pdf',
        ?string $cssPath = null,
        string $modoSalida = 'I',
        string $orientation = 'P',
        array $mpdfOptions = []
    ): void {
        $modoSalida = self::normalizarModoSalida($modoSalida);

        $options = [
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

        if ($mpdfOptions !== []) {
            $options = array_merge($options, $mpdfOptions);
        }

        $mpdf = new Mpdf($options);

        if ($cssPath) {
            if (!file_exists($cssPath)) {
                die('No se encontró el CSS en: ' . $cssPath);
            }

            $stylesheet = file_get_contents($cssPath);

            if (!$stylesheet) {
                die('El CSS existe, pero está vacío o no se pudo leer.');
            }

            $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS); // ✅ Aquí sí cargamos el CSS
        }

        self::writeHtmlInChunks($mpdf, $html, HTMLParserMode::HTML_BODY);
        $mpdf->Output($finalName, $modoSalida);
    }

    /**
     * @param array<int, array{html: string, orientation?: string}|string> $segments
     * @param array<string, mixed> $mpdfOptions
     */
    public static function generarDesdeHtmlSegments(
        array $segments,
        string $finalName = 'documento.pdf',
        ?string $cssPath = null,
        string $modoSalida = 'I',
        array $mpdfOptions = []
    ): void {
        $segments = self::normalizeSegments($segments);

        if ($segments === []) {
            self::generarDesdeHtml('', $finalName, $cssPath, $modoSalida, 'P', $mpdfOptions);
            return;
        }

        $modoSalida = self::normalizarModoSalida($modoSalida);
        $firstOrientation = $segments[0]['orientation'] ?? 'P';

        $options = [
            'default_font_size' => 8,
            'default_font' => 'dejavusans',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'orientation' => $firstOrientation,
            'shrink_tables_to_fit' => 1,
            'use_kwt' => true,
            'autoScriptToLang' => true,
            'keep_table_proportions' => true,
            'allow_url_fopen' => true,
            'curlAllowUnsafeSslRequests' => true,
        ];

        if ($mpdfOptions !== []) {
            $options = array_merge($options, $mpdfOptions);
        }

        $mpdf = new Mpdf($options);

        if ($cssPath) {
            if (!file_exists($cssPath)) {
                die('No se encontró el CSS en: ' . $cssPath);
            }

            $stylesheet = file_get_contents($cssPath);

            if (!$stylesheet) {
                die('El CSS existe, pero está vacío o no se pudo leer.');
            }

            $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        }

        $isFirst = true;
        foreach ($segments as $segment) {
            $html = $segment['html'];

            if ($html === '') {
                continue;
            }

            if ($isFirst) {
                $isFirst = false;
            } else {
                $orientation = $segment['orientation'] ?? $firstOrientation;
                $mpdf->AddPage($orientation);
            }

            self::writeHtmlInChunks($mpdf, $html, HTMLParserMode::HTML_BODY);
        }

        $mpdf->Output($finalName, $modoSalida);
    }

    /**
     * Genera un PDF a partir de un slug del módulo Reporting, soportando plantillas PDF.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public static function generarReporte(string $slug, array $data = [], array $options = []): void
    {
        $service = new ReportService();

        if ($service->hasPdfTemplate($slug)) {
            $document = $service->renderDocument($slug, $data, [
                'filename' => $options['finalName'] ?? $slug . '.pdf',
                'destination' => 'S',
                'font_family' => $options['font_family'] ?? null,
                'font_size' => $options['font_size'] ?? null,
                'line_height' => $options['line_height'] ?? null,
                'text_color' => $options['text_color'] ?? null,
                'overrides' => $options['overrides'] ?? null,
            ]);

            $filename = $options['finalName'] ?? $document['filename'];
            $modoSalida = self::normalizarModoSalida($options['modoSalida'] ?? 'I');
            $filePath = $options['filePath'] ?? null;

            self::emitirPdfBinario($document['content'], $filename, $modoSalida, $filePath);
            return;
        }

        $html = $service->render($slug, $data);

        self::generarDesdeHtml(
            $html,
            $options['finalName'] ?? ($slug . '.pdf'),
            $options['css'] ?? null,
            self::normalizarModoSalida($options['modoSalida'] ?? 'I'),
            $options['orientation'] ?? 'P'
        );
    }

    /**
     * @param mixed $modoSalida
     */
    private static function emitirPdfBinario(string $contenido, string $nombreArchivo, $modoSalida, ?string $filePath = null): void
    {
        $modo = strtoupper(self::normalizarModoSalida($modoSalida));

        if ($modo === 'F') {
            $destino = $filePath ?? $nombreArchivo;
            file_put_contents($destino, $contenido);
            return;
        }

        if ($modo === 'S') {
            echo $contenido;
            return;
        }

        $disposition = $modo === 'D' ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $nombreArchivo));
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
    }

    /**
     * @param mixed $modoSalida
     */
    public static function normalizarModoSalida($modoSalida): string
    {
        while (is_array($modoSalida)) {
            $next = reset($modoSalida);

            if ($next === false && $next !== 0) {
                $modoSalida = null;
                break;
            }

            $modoSalida = $next;
        }

        if ($modoSalida instanceof Stringable) {
            $modoSalida = (string) $modoSalida;
        }

        if (is_string($modoSalida)) {
            $modoSalida = trim($modoSalida);
            if ($modoSalida !== '') {
                return $modoSalida;
            }
        } elseif (is_scalar($modoSalida) && $modoSalida !== null) {
            $modoSalida = trim((string) $modoSalida);
            if ($modoSalida !== '') {
                return $modoSalida;
            }
        }

        return 'I';
    }

    public static function writeHtmlInChunks(Mpdf $mpdf, string $html, int $mode, int $chunkSize = 200000): void
    {
        if (strlen($html) <= $chunkSize) {
            $mpdf->WriteHTML($html, $mode);
            return;
        }

        $parts = preg_split('/(<pagebreak[^>]*>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            self::writeSplitChunks($mpdf, $html, $mode, $chunkSize);
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
                self::writeSplitChunks($mpdf, $part, $mode, $chunkSize);
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

    private static function writeSplitChunks(Mpdf $mpdf, string $html, int $mode, int $chunkSize): void
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

    /**
     * @param array<int, array{html: string, orientation?: string}|string> $segments
     * @return array<int, array{html: string, orientation: string}>
     */
    private static function normalizeSegments(array $segments): array
    {
        $normalized = [];

        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $html = trim($segment);
                if ($html === '') {
                    continue;
                }
                $normalized[] = [
                    'html' => $html,
                    'orientation' => 'P',
                ];
                continue;
            }

            if (!is_array($segment)) {
                continue;
            }

            $html = isset($segment['html']) && is_string($segment['html']) ? trim($segment['html']) : '';
            if ($html === '') {
                continue;
            }

            $orientation = isset($segment['orientation']) && is_string($segment['orientation'])
                ? strtoupper(trim($segment['orientation']))
                : 'P';

            if ($orientation !== 'P' && $orientation !== 'L') {
                $orientation = 'P';
            }

            $normalized[] = [
                'html' => $html,
                'orientation' => $orientation,
            ];
        }

        return $normalized;
    }
}

\class_alias(__NAMESPACE__ . '\\PdfGenerator', 'PdfGenerator');
