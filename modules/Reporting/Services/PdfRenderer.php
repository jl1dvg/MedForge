<?php

namespace Modules\Reporting\Services;

use InvalidArgumentException;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Modules\Reporting\Support\PdfDestinationNormalizer;

class PdfRenderer
{
    private const DEFAULT_CONFIG = [
        'default_font_size' => 8,
        'default_font' => 'dejavusans',
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 5,
        'margin_bottom' => 5,
        'orientation' => 'P',
        'shrink_tables_to_fit' => 1,
        'use_kwt' => true,
        'autoScriptToLang' => true,
        'keep_table_proportions' => true,
        'allow_url_fopen' => true,
        'curlAllowUnsafeSslRequests' => true,
    ];

    public function __construct(private ?string $basePath = null)
    {
        $this->basePath = $basePath ?? BASE_PATH;
    }

    public function render(string $template, array $data, array $options = []): string
    {
        $html = $this->renderTemplate($template, $data);
        return $this->outputPdf($html, $options);
    }

    public function renderMany(array $pages, array $options = []): string
    {
        if (empty($pages)) {
            throw new InvalidArgumentException('No se proporcionaron páginas para el PDF.');
        }

        $html = '';
        foreach ($pages as $index => $page) {
            if (!isset($page['template'], $page['data'])) {
                throw new InvalidArgumentException('Cada página debe incluir las claves "template" y "data".');
            }

            $orientation = null;
            if (isset($page['orientation'])) {
                $orientation = strtoupper((string) $page['orientation']);
                if (!in_array($orientation, ['P', 'L'], true)) {
                    $orientation = null;
                }
            }

            if ($index > 0) {
                $html .= $orientation ? sprintf('<pagebreak orientation="%s">', $orientation) : '<pagebreak>';
            }

            $html .= $this->renderTemplate($page['template'], $page['data']);
        }

        return $this->outputPdf($html, $options);
    }

    public function renderHtml(string $html, array $options = []): string
    {
        return $this->outputPdf($html, $options);
    }

    private function renderTemplate(string $template, array $data): string
    {
        $path = $this->resolveTemplate($template);

        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('La plantilla "%s" no existe.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private function resolveTemplate(string $template): string
    {
        if ($template === '') {
            throw new InvalidArgumentException('La plantilla no puede estar vacía.');
        }

        if ($template[0] === '/' || str_starts_with($template, BASE_PATH)) {
            return $template;
        }

        return rtrim($this->basePath, '/\\') . '/' . ltrim($template, '/');
    }

    private function outputPdf(string $html, array $options): string
    {
        $config = self::DEFAULT_CONFIG;
        if (isset($options['mpdf']) && is_array($options['mpdf'])) {
            $config = array_replace($config, $options['mpdf']);
        }

        $mpdf = new Mpdf($config);

        if (!empty($options['css'])) {
            $cssPath = $this->resolveTemplate($options['css']);
            if (!is_file($cssPath)) {
                throw new InvalidArgumentException(sprintf('El archivo CSS "%s" no existe.', $options['css']));
            }

            $stylesheet = file_get_contents($cssPath);
            if ($stylesheet === false) {
                throw new InvalidArgumentException(sprintf('No se pudo leer el CSS "%s".', $cssPath));
            }

            $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        }

        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        $filename = $options['filename'] ?? 'documento.pdf';
        $destination = PdfDestinationNormalizer::normalize($options['destination'] ?? 'I');

        return $mpdf->Output($filename, $destination);
    }
}
