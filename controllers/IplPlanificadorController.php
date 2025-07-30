<?php

namespace Controllers;

use Models\IplPlanificadorModel;
use PDO;

class IplPlanificadorController
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerCirugias(): array
    {
        return IplPlanificadorModel::obtenerTodas($this->db);
    }

    public function verificarDerivacion(string $form_id, string $hc_number, array $scraperResponse): void
    {
        IplPlanificadorModel::verificarOInsertarDerivacion($this->db, $form_id, $hc_number, $scraperResponse);
    }
    public function existeDerivacionEnBD($form_id, $hc_number): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM derivaciones_form_id WHERE form_id = ? AND hc_number = ?");
        $stmt->execute([$form_id, $hc_number]);
        return $stmt->fetchColumn() > 0;
    }

    public function guardarDerivacionManual($form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico)
    {
        $stmt = $this->db->prepare("INSERT INTO derivaciones_form_id (form_id, hc_number, cod_derivacion, fecha_registro, fecha_vigencia, diagnostico) VALUES (?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}