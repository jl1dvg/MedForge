<?php

namespace Controllers;

use PDO;

class DerivacionController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardarDerivacion($codDerivacion, $formId, $hcNumber = null, $fechaRegistro = null, $fechaVigencia = null, $referido = null, $diagnostico = null)


    {
        $stmt = $this->db->prepare("
            INSERT INTO derivaciones_form_id (cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, referido, diagnostico)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                fecha_registro = VALUES(fecha_registro),
                fecha_vigencia = VALUES(fecha_vigencia),
                cod_derivacion = VALUES(cod_derivacion),
                referido = VALUES(referido),
                diagnostico = VALUES(diagnostico)
        ");
        return $stmt->execute([$codDerivacion, $formId, $hcNumber, $fechaRegistro, $fechaVigencia, $referido, $diagnostico]);
    }

    public function verificarFormIds(array $form_ids): array
    {
        if (empty($form_ids)) {
            return [
                "success" => false,
                "message" => "No se enviaron form_ids.",
                "existentes" => [],
                "nuevos" => []
            ];
        }

        // Evita inyecciones SQL
        $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
        $sql = "SELECT form_id FROM procedimiento_proyectado WHERE form_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($form_ids);
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $form_ids_existentes = array_map('strval', $resultados);
        $form_ids_todos = array_map('strval', $form_ids);

        $form_ids_nuevos = array_diff($form_ids_todos, $form_ids_existentes);

        return [
            "success" => true,
            "existentes" => $form_ids_existentes,
            "nuevos" => array_values($form_ids_nuevos)
        ];
    }
}