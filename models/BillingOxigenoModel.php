<?php

namespace Models;

use PDO;

class BillingOxigenoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $oxigeno): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_oxigeno
            (billing_id, codigo, nombre, tiempo, litros, valor1, valor2, precio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $oxigeno['codigo'],
            $oxigeno['nombre'],
            $oxigeno['tiempo'],
            $oxigeno['litros'],
            $oxigeno['valor1'],
            $oxigeno['valor2'],
            $oxigeno['precio']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_oxigeno WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_oxigeno WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }
}