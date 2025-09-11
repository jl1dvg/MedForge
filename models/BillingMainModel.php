<?php

namespace Models;

use PDO;

class BillingMainModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(string $hcNumber, string $formId): int
    {
        $stmt = $this->db->prepare("INSERT INTO billing_main (hc_number, form_id) VALUES (?, ?)");
        $stmt->execute([$hcNumber, $formId]);
        return (int)$this->db->lastInsertId();
    }

    public function update(string $hcNumber, int $billingId): void
    {
        $stmt = $this->db->prepare("UPDATE billing_main SET hc_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hcNumber, $billingId]);
    }

    public function updateFechaCreacion(int $billingId, string $fecha): void
    {
        $stmt = $this->db->prepare("UPDATE billing_main SET created_at = ? WHERE id = ?");
        $stmt->execute([$fecha, $billingId]);
    }
}