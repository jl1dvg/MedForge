<?php
namespace Helpers;
use PDO;
class TarifarioHelper {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function obtenerPrecio(string $codigo): float {
        $stmt = $this->db->prepare("SELECT valor_facturar_nivel3 FROM tarifario_2014 WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1");
        $stmt->execute(['codigo' => $codigo, 'codigo_sin_0' => ltrim($codigo, '0')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['valor_facturar_nivel3'] : 0.0;
    }
}