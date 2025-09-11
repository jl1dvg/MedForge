<?php

namespace Models;

use PDO;

class BillingAnestesiaModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $anestesia): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_anestesia
            (billing_id, codigo, nombre, tiempo, valor2, precio)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $anestesia['codigo'],
            $anestesia['nombre'],
            $anestesia['tiempo'],
            $anestesia['valor2'],
            $anestesia['precio']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_anestesia WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_anestesia WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }
}