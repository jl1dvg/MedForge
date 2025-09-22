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
        require_once __DIR__ . '/../PacienteController.php';
        require_once __DIR__ . '/../GuardarProyeccionController.php';
        $pacienteController = new \Controllers\PacienteController($this->db);
        $guardarProyeccionController = new \Controllers\GuardarProyeccionController($this->db);

        $pacienteInfo = $pacienteController->getPatientDetails($billing['hc_number']);
        $formDetails = $pacienteController->getDetalleSolicitud($billing['hc_number'], $formId);
        $visita = $guardarProyeccionController->obtenerDatosPacientePorFormId($formId);
        $protocoloExtendido = $this->protocoloModel->obtenerProtocoloTiny($formId, $billing['hc_number']);

        // Detalles de billing
        $procedimientos = $this->billingProcedimientosModel->obtenerPorBillingId($billingId);
        $derechos = $this->billingDerechosModel->obtenerPorBillingId($billingId);
        $insumos = $this->billingInsumosModel->obtenerPorBillingId($billingId);
        $insumosConIVA = array_filter($insumos, fn($i) => isset($i['iva']) && (int)$i['iva'] === 1);
        $medicamentosSinIVA = array_filter($insumos, fn($i) => isset($i['iva']) && (int)$i['iva'] === 0);

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