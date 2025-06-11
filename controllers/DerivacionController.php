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

    public function guardarDerivacion($codDerivacion, $formId, $hcNumber = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO derivaciones_form_id (cod_derivacion, form_id, hc_number)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE hc_number = VALUES(hc_number)
        ");
        return $stmt->execute([$codDerivacion, $formId, $hcNumber]);
    }
}