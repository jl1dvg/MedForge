<?php

namespace Models;

use PDO;

class BillingInsumosModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $insumo): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_insumos
            (billing_id, insumo_id, codigo, nombre, cantidad, precio, iva)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $insumo['id'],
            $insumo['codigo'],
            $insumo['nombre'],
            $insumo['cantidad'],
            $insumo['precio'],
            $insumo['iva']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_insumos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_insumos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }
}