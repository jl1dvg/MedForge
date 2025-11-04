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

    public function turnero(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/turnero.php',
            [
                'pageTitle' => 'Turnero Coordinación Quirúrgica',
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
            $resultado = $this->solicitudModel->actualizarEstado($id, $estado);
            $this->json([
                'success' => true,
                'estado' => $resultado['estado'] ?? $estado,
                'turno' => $resultado['turno'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar el estado'], 500);
        }
    }

    public function turneroData(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['data' => [], 'error' => 'Sesión expirada'], 401);
            return;
        }

        $estados = [];
        if (!empty($_GET['estado'])) {
            $estados = array_values(array_filter(array_map('trim', explode(',', (string) $_GET['estado']))));
        }

        try {
            $solicitudes = $this->solicitudModel->fetchTurneroSolicitudes($estados);

            foreach ($solicitudes as &$solicitud) {
                $nombreCompleto = trim((string) ($solicitud['full_name'] ?? ''));
                $solicitud['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
                $solicitud['turno'] = isset($solicitud['turno']) ? (int) $solicitud['turno'] : null;
                $estadoNormalizado = $this->normalizarEstadoTurnero((string) ($solicitud['estado'] ?? ''));
                $solicitud['estado'] = $estadoNormalizado ?? ($solicitud['estado'] ?? null);

                $solicitud['hora'] = null;
                $solicitud['fecha'] = null;

                if (!empty($solicitud['created_at'])) {
                    $timestamp = strtotime((string) $solicitud['created_at']);
                    if ($timestamp !== false) {
                        $solicitud['hora'] = date('H:i', $timestamp);
                        $solicitud['fecha'] = date('d/m/Y', $timestamp);
                    }
                }
            }
            unset($solicitud);

            $this->json(['data' => $solicitudes]);
        } catch (Throwable $e) {
            $this->json(['data' => [], 'error' => 'No se pudo cargar el turnero'], 500);
        }
    }

    public function turneroLlamar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        $turno = isset($payload['turno']) ? (int) $payload['turno'] : null;
        $estadoSolicitado = isset($payload['estado']) ? trim((string) $payload['estado']) : 'Llamado';
        $estadoNormalizado = $this->normalizarEstadoTurnero($estadoSolicitado);

        if ($estadoNormalizado === null) {
            $this->json(['success' => false, 'error' => 'Estado no permitido para el turnero'], 422);
            return;
        }

        if ((!$id || $id <= 0) && (!$turno || $turno <= 0)) {
            $this->json(['success' => false, 'error' => 'Debe especificar un ID o número de turno'], 422);
            return;
        }

        try {
            $registro = $this->solicitudModel->llamarTurno($id, $turno, $estadoNormalizado);

            if (!$registro) {
                $this->json(['success' => false, 'error' => 'No se encontró la solicitud indicada'], 404);
                return;
            }

            $nombreCompleto = trim((string) ($registro['full_name'] ?? ''));
            $registro['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
            $registro['estado'] = $this->normalizarEstadoTurnero((string) ($registro['estado'] ?? '')) ?? ($registro['estado'] ?? null);

            $this->json([
                'success' => true,
                'data' => $registro,
            ]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo llamar el turno solicitado'], 500);
        }
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'llamado' => 'Llamado',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);

        return $mapa[$clave] ?? null;
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