<?php
require_once __DIR__ . '/../../bootstrap.php';

/** @var PDO $pdo */
global $pdo;

use Controllers\ReglaController;
use Controllers\BillingController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener form_id
$formIds = isset($_GET['form_id']) ? explode(',', $_GET['form_id']) : [];
if (empty($formIds)) {
    die("Falta el parámetro form_id.");
}

// Obtener datos desde variables globales
$billingController = new BillingController($pdo);
$datosFacturacionLote = [];

foreach ($formIds as $fid) {
    $datos = $billingController->obtenerDatos($fid);
    if ($datos) {
        $datosFacturacionLote[] = ['form_id' => $fid, 'data' => $datos];
    }
}

$fechasGlobales = $billingController->obtenerFechasIngresoYEgreso($formIds);
$fechaIngresoFormateadaGlobal = $fechasGlobales['ingreso'] ? date('d/m/Y', strtotime($fechasGlobales['ingreso'])) : '';
$fechaEgresoFormateadaGlobal = $fechasGlobales['egreso'] ? date('d/m/Y', strtotime($fechasGlobales['egreso'])) : '';

if (empty($datosFacturacionLote)) {
    die("No se encontró ninguna prefactura válida.");
}

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('IESS');

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
}
$row = 2;

$cols = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
    'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR'
];

foreach ($datosFacturacionLote as $bloque) {
    $formId = $bloque['form_id'];
    $data = $bloque['data'];

    // Acceso directo a los datos del paciente y formulario desde $data
    $pacienteInfo = $data['paciente'] ?? [];
    $nombrePaciente = $pacienteInfo['lname'] . ' ' . $pacienteInfo['lname2'] . ' ' . $pacienteInfo['fname'] . ' ' . $pacienteInfo['mname'];
    $sexo = isset($pacienteInfo['sexo']) ? strtoupper(substr($pacienteInfo['sexo'], 0, 1)) : '--';
    $formDetails = $data['formulario'] ?? [];
    $visita = $data['visita'] ?? [];
    $formDetails['fecha_inicio'] = $data['protocoloExtendido']['fecha_inicio'] ?? '';
    $fechaISO = $formDetails['fecha_inicio'] ?? '';
    $fecha = $fechaISO ? date('d-m-Y', strtotime($fechaISO)) : '';
    $cedula = $pacienteInfo['cedula'] ?? '';
    $periodo = date('Y-m', strtotime($fechaISO));

    // Agregar valores de protocoloExtendido para uso en el Excel
    $formDetails['diagnosticos'] = [];

    if (!empty($data['protocoloExtendido']) && !empty($data['protocoloExtendido']['diagnosticos'])) {
        $formDetails['diagnosticos'] = json_decode($data['protocoloExtendido']['diagnosticos'], true) ?? [];
    }
    $formDetails['diagnostico1'] = $formDetails['diagnosticos'][0]['idDiagnostico'] ?? '';

    $formDetails['diagnostico2'] = $formDetails['diagnosticos'][1]['idDiagnostico'] ?? '';

// Inicializar controlador de reglas clínicas
    $reglaController = new ReglaController($pdo);
    $billingController = new BillingController($pdo);

// Preparar contexto para evaluación
    $contexto = [
        'afiliacion' => $pacienteInfo['afiliacion'] ?? '',
        'procedimiento' => $data['procedimientos'][0]['proc_detalle'] ?? '',
        'edad' => isset($pacienteInfo['fecha_nacimiento']) ? date_diff(date_create($pacienteInfo['fecha_nacimiento']), date_create('today'))->y : null,
    ];

// Evaluar reglas clínicas activas
    $accionesReglas = $reglaController->evaluar($contexto);
    $derivacion = $billingController->obtenerDerivacionPorFormId($formId);
    $codigoDerivacion = $derivacion['cod_derivacion'] ?? '';
    $referido = $derivacion['referido'] ?? '';
    $diagnosticoStr = $derivacion['diagnostico'] ?? '';
    if ($diagnosticoStr) {
        // Toma solo el primer diagnóstico y solo el CIE10 (antes del primer espacio o guion)
        $primerDiagnostico = explode(';', $diagnosticoStr)[0];
        $cie10 = trim(explode(' ', explode('-', $primerDiagnostico)[0])[0]);
    } else {
        $cie10 = '';
    }
    $abreviaturaAfiliacion = $billingController->abreviarAfiliacion($pacienteInfo['afiliacion'] ?? '');

    $diagnosticoPrincipal = $formDetails['diagnostico1'] ?? '';
    $diagnosticoSecundario = $formDetails['diagnostico2'] ?? '';

    // Determinar si es cirugía
    $esCirugia = $billingController->esCirugiaPorFormId($formId);
    //$esCirugia = !empty($data['protocoloExtendido']['cirugia']) && $data['protocoloExtendido']['cirugia'] === 'SI';


    usort($data['procedimientos'], function ($a, $b) {
        return (float)$b['proc_precio'] <=> (float)$a['proc_precio'];
    });

    $tiene67036 = false;
    foreach ($data['procedimientos'] as $p) {
        if (($p['proc_codigo'] ?? '') === '67036') {
            $tiene67036 = true;
            break;
        }
    }

    foreach ($data['procedimientos'] as $index => $p) {
        //echo '<pre>' . var_dump($data['visita']) . '</pre>';
        $descripcion = $p['proc_detalle'] ?? '';
        $precioBase = (float)($p['proc_precio'] ?? 0);
        $codigo = $p['proc_codigo'] ?? '';
        $fechaVisitaCruda = $data['visita']['fecha'] ?? null;
        $fecha = (!empty($fechaVisitaCruda) && $fechaVisitaCruda !== '0000-00-00')
            ? date('d/m/Y', strtotime($fechaVisitaCruda))
            : '';

        $fechaValida = $formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? null;
        $fechaFormateada = (!empty($fechaValida) && $fechaValida !== '0000-00-00')
            ? date('d/m/Y', strtotime($fechaValida))
            : '';

        $fechaFacturacion = $esCirugia ? $fechaFormateada : $fecha;

        // Lógica especial para el código 67036 (duplicar fila y 62.5%)
        if ($codigo === '67036') {
            $porcentaje = 0.625;
            $valorUnitario = $precioBase;
            $total = $valorUnitario * $porcentaje;
            for ($dup = 0; $dup < 2; $dup++) {
                $colVals = [
                    '0000000135', // A
                    '',     // B
                    $fechaFacturacion,
                    $abreviaturaAfiliacion, // D
                    $pacienteInfo['hc_number'] ?? '', // E
                    $nombrePaciente,   // F
                    $sexo,             // G
                    !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H
                    $contexto['edad'] ?? '', // I
                    $esCirugia ? 'PRO/INTERV' : 'IMAGEN',      // J
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
                    '', '', '', '',    // Z, AA, AB, AC
                    '0',               // AD
                    '0',               // AE
                    number_format($total, 2), // AF (total 62.5%)
                    '',                // AG
                    $fechaIngresoFormateadaGlobal,
                    $fechaEgresoFormateadaGlobal, // AH, AI
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
                $row++;
            }
            continue;
        }

        // Lógica normal
        $porcentaje = ($tiene67036) ? 0.5 : (($index === 0 || stripos($descripcion, 'separado') !== false) ? 1 : 0.5);
        $valorUnitario = $precioBase;
        $total = $valorUnitario * $porcentaje;

        // Evitar duplicados en casos que no son cirugías
        if (!$esCirugia && $index > 0) {
            continue;
        }
        $colVals = [
            '0000000135',        // A: Número de protocolo/referencia
            '',            // B: Ítem
            $fechaFacturacion,
            $abreviaturaAfiliacion, // D: Día
            $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
            $nombrePaciente,     // F: Nombre completo paciente
            $sexo,               // G: Sexo
            !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
            $contexto['edad'] ?? '',  // I: Edad
            $esCirugia ? 'PRO/INTERV' : 'IMAGEN',        // J: Tipo prestación
            $codigo, // K: Código procedimiento
            $descripcion,// L: Descripción procedimiento
            $cie10,   // M: Diagnóstico principal (CIE10)
            '',                  // N: Diagnóstico secundario
            '',                  // O: Diagnóstico 3
            '1',                 // P: Cantidad
            number_format($total, 2), // Q: Valor unitario **con porcentaje**
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
            $fechaIngresoFormateadaGlobal, // AH: Fecha ingreso
            $fechaEgresoFormateadaGlobal, // AI: Fecha egreso
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
        }
        $row++;
    }

    if (!$tiene67036 && (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante']))) {
        foreach ($data['procedimientos'] as $index => $p) {
            $descripcion = $p['proc_detalle'] ?? '';
            $precio = (float)$p['proc_precio'];
            $porcentaje = ($index === 0) ? 0.2 : 0.1;
            $valorUnitario = $precio * $porcentaje;
            $total = $valorUnitario;

            $colVals = [
                '0000000135',        // A: Número de protocolo/referencia
                '',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                $esCirugia ? 'PRO/INTERV' : 'IMAGEN',        // J: Tipo prestación
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
                $codigoDerivacion ?? '', // W: Autorización/referencia (ajustar)
                '1',                 // X: Ítem adicional/fijo
                'D',                 // Y: Movimiento
                '', '', '', '',      // Z, AA, AB, AC: vacíos
                '0',                 // AD: IVA
                '0',                 // AE: Descuento
                number_format($total, 2), // AF: Total **con porcentaje**
                '',                  // AG: Vacío
                $fechaIngresoFormateadaGlobal,
                $fechaEgresoFormateadaGlobal,
                '',                  // AJ: Vacío
                'NO',                // AK: Emergencia
                '',                  // AL: Vacío
                'NO',                // AM: Reingreso
                'P',                 // AN: Estado prestación
                '3',                 // AO: Número de prestación
                '', '',              // AP, AQ: vacíos
                'F',                 // AR: ¿Facturado?
            ];

            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
    $precioReal = $codigoAnestesia ? $GLOBALS['controller']->obtenerValorAnestesia($codigoAnestesia) : null;
    $esCirugia = $billingController->esCirugiaPorFormId($formId);

    if ($esCirugia && !empty($data['procedimientos']) && isset($data['procedimientos'][0]['proc_codigo'])) {
        $p = $data['procedimientos'][0];
        $precio = (float)$p['proc_precio'];
        $valorUnitario = $precioReal ?? $precio;
        $cantidad = 1;
        $total = $valorUnitario * $cantidad;

        $colVals = [
            '0000000135',        // A: Número de protocolo/referencia
            '',            // B: Ítem
            date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
            $abreviaturaAfiliacion, // D: Día
            $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
            $nombrePaciente,     // F: Nombre completo paciente
            $sexo,               // G: Sexo
            !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
            $contexto['edad'] ?? '',  // I: Edad
            $esCirugia ? 'PRO/INTERV' : 'IMAGEN',        // J: Tipo prestación
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
            $fechaIngresoFormateadaGlobal,
            $fechaEgresoFormateadaGlobal,
            '',                  // AJ: Vacío
            'NO',                // AK: Emergencia
            '',                  // AL: Vacío
            'NO',                // AM: Reingreso
            'P',                 // AN: Estado prestación
            '6',                 // AO: Número de medico
            '', '',              // AP, AQ: vacíos
            'F',                 // AR: ¿Facturado?
        ];

        foreach ($cols as $i => $col) {
            $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
            '',            // B: Ítem
            date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
            $abreviaturaAfiliacion, // D: Día
            $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
            $nombrePaciente,     // F: Nombre completo paciente
            $sexo,               // G: Sexo
            !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
            $contexto['edad'] ?? '',  // I: Edad
            $esCirugia ? 'PRO/INTERV' : 'IMAGEN',        // J: Tipo prestación
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
            $fechaIngresoFormateadaGlobal,
            $fechaEgresoFormateadaGlobal,
            '',                  // AJ: Vacío
            'NO',                // AK: Emergencia
            '',                  // AL: Vacío
            'NO',                // AM: Reingreso
            'P',                 // AN: Estado prestación
            '6',                 // AO: Número de prestación
            '', '',              // AP, AQ: vacíos
            'F',                 // AR: ¿Facturado?
        ];

        foreach ($cols as $i => $col) {
            $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
            $descripcion = str_replace(["\r", "\n"], ' ', $item['nombre'] ?? $item['detalle'] ?? '');
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
                '',            // B: Ítem
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
                $abreviaturaAfiliacion, // D: Día
                $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
                $nombrePaciente,     // F: Nombre completo paciente
                $sexo,               // G: Sexo
                !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
                $contexto['edad'] ?? '',  // I: Edad
                $esCirugia ? 'PRO/INTERV' : 'IMAGEN',              // J: Tipo prestación (FARMACIA/INSUMOS)
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
                $fechaIngresoFormateadaGlobal,
                $fechaEgresoFormateadaGlobal,
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
            $row++;
        }
    }

// === Servicios institucionales y equipos especializados en formato IESS 44 columnas ===
    foreach ($data['derechos'] as $servicio) {
        $codigo = $servicio['codigo'];
        $descripcion = $servicio['detalle'];
        $cantidad = $servicio['cantidad'];
        $valorUnitario = $servicio['precio_afiliacion'];
        // Aplicar 2% adicional si el código es 395281
        // Nueva lógica para ciertos códigos
        if (
            ((int)$codigo >= 394200 && (int)$codigo < 394400)) {
            $valorUnitario *= 1.02;
            $valorUnitario -= 0.01;
        }
        if ($codigo === '395281') {
            $valorUnitario *= 1.02; // Aumenta 2%
        }
        $subtotal = $valorUnitario * $cantidad;
        $bodega = 0;
        $iva = 0;
        $total = $subtotal;
        $porcentajePago = 100;

        $colVals = [
            '0000000135',        // A: Número de protocolo/referencia
            '',            // B: Ítem
            date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), // C: Fecha egreso
            $abreviaturaAfiliacion, // D: Día
            $pacienteInfo['hc_number'] ?? '',      // E: Cédula paciente
            $nombrePaciente,     // F: Nombre completo paciente
            $sexo,               // G: Sexo
            !empty($pacienteInfo['fecha_nacimiento']) ? date('d/m/Y', strtotime($pacienteInfo['fecha_nacimiento'])) : '', // H: Fecha nacimiento
            $contexto['edad'] ?? '',  // I: Edad
            $esCirugia ? 'PRO/INTERV' : 'IMAGEN', // J: Tipo prestación
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
            $fechaIngresoFormateadaGlobal,
            $fechaEgresoFormateadaGlobal,
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
        $row++;
    }
}

// Reemplaza por esto:
$GLOBALS['spreadsheet'] = $spreadsheet;

// Descargar archivo
//file_put_contents(__DIR__ . '/debug_oxigeno.log', print_r($data['oxigeno'], true));
// Elimina esto:
//header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
//header('Content-Disposition: attachment; filename="' . $pacienteInfo['hc_number'] . '_' . $pacienteInfo['lname'] . '_' . $pacienteInfo['lname2'] . '_' . $pacienteInfo['fname'] . '_' . $pacienteInfo['mname'] . '.xlsx"');
//$writer = new Xlsx($spreadsheet);
//$writer->save('php://output');
//exit;