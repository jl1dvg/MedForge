<?php

namespace Models;

use PDO;

class DiagnosticoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar()
    {
        $stmt = $this->db->query("SELECT dx_id, dx_code, short_desc FROM icd10_dx_order_code WHERE active = 1 ORDER BY short_desc ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($dx_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM icd10_dx_order_code WHERE dx_id = ?");
        $stmt->execute([$dx_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}