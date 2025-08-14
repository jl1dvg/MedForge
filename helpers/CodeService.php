<?php
namespace Helpers;

use PDO;

class CodeService {
    private PDO $db;
    private string $table = 'tarifario_2014';
    public function __construct(PDO $db) { $this->db = $db; }

    public function isDuplicate(string $codigo, ?string $codeType, ?string $modifier, ?int $excludeId=null): bool {
        $sql = "SELECT COUNT(*) c FROM {$this->table} WHERE codigo=? AND IFNULL(code_type,'')=IFNULL(?, '') AND IFNULL(modifier,'')=IFNULL(?, '')";
        $params = [$codigo, $codeType, $modifier];
        if ($excludeId) { $sql .= " AND id<>?"; $params[] = $excludeId; }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int)$st->fetchColumn()) > 0;
    }

    public function snapshot(int $codeId): array {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id=?");
        $st->execute([$codeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        // precios dinámicos (si existen)
        $p = $this->db->prepare("SELECT level_key, price FROM prices WHERE code_id=?");
        $p->execute([$codeId]);
        $row['prices'] = $p->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function saveHistory(string $action, string $user, int $codeId): void {
        $snap = $this->snapshot($codeId);
        $st = $this->db->prepare("INSERT INTO codes_history(action_at,action_type,user,code_id,snapshot) VALUES(NOW(),?,?,?,?)");
        $st->execute([$action, $user, $codeId, json_encode($snap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }
}