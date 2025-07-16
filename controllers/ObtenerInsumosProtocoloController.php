<?php

namespace Controllers;

use PDO;

class ObtenerInsumosProtocoloController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtener(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'])) {
            return ["success" => false, "message" => "Parámetros insuficientes"];
        }

        $sql = "SELECT insumos, hora_inicio, hora_fin, status FROM protocolo_data WHERE hc_number = :hc AND form_id = :form_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':hc' => trim((string)$data['hcNumber']),
            ':form_id' => (int)$data['form_id']
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug = [
            "inputs" => $data,
            "sql_row" => $row,
            "pdo_error" => $stmt->errorInfo()
        ];

        if ($row) {
            $horaInicio = $row['hora_inicio'];
            $horaFin = $row['hora_fin'];
            $status = $row['status'];

            $duracion = null;
            if ($horaInicio && $horaFin) {
                $inicio = new \DateTime($horaInicio);
                $fin = new \DateTime($horaFin);
                $intervalo = $inicio->diff($fin);
                $duracion = $intervalo->format('%H:%I');
            }

            $insumos = json_decode($row['insumos'], true);
            $debug['raw_insumos'] = $row['insumos'];
            $debug['parsed_insumos'] = $insumos;

            if (!is_array($insumos)) {
                return [
                    "success" => false,
                    "message" => "Error al decodificar JSON de insumos",
                    "debug" => $debug
                ];
            }
            // Obtener afiliación del paciente
            $sqlAfiliacion = "SELECT afiliacion FROM patient_data WHERE hc_number = :hc LIMIT 1";
            $stmtAfiliacion = $this->db->prepare($sqlAfiliacion);
            $stmtAfiliacion->execute([':hc' => $data['hcNumber']]);
            $afiliacionRow = $stmtAfiliacion->fetch(PDO::FETCH_ASSOC);
            $afiliacion = $afiliacionRow['afiliacion'] ?? '';

            foreach ($insumos as $categoria => &$items) {
                foreach ($items as &$item) {
                    $stmtCodigo = $this->db->prepare("SELECT codigo_issfa, codigo_isspol, codigo_iess, codigo_msp FROM insumos WHERE id = :id");
                    $stmtCodigo->execute([':id' => $item['id']]);
                    $codigoRow = $stmtCodigo->fetch(PDO::FETCH_ASSOC);
                    $codigo = null;

                    if ($codigoRow) {
                        /*
                        switch (strtoupper($afiliacion)) {
                            case 'ISSFA':
                                $codigo = $codigoRow['codigo_issfa'];
                                break;
                            case 'ISSPOL':
                                $codigo = $codigoRow['codigo_isspol'];
                                break;
                            case 'MSP':
                                $codigo = $codigoRow['codigo_msp'];
                                break;
                            default:
                                $codigo = $codigoRow['codigo_iess'];
                                break;
                        }
                        */
                        $codigo = $codigoRow['codigo_isspol'];
                    }

                    $item['codigo'] = $codigo;
                }
            }
            unset($items, $item); // romper referencias
            return [
                "success" => true,
                "message" => "Insumos encontrados",
                "insumos" => $insumos,
                "duracion" => $duracion,
                "status" => $status,
                "afiliacion" => $afiliacion,
                "debug" => $debug
            ];
        } else {
            return [
                "success" => false,
                "message" => "No se encontraron insumos para este paciente",
                "debug" => [
                    "inputs" => $data
                ]
            ];
        }
    }
}