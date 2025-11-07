<?php

use Modules\Reporting\Services\Definitions\ArraySolicitudTemplateDefinition;

return [
    new ArraySolicitudTemplateDefinition(
        'cobertura-ecuasanitas',
        ['cobertura_ecuasanitas'],
        [
            'css' => dirname(__DIR__, 2) . '/Templates/assets/pdf.css',
            'orientation' => 'P',
            'filename_pattern' => 'cobertura_ecuasanitas_%2$s_%3$s.pdf',
            'report' => ['slug' => 'cobertura-ecuasanitas-form'],
            // 👇 NUEVO: vistas a anexar luego del PDF fijo
            'append_views' => ['007'],
            'orientations' => [
                'referencia' => 'P',
            ],
        ],
        ['ecuasanitas', 'ECUASANITAS', 'Ecuasanitas'] // palabras clave de coincidencia
    ),
    new ArraySolicitudTemplateDefinition(
        'cobertura',
        ['007', '010', 'referencia'],
        [
            'css' => dirname(__DIR__, 2) . '/Templates/assets/pdf.css',
            'orientations' => [
                'referencia' => 'P',
            ],
            'orientation' => 'P',
            'filename_pattern' => 'cobertura_%2$s_%3$s.pdf',
        ],
        ['*'],
        true
    ),
];
