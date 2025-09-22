<?php

namespace Services;

use PDO;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Resuelve la plantilla Excel según la afiliación.
     */
    private function resolverPlantilla(string $grupoAfiliacion): string
    {
        $base = __DIR__ . '/../../views/billing/';
        $slug = strtolower(trim($grupoAfiliacion));
        $candidatos = [
            "generar_excel_{$slug}RelatedCode.php",
            "generar_excel_{$slug}.php",
            "generar_excel_{$slug}_related.php",
            $slug . "/generar_excel.php",
        ];

        foreach ($candidatos as $rel) {
            $ruta = $base . $rel;
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        throw new \RuntimeException("No se encontró plantilla para '{$grupoAfiliacion}'");
    }

    /**
     * Genera un Excel y lo envía directamente al navegador.
     */
    public function generarExcel(array $datos, string $formId, string $grupoAfiliacion, string $modo = 'individual'): void
    {
        if (empty($grupoAfiliacion)) {
            die("Error: Debe especificar el grupo de afiliación.");
        }

        $GLOBALS['datos_facturacion'] = $datos;
        $GLOBALS['form_id_facturacion'] = $formId;

            $archivoPlantilla = $this->resolverPlantilla($grupoAfiliacion);

        if ($modo === 'bulk') {
            require $archivoPlantilla;
        } else {
            require_once __DIR__ . '/../../vendor/autoload.php';
            $spreadsheet = new Spreadsheet();
            $GLOBALS['spreadsheet'] = $spreadsheet;

            require $archivoPlantilla;

            $writer = new Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $paciente = $datos['paciente'];
            $filename = $paciente['hc_number'] . '_' . $paciente['lname'] . '_' . $paciente['fname'] . '.xlsx';
            header("Content-Disposition: attachment; filename=\"$filename\"");
            $writer->save('php://output');
            exit;
        }
    }

    /**
     * Genera un Excel en archivo físico.
     */
    public function generarExcelAArchivo(array $datos, string $formId, string $destino, string $grupoAfiliacion): bool
    {
        try {
        $GLOBALS['datos_facturacion'] = $datos;
        $GLOBALS['form_id_facturacion'] = $formId;

        require_once __DIR__ . '/../../vendor/autoload.php';
        $spreadsheet = new Spreadsheet();
        $GLOBALS['spreadsheet'] = $spreadsheet;

            $archivoPlantilla = $this->resolverPlantilla($grupoAfiliacion);

                ob_start();
            require $archivoPlantilla;
                $error_output = ob_get_clean();

                if (!empty($error_output)) {
                file_put_contents(__DIR__ . '/../../exportar_zip_log.txt', "❌ Error en $archivoPlantilla para $formId: $error_output\n", FILE_APPEND);
                return false;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($destino);
            return true;
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../../exportar_zip_log.txt', "❌ Error general: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Exporta todas las planillas de un mes para un grupo de afiliación.
     */
    public function exportarPlanillasPorMes(string $mes, string $grupoAfiliacion, callable $obtenerDatos): void
    {
        $stmt = $this->db->prepare("
            SELECT pd.form_id
            FROM protocolo_data pd
            JOIN patient_data pa ON pa.hc_number = pd.hc_number
            WHERE DATE_FORMAT(pd.fecha_inicio, '%Y-%m') = ?
              AND UPPER(pa.afiliacion) = ?
              AND pd.status = 1
        ");
        $stmt->execute([$mes, strtoupper($grupoAfiliacion)]);
        $formIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($formIds)) {
            die("No hay planillas para el mes indicado.");
        }

        $excelFiles = [];
        foreach ($formIds as $formId) {
            $datos = $obtenerDatos($formId);
            if (!$datos) continue;

            $tempFile = sys_get_temp_dir() . '/' . uniqid("excel_{$formId}_") . '.xlsx';
            $ok = $this->generarExcelAArchivo($datos, $formId, $tempFile, $grupoAfiliacion);

            if ($ok && file_exists($tempFile)) {
                $paciente = $datos['paciente'];
                $filename = $paciente['hc_number'] . "_" . $paciente['lname'] . "_" . $paciente['fname'] . ".xlsx";
                $excelFiles[] = ['path' => $tempFile, 'name' => $filename];
            }
        }

        if (empty($excelFiles)) {
            die("No se generaron archivos.");
        }

        $publicDir = __DIR__ . '/../../tmp';
        if (!is_dir($publicDir)) mkdir($publicDir, 0777, true);

        foreach ($excelFiles as $file) {
            copy($file['path'], $publicDir . '/' . $file['name']);
        }

        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Descargando archivos...</title></head><body>";
        echo "<p>Descargando archivos...</p>";
        echo "<script>";
        echo "window.onload = function() {";
        foreach ($excelFiles as $file) {
            $url = '/tmp/' . rawurlencode($file['name']);
            echo "var a=document.createElement('a');a.href='$url';a.download='';document.body.appendChild(a);a.click();a.remove();";
        }
        echo "setTimeout(()=>window.close(),5000);";
        echo "}";
        echo "</script></body></html>";
        exit;
    }
}