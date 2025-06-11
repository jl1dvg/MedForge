<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/InformesHelper.php';

use Controllers\BillingController;
use Controllers\PacienteController;
use Controllers\ReglaController;
use Helpers\InformesHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$billingController = new BillingController($pdo);
$pacienteController = new PacienteController($pdo);

$mes = $_GET['mes'] ?? null;
$facturas = $billingController->obtenerFacturasDisponibles();

$pacientesCache = [];
$datosCache = [];
$filtros = ['mes' => $mes];

$consolidado = InformesHelper::obtenerConsolidadoFiltrado($facturas, $filtros, $billingController, $pacienteController, $pacientesCache, $datosCache);

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

        $diagnosticoPrincipal = $formDetails['diagnostico1'] ?? '';
        $diagnosticoSecundario = $formDetails['diagnostico2'] ?? '';

        // === Procedimientos ===
        foreach ($data['procedimientos'] as $index => $p) {
            $descripcion = $p['proc_detalle'] ?? '';
            $precioBase = (float)($p['proc_precio'] ?? 0);
            $porcentaje = ($index === 0 || stripos($descripcion, 'separado') !== false) ? 1 : 0.5;
            $valorUnitario = $precioBase * $porcentaje;
            $total = $valorUnitario;
            $colVals = [
                '0000000135',
                '000002',
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                $sexo,
                $pacienteInfo['fecha_nacimiento'] ?? '',
                $contexto['edad'] ?? '',
                'PRO/INTERV',
                $p['proc_codigo'] ?? '',
                $p['proc_detalle'] ?? '',
                $diagnosticoPrincipal,
                '',
                '',
                '1',
                number_format($valorUnitario, 2),
                '',
                'T',
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                '',
                'CPPSSG-27-05-2024-RPC-SFGG-208',
                '1',
                'D',
                '', '', '', '', // Z, AA, AB, AC
                '0',
                '0',
                number_format($total, 2),
                '',
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                '',
                'NO',
                '',
                'NO',
                'P',
                '1',
                '', '',
                'F',
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        // === Ayudantes ===
        if (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante'])) {
            foreach ($data['procedimientos'] as $index => $p) {
                $descripcion = $p['proc_detalle'] ?? '';
                $precio = (float)$p['proc_precio'];
                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                $valorUnitario = $precio * $porcentaje;
                $total = $valorUnitario;
                $colVals = [
                    '0000000135',
                    '000002',
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                    strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                    $pacienteInfo['hc_number'] ?? '',
                    $nombrePaciente,
                    $sexo,
                    $pacienteInfo['fecha_nacimiento'] ?? '',
                    $contexto['edad'] ?? '',
                    'PRO/INTERV',
                    $p['proc_codigo'] ?? '',
                    $p['proc_detalle'] ?? '',
                    $diagnosticoPrincipal,
                    '',
                    '',
                    '1',
                    number_format($valorUnitario, 2),
                    '',
                    'T',
                    $pacienteInfo['hc_number'] ?? '',
                    $nombrePaciente,
                    '',
                    'CPPSSG-27-05-2024-RPC-SFGG-208',
                    '1',
                    'D',
                    '', '', '', '', // Z, AA, AB, AC
                    '0',
                    '0',
                    number_format($total, 2),
                    '',
                    date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                    '',
                    'NO',
                    '',
                    'NO',
                    'P',
                    '1',
                    '', '',
                    'F',
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

        // === ANESTESIA ===
        $codigoAnestesia = $data['procedimientos'][0]['proc_codigo'] ?? '';
        $precioReal = $codigoAnestesia && isset($GLOBALS['controller']) ? $GLOBALS['controller']->obtenerValorAnestesia($codigoAnestesia) : null;
        if (!empty($data['procedimientos'][0])) {
            $p = $data['procedimientos'][0];
            $precio = (float)$p['proc_precio'];
            $valorUnitario = $precioReal ?? $precio;
            $cantidad = 1;
            $total = $valorUnitario * $cantidad;
            $colVals = [
                '0000000135',
                '000002',
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                $sexo,
                $pacienteInfo['fecha_nacimiento'] ?? '',
                $contexto['edad'] ?? '',
                'PRO/INTERV',
                $p['proc_codigo'] ?? '',
                $p['proc_detalle'] ?? '',
                $diagnosticoPrincipal,
                '',
                '',
                '1',
                number_format($valorUnitario, 2),
                '',
                'T',
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                '',
                'CPPSSG-27-05-2024-RPC-SFGG-208',
                '1',
                'D',
                '', '', '', '', // Z, AA, AB, AC
                '0',
                '0',
                number_format($total, 2),
                '',
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                '',
                'NO',
                '',
                'NO',
                'P',
                '1',
                '', '',
                'F',
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }
        foreach ($data['anestesia'] as $a) {
            $codigo = $a['codigo'];
            $descripcion = $a['nombre'];
            $cantidad = (float)$a['tiempo'];
            $valorUnitario = (float)$a['valor2'];
            $total = $cantidad * $valorUnitario;
            $colVals = [
                '0000000135',
                '000002',
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                $sexo,
                $pacienteInfo['fecha_nacimiento'] ?? '',
                $contexto['edad'] ?? '',
                'PRO/INTERV',
                $codigo,
                $descripcion,
                $diagnosticoPrincipal,
                '',
                '',
                $cantidad,
                number_format($valorUnitario, 2),
                '',
                'T',
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                '',
                'CPPSSG-27-05-2024-RPC-SFGG-208',
                '1',
                'D',
                '', '', '', '', // Z, AA, AB, AC
                '0',
                '0',
                number_format($total, 2),
                '',
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                '',
                'NO',
                '',
                'NO',
                'P',
                '1',
                '', '',
                'F',
            ];
            foreach ($cols as $i => $col) {
                $sheet->setCellValueExplicit($col . $row, $colVals[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach ($cols as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        // === FARMACIA E INSUMOS ===
        $fuenteDatos = [
            ['grupo' => 'FARMACIA', 'items' => array_merge($data['medicamentos'] ?? [], $data['oxigeno'] ?? [])],
            ['grupo' => 'INSUMOS', 'items' => $data['insumos'] ?? []],
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
                    $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                    $valorUnitario = (float)$item['valor2'];
                } else {
                    $cantidad = $item['cantidad'] ?? 1;
                    $valorUnitario = $item['precio'] ?? 0;
                }
                $subtotal = $valorUnitario * $cantidad;
                $bodega = 1;
                $iva = ($grupo === 'FARMACIA') ? 0 : 1;
                $total = $subtotal + ($iva ? $subtotal * 0.1 : 0);
                $colVals = [
                    '0000000135',
                    '000002',
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                    strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                    $pacienteInfo['hc_number'] ?? '',
                    $nombrePaciente,
                    $sexo,
                    $pacienteInfo['fecha_nacimiento'] ?? '',
                    $contexto['edad'] ?? '',
                    $grupo,
                    $codigo,
                    $descripcion,
                    $diagnosticoPrincipal,
                    '',
                    '',
                    $cantidad,
                    number_format($valorUnitario, 2),
                    '',
                    'T',
                    $pacienteInfo['hc_number'] ?? '',
                    $nombrePaciente,
                    '',
                    'CPPSSG-27-05-2024-RPC-SFGG-208',
                    '1',
                    'D',
                    '', '', '', '', // Z, AA, AB, AC
                    $iva,
                    '0',
                    number_format($total, 2),
                    '',
                    date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                    date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                    '',
                    'NO',
                    '',
                    'NO',
                    'P',
                    '1',
                    '', '',
                    'F',
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

        // === Servicios institucionales ===
        foreach ($data['derechos'] as $servicio) {
            $codigo = $servicio['codigo'];
            $descripcion = $servicio['detalle'];
            $cantidad = $servicio['cantidad'];
            $valorUnitario = $servicio['precio_afiliacion'];
            $subtotal = $valorUnitario * $cantidad;
            $bodega = 0;
            $iva = 0;
            $total = $subtotal;
            $colVals = [
                '0000000135',
                '000002',
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                strtoupper(substr(date('l', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')), 0, 2)),
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                $sexo,
                $pacienteInfo['fecha_nacimiento'] ?? '',
                $contexto['edad'] ?? '',
                'SERVICIOS INSTITUCIONALES',
                $codigo,
                $descripcion,
                $diagnosticoPrincipal,
                '',
                '',
                $cantidad,
                number_format($valorUnitario, 2),
                '',
                'T',
                $pacienteInfo['hc_number'] ?? '',
                $nombrePaciente,
                '',
                'CPPSSG-27-05-2024-RPC-SFGG-208',
                '1',
                'D',
                '', '', '', '', // Z, AA, AB, AC
                $iva,
                '0',
                number_format($total, 2),
                '',
                date('d/m/Y', strtotime($formDetails['fecha_inicio'] ?? '')),
                date('d/m/Y', strtotime($formDetails['fecha_fin'] ?? $formDetails['fecha_inicio'] ?? '')),
                '',
                'NO',
                '',
                'NO',
                'P',
                '1',
                '', '',
                'F',
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