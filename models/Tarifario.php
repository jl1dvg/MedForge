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
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function search(array $f, int $offset = 0, int $limit = 100): array
    {
        // $f: ['q','code_type','superbill','active','reportable','financial_reporting']
        $where = [];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = "(t.codigo LIKE :q1 OR t.descripcion LIKE :q2)";
            $params[':q1'] = '%' . $f['q'] . '%';
            $params[':q2'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['code_type'])) {
            $where[] = "t.code_type = :code_type";
            $params[':code_type'] = $f['code_type'];
        }
        if (!empty($f['superbill'])) {
            $where[] = "t.superbill = :superbill";
            $params[':superbill'] = $f['superbill'];
        }
        if (!empty($f['active'])) {
            $where[] = "t.active = 1";
        }
        if (!empty($f['reportable'])) {
            $where[] = "t.reportable = 1";
        }
        if (!empty($f['financial_reporting'])) {
            $where[] = "t.financial_reporting = 1";
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT t.*
                FROM {$this->table} t
                {$sqlWhere}
                ORDER BY t.codigo ASC
                LIMIT :offset, :limit";

        $st = $this->db->prepare($sql);

        // named params primero
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        // MUY IMPORTANTE: bind como enteros para paginación (si ATTR_EMULATE_PREPARES = false)
        $st->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $st->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);

        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(array $f): int
    {
        $where = [];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = "(t.codigo LIKE :q1 OR t.descripcion LIKE :q2)";
            $params[':q1'] = '%' . $f['q'] . '%';
            $params[':q2'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['code_type'])) {
            $where[] = "t.code_type = :code_type";
            $params[':code_type'] = $f['code_type'];
        }
        if (!empty($f['superbill'])) {
            $where[] = "t.superbill = :superbill";
            $params[':superbill'] = $f['superbill'];
        }
        if (!empty($f['active'])) {
            $where[] = "t.active = 1";
        }
        if (!empty($f['reportable'])) {
            $where[] = "t.reportable = 1";
        }
        if (!empty($f['financial_reporting'])) {
            $where[] = "t.financial_reporting = 1";
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT COUNT(*) AS c
                FROM {$this->table} t
                {$sqlWhere}";
        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        return (int)($st->fetchColumn() ?: 0);
    }

    public function create(array $d): int
    {
        $sql = "INSERT INTO {$this->table}
            (codigo, descripcion, short_description, code_type, modifier, superbill,
             active, reportable, financial_reporting, revenue_code,
             valor_facturar_nivel1, valor_facturar_nivel2, valor_facturar_nivel3,
             anestesia_nivel1, anestesia_nivel2, anestesia_nivel3)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st = $this->db->prepare($sql);
        $st->execute([
            $d['codigo'], $d['descripcion'] ?? null, $d['short_description'] ?? null,
            $d['code_type'] ?? null, $d['modifier'] ?? null, $d['superbill'] ?? null,
            !empty($d['active']) ? 1 : 0, !empty($d['reportable']) ? 1 : 0, !empty($d['financial_reporting']) ? 1 : 0,
            $d['revenue_code'] ?? null,
            $d['precio_nivel1'] ?? null, $d['precio_nivel2'] ?? null, $d['precio_nivel3'] ?? null,
            $d['anestesia_nivel1'] ?? null, $d['anestesia_nivel2'] ?? null, $d['anestesia_nivel3'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $sql = "UPDATE {$this->table} SET
                codigo=?, descripcion=?, short_description=?, code_type=?, modifier=?, superbill=?,
                active=?, reportable=?, financial_reporting=?, revenue_code=?,
                valor_facturar_nivel1=?, valor_facturar_nivel2=?, valor_facturar_nivel3=?,
                anestesia_nivel1=?, anestesia_nivel2=?, anestesia_nivel3=?
            WHERE id=?";
        $st = $this->db->prepare($sql);
        $st->execute([
            $d['codigo'], $d['descripcion'] ?? null, $d['short_description'] ?? null,
            $d['code_type'] ?? null, $d['modifier'] ?? null, $d['superbill'] ?? null,
            !empty($d['active']) ? 1 : 0, !empty($d['reportable']) ? 1 : 0, !empty($d['financial_reporting']) ? 1 : 0,
            $d['revenue_code'] ?? null,
            $d['precio_nivel1'] ?? null, $d['precio_nivel2'] ?? null, $d['precio_nivel3'] ?? null,
            $d['anestesia_nivel1'] ?? null, $d['anestesia_nivel2'] ?? null, $d['anestesia_nivel3'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->db->prepare("DELETE FROM {$this->table} WHERE id=?");
        $st->execute([$id]);
    }
}