<?php

namespace Models;

use PDO;

class Tarifario
{
    private PDO $db;
    private string $table = 'tarifario_2014';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function search(array $filters, int $offset = 0, int $limit = 100): array
    {
        [$sqlWhere, $params] = $this->buildFilter($filters);
        $sql = "SELECT t.*
                FROM {$this->table} t
                {$sqlWhere}
                ORDER BY t.codigo ASC
                LIMIT :offset, :limit";

        $st = $this->db->prepare($sql);
        $this->bindFilterParams($st, $params);
        $st->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $st->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchOrdered(array $filters, int $offset, int $limit, string $orderBy, string $direction): array
    {
        [$sqlWhere, $params] = $this->buildFilter($filters);

        $allowedColumns = [
            'codigo',
            'modifier',
            'active',
            'superbill',
            'reportable',
            'financial_reporting',
            'code_type',
            'descripcion',
            'short_description',
            'id',
            'valor_facturar_nivel1',
            'valor_facturar_nivel2',
            'valor_facturar_nivel3',
        ];

        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'codigo';
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT t.*
                FROM {$this->table} t
                {$sqlWhere}
                ORDER BY {$orderBy} {$direction}
                LIMIT :offset, :limit";

        $st = $this->db->prepare($sql);
        $this->bindFilterParams($st, $params);
        $st->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $st->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        [$sqlWhere, $params] = $this->buildFilter($filters);
        $sql = "SELECT COUNT(*) AS c
                FROM {$this->table} t
                {$sqlWhere}";

        $st = $this->db->prepare($sql);
        $this->bindFilterParams($st, $params);
        $st->execute();

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
            (codigo, descripcion, short_description, code_type, modifier, superbill,
             active, reportable, financial_reporting, revenue_code,
             valor_facturar_nivel1, valor_facturar_nivel2, valor_facturar_nivel3,
             anestesia_nivel1, anestesia_nivel2, anestesia_nivel3)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $st = $this->db->prepare($sql);
        $st->execute([
            $data['codigo'],
            $data['descripcion'] ?? null,
            $data['short_description'] ?? null,
            $data['code_type'] ?? null,
            $data['modifier'] ?? null,
            $data['superbill'] ?? null,
            !empty($data['active']) ? 1 : 0,
            !empty($data['reportable']) ? 1 : 0,
            !empty($data['financial_reporting']) ? 1 : 0,
            $data['revenue_code'] ?? null,
            $data['precio_nivel1'] ?? null,
            $data['precio_nivel2'] ?? null,
            $data['precio_nivel3'] ?? null,
            $data['anestesia_nivel1'] ?? null,
            $data['anestesia_nivel2'] ?? null,
            $data['anestesia_nivel3'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = "UPDATE {$this->table} SET
                codigo = ?, descripcion = ?, short_description = ?, code_type = ?, modifier = ?, superbill = ?,
                active = ?, reportable = ?, financial_reporting = ?, revenue_code = ?,
                valor_facturar_nivel1 = ?, valor_facturar_nivel2 = ?, valor_facturar_nivel3 = ?,
                anestesia_nivel1 = ?, anestesia_nivel2 = ?, anestesia_nivel3 = ?
            WHERE id = ?";

        $st = $this->db->prepare($sql);
        $st->execute([
            $data['codigo'],
            $data['descripcion'] ?? null,
            $data['short_description'] ?? null,
            $data['code_type'] ?? null,
            $data['modifier'] ?? null,
            $data['superbill'] ?? null,
            !empty($data['active']) ? 1 : 0,
            !empty($data['reportable']) ? 1 : 0,
            !empty($data['financial_reporting']) ? 1 : 0,
            $data['revenue_code'] ?? null,
            $data['precio_nivel1'] ?? null,
            $data['precio_nivel2'] ?? null,
            $data['precio_nivel3'] ?? null,
            $data['anestesia_nivel1'] ?? null,
            $data['anestesia_nivel2'] ?? null,
            $data['anestesia_nivel3'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $st->execute([$id]);
    }

    public function quickSearch(string $query, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT id, codigo, descripcion, short_description,
                       valor_facturar_nivel1, valor_facturar_nivel2, valor_facturar_nivel3,
                       code_type, superbill
                FROM {$this->table}
                WHERE codigo LIKE :codigo_pattern OR descripcion LIKE :descripcion_pattern
                ORDER BY codigo ASC
                LIMIT :limit";

        $pattern = '%' . $query . '%';
        $st = $this->db->prepare($sql);
        $st->bindValue(':codigo_pattern', $pattern, PDO::PARAM_STR);
        $st->bindValue(':descripcion_pattern', $pattern, PDO::PARAM_STR);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildFilter(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(t.codigo LIKE :q1 OR t.descripcion LIKE :q2)";
            $params[':q1'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['code_type'])) {
            $where[] = "t.code_type = :code_type";
            $params[':code_type'] = $filters['code_type'];
        }
        if (!empty($filters['superbill'])) {
            $where[] = "t.superbill = :superbill";
            $params[':superbill'] = $filters['superbill'];
        }
        if (!empty($filters['active'])) {
            $where[] = "t.active = 1";
        }
        if (!empty($filters['reportable'])) {
            $where[] = "t.reportable = 1";
        }
        if (!empty($filters['financial_reporting'])) {
            $where[] = "t.financial_reporting = 1";
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        return [$sqlWhere, $params];
    }

    private function bindFilterParams(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
