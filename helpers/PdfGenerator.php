<?php
require_once __DIR__ . '/../bootstrap.php';

use Mpdf\Mpdf;

class PdfGenerator
{
    private static function cargarHTML($archivo)
    {
        ob_start();
        include __DIR__ . '/../views/pdf/' . $archivo;
        return ob_get_clean();
    }

    public static function generarDesdeHtml(string $html, string $finalName = 'documento.pdf', string $cssPath = null, string $modoSalida = 'I', string $orientation = 'P')
    {
        $mpdf = new \Mpdf\Mpdf([
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

            $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS); // ✅ Aquí sí cargamos el CSS
        }

        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY); // ✅ Esta es la línea corregida
        $mpdf->Output($finalName, $modoSalida);
    }
}