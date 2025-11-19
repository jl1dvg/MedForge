<?php

namespace Services;

use PDO;
use Exception;
use Models\BillingMainModel;
use Models\BillingProcedimientosModel;
use Models\BillingDerechosModel;
use Models\BillingInsumosModel;
use Models\BillingOxigenoModel;
use Models\BillingAnestesiaModel;
use Models\ProtocoloModel;
use Helpers\FacturacionHelper;
use Modules\Pacientes\Services\PacienteService;

class BillingService
{
    private PDO $db;
    private BillingMainModel $billingMainModel;
    private BillingProcedimientosModel $billingProcedimientosModel;
    private BillingDerechosModel $billingDerechosModel;
    private BillingInsumosModel $billingInsumosModel;
    private BillingOxigenoModel $billingOxigenoModel;
    private BillingAnestesiaModel $billingAnestesiaModel;
    private ProtocoloModel $protocoloModel;
    private PacienteService $pacienteService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->billingMainModel = new BillingMainModel($pdo);
        $this->billingProcedimientosModel = new BillingProcedimientosModel($pdo);
        $this->billingDerechosModel = new BillingDerechosModel($pdo);
        $this->billingInsumosModel = new BillingInsumosModel($pdo);
        $this->billingOxigenoModel = new BillingOxigenoModel($pdo);
        $this->billingAnestesiaModel = new BillingAnestesiaModel($pdo);
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    /**
     * Guarda todos los datos de facturación en la base.
     *
     * @param array $data Datos del formulario (procedimientos, insumos, etc.)
     * @return array Resultado con éxito o error
     */
    public function guardar(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Billing main
            $billing = $this->billingMainModel->findByFormId($data['form_id']);
            if ($billing) {
                $billingId = $billing['id'];
                $this->borrarDetalles($billingId);
                $this->billingMainModel->update($data['hcNumber'], $billingId);
            } else {
                $billingId = $this->billingMainModel->insert($data['hcNumber'], $data['form_id']);
            }

            // Actualizar fecha de creación si existe en protocolo
            if (!empty($data['fecha_inicio'])) {
                $this->billingMainModel->updateFechaCreacion($billingId, $data['fecha_inicio']);
            }

            // Procedimientos
            foreach ($data['procedimientos'] ?? [] as $p) {
                $this->billingProcedimientosModel->insertar($billingId, $p);
            }

            // Derechos
            foreach ($data['derechos'] ?? [] as $d) {
                $this->billingDerechosModel->insertar($billingId, $d);
            }

            // Insumos
            foreach ($data['insumos'] ?? [] as $i) {
                $this->billingInsumosModel->insertar($billingId, $i);
            }

            // Oxígeno
            foreach ($data['oxigeno'] ?? [] as $o) {
                $this->billingOxigenoModel->insertar($billingId, $o);
            }

            // Anestesia
            foreach ($data['anestesia'] ?? [] as $a) {
                $this->billingAnestesiaModel->insertar($billingId, $a);
            }

            $this->db->commit();
            return ["success" => true, "message" => "Billing guardado correctamente", "billing_id" => $billingId];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "message" => "Error al guardar billing: " . $e->getMessage()];
        }
    }

    /**
     * Borra detalles de un billing antes de volver a insertarlos.
     */
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
     * Obtiene todos los datos de facturación asociados a un form_id
     */
    public function obtenerDatos(string $formId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing) return null;

        $billingId = $billing['id'];

        // Dependencias
        require_once __DIR__ . '/../GuardarProyeccionController.php';
        $guardarProyeccionController = new \Controllers\GuardarProyeccionController($this->db);

        $pacienteInfo = $this->pacienteService->getPatientDetails($billing['hc_number']);
        $formDetails = $this->pacienteService->getDetalleSolicitud($billing['hc_number'], $formId);
        $visita = $guardarProyeccionController->obtenerDatosPacientePorFormId($formId);
        $protocoloExtendido = $this->protocoloModel->obtenerProtocoloTiny($formId, $billing['hc_number']);

        // Detalles de billing
        $procedimientos = $this->billingProcedimientosModel->obtenerPorBillingId($billingId);
        $derechos = $this->billingDerechosModel->obtenerPorBillingId($billingId);
        $insumos = $this->billingInsumosModel->obtenerPorBillingId($billingId);
        $insumosConIVA = [];
        $medicamentosSinIVA = [];

        foreach ($insumos as $insumo) {
            $esMedicamento = $insumo['es_medicamento'] ?? null;

            if ($esMedicamento === null) {
                // Algunos registros de billing_insumos (en especial de ISSPOL/ISSFA)
                // no tienen relación en la tabla de catálogo y llegan sin bandera.
                // Usamos el IVA como pista: en farmacia suele ser 0 y en insumos 1.
                $esMedicamento = isset($insumo['iva']) && (int)$insumo['iva'] === 0 ? 1 : 0;
            } else {
                $esMedicamento = (int)$esMedicamento;
            }

            if ($esMedicamento === 1) {
                $medicamentosSinIVA[] = $insumo;
            } else {
                $insumosConIVA[] = $insumo;
            }
        }

        if (!empty($medicamentosSinIVA)) {
            $codigos = array_unique(array_filter(array_map(fn($m) => $m['codigo'], $medicamentosSinIVA)));
            if (!empty($codigos)) {
                $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                $stmt = $this->db->prepare("SELECT codigo_isspol, codigo_issfa, codigo_msp, codigo_iess, nombre 
                                            FROM insumos WHERE codigo_isspol IN ($placeholders)");
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

        return [
            'billing' => $billing,
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $insumosConIVA,
            'medicamentos' => $medicamentosSinIVA,
            'oxigeno' => $this->billingOxigenoModel->obtenerPorBillingId($billingId),
            'anestesia' => $this->billingAnestesiaModel->obtenerPorBillingId($billingId),
            'paciente' => $pacienteInfo,
            'visita' => $visita,
            'formulario' => $formDetails,
            'protocoloExtendido' => $protocoloExtendido,
        ];
    }

    public function obtenerResumenConsolidado(?string $mes = null): array
    {
        $query = "
        SELECT 
            bm.form_id,
            bm.hc_number,
            COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_orden,
            CONCAT(pa.fname, ' ', pa.mname, ' ', pa.lname, ' ', pa.lname2) AS paciente,
            pa.afiliacion,
            d.diagnostico,
            SUM(bp.proc_precio) AS total_facturado
        FROM billing_main bm
        LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
        LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
        LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
        LEFT JOIN derivaciones_form_id d ON d.form_id = bm.form_id
        LEFT JOIN billing_procedimientos bp ON bp.billing_id = bm.id
    ";

        if ($mes) {
            $startDate = $mes . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= " WHERE COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :startDate AND :endDate";
        }

        $query .= "
        GROUP BY bm.form_id
        ORDER BY fecha_orden DESC
    ";

        $stmt = $this->db->prepare($query);

        if ($mes) {
            $stmt->execute(['startDate' => $startDate, 'endDate' => $endDate]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajusta código/nombre de medicamentos según afiliación
     */
    private function ajustarCodigoPorAfiliacion(array $medicamento, string $afiliacion, array $referenciaMap): array
    {
        $codigoClave = $medicamento['codigo'] ?? '';
        $referencia = $referenciaMap[$codigoClave] ?? null;

        if ($referencia) {
            switch (strtoupper($afiliacion)) {
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
}