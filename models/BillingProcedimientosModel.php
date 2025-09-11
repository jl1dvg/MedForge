<?php

namespace Models;

use PDO;

class BillingProcedimientosModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $procedimiento): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_procedimientos 
            (billing_id, procedimiento_id, proc_codigo, proc_detalle, proc_precio)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $procedimiento['id'],
            $procedimiento['procCodigo'],
            $procedimiento['procDetalle'],
            $procedimiento['procPrecio']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_procedimientos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_procedimientos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }
}