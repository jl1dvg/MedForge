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
}