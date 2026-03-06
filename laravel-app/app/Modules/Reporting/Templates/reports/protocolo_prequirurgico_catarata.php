<?php

$layout = __DIR__ . '/../layouts/base.php';
$data = is_array($data ?? null) ? $data : [];
$fechaEmision = trim((string)($data['fecha_emision'] ?? date('Y-m-d')));
$headerImage = trim((string)($data['header_image_data_uri'] ?? ''));
$footerImage = trim((string)($data['footer_image_data_uri'] ?? ''));
$firmante = is_array($data['firmante'] ?? null) ? $data['firmante'] : [];

$resolveAsset = static function (?string $path): string {
    $path = trim((string)($path ?? ''));
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, 'data:image/')) {
        return $path;
    }
    if (function_exists('asset')) {
        return (string)asset($path);
    }
    return $path;
};

$firmaPath = $resolveAsset($firmante['firma'] ?? '');
$signaturePath = $resolveAsset($firmante['signature_path'] ?? '');

ob_start();
?>
    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .protocolo-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 210mm;
            z-index: 3;
        }

        .protocolo-header img {
            width: 210mm;
            height: 24mm;
            object-fit: cover;
            display: block;
        }

        .protocolo-body {
            position: relative;
            z-index: 2;
            padding: 26mm 12mm 24mm 12mm;
        }

        .protocolo-wrap {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #111;
        }

        .protocolo-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 14px 0;
        }

        .protocolo-intro {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        .protocolo-list {
            margin: 0 0 14px 16px;
            padding: 0;
        }

        .protocolo-list li {
            margin-bottom: 8px;
            text-align: justify;
            page-break-inside: avoid;
        }

        .protocolo-foot {
            margin-top: 22px;
            margin-bottom: 8px;
        }

        .protocolo-firmas {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .protocolo-firma-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding-right: 6px;
        }

        .protocolo-firma-col:last-child {
            padding-right: 0;
            padding-left: 6px;
        }

        .protocolo-firma-col img {
            max-width: 100%;
            max-height: 24mm;
            display: block;
            margin: 0 auto 2px auto;
        }

        .protocolo-firma-label {
            font-size: 8.5pt;
            color: #333;
        }

        .protocolo-meta {
            margin-top: 26px;
            font-size: 9pt;
            color: #444;
        }

        .protocolo-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            width: 210mm;
            z-index: 3;
        }

        .protocolo-footer img {
            width: 210mm;
            height: 22mm;
            object-fit: cover;
            display: block;
        }
    </style>

    <div class="protocolo-header">
        <?php if ($headerImage !== ''): ?>
            <img src="<?= htmlspecialchars($headerImage, ENT_QUOTES, 'UTF-8') ?>" alt="Encabezado protocolo">
        <?php endif; ?>
    </div>

    <div class="protocolo-body">
        <div class="protocolo-wrap">
            <br>
            <br>
            <p class="protocolo-title">PROTOCOLO PREQUIRURGICO IMAGENES PROCEDIMIENTO DE CATARATA + LIO</p>
            <p class="protocolo-intro">
                Los estudios de imágenes para la cirugía de catarata son fundamentales para asegurar el éxito visual y
                calcular el lente intraocular adecuado.
                Los exámenes que constan dentro del protocolo son:
            </p>

            <ul class="protocolo-list">
                <li>
                    <strong>TOPOGRAFIA CORNEAL COMPUTARIZADA, UNILATERAL:</strong>
                    determina la forma de la córnea y las curvas. Es un dato que pide el biómetro para hacer el cálculo
                    del lente.
                </li>
                <li>
                    <strong>RECUENTO DE CELULAS ENDOTELIALES:</strong>
                    el recuento de células endoteliales (normalmente 1.400-2.500 células/mm2) es un examen preoperatorio
                    esencial en cirugía de catarata para evaluar la salud corneal.
                    Entre 400-700 células/mm2 es el nivel crítico con riesgo de edema crónico.
                    La facoemulsificación causa una pérdida celular del 6% al 12%.
                    Determina cuántas células tiene la córnea por sus células internas en el endotelio, que es donde
                    trabaja el equipo de facoemulsificador;
                    si la córnea tiene bajo contaje celular se puede descompensar y en esos casos estaría contraindicada
                    la cirugía.
                </li>
                <li>
                    <strong>BIOMETRIA OCULAR (UNILATERAL):</strong>
                    es fundamental para calcular la potencia exacta de la lente intraocular antes de cirugías de
                    catarata o refractiva;
                    es necesaria para el cálculo del lente.
                </li>
                <li>
                    <strong>ULTRASONIDO DE SEGMENTO ANTERIOR, B SCAN DE INMERSION O BIOMICROSCOPIA DE ALTA
                        RESOLUCION:</strong>
                    ayuda en la planificación de cirugías, midiendo la distancia entre estructuras para la selección de
                    lentes intraoculares (LIO).
                </li>
                <li>
                    <strong>TOMOGRAFIA DE PRUEBAS PROVOCATIVAS MACULAR (AO):</strong>
                    es una prueba no invasiva crucial antes de la cirugía de cataratas para evaluar la salud de la
                    retina central,
                    permitiendo detectar condiciones subyacentes como degeneración macular, membranas epirretinianas o
                    edema que podrían limitar la visión postoperatoria.
                    La OCT evalúa si la mácula está sana, lo que ayuda a determinar si el paciente recuperará la visión
                    tras retirar la catarata.
                </li>
            </ul>

            <p class="protocolo-foot">Atentamente</p>
            <div class="protocolo-firmas">
                <div class="protocolo-firma-col">
                    <?php if ($signaturePath !== ''): ?>
                        <img src="<?= htmlspecialchars($signaturePath, ENT_QUOTES, 'UTF-8') ?>"
                             alt="Firma (signature_path)">
                    <?php endif; ?>
                    <?php if ($firmaPath !== ''): ?>
                        <img src="<?= htmlspecialchars($firmaPath, ENT_QUOTES, 'UTF-8') ?>" alt="Firma (campo firma)">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="protocolo-footer">
        <?php if ($footerImage !== ''): ?>
            <img src="<?= htmlspecialchars($footerImage, ENT_QUOTES, 'UTF-8') ?>" alt="Pie de pagina protocolo">
        <?php endif; ?>
    </div>
<?php
$content = ob_get_clean();
$title = 'Protocolo prequirurgico imagenes catarata';

include $layout;
