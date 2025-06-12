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
}