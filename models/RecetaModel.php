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

    public function resumenPorMes(array $filtros): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(re.created_at, '%Y-%m') AS mes,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
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

        $sql .= " GROUP BY mes ORDER BY mes DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorProducto(array $filtros): array
    {
        $sql = "
            SELECT
                re.producto,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
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

        $sql .= " GROUP BY re.producto ORDER BY total_recetas DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorProductoDoctor(array $filtros): array
    {
        $sql = "
            SELECT
                pp.doctor,
                re.producto,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
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

        $sql .= " GROUP BY pp.doctor, re.producto ORDER BY total_recetas DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenGeneral(array $filtros): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades,
                COUNT(DISTINCT pp.doctor) AS total_doctores,
                COUNT(DISTINCT re.producto) AS total_productos
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarDoctores(): array
    {
        $sql = "
            SELECT DISTINCT pp.doctor
            FROM procedimiento_proyectado pp
            WHERE pp.doctor IS NOT NULL AND pp.doctor <> ''
            ORDER BY pp.doctor ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
