<?php

namespace Controllers;

use Core\BaseController;
use Models\SolicitudModel;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use Throwable;

class SolicitudController extends BaseController
{
    private SolicitudModel $solicitudModel;
    private PacienteService $pacienteService;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/solicitudes.php',
            [
                'pageTitle' => 'Solicitudes Quirúrgicas',
            ]
        );
    }

    public function kanbanData(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'data' => [],
                'options' => [
                    'afiliaciones' => [],
                    'doctores' => [],
                ],
                'error' => 'Sesión expirada',
            ], 401);
            return;
        }

        $payload = [];
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $payload = array_merge($_POST, $payload);

        $filtros = [
            'afiliacion' => trim($payload['afiliacion'] ?? ''),
            'doctor' => trim($payload['doctor'] ?? ''),
            'prioridad' => trim($payload['prioridad'] ?? ''),
            'fechaTexto' => trim($payload['fechaTexto'] ?? ''),
        ];

        try {
            $solicitudes = $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filtros);

            $afiliaciones = array_values(array_unique(array_filter(array_map(
                static fn($row) => $row['afiliacion'] ?? null,
                $solicitudes
            ))));
            sort($afiliaciones, SORT_NATURAL | SORT_FLAG_CASE);

            $doctores = array_values(array_unique(array_filter(array_map(
                static fn($row) => $row['doctor'] ?? null,
                $solicitudes
            ))));
            sort($doctores, SORT_NATURAL | SORT_FLAG_CASE);

            $this->json([
                'data' => $solicitudes,
                'options' => [
                    'afiliaciones' => $afiliaciones,
                    'doctores' => $doctores,
                ],
            ]);
        } catch (Throwable $e) {
            $this->json([
                'data' => [],
                'options' => [
                    'afiliaciones' => [],
                    'doctores' => [],
                ],
                'error' => 'No se pudo cargar la información de solicitudes',
            ], 500);
        }
    }

    public function actualizarEstado(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $estado = trim($payload['estado'] ?? '');

        if ($id <= 0 || $estado === '') {
            $this->json(['success' => false, 'error' => 'Datos incompletos'], 422);
            return;
        }

        try {
            $this->solicitudModel->actualizarEstado($id, $estado);
            $this->json(['success' => true]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar el estado'], 500);
        }
    }

    public function prefactura(): void
    {
        $this->requireAuth();

        $hcNumber = $_GET['hc_number'] ?? '';
        $formId = $_GET['form_id'] ?? '';

        if ($hcNumber === '' || $formId === '') {
            http_response_code(400);
            echo '<p class="text-danger">Faltan parámetros para mostrar la prefactura.</p>';
            return;
        }

        $data = $this->obtenerDatosParaVista($hcNumber, $formId);

        if (empty($data['solicitud'])) {
            http_response_code(404);
            echo '<p class="text-danger">No se encontraron datos para la solicitud seleccionada.</p>';
            return;
        }

        $viewData = $data;
        ob_start();
        include __DIR__ . '/../views/prefactura_detalle.php';
        echo ob_get_clean();
    }

    public function getSolicitudesConDetalles(array $filtros = []): array
    {
        return $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filtros);
    }

    public function obtenerDatosParaVista($hc, $form_id)
    {
        $data = $this->solicitudModel->obtenerDerivacionPorFormId($form_id);
        $solicitud = $this->solicitudModel->obtenerDatosYCirujanoSolicitud($form_id, $hc);
        $paciente = $this->pacienteService->getPatientDetails($hc);
        $diagnostico = $this->solicitudModel->obtenerDxDeSolicitud($form_id);
        $consulta = $this->solicitudModel->obtenerConsultaDeSolicitud($form_id);
        return [
            'derivacion' => $data,
            'solicitud' => $solicitud,
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
        ];
    }
}