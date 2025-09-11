<?php

namespace Models;

use PDO;

class BillingDerechosModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $derecho): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_derechos
            (billing_id, derecho_id, codigo, detalle, cantidad, iva, precio_afiliacion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $derecho['id'],
            $derecho['codigo'],
            $derecho['detalle'],
            $derecho['cantidad'],
            $derecho['iva'],
            $derecho['precioAfiliacion']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_derechos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_derechos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }
}