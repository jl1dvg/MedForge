<?php

namespace models;

use PDO;

class Price
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function upsertMany(int $codeId, array $map): void
    {
        // $map: ['nivel1'=>123.45, 'nivel2'=>...]
        foreach ($map as $levelKey => $price) {
            if ($price === '' || $price === null) continue;
            $st = $this->db->prepare("INSERT INTO prices(code_id,level_key,price)
                    VALUES(?,?,?)
                    ON DUPLICATE KEY UPDATE price=VALUES(price)");
            $st->execute([$codeId, $levelKey, (float)$price]);
        }
    }

    public function listFor(int $codeId): array
    {
        $st = $this->db->prepare("SELECT level_key, price FROM prices WHERE code_id=?");
        $st->execute([$codeId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[$r['level_key']] = (float)$r['price'];
        return $out;
    }

    public function deleteFor(int $codeId): void
    {
        $st = $this->db->prepare("DELETE FROM prices WHERE code_id=?");
        $st->execute([$codeId]);
    }
}
