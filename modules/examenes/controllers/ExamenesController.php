<?php

namespace Controllers;

use Core\BaseController;
use Helpers\JsonLogger;
use Modules\Examenes\Models\ExamenesModel;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Pacientes\Services\PacienteService;
use Modules\Solicitudes\Services\ExamenesCrmService;
use Modules\Solicitudes\Services\ExamenesReminderService;
use PDO;
use Throwable;

class ExamenesController extends BaseController
{
    private ExamenesModel $solicitudModel;
    private PacienteService $pacienteService;
    private ExamenesCrmService $crmService;
    private LeadConfigurationService $leadConfig;
    private PusherConfigService $pusherConfig;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->solicitudModel = new ExamenesModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->crmService = new ExamenesCrmService($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->pusherConfig = new PusherConfigService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/examenes.php',
            [
                'pageTitle' => 'Solicitudes Quirúrgicas',
                'realtime' => $this->pusherConfig->getPublicConfig(),
            ]
        );
    }

    public function turnero(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/turnero.php',
            [
                'pageTitle' => 'Turnero de Exámenes',
                'turneroContext' => 'Coordinación de Exámenes',
                'turneroEmptyMessage' => 'No hay pacientes en cola para coordinación de exámenes.',
                'bodyClass' => 'turnero-body',
            ],
            'layout-turnero.php'
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

        $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
        $pipelineStages = $this->leadConfig->getPipelineStages();

        try {
            $solicitudes = $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filtros);
            $solicitudes = array_map([$this, 'transformSolicitudRow'], $solicitudes);
            $solicitudes = $this->ordenarSolicitudes($solicitudes, $kanbanPreferences['sort'] ?? 'fecha_desc');
            $solicitudes = $this->limitarSolicitudesPorEstado($solicitudes, (int) ($kanbanPreferences['column_limit'] ?? 0));

            $responsables = $this->leadConfig->getAssignableUsers();
            $responsables = array_map([$this, 'transformResponsable'], $responsables);
            $fuentes = $this->leadConfig->getSources();

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
                    'crm' => [
                        'responsables' => $responsables,
                        'etapas' => $pipelineStages,
                        'fuentes' => $fuentes,
                        'kanban' => $kanbanPreferences,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            $this->json([
                'data' => [],
                'options' => [
                    'afiliaciones' => [],
                    'doctores' => [],
                    'crm' => [
                        'responsables' => [],
                        'etapas' => $pipelineStages,
                        'fuentes' => [],
                        'kanban' => $kanbanPreferences,
                    ],
                ],
                'error' => 'No se pudo cargar la información de solicitudes',
            ], 500);
        }
    }

    public function crmResumen(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        try {
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo cargar el detalle CRM'], 500);
        }
    }

    public function crmGuardarDetalles(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $this->crmService->guardarDetalles($solicitudId, $payload, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $detalle = $resumen['detalle'] ?? [];

            $this->pusherConfig->trigger(
                [
                    'solicitud_id' => $solicitudId,
                    'crm_lead_id' => $detalle['crm_lead_id'] ?? null,
                    'pipeline_stage' => $detalle['crm_pipeline_stage'] ?? null,
                    'responsable_id' => $detalle['crm_responsable_id'] ?? null,
                    'responsable_nombre' => $detalle['crm_responsable_nombre'] ?? null,
                    'fuente' => $detalle['crm_fuente'] ?? null,
                    'contacto_email' => $detalle['crm_contacto_email'] ?? null,
                    'contacto_telefono' => $detalle['crm_contacto_telefono'] ?? null,
                    'paciente_nombre' => $detalle['paciente_nombre'] ?? null,
                    'procedimiento' => $detalle['procedimiento'] ?? null,
                    'doctor' => $detalle['doctor'] ?? null,
                    'prioridad' => $detalle['prioridad'] ?? null,
                    'kanban_estado' => $detalle['estado'] ?? null,
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                null,
                PusherConfigService::EVENT_CRM_UPDATED
            );

            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudieron guardar los cambios'], 500);
        }
    }

    public function crmAgregarNota(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $nota = trim((string) ($payload['nota'] ?? ''));

        if ($nota === '') {
            $this->json(['success' => false, 'error' => 'La nota no puede estar vacía'], 422);
            return;
        }

        try {
            $this->crmService->registrarNota($solicitudId, $nota, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo registrar la nota'], 500);
        }
    }

    public function crmGuardarTarea(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $this->crmService->registrarTarea($solicitudId, $payload, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage() ?: 'No se pudo crear la tarea'], 500);
        }
    }

    public function crmActualizarTarea(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $tareaId = isset($payload['tarea_id']) ? (int) $payload['tarea_id'] : 0;
        $estado = isset($payload['estado']) ? (string) $payload['estado'] : '';

        if ($tareaId <= 0 || $estado === '') {
            $this->json(['success' => false, 'error' => 'Datos incompletos'], 422);
            return;
        }

        try {
            $this->crmService->actualizarEstadoTarea($solicitudId, $tareaId, $estado);
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar la tarea'], 500);
        }
    }

    public function crmSubirAdjunto(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
            $this->json(['success' => false, 'error' => 'No se recibió el archivo'], 422);
            return;
        }

        $archivo = $_FILES['archivo'];
        if ((int) ($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
            $this->json(['success' => false, 'error' => 'El archivo es inválido'], 422);
            return;
        }

        $descripcion = isset($_POST['descripcion']) ? trim((string) $_POST['descripcion']) : null;
        $nombreOriginal = (string) ($archivo['name'] ?? 'adjunto');
        $mimeType = isset($archivo['type']) ? (string) $archivo['type'] : null;
        $tamano = isset($archivo['size']) ? (int) $archivo['size'] : null;

        $carpetaBase = rtrim(PUBLIC_PATH . '/uploads/solicitudes/' . $solicitudId, '/');
        if (!is_dir($carpetaBase) && !mkdir($carpetaBase, 0775, true) && !is_dir($carpetaBase)) {
            $this->json(['success' => false, 'error' => 'No se pudo preparar la carpeta de adjuntos'], 500);
            return;
        }

        $nombreLimpio = preg_replace('/[^A-Za-z0-9_\.-]+/', '_', $nombreOriginal);
        $nombreLimpio = trim($nombreLimpio, '_');
        if ($nombreLimpio === '') {
            $nombreLimpio = 'adjunto';
        }

        $destinoNombre = uniqid('crm_', true) . '_' . $nombreLimpio;
        $destinoRuta = $carpetaBase . '/' . $destinoNombre;

        if (!move_uploaded_file($archivo['tmp_name'], $destinoRuta)) {
            $this->json(['success' => false, 'error' => 'No se pudo guardar el archivo'], 500);
            return;
        }

        $rutaRelativa = 'uploads/solicitudes/' . $solicitudId . '/' . $destinoNombre;

        try {
            $this->crmService->registrarAdjunto(
                $solicitudId,
                $nombreOriginal,
                $rutaRelativa,
                $mimeType,
                $tamano,
                $this->getCurrentUserId(),
                $descripcion !== '' ? $descripcion : null
            );

            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            @unlink($destinoRuta);
            $this->json(['success' => false, 'error' => 'No se pudo registrar el adjunto'], 500);
        }
            }

    private function getRequestBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];
            return $this->bodyCache;
        }

        if (!empty($_POST)) {
            $this->bodyCache = $_POST;
            return $this->bodyCache;
        }

        $decoded = json_decode(file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    private function transformSolicitudRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        return $row;
    }

    private function transformResponsable(array $usuario): array
    {
        $usuario['avatar'] = $this->formatProfilePhoto($usuario['avatar'] ?? ($usuario['profile_photo'] ?? null));

        if (isset($usuario['profile_photo'])) {
            $usuario['profile_photo'] = $this->formatProfilePhoto($usuario['profile_photo']);
        }

        return $usuario;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return function_exists('asset') ? asset($path) : $path;
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

            $this->pusherConfig->trigger(
                $resultado + [
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                null,
                PusherConfigService::EVENT_STATUS_UPDATED
            );

            $this->json([
                'success' => true,
                'estado' => $resultado['estado'] ?? $estado,
                'turno' => $resultado['turno'] ?? null,
                'estado_anterior' => $resultado['estado_anterior'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar el estado'], 500);
        }
    }

    public function enviarRecordatorios(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $horas = isset($payload['horas']) ? (int) $payload['horas'] : 24;
        $horasPasadas = isset($payload['horas_pasadas']) ? (int) $payload['horas_pasadas'] : 48;

        $scheduler = new ExamenesReminderService($this->pdo, $this->pusherConfig);
        $enviados = $scheduler->dispatchUpcoming($horas, $horasPasadas);

        $this->json([
            'success' => true,
            'dispatched' => $enviados,
            'count' => count($enviados),
        ]);
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
            JsonLogger::log(
                'turnero_examenes_panel',
                'Error cargando turnero de exámenes (panel múltiples)',
                $e,
                ['estados' => $estados]
            );
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
            JsonLogger::log(
                'turnero_examenes_panel',
                'Error al llamar turno del turnero de exámenes (panel múltiples)',
                $e,
                [
                    'payload' => [
                        'id' => $id,
                        'turno' => $turno,
                        'estado' => $estadoNormalizado,
                    ],
                    'usuario' => $this->getCurrentUserId(),
                ]
            );
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

    private function ordenarSolicitudes(array $solicitudes, string $criterio): array
    {
        $criterio = strtolower(trim($criterio));

        $comparador = match ($criterio) {
            'fecha_asc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'fecha', true),
            'creado_desc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'created_at', false),
            'creado_asc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'created_at', true),
            default => fn($a, $b) => $this->compararPorFecha($a, $b, 'fecha', false),
        };

        usort($solicitudes, $comparador);

        return $solicitudes;
    }

    private function compararPorFecha(array $a, array $b, string $campo, bool $ascendente): int
    {
        $valorA = $this->parseFecha($a[$campo] ?? null);
        $valorB = $this->parseFecha($b[$campo] ?? null);

        if ($valorA === $valorB) {
            return 0;
        }

        if ($ascendente) {
            return $valorA <=> $valorB;
        }

        return $valorB <=> $valorA;
    }

    private function parseFecha($valor): int
    {
        if ($valor === null) {
            return 0;
        }

        $timestamp = strtotime((string) $valor);

        return $timestamp ?: 0;
    }

    private function limitarSolicitudesPorEstado(array $solicitudes, int $limite): array
    {
        if ($limite <= 0) {
            return $solicitudes;
        }

        $contador = [];
        $filtradas = [];

        foreach ($solicitudes as $solicitud) {
            $estado = $this->normalizarEstadoKanban($solicitud['estado'] ?? '');
            $contador[$estado] = ($contador[$estado] ?? 0);

            if ($contador[$estado] >= $limite) {
                continue;
            }

            $filtradas[] = $solicitud;
            $contador[$estado]++;
        }

        return $filtradas;
    }

    private function normalizarEstadoKanban(string $estado): string
    {
        $estado = trim($estado);

        if ($estado === '') {
            return 'sin-estado';
        }

        if (function_exists('mb_strtolower')) {
            $estado = mb_strtolower($estado, 'UTF-8');
        } else {
            $estado = strtolower($estado);
        }

        $estado = preg_replace('/\s+/', '-', $estado) ?? $estado;

        return $estado;
    }
}
