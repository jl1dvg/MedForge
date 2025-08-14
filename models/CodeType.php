<?php
namespace Models;

use PDO;

class CodeType {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function allActive(): array {
        $st = $this->db->query("SELECT * FROM code_types ORDER BY label");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}