<?php
require_once __DIR__ . '/../../bootstrap.php';

/** @var PDO $pdo */
global $pdo;

function truncar($valor, $decimales = 2)
{
    $factor = pow(10, $decimales);
    return floor($valor * $factor) / $factor;
}

use Controllers\ReglaController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener form_id
$formId = $_GET['form_id'] ?? null;
if (!$formId) {
    die("Falta el parámetro form_id.");
}

// Obtener datos desde variables globales
$data = $GLOBALS['datos_facturacion'];
$formId = $GLOBALS['form_id_facturacion'] ?? ($_GET['form_id'] ?? null);
if (!$data) {
    die("No se encontró la prefactura para form_id: $formId");
}

// Acceso directo a los datos del paciente y formulario desde $data
$pacienteInfo = $data['paciente'] ?? [];
$formDetails = $data['formulario'] ?? [];
$formDetails['fecha_inicio'] = $data['protocoloExtendido']['fecha_inicio'] ?? '';
$fechaISO = $formDetails['fecha_inicio'] ?? '';
$fecha = $fechaISO ? date('d-m-Y', strtotime($fechaISO)) : '';
$cedula = $pacienteInfo['cedula'] ?? '';
$periodo = date('Y-m', strtotime($fechaISO));

// Agregar valores de protocoloExtendido para uso en el Excel
$formDetails['diagnosticos'] = json_decode($data['protocoloExtendido']['diagnosticos'], true) ?? [];
$formDetails['diagnostico1'] = $formDetails['diagnosticos'][0]['idDiagnostico'] ?? '';

$formDetails['diagnostico2'] = $formDetails['diagnosticos'][1]['idDiagnostico'] ?? '';

// Inicializar controlador de reglas clínicas
$reglaController = new ReglaController($pdo);

// Preparar contexto para evaluación
$contexto = [
    'afiliacion' => $pacienteInfo['afiliacion'] ?? '',
    'procedimiento' => $data['procedimientos'][0]['proc_detalle'] ?? '',
    'edad' => isset($pacienteInfo['fecha_nacimiento']) ? date_diff(date_create($pacienteInfo['fecha_nacimiento']), date_create('today'))->y : null,
];

// Evaluar reglas clínicas activas
$accionesReglas = $reglaController->evaluar($contexto);

$diagnosticoPrincipal = $formDetails['diagnostico1'] ?? '';
$diagnosticoSecundario = $formDetails['diagnostico2'] ?? '';
// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('ISSPOL');

// Encabezados
// Nuevos encabezados
$headers = [
    'A1' => 'Tipo Prestación',
    'B1' => 'Cédula Paciente',
    'C1' => 'Período',
    'D1' => 'Grupo-tipo',
    'E1' => 'Tipo de procedimiento',
    'F1' => 'Cédula del médico',
    'G1' => 'Fecha de prestación',
    'H1' => 'Código de prestación',
    'I1' => 'Descripción',
    'J1' => 'Anestesia Si/NO',
    'K1' => '%Pago',
    'L1' => 'Cantidad',
    'M1' => 'Valor Unitario',
    'N1' => 'Subotal',
    'O1' => '%Bodega',
    'P1' => '% IVA',
    'Q1' => 'Total',
];
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
    $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle('thin');
}
$row = 2;

// === Procedimientos para ISSPOL
foreach ($data['procedimientos'] as $index => $p) {
    $codigo = $p['proc_codigo'] ?? '';
    $descripcion = $p['proc_detalle'] ?? '';
    $precio = (float)$p['proc_precio'];

    // Lógica de porcentaje
    if ($index === 0) {
        $porcentaje = 1;
    } elseif (stripos($descripcion, 'separado') !== false) {
        $porcentaje = 1;
    } else {
        $porcentaje = 0.5;
    }

    if ($codigo === '67036') {
        $porcentaje = 0.625;

        // Primera fila (normal)
        $valorPorcentaje = $precio * $porcentaje;
        $cantidad = 1;
        $valorUnitario = truncar($precio, 2);
        $subtotal = truncar($valorUnitario * $cantidad * $porcentaje, 2);
        $bodega = 0;
        $iva = 0;
        $total = truncar($subtotal, 2);
        $porcentajePago = $porcentaje * 100;

        $sheet->setCellValue("A{$row}", 'AMBULATORIO');
        $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
        $sheet->setCellValue("C{$row}", $periodo);
        $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
        $sheet->setCellValue("E{$row}", 'CIRUJANO');
        $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
        $sheet->setCellValue("G{$row}", $fecha);
        $sheet->setCellValue("H{$row}", $codigo);
        $sheet->setCellValue("I{$row}", $descripcion);
        $sheet->setCellValue("J{$row}", 'NO');
        $sheet->setCellValue("K{$row}", $porcentajePago);
        $sheet->setCellValue("L{$row}", $cantidad);
        $sheet->setCellValue("M{$row}", $valorUnitario);
        $sheet->setCellValue("N{$row}", $subtotal);
        $sheet->setCellValue("O{$row}", $bodega);
        $sheet->setCellValue("P{$row}", $iva);
        $sheet->setCellValue("Q{$row}", $total);
        foreach (range('A', 'Q') as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
        $row++;

        // Segunda fila (duplicado)
        $sheet->setCellValue("A{$row}", 'AMBULATORIO');
        $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
        $sheet->setCellValue("C{$row}", $periodo);
        $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
        $sheet->setCellValue("E{$row}", 'CIRUJANO');
        $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
        $sheet->setCellValue("G{$row}", $fecha);
        $sheet->setCellValue("H{$row}", $codigo);
        $sheet->setCellValue("I{$row}", $descripcion);
        $sheet->setCellValue("J{$row}", 'NO');
        $sheet->setCellValue("K{$row}", $porcentajePago);
        $sheet->setCellValue("L{$row}", $cantidad);
        $sheet->setCellValue("M{$row}", $valorUnitario);
        $sheet->setCellValue("N{$row}", $subtotal);
        $sheet->setCellValue("O{$row}", $bodega);
        $sheet->setCellValue("P{$row}", $iva);
        $sheet->setCellValue("Q{$row}", $total);
        foreach (range('A', 'Q') as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
        $row++;

        // Saltar continuar lógica normal para este código
        continue;
    }

    $valorPorcentaje = $precio * $porcentaje;
    $cantidad = 1;
    $valorUnitario = truncar($precio, 2);
    $subtotal = truncar($valorUnitario * $cantidad * $porcentaje, 2);
    $bodega = 0;
    $iva = 0;
    $total = truncar($subtotal, 2);
    $porcentajePago = $porcentaje * 100;

    $sheet->setCellValue("A{$row}", 'AMBULATORIO');
    $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
    $sheet->setCellValue("C{$row}", $periodo);
    $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
    $sheet->setCellValue("E{$row}", 'CIRUJANO'); // Tipo de procedimiento
    $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
    $sheet->setCellValue("G{$row}", $fecha);
    $sheet->setCellValue("H{$row}", $codigo);
    $sheet->setCellValue("I{$row}", $descripcion);
    $sheet->setCellValue("J{$row}", 'NO'); // Anestesia
    $sheet->setCellValue("K{$row}", $porcentajePago);
    $sheet->setCellValue("L{$row}", $cantidad);
    $sheet->setCellValue("M{$row}", $valorUnitario);
    $sheet->setCellValue("N{$row}", $subtotal);
    $sheet->setCellValue("O{$row}", $bodega);
    $sheet->setCellValue("P{$row}", $iva);
    $sheet->setCellValue("Q{$row}", $total);

    foreach (range('A', 'Q') as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
    $row++;
}

if (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante'])) {
    foreach ($data['procedimientos'] as $index => $p) {
        $porcentaje = ($index === 0) ? 0.2 : 0.1;
        $precio = (float)$p['proc_precio'];
        $valorPorcentaje = $precio * $porcentaje;
        $codigo = $p['proc_codigo'] ?? '';
        $descripcion = $p['proc_detalle'] ?? '';
        $cantidad = 1;
        $valorUnitario = truncar($precio, 2);
        $subtotal = truncar($valorUnitario * $cantidad * $porcentaje, 2);
        $bodega = 0;
        $iva = 0;
        $total = truncar($subtotal, 2);
        $porcentajePago = $porcentaje * 100;

        $sheet->setCellValue("A{$row}", 'AMBULATORIO');
        $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
        $sheet->setCellValue("C{$row}", $periodo);
        $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
        $sheet->setCellValue("E{$row}", 'AYUDANTE'); // Tipo de procedimiento
        $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
        $sheet->setCellValue("G{$row}", $fecha);
        $sheet->setCellValue("H{$row}", ltrim($codigo, '0'));
        $sheet->setCellValue("I{$row}", $descripcion);
        $sheet->setCellValue("J{$row}", 'NO'); // Anestesia
        $sheet->setCellValue("K{$row}", $porcentajePago);
        $sheet->setCellValue("L{$row}", $cantidad);
        $sheet->setCellValue("M{$row}", $valorUnitario);
        $sheet->setCellValue("N{$row}", $subtotal);
        $sheet->setCellValue("O{$row}", $bodega);
        $sheet->setCellValue("P{$row}", $iva);
        $sheet->setCellValue("Q{$row}", $total);

        foreach (range('A', 'Q') as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
        $row++;
    }
}

// Obtener precio real de anestesia desde BillingController centralizado
$codigoAnestesia = $data['procedimientos'][0]['proc_codigo'] ?? '';
$precioReal = $codigoAnestesia ? $GLOBALS['controller']->obtenerValorAnestesia($codigoAnestesia) : null;

if (!empty($data['procedimientos'][0])) {
    $p = $data['procedimientos'][0];
    $precio = (float)$p['proc_precio'];
    $porcentaje = 1;
    $valorPorcentaje = $precio * $porcentaje;
    $codigo = $p['proc_codigo'] ?? '';
    $descripcion = $p['proc_detalle'] ?? '';
    $cantidad = 1;
    $valorUnitario = truncar($precioReal ?? $precio, 2);
    $subtotal = truncar($valorUnitario * $cantidad * $porcentaje, 2);
    $bodega = 0;
    $iva = 0;
    $total = truncar($subtotal, 2);
    $porcentajePago = $porcentaje * 100;

    $sheet->setCellValue("A{$row}", 'AMBULATORIO');
    $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
    $sheet->setCellValue("C{$row}", $periodo);
    $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
    $sheet->setCellValue("E{$row}", 'ANESTESIOLOGO'); // Tipo de procedimiento
    $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
    $sheet->setCellValue("G{$row}", $fecha);
    $sheet->setCellValue("H{$row}", $codigo);
    $sheet->setCellValue("I{$row}", $descripcion);
    $sheet->setCellValue("J{$row}", 'SI'); // Anestesia
    $sheet->setCellValue("K{$row}", $porcentajePago);
    $sheet->setCellValue("L{$row}", $cantidad);
    $sheet->setCellValue("M{$row}", $valorUnitario);
    $sheet->setCellValue("N{$row}", $subtotal);
    $sheet->setCellValue("O{$row}", $bodega);
    $sheet->setCellValue("P{$row}", $iva);
    $sheet->setCellValue("Q{$row}", $total);

    foreach (range('A', 'Q') as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
    $row++;
}
// Obtener el primer procedimiento principal fuera del bucle
$p = $data['procedimientos'][0] ?? [];

foreach ($data['anestesia'] as $a) {
    // Si el código de anestesia es 999999, usa el código y descripción del procedimiento principal
    if ($a['codigo'] === '999999') {
        $codigo = $p['proc_codigo'] ?? '';
        $descripcion = $p['proc_detalle'] ?? '';
    } else {
        $codigo = $a['codigo'];
        $descripcion = $a['nombre'];
    }
    $cantidad = (float)$a['tiempo'];
    $valorUnitario = truncar((float)$a['valor2'], 2);
    $subtotal = truncar($cantidad * $valorUnitario, 2);
    $bodega = 0;
    $iva = 0;
    $total = truncar($subtotal, 2);
    $porcentajePago = 100;

    $sheet->setCellValue("A{$row}", 'AMBULATORIO');
    $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
    $sheet->setCellValue("C{$row}", $periodo);
    $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
    $sheet->setCellValue("E{$row}", 'ANESTESIOLOGO');
    $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
    $sheet->setCellValue("G{$row}", $fecha);
    $sheet->setCellValue("H{$row}", $codigo);
    $sheet->setCellValue("I{$row}", $descripcion);
    $sheet->setCellValue("J{$row}", 'SI'); // Anestesia
    $sheet->setCellValue("K{$row}", $porcentajePago);
    $sheet->setCellValue("L{$row}", $cantidad);
    $sheet->setCellValue("M{$row}", $valorUnitario);
    $sheet->setCellValue("N{$row}", $subtotal);
    $sheet->setCellValue("O{$row}", $bodega);
    $sheet->setCellValue("P{$row}", $iva);
    $sheet->setCellValue("Q{$row}", $total);

    foreach (range('A', 'Q') as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
    $row++;
}

// Armar filas
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
        // Detectar si es oxígeno
        $esOxigeno = isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2']);
        if ($esOxigeno) {
            $codigo = '1442'; // Código fijo para oxígeno
            $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
            $valorUnitario = truncar((float)$item['valor2'], 2);
            $valorConGestion = $valorUnitario; // Para oxígeno, sin gestión extra
            $subtotal = truncar($valorUnitario * $cantidad, 2);
            $total = $subtotal;
        } else {
            $codigo = $item['codigo'] ?? '';
            $cantidad = $item['cantidad'] ?? 1;
            $valorConGestion = $item['precio'] ?? 0;
            // Si es farmacia, desglosar el 10%
            if ($grupo === 'FARMACIA') {
                // Valor base sin gestión
                $valorUnitario = truncar($valorConGestion / 1.10, 2);
                $subtotal = truncar($valorUnitario * $cantidad, 2);
                $total = truncar($valorConGestion * $cantidad, 2); // Solo el valor original unitario con gestión
            } else {
                // INSUMOS
                $valorUnitario = truncar($valorConGestion, 2);
                $subtotal = truncar($valorUnitario * $cantidad, 2);
                $total = truncar($valorConGestion * 1.12, 2) * $cantidad;
                $total = truncar($total, 2);
            }
        }
        $codigo = ltrim($codigo, '0'); // Quitar ceros a la izquierda
        $bodega = 1;
        $iva = ($grupo === 'FARMACIA') ? 0 : 1;
        // $total ya calculado arriba
        $porcentajePago = 100;

        $sheet->setCellValue("A{$row}", 'AMBULATORIO');
        $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
        $sheet->setCellValue("C{$row}", $periodo);
        $sheet->setCellValue("D{$row}", $grupo);
        $sheet->setCellValue("E{$row}", ''); // Tipo de procedimiento
        $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
        $sheet->setCellValue("G{$row}", $fecha);
        $sheet->setCellValue("H{$row}", $codigo);
        $sheet->setCellValue("I{$row}", $descripcion);
        $sheet->setCellValue("J{$row}", 'NO'); // Anestesia
        $sheet->setCellValue("K{$row}", $porcentajePago);
        $sheet->setCellValue("L{$row}", $cantidad);
        $sheet->setCellValue("M{$row}", $valorUnitario);
        $sheet->setCellValue("N{$row}", $subtotal);
        $sheet->setCellValue("O{$row}", $bodega);
        $sheet->setCellValue("P{$row}", $iva);
        $sheet->setCellValue("Q{$row}", $total);

        foreach (range('A', 'Q') as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
        $row++;
    }
}

// === Servicios institucionales y equipos especializados para ISSPOL
// Lista de códigos que requieren descuento del 2%
$codigos_descuento_2 = [
    '394233', '394244', '394255', '394266', '394277', '394288', '394299', '394301',
    '394312', '394323', '394333', '394344', '395281'
];

foreach ($data['derechos'] as $servicio) {
    $codigo = $servicio['codigo'];
    $descripcion = $servicio['detalle'];
    $cantidad = $servicio['cantidad'];
    $valorUnitarioReal = $servicio['precio_afiliacion']; // valor sin descuento
    // Por defecto
    $valorUnitario = truncar($valorUnitarioReal, 2);
    $subtotal = truncar($valorUnitario * $cantidad, 2);
    $total = $subtotal;

    // Descuento 2% para ciertos códigos SOLO en unitario y subtotal
    if (in_array($codigo, $codigos_descuento_2)) {
        $valorUnitario = truncar($valorUnitarioReal / 1.02, 2);
        $subtotal = truncar($valorUnitario * $cantidad, 2);
        $total = truncar($valorUnitarioReal * $cantidad, 2); // total real sin descuento
    }

    $bodega = 0;
    $iva = 0;
    $porcentajePago = 100;

    $sheet->setCellValue("A{$row}", 'AMBULATORIO');
    $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number']);
    $sheet->setCellValue("C{$row}", $periodo);
    $sheet->setCellValue("D{$row}", 'SERVICIOS INSTITUCIONALES');
    $sheet->setCellValue("E{$row}", ''); // Tipo de procedimiento
    $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
    $sheet->setCellValue("G{$row}", $fecha);
    $sheet->setCellValue("H{$row}", $codigo);
    $sheet->setCellValue("I{$row}", $descripcion);
    $sheet->setCellValue("J{$row}", 'NO'); // Anestesia
    $sheet->setCellValue("K{$row}", $porcentajePago);
    $sheet->setCellValue("L{$row}", $cantidad);
    $sheet->setCellValue("M{$row}", $valorUnitario);
    $sheet->setCellValue("N{$row}", $subtotal);
    $sheet->setCellValue("O{$row}", $bodega);
    $sheet->setCellValue("P{$row}", $iva);
    $sheet->setCellValue("Q{$row}", $total);

    foreach (range('A', 'Q') as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
    $row++;
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