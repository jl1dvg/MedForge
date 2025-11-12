<?php

use Modules\Reporting\Services\Definitions\ArrayPdfTemplateDefinition;

return [
    new ArrayPdfTemplateDefinition(
        'cobertura-ecuasanitas-form',
        'aseguradoras/cobertura_ecuasanitas.pdf',
        [
            // Página 1
            'hc_number' => ['x' => 140, 'y' => 96, 'page' => 1],
            'pacienteNombreCompleto' => ['x' => 50, 'y' => 88, 'width' => 100, 'multiline' => true, 'page' => 1],
            'pacienteNombreCompletoLinea2' => ['x' => 50, 'y' => 96, 'width' => 100, 'multiline' => true, 'page' => 1],
            'pacienteEdad' => ['x' => 190, 'y' => 96, 'page' => 1],
            'diagnosticoListaTexto' => ['x' => 83, 'y' => 145, 'width' => 140, 'multiline' => true, 'line_height' => 4.5, 'page' => 1],
            'diagnosticoListaTextoLinea2' => ['x' => 83, 'y' => 152, 'width' => 140, 'multiline' => true, 'line_height' => 4.5, 'page' => 1],
            'consultaTexto' => ['x' => 15, 'y' => 120, 'width' => 140, 'multiline' => true, 'line_height' => 4.5, 'page' => 1],
            'diagnosticoLista' => [
                'x' => 40,
                'y' => 186,
                'width' => 460,
                'line_height' => 5,
                'multiline' => true,
            ],

            // Si necesitas algo en la página 2, declaras con 'page' => 2:
            // 'doctor' => ['x' => 125, 'y' => 250, 'page' => 2],
            // 'fechaActual' => ['x' => 125, 'y' => 260, 'page' => 2],


        ],
        [
            'font_family' => 'helvetica',
            'font_size' => 6,
            'line_height' => 3,
            // 👇 Hace que el renderer importe todas las páginas, aunque no tengan campos
            'template_pages' => 2,
            'aliases' => [
                'pacienteNombreCompletoLinea2' => 'pacienteNombreCompleto',
                'diagnosticoListaTextoLinea2' => 'diagnosticoListaTexto',
            ],
            'defaults' => [
                'diagnosticoLista' => [],
                'consultaTexto' => '',
            ],
        ]
    ),
];
