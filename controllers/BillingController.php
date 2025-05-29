<?php

namespace Controllers;

use PDO;
use Exception;
use Models\ProtocoloModel;

class BillingController
{
    private $db;
    private ProtocoloModel $protocoloModel;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
    }

    public function guardar(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Verifica si ya existe billing_main
            $stmt = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
            $stmt->execute([$data['form_id']]);
            $billingId = $stmt->fetchColumn();

            if ($billingId) {
                // Si ya existe, eliminamos detalles previos
                $this->borrarDetalles($billingId);

                // Y actualizamos datos generales
                $stmt = $this->db->prepare("UPDATE billing_main SET hc_number = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$data['hcNumber'], $billingId]);
            } else {
                // Insertamos nueva fila principal
                $stmt = $this->db->prepare("INSERT INTO billing_main (hc_number, form_id) VALUES (?, ?)");
                $stmt->execute([$data['hcNumber'], $data['form_id']]);
                $billingId = $this->db->lastInsertId();
            }

            // Establecer la fecha del billing según el protocolo
            $stmt = $this->db->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
            $stmt->execute([$data['form_id']]);
            $fechaInicio = $stmt->fetchColumn();
            if ($fechaInicio) {
                $stmt = $this->db->prepare("UPDATE billing_main SET created_at = ? WHERE id = ?");
                $stmt->execute([$fechaInicio, $billingId]);
            }

            // Insertar procedimientos
            foreach ($data['procedimientos'] as $p) {
                $stmt = $this->db->prepare("INSERT INTO billing_procedimientos (billing_id, procedimiento_id, proc_codigo, proc_detalle, proc_precio) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$billingId, $p['id'], $p['procCodigo'], $p['procDetalle'], $p['procPrecio']]);
            }

            // Insertar derechos
            foreach ($data['derechos'] as $d) {
                $stmt = $this->db->prepare("INSERT INTO billing_derechos (billing_id, derecho_id, codigo, detalle, cantidad, iva, precio_afiliacion) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$billingId, $d['id'], $d['codigo'], $d['detalle'], $d['cantidad'], $d['iva'], $d['precioAfiliacion']]);
            }

            // Insertar insumos
            foreach ($data['insumos'] as $i) {
                $stmt = $this->db->prepare("INSERT INTO billing_insumos (billing_id, insumo_id, codigo, nombre, cantidad, precio, iva) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$billingId, $i['id'], $i['codigo'], $i['nombre'], $i['cantidad'], $i['precio'], $i['iva']]);
            }

            // Insertar oxígeno
            foreach ($data['oxigeno'] as $o) {
                $stmt = $this->db->prepare("INSERT INTO billing_oxigeno (billing_id, codigo, nombre, tiempo, litros, valor1, valor2, precio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$billingId, $o['codigo'], $o['nombre'], $o['tiempo'], $o['litros'], $o['valor1'], $o['valor2'], $o['precio']]);
            }

            // Insertar anestesia
            foreach ($data['anestesiaTiempo'] as $a) {
                $stmt = $this->db->prepare("INSERT INTO billing_anestesia (billing_id, codigo, nombre, tiempo, valor2, precio) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$billingId, $a['codigo'], $a['nombre'], $a['tiempo'], $a['valor2'], $a['precio']]);
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
        $pacienteController = new \Controllers\PacienteController($this->db);
        $pacienteInfo = $pacienteController->getPatientDetails($billing['hc_number']);
        $formDetails = $pacienteController->getDetalleSolicitud($billing['hc_number'], $formId);

        // Obtener protocoloExtendido usando ProtocoloModel
        $protocoloExtendido = $this->protocoloModel->obtenerProtocolo($formId, $billing['hc_number']);

        // Obtener procedimientos
        $stmt = $this->db->prepare("SELECT * FROM billing_procedimientos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener derechos
        $stmt = $this->db->prepare("SELECT * FROM billing_derechos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        $derechos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener insumos
        $stmt = $this->db->prepare("SELECT * FROM billing_insumos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt = $this->db->prepare("SELECT * FROM billing_oxigeno WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        $oxigeno = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener anestesia
        $stmt = $this->db->prepare("SELECT * FROM billing_anestesia WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        $anestesia = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'billing' => $billing,
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $insumosConIVA,
            'medicamentos' => $medicamentosSinIVA,
            'oxigeno' => $oxigeno,
            'anestesia' => $anestesia,
            'paciente' => $pacienteInfo,
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

    public function generarExcel(string $formId): void
    {
        $datos = $this->obtenerDatos($formId);

        // 🔍 DEBUG: Mostrar contenido completo
        //echo "<pre>";
        //var_dump($datos);
        //echo "</pre>";
        //exit;

        // Código normal que puedes volver a activar después de revisar
        $afiliacion = strtoupper($datos['paciente']['afiliacion'] ?? '');

        // Pasar los datos como variables globales
        $GLOBALS['datos_facturacion'] = $datos;
        $GLOBALS['form_id_facturacion'] = $formId;

        $modo = $_GET['modo'] ?? 'individual';
        if ($modo === 'bulk') {
            if ($afiliacion === 'ISSPOL') {
                require __DIR__ . '/../views/billing/generar_excel_isspol.php';
            } else {
                require __DIR__ . '/../views/billing/descargar_excel.php';
            }
        } else {
            // Individual: enviar encabezados y descargar directamente
            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $GLOBALS['spreadsheet'] = $spreadsheet;

            if ($afiliacion === 'ISSPOL') {
                require __DIR__ . '/../views/billing/generar_excel_isspol.php';
            } else {
                require __DIR__ . '/../views/billing/descargar_excel.php';
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $pacienteInfo = $datos['paciente'];
            $filename = $pacienteInfo['hc_number'] . '_' . $pacienteInfo['lname'] . '_' . $pacienteInfo['fname'] . '.xlsx';
            header("Content-Disposition: attachment; filename=\"$filename\"");
            $writer->save('php://output');
            exit;
        }
    }

    public function generarExcelAArchivo(string $formId, string $destino): bool
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
            if ($afiliacion !== 'ISSPOL') return false;

            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $GLOBALS['spreadsheet'] = $spreadsheet;

            // Intenta generar el archivo Excel capturando excepciones
            try {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);

                ob_start();
                require __DIR__ . '/../views/billing/generar_excel_isspol.php';
                $error_output = ob_get_clean();

                if (!empty($error_output)) {
                    file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error fatal incluyendo generar_excel_isspol.php para form_id $formId: $error_output\n", FILE_APPEND);
                    return false;
                }
            } catch (Exception $e) {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error en generar_excel_isspol.php para form_id $formId: " . $e->getMessage() . "\n", FILE_APPEND);
                return false;
            }

            // Guardar archivo Excel generado
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($destino);
            return true;

        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/exportar_zip_log.txt', "❌ Error general generando Excel para form_id $formId: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function exportarPlanillasPorMes(string $mes): void
    {
        $stmt = $this->db->prepare("
            SELECT pd.form_id
            FROM protocolo_data pd
            JOIN patient_data pa ON pa.hc_number = pd.hc_number
            WHERE DATE_FORMAT(pd.fecha_inicio, '%Y-%m') = ?
              AND UPPER(pa.afiliacion) = 'ISSPOL'
              AND pd.status = 1
        ");
        $stmt->execute([$mes]);
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

            if ($afiliacion !== 'ISSPOL') {
                continue;
            }

            // Usar un nombre temporal único y persistente para cada archivo Excel
            $tempFile = sys_get_temp_dir() . '/' . uniqid("excel_{$formId}_") . '.xlsx';

            try {
                file_put_contents(__DIR__ . '/exportar_zip_log.txt', "→ Iniciando generación de Excel para $formId...\n", FILE_APPEND);
                $ok = $this->generarExcelAArchivo($formId, $tempFile);
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
            SELECT bm.id, bm.form_id, pd.hc_number, pd.fecha_inicio 
            FROM billing_main bm
            JOIN protocolo_data pd ON bm.form_id = pd.form_id
            ORDER BY pd.fecha_inicio DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}