<?php
namespace Models;

use PDO;

class CodeCategory {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function allActive(): array {
        $st = $this->db->query("SELECT * FROM code_categories WHERE active=1 ORDER BY seq, title");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}