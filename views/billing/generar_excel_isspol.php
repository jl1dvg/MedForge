<?php
require_once __DIR__ . '/../../bootstrap.php';

/** @var PDO $pdo */
global $pdo;

function truncar($valor, $decimales = 2)
{
    $factor = pow(10, $decimales);
    return floor($valor * $factor) / $factor;
}

/**
 * Detecta si el ítem es uno de los medicamentos de FARMACIA con cálculo especial.
 */
function esMedicamentoEspecial($descripcion)
{
    $txt = strtoupper(preg_replace('/\s+/', ' ', trim((string)$descripcion)));
    $objetivos = [
        'ATROPINA LIQUIDO OFTALMICO',
        'BUPIVACAINA (SIN EPINEFRINA) LIQUIDO PARENTERAL',
        'TROPICAMIDA LIQUIDO OFTALMICO',
        'DICLOFENACO LIQUIDO PARENTERAL',
        'ENALAPRIL LIQUIDO PARENTERAL',
        'FLUMAZENIL LIQUIDO PARENTERAL',
    ];
    foreach ($objetivos as $needle) {
        if (strpos($txt, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Devuelve el valor unitario y mL predeterminados para medicamentos especiales según la descripción.
 * Retorna array ['valor' => float, 'ml' => float] o null si no aplica.
 */
function obtenerValorMedicamentoEspecial($descripcion)
{
    $txt = strtoupper(preg_replace('/\s+/', ' ', trim((string)$descripcion)));
    // Reglas: buscar por nombre y ml
    if (strpos($txt, 'ATROPINA LIQUIDO OFTALMICO') !== false) {
        // ATROPINA (5ML) => 1.21
        return ['valor' => 1.21, 'ml' => 5];
    }
    if (strpos($txt, 'DICLOFENACO LIQUIDO PARENTERAL') !== false) {
        // DICLOFENACO PARENTERAL (3ML) => 0.25
        return ['valor' => 0.25, 'ml' => 3];
    }
    if (strpos($txt, 'ENALAPRIL LIQUIDO PARENTERAL') !== false) {
        // ENALAPRIL (1ML) => 8.54
        return ['valor' => 8.54, 'ml' => 1];
    }
    if (strpos($txt, 'FLUMAZENIL LIQUIDO PARENTERAL') !== false) {
        // FLUMAZENIL (5ML) => 24.20
        return ['valor' => 24.20, 'ml' => 5];
    }
    if (strpos($txt, 'TROPICAMIDA LIQUIDO OFTALMICO') !== false) {
        // TROPICAMIDA (15ML) => 0.89
        return ['valor' => 0.89, 'ml' => 15];
    }
    if (strpos($txt, 'BUPIVACAINA (SIN EPINEFRINA) LIQUIDO PARENTERAL') !== false) {
        // BUPIVACAINA (20ML) => 0.15
        return ['valor' => 0.15, 'ml' => 20];
    }
    // DICLOFENACO LIQUIDO OFTALMICO no tiene valor especial definido, usar 0.89 como antes
    return null;
}

/**
 * Extrae la cantidad de mL desde la descripción si viene en el formato "(5ML)" o "(5 ML)".
 * Retorna float|null si no encuentra coincidencia.
 */
function extraerMlDeDescripcion($descripcion)
{
    $desc = (string)$descripcion;
    if (preg_match('/\((\d+(?:\.\d+)?)\s*ML\)/i', $desc, $m)) {
        return (float)$m[1];
    }
    return null;
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

// Regla: si existe 67036, NO generar secciones de AYUDANTE
$hay67036 = false;
foreach ($data['procedimientos'] as $procTmp) {
    if (($procTmp['proc_codigo'] ?? '') === '67036') {
        $hay67036 = true;
        break;
    }
}

if (!$hay67036 && (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante']))) {
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
            // Si es farmacia, desglosar el 10% o cálculo especial
            if ($grupo === 'FARMACIA') {
                // Cálculo especial por mL para medicamentos específicos
                if (esMedicamentoEspecial($descripcion)) {
                    // Buscar valores predeterminados para este medicamento especial
                    $valoresEspeciales = obtenerValorMedicamentoEspecial($descripcion);
                    // Cantidad mL: primero campos explícitos; si no, desde la descripción "(5ML)", si no, usar default especial
                    $cantidadMl = $item['ml_admin'] ?? $item['ml'] ?? $item['cantidad_ml'] ?? null;
                    if ($cantidadMl === null) {
                        $cantidadMl = extraerMlDeDescripcion($descripcion);
                    }
                    if ($cantidadMl === null && is_array($valoresEspeciales) && isset($valoresEspeciales['ml'])) {
                        $cantidadMl = $valoresEspeciales['ml'];
                    }
                    $cantidad = (float)($cantidadMl ?? $cantidad);

                    // Valor por mL: si no viene, usar el default especial si existe, si no, 0.89 como antes
                    if (isset($item['valor_unitario_manual'])) {
                        $valorUnitarioBase = $item['valor_unitario_manual'];
                    } elseif (isset($item['valor_unitario_ml'])) {
                        $valorUnitarioBase = $item['valor_unitario_ml'];
                    } elseif (isset($item['valor_unitario'])) {
                        $valorUnitarioBase = $item['valor_unitario'];
                    } elseif (is_array($valoresEspeciales) && isset($valoresEspeciales['valor'])) {
                        $valorUnitarioBase = $valoresEspeciales['valor'];
                    } else {
                        $valorUnitarioBase = 0.89;
                    }
                    $valorUnitario = truncar((float)$valorUnitarioBase, 2);

                    $subtotal = truncar($valorUnitario * $cantidad, 2);
                    $total = truncar($subtotal * 1.1, 2); // sin gestión adicional
                } else {
                    // Valor base sin gestión (se desglosa el 10% de gestión)
                    $valorUnitario = truncar($valorConGestion / 1.10, 2);
                    $subtotal = truncar($valorUnitario * $cantidad, 2);
                    // Total conserva el valor original con gestión
                    $total = truncar($valorConGestion * $cantidad, 2);
                }
            } else {
                // INSUMOS
                $valorUnitario = truncar($valorConGestion, 2);
                $subtotal = truncar($valorUnitario * $cantidad, 2);
                // Para INSUMOS, se mantiene el 10% de gestión en el total
                $total = truncar($valorConGestion * 1.1, 2) * $cantidad;
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
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $pacienteInfo['lname'] . '_' . $pacienteInfo['lname2'] . '_' . $pacienteInfo['fname'] . '_' . $pacienteInfo['mname'] . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;