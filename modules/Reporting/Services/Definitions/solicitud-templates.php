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
            'report' => [
                'slug' => 'cobertura-ecuasanitas-form',
            ],
        ],
        ['ecuasanitas']
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
