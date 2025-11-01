<?php

namespace Modules\Pacientes\Controllers;

use Controllers\PacienteController as LegacyPacienteController;
use Core\View;
use Modules\Dashboard\Controllers\DashboardController;
use PDO;
use Throwable;

class PacientesController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureAuthenticated(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
    }

    public function index(): void
    {
        $this->ensureAuthenticated();

        $dashboardController = new DashboardController($this->pdo);

        View::render(
            __DIR__ . '/../views/index.php',
            [
                'username' => $dashboardController->getAuthenticatedUser(),
                'pageTitle' => 'Pacientes',
            ]
        );
    }

    public function datatable(): void
    {
        header('Content-Type: application/json');

        try {
            $controller = new LegacyPacienteController($this->pdo);

            $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
            $start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;
            $search = $_POST['search']['value'] ?? '';
            $orderColumnIndex = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;
            $orderDir = $_POST['order'][0]['dir'] ?? 'asc';

            $columnMap = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
            $orderColumn = $columnMap[$orderColumnIndex] ?? 'hc_number';

            $response = $controller->obtenerPacientesPaginados(
                $start,
                $length,
                $search,
                $orderColumn,
                strtoupper($orderDir)
            );
            $response['draw'] = $draw;

            echo json_encode($response);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => isset($_POST['draw']) ? (int) $_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function detalles(): void
    {
        $this->ensureAuthenticated();

        $hcNumber = $_GET['hc_number'] ?? null;
        if (!$hcNumber) {
            header('Location: /pacientes');
            exit;
        }

        $pacienteController = new LegacyPacienteController($this->pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_paciente'])) {
            $pacienteController->actualizarPaciente(
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

        $dashboardController = new DashboardController($this->pdo);

        $diagnosticos = $pacienteController->getDiagnosticosPorPaciente($hcNumber);
        $medicos = $pacienteController->getDoctoresAsignados($hcNumber);
        $solicitudes = $pacienteController->getSolicitudesPorPaciente($hcNumber);
        $prefacturas = $pacienteController->getPrefacturasPorPaciente($hcNumber);

        foreach ($solicitudes as &$item) {
            $item['origen'] = 'Solicitud';
        }
        unset($item);

        foreach ($prefacturas as &$item) {
            $item['origen'] = 'Prefactura';
        }
        unset($item);

        $timelineItems = array_merge($solicitudes, $prefacturas);
        usort($timelineItems, static function (array $a, array $b): int {
            return strtotime($b['fecha']) <=> strtotime($a['fecha']);
        });

        View::render(
            __DIR__ . '/../views/detalles.php',
            [
                'username' => $dashboardController->getAuthenticatedUser(),
                'pageTitle' => 'Paciente ' . $hcNumber,
                'hc_number' => $hcNumber,
                'patientData' => $pacienteController->getPatientDetails($hcNumber),
                'afiliacionesDisponibles' => $pacienteController->getAfiliacionesDisponibles(),
                'diagnosticos' => $diagnosticos,
                'medicos' => $medicos,
                'timelineItems' => $timelineItems,
                'eventos' => $pacienteController->getEventosTimeline($hcNumber),
                'documentos' => $pacienteController->getDocumentosDescargables($hcNumber),
                'estadisticas' => $pacienteController->getEstadisticasProcedimientos($hcNumber),
            ]
        );
    }
}
