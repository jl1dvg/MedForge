<?php

namespace Modules\Farmacia\Controllers;

use Core\BaseController;
use Models\RecetaModel;
use PDO;

class FarmaciaController extends BaseController
{
    private RecetaModel $recetaModel;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->recetaModel = new RecetaModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filtros = [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? null,
            'fecha_fin' => $_GET['fecha_fin'] ?? null,
            'doctor' => $_GET['doctor'] ?? null,
            'producto' => $_GET['producto'] ?? null,
        ];

        $data = [
            'pageTitle' => 'Estadísticas de Farmacia',
            'filtros' => $filtros,
            'resumen' => $this->recetaModel->resumenGeneral($filtros),
            'porMes' => $this->recetaModel->resumenPorMes($filtros),
            'porProducto' => $this->recetaModel->resumenPorProducto($filtros),
            'porDoctor' => $this->recetaModel->resumenPorDoctor($filtros),
            'porProductoDoctor' => $this->recetaModel->resumenPorProductoDoctor($filtros),
            'detalle' => $this->recetaModel->obtenerReporte($filtros),
            'doctores' => $this->recetaModel->listarDoctores(),
            'scripts' => [
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                'js/pages/farmacia-dashboard.js',
            ],
        ];

        $this->render(__DIR__ . '/../views/index.php', $data);
    }
}
