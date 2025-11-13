<?php

namespace Models;

use PDO;

class BillingSriDocumentModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $billingId, array $data): int
    {
        $columns = ['billing_id'];
        $placeholders = ['?'];
        $values = [$billingId];

        foreach ($data as $column => $value) {
            $columns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO billing_sri_documents (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $documentId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = $column . ' = ?';
            $values[] = $value;
        }
        $values[] = $documentId;

        $sql = sprintf(
            'UPDATE billing_sri_documents SET %s WHERE id = ?',
            implode(', ', $sets)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    public function findLatestByBillingId(int $billingId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM billing_sri_documents WHERE billing_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$billingId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findById(int $documentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM billing_sri_documents WHERE id = ? LIMIT 1');
        $stmt->execute([$documentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function incrementarIntentos(int $documentId): void
    {
        $stmt = $this->db->prepare('UPDATE billing_sri_documents SET intentos = intentos + 1 WHERE id = ?');
        $stmt->execute([$documentId]);
    }
}
