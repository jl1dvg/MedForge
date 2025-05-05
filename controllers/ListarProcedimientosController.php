<?php

namespace Controllers;

use PDO;

class ListarProcedimientosController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function listar(): array
    {
        $procedimientos = [];
        $sql = "SELECT * FROM procedimientos";
        $stmt = $this->db->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id'];

            $tecnicos = $this->fetchAll("SELECT * FROM procedimientos_tecnicos WHERE procedimiento_id = ?", [$id]);
            $codigos = $this->fetchAll("SELECT * FROM procedimientos_codigos WHERE procedimiento_id = ?", [$id]);
            $diagnosticos = $this->fetchAll("SELECT * FROM procedimientos_diagnosticos WHERE procedimiento_id = ?", [$id]);

            $operatorio = $this->procesarOperatorio($row['operatorio'] ?? '');

            $procedimientos[] = array_merge($row, [
                'tecnicos' => $tecnicos,
                'codigos' => $codigos,
                'diagnosticos' => $diagnosticos,
                'operatorio' => $operatorio,
            ]);
        }

        return ["procedimientos" => $procedimientos];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function procesarOperatorio(string $texto): string
    {
        return preg_replace_callback('/\[\[ID:(\d+)\]\]/', function ($matches) {
            $stmt = $this->db->prepare("SELECT nombre FROM insumos WHERE id = ?");
            $stmt->execute([(int)$matches[1]]);
            $nombre = $stmt->fetchColumn();
            return $nombre ?: $matches[0];
        }, $texto);
    }
}