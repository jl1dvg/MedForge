<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Support\InformesHelper;
use Modules\Consulta\Services\ConsultaReportService;
use Modules\Reporting\Services\ReportService;
use Modules\Reporting\Support\SolicitudDataFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use ZipArchive;

class BillingConsolidadoExportService
{
    public function __construct(
        private readonly BillingInformeDataService $billingService,
        private readonly BillingInformePacienteService $pacienteService
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename:string,content:string,content_type:string}
     */
    public function exportSimple(string $grupo, array $filters): array
    {
        $grupo = strtolower(trim($grupo));
        if (!in_array($grupo, ['iess', 'isspol', 'issfa'], true)) {
            throw new \InvalidArgumentException('Grupo de consolidado no soportado: ' . $grupo);
        }

        $categoria = $this->sanitizeCategoria((string) ($filters['categoria'] ?? ''));
        $formIds = $this->extractFormIds($filters['form_ids'] ?? []);
        unset($filters['categoria']);
        unset($filters['form_ids']);

        $facturas = $this->billingService->obtenerFacturasDisponibles();
        $pacientesCache = [];
        $datosCache = [];
        $cacheDerivaciones = [];
        $sedesCache = [];
        $afiliacionesPermitidas = $this->resolveAfiliacionesPermitidas($grupo);

        $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
            $facturas,
            $filters,
            $this->billingService,
            $this->pacienteService,
            $afiliacionesPermitidas,
            $categoria,
            $cacheDerivaciones,
            $pacientesCache,
            $datosCache,
            $sedesCache
        );
        $consolidado = $this->filtrarConsolidadoPorFormIds($consolidado, $formIds);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consolidado ' . strtoupper($grupo));

        $headers = [
            '# Expediente',
            'Cédula',
            'Apellidos',
            'Nombre',
            'Fecha Ingreso',
            'Fecha Egreso',
            'CIE10',
            'Descripción',
            '# Hist. C.',
            'Edad',
            'Ge',
            'Items',
            'Monto Sol.',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $n = 1;
        foreach ($consolidado as $pacientes) {
            foreach ($pacientes as $factura) {
                $hcNumber = (string) ($factura['hc_number'] ?? '');
                $formId = (string) ($factura['form_id'] ?? '');
                if ($hcNumber === '' || $formId === '') {
                    continue;
                }

                if (!isset($pacientesCache[$hcNumber])) {
                    $pacientesCache[$hcNumber] = $this->pacienteService->getPatientDetails($hcNumber);
                }
                $paciente = $pacientesCache[$hcNumber] ?? [];

                if (!isset($cacheDerivaciones[$formId])) {
                    $cacheDerivaciones[$formId] = $this->billingService->obtenerDerivacionPorFormId($formId);
                }
                $derivacion = $cacheDerivaciones[$formId] ?? [];

                if (!isset($datosCache[$formId])) {
                    $datosCache[$formId] = $this->billingService->obtenerDatos($formId);
                }
                $datos = $datosCache[$formId] ?? null;

                $apellido = trim((string) ($paciente['lname'] ?? '') . ' ' . (string) ($paciente['lname2'] ?? ''));
                $nombre = trim((string) ($paciente['fname'] ?? '') . ' ' . (string) ($paciente['mname'] ?? ''));
                $edad = $this->pacienteService->calcularEdad(
                    isset($paciente['fecha_nacimiento']) ? (string) $paciente['fecha_nacimiento'] : null,
                    isset($factura['fecha']) ? (string) $factura['fecha'] : null
                );
                $genero = !empty($paciente['sexo']) ? strtoupper(substr((string) $paciente['sexo'], 0, 1)) : '--';
                $cie10 = InformesHelper::extraerCie10((string) ($derivacion['diagnostico'] ?? ''));
                if ($cie10 === '') {
                    $cie10 = '--';
                }

                $descripcion = '--';
                $items = 0;
                if (is_array($datos)) {
                    $descripcion = (string) ($datos['procedimientos'][0]['proc_detalle'] ?? '--');
                    $items = $this->contarItemsFactura($datos);
                }

                $fecha = isset($factura['fecha']) && strtotime((string) $factura['fecha'])
                    ? date('d/m/Y', strtotime((string) $factura['fecha']))
                    : '';

                $sheet->setCellValue("A{$row}", strtoupper($grupo) . '-' . $n);
                $sheet->setCellValue("B{$row}", $hcNumber);
                $sheet->setCellValue("C{$row}", $apellido);
                $sheet->setCellValue("D{$row}", $nombre);
                $sheet->setCellValue("E{$row}", $fecha);
                $sheet->setCellValue("F{$row}", $fecha);
                $sheet->setCellValue("G{$row}", $cie10);
                $sheet->setCellValue("H{$row}", $descripcion);
                $sheet->setCellValue("I{$row}", $hcNumber);
                $sheet->setCellValue("J{$row}", $edad ?? '');
                $sheet->setCellValue("K{$row}", $genero);
                $sheet->setCellValue("L{$row}", $items > 0 ? $items : 75);
                $sheet->setCellValue("M{$row}", number_format((float) ($factura['total'] ?? 0), 2, '.', ''));

                $row++;
                $n++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return [
            'filename' => 'consolidado_' . $grupo . ($categoria ? '_' . $categoria : '') . '.xlsx',
            'content' => is_string($content) ? $content : '',
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename:string,content:string,content_type:string}
     */
    public function exportIessSoam(array $filters, bool $zipSolicitado): array
    {
        $categoria = $this->sanitizeCategoria((string) ($filters['categoria'] ?? ''));
        $formIds = $this->extractFormIds($filters['form_ids'] ?? []);
        unset($filters['categoria'], $filters['form_ids']);

        $facturas = $this->billingService->obtenerFacturasDisponibles();
        $cacheDerivaciones = [];
        $pacientesCache = [];
        $datosCache = [];
        $sedesCache = [];

        $consolidado = InformesHelper::obtenerConsolidadoFiltrado(
            $facturas,
            $filters,
            $this->billingService,
            $this->pacienteService,
            $this->resolveAfiliacionesPermitidas('iess'),
            $categoria,
            $cacheDerivaciones,
            $pacientesCache,
            $datosCache,
            $sedesCache
        );
        $consolidado = $this->filtrarConsolidadoPorFormIds($consolidado, $formIds);

        $formIdsConsolidado = [];
        foreach ($consolidado as $pacientesDelMes) {
            foreach ($pacientesDelMes as $factura) {
                $formId = trim((string) ($factura['form_id'] ?? ''));
                if ($formId !== '') {
                    $formIdsConsolidado[] = $formId;
                }
            }
        }
        $formIdsConsolidado = array_values(array_unique($formIdsConsolidado));
        if ($formIdsConsolidado === []) {
            throw new \RuntimeException('No se encontraron datos para el consolidado SOAM.');
        }

        $spreadsheet = $this->buildIessSoamSpreadsheet($formIdsConsolidado);
        $baseFileName = 'consolidado_iess_soam' . ($categoria ? '_' . $categoria : '');

        if (!$zipSolicitado) {
            return [
                'filename' => $baseFileName . '.xlsx',
                'content' => $this->renderSpreadsheet($spreadsheet),
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        return $this->buildIessSoamZip($spreadsheet, $consolidado, $baseFileName);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function contarItemsFactura(array $datos): int
    {
        $total = 0;
        $total += count($datos['procedimientos'] ?? []);
        $total += count($datos['anestesia'] ?? []);
        $total += count($datos['medicamentos'] ?? []);
        $total += count($datos['oxigeno'] ?? []);
        $total += count($datos['insumos'] ?? []);
        $total += count($datos['derechos'] ?? []);
        return $total;
    }

    private function sanitizeCategoria(string $categoria): ?string
    {
        $categoria = strtolower(trim($categoria));
        if ($categoria === '') {
            return null;
        }
        return in_array($categoria, ['procedimientos', 'consulta', 'imagenes'], true)
            ? $categoria
            : null;
    }

    /**
     * @param array<int, string> $formIds
     */
    private function buildIessSoamSpreadsheet(array $formIds): Spreadsheet
    {
        $generatorPath = base_path('../views/billing/generar_excel_iess_soam.php');
        if (!is_file($generatorPath)) {
            throw new \RuntimeException('No se encontró el generador SOAM.');
        }

        $prevGet = $_GET;
        try {
            $_GET['form_id'] = implode(',', $formIds);
            $GLOBALS['spreadsheet'] = null;
            include $generatorPath;
            $spreadsheet = $GLOBALS['spreadsheet'] ?? null;
        } finally {
            $_GET = $prevGet;
        }

        if (!($spreadsheet instanceof Spreadsheet)) {
            throw new \RuntimeException('No se pudo generar el consolidado SOAM.');
        }

        return $spreadsheet;
    }

    private function renderSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return is_string($content) ? $content : '';
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $consolidado
     * @return array{filename:string,content:string,content_type:string}
     */
    private function buildIessSoamZip(Spreadsheet $spreadsheet, array $consolidado, string $baseFileName): array
    {
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'soam_zip_' . uniqid();
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException('No se pudo preparar el zip del consolidado SOAM.');
        }

        $excelName = $baseFileName . '.xlsx';
        $excelPath = $tmpDir . DIRECTORY_SEPARATOR . $excelName;
        $writer = new Xlsx($spreadsheet);
        $writer->save($excelPath);

        $zipName = $baseFileName . '.zip';
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP del consolidado SOAM.');
        }

        $zip->addFile($excelPath, $excelName);

        $consultaReportService = new ConsultaReportService($this->resolvePdo());
        $reportService = new ReportService();
        $pairs = [];

        foreach ($consolidado as $pacientesDelMes) {
            foreach ($pacientesDelMes as $factura) {
                $formId = (string) ($factura['form_id'] ?? '');
                $hcNumber = (string) ($factura['hc_number'] ?? '');
                if ($formId === '' || $hcNumber === '') {
                    continue;
                }

                $pairKey = $formId . '|' . $hcNumber;
                if (isset($pairs[$pairKey])) {
                    continue;
                }
                $pairs[$pairKey] = true;

                $data = $consultaReportService->buildConsultaReportData($hcNumber, $formId);
                if ($data === []) {
                    continue;
                }

                $data = SolicitudDataFormatter::enrich($data, $formId, $hcNumber);
                $filename = sprintf('consulta_iess_%s_%s.pdf', $formId, $hcNumber);

                try {
                    $pdfContent = $reportService->renderPdf('002', $data, [
                        'filename' => $filename,
                    ]);
                } catch (\Throwable) {
                    continue;
                }

                if ($pdfContent !== '') {
                    $zip->addFromString($filename, $pdfContent);
                }
            }
        }

        $zip->close();
        $content = file_get_contents($zipPath);

        if (is_file($excelPath)) {
            @unlink($excelPath);
        }
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
        if (is_dir($tmpDir)) {
            @rmdir($tmpDir);
        }

        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('No se pudo generar el archivo ZIP del consolidado SOAM.');
        }

        return [
            'filename' => $zipName,
            'content' => $content,
            'content_type' => 'application/zip',
        ];
    }

    private function resolvePdo(): PDO
    {
        return $this->billingService->getPdo();
    }

    /**
     * @return array<int, string>
     */
    private function resolveAfiliacionesPermitidas(string $grupo): array
    {
        if ($grupo !== 'iess') {
            return [$grupo];
        }

        return [
            'contribuyente voluntario',
            'conyuge',
            'conyuge pensionista',
            'seguro campesino',
            'seguro campesino jubilado',
            'seguro general',
            'seguro general jubilado',
            'seguro general por montepio',
            'seguro general tiempo parcial',
            'iess',
            'hijos dependientes',
        ];
    }

    /**
     * @param mixed $rawFormIds
     * @return array<int, string>
     */
    private function extractFormIds(mixed $rawFormIds): array
    {
        if (is_array($rawFormIds)) {
            $formIds = $rawFormIds;
        } elseif (is_string($rawFormIds) && trim($rawFormIds) !== '') {
            $formIds = preg_split('/\s*,\s*/', $rawFormIds) ?: [];
        } else {
            $formIds = [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $formIds
        ))));
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $consolidado
     * @param array<int, string> $formIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function filtrarConsolidadoPorFormIds(array $consolidado, array $formIds): array
    {
        if ($formIds === []) {
            return $consolidado;
        }

        $lookup = array_fill_keys($formIds, true);
        foreach ($consolidado as $mes => $pacientesDelMes) {
            $consolidado[$mes] = array_values(array_filter(
                $pacientesDelMes,
                static function (array $factura) use ($lookup): bool {
                    $formId = trim((string) ($factura['form_id'] ?? ''));
                    return $formId !== '' && isset($lookup[$formId]);
                }
            ));
            if ($consolidado[$mes] === []) {
                unset($consolidado[$mes]);
            }
        }

        return $consolidado;
    }
}
