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

        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY); // ✅ Esta es la línea corregida
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
}

\class_alias(__NAMESPACE__ . '\\PdfGenerator', 'PdfGenerator');
