<?php

namespace Modules\Pacientes\Controllers;

use Core\BaseController;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use Throwable;

class PacientesController extends BaseController
{
    private PacienteService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new PacienteService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/index.php',
            [
                'pageTitle' => 'Pacientes',
                'showNotFoundAlert' => isset($_GET['not_found']),
            ]
        );
    }

    public function datatable(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'draw' => isset($_POST['draw']) ? (int) $_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión expirada',
            ], 401);
            return;
        }

        try {
            $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
            $start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;
            $search = $_POST['search']['value'] ?? '';
            $orderColumnIndex = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;
            $orderDir = $_POST['order'][0]['dir'] ?? 'asc';

            $columnMap = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
            $orderColumn = $columnMap[$orderColumnIndex] ?? 'hc_number';

            $response = $this->service->obtenerPacientesPaginados(
                $start,
                $length,
                $search,
                $orderColumn,
                strtoupper($orderDir)
            );
            $response['draw'] = $draw;
            $this->json($response);
        } catch (Throwable $e) {
            $this->json([
                'draw' => isset($_POST['draw']) ? (int) $_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudo cargar la tabla de pacientes',
            ], 500);
        }
    }

    public function detalles(): void
    {
        $this->requireAuth();

        $hcNumber = $_GET['hc_number'] ?? null;
        if (!$hcNumber) {
            header('Location: /pacientes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_paciente'])) {
            $this->service->actualizarPaciente(
                $hcNumber,
                $_POST['fname'] ?? '',
                $_POST['mname'] ?? '',
                $_POST['lname'] ?? '',
                $_POST['lname2'] ?? '',
                $_POST['afiliacion'] ?? '',
                $_POST['fecha_nacimiento'] ?? '',
                $_POST['sexo'] ?? '',
                $_POST['celular'] ?? ''
            );

            header('Location: /pacientes/detalles?hc_number=' . urlencode($hcNumber));
            exit;
        }

        $context = $this->service->obtenerContextoPaciente($hcNumber);

        if (empty($context)) {
            header('Location: /pacientes?not_found=1');
            exit;
        }

        $this->render(
            __DIR__ . '/../views/detalles.php',
            array_merge(
                [
                    'pageTitle' => 'Paciente ' . $hcNumber,
                    'hc_number' => $hcNumber,
                ],
                $context
            )
        );
    }
}
