<?php

namespace Controllers;

use PDO;
use Models\BillingMainModel;
use Models\BillingProcedimientosModel;
use Models\BillingDerechosModel;
use Models\BillingInsumosModel;
use Models\BillingOxigenoModel;
use Models\BillingAnestesiaModel;
use Exception;
use Models\ProtocoloModel;

class BillingController
{
    private $db;
    private ProtocoloModel $protocoloModel;
    private BillingMainModel $billingMainModel;
    private BillingProcedimientosModel $billingProcedimientosModel;
    private BillingDerechosModel $billingDerechosModel;
    private BillingInsumosModel $billingInsumosModel;
    private BillingOxigenoModel $billingOxigenoModel;
    private BillingAnestesiaModel $billingAnestesiaModel;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->billingMainModel = new BillingMainModel($pdo);
        $this->billingProcedimientosModel = new BillingProcedimientosModel($pdo);
        $this->billingDerechosModel = new BillingDerechosModel($pdo);
        $this->billingInsumosModel = new BillingInsumosModel($pdo);
        $this->billingOxigenoModel = new BillingOxigenoModel($pdo);
        $this->billingAnestesiaModel = new BillingAnestesiaModel($pdo);
    }

    public function guardar(array $data): array
    {
        try {
            $this->db->beginTransaction();

            $billing = $this->billingMainModel->findByFormId($data['form_id']);

            if ($billing) {
                $billingId = $billing['id'];
                $this->borrarDetalles($billingId);
                $this->billingMainModel->update($data['hcNumber'], $billingId);
            } else {
                $billingId = $this->billingMainModel->insert($data['hcNumber'], $data['form_id']);
            }

            $stmt = $this->db->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
            $stmt->execute([$data['form_id']]);
            $fechaInicio = $stmt->fetchColumn();
            if ($fechaInicio) {
                $this->billingMainModel->updateFechaCreacion($billingId, $fechaInicio);
            }

            foreach ($data['procedimientos'] as $p) {
                $this->billingProcedimientosModel->insertar($billingId, $p);
            }

            foreach ($data['derechos'] as $d) {
                $this->billingDerechosModel->insertar($billingId, $d);
            }

            foreach ($data['insumos'] as $i) {
                $this->billingInsumosModel->insertar($billingId, $i);
            }

            foreach ($data['oxigeno'] as $o) {
                $this->billingOxigenoModel->insertar($billingId, $o);
            }

            foreach ($data['anestesiaTiempo'] as $a) {
                $this->billingAnestesiaModel->insertar($billingId, $a);
            }

            $this->db->commit();
            return ["success" => true, "message" => "Billing guardado correctamente"];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "message" => "Error al guardar billing: " . $e->getMessage()];
        }
    }

    public function obtenerDatos(string $formId): ?array
    {
        // Obtener billing_main
        $stmt = $this->db->prepare("SELECT * FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$billing) {
            return null;
        }

        $billingId = $billing['id'];

        // Obtener datos adicionales del paciente y formulario
        require_once __DIR__ . '/PacienteController.php';
        require_once __DIR__ . '/GuardarProyeccionController.php';
        $pacienteController = new \Controllers\PacienteController($this->db);
        $guardarProyeccionController = new \Controllers\GuardarProyeccionController($this->db);
        $pacienteInfo = $pacienteController->getPatientDetails($billing['hc_number']);
        $formDetails = $pacienteController->getDetalleSolicitud($billing['hc_number'], $formId);
        $visita = $guardarProyeccionController->obtenerDatosPacientePorFormId($formId);

        // Obtener protocoloExtendido usando ProtocoloModel
        $protocoloExtendido = $this->protocoloModel->obtenerProtocolo($formId, $billing['hc_number']);

        // Obtener procedimientos
        $procedimientos = $this->billingProcedimientosModel->obtenerPorBillingId($billingId);

        // Obtener derechos
        $derechos = $this->billingDerechosModel->obtenerPorBillingId($billingId);

        // Obtener insumos
        $insumos = $this->billingInsumosModel->obtenerPorBillingId($billingId);

        $insumosConIVA = array_filter($insumos, fn($i) => isset($i['iva']) && (int)$i['iva'] === 1);

        // Obtener medicamentos (iva = 0) y mapear código según afiliación
        $medicamentosSinIVA = array_filter($insumos, fn($i) => isset($i['iva']) && (int)$i['iva'] === 0);
        //var_dump($medicamentosSinIVA);

        if (!empty($medicamentosSinIVA)) {
            $codigos = array_unique(array_filter(array_map(fn($m) => $m['codigo'], $medicamentosSinIVA)));

            if (!empty($codigos)) {
                $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                $stmt = $this->db->prepare("SELECT codigo_isspol, codigo_issfa, codigo_msp, codigo_iess, nombre FROM insumos WHERE codigo_isspol IN ($placeholders)");
                $stmt->execute(array_values($codigos));
                $insumosReferencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $referenciaMap = [];
                foreach ($insumosReferencia as $r) {
                    $referenciaMap[$r['codigo_isspol']] = $r;
                }

                $afiliacion = $pacienteInfo['afiliacion'] ?? '';
                foreach ($medicamentosSinIVA as &$med) {
                    $med = $this->ajustarCodigoPorAfiliacion($med, $afiliacion, $referenciaMap);
                }
                unset($med);
            }
        }

        // Obtener oxigeno
        $oxigeno = $this->billingOxigenoModel->obtenerPorBillingId($billingId);

        // Obtener anestesia
        $anestesia = $this->billingAnestesiaModel->obtenerPorBillingId($billingId);

        return [
            'billing' => $billing,
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $insumosConIVA,
            'medicamentos' => $medicamentosSinIVA,
            'oxigeno' => $oxigeno,
            'anestesia' => $anestesia,
            'paciente' => $pacienteInfo,
            'visita' => $visita,
            'formulario' => $formDetails,
            'protocoloExtendido' => $protocoloExtendido,
        ];
    }

    private function borrarDetalles(int $billingId): void
    {
        $tablas = [
            'billing_procedimientos',
            'billing_derechos',
            'billing_insumos',
            'billing_oxigeno',
            'billing_anestesia'
        ];

        foreach ($tablas as $tabla) {
            $stmt = $this->db->prepare("DELETE FROM $tabla WHERE billing_id = ?");
            $stmt->execute([$billingId]);
        }
    }

    /**
     * Ajusta el código y nombre del medicamento según la afiliación y el mapa de referencia.
     */
    private function ajustarCodigoPorAfiliacion(array $medicamento, string $afiliacion, array $referenciaMap): array
    {
        $codigoClave = $medicamento['codigo'] ?? '';
        $referencia = $referenciaMap[$codigoClave] ?? null;

        if ($referencia) {
            switch ($afiliacion) {
                case 'ISSFA':
                    $medicamento['codigo'] = $referencia['codigo_issfa'] ?? $codigoClave;
                    break;
                case 'MSP':
                    $medicamento['codigo'] = $referencia['codigo_msp'] ?? $codigoClave;
                    break;
                case 'IESS':
                    $medicamento['codigo'] = $referencia['codigo_iess'] ?? $codigoClave;
                    break;
                case 'ISSPOL':
                    $medicamento['codigo'] = $referencia['codigo_isspol'] ?? $codigoClave;
                    break;
            }

            $medicamento['nombre'] = $referencia['nombre'] ?? $medicamento['nombre'];
        }

        return $medicamento;
    }

    public function obtenerValorAnestesia(string $codigo): ?float
    {
        $stmt = $this->db->prepare("SELECT anestesia_nivel3 FROM tarifario_2014 WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1");
        $stmt->execute([
            'codigo' => $codigo,
            'codigo_sin_0' => ltrim($codigo, '0')
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? (float)$resultado['anestesia_nivel3'] : null;
    }

    /**
     * Resuelve la ruta del archivo de plantilla para generar Excel según el grupo de afiliación.
     * Intenta varias convenciones de nombre para tolerar diferencias.
     */
    private function resolverPlantilla(string $grupoAfiliacion): string
    {
        $base = __DIR__ . '/../views/billing/';
        $slug = strtolower(trim($grupoAfiliacion));
        $candidatos = [
            "generar_excel_{$slug}RelatedCode.php",
            "generar_excel_{$slug}.php",
            "generar_excel_{$slug}_related.php",
            $slug . "/generar_excel.php",
        ];
        $probados = [];
        foreach ($candidatos as $rel) {
            $ruta = $base . $rel;
            $probados[] = $ruta;
            if (is_file($ruta)) {
                return $ruta;
            }
        }
        throw new \RuntimeException(
            "No se encontró plantilla para '{$grupoAfiliacion}'. Buscado en:\n - " . implode("\n - ", $probados)
        );
    }

    public function generarExcel(string $formId, string $grupoAfiliacion = ''): void
    {
        $datos = $this->obtenerDatos($formId);

        // Validar que se pase grupo de afiliación
        if (empty($grupoAfiliacion)) {
            die("Error: Debe especificar el grupo de afiliación (IESS, ISSPOL, ISSFA, MSP, etc).");
        }

        // Código normal que puedes volver a activar después de revisar
        $afiliacion = strtoupper($datos['paciente']['afiliacion'] ?? '');
        $GLOBALS['datos_facturacion'] = $datos;
        $GLOBALS['form_id_facturacion'] = $formId;

        $modo = $_GET['modo'] ?? 'individual';
        // Asignar plantilla obligatoriamente según grupo de afiliación
        try {
            $archivoPlantilla = $this->resolverPlantilla($grupoAfiliacion);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            die("Error: " . nl2br(htmlentities($e->getMessage(), ENT_QUOTES, 'UTF-8')));
        }

        if ($modo === 'bulk') {
            require $archivoPlantilla;
        } else {
            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $GLOBALS['spreadsheet'] = $spreadsheet;
            require $archivoPlantilla;

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $pacienteInfo = $datos['paciente'];
            $filename = $pacienteInfo['hc_number'] . '_' . $pacienteInfo['lname'] . '_' . $pacienteInfo['fname'] . '.xlsx';
            header("Content-Disposition: attachment; filename=\"$filename\"");
            $writer->save('php://output');
            exit;
        }
    }

    public function generarExcelAArchivo(string $formId, string $destino, string $grupoAfiliacion = ''): bool
    {
        try {
            // Verifica que exista billing_main
            $stmt = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
            $stmt->execute([$formId]);
            $billingId = $stmt->fetchColumn();
            if (!$billingId) return false;

            $datos = $this->obtenerDatos($formId);
            if (!$datos || !isset($datos['paciente']['hc_number'])) return false;

            // Parámetros globales requeridos
            $GLOBALS['datos_facturacion'] = $datos;
            $GLOBALS['form_id_facturacion'] = $formId;

            $afiliacion = strtoupper($datos['paciente']['afiliacion'] ?? '');
            if ($grupoAfiliacion && $afiliacion !== strtoupper($grupoAfiliacion)) return false;

            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $GLOBALS['spreadsheet'] = $spreadsheet;

            try {
                $archivoPlantilla = $this->resolverPlantilla($grupoAfiliacion);
            } catch (\RuntimeException $e) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Plantilla no encontrada para {$grupoAfiliacion}: " . $e->getMessage() . "\n", FILE_APPEND);
                return false;
            }
            try {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);

                ob_start();
                require $archivoPlantilla;
                $error_output = ob_get_clean();

                if (!empty($error_output)) {
                    file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error fatal incluyendo $archivoPlantilla para form_id $formId: $error_output\n", FILE_APPEND);
                    return false;
                }
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error en $archivoPlantilla para form_id $formId: " . $e->getMessage() . "\n", FILE_APPEND);
                return false;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($destino);
            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error general generando Excel para form_id $formId: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function exportarPlanillasPorMes(string $mes, string $grupoAfiliacion = 'ISSPOL'): void
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

        file_put_contents(__DIR__ . '/exportar_zip_log.txt', "== Exportando planillas del mes $mes ==\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/exportar_zip_log.txt', "Cantidad de formIds encontrados: " . count($formIds) . "\n", FILE_APPEND);

        $excelFiles = [];
        foreach ($formIds as $formId) {
            // Verificar si es ISSPOL para debug
            $stmtDebug = $this->db->prepare("
                SELECT pd.form_id, pd.fecha_inicio, pa.afiliacion, bm.id AS billing_id
                FROM protocolo_data pd
                LEFT JOIN billing_main bm ON bm.form_id = pd.form_id
                LEFT JOIN patient_data pa ON pa.hc_number = pd.hc_number
                WHERE pd.form_id = ?
                LIMIT 1
            ");
            $stmtDebug->execute([$formId]);
            $debugRow = $stmtDebug->fetch(\PDO::FETCH_ASSOC);
            $logLine = "↪ form_id {$debugRow['form_id']} | Afiliacion: {$debugRow['afiliacion']} | Billing ID: " . ($debugRow['billing_id'] ?? 'NO') . "\n";
            file_put_contents(__DIR__ . '/exportar_zip_log.txt', $logLine, FILE_APPEND);
            file_put_contents(__DIR__ . '/exportar_zip_log.txt', "Procesando form_id $formId\n", FILE_APPEND);
            // Verificar que exista billing_main para este form_id y loguear el id si existe
            $stmtCheck = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
            $stmtCheck->execute([$formId]);
            $billingId = $stmtCheck->fetchColumn();
            if (!$billingId) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "✘ No hay billing_main registrado para el form_id: $formId\n", FILE_APPEND);
                continue;
            } else {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "✔ billing_main encontrado: billing_id = $billingId para el form_id: $formId\n", FILE_APPEND);
            }
            $datos = $this->obtenerDatos($formId);
            // Asegurar parámetros globales y GET antes de generar Excel
            $_GET['form_id'] = $formId;
            $GLOBALS['form_id_facturacion'] = $formId;
            $GLOBALS['datos_facturacion'] = $datos;

            $afiliacion = strtoupper($datos['paciente']['afiliacion'] ?? '');
            file_put_contents(__DIR__ . '/exportar_zip_log.txt', "Afiliación: $afiliacion\n", FILE_APPEND);

            // Usar un nombre temporal único y persistente para cada archivo Excel
            $tempFile = sys_get_temp_dir() . '/' . uniqid("excel_{$formId}_") . '.xlsx';

            try {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "→ Iniciando generación de Excel para $formId...\n", FILE_APPEND);
                $ok = $this->generarExcelAArchivo($formId, $tempFile, $grupoAfiliacion);
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "← Finalizó intento de generación de Excel para $formId, resultado: " . ($ok ? 'Éxito' : 'Error') . "\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Excepción al generar Excel para $formId: " . $e->getMessage() . "\n", FILE_APPEND);
                $ok = false;
            }
            if ($ok && file_exists($tempFile) && filesize($tempFile) > 0) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "✔ Excel generado para $formId\n", FILE_APPEND);
                $filename = $datos['paciente']['hc_number'] . "_" . $datos['paciente']['lname'] . "_" . $datos['paciente']['fname'] . ".xlsx";
                // Guardar info para descarga posterior
                $excelFiles[] = ['path' => $tempFile, 'name' => $filename];
            } else {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "✘ Error generando Excel para $formId\n", FILE_APPEND);
            }
        }

        if (empty($excelFiles)) {
            die("No se generaron archivos para exportar.");
        }

        // Copiar los archivos al directorio público y preparar para descarga automática
        $publicDir = __DIR__ . '/../tmp';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0777, true);
        }
        foreach ($excelFiles as $file) {
            $publicPath = $publicDir . '/' . $file['name'];
            copy($file['path'], $publicPath);
        }
        // Página HTML con redirección y descarga automática por JavaScript
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Descargando archivos...</title>";
        echo "<script>";
        echo "window.onload = function() {";
        foreach ($excelFiles as $file) {
            $url = '/tmp/' . rawurlencode($file['name']);
            echo "var a = document.createElement('a'); a.href = '$url'; a.download = ''; document.body.appendChild(a); a.click(); document.body.removeChild(a);";
        }
        echo "setTimeout(function() { window.close(); }, 5000);";
        echo "};";
        echo "</script></head><body>";
        echo "<p>Iniciando descarga automática de archivos...</p>";
        echo "</body></html>";
        exit;
    }

    public function obtenerFacturasDisponibles(): array
    {
        $stmt = $this->db->query("
            SELECT 
                bm.id, 
                bm.form_id, 
                bm.hc_number, 
            COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_ordenada
            FROM billing_main bm
            LEFT JOIN protocolo_data pd ON bm.form_id = pd.form_id
            LEFT JOIN procedimiento_proyectado pp ON bm.form_id = pp.form_id
        ORDER BY fecha_ordenada DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function obtenerDerivacionPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT * FROM derivaciones_form_id WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // Devuelve un array con todas las columnas
    }

    function abreviarAfiliacion($afiliacion)
    {
        $mapa = [
            'contribuyente voluntario' => 'CV',
            'conyuge' => 'CY',
            'conyuge pensionista' => 'CP',
            'seguro campesino' => 'SC',
            'seguro campesino jubilado' => 'CJ',
            'seguro general' => 'SG',
            'seguro general jubilado' => 'JU',
            'seguro general por montepio' => 'MO',
            'seguro general tiempo parcial' => 'TP'
        ];
        $normalizado = strtolower(trim($afiliacion));
        return $mapa[$normalizado] ?? strtoupper($afiliacion);
    }

    public function obtenerFormIdsFacturados(): array
    {
        $stmt = $this->db->query("SELECT form_id FROM billing_main");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function obtenerBillingIdPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn();
    }

    public function esCirugiaPorFormId($formId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM protocolo_data WHERE form_id = ? LIMIT 1");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn() !== false;
    }

    public function obtenerFechasIngresoYEgreso(array $formIds): array
    {
        $fechas = [];

        foreach ($formIds as $formId) {
            // 1. Buscar en protocolo_data
            $stmt = $this->db->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($row['fecha_inicio']) && $row['fecha_inicio'] !== '0000-00-00') {
                $fechas[] = $row['fecha_inicio'];
                continue;
            }

            // 2. Si no hay en protocolo_data, buscar en procedimiento_proyectado
            $stmt = $this->db->prepare("SELECT fecha FROM procedimiento_proyectado WHERE form_id = ?");
            $stmt->execute([$formId]);
            while ($procRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($procRow['fecha']) && $procRow['fecha'] !== '0000-00-00') {
                    $fechas[] = $procRow['fecha'];
                }
            }
        }

        if (empty($fechas)) {
            return ['ingreso' => null, 'egreso' => null];
        }

        usort($fechas, fn($a, $b) => strtotime($a) <=> strtotime($b));

        return [
            'ingreso' => $fechas[0],
            'egreso' => end($fechas),
        ];
    }

    public function procedimientosNoFacturadosClasificados()
    {
        // Unifica quirúrgicos (con protocolo_data) y no quirúrgicos (sin protocolo_data)
        $afiliaciones = [
            'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
            'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
            'seguro general por montepío', 'seguro general tiempo parcial', 'iess'
        ];
        // 1. Procedimientos quirúrgicos (con protocolo_data)
        $queryQuirurgicos = "
            SELECT 
                pr.form_id,
                pr.hc_number,
                pd.fecha_inicio AS fecha,
                pd.status,
                pr.procedimiento_proyectado AS nombre_procedimiento,
                pa.afiliacion,
                pa.fname,
                pa.mname,
                pa.lname,
                pa.lname2
            FROM protocolo_data pd
            JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
            JOIN patient_data pa ON pa.hc_number = pd.hc_number
            WHERE pd.form_id NOT IN (
                SELECT form_id FROM billing_main
            )
            AND pd.fecha_inicio >= '2024-11-01'
            AND LOWER(pa.afiliacion) IN (" . implode(', ', array_map(fn($a) => $this->db->quote($a), $afiliaciones)) . ")
        ";
        // 2. Procedimientos NO quirúrgicos (sin protocolo_data)
        $queryNoQuirurgicos = "
            SELECT 
                pr.form_id,
                pr.hc_number,
                pr.fecha AS fecha,
                pr.procedimiento_proyectado AS nombre_procedimiento,
                pa.afiliacion,
                pa.fname,
                pa.mname,
                pa.lname,
                pa.lname2
            FROM procedimiento_proyectado pr
            LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
            JOIN patient_data pa ON pa.hc_number = pr.hc_number
            WHERE pr.form_id NOT IN (
                SELECT form_id FROM billing_main
            )
            AND pd.form_id IS NULL
            AND pr.fecha >= '2024-11-01'
            AND LOWER(pa.afiliacion) IN (" . implode(', ', array_map(fn($a) => $this->db->quote($a), $afiliaciones)) . ")
        ";
        // Ejecutar ambos y unificar
        $quirurgicosRaw = $this->db->query($queryQuirurgicos)->fetchAll(PDO::FETCH_ASSOC);
        $noQuirurgicosRaw = $this->db->query($queryNoQuirurgicos)->fetchAll(PDO::FETCH_ASSOC);
        $todos = array_merge($quirurgicosRaw, $noQuirurgicosRaw);
        // Clasificar según si el texto inicia con "CIRUGIAS -"
        $quirurgicos = [];
        $noQuirurgicos = [];
        foreach ($todos as $r) {
            if (stripos(trim($r['nombre_procedimiento']), 'CIRUGIAS -') === 0) {
                $quirurgicos[] = $r;
            } else {
                $noQuirurgicos[] = $r;
            }
        }
        // Ordenar por fecha descendente
        usort($quirurgicos, fn($a, $b) => strtotime($b['fecha'] ?? '1970-01-01') <=> strtotime($a['fecha'] ?? '1970-01-01'));
        usort($noQuirurgicos, fn($a, $b) => strtotime($b['fecha'] ?? '1970-01-01') <=> strtotime($a['fecha'] ?? '1970-01-01'));
        return [
            'quirurgicos' => $quirurgicos,
            'no_quirurgicos' => $noQuirurgicos
        ];
    }

    public function prepararPreviewFacturacion(string $formId, string $hcNumber): array
    {
        $preview = [
            'procedimientos' => [],
            'insumos' => [],
            'derechos' => [],
            'oxigeno' => [],
            'anestesia' => []
        ];

        // 1. Procedimientos
        $stmt = $this->db->prepare("SELECT procedimientos FROM protocolo_data WHERE form_id = ?");
        $stmt->execute([$formId]);
        $json = $stmt->fetchColumn();

        if ($json) {
            $procedimientos = json_decode($json, true);
            if (is_array($procedimientos)) {
                $tarifarioStmt = $this->db->prepare("
                SELECT valor_facturar_nivel3, descripcion 
                FROM tarifario_2014 
                WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1
            ");

                foreach ($procedimientos as $p) {
                    if (isset($p['procInterno']) && preg_match('/- (\d{5}) - (.+)$/', $p['procInterno'], $matches)) {
                        $codigo = $matches[1];
                        $detalle = $matches[2];

                        $tarifarioStmt->execute([
                            'codigo' => $codigo,
                            'codigo_sin_0' => ltrim($codigo, '0')
                        ]);
                        $row = $tarifarioStmt->fetch(PDO::FETCH_ASSOC);
                        $precio = $row ? (float)$row['valor_facturar_nivel3'] : 0;

                        $preview['procedimientos'][] = [
                            'procCodigo' => $codigo,
                            'procDetalle' => $detalle,
                            'procPrecio' => $precio
                        ];
                    }
                }
            }
        }

        // 2. Insumos y derechos (desde API)
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => json_encode(["hcNumber" => $hcNumber, "form_id" => $formId])
            ]
        ];
        $context = stream_context_create($opts);
        $result = file_get_contents("https://asistentecive.consulmed.me/api/insumos/obtener.php", false, $context);
        $responseData = json_decode($result, true);

        if (!empty($responseData['insumos'])) {
            $insumosDecodificados = $responseData['insumos'];

            // Obtener afiliación desde la respuesta del API
            $afiliacion = strtoupper(trim($responseData['afiliacion'] ?? ''));

            foreach (['quirurgicos', 'anestesia'] as $categoria) {
                if (!empty($insumosDecodificados[$categoria])) {
                    foreach ($insumosDecodificados[$categoria] as $i) {
                        if (!empty($i['codigo'])) {
                            // 👉 Resolver precio real según afiliación
                            $precio = null;
                            try {
                                if (!empty($i['id']) || !empty($i['codigo'])) {
                                    $precio = $this->billingInsumosModel->obtenerPrecioPorAfiliacion(
                                        $i['codigo'] ?? '',
                                        $afiliacion,
                                        !empty($i['id']) ? (int)$i['id'] : null
                                    );
                                }
                            } catch (\Throwable $e) {
                                // Log de error para depuración
                                error_log("❌ Error obteniendo precio por afiliación: " . $e->getMessage());
                            }

                            // Fallback seguro
                            if ($precio === null) {
                                $precio = $i['precio'] ?? 0;
                            }

                            $preview['insumos'][] = [
                                'id' => $i['id'],
                                'codigo' => $i['codigo'],
                                'nombre' => $i['nombre'],
                                'cantidad' => $i['cantidad'],
                                'precio' => $precio,
                                'iva' => $i['iva'] ?? 1
                            ];
                        }
                    }
                }
            }

            if (!empty($insumosDecodificados['equipos'])) {
                foreach ($insumosDecodificados['equipos'] as $equipo) {
                    if (!empty($equipo['codigo'])) {
                        $precio = null;
                        try {
                            if (!empty($equipo['id']) || !empty($equipo['codigo'])) {
                                $precio = $this->billingInsumosModel->obtenerPrecioPorAfiliacion(
                                    $equipo['codigo'] ?? '',
                                    $afiliacion,
                                    !empty($equipo['id']) ? (int)$equipo['id'] : null
                                );
                            }
                        } catch (\Throwable $e) {
                            error_log("❌ Error obteniendo precio por afiliación: " . $e->getMessage());
                        }

                        if ($precio === null) {
                            $precio = $equipo['precio'] ?? 0;
                        }

                        $preview['derechos'][] = [
                            'id' => (int)$equipo['id'],
                            'codigo' => $equipo['codigo'],
                            'detalle' => $equipo['nombre'],
                            'cantidad' => (int)$equipo['cantidad'],
                            'iva' => 0,
                            'precioAfiliacion' => $precio
                        ];
                    }
                }
            }
        }

        // 3. Oxígeno
        if (!empty($responseData['duracion'])) {
            [$h, $m] = explode(':', $responseData['duracion']);
            $tiempo = (float)$h + ((int)$m / 60);

            $preview['oxigeno'][] = [
                'codigo' => '911111',
                'nombre' => 'OXIGENO',
                'tiempo' => $tiempo,
                'litros' => 3,
                'valor1' => 60.00,
                'valor2' => 0.01,
                'precio' => round($tiempo * 3 * 60.00 * 0.01, 2)
            ];
        }

        // 4. Anestesia
        $afiliacion = strtoupper(trim($responseData['afiliacion'] ?? ''));
        $codigoCirugia = $preview['procedimientos'][0]['procCodigo'] ?? '';
        $duracion = $responseData['duracion'] ?? '01:00';
        [$h, $m] = explode(':', $duracion);
        $cuartos = ceil(((int)$h * 60 + (int)$m) / 15);

        if ($afiliacion === "ISSFA" && $codigoCirugia === "66984") {
            $preview['anestesia'][] = [
                'codigo' => '999999',
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];
        } elseif ($afiliacion === "ISSFA") {
            $cantidad99149 = ($cuartos >= 2) ? 1 : $cuartos;
            $cantidad99150 = ($cuartos > 2) ? $cuartos - 2 : 0;

            if ($cantidad99149 > 0) {
                $preview['anestesia'][] = [
                    'codigo' => '99149',
                    'nombre' => 'SEDACIÓN INICIAL 30 MIN',
                    'tiempo' => $cantidad99149,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99149 * 13.34, 2)
                ];
            }
            if ($cantidad99150 > 0) {
                $preview['anestesia'][] = [
                    'codigo' => '99150',
                    'nombre' => 'SEDACIÓN ADICIONAL 15 MIN',
                    'tiempo' => $cantidad99150,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99150 * 13.34, 2)
                ];
            }
        } else {
            $preview['anestesia'][] = [
                'codigo' => '999999',
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];
        }

        return $preview;
    }

}