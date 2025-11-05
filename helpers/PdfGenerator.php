<?php

namespace Helpers;

require_once __DIR__ . '/../bootstrap.php';

use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Modules\Reporting\Services\ReportService;

class PdfGenerator
{
    private static function cargarHTML($archivo)
    {
        $service = new ReportService();

        return $service->render($archivo);
    }

    public static function generarDesdeHtml(
        string $html,
        string $finalName = 'documento.pdf',
        string $cssPath = null,
        string $modoSalida = 'I',
        string $orientation = 'P'
    ): void {
        $mpdf = new Mpdf([
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
        ]);

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
}

\class_alias(__NAMESPACE__ . '\\PdfGenerator', 'PdfGenerator');
