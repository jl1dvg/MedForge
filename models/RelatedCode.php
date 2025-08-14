<?php
namespace Models;

use PDO;

class RelatedCode {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function add(int $codeId, int $relatedId, string $type='maps_to'): void {
        $st = $this->db->prepare("INSERT IGNORE INTO related_codes(code_id,related_code_id,relation_type) VALUES(?,?,?)");
        $st->execute([$codeId, $relatedId, $type]);
    }

    public function remove(int $codeId, int $relatedId): void {
        $st = $this->db->prepare("DELETE FROM related_codes WHERE code_id=? AND related_code_id=?");
        $st->execute([$codeId, $relatedId]);
    }

    public function listFor(int $codeId): array {
        $sql = "SELECT rc.related_code_id, rc.relation_type, t.codigo, t.descripcion
                FROM related_codes rc
                JOIN tarifario_2014 t ON t.id = rc.related_code_id
                WHERE rc.code_id=?";
        $st = $this->db->prepare($sql);
        $st->execute([$codeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeAllFor(int $codeId): void {
        $st = $this->db->prepare("DELETE FROM related_codes WHERE code_id=?");
        $st->execute([$codeId]);
    }
}