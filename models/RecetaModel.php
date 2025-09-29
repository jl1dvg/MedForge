<?php

namespace Models;

use PDO;

class RecetaModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerReporte(array $filtros): array
    {
        $sql = "
            SELECT 
                re.created_at AS fecha_receta,
                re.producto,
                re.total_farmacia AS cantidad,
                re.dosis,
                pp.doctor,
                pp.procedimiento_proyectado,
                pp.hc_number,
                pp.afiliacion
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(re.created_at) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['doctor'])) {
            $sql .= " AND pp.doctor = :doctor";
            $params[':doctor'] = $filtros['doctor'];
        }

        if (!empty($filtros['producto'])) {
            $sql .= " AND re.producto LIKE :producto";
            $params[':producto'] = "%" . $filtros['producto'] . "%";
        }

        $sql .= " ORDER BY re.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function productosMasRecetados(array $filtros): array
    {
        $sql = "
            SELECT 
                re.producto,
                COUNT(*) AS veces_recetado
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(re.created_at) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        $sql .= " GROUP BY re.producto ORDER BY veces_recetado DESC LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorDoctor(array $filtros): array
    {
        $sql = "
            SELECT 
                pp.doctor,
                COUNT(*) AS total_recetas,
                SUM(re.total_farmacia) AS total_unidades
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(re.created_at) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        $sql .= " GROUP BY pp.doctor ORDER BY total_unidades DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodas(): array
    {
        $sql = "
            SELECT 
                re.*, 
                pp.procedimiento_proyectado, 
                pp.hc_number, 
                pp.afiliacion
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            ORDER BY re.created_at DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
