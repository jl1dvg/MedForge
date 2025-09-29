<?php

namespace Controllers;

use Models\RecetaModel;
use Helpers\ResponseHelper;
use PDO;

class RecetasController
{
    private PDO $db;
    private RecetaModel $model;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->model = new RecetaModel($pdo);
    }

    // Mostrar reporte general de recetas con filtros opcionales
    public function reporte(array $filtros): array
    {
        return $this->model->obtenerReporte($filtros);
    }

    // Mostrar top productos más recetados
    public function topProductos(array $filtros): array
    {
        return $this->model->productosMasRecetados($filtros);
    }

    // Mostrar resumen de recetas por doctor
    public function resumenPorDoctor(array $filtros): array
    {
        return $this->model->resumenPorDoctor($filtros);
    }

    // Obtener todas las recetas sin filtros (opcional)
    public function listarTodo(): array
    {
        return $this->model->obtenerTodas();
    }
}
