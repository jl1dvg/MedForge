<?php

namespace Controllers;

use PDO;
use Exception;
use Models\EstadisticaFlujoModel;

class EstadisticaFlujoController
{

    private $db;
    private EstadisticaFlujoModel $estadisticaFlujoModel;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->estadisticaFlujoModel = new EstadisticaFlujoModel($pdo);
    }

    // EstadisticaFlujoController.php
    public function index(array $filtros)
    {
        return $this->estadisticaFlujoModel->obtenerEstadisticas($filtros);
    }
    public function getEstadisticaFlujo($filtros)
    {
        return $this->estadisticaFlujoModel->getEstadisticaFlujo(
            $filtros['fecha_inicio'],
            $filtros['fecha_fin'],
            $filtros['medico'],
            $filtros['servicio']
        );
    }
}
