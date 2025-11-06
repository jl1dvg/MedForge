<?php

use Modules\Reporting\Services\Definitions\ArrayPdfTemplateDefinition;

return [
    new ArrayPdfTemplateDefinition(
        'cobertura-ecuasanitas-form',
        'aseguradoras/cobertura_ecuasanitas.pdf',
        [
            'hc_number' => ['x' => 135, 'y' => 126],
            'pacienteNombreCompleto' => [
                'x' => 135,
                'y' => 138,
                'width' => 320,
                'line_height' => 5,
                'multiline' => true,
            ],
            'pacienteFechaNacimientoFormateada' => ['x' => 135, 'y' => 150],
            'diagnosticoLista' => [
                'x' => 40,
                'y' => 186,
                'width' => 460,
                'line_height' => 5,
                'multiline' => true,
            ],
        ],
        [
            'font_family' => 'helvetica',
            'font_size' => 10,
            'line_height' => 5,
            'defaults' => [
                'diagnosticoLista' => [],
            ],
        ]
    ),
];
