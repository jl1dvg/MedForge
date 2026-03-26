<?php

namespace App\Modules\Billing\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;

class IessSpreadsheetExportService
{
    private BillingSoamAdapter $billingAdapter;
    private BillingSoamRuleAdapter $ruleAdapter;
    /** @var array<string, array<string, mixed>|null> */
    private array $tarifarioCache = [];

    public function __construct(
        private readonly PDO $db,
        private readonly BillingInformeDataService $billingService
    ) {
        $this->billingAdapter = new BillingSoamAdapter($db);
        $this->ruleAdapter = new BillingSoamRuleAdapter($db);
    }

    /**
     * @param array<int, string> $formIds
     */
    public function buildFlatSpreadsheet(array $formIds): Spreadsheet
    {
        return $this->buildSpreadsheet($formIds, 'flat');
    }

    /**
     * @param array<int, string> $formIds
     */
    public function buildSoamSpreadsheet(array $formIds): Spreadsheet
    {
        return $this->buildSpreadsheet($formIds, 'soam');
    }

    /**
     * @param array<int, string> $formIds
     */
    private function buildSpreadsheet(array $formIds, string $format): Spreadsheet
    {
        $formIds = $this->normalizeFormIds($formIds);
        if ($formIds === []) {
            throw new \RuntimeException('Falta al menos un form_id para exportar IESS.');
        }

        $datosLote = $this->loadDatosFacturacionLote($formIds);
        if ($datosLote === []) {
            throw new \RuntimeException('No se encontró ninguna prefactura válida de IESS.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IESS');

        $cols = $format === 'soam' ? $this->soamColumns() : $this->flatColumns();
        foreach ($cols as $index => $col) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, (string) ($index + 1));
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
        }

        $rowNumber = 2;
        $fechasGlobales = $this->billingAdapter->obtenerFechasIngresoYEgreso($formIds);
        $fechaIngresoGlobal = $this->formatDate($fechasGlobales['ingreso'] ?? null);
        $fechaEgresoGlobal = $this->formatDate($fechasGlobales['egreso'] ?? null);

        foreach ($datosLote as $bloque) {
            $context = $this->buildCaseContext(
                (string) $bloque['form_id'],
                is_array($bloque['data']) ? $bloque['data'] : [],
                $fechaIngresoGlobal,
                $fechaEgresoGlobal,
                $format
            );

            foreach ($this->buildProcedureRows($context) as $values) {
                $this->writeRow($sheet, $cols, $rowNumber++, $values);
            }

            foreach ($this->buildSecondarySurgeonRows($context) as $values) {
                $this->writeRow($sheet, $cols, $rowNumber++, $values);
            }

            foreach ($this->buildAnesthesiaRows($context) as $values) {
                $this->writeRow($sheet, $cols, $rowNumber++, $values);
            }

            foreach ($this->buildSupplyRows($context) as $values) {
                $this->writeRow($sheet, $cols, $rowNumber++, $values);
            }

            foreach ($this->buildDerechoRows($context) as $values) {
                $this->writeRow($sheet, $cols, $rowNumber++, $values);
            }
        }

        return $spreadsheet;
    }

    /**
     * @param array<int, string> $formIds
     * @return array<int, string>
     */
    private function normalizeFormIds(array $formIds): array
    {
        $normalized = [];
        foreach ($formIds as $formId) {
            foreach (explode(',', (string) $formId) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $normalized[] = $part;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, string> $formIds
     * @return array<int, array{form_id:string,data:array<string,mixed>}>
     */
    private function loadDatosFacturacionLote(array $formIds): array
    {
        $result = [];
        foreach ($formIds as $formId) {
            $datos = $this->billingService->obtenerDatos($formId);
            if (is_array($datos) && $datos !== []) {
                $result[] = ['form_id' => $formId, 'data' => $datos];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCaseContext(
        string $formId,
        array $data,
        string $fechaIngresoGlobal,
        string $fechaEgresoGlobal,
        string $format
    ): array {
        $paciente = is_array($data['paciente'] ?? null) ? $data['paciente'] : [];
        $formulario = is_array($data['formulario'] ?? null) ? $data['formulario'] : [];
        $visita = is_array($data['visita'] ?? null) ? $data['visita'] : [];
        $protocolo = is_array($data['protocoloExtendido'] ?? null) ? $data['protocoloExtendido'] : [];
        $procedimientos = array_values(is_array($data['procedimientos'] ?? null) ? $data['procedimientos'] : []);
        $anestesia = array_values(is_array($data['anestesia'] ?? null) ? $data['anestesia'] : []);
        $medicamentos = array_values(is_array($data['medicamentos'] ?? null) ? $data['medicamentos'] : []);
        $oxigeno = array_values(is_array($data['oxigeno'] ?? null) ? $data['oxigeno'] : []);
        $insumos = array_values(is_array($data['insumos'] ?? null) ? $data['insumos'] : []);
        $derechos = array_values(is_array($data['derechos'] ?? null) ? $data['derechos'] : []);

        $nombrePaciente = trim(implode(' ', array_filter([
            (string) ($paciente['lname'] ?? ''),
            (string) ($paciente['lname2'] ?? ''),
            (string) ($paciente['fname'] ?? ''),
            (string) ($paciente['mname'] ?? ''),
        ])));
        $sexo = !empty($paciente['sexo']) ? strtoupper(substr((string) $paciente['sexo'], 0, 1)) : '--';
        $fechaNacimiento = $this->formatDate($paciente['fecha_nacimiento'] ?? null);
        $edad = null;
        if (!empty($paciente['fecha_nacimiento'])) {
            try {
                $edad = date_diff(date_create((string) $paciente['fecha_nacimiento']), date_create('today'))->y;
            } catch (\Throwable) {
                $edad = null;
            }
        }

        $diagnosticos = [];
        if (!empty($protocolo['diagnosticos'])) {
            $diagnosticos = json_decode((string) $protocolo['diagnosticos'], true) ?: [];
        }

        usort($procedimientos, static fn(array $a, array $b): int => ((float) ($b['proc_precio'] ?? 0)) <=> ((float) ($a['proc_precio'] ?? 0)));

        $derivacion = $this->billingAdapter->obtenerDerivacionPorFormId($formId);
        $diagnosticoStr = (string) ($derivacion['diagnostico'] ?? '');
        $cie10 = $this->extractCie10($diagnosticoStr);
        $accionesReglas = $this->ruleAdapter->evaluar([
            'afiliacion' => (string) ($paciente['afiliacion'] ?? ''),
            'procedimiento' => (string) ($procedimientos[0]['proc_detalle'] ?? ''),
            'edad' => $edad,
        ]);

        return [
            'format' => $format,
            'form_id' => $formId,
            'data' => $data,
            'paciente' => $paciente,
            'formulario' => $formulario,
            'visita' => $visita,
            'protocolo' => $protocolo,
            'procedimientos' => $procedimientos,
            'anestesia' => $anestesia,
            'medicamentos' => $medicamentos,
            'oxigeno' => $oxigeno,
            'insumos' => $insumos,
            'derechos' => $derechos,
            'nombre_paciente' => $nombrePaciente,
            'sexo' => $sexo,
            'fecha_nacimiento' => $fechaNacimiento,
            'edad' => $edad,
            'derivacion' => $derivacion,
            'codigo_derivacion' => (string) ($derivacion['cod_derivacion'] ?? ''),
            'cie10' => $cie10,
            'abreviacion_afiliacion' => $this->billingAdapter->abreviarAfiliacion((string) ($paciente['afiliacion'] ?? '')),
            'es_cirugia' => $this->billingAdapter->esCirugiaPorFormId($formId),
            'fecha_ingreso_global' => $fechaIngresoGlobal,
            'fecha_egreso_global' => $fechaEgresoGlobal,
            'acciones_reglas' => $accionesReglas,
            'tipo_prestacion_lookup' => $this->buildTipoPrestacionLookup(),
            'tiene_67036' => $this->hasProcedureCode($procedimientos, '67036'),
            'fecha_facturacion' => $this->resolveFechaFacturacion($visita, $formulario, $protocolo, $this->billingAdapter->esCirugiaPorFormId($formId)),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildProcedureRows(array $context): array
    {
        $rows = [];
        $procedimientos = $context['procedimientos'];
        if (!is_array($procedimientos)) {
            return [];
        }

        foreach ($procedimientos as $index => $procedimiento) {
            if (!is_array($procedimiento)) {
                continue;
            }

            $codigo = (string) ($procedimiento['proc_codigo'] ?? '');
            $descripcion = $this->resolveTarifarioDescription($codigo, (string) ($procedimiento['proc_detalle'] ?? ''));
            $precioBase = (float) ($procedimiento['proc_precio'] ?? 0);

            if ($codigo === '67036') {
                $total = $precioBase * 0.625;
                for ($dup = 0; $dup < 2; $dup++) {
                $rows[] = $this->buildExportRow($context, [
                    'b' => '1',
                    'tipo' => $this->resolveProcedureType($context, $codigo),
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                        'cantidad' => '1',
                        'valor_unitario' => $total,
                        'total' => $total,
                        'ao' => '1',
                    ]);
                }
                continue;
            }

            if (!$context['es_cirugia'] && $index > 0) {
                continue;
            }

            $porcentaje = $context['tiene_67036']
                ? 0.5
                : (($index === 0 || stripos($descripcion, 'separado') !== false) ? 1.0 : 0.5);
            $total = $precioBase * $porcentaje;

            $rows[] = $this->buildExportRow($context, [
                'b' => '1',
                'tipo' => $this->resolveProcedureType($context, $codigo),
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'cantidad' => '1',
                'valor_unitario' => $total,
                'total' => $total,
                'ao' => '1',
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildSecondarySurgeonRows(array $context): array
    {
        if ($context['tiene_67036']) {
            return [];
        }

        $protocolo = is_array($context['protocolo']) ? $context['protocolo'] : [];
        if (empty($protocolo['cirujano_2']) && empty($protocolo['primer_ayudante'])) {
            return [];
        }

        $rows = [];
        foreach ($context['procedimientos'] as $index => $procedimiento) {
            if (!is_array($procedimiento)) {
                continue;
            }

            $codigo = (string) ($procedimiento['proc_codigo'] ?? '');
            $descripcion = $this->resolveTarifarioDescription($codigo, (string) ($procedimiento['proc_detalle'] ?? ''));
            $precio = (float) ($procedimiento['proc_precio'] ?? 0);
            $valorUnitario = $precio * ($index === 0 ? 0.2 : 0.1);

            $rows[] = $this->buildExportRow($context, [
                'tipo' => $this->resolveProcedureType($context, $codigo),
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'cantidad' => '1',
                'valor_unitario' => $valorUnitario,
                'total' => $valorUnitario,
                'ao' => '3',
                'fecha_facturacion' => $this->resolveFechaEgresoContext($context),
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildAnesthesiaRows(array $context): array
    {
        $rows = [];
        $procedimientos = is_array($context['procedimientos']) ? $context['procedimientos'] : [];
        if ($context['es_cirugia'] && $procedimientos !== [] && is_array($procedimientos[0])) {
            $principal = $procedimientos[0];
            $codigo = (string) ($principal['proc_codigo'] ?? '');
            $precioBase = (float) ($principal['proc_precio'] ?? 0);
            $valorUnitario = $codigo !== ''
                ? ($this->billingAdapter->obtenerValorAnestesia($codigo) ?? $precioBase)
                : $precioBase;
            $rows[] = $this->buildExportRow($context, [
                'tipo' => $this->resolveProcedureType($context, $codigo, true),
                'codigo' => $codigo,
                'descripcion' => $this->resolveTarifarioDescription($codigo, (string) ($principal['proc_detalle'] ?? '')),
                'cantidad' => '1',
                'valor_unitario' => $valorUnitario,
                'total' => $valorUnitario,
                'ao' => '6',
                'fecha_facturacion' => $this->resolveFechaEgresoContext($context),
            ]);
        }

        foreach ($context['anestesia'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $cantidad = (float) ($item['tiempo'] ?? 0);
            $valorUnitario = (float) ($item['valor2'] ?? 0);
            $rows[] = $this->buildExportRow($context, [
                'tipo' => $context['format'] === 'soam' ? 'AMB' : $this->resolveProcedureType($context, (string) ($item['codigo'] ?? ''), true),
                'codigo' => (string) ($item['codigo'] ?? ''),
                'descripcion' => (string) ($item['nombre'] ?? ''),
                'cantidad' => $cantidad,
                'valor_unitario' => $valorUnitario,
                'total' => $cantidad * $valorUnitario,
                'ao' => '6',
                'fecha_facturacion' => $this->resolveFechaEgresoContext($context),
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildSupplyRows(array $context): array
    {
        $rows = [];
        $fuenteDatos = [
            ['grupo' => 'FARMACIA', 'items' => array_merge($context['medicamentos'], $context['oxigeno'])],
            ['grupo' => 'INSUMOS', 'items' => $context['insumos']],
        ];

        foreach ($fuenteDatos as $bloque) {
            $grupo = (string) $bloque['grupo'];
            foreach ($bloque['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $descripcion = str_replace(["\r", "\n"], ' ', (string) ($item['nombre'] ?? $item['detalle'] ?? ''));
                if ($this->shouldExcludeSupply($context, $descripcion)) {
                    continue;
                }

                if (isset($item['litros'], $item['tiempo'], $item['valor2'])) {
                    $cantidad = (float) $item['tiempo'] * (float) $item['litros'] * 60;
                    $valorUnitario = (float) $item['valor2'];
                } else {
                    $cantidad = (float) ($item['cantidad'] ?? 1);
                    $valorUnitario = (float) ($item['precio'] ?? 0);
                }

                $rows[] = $this->buildExportRow($context, [
                    'tipo' => $grupo === 'FARMACIA' ? 'FAR' : 'IMM',
                    'codigo' => ltrim((string) ($item['codigo'] ?? ''), '0'),
                    'descripcion' => $descripcion,
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnitario,
                    'total' => $cantidad * $valorUnitario,
                    'ad' => $grupo === 'FARMACIA' ? '0' : '1',
                    'an' => $grupo === 'FARMACIA' ? 'M' : 'I',
                    'ao' => '',
                    'fecha_facturacion' => $this->resolveFechaEgresoContext($context),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildDerechoRows(array $context): array
    {
        $rows = [];
        foreach ($context['derechos'] as $servicio) {
            if (!is_array($servicio)) {
                continue;
            }

            $codigo = (string) ($servicio['codigo'] ?? '');
            $valorUnitario = (float) ($servicio['precio_afiliacion'] ?? 0);
            if ((int) $codigo >= 394200 && (int) $codigo < 394400) {
                $valorUnitario *= 1.02;
                $valorUnitario -= 0.01;
            }
            if ($codigo === '395281') {
                $valorUnitario *= 1.02;
            }

            $cantidad = (float) ($servicio['cantidad'] ?? 0);
            $rows[] = $this->buildExportRow($context, [
                'tipo' => $context['format'] === 'soam' ? 'AMB' : $this->resolveProcedureType($context, $codigo),
                'codigo' => $codigo,
                'descripcion' => (string) ($servicio['detalle'] ?? ''),
                'cantidad' => $cantidad,
                'valor_unitario' => $valorUnitario,
                'total' => $cantidad * $valorUnitario,
                'ao' => '',
                'fecha_facturacion' => $this->resolveFechaEgresoContext($context),
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $override
     * @return array<int, string|int|float|null>
     */
    private function buildExportRow(array $context, array $override): array
    {
        $format = (string) $context['format'];
        $isSoam = $format === 'soam';
        $hcNumber = (string) ($context['paciente']['hc_number'] ?? '');
        $nombrePaciente = (string) ($context['nombre_paciente'] ?? '');
        $sexo = (string) ($context['sexo'] ?? '--');
        $fechaNacimiento = (string) ($context['fecha_nacimiento'] ?? '');
        $edad = $context['edad'] ?? '';
        $fechaFacturacion = (string) ($override['fecha_facturacion'] ?? $context['fecha_facturacion'] ?? '');
        $abreviacionAfiliacion = (string) ($context['abreviacion_afiliacion'] ?? '');
        $codigoDerivacion = (string) ($context['codigo_derivacion'] ?? '');
        $cie10 = (string) ($context['cie10'] ?? '');
        $tipo = (string) ($override['tipo'] ?? '');
        $codigo = (string) ($override['codigo'] ?? '');
        $descripcion = (string) ($override['descripcion'] ?? '');
        $b = (string) ($override['b'] ?? '');
        $cantidad = $override['cantidad'] ?? '';
        $valorUnitario = $override['valor_unitario'] ?? '';
        $total = $override['total'] ?? '';
        $ad = (string) ($override['ad'] ?? '0');
        $ae = (string) ($override['ae'] ?? '0');
        $an = (string) ($override['an'] ?? 'P');
        $ao = (string) ($override['ao'] ?? '1');

        if ($isSoam) {
            return [
                '0000000135',
                $b,
                $fechaFacturacion,
                $abreviacionAfiliacion,
                $hcNumber,
                $nombrePaciente,
                $sexo,
                $fechaNacimiento,
                $edad === null ? '' : (string) $edad,
                $tipo,
                $codigo,
                $descripcion,
                $cie10,
                '',
                '',
                $this->stringifyNumber($cantidad),
                $this->formatNumber($valorUnitario, true),
                '',
                'T',
                $hcNumber,
                $nombrePaciente,
                'CVA',
                $codigoDerivacion,
                '1',
                'D',
                '0',
                '',
                '',
                '',
                $ad,
                $ae,
                'F',
                $this->formatNumber($total, true),
            ];
        }

        return [
            '0000000135',
            '',
            $fechaFacturacion,
            $abreviacionAfiliacion,
            $hcNumber,
            $nombrePaciente,
            $sexo,
            $fechaNacimiento,
            $edad === null ? '' : (string) $edad,
            $tipo,
            $codigo,
            $descripcion,
            $cie10,
            '',
            '',
            $this->stringifyNumber($cantidad),
            $this->formatNumber($valorUnitario, false),
            '',
            'T',
            $hcNumber,
            $nombrePaciente,
            '',
            $codigoDerivacion,
            '1',
            'D',
            '',
            '',
            '',
            '',
            $ad,
            $ae,
            $this->formatNumber($total, false),
            '',
            (string) ($context['fecha_ingreso_global'] ?? ''),
            (string) ($context['fecha_egreso_global'] ?? ''),
            '',
            'NO',
            '',
            'NO',
            $an,
            $ao,
            '',
            '',
            'F',
        ];
    }

    private function resolveFechaFacturacion(array $visita, array $formulario, array $protocolo, bool $esCirugia): string
    {
        $fechaVisita = $this->formatDate($visita['fecha'] ?? null);
        $fechaForm = $this->formatDate($formulario['fecha_fin'] ?? $formulario['fecha_inicio'] ?? $protocolo['fecha_inicio'] ?? null);
        return $esCirugia ? $fechaForm : $fechaVisita;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveFechaEgresoContext(array $context): string
    {
        $formulario = is_array($context['formulario']) ? $context['formulario'] : [];
        $protocolo = is_array($context['protocolo']) ? $context['protocolo'] : [];
        return $this->formatDate($formulario['fecha_fin'] ?? $formulario['fecha_inicio'] ?? $protocolo['fecha_inicio'] ?? null);
    }

    private function formatDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('d/m/Y', $timestamp);
    }

    private function extractCie10(string $diagnosticoStr): string
    {
        $diagnosticoStr = trim($diagnosticoStr);
        if ($diagnosticoStr === '') {
            return '';
        }

        $primerDiagnostico = explode(';', $diagnosticoStr)[0];
        return trim(explode(' ', explode('-', $primerDiagnostico)[0])[0]);
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     */
    private function hasProcedureCode(array $procedimientos, string $codigo): bool
    {
        foreach ($procedimientos as $procedimiento) {
            if ((string) ($procedimiento['proc_codigo'] ?? '') === $codigo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function flatColumns(): array
    {
        return [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL',
            'AM', 'AN', 'AO', 'AP', 'AQ', 'AR',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function soamColumns(): array
    {
        return [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG',
        ];
    }

    /**
     * @param array<int, string> $cols
     * @param array<int, string|int|float|null> $values
     */
    private function writeRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $cols, int $row, array $values): void
    {
        foreach ($cols as $index => $col) {
            $sheet->setCellValueExplicit($col . $row, (string) ($values[$index] ?? ''), DataType::TYPE_STRING);
        }
    }

    private function formatNumber(mixed $value, bool $commaDecimal): string
    {
        $number = (float) $value;
        return number_format($number, 2, $commaDecimal ? ',' : '.', '');
    }

    private function stringifyNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }

    /**
     * @return array<string, bool>
     */
    private function buildTipoPrestacionLookup(): array
    {
        return array_fill_keys([
            '76512', '92081', '92225', '281010', '281021', '281032',
            '281229', '281186', '281197', '281230', '281306', '281295',
        ], true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveProcedureType(array $context, string $codigo, bool $isAnesthesia = false): string
    {
        if ($context['format'] !== 'soam') {
            return $context['es_cirugia'] ? 'PRO/INTERV' : 'IMAGEN';
        }

        if ($isAnesthesia) {
            return 'AMB';
        }

        $lookup = is_array($context['tipo_prestacion_lookup'] ?? null) ? $context['tipo_prestacion_lookup'] : [];
        $codigo = trim($codigo);
        return ($codigo !== '' && isset($lookup[$codigo])) ? 'IMA' : 'AMB';
    }

    private function shouldExcludeSupply(array $context, string $descripcion): bool
    {
        $acciones = is_array($context['acciones_reglas'] ?? null) ? $context['acciones_reglas'] : [];
        foreach ($acciones as $accion) {
            if (!is_array($accion)) {
                continue;
            }
            if ((string) ($accion['tipo'] ?? '') !== 'excluir_insumo') {
                continue;
            }
            if (stripos($descripcion, (string) ($accion['parametro'] ?? '')) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolveTarifarioDescription(string $codigo, string $fallback = ''): string
    {
        $codigo = trim($codigo);
        $fallback = trim($fallback);
        if ($codigo === '') {
            return $fallback;
        }

        if (!array_key_exists($codigo, $this->tarifarioCache)) {
            $stmt = $this->db->prepare(
                'SELECT codigo, descripcion, short_description
                 FROM tarifario_2014
                 WHERE codigo = ? OR codigo = ?
                 LIMIT 1'
            );
            $stmt->execute([$codigo, ltrim($codigo, '0')]);
            $this->tarifarioCache[$codigo] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $row = $this->tarifarioCache[$codigo];
        if (!is_array($row)) {
            return $fallback;
        }

        $descripcion = trim((string) ($row['descripcion'] ?? ''));
        if ($descripcion !== '') {
            return $descripcion;
        }

        $shortDescription = trim((string) ($row['short_description'] ?? ''));
        return $shortDescription !== '' ? $shortDescription : $fallback;
    }
}
