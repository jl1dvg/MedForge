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

    public function listar(string $afiliacion = ''): array
    {
        $procedimientos = [];
        $sql = "SELECT * FROM procedimientos";
        $stmt = $this->db->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id'];

            $tecnicos = $this->fetchAll("SELECT * FROM procedimientos_tecnicos WHERE procedimiento_id = ?", [$id]);
            $codigos = $this->fetchAll("SELECT * FROM procedimientos_codigos WHERE procedimiento_id = ?", [$id]);
            $diagnosticos = $this->fetchAll("SELECT * FROM procedimientos_diagnosticos WHERE procedimiento_id = ?", [$id]);

            $operatorio = $this->procesarOperatorio($row['operatorio'] ?? '', $afiliacion);

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

    private function procesarOperatorio(string $texto, string $afiliacion): string
    {
        return preg_replace_callback('/\[\[ID:(\d+)\]\]/', function ($matches) use ($afiliacion) {
            $stmt = $this->db->prepare("SELECT nombre, producto_issfa FROM insumos WHERE id = ?");
            $stmt->execute([(int)$matches[1]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $matches[0];
            if ($afiliacion === 'ISSFA' && !empty($row['producto_issfa'])) {
                return $row['producto_issfa'];
            }
            return $row['nombre'];
        }, $texto);
    }
}