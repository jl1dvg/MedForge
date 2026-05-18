<?php

declare(strict_types=1);

namespace App\Modules\Billing\Excel;

use App\Modules\Billing\Services\BillingInformeDataService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class IsspolicIndividualExcelBuilder
{
    public function __construct(private readonly BillingInformeDataService $billingService) {}

    /** @param array<string, mixed> $datos */
    public function build(array $datos, string $formId): Spreadsheet
    {
        $pacienteInfo  = $datos['paciente'] ?? [];
        $formDetails   = $datos['formulario'] ?? [];
        $formDetails['fecha_inicio'] = $datos['protocoloExtendido']['fecha_inicio'] ?? '';
        $fechaISO = $formDetails['fecha_inicio'] ?? '';
        $fecha    = $fechaISO ? date('d-m-Y', strtotime($fechaISO)) : '';
        $periodo  = $fechaISO ? date('Y-m', strtotime($fechaISO)) : '';

        $diagnosticos  = json_decode((string) ($datos['protocoloExtendido']['diagnosticos'] ?? '[]'), true) ?? [];
        $formDetails['diagnostico1'] = $diagnosticos[0]['idDiagnostico'] ?? '';
        $formDetails['diagnostico2'] = $diagnosticos[1]['idDiagnostico'] ?? '';

        $contexto = [
            'afiliacion'    => $pacienteInfo['afiliacion'] ?? '',
            'procedimiento' => $datos['procedimientos'][0]['proc_detalle'] ?? '',
            'edad'          => isset($pacienteInfo['fecha_nacimiento'])
                ? date_diff(date_create((string) $pacienteInfo['fecha_nacimiento']), date_create('today'))->y
                : null,
        ];
        $accionesReglas = $this->evaluarReglas($contexto);

        $codigoAnestesia = $datos['procedimientos'][0]['proc_codigo'] ?? '';
        $precioRealAnestesia = $codigoAnestesia
            ? $this->billingService->obtenerValorAnestesia($codigoAnestesia)
            : null;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ISSPOL');

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

        foreach ($datos['procedimientos'] as $index => $p) {
            $codigo      = $p['proc_codigo'] ?? '';
            $descripcion = $p['proc_detalle'] ?? '';
            $precio      = (float) $p['proc_precio'];

            if ($index === 0) {
                $porcentaje = 1;
            } elseif (stripos($descripcion, 'separado') !== false) {
                $porcentaje = 1;
            } else {
                $porcentaje = 0.5;
            }

            if ($codigo === '67036') {
                $porcentaje = 0.625;
                for ($i = 0; $i < 2; $i++) {
                    $valorUnitario  = $this->truncar($precio, 2);
                    $subtotal       = $this->truncar($valorUnitario * 1 * $porcentaje, 2);
                    $total          = $this->truncar($subtotal, 2);
                    $porcentajePago = $porcentaje * 100;

                    $this->fillHonorariosRow($sheet, $row, $pacienteInfo, $periodo, $fecha, $codigo, $descripcion, 'CIRUJANO', $porcentajePago, 1, $valorUnitario, $subtotal, $total);
                    $row++;
                }
                continue;
            }

            $valorUnitario  = $this->truncar($precio, 2);
            $subtotal       = $this->truncar($valorUnitario * 1 * $porcentaje, 2);
            $total          = $this->truncar($subtotal, 2);
            $porcentajePago = $porcentaje * 100;

            $this->fillHonorariosRow($sheet, $row, $pacienteInfo, $periodo, $fecha, $codigo, $descripcion, 'CIRUJANO', $porcentajePago, 1, $valorUnitario, $subtotal, $total);
            $row++;
        }

        $hay67036 = false;
        foreach ($datos['procedimientos'] as $procTmp) {
            if (($procTmp['proc_codigo'] ?? '') === '67036') {
                $hay67036 = true;
                break;
            }
        }

        if (!$hay67036 && (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante']))) {
            foreach ($datos['procedimientos'] as $index => $p) {
                $porcentaje  = ($index === 0) ? 0.2 : 0.1;
                $precio      = (float) $p['proc_precio'];
                $codigo      = $p['proc_codigo'] ?? '';
                $descripcion = $p['proc_detalle'] ?? '';
                $valorUnitario  = $this->truncar($precio, 2);
                $subtotal       = $this->truncar($valorUnitario * 1 * $porcentaje, 2);
                $total          = $this->truncar($subtotal, 2);
                $porcentajePago = $porcentaje * 100;

                $this->fillHonorariosRow($sheet, $row, $pacienteInfo, $periodo, $fecha, ltrim($codigo, '0'), $descripcion, 'AYUDANTE', $porcentajePago, 1, $valorUnitario, $subtotal, $total);
                $row++;
            }
        }

        if (!empty($datos['procedimientos'][0])) {
            $p           = $datos['procedimientos'][0];
            $precio      = (float) $p['proc_precio'];
            $codigo      = $p['proc_codigo'] ?? '';
            $descripcion = $p['proc_detalle'] ?? '';
            $valorUnitario = $this->truncar($precioRealAnestesia ?? $precio, 2);
            $subtotal      = $this->truncar($valorUnitario, 2);
            $total         = $subtotal;

            $this->fillHonorariosRow($sheet, $row, $pacienteInfo, $periodo, $fecha, $codigo, $descripcion, 'ANESTESIOLOGO', 100, 1, $valorUnitario, $subtotal, $total, 'SI');
            $row++;
        }

        $primerProc = $datos['procedimientos'][0] ?? [];
        foreach ($datos['anestesia'] as $a) {
            if ($a['codigo'] === '999999') {
                $codigo      = $primerProc['proc_codigo'] ?? '';
                $descripcion = $primerProc['proc_detalle'] ?? '';
            } else {
                $codigo      = $a['codigo'];
                $descripcion = $a['nombre'];
            }
            $cantidad      = (float) $a['tiempo'];
            $valorUnitario = $this->truncar((float) $a['valor2'], 2);
            $subtotal      = $this->truncar($cantidad * $valorUnitario, 2);
            $total         = $subtotal;

            $this->fillHonorariosRow($sheet, $row, $pacienteInfo, $periodo, $fecha, $codigo, $descripcion, 'ANESTESIOLOGO', 100, $cantidad, $valorUnitario, $subtotal, $total, 'SI');
            $row++;
        }

        $fuenteDatos = [
            ['grupo' => 'FARMACIA', 'items' => array_merge($datos['medicamentos'], $datos['oxigeno'])],
            ['grupo' => 'INSUMOS',  'items' => $datos['insumos']],
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

                $esOxigeno = isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2']);
                if ($esOxigeno) {
                    $codigo        = '1442';
                    $cantidad      = (float) $item['tiempo'] * (float) $item['litros'] * 60;
                    $valorUnitario = $this->truncar((float) $item['valor2'], 2);
                    $subtotal      = $this->truncar($valorUnitario * $cantidad, 2);
                    $total         = $subtotal;
                    $bodega        = 1;
                    $iva           = 0;
                } else {
                    $codigo          = ltrim((string) ($item['codigo'] ?? ''), '0');
                    $cantidad        = $item['cantidad'] ?? 1;
                    $valorConGestion = $item['precio'] ?? 0;

                    if ($grupo === 'FARMACIA') {
                        if ($this->esMedicamentoEspecial($descripcion)) {
                            $valoresEspeciales = $this->obtenerValorMedicamentoEspecial($descripcion);
                            $cantidadMl = $item['ml_admin'] ?? $item['ml'] ?? $item['cantidad_ml'] ?? null;
                            if ($cantidadMl === null) {
                                $cantidadMl = $this->extraerMlDeDescripcion($descripcion);
                            }
                            if ($cantidadMl === null && is_array($valoresEspeciales) && isset($valoresEspeciales['ml'])) {
                                $cantidadMl = $valoresEspeciales['ml'];
                            }
                            $cantidad = (float) ($cantidadMl ?? $cantidad);

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
                            $valorUnitario = $this->truncar((float) $valorUnitarioBase, 2);
                            $subtotal      = $this->truncar($valorUnitario * $cantidad, 2);
                            $total         = $this->truncar($subtotal * 1.1, 2);
                        } else {
                            $valorUnitario = $this->truncar($valorConGestion / 1.10, 2);
                            $subtotal      = $this->truncar($valorUnitario * $cantidad, 2);
                            $total         = $this->truncar($valorConGestion * $cantidad, 2);
                        }
                    } else {
                        $valorUnitario = $this->truncar($valorConGestion, 2);
                        $subtotal      = $this->truncar($valorUnitario * $cantidad, 2);
                        $total         = $this->truncar($this->truncar($valorConGestion * 1.1, 2) * $cantidad, 2);
                    }
                    $bodega = 1;
                    $iva    = ($grupo === 'FARMACIA') ? 0 : 1;
                }

                $sheet->setCellValue("A{$row}", 'AMBULATORIO');
                $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number'] ?? '');
                $sheet->setCellValue("C{$row}", $periodo);
                $sheet->setCellValue("D{$row}", $grupo);
                $sheet->setCellValue("E{$row}", '');
                $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
                $sheet->setCellValue("G{$row}", $fecha);
                $sheet->setCellValue("H{$row}", $codigo);
                $sheet->setCellValue("I{$row}", $descripcion);
                $sheet->setCellValue("J{$row}", 'NO');
                $sheet->setCellValue("K{$row}", 100);
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

        $codigos_descuento_2 = [
            '394233', '394244', '394255', '394266', '394277', '394288', '394299', '394301',
            '394312', '394323', '394333', '394344', '395281',
        ];

        foreach ($datos['derechos'] as $servicio) {
            $codigo            = $servicio['codigo'];
            $descripcion       = $servicio['detalle'];
            $cantidad          = $servicio['cantidad'];
            $valorUnitarioReal = $servicio['precio_afiliacion'];
            $valorUnitario     = $this->truncar($valorUnitarioReal, 2);
            $subtotal          = $this->truncar($valorUnitario * $cantidad, 2);
            $total             = $subtotal;

            if (in_array($codigo, $codigos_descuento_2, true)) {
                $valorUnitario = $this->truncar($valorUnitarioReal / 1.02, 2);
                $subtotal      = $this->truncar($valorUnitario * $cantidad, 2);
                $total         = $this->truncar($valorUnitarioReal * $cantidad, 2);
            }

            $sheet->setCellValue("A{$row}", 'AMBULATORIO');
            $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number'] ?? '');
            $sheet->setCellValue("C{$row}", $periodo);
            $sheet->setCellValue("D{$row}", 'SERVICIOS INSTITUCIONALES');
            $sheet->setCellValue("E{$row}", '');
            $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
            $sheet->setCellValue("G{$row}", $fecha);
            $sheet->setCellValue("H{$row}", $codigo);
            $sheet->setCellValue("I{$row}", $descripcion);
            $sheet->setCellValue("J{$row}", 'NO');
            $sheet->setCellValue("K{$row}", 100);
            $sheet->setCellValue("L{$row}", $cantidad);
            $sheet->setCellValue("M{$row}", $valorUnitario);
            $sheet->setCellValue("N{$row}", $subtotal);
            $sheet->setCellValue("O{$row}", 0);
            $sheet->setCellValue("P{$row}", 0);
            $sheet->setCellValue("Q{$row}", $total);
            foreach (range('A', 'Q') as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        return $spreadsheet;
    }

    /**
     * @param array<string, mixed> $pacienteInfo
     */
    private function fillHonorariosRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        array $pacienteInfo,
        string $periodo,
        string $fecha,
        string $codigo,
        string $descripcion,
        string $tipo,
        float $porcentajePago,
        float $cantidad,
        float $valorUnitario,
        float $subtotal,
        float $total,
        string $anestesia = 'NO'
    ): void {
        $sheet->setCellValue("A{$row}", 'AMBULATORIO');
        $sheet->setCellValue("B{$row}", $pacienteInfo['hc_number'] ?? '');
        $sheet->setCellValue("C{$row}", $periodo);
        $sheet->setCellValue("D{$row}", 'HONORARIOS PROFESIONALES');
        $sheet->setCellValue("E{$row}", $tipo);
        $sheet->setCellValue("F{$row}", $pacienteInfo['cedula_medico'] ?? '');
        $sheet->setCellValue("G{$row}", $fecha);
        $sheet->setCellValue("H{$row}", $codigo);
        $sheet->setCellValue("I{$row}", $descripcion);
        $sheet->setCellValue("J{$row}", $anestesia);
        $sheet->setCellValue("K{$row}", $porcentajePago);
        $sheet->setCellValue("L{$row}", $cantidad);
        $sheet->setCellValue("M{$row}", $valorUnitario);
        $sheet->setCellValue("N{$row}", $subtotal);
        $sheet->setCellValue("O{$row}", 0);
        $sheet->setCellValue("P{$row}", 0);
        $sheet->setCellValue("Q{$row}", $total);
        foreach (range('A', 'Q') as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
    }

    /** @param array<string, mixed> $contexto */
    private function evaluarReglas(array $contexto): array
    {
        try {
            $reglas = DB::table('reglas')->where('activa', 1)->get();
        } catch (\Throwable) {
            return [];
        }

        $accionesAplicables = [];
        foreach ($reglas as $regla) {
            $condiciones = DB::table('condiciones')->where('regla_id', $regla->id)->get();
            $cumple = true;
            foreach ($condiciones as $cond) {
                $campo           = $cond->campo;
                $valorPaciente   = strtolower(trim((string) ($contexto[$campo] ?? '')));
                $valorCondicion  = strtolower(trim((string) $cond->valor));
                $match = match ($cond->operador) {
                    '='    => $valorPaciente === $valorCondicion,
                    'LIKE' => str_contains($valorPaciente, str_replace('%', '', $valorCondicion)),
                    'IN'   => in_array($valorPaciente, array_map('trim', explode(',', $valorCondicion)), true),
                    default => false,
                };
                if (!$match) {
                    $cumple = false;
                    break;
                }
            }
            if (!$cumple) {
                continue;
            }
            $acciones = DB::table('acciones')->where('regla_id', $regla->id)->get();
            foreach ($acciones as $accion) {
                $accionesAplicables[] = [
                    'regla'     => $regla->nombre,
                    'tipo'      => $accion->tipo,
                    'parametro' => $accion->parametro,
                ];
            }
        }

        return $accionesAplicables;
    }

    private function truncar(float $valor, int $decimales = 2): float
    {
        $factor = 10 ** $decimales;
        return floor($valor * $factor) / $factor;
    }

    private function esMedicamentoEspecial(string $descripcion): bool
    {
        $txt = strtoupper(preg_replace('/\s+/', ' ', trim($descripcion)) ?? '');
        $objetivos = [
            'ATROPINA LIQUIDO OFTALMICO',
            'BUPIVACAINA (SIN EPINEFRINA) LIQUIDO PARENTERAL',
            'TROPICAMIDA LIQUIDO OFTALMICO',
            'DICLOFENACO LIQUIDO PARENTERAL',
            'ENALAPRIL LIQUIDO PARENTERAL',
            'FLUMAZENIL LIQUIDO PARENTERAL',
        ];
        foreach ($objetivos as $needle) {
            if (str_contains($txt, $needle)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{valor:float,ml:float}|null */
    private function obtenerValorMedicamentoEspecial(string $descripcion): ?array
    {
        $txt = strtoupper(preg_replace('/\s+/', ' ', trim($descripcion)) ?? '');
        if (str_contains($txt, 'ATROPINA LIQUIDO OFTALMICO')) {
            return ['valor' => 1.21, 'ml' => 5];
        }
        if (str_contains($txt, 'DICLOFENACO LIQUIDO PARENTERAL')) {
            return ['valor' => 0.25, 'ml' => 3];
        }
        if (str_contains($txt, 'ENALAPRIL LIQUIDO PARENTERAL')) {
            return ['valor' => 8.54, 'ml' => 1];
        }
        if (str_contains($txt, 'FLUMAZENIL LIQUIDO PARENTERAL')) {
            return ['valor' => 24.20, 'ml' => 5];
        }
        if (str_contains($txt, 'TROPICAMIDA LIQUIDO OFTALMICO')) {
            return ['valor' => 0.89, 'ml' => 15];
        }
        if (str_contains($txt, 'BUPIVACAINA (SIN EPINEFRINA) LIQUIDO PARENTERAL')) {
            return ['valor' => 0.15, 'ml' => 20];
        }
        return null;
    }

    private function extraerMlDeDescripcion(string $descripcion): ?float
    {
        if (preg_match('/\((\d+(?:\.\d+)?)\s*ML\)/i', $descripcion, $m)) {
            return (float) $m[1];
        }
        return null;
    }
}
