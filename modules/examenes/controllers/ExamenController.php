<?php

namespace Controllers;

use Core\BaseController;
use DateTimeImmutable;
use Helpers\JsonLogger;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Examenes\Models\ExamenModel;
use Modules\Examenes\Services\ExamenCrmService;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Examenes\Services\ExamenReportExcelService;
use Modules\Examenes\Services\ExamenReminderService;
use Modules\Examenes\Services\ExamenSettingsService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Pacientes\Services\PacienteService;
use Modules\Reporting\Services\ReportService;
use PDO;
use RuntimeException;
use Throwable;

class ExamenController extends BaseController
{
    private ExamenModel $examenModel;
    private PacienteService $pacienteService;
    private ExamenCrmService $crmService;
    private ExamenEstadoService $estadoService;
    private ExamenSettingsService $settingsService;
    private LeadConfigurationService $leadConfig;
    private PusherConfigService $pusherConfig;
    private ?array $bodyCache = null;

    private const PUSHER_CHANNEL = 'examenes-kanban';
    private const STORAGE_PATH = 'uploads/examenes';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->examenModel = new ExamenModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->crmService = new ExamenCrmService($pdo);
        $this->estadoService = new ExamenEstadoService();
        $this->settingsService = new ExamenSettingsService($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->pusherConfig = new PusherConfigService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $realtime = $this->pusherConfig->getPublicConfig();
        $realtime['channel'] = self::PUSHER_CHANNEL;
        $realtime['events'][PusherConfigService::EVENT_NEW_REQUEST] = 'kanban.nueva-examen';
        $realtime['events'][PusherConfigService::EVENT_STATUS_UPDATED] = 'kanban.estado-actualizado';
        $realtime['events'][PusherConfigService::EVENT_CRM_UPDATED] = 'crm.detalles-actualizados';

        $examReminderAlias = 'exam_reminder';
        $examReminderKey = PusherConfigService::class . '::EVENT_EXAM_REMINDER';
        if (defined($examReminderKey)) {
            $examReminderAlias = constant($examReminderKey);
        }

        $realtime['events'][$examReminderAlias] = 'recordatorio-examen';
        $realtime['event'] = $realtime['events'][PusherConfigService::EVENT_NEW_REQUEST] ?? $realtime['event'];

        $this->render(
            __DIR__ . '/../views/examenes.php',
            [
                'pageTitle' => 'Solicitudes de Exámenes',
                'kanbanColumns' => $this->estadoService->getColumns(),
                'kanbanStages' => $this->estadoService->getStages(),
                'realtime' => $realtime,
                'reporting' => [
                    'formats' => $this->settingsService->getReportFormats(),
                    'quickMetrics' => $this->settingsService->getQuickMetrics(),
                ],
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

        $payload = $this->getRequestBody();
        $filtros = [
            'afiliacion' => trim((string) ($payload['afiliacion'] ?? '')),
            'doctor' => trim((string) ($payload['doctor'] ?? '')),
            'prioridad' => trim((string) ($payload['prioridad'] ?? '')),
            'estado' => trim((string) ($payload['estado'] ?? '')),
            'fechaTexto' => trim((string) ($payload['fechaTexto'] ?? '')),
        ];

        $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
        $pipelineStages = $this->leadConfig->getPipelineStages();

        try {
            $examenes = $this->examenModel->fetchExamenesConDetallesFiltrado($filtros);
            $examenes = array_map([$this, 'transformExamenRow'], $examenes);
            $examenes = $this->estadoService->enrichExamenes($examenes);
            $examenes = $this->ordenarExamenes($examenes, $kanbanPreferences['sort'] ?? 'fecha_desc');
            $examenes = $this->limitarExamenesPorEstado($examenes, (int) ($kanbanPreferences['column_limit'] ?? 0));

            $responsables = $this->leadConfig->getAssignableUsers();
            $responsables = array_map([$this, 'transformResponsable'], $responsables);
            $fuentes = $this->leadConfig->getSources();

            $afiliaciones = array_values(array_unique(array_filter(array_map(
                static fn($row) => $row['afiliacion'] ?? null,
                $examenes
            ))));
            sort($afiliaciones, SORT_NATURAL | SORT_FLAG_CASE);

            $doctores = array_values(array_unique(array_filter(array_map(
                static fn($row) => $row['doctor'] ?? null,
                $examenes
            ))));
            sort($doctores, SORT_NATURAL | SORT_FLAG_CASE);

            $this->json([
                'data' => $examenes,
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
                'error' => 'No se pudo cargar la información de exámenes',
            ], 500);
        }
    }

    public function reportePdf(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['error' => 'Sesión expirada'], 401);
            return;
        }

        if (!$this->hasPermission(['reportes.export', 'reportes.view', 'examenes.view'])) {
            $this->json(['error' => 'No tienes permisos para exportar reportes.'], 403);
            return;
        }

        $payload = $this->getRequestBody();
        $filtersInput = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
        $quickMetric = isset($payload['quickMetric']) ? trim((string) $payload['quickMetric']) : '';
        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        $allowedFormats = $this->settingsService->getReportFormats();

        if ($format !== 'pdf') {
            $this->json(['error' => 'Formato no soportado.'], 422);
            return;
        }

        if (!in_array('pdf', $allowedFormats, true)) {
            $this->json(['error' => 'El formato PDF está deshabilitado en configuración.'], 422);
            return;
        }

        if ($quickMetric !== '' && !$this->isQuickMetricAllowed($quickMetric)) {
            $this->json(['error' => 'Quick report no permitido en configuración.'], 422);
            return;
        }

        try {
            $reportData = $this->buildReportData($filtersInput, $quickMetric);
            $examenes = $reportData['rows'];
            $filtersSummary = $reportData['filtersSummary'];
            $metricLabel = $reportData['metricLabel'];
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');
            $filename = 'examenes_' . date('Ymd_His') . '.pdf';

            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('examenes_kanban', [
                'titulo' => 'Reporte de exámenes',
                'generatedAt' => $generatedAt,
                'filters' => $filtersSummary,
                'total' => count($examenes),
                'rows' => $examenes,
                'metricLabel' => $metricLabel,
            ], [
                'destination' => 'S',
                'filename' => $filename,
                'mpdf' => [
                    'orientation' => 'L',
                    'margin_left' => 6,
                    'margin_right' => 6,
                    'margin_top' => 8,
                    'margin_bottom' => 8,
                ],
            ]);

            if (strncmp($pdf, '%PDF-', 5) !== 0) {
                JsonLogger::log(
                    'examenes_reportes',
                    'Reporte PDF de exámenes devolvió contenido no-PDF',
                    null,
                    [
                        'user_id' => $this->getCurrentUserId(),
                        'preview' => substr($pdf, 0, 200),
                    ]
                );
                $this->json([
                    'error' => 'No se pudo generar el PDF (contenido inválido).',
                ], 500);
                return;
            }

            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Length: ' . strlen($pdf));
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('X-Content-Type-Options: nosniff');
            }

            echo $pdf;
            return;
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'examenes_reportes',
                'Reporte PDF de exámenes falló',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $this->json([
                'error' => 'No se pudo generar el reporte (ref: ' . $errorId . ')',
            ], 500);
        }
    }

    public function reporteExcel(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['error' => 'Sesión expirada'], 401);
            return;
        }

        if (!$this->hasPermission(['reportes.export', 'reportes.view', 'examenes.view'])) {
            $this->json(['error' => 'No tienes permisos para exportar reportes.'], 403);
            return;
        }

        $payload = $this->getRequestBody();
        $filtersInput = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
        $quickMetric = isset($payload['quickMetric']) ? trim((string) $payload['quickMetric']) : '';
        $format = strtolower(trim((string) ($payload['format'] ?? 'excel')));
        $allowedFormats = $this->settingsService->getReportFormats();

        if ($format !== 'excel') {
            $this->json(['error' => 'Formato no soportado.'], 422);
            return;
        }

        if (!in_array('excel', $allowedFormats, true)) {
            $this->json(['error' => 'El formato Excel está deshabilitado en configuración.'], 422);
            return;
        }

        if ($quickMetric !== '' && !$this->isQuickMetricAllowed($quickMetric)) {
            $this->json(['error' => 'Quick report no permitido en configuración.'], 422);
            return;
        }

        try {
            $reportData = $this->buildReportData($filtersInput, $quickMetric);
            $examenes = $reportData['rows'];
            $filtersSummary = $reportData['filtersSummary'];
            $metricLabel = $reportData['metricLabel'];
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');
            $filename = 'examenes_' . date('Ymd_His') . '.xlsx';

            $excelService = new ExamenReportExcelService();
            $content = $excelService->render($examenes, $filtersSummary, [
                'title' => 'Reporte de exámenes',
                'generated_at' => $generatedAt,
                'metric_label' => $metricLabel,
                'total' => count($examenes),
            ]);

            if ($content === '') {
                JsonLogger::log(
                    'examenes_reportes',
                    'Reporte Excel de exámenes devolvió contenido vacío',
                    null,
                    [
                        'user_id' => $this->getCurrentUserId(),
                    ]
                );
                $this->json([
                    'error' => 'No se pudo generar el Excel (contenido vacío).',
                ], 500);
                return;
            }

            if (strncmp($content, 'PK', 2) !== 0) {
                JsonLogger::log(
                    'examenes_reportes',
                    'Reporte Excel de exámenes devolvió contenido no-ZIP',
                    null,
                    [
                        'user_id' => $this->getCurrentUserId(),
                        'preview' => substr($content, 0, 200),
                    ]
                );
                $this->json([
                    'error' => 'No se pudo generar el Excel (contenido inválido).',
                ], 500);
                return;
            }

            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Length: ' . strlen($content));
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('X-Content-Type-Options: nosniff');
            }

            echo $content;
            return;
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'examenes_reportes',
                'Reporte Excel de exámenes falló',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $this->json([
                'error' => 'No se pudo generar el reporte (ref: ' . $errorId . ')',
            ], 500);
        }
    }

    public function crmResumen(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        try {
            $resumen = $this->crmService->obtenerResumen($examenId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo cargar el detalle CRM'], 500);
        }
    }

    public function crmBootstrap(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $resultado = $this->crmService->bootstrapChecklist(
                $examenId,
                $payload,
                $this->getCurrentUserId(),
                $this->currentPermissions()
            );
            $this->json(['success' => true] + $resultado);
        } catch (RuntimeException $e) {
            $status = (int) ($e->getCode() ?: 422);
            if ($status < 400 || $status >= 500) {
                $status = 422;
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500);
        }
    }

    public function crmChecklistState(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        try {
            $resultado = $this->crmService->checklistState(
                $examenId,
                $this->currentPermissions()
            );
            $this->json(['success' => true] + $resultado);
        } catch (RuntimeException $e) {
            $status = (int) ($e->getCode() ?: 422);
            if ($status < 400 || $status >= 500) {
                $status = 422;
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo cargar el checklist'], 500);
        }
    }

    public function crmActualizarChecklist(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $etapa = trim((string) ($payload['etapa_slug'] ?? $payload['etapa'] ?? ''));
        $completado = isset($payload['completado']) ? (bool) $payload['completado'] : true;

        if ($etapa === '') {
            $this->json(['success' => false, 'error' => 'Etapa requerida'], 422);
            return;
        }

        try {
            $resultado = $this->crmService->syncChecklistStage(
                $examenId,
                $etapa,
                $completado,
                $this->getCurrentUserId(),
                $this->currentPermissions()
            );

            $this->json([
                'success' => true,
                'checklist' => $resultado['checklist'] ?? [],
                'checklist_progress' => $resultado['checklist_progress'] ?? [],
                'tasks' => $resultado['tasks'] ?? [],
                'lead_id' => $resultado['lead_id'] ?? null,
                'project_id' => $resultado['project_id'] ?? null,
                'kanban_estado' => $resultado['kanban_estado'] ?? null,
                'kanban_estado_label' => $resultado['kanban_estado_label'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500);
        }
    }

    public function crmRegistrarBloqueo(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $resultado = $this->crmService->registrarBloqueoAgenda(
                $examenId,
                $payload,
                $this->getCurrentUserId()
            );

            $this->json(['success' => true, 'data' => $resultado]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage() ?: 'No se pudo registrar el bloqueo de agenda',
            ], 500);
        }
    }

    public function crmGuardarDetalles(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $this->crmService->guardarDetalles($examenId, $payload, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($examenId);
            $detalle = $resumen['detalle'] ?? [];

            $this->pusherConfig->trigger(
                [
                    'examen_id' => $examenId,
                    'crm_lead_id' => $detalle['crm_lead_id'] ?? null,
                    'pipeline_stage' => $detalle['crm_pipeline_stage'] ?? null,
                    'responsable_id' => $detalle['crm_responsable_id'] ?? null,
                    'responsable_nombre' => $detalle['crm_responsable_nombre'] ?? null,
                    'fuente' => $detalle['crm_fuente'] ?? null,
                    'contacto_email' => $detalle['crm_contacto_email'] ?? null,
                    'contacto_telefono' => $detalle['crm_contacto_telefono'] ?? null,
                    'paciente_nombre' => $detalle['paciente_nombre'] ?? null,
                    'examen_nombre' => $detalle['examen_nombre'] ?? null,
                    'doctor' => $detalle['doctor'] ?? null,
                    'prioridad' => $detalle['prioridad'] ?? null,
                    'kanban_estado' => $detalle['estado'] ?? null,
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                self::PUSHER_CHANNEL,
                PusherConfigService::EVENT_CRM_UPDATED
            );

            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudieron guardar los cambios'], 500);
        }
    }

    public function crmAgregarNota(int $examenId): void
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
            $this->crmService->registrarNota($examenId, $nota, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($examenId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo registrar la nota'], 500);
        }
    }

    public function crmGuardarTarea(int $examenId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $this->crmService->registrarTarea($examenId, $payload, $this->getCurrentUserId());
            $resumen = $this->crmService->obtenerResumen($examenId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage() ?: 'No se pudo crear la tarea'], 500);
        }
    }

    public function crmActualizarTarea(int $examenId): void
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
            $this->crmService->actualizarEstadoTarea($examenId, $tareaId, $estado);
            $resumen = $this->crmService->obtenerResumen($examenId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar la tarea'], 500);
        }
    }

    public function crmSubirAdjunto(int $examenId): void
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

        $carpetaBase = rtrim(PUBLIC_PATH . '/' . self::STORAGE_PATH . '/' . $examenId, '/');
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

        $rutaRelativa = self::STORAGE_PATH . '/' . $examenId . '/' . $destinoNombre;

        try {
            $this->crmService->registrarAdjunto(
                $examenId,
                $nombreOriginal,
                $rutaRelativa,
                $mimeType,
                $tamano,
                $this->getCurrentUserId(),
                $descripcion !== '' ? $descripcion : null
            );

            $resumen = $this->crmService->obtenerResumen($examenId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (Throwable $e) {
            @unlink($destinoRuta);
            $this->json(['success' => false, 'error' => 'No se pudo registrar el adjunto'], 500);
        }
    }

    public function actualizarEstado(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $estado = trim((string) ($payload['estado'] ?? ''));
        $origen = trim((string) ($payload['origen'] ?? 'kanban'));
        $observacion = isset($payload['observacion']) ? trim((string) $payload['observacion']) : null;

        if ($id <= 0 || $estado === '') {
            $this->json(['success' => false, 'error' => 'Datos incompletos'], 422);
            return;
        }

        try {
            $resultado = $this->examenModel->actualizarEstado(
                $id,
                $estado,
                $this->getCurrentUserId(),
                $origen !== '' ? $origen : 'kanban',
                $observacion
            );

            $this->pusherConfig->trigger(
                $resultado + [
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                self::PUSHER_CHANNEL,
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

        $scheduler = new ExamenReminderService($this->pdo, $this->pusherConfig);
        $enviados = $scheduler->dispatchUpcoming($horas);

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
            $examenes = $this->examenModel->fetchTurneroExamenes($estados);

            foreach ($examenes as &$examen) {
                $nombreCompleto = trim((string) ($examen['full_name'] ?? ''));
                $examen['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
                $examen['turno'] = isset($examen['turno']) ? (int) $examen['turno'] : null;
                $estadoNormalizado = $this->normalizarEstadoTurnero((string) ($examen['estado'] ?? ''));
                $examen['estado'] = $estadoNormalizado ?? ($examen['estado'] ?? null);
                $examen['hora'] = null;
                $examen['fecha'] = null;

                if (!empty($examen['created_at'])) {
                    $timestamp = strtotime((string) $examen['created_at']);
                    if ($timestamp !== false) {
                        $examen['hora'] = date('H:i', $timestamp);
                        $examen['fecha'] = date('d/m/Y', $timestamp);
                    }
                }
            }
            unset($examen);

            $this->json(['data' => $examenes]);
        } catch (Throwable $e) {
            JsonLogger::log(
                'turnero_examenes',
                'Error cargando turnero de exámenes',
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

        $payload = $this->getRequestBody();
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
            $registro = $this->examenModel->llamarTurno(
                $id,
                $turno,
                $estadoNormalizado,
                $this->getCurrentUserId(),
                'turnero'
            );

            if (!$registro) {
                $this->json(['success' => false, 'error' => 'No se encontró el examen indicado'], 404);
                return;
            }

            $nombreCompleto = trim((string) ($registro['full_name'] ?? ''));
            $registro['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
            $registro['estado'] = $this->normalizarEstadoTurnero((string) ($registro['estado'] ?? '')) ?? ($registro['estado'] ?? null);

            try {
                $this->pusherConfig->trigger(
                    [
                        'id' => (int) ($registro['id'] ?? $id ?? 0),
                        'turno' => $registro['turno'] ?? $turno,
                        'estado' => $registro['estado'] ?? $estadoNormalizado,
                        'hc_number' => $registro['hc_number'] ?? null,
                        'full_name' => $registro['full_name'] ?? null,
                        'kanban_estado' => $registro['kanban_estado'] ?? ($registro['estado'] ?? null),
                        'triggered_by' => $this->getCurrentUserId(),
                    ],
                    self::PUSHER_CHANNEL,
                    PusherConfigService::EVENT_TURNERO_UPDATED
                );
            } catch (Throwable $notificationError) {
                JsonLogger::log(
                    'turnero_examenes',
                    'No se pudo notificar la actualización del turnero de exámenes',
                    $notificationError,
                    [
                        'registro' => [
                            'id' => (int) ($registro['id'] ?? $id ?? 0),
                            'turno' => $registro['turno'] ?? $turno,
                            'estado' => $registro['estado'] ?? $estadoNormalizado,
                        ],
                    ]
                );
            }

            $this->json([
                'success' => true,
                'data' => $registro,
            ]);
        } catch (Throwable $e) {
            JsonLogger::log(
                'turnero_examenes',
                'Error al llamar turno del turnero de exámenes',
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

    public function obtenerEstadosPorHc(string $hcNumber): array
    {
        $examenes = $this->examenModel->obtenerEstadosPorHc($hcNumber);
        $examenes = array_map([$this, 'transformExamenRow'], $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);

        return [
            'success' => true,
            'hcNumber' => $hcNumber,
            'total' => count($examenes),
            'examenes' => $examenes,
        ];
    }

    public function actualizarExamenParcial(
        int $id,
        array $campos,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): array
    {
        return $this->examenModel->actualizarExamenParcial($id, $campos, $changedBy, $origen, $observacion);
    }

    public function apiEstadoGet(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $hcNumber = $_GET['hcNumber'] ?? $_GET['hc_number'] ?? null;
        if (!$hcNumber) {
            $this->json(['success' => false, 'message' => 'Parámetro hcNumber requerido'], 400);
            return;
        }

        try {
            $this->json($this->obtenerEstadosPorHc((string) $hcNumber));
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Error al obtener exámenes'], 500);
        }
    }

    public function apiEstadoPost(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0 && isset($payload['examen_id'])) {
            $id = (int) $payload['examen_id'];
        }

        if ($id <= 0) {
            $this->json(['success' => false, 'message' => 'Parámetro id requerido para actualizar el examen'], 400);
            return;
        }

        $campos = [
            'estado' => $payload['estado'] ?? null,
            'doctor' => $payload['doctor'] ?? null,
            'solicitante' => $payload['solicitante'] ?? null,
            'consulta_fecha' => $payload['consulta_fecha'] ?? ($payload['fecha'] ?? null),
            'prioridad' => $payload['prioridad'] ?? null,
            'observaciones' => $payload['observaciones'] ?? ($payload['observacion'] ?? null),
            'examen_nombre' => $payload['examen_nombre'] ?? ($payload['examen'] ?? null),
            'examen_codigo' => $payload['examen_codigo'] ?? null,
            'lateralidad' => $payload['lateralidad'] ?? ($payload['ojo'] ?? null),
            'turno' => $payload['turno'] ?? null,
        ];

        try {
            $resultado = $this->actualizarExamenParcial(
                $id,
                $campos,
                $this->getCurrentUserId(),
                'api_estado',
                isset($payload['observacion']) ? trim((string) $payload['observacion']) : null
            );
            $status = (!is_array($resultado) || ($resultado['success'] ?? false) === false) ? 422 : 200;
            $this->json(is_array($resultado) ? $resultado : ['success' => false], $status);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Error al actualizar el examen'], 500);
        }
    }

    public function prefactura(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string) ($_GET['hc_number'] ?? ''));
        $formId = trim((string) ($_GET['form_id'] ?? ''));
        $examenId = isset($_GET['examen_id']) ? (int) $_GET['examen_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            http_response_code(400);
            echo '<p class="text-danger">Faltan parámetros para mostrar el detalle del examen.</p>';
            return;
        }

        $viewData = $this->obtenerDatosParaVista($hcNumber, $formId, $examenId);
        if (empty($viewData['examen'])) {
            http_response_code(404);
            echo '<p class="text-danger">No se encontraron datos para el examen seleccionado.</p>';
            return;
        }

        ob_start();
        include __DIR__ . '/../views/prefactura_detalle.php';
        echo ob_get_clean();
    }

    private function obtenerDatosParaVista(string $hcNumber, string $formId, ?int $examenId = null): array
    {
        $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
        if (!$examen) {
            return ['examen' => null];
        }

        $paciente = $this->pacienteService->getPatientDetails($hcNumber);
        $consulta = $this->examenModel->obtenerConsultaPorFormHc($formId, $hcNumber) ?? [];
        $examenesRelacionados = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);

        $crmResumen = [];
        try {
            $crmResumen = $this->crmService->obtenerResumen((int) $examen['id']);
        } catch (Throwable $e) {
            $crmResumen = [];
        }

        $imagenesSolicitadas = $this->extraerImagenesSolicitadas(
            $consulta['examenes'] ?? null,
            $examenesRelacionados,
            $crmResumen['adjuntos'] ?? []
        );

        $diagnosticos = [];
        if (isset($consulta['diagnosticos']) && is_string($consulta['diagnosticos'])) {
            $decodedDx = json_decode($consulta['diagnosticos'], true);
            if (is_array($decodedDx)) {
                $diagnosticos = $decodedDx;
            }
        }

        $trazabilidad = $this->construirTrazabilidad($examen, $crmResumen);

        return [
            'examen' => $examen,
            'paciente' => is_array($paciente) ? $paciente : [],
            'consulta' => $consulta,
            'diagnostico' => $diagnosticos,
            'imagenes_solicitadas' => $imagenesSolicitadas,
            'trazabilidad' => $trazabilidad,
            'crm' => $crmResumen,
        ];
    }

    private function extraerImagenesSolicitadas($rawExamenes, array $examenesRelacionados, array $adjuntosCrm): array
    {
        $items = [];
        if (is_string($rawExamenes) && trim($rawExamenes) !== '') {
            $decoded = json_decode($rawExamenes, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        } elseif (is_array($rawExamenes)) {
            $items = $rawExamenes;
        }

        if (!is_array($items)) {
            $items = [];
        }

        $normalizedAdjuntos = [];
        foreach ($adjuntosCrm as $adjunto) {
            $normalizedAdjuntos[] = [
                'raw' => $adjunto,
                'search' => $this->normalizarTexto(
                    ($adjunto['descripcion'] ?? '') . ' ' . ($adjunto['nombre_original'] ?? '')
                ),
            ];
        }

        $buildRecord = function ($item, bool $allowNonImage) use ($examenesRelacionados, $normalizedAdjuntos) {
            $nombre = null;
            $codigo = null;
            $fuente = 'Consulta';
            $fecha = null;

            if (is_array($item)) {
                $nombre = trim((string) ($item['nombre'] ?? $item['examen'] ?? $item['descripcion'] ?? ''));
                $codigo = trim((string) ($item['codigo'] ?? $item['id'] ?? $item['code'] ?? ''));
                $fuente = trim((string) ($item['fuente'] ?? $item['origen'] ?? 'Consulta')) ?: 'Consulta';
                $fecha = $item['fecha'] ?? null;
            } elseif (is_string($item)) {
                $nombre = trim($item);
            }

            if ($nombre === null || $nombre === '') {
                return null;
            }

            if (!$allowNonImage && !$this->esEstudioImagen($nombre, $codigo)) {
                return null;
            }

            $nombreNorm = $this->normalizarTexto($nombre);
            $match = null;
            foreach ($examenesRelacionados as $rel) {
                $relNorm = $this->normalizarTexto($rel['examen_nombre'] ?? '');
                if ($relNorm === '') {
                    continue;
                }
                if ($relNorm === $nombreNorm || str_contains($relNorm, $nombreNorm) || str_contains($nombreNorm, $relNorm)) {
                    $match = $rel;
                    break;
                }
            }

            $estado = $match['estado'] ?? 'Solicitado';
            $fuenteFinal = $fuente;
            if (($fuenteFinal === '' || $fuenteFinal === 'Consulta') && !empty($match['solicitante'])) {
                $fuenteFinal = (string) $match['solicitante'];
            }
            $fechaFinal = $match['consulta_fecha'] ?? $fecha ?? $match['created_at'] ?? null;

            $evidencias = [];
            foreach ($normalizedAdjuntos as $adjunto) {
                $search = $adjunto['search'] ?? '';
                if ($search === '' || !str_contains($search, $nombreNorm)) {
                    continue;
                }

                $raw = $adjunto['raw'] ?? [];
                $evidencias[] = [
                    'url' => $raw['url'] ?? null,
                    'descripcion' => $raw['descripcion'] ?? null,
                    'nombre' => $raw['nombre_original'] ?? null,
                ];
            }

            return [
                'nombre' => $nombre,
                'codigo' => $codigo !== '' ? $codigo : null,
                'estado' => $estado,
                'fuente' => $fuenteFinal !== '' ? $fuenteFinal : 'Consulta',
                'fecha' => $fechaFinal,
                'evidencias' => $evidencias,
                'evidencias_count' => count($evidencias),
            ];
        };

        $records = [];
        $seen = [];
        foreach ($items as $item) {
            $record = $buildRecord($item, false);
            if (!$record) {
                continue;
            }
            $key = $this->normalizarTexto(($record['nombre'] ?? '') . '|' . ($record['codigo'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $records[] = $record;
        }

        if ($records === []) {
            foreach ($items as $item) {
                $record = $buildRecord($item, true);
                if (!$record) {
                    continue;
                }
                $key = $this->normalizarTexto(($record['nombre'] ?? '') . '|' . ($record['codigo'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $records[] = $record;
            }
        }

        return $records;
    }

    private function construirTrazabilidad(array $examen, array $crmResumen): array
    {
        $eventos = [];

        if (!empty($examen['created_at'])) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'estado',
                $examen['created_at'],
                'Examen registrado',
                'Estado inicial: ' . ((string) ($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        if (!empty($examen['updated_at']) && ($examen['updated_at'] ?? null) !== ($examen['created_at'] ?? null)) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'estado',
                $examen['updated_at'],
                'Actualización operativa',
                'Último estado reportado: ' . ((string) ($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        foreach (($crmResumen['notas'] ?? []) as $nota) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'nota',
                $nota['created_at'] ?? null,
                'Nota CRM',
                (string) ($nota['nota'] ?? ''),
                $nota['autor_nombre'] ?? null
            );
        }

        foreach (($crmResumen['tareas'] ?? []) as $tarea) {
            $titulo = trim((string) ($tarea['titulo'] ?? 'Tarea CRM'));
            $estado = trim((string) ($tarea['estado'] ?? 'pendiente'));
            $descripcion = $titulo . ' · Estado: ' . $estado;
            if (!empty($tarea['due_date'])) {
                $descripcion .= ' · Vence: ' . (string) $tarea['due_date'];
            }

            $eventos[] = $this->crearEventoTrazabilidad(
                'tarea',
                $tarea['updated_at'] ?? ($tarea['created_at'] ?? null),
                'Tarea CRM',
                $descripcion,
                $tarea['assigned_name'] ?? null
            );
        }

        foreach (($crmResumen['adjuntos'] ?? []) as $adjunto) {
            $descripcion = trim((string) ($adjunto['descripcion'] ?? ''));
            $nombre = trim((string) ($adjunto['nombre_original'] ?? 'Documento'));
            $texto = $descripcion !== '' ? $descripcion : $nombre;

            $eventos[] = $this->crearEventoTrazabilidad(
                'adjunto',
                $adjunto['created_at'] ?? null,
                'Adjunto CRM',
                $texto,
                $adjunto['subido_por_nombre'] ?? null
            );
        }

        foreach (($crmResumen['mail_events'] ?? []) as $mailEvent) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'correo',
                $mailEvent['created_at'] ?? null,
                'Correo saliente',
                (string) ($mailEvent['subject'] ?? 'Sin asunto'),
                $mailEvent['sent_by_name'] ?? null
            );
        }

        usort(
            $eventos,
            static function (array $a, array $b): int {
                return strtotime((string) ($b['fecha'] ?? '')) <=> strtotime((string) ($a['fecha'] ?? ''));
            }
        );

        return array_values(array_filter($eventos));
    }

    private function crearEventoTrazabilidad(string $tipo, $fecha, string $titulo, string $detalle, ?string $autor): array
    {
        return [
            'tipo' => $tipo,
            'fecha' => $fecha,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'autor' => $autor,
        ];
    }

    private function esEstudioImagen(string $nombre, ?string $codigo = null): bool
    {
        $texto = $this->normalizarTexto($nombre . ' ' . ($codigo ?? ''));
        if ($texto === '') {
            return false;
        }

        $keywords = [
            'oct',
            'tomografia',
            'retinografia',
            'angiografia',
            'ecografia',
            'ultrasonido',
            'biometria',
            'campimetria',
            'paquimetria',
            'resonancia',
            'tac',
            'rx',
            'rayos x',
            'fotografia',
            'imagen',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($texto, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $texto = preg_replace('/\p{Mn}/u', '', $normalized) ?? $texto;
            }
        }

        $texto = function_exists('mb_strtolower')
            ? mb_strtolower($texto, 'UTF-8')
            : strtolower($texto);
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
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

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string|null>
     */
    private function sanitizeReportFilters(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $doctor = trim((string) ($filters['doctor'] ?? ''));
        $afiliacion = trim((string) ($filters['afiliacion'] ?? ''));
        $prioridad = trim((string) ($filters['prioridad'] ?? ''));
        $estado = trim((string) ($filters['estado'] ?? ''));

        $allowedPriorities = ['normal', 'pendiente', 'urgente'];
        if ($prioridad !== '' && !in_array(strtolower($prioridad), $allowedPriorities, true)) {
            $prioridad = '';
        }

        $dateFrom = $this->normalizeDateInput($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($filters['date_to'] ?? null);

        if (!$dateFrom && !$dateTo && !empty($filters['fechaTexto'])) {
            [$dateFrom, $dateTo] = $this->parseDateRange((string) $filters['fechaTexto']);
        }

        return [
            'search' => $search,
            'doctor' => $doctor,
            'afiliacion' => $afiliacion,
            'prioridad' => $prioridad,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'estado' => $estado,
        ];
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('d-m-Y', $value);
        } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);
        } else {
            try {
                $date = new DateTimeImmutable($value);
            } catch (\Exception $e) {
                $date = null;
            }
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseDateRange(string $value): array
    {
        if (!str_contains($value, ' - ')) {
            $single = $this->normalizeDateInput($value);
            return [$single, $single];
        }

        [$from, $to] = explode(' - ', $value, 2);
        return [
            $this->normalizeDateInput($from),
            $this->normalizeDateInput($to),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function applySearchFilter(array $examenes, string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return $examenes;
        }

        $term = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
        $keys = [
            'full_name',
            'hc_number',
            'examen_nombre',
            'procedimiento',
            'doctor',
            'afiliacion',
            'estado',
            'kanban_estado',
            'crm_pipeline_stage',
        ];

        return array_values(array_filter($examenes, static function (array $row) use ($term, $keys) {
            foreach ($keys as $key) {
                $value = $row[$key] ?? '';
                if ($value === null || $value === '') {
                    continue;
                }

                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower((string) $value, 'UTF-8')
                    : strtolower((string) $value);

                if (str_contains($haystack, $term)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function applyDateRangeFilter(array $examenes, ?string $dateFrom, ?string $dateTo): array
    {
        if (!$dateFrom && !$dateTo) {
            return $examenes;
        }

        $from = $dateFrom ? DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom) : null;
        $to = $dateTo ? DateTimeImmutable::createFromFormat('Y-m-d', $dateTo) : null;

        if ($from instanceof DateTimeImmutable) {
            $from = $from->setTime(0, 0, 0);
        }

        if ($to instanceof DateTimeImmutable) {
            $to = $to->setTime(23, 59, 59);
        }

        return array_values(array_filter($examenes, function (array $row) use ($from, $to): bool {
            $value = $row['consulta_fecha'] ?? ($row['created_at'] ?? null);
            $date = $this->parseFecha($value);
            if (!$date) {
                return false;
            }

            if ($from instanceof DateTimeImmutable && $date < $from) {
                return false;
            }

            if ($to instanceof DateTimeImmutable && $date > $to) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<string, string>
     */
    private function getQuickMetricConfig(string $quickMetric): array
    {
        $map = $this->settingsService->getQuickMetrics();
        return $map[$quickMetric] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @param array<string, string> $metricConfig
     * @return array<int, array<string, mixed>>
     */
    private function applyQuickMetricFilter(array $examenes, array $metricConfig): array
    {
        if (isset($metricConfig['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug($metricConfig['estado']);
            return array_values(array_filter($examenes, function (array $row) use ($estadoSlug): bool {
                $rawEstado = $row['kanban_estado'] ?? ($row['estado'] ?? '');
                $estadoActual = $this->estadoService->normalizeSlug((string) $rawEstado);

                return $estadoActual === $estadoSlug;
            }));
        }

        if (isset($metricConfig['sla_status'])) {
            return array_values(array_filter(
                $examenes,
                static fn(array $row): bool => ($row['sla_status'] ?? '') === $metricConfig['sla_status']
            ));
        }

        return $examenes;
    }

    /**
     * @param array<string, string|null> $filters
     * @return array<int, array<string, string>>
     */
    private function buildReportFiltersSummary(array $filters, ?string $metricLabel): array
    {
        $summary = [];

        if (!empty($filters['search'])) {
            $summary[] = ['label' => 'Buscar', 'value' => $filters['search']];
        }
        if (!empty($filters['doctor'])) {
            $summary[] = ['label' => 'Doctor', 'value' => $filters['doctor']];
        }
        if (!empty($filters['afiliacion'])) {
            $summary[] = ['label' => 'Afiliación', 'value' => $filters['afiliacion']];
        }
        if (!empty($filters['prioridad'])) {
            $summary[] = ['label' => 'Prioridad', 'value' => $filters['prioridad']];
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $from = $filters['date_from'] ?? '—';
            $to = $filters['date_to'] ?? '—';
            $summary[] = ['label' => 'Fecha', 'value' => sprintf('%s a %s', $from, $to)];
        }

        if (!empty($filters['estado'])) {
            $summary[] = ['label' => 'Estado/Columna', 'value' => $filters['estado']];
        }

        if ($metricLabel) {
            $summary[] = ['label' => 'Quick report', 'value' => $metricLabel];
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $filtersInput
     * @return array{
     *     filters: array<string, string|null>,
     *     rows: array<int, array<string, mixed>>,
     *     filtersSummary: array<int, array<string, string>>,
     *     metricLabel: string|null
     * }
     */
    private function buildReportData(array $filtersInput, string $quickMetric): array
    {
        $filters = $this->sanitizeReportFilters($filtersInput);

        $queryFilters = [
            'doctor' => $filters['doctor'],
            'afiliacion' => $filters['afiliacion'],
            'prioridad' => $filters['prioridad'],
        ];

        $examenes = $this->examenModel->fetchExamenesConDetallesFiltrado($queryFilters);
        $examenes = array_map([$this, 'transformExamenRow'], $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);
        $examenes = $this->applySearchFilter($examenes, $filters['search'] ?? '');
        $examenes = $this->applyDateRangeFilter($examenes, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if (!empty($filters['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug($filters['estado']);
            $examenes = array_values(array_filter(
                $examenes,
                fn(array $row): bool => $this->estadoService->normalizeSlug((string) ($row['kanban_estado'] ?? ($row['estado'] ?? ''))) === $estadoSlug
            ));
        }

        $metricConfig = $this->getQuickMetricConfig($quickMetric);
        $metricLabel = $metricConfig['label'] ?? null;
        if (!empty($metricConfig)) {
            $examenes = $this->applyQuickMetricFilter($examenes, $metricConfig);
        }

        $filtersSummary = $this->buildReportFiltersSummary($filters, $metricLabel);

        return [
            'filters' => $filters,
            'rows' => $examenes,
            'filtersSummary' => $filtersSummary,
            'metricLabel' => $metricLabel,
        ];
    }

    private function isQuickMetricAllowed(string $quickMetric): bool
    {
        $metrics = $this->settingsService->getQuickMetrics();
        return array_key_exists($quickMetric, $metrics);
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    private function transformExamenRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        // Reutilizamos los mismos nombres de campos que espera el front-end de solicitudes
        // para que el tablero compartido muestre la información correcta de los exámenes.
        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        }

        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? $row['examen_codigo'] ?? null;
        }

        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? $row['examen_nombre'] ?? null;
        }

        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }

        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }

        $dias = 0;
        $fechaReferencia = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        if ($fechaReferencia) {
            $dt = $this->parseFecha($fechaReferencia);
            if ($dt) {
                $dias = max(0, (int) floor((time() - $dt->getTimestamp()) / 86400));
            }
        }

        $row['dias_transcurridos'] = $dias;

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

    private function ordenarExamenes(array $examenes, string $criterio): array
    {
        $criterio = strtolower(trim($criterio));

        $comparador = match ($criterio) {
            'fecha_asc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'consulta_fecha', true),
            'creado_desc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'created_at', false),
            'creado_asc' => fn($a, $b) => $this->compararPorFecha($a, $b, 'created_at', true),
            default => fn($a, $b) => $this->compararPorFecha($a, $b, 'consulta_fecha', false),
        };

        usort($examenes, $comparador);

        return $examenes;
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

    private function parseFecha($valor): ?\DateTimeImmutable
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($valor);
        }

        $string = is_string($valor) ? trim($valor) : '';
        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof \DateTimeImmutable) {
                if ($format === 'Y-m-d') {
                    $dt = $dt->setTime(0, 0);
                }
                return $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp !== false) {
            return (new \DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    private function limitarExamenesPorEstado(array $examenes, int $limitePorColumna): array
    {
        if ($limitePorColumna <= 0) {
            return $examenes;
        }

        $contadores = [];
        $filtrados = [];

        foreach ($examenes as $examen) {
            $estadoBase = $examen['kanban_estado'] ?? $examen['estado'] ?? 'Pendiente';
            $estado = strtolower(trim((string) $estadoBase));
            $contadores[$estado] = ($contadores[$estado] ?? 0) + 1;

            if ($contadores[$estado] <= $limitePorColumna) {
                $filtrados[] = $examen;
            }
        }

        return $filtrados;
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'llamado' => 'Llamado',
            'revision-cobertura' => 'Revisión de Cobertura',
            'revision-de-cobertura' => 'Revisión de Cobertura',
            'revision-codigos' => 'Revisión de Cobertura',
            'revision-de-codigos' => 'Revisión de Cobertura',
            'revision de cobertura' => 'Revisión de Cobertura',
            'revision cobertura' => 'Revisión de Cobertura',
            'listo para agenda' => 'Listo para Agenda',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);
        $clave = strtr($clave, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return $mapa[$clave] ?? null;
    }
}
