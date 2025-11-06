<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}
require_once BASE_PATH . '/helpers/InformesHelper.php';

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\ReglaController;
use Helpers\InformesHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$billingController = new BillingController($pdo);
$pacienteService = new PacienteService($pdo);

$mes = $_GET['mes'] ?? null;
$facturas = $billingController->obtenerFacturasDisponibles();


$pacientesCache = [];
$datosCache = [];
$filtros = ['mes' => $mes];

$consolidado = InformesHelper::obtenerConsolidadoFiltrado($facturas, $filtros, $billingController, $pacienteService, $pacientesCache, $datosCache);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Consolidado IESS");


// Encabezados
// Nuevos encabezados
$headers = [
    'A1' => '1',
    'B1' => '2',
    'C1' => '3',
    'D1' => '4',
    'E1' => '5',
    'F1' => '6',
    'G1' => '7',
    'H1' => '8',
    'I1' => '9',
    'J1' => '10',
    'K1' => '11',
    'L1' => '12',
    'M1' => '13',
    'N1' => '14',
    'O1' => '15',
    'P1' => '16',
    'Q1' => '17',
    'R1' => '18',
    'S1' => '19',
    'T1' => '20',
    'U1' => '21',
    'V1' => '22',
    'W1' => '23',
    'X1' => '24',
    'Y1' => '25',
    'Z1' => '26',
    'AA1' => '27',
    'AB1' => '28',
    'AC1' => '29',
    'AD1' => '30',
    'AE1' => '31',
    'AF1' => '32',
    'AG1' => '33',
    'AH1' => '34',
    'AI1' => '35',
    'AJ1' => '36',
    'AK1' => '37',
    'AL1' => '38',
    'AM1' => '39',
    'AN1' => '40',
    'AO1' => '41',
    'AP1' => '42',
    'AQ1' => '43',
    'AR1' => '44',
];
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
    $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle('thin');
}

$row = 2;
$cols = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
    'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'
];

// Logging y loop principal
error_log("Consolidado tiene " . count($consolidado) . " meses: " . implode(', ', array_keys($consolidado)));
foreach ($consolidado as $mes => $pacientesDelMes) {
    error_log("Procesando mes $mes - pacientes: " . count($pacientesDelMes));
    foreach ($pacientesDelMes as $factura) {
        $formId = $factura['form_id'] ?? null;
        error_log("Intentando procesar form_id: " . print_r($formId, true));
        if (!$formId) {
            error_log("Paciente sin form_id: " . print_r($factura, true));
            continue;
        }
        if (!isset($datosCache[$formId])) {
            $datosCache[$formId] = $billingController->obtenerDatos($formId);
            if (empty($datosCache[$formId])) {
                error_log("Sin datos para form_id: $formId");
                continue;
            }
        }
        $data = $datosCache[$formId];
        $pacienteInfo = $pacientesCache[$formId] ?? ($data['paciente'] ?? []);
        $nombrePaciente = trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '') . ' ' . ($pacienteInfo['fname'] ?? '') . ' ' . ($pacienteInfo['mname'] ?? ''));
        error_log("Fila para paciente: $nombrePaciente, form_id: $formId, sexo: " . ($pacienteInfo['sexo'] ?? '--'));
        $sexo = isset($pacienteInfo['sexo']) ? strtoupper(substr($pacienteInfo['sexo'], 0, 1)) : '--';
        $formDetails = $data['formulario'] ?? [];
        $formDetails['fecha_inicio'] = $data['protocoloExtendido']['fecha_inicio'] ?? '';
        $formDetails['fecha_fin'] = $data['protocoloExtendido']['fecha_fin'] ?? ($formDetails['fecha_fin'] ?? '');
        $fechaISO = $formDetails['fecha_inicio'] ?? '';
        $cedula = $pacienteInfo['cedula'] ?? '';
        $periodo = $fechaISO ? date('Y-m', strtotime($fechaISO)) : '';
        // Diagnósticos
        $formDetails['diagnosticos'] = isset($data['protocoloExtendido']['diagnosticos'])
            ? (is_array($data['protocoloExtendido']['diagnosticos']) ? $data['protocoloExtendido']['diagnosticos'] : json_decode($data['protocoloExtendido']['diagnosticos'], true))
            : [];
        $formDetails['diagnostico1'] = $formDetails['diagnosticos'][0]['idDiagnostico'] ?? '';
        $formDetails['diagnostico2'] = $formDetails['diagnosticos'][1]['idDiagnostico'] ?? '';

        // Inicializar controlador de reglas clínicas
        $reglaController = new ReglaController($pdo);
        $contexto = [
            'afiliacion' => $pacienteInfo['afiliacion'] ?? '',
            'procedimiento' => $data['procedimientos'][0]['proc_detalle'] ?? '',
            'edad' => isset($pacienteInfo['fecha_nacimiento']) ? date_diff(date_create($pacienteInfo['fecha_nacimiento']), date_create('today'))->y : null,
        ];
        $accionesReglas = $reglaController->evaluar($contexto);

        $derivacion = $billingController->obtenerDerivacionPorFormId($formId);
        $codigoDerivacion = $derivacion['cod_derivacion'] ?? '';
        $referido = $derivacion['referido'] ?? '';
        $diagnosticoStr = $derivacion['diagnostico'] ?? '';
        if ($diagnosticoStr) {
            $cie10 = implode('; ', array_map(fn($d) => explode(' -', trim($d))[0], explode(';', $diagnosticoStr)));
        } else {
            $cie10 = '';
        }
        $abreviaturaAfiliacion = $billingController->abreviarAfiliacion($pacienteInfo['afiliacion'] ?? '');
        $diagnosticoPrincipal = $formDetails['diagnostico1'] ?? '';
        $diagnosticoSecundario = $formDetails['diagnostico2'] ?? '';

        // === Procedimientos ===
        foreach ($data['procedimientos'] as $index => $p) {
            $descripcion = $p['proc_detalle'] ?? '';
            $precioBase = (float)($p['proc_precio'] ?? 0);
            $codigo = $p['proc_codigo'] ?? '';

            // Lógica especial para el código 67036 (duplicar fila y 62.5%)
            if ($codigo === '67036') {
                $porcentaje = 0.625;
                $valorUnitario = $precioBase;
                $total = $valorUnitario * $porcentaje;
                for ($dup = 0; $dup < 2; $dup++) {
                    $colVals = [
                        '0000000135', // A
                        '000002',     // B
                        date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C
                        $abreviaturaAfiliacion, // D
                        $pacienteInfo['hc_number'] ?? '', // E
                        $nombrePaciente,   // F
                        $sexo,             // G
                        $pacienteInfo['fecha_nacimiento'] ?? '', // H
                        $contexto['edad'] ?? '', // I
                        'PRO/INTERV',      // J
                        $codigo,           // K
                        $descripcion,      // L
                        $cie10, // M
                        '', '',            // N, O
                        '1',               // P
                        number_format($total, 2), // Q (unitario sin %)
                        '',                // R
                        'T',               // S
                        $pacienteInfo['hc_number'] ?? '', // T
                        $nombrePaciente,   // U
                        '',                // V
                        $codigoDerivacion ?? '', // W
                        '1',               // X
                        'D',               // Y
                        '', '', '', '', // Z, AA, AB, AC
                        '0',               // AD
                        '0',               // AE
                        number_format($total, 2), // AF (total 62.5%)
                        '',                // AG
                        date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH
                        date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI
                        '',                // AJ
                        'NO',              // AK
                        '',                // AL
                        'NO',              // AM
                        'P',               // AN
                        '1',               // AO
                        '', '',            // AP, AQ
                        'F',               // AR
                    ];
                    foreach ($cols as $i => $col) {
                        $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    foreach ($cols as $col) {
                        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                    }
                    $row++;
                }
                continue;
            }

            // Lógica normal
            if ($index === 0 || stripos($descripcion, 'separado') !== false) {
                $porcentaje = 1;
            } else {
                $porcentaje = 0.5;
            }
            $valorUnitario = $precioBase;
            $total = $valorUnitario * $porcentaje;

            $colVals = [
                '0000000135',        // A: Número de protocolo/referencia
                '000002',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                'PRO/INTERV',        // J: Tipo prestación
                $codigo, // K: Código procedimiento
                $descripcion,// L: Descripción procedimiento
                $cie10,   // M: Diagnóstico principal (CIE10)
                '',                  // N: Diagnóstico secundario
                '',                  // O: Diagnóstico 3
                '1',                 // P: Cantidad
                number_format($valorUnitario, 2), // Q: Valor unitario **con porcentaje**
                '',                  // R: Vacío/fijo
                'T',                 // S: Tipo pago
                $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                $nombrePaciente,     // U: Nombre (repetido)
                '',                  // V: Vacío
                $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                '1',                 // X: Ítem adicional/fijo
                'D',                 // Y: Movimiento
                '', '', '', '',      // Z, AA, AB, AC: vacíos
                '0',                 // AD: IVA
                '0',                 // AE: Descuento
                number_format($total, 2), // AF: Total **con porcentaje**
                '',                  // AG: Vacío
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                '',                  // AJ: Vacío
                'NO',                // AK: Emergencia
                '',                  // AL: Vacío
                'NO',                // AM: Reingreso
                'P',                 // AN: Estado prestación
                '1',                 // AO: Número de medico
                '', '',              // AP, AQ: vacíos
                'F',                 // AR: ¿Facturado?
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        if (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante'])) {
            foreach ($data['procedimientos'] as $index => $p) {
                $descripcion = $p['proc_detalle'] ?? '';
                $precio = (float)$p['proc_precio'];
                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                $valorUnitario = $precio * $porcentaje;
                $total = $valorUnitario;
                $colVals = [
                    '0000000135',        // A: Número de protocolo/referencia
                    '000002',            // B: Ítem
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                    $abreviaturaAfiliacion, // D: Día
                    $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                    $nombrePaciente,     // F: Nombre completo paciente
                    $sexo,               // G: Sexo
                    $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                    $contexto['edad'] ?? '',  // I: Edad
                    'PRO/INTERV',        // J: Tipo prestación
                    $p['proc_codigo'] ?? '', // K: Código procedimiento
                    $p['proc_detalle'] ?? '',// L: Descripción procedimiento
                    $cie10,   // M: Diagnóstico principal (CIE10)
                    '',                  // N: Diagnóstico secundario
                    '',                  // O: Diagnóstico 3
                    '1',                 // P: Cantidad
                    number_format($valorUnitario, 2), // Q: Valor unitario **con porcentaje**
                    '',                  // R: Vacío/fijo
                    'T',                 // S: Tipo pago
                    $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                    $nombrePaciente,     // U: Nombre (repetido)
                    '',                  // V: Vacío
                    'CPPSSG-27-05-2024-RPC-SFGG-208', // W: Autorización/referencia (ajustar)
                    '1',                 // X: Ítem adicional/fijo
                    'D',                 // Y: Movimiento
                    '', '', '', '',      // Z, AA, AB, AC: vacíos
                    '0',                 // AD: IVA
                    '0',                 // AE: Descuento
                    number_format($total, 2), // AF: Total **con porcentaje**
                    '',                  // AG: Vacío
                    date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                    '',                  // AJ: Vacío
                    'NO',                // AK: Emergencia
                    '',                  // AL: Vacío
                    'NO',                // AM: Reingreso
                    'P',                 // AN: Estado prestación
                    '1',                 // AO: Número de prestación
                    '', '',              // AP, AQ: vacíos
                    'F',                 // AR: ¿Facturado?
                ];
                foreach ($cols as $i => $col) {
                    $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                foreach ($cols as $col) {
                    $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                }
                $row++;
            }
        }

// === ANESTESIA en formato IESS 44 columnas ===
        $cols = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'
        ];

// -- Anestesia por procedimiento principal (si aplica) --
        $codigoAnestesia = $data['procedimientos'][0]['proc_codigo'] ?? '';
        $precioReal = $codigoAnestesia ? $billingController->obtenerValorAnestesia($codigoAnestesia) : null;


        if (!empty($data['procedimientos'][0])) {
            $p = $data['procedimientos'][0];
            $precio = (float)$p['proc_precio'];
            $valorUnitario = $precioReal ?? $precio;
            $cantidad = 1;
            $total = $valorUnitario * $cantidad;
            $colVals = [
                '0000000135',        // A: Número de protocolo/referencia
                '000002',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                'PRO/INTERV',        // J: Tipo prestación
                $p['proc_codigo'] ?? '', // K: Código procedimiento
                $p['proc_detalle'] ?? '',// L: Descripción procedimiento
                $cie10,   // M: Diagnóstico principal (CIE10)
                '',                  // N: Diagnóstico secundario
                '',                  // O: Diagnóstico 3
                '1',                 // P: Cantidad
                number_format($valorUnitario, 2), // Q: Valor unitario
                '',                  // R: Vacío/fijo
                'T',                 // S: Tipo pago
                $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                $nombrePaciente,     // U: Nombre (repetido)
                '',                  // V: Vacío
                $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                '1',                 // X: Ítem adicional/fijo
                'D',                 // Y: Movimiento
                '', '', '', '',      // Z, AA, AB, AC: vacíos
                '0',                 // AD: IVA
                '0',                 // AE: Descuento
                number_format($total, 2), // AF: Total
                '',                  // AG: Vacío
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                '',                  // AJ: Vacío
                'NO',                // AK: Emergencia
                '',                  // AL: Vacío
                'NO',                // AM: Reingreso
                'P',                 // AN: Estado prestación
                '3',                 // AO: Número de medico
                '', '',              // AP, AQ: vacíos
                'F',                 // AR: ¿Facturado?
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

// -- Anestesia agrupada (foreach) --
        foreach ($data['anestesia'] as $a) {
            $codigo = $a['codigo'];
            $descripcion = $a['nombre'];
            $cantidad = (float)$a['tiempo'];
            $valorUnitario = (float)$a['valor2'];
            $total = $cantidad * $valorUnitario;
            $colVals = [
                '0000000135',        // A: Número de protocolo/referencia
                '000002',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                'PRO/INTERV',        // J: Tipo prestación
                $codigo,             // K: Código procedimiento (anestesia)
                $descripcion,        // L: Descripción procedimiento (anestesia)
                $cie10,   // M: Diagnóstico principal (CIE10)
                '',                  // N: Diagnóstico secundario
                '',                  // O: Diagnóstico 3
                $cantidad,           // P: Cantidad
                number_format($valorUnitario, 2), // Q: Valor unitario
                '',                  // R: Vacío/fijo
                'T',                 // S: Tipo pago
                $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                $nombrePaciente,     // U: Nombre (repetido)
                '',                  // V: Vacío
                $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                '1',                 // X: Ítem adicional/fijo
                'D',                 // Y: Movimiento
                '', '', '', '',      // Z, AA, AB, AC: vacíos
                '0',                 // AD: IVA
                '0',                 // AE: Descuento
                number_format($total, 2), // AF: Total
                '',                  // AG: Vacío
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                '',                  // AJ: Vacío
                'NO',                // AK: Emergencia
                '',                  // AL: Vacío
                'NO',                // AM: Reingreso
                'P',                 // AN: Estado prestación
                '1',                 // AO: Número de prestación
                '', '',              // AP, AQ: vacíos
                'F',                 // AR: ¿Facturado?
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

// === FARMACIA E INSUMOS EN FORMATO IESS (44 COLUMNAS) ===
        $cols = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'
        ];

        $fuenteDatos = [
            ['grupo' => 'FARMACIA', 'items' => array_merge($data['medicamentos'], $data['oxigeno'])],
            ['grupo' => 'INSUMOS', 'items' => $data['insumos']],
        ];
        foreach ($fuenteDatos as $bloque) {
            $grupo = $bloque['grupo'];
            foreach ($bloque['items'] as $item) {
                $descripcion = $item['nombre'] ?? $item['detalle'] ?? '';
                $excluir = false;
                foreach ($accionesReglas as $accion) {
                    if ($accion['tipo'] === 'excluir_insumo' && stripos($descripcion, $accion['parametro']) !== false) {
                        $excluir = true;
                        break;
                    }
                }
                if ($excluir) {
                    continue;
                }
                $codigo = $item['codigo'] ?? '';
                if (isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2'])) {
                    // Este es oxígeno
                    $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                    $valorUnitario = (float)$item['valor2'];
                } else {
                    $cantidad = $item['cantidad'] ?? 1;
                    $valorUnitario = $item['precio'] ?? 0;
                }
                $subtotal = $valorUnitario * $cantidad;
                $bodega = 1;
                $abreviatura = ($grupo === 'FARMACIA') ? 'M' : 'I';
                $iva = ($grupo === 'FARMACIA') ? 0 : 1;
                $total = $subtotal;     // + ($iva ? $subtotal * 0.12 : 0);

                $colVals = [
                    '0000000135',        // A: Número de protocolo/referencia
                    '000002',            // B: Ítem
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                    $abreviaturaAfiliacion, // D: Día
                    $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                    $nombrePaciente,     // F: Nombre completo paciente
                    $sexo,               // G: Sexo
                    $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                    $contexto['edad'] ?? '',  // I: Edad
                    'PRO/INTERV',              // J: Tipo prestación (FARMACIA/INSUMOS)
                    ltrim($codigo, '0'),   // K: Código insumo/fármaco SIN ceros a la izquierda
                    $descripcion,        // L: Descripción insumo/fármaco
                    $cie10,   // M: Diagnóstico principal (CIE10)
                    '',                  // N: Diagnóstico secundario
                    '',                  // O: Diagnóstico 3
                    $cantidad,           // P: Cantidad
                    number_format($valorUnitario, 2), // Q: Valor unitario
                    '',                  // R: Vacío/fijo
                    'T',                 // S: Tipo pago
                    $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                    $nombrePaciente,     // U: Nombre (repetido)
                    '',                  // V: Vacío
                    $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                    '1',                 // X: Ítem adicional/fijo
                    'D',                 // Y: Movimiento
                    '', '', '', '',      // Z, AA, AB, AC: vacíos
                    $iva,                // AD: IVA
                    '0',                 // AE: Descuento
                    number_format($total, 2), // AF: Total (con IVA si aplica)
                    '',                  // AG: Vacío
                    date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                    '',                  // AJ: Vacío
                    'NO',                // AK: Emergencia
                    '',                  // AL: Vacío
                    'NO',                // AM: Reingreso
                    $abreviatura,                 // AN: Estado prestación
                    '',                 // AO: Número de prestación
                    '', '',              // AP, AQ: vacíos
                    'F',                 // AR: ¿Facturado?
                ];
                foreach ($cols as $i => $col) {
                    $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                foreach ($cols as $col) {
                    $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                }
                $row++;
            }
        }

// === Servicios institucionales y equipos especializados en formato IESS 44 columnas ===
        foreach ($data['derechos'] as $servicio) {
            $codigo = $servicio['codigo'];
            $descripcion = $servicio['detalle'];
            $cantidad = $servicio['cantidad'];
            $valorUnitario = $servicio['precio_afiliacion'];
            $subtotal = $valorUnitario * $cantidad;
            $bodega = 0;
            $iva = 0;
            $total = $subtotal;
            $porcentajePago = 100;

            $colVals = [
                '0000000135',        // A: Número de protocolo/referencia
                '000002',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                $pacienteInfo['fecha_nacimiento'] ?? '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                'PRO/INTERV', // J: Tipo prestación
                $codigo,             // K: Código servicio
                $descripcion,        // L: Descripción servicio
                $cie10,   // M: Diagnóstico principal (CIE10)
                '',                  // N: Diagnóstico secundario
                '',                  // O: Diagnóstico 3
                $cantidad,           // P: Cantidad
                number_format($valorUnitario, 2), // Q: Valor unitario
                '',                  // R: Vacío/fijo
                'T',                 // S: Tipo pago
                $pacienteInfo['hc_number'] ?? '',      // T: Cédula (repetido)
                $nombrePaciente,     // U: Nombre (repetido)
                '',                  // V: Vacío
                $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                '1',                 // X: Ítem adicional/fijo
                'D',                 // Y: Movimiento
                '', '', '', '',      // Z, AA, AB, AC: vacíos
                $iva,                // AD: IVA
                '0',                 // AE: Descuento
                number_format($total, 2), // AF: Total
                '',                  // AG: Vacío
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')), // AH: Fecha ingreso
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // AI: Fecha egreso
                '',                  // AJ: Vacío
                'NO',                // AK: Emergencia
                '',                  // AL: Vacío
                'NO',                // AM: Reingreso
                'P',                 // AN: Estado prestación
                '',                 // AO: Número de prestación
                '', '',              // AP, AQ: vacíos
                'F',                 // AR: ¿Facturado?
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }
    }
}

// Headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="consolidado_iess.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;