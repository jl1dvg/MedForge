<?php
namespace Models;

use PDO;

class PriceLevel {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function active(): array {
        $st = $this->db->query("SELECT * FROM price_levels WHERE active=1 ORDER BY seq");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
