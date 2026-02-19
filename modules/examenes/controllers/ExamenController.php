<?php

namespace Controllers;

use Core\BaseController;
use DateTimeImmutable;
use Helpers\JsonLogger;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Examenes\Models\ExamenModel;
use Modules\Examenes\Services\ExamenCrmService;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Examenes\Services\ExamenMailLogService;
use Modules\Examenes\Services\ExamenReportExcelService;
use Modules\Examenes\Services\ExamenReminderService;
use Modules\Examenes\Services\ExamenSettingsService;
use Modules\Examenes\Services\NasImagenesService;
use Modules\Mail\Services\MailProfileService;
use Modules\Mail\Services\NotificationMailer;
use Modules\MailTemplates\Services\CoberturaMailTemplateService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Pacientes\Services\PacienteService;
use Modules\Reporting\Services\ReportService;
use PDO;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;
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
    private NasImagenesService $nasImagenesService;
    private ?array $bodyCache = null;

    private const PUSHER_CHANNEL = 'examenes-kanban';
    private const STORAGE_PATH = 'uploads/examenes';
    private const COBERTURA_MAIL_TO = 'cespinoza@cive.ec';
    private const COBERTURA_MAIL_CC = ['oespinoza@cive.ec'];

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
        $this->nasImagenesService = new NasImagenesService();
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
            'afiliacion' => trim((string)($payload['afiliacion'] ?? '')),
            'doctor' => trim((string)($payload['doctor'] ?? '')),
            'prioridad' => trim((string)($payload['prioridad'] ?? '')),
            'estado' => trim((string)($payload['estado'] ?? '')),
            'fechaTexto' => trim((string)($payload['fechaTexto'] ?? '')),
            'con_pendientes' => (string)($payload['con_pendientes'] ?? ''),
        ];

        $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
        $pipelineStages = $this->leadConfig->getPipelineStages();

        try {
            $examenes = $this->examenModel->fetchExamenesConDetallesFiltrado($filtros);
            $examenes = array_map([$this, 'transformExamenRow'], $examenes);
            $examenes = $this->estadoService->enrichExamenes($examenes);
            $examenes = $this->agruparExamenesPorSolicitud($examenes);
            if (in_array(strtolower(trim((string)($filtros['con_pendientes'] ?? ''))), ['1', 'true', 'si', 'sí', 'yes'], true)) {
                $examenes = array_values(array_filter(
                    $examenes,
                    static fn(array $item): bool => (int)($item['pendientes_estudios_total'] ?? 0) > 0
                ));
            }
            $examenes = $this->ordenarExamenes($examenes, $kanbanPreferences['sort'] ?? 'fecha_desc');
            $examenes = $this->limitarExamenesPorEstado($examenes, (int)($kanbanPreferences['column_limit'] ?? 0));

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
        $quickMetric = isset($payload['quickMetric']) ? trim((string)$payload['quickMetric']) : '';
        $format = strtolower(trim((string)($payload['format'] ?? 'pdf')));
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
        $quickMetric = isset($payload['quickMetric']) ? trim((string)$payload['quickMetric']) : '';
        $format = strtolower(trim((string)($payload['format'] ?? 'excel')));
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
            $status = (int)($e->getCode() ?: 422);
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
            $status = (int)($e->getCode() ?: 422);
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
        $etapa = trim((string)($payload['etapa_slug'] ?? $payload['etapa'] ?? ''));
        $completado = isset($payload['completado']) ? (bool)$payload['completado'] : true;

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
        $nota = trim((string)($payload['nota'] ?? ''));

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
        $tareaId = isset($payload['tarea_id']) ? (int)$payload['tarea_id'] : 0;
        $estado = isset($payload['estado']) ? (string)$payload['estado'] : '';

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
        if ((int)($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
            $this->json(['success' => false, 'error' => 'El archivo es inválido'], 422);
            return;
        }

        $descripcion = isset($_POST['descripcion']) ? trim((string)$_POST['descripcion']) : null;
        $nombreOriginal = (string)($archivo['name'] ?? 'adjunto');
        $mimeType = isset($archivo['type']) ? (string)$archivo['type'] : null;
        $tamano = isset($archivo['size']) ? (int)$archivo['size'] : null;

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
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $estado = trim((string)($payload['estado'] ?? ''));
        $origen = trim((string)($payload['origen'] ?? 'kanban'));
        $observacion = isset($payload['observacion']) ? trim((string)$payload['observacion']) : null;

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
        $horas = isset($payload['horas']) ? (int)$payload['horas'] : 24;

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
            $estados = array_values(array_filter(array_map('trim', explode(',', (string)$_GET['estado']))));
        }

        try {
            $examenes = $this->examenModel->fetchTurneroExamenes($estados);

            foreach ($examenes as &$examen) {
                $nombreCompleto = trim((string)($examen['full_name'] ?? ''));
                $examen['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
                $examen['turno'] = isset($examen['turno']) ? (int)$examen['turno'] : null;
                $estadoNormalizado = $this->normalizarEstadoTurnero((string)($examen['estado'] ?? ''));
                $examen['estado'] = $estadoNormalizado ?? ($examen['estado'] ?? null);
                $examen['hora'] = null;
                $examen['fecha'] = null;

                if (!empty($examen['created_at'])) {
                    $timestamp = strtotime((string)$examen['created_at']);
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
        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        $turno = isset($payload['turno']) ? (int)$payload['turno'] : null;
        $estadoSolicitado = isset($payload['estado']) ? trim((string)$payload['estado']) : 'Llamado';
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

            $nombreCompleto = trim((string)($registro['full_name'] ?? ''));
            $registro['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
            $registro['estado'] = $this->normalizarEstadoTurnero((string)($registro['estado'] ?? '')) ?? ($registro['estado'] ?? null);

            try {
                $this->pusherConfig->trigger(
                    [
                        'id' => (int)($registro['id'] ?? $id ?? 0),
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
                            'id' => (int)($registro['id'] ?? $id ?? 0),
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
        int     $id,
        array   $campos,
        ?int    $changedBy = null,
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
            $this->json($this->obtenerEstadosPorHc((string)$hcNumber));
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
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($id <= 0 && isset($payload['examen_id'])) {
            $id = (int)$payload['examen_id'];
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
                isset($payload['observacion']) ? trim((string)$payload['observacion']) : null
            );
            $status = (!is_array($resultado) || ($resultado['success'] ?? false) === false) ? 422 : 200;
            $this->json(is_array($resultado) ? $resultado : ['success' => false], $status);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Error al actualizar el examen'], 500);
        }
    }

    public function derivacionDetalle(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string) ($_GET['hc_number'] ?? ''));
        $formId = trim((string) ($_GET['form_id'] ?? ''));
        $examenId = isset($_GET['examen_id']) ? (int) $_GET['examen_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            $this->json(
                [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar la derivación.',
                ],
                400
            );
            return;
        }

        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, $examenId);
        } catch (Throwable $e) {
            $this->json(
                [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'error',
                    'derivacion' => null,
                ],
                200
            );
            return;
        }

        if (!$derivacion) {
            $this->json(
                [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'missing',
                    'message' => 'No hay derivación registrada para este examen.',
                    'derivacion' => null,
                ]
            );
            return;
        }

        $vigenciaStatus = $this->resolveDerivacionVigenciaStatus(
            isset($derivacion['fecha_vigencia']) && is_string($derivacion['fecha_vigencia'])
                ? $derivacion['fecha_vigencia']
                : null
        );
        $estadoSugerido = null;
        if ($vigenciaStatus) {
            $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
            if ($examen) {
                $estadoSugerido = $this->resolveEstadoPorDerivacion($vigenciaStatus, (string) ($examen['estado'] ?? ''));
                if ($estadoSugerido) {
                    $this->actualizarEstadoPorFormHc(
                        $formId,
                        $hcNumber,
                        $estadoSugerido,
                        $this->getCurrentUserId(),
                        'derivacion_vigencia',
                        'Actualizado por vigencia de derivación'
                    );
                }
            }
        }

        $this->json(
            [
                'success' => true,
                'has_derivacion' => true,
                'derivacion_status' => 'ok',
                'message' => null,
                'derivacion' => $derivacion,
                'vigencia_status' => $vigenciaStatus,
                'estado_sugerido' => $estadoSugerido,
            ]
        );
    }

    public function derivacionPreseleccion(): void
    {
        $this->requireAuth();

        $payload = $this->getRequestBody();
        $hcNumber = trim((string) ($payload['hc_number'] ?? $_POST['hc_number'] ?? ''));
        $formId = trim((string) ($payload['form_id'] ?? $_POST['form_id'] ?? ''));
        $examenId = isset($payload['examen_id'])
            ? (int) $payload['examen_id']
            : (isset($_POST['examen_id']) ? (int) $_POST['examen_id'] : null);

        if ($hcNumber === '' || $formId === '') {
            $this->json(
                [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar derivaciones disponibles.',
                ],
                400
            );
            return;
        }

        $seleccion = null;
        if ($examenId) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }

        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        if (!empty($seleccion['derivacion_pedido_id'])) {
            $this->json([
                'success' => true,
                'selected' => [
                    'codigo_derivacion' => $seleccion['derivacion_codigo'] ?? null,
                    'pedido_id_mas_antiguo' => $seleccion['derivacion_pedido_id'] ?? null,
                    'lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
                    'fecha_vigencia' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
                    'prefactura' => $seleccion['derivacion_prefactura'] ?? null,
                ],
                'needs_selection' => false,
                'options' => [],
            ]);
            return;
        }

        $script = BASE_PATH . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            $this->json(
                [
                    'success' => false,
                    'message' => 'No se encontró el script de admisiones.',
                ],
                500
            );
            return;
        }

        $cmd = sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $rawOutput = trim(implode("\n", $output));
        $parsed = null;

        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim((string) $output[$i]);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if (!$parsed) {
            $this->json(
                [
                    'success' => false,
                    'message' => 'No se pudo interpretar la respuesta del scraper de admisiones.',
                    'raw_output' => $rawOutput,
                    'exit_code' => $exitCode,
                ],
                500
            );
            return;
        }

        $grouped = $parsed['grouped'] ?? [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = $item['data'] ?? [];
            $options[] = [
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ];
        }

        $this->json([
            'success' => true,
            'selected' => null,
            'needs_selection' => true,
            'options' => $options,
        ]);
    }

    public function guardarDerivacionPreseleccion(): void
    {
        $this->requireAuth();

        $payload = $this->getRequestBody();
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;
        $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura = trim((string) ($payload['prefactura'] ?? ''));

        if (!$examenId || $codigo === '' || $pedidoId === '') {
            $this->json(
                [
                    'success' => false,
                    'message' => 'Datos incompletos para guardar la derivación seleccionada.',
                ],
                422
            );
            return;
        }

        $saved = $this->examenModel->guardarDerivacionPreseleccion($examenId, [
            'derivacion_codigo' => $codigo,
            'derivacion_pedido_id' => $pedidoId,
            'derivacion_lateralidad' => $lateralidad !== '' ? $lateralidad : null,
            'derivacion_fecha_vigencia_sel' => $vigencia !== '' ? $vigencia : null,
            'derivacion_prefactura' => $prefactura !== '' ? $prefactura : null,
        ]);

        $this->json([
            'success' => $saved,
        ]);
    }

    public function prefactura(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));
        $formId = trim((string)($_GET['form_id'] ?? ''));
        $examenId = isset($_GET['examen_id']) ? (int)$_GET['examen_id'] : null;

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

            $afiliacion = trim((string)($viewData['paciente']['afiliacion'] ?? ($viewData['examen']['afiliacion'] ?? '')));
            $templateService = new CoberturaMailTemplateService($this->pdo);
            $templateKey = $templateService->resolveImagenesTemplateKey($afiliacion);
            $templateAvailable = false;
            if ($templateKey && $templateService->hasEnabledTemplate($templateKey)) {
                $templateAvailable = true;
            } else {
                $baseTemplateKey = $templateService->resolveTemplateKey($afiliacion);
                $examenTemplateKey = $baseTemplateKey ? $baseTemplateKey . '_examenes' : null;
                if ($examenTemplateKey && $templateService->hasEnabledTemplate($examenTemplateKey)) {
                    $templateKey = $examenTemplateKey;
                    $templateAvailable = true;
                } elseif ($baseTemplateKey && $templateService->hasEnabledTemplate($baseTemplateKey)) {
                    $templateKey = $baseTemplateKey;
                    $templateAvailable = true;
                }
            }
            $viewData['coberturaTemplateKey'] = $templateKey;
            $viewData['coberturaTemplateAvailable'] = $templateAvailable;
            $viewData['coberturaMailLog'] = null;
            $examenIdValue = isset($viewData['examen']['id']) ? (int) $viewData['examen']['id'] : null;
            if ($examenIdValue) {
                $mailLogService = new ExamenMailLogService($this->pdo);
                $viewData['coberturaMailLog'] = $mailLogService->fetchLatestByExamen($examenIdValue);
            }

            ob_start();
            include __DIR__ . '/../views/prefactura_detalle.php';
            echo ob_get_clean();
    }

    public function enviarCoberturaMail(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $subject = trim((string)($payload['subject'] ?? $_POST['subject'] ?? ''));
        $body = trim((string)($payload['body'] ?? $_POST['body'] ?? ''));
        $toRaw = trim((string)($payload['to'] ?? $_POST['to'] ?? ''));
        $ccRaw = trim((string)($payload['cc'] ?? $_POST['cc'] ?? ''));
        $isHtml = filter_var($payload['is_html'] ?? $_POST['is_html'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $examenId = isset($payload['examen_id'])
            ? (int) $payload['examen_id']
            : (isset($_POST['examen_id']) ? (int) $_POST['examen_id'] : null);
        $formId = trim((string)($payload['form_id'] ?? $_POST['form_id'] ?? ''));
        $hcNumber = trim((string)($payload['hc_number'] ?? $_POST['hc_number'] ?? ''));
        $afiliacion = trim((string)($payload['afiliacion'] ?? $_POST['afiliacion'] ?? ''));
        $templateKey = trim((string)($payload['template_key'] ?? $_POST['template_key'] ?? ''));
        $derivacionPdf = trim((string)($payload['derivacion_pdf'] ?? $_POST['derivacion_pdf'] ?? ''));
        $currentUserId = $this->getCurrentUserId();

        JsonLogger::log(
            'examenes_mail',
            'Cobertura mail ▶ Payload recibido',
            null,
            [
                'examen_id' => $examenId,
                'form_id' => $formId !== '' ? $formId : null,
                'hc_number' => $hcNumber !== '' ? $hcNumber : null,
                'user_id' => $currentUserId,
            ]
        );

        if ($formId !== '' && $hcNumber !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM consulta_examenes
                 WHERE form_id = :form_id AND hc_number = :hc
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([
                ':form_id' => $formId,
                ':hc' => $hcNumber,
            ]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                $resolvedId = (int) $resolved;
                if ($examenId && $examenId !== $resolvedId) {
                    JsonLogger::log(
                        'examenes_mail',
                        'Cobertura mail ▶ examen_id corregido',
                        null,
                        [
                            'examen_id_payload' => $examenId,
                            'examen_id_resolved' => $resolvedId,
                            'form_id' => $formId,
                            'hc_number' => $hcNumber,
                            'user_id' => $currentUserId,
                        ]
                    );
                }
                $examenId = $resolvedId;
            }
        }

        JsonLogger::log(
            'examenes_mail',
            'Cobertura mail ▶ Examen resuelto',
            null,
            [
                'examen_id' => $examenId,
                'form_id' => $formId !== '' ? $formId : null,
                'hc_number' => $hcNumber !== '' ? $hcNumber : null,
                'user_id' => $currentUserId,
            ]
        );

        if ($subject === '' || $body === '') {
            $this->json(['success' => false, 'error' => 'Asunto y mensaje son obligatorios'], 422);
            return;
        }

        $toList = $this->parseCoberturaEmails($toRaw);
        $ccList = $this->parseCoberturaEmails($ccRaw);
        if ($toList === []) {
            $toList = [self::COBERTURA_MAIL_TO];
        }

        $attachments = [];
        $generatedFiles = [];
        $autoAttachment = $this->buildCobertura012AAttachment($formId, $hcNumber, $examenId);
        if ($autoAttachment) {
            $attachments[] = $autoAttachment;
            $generatedFiles[] = $autoAttachment['path'];
        }
        $attachment = $this->getCoberturaAttachment();
        if ($attachment) {
            $attachments[] = $attachment;
        }

        $profileService = new MailProfileService($this->pdo);
        $profileSlug = $profileService->getProfileSlugForContext('examenes');
        $mailer = new NotificationMailer($this->pdo, $profileSlug);
        $toList = array_values(array_unique($toList));
        $ccList = array_values(array_unique(array_merge($ccList, self::COBERTURA_MAIL_CC)));
        $result = $mailer->sendPatientUpdate($toList, $subject, $body, $ccList, $attachments, $isHtml, $profileSlug);
        foreach ($generatedFiles as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        $sentAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $bodyText = $this->formatCoberturaMailBodyText($body, $isHtml);
        $mailLogService = new ExamenMailLogService($this->pdo);
        $mailLogPayload = [
            'examen_id' => $examenId ?: null,
            'form_id' => $formId !== '' ? $formId : null,
            'hc_number' => $hcNumber !== '' ? $hcNumber : null,
            'to_emails' => implode(', ', $toList),
            'cc_emails' => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject' => $subject,
            'body_text' => $bodyText !== '' ? $bodyText : null,
            'body_html' => $isHtml ? $body : null,
            'channel' => 'email',
            'sent_by_user_id' => $currentUserId,
            'status' => 'sent',
            'error_message' => null,
            'sent_at' => $sentAt,
        ];

        if (!($result['success'] ?? false)) {
            $mailLogPayload['status'] = 'failed';
            $mailLogPayload['error_message'] = $result['error'] ?? 'No se pudo enviar el correo';
            try {
                $mailLogService->create($mailLogPayload);
            } catch (Throwable $e) {
                JsonLogger::log(
                    'examenes_mail',
                    'No se pudo guardar el log de correo fallido',
                    $e,
                    [
                        'examen_id' => $examenId,
                        'user_id' => $currentUserId,
                    ]
                );
            }
            $this->json(
                ['success' => false, 'error' => $result['error'] ?? 'No se pudo enviar el correo'],
                500
            );
            return;
        }

        $mailLogId = null;
        try {
            $mailLogId = $mailLogService->create($mailLogPayload);
        } catch (Throwable $e) {
            JsonLogger::log(
                'examenes_mail',
                'No se pudo guardar el log de correo enviado',
                $e,
                [
                    'examen_id' => $examenId,
                    'user_id' => $currentUserId,
                ]
            );
        }

        if ($examenId) {
            $notaLineas = [
                'Cobertura solicitada por correo',
                'Para: ' . implode(', ', $toList),
            ];
            if ($ccList !== []) {
                $notaLineas[] = 'CC: ' . implode(', ', $ccList);
            }
            $notaLineas[] = 'Asunto: ' . $subject;
            if ($templateKey !== '') {
                $notaLineas[] = 'Plantilla: ' . $templateKey;
            }
            if ($derivacionPdf !== '') {
                $notaLineas[] = 'PDF derivación: ' . $derivacionPdf;
            }

            try {
                $this->crmService->registrarNota($examenId, implode("\n", $notaLineas), $currentUserId);
            } catch (Throwable $e) {
                JsonLogger::log(
                    'examenes_mail',
                    'No se pudo registrar la nota de cobertura enviada',
                    $e,
                    [
                        'examen_id' => $examenId,
                        'user_id' => $currentUserId,
                    ]
                );
            }
        }

        $sentByName = null;
        if ($mailLogId) {
            $mailLog = $mailLogService->fetchById($mailLogId);
            $sentByName = $mailLog['sent_by_name'] ?? null;
            $sentAt = $mailLog['sent_at'] ?? $sentAt;
        }

        $this->json([
            'success' => true,
            'ok' => true,
            'mail_log_id' => $mailLogId,
            'sent_at' => $sentAt,
            'sent_by' => $currentUserId,
            'sent_by_name' => $sentByName,
            'template_key' => $templateKey !== '' ? $templateKey : null,
        ]);
    }

    private function obtenerDatosParaVista(string $hcNumber, string $formId, ?int $examenId = null): array
    {
        $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
        if (!$examen) {
            return ['examen' => null];
        }

        $examenIdValue = isset($examen['id']) ? (int) $examen['id'] : 0;
        if ($examenIdValue > 0) {
            $this->ensureDerivacionPreseleccionAuto($hcNumber, $formId, $examenIdValue);
        }

        $derivacion = null;
        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, $examenIdValue > 0 ? $examenIdValue : null);
        } catch (Throwable $e) {
            $derivacion = null;
        }

        $fechaVigencia = $derivacion['fecha_vigencia'] ?? ($examen['derivacion_fecha_vigencia_sel'] ?? null);
        $vigenciaStatus = $this->resolveDerivacionVigenciaStatus(is_string($fechaVigencia) ? $fechaVigencia : null);
        $estadoSugerido = $this->resolveEstadoPorDerivacion($vigenciaStatus, (string) ($examen['estado'] ?? ''));
        if ($estadoSugerido) {
            $this->actualizarEstadoPorFormHc(
                $formId,
                $hcNumber,
                $estadoSugerido,
                $this->getCurrentUserId(),
                'derivacion_vigencia',
                'Actualizado por vigencia de derivación'
            );
            $examen['estado'] = $estadoSugerido;
        }

        $paciente = $this->pacienteService->getPatientDetails($hcNumber);
        $consulta = $this->examenModel->obtenerConsultaPorFormHc($formId, $hcNumber) ?? [];
        if (empty(trim((string)($consulta['doctor'] ?? '')))) {
            $doctorFromJoin = trim((string)($consulta['doctor_nombre'] ?? $consulta['procedimiento_doctor'] ?? ''));
            if ($doctorFromJoin !== '') {
                $consulta['doctor'] = $doctorFromJoin;
            }
        }
        $examenesRelacionados = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        $examenesRelacionados = array_map([$this, 'transformExamenRow'], $examenesRelacionados);
        $examenesRelacionados = $this->estadoService->enrichExamenes($examenesRelacionados);
        foreach ($examenesRelacionados as &$rel) {
            if (empty($rel['derivacion_status']) && $vigenciaStatus) {
                $rel['derivacion_status'] = $vigenciaStatus;
            }
        }
        unset($rel);

        $consultaSolicitante = trim((string)($consulta['solicitante'] ?? ''));
        if ($consultaSolicitante === '') {
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string)($rel['solicitante'] ?? ''));
                if ($candidate !== '') {
                    $consultaSolicitante = $candidate;
                    break;
                }
            }
            if ($consultaSolicitante !== '') {
                $consulta['solicitante'] = $consultaSolicitante;
            }
        }

        if (empty(trim((string)($consulta['doctor'] ?? '')))) {
            $doctor = '';
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string)($rel['doctor'] ?? $rel['solicitante'] ?? ''));
                if ($candidate !== '') {
                    $doctor = $candidate;
                    break;
                }
            }
            if ($doctor === '') {
                $doctor = $this->examenModel->obtenerDoctorProcedimientoProyectado($formId, $hcNumber) ?? '';
            }
            if ($doctor !== '') {
                $consulta['doctor'] = $doctor;
            }
        }

        $crmResumen = [];
        try {
            $crmResumen = $this->crmService->obtenerResumen((int)$examen['id']);
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
            'examenes_relacionados' => $examenesRelacionados,
            'trazabilidad' => $trazabilidad,
            'crm' => $crmResumen,
            'derivacion' => $derivacion,
            'derivacion_vigencia' => $vigenciaStatus,
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
                $nombre = trim((string)($item['nombre'] ?? $item['examen'] ?? $item['descripcion'] ?? ''));
                $codigo = trim((string)($item['codigo'] ?? $item['id'] ?? $item['code'] ?? ''));
                $fuente = trim((string)($item['fuente'] ?? $item['origen'] ?? 'Consulta')) ?: 'Consulta';
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
                $fuenteFinal = (string)$match['solicitante'];
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
                'Estado inicial: ' . ((string)($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        if (!empty($examen['updated_at']) && ($examen['updated_at'] ?? null) !== ($examen['created_at'] ?? null)) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'estado',
                $examen['updated_at'],
                'Actualización operativa',
                'Último estado reportado: ' . ((string)($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        foreach (($crmResumen['notas'] ?? []) as $nota) {
            $eventos[] = $this->crearEventoTrazabilidad(
                'nota',
                $nota['created_at'] ?? null,
                'Nota CRM',
                (string)($nota['nota'] ?? ''),
                $nota['autor_nombre'] ?? null
            );
        }

        foreach (($crmResumen['tareas'] ?? []) as $tarea) {
            $titulo = trim((string)($tarea['titulo'] ?? 'Tarea CRM'));
            $estado = trim((string)($tarea['estado'] ?? 'pendiente'));
            $descripcion = $titulo . ' · Estado: ' . $estado;
            if (!empty($tarea['due_date'])) {
                $descripcion .= ' · Vence: ' . (string)$tarea['due_date'];
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
            $descripcion = trim((string)($adjunto['descripcion'] ?? ''));
            $nombre = trim((string)($adjunto['nombre_original'] ?? 'Documento'));
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
                (string)($mailEvent['subject'] ?? 'Sin asunto'),
                $mailEvent['sent_by_name'] ?? null
            );
        }

        usort(
            $eventos,
            static function (array $a, array $b): int {
                return strtotime((string)($b['fecha'] ?? '')) <=> strtotime((string)($a['fecha'] ?? ''));
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

    private function mapearPlantillaInforme(string $tipoExamen): ?string
    {
        $texto = $this->normalizarTexto($tipoExamen);
        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, 'angio')) {
            return 'angio';
        }
        if (str_contains($texto, 'angulo')) {
            return 'angulo';
        }
        if (str_contains($texto, 'auto') || str_contains($texto, 'autorefrac')) {
            return 'auto';
        }
        if (str_contains($texto, 'biometria') || str_contains($texto, 'biometr')) {
            return 'biometria';
        }
        if (str_contains($texto, 'cornea') || str_contains($texto, 'corneal') || str_contains($texto, 'topograf')) {
            return 'cornea';
        }
        if (str_contains($texto, 'campo visual') || str_contains($texto, 'campimet') || preg_match('/\bcv\b/', $texto)) {
            return 'cv';
        }
        if (str_contains($texto, 'eco') || str_contains($texto, 'ecografia')) {
            return 'eco';
        }
        if (
            str_contains($texto, 'oct') &&
            (
                str_contains($texto, 'nervio')
                || str_contains($texto, 'papila')
                || str_contains($texto, 'cfnr')
                || str_contains($texto, 'fibras nerviosas')
                || str_contains($texto, 'rnfl')
            )
        ) {
            return 'octno';
        }
        if (str_contains($texto, 'oct')) {
            return 'octm';
        }
        if (str_contains($texto, 'retino') || str_contains($texto, 'retin')) {
            return 'retino';
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, label: string, text: string}>
     */
    private function obtenerChecklistInforme(string $plantilla): array
    {
        $base = dirname(__DIR__) . '/resources/informes/';
        $file = $base . $plantilla . '.json';
        if (!is_file($file)) {
            return [];
        }

        $json = file_get_contents($file);
        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static function ($item): bool {
            return is_array($item);
        }));
    }

    public function informeDatos(): void
    {
        $this->requireAuth();

        $formId = trim((string)($_GET['form_id'] ?? ''));
        $tipoExamen = trim((string)($_GET['tipo_examen'] ?? ''));

        if ($formId === '' || $tipoExamen === '') {
            $this->json(['success' => false, 'error' => 'Parámetros incompletos'], 400);
            return;
        }

        $informe = $this->examenModel->obtenerInformeImagen($formId);
        $plantilla = $informe['plantilla'] ?? null;
        if ($plantilla === null) {
            $plantilla = $this->mapearPlantillaInforme($tipoExamen);
        }
        if ($plantilla === null) {
            $this->json(['success' => false, 'error' => 'No hay plantilla para este examen'], 404);
            return;
        }
        $payload = null;
        if ($informe && isset($informe['payload_json'])) {
            $decoded = json_decode((string)$informe['payload_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }
        if (is_array($payload) && !isset($payload['firmante_id']) && isset($informe['firmado_por'])) {
            $payload['firmante_id'] = $informe['firmado_por'];
        }

        $this->json([
            'success' => true,
            'plantilla' => $plantilla,
            'payload' => $payload,
            'exists' => $informe !== null,
            'updated_at' => $informe['updated_at'] ?? null,
        ]);
    }

    public function informeGuardar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $formId = trim((string)($payload['form_id'] ?? ''));
        $hcNumber = trim((string)($payload['hc_number'] ?? ''));
        $tipoExamen = trim((string)($payload['tipo_examen'] ?? ''));
        $plantilla = trim((string)($payload['plantilla'] ?? ''));
        $data = $payload['payload'] ?? null;
        $firmanteId = isset($payload['firmante_id']) ? (int)$payload['firmante_id'] : 0;

        if (is_object($data)) {
            $data = (array)$data;
        }

        if ($formId === '' || $tipoExamen === '' || !is_array($data)) {
            $this->json(['success' => false, 'error' => 'Datos incompletos para guardar'], 422);
            return;
        }

        $plantillaEsperada = $this->mapearPlantillaInforme($tipoExamen);
        if ($plantillaEsperada === null) {
            $this->json(['success' => false, 'error' => 'No hay plantilla para este examen'], 422);
            return;
        }

        if ($plantilla === '' || $plantilla !== $plantillaEsperada) {
            $plantilla = $plantillaEsperada;
        }

        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $this->json(['success' => false, 'error' => 'No se pudo serializar el informe'], 500);
            return;
        }

        $firmanteId = $firmanteId > 0 ? $firmanteId : null;

        try {
            $ok = $this->examenModel->guardarInformeImagen(
                $formId,
                $hcNumber !== '' ? $hcNumber : null,
                $tipoExamen,
                $plantilla,
                $payloadJson,
                $this->getCurrentUserId(),
                $firmanteId
            );
            $this->json(['success' => $ok]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo guardar el informe'], 500);
        }
    }

    public function informePlantilla(): void
    {
        $this->requireAuth();

        $plantilla = trim((string)($_GET['plantilla'] ?? ''));
        $tipoExamen = trim((string)($_GET['tipo_examen'] ?? ''));

        if ($plantilla === '' && $tipoExamen !== '') {
            $plantilla = (string)($this->mapearPlantillaInforme($tipoExamen) ?? '');
        }

        if ($plantilla === '') {
            http_response_code(404);
            echo 'Plantilla no encontrada';
            return;
        }

        $view = __DIR__ . '/../views/informes/' . $plantilla . '.php';
        if (!is_file($view)) {
            http_response_code(404);
            echo 'Plantilla no disponible';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        $checkboxes = $this->obtenerChecklistInforme($plantilla);
        $usuariosFirmantes = $this->examenModel->listarUsuariosAsignables();
        $firmanteDefaultId = $this->getCurrentUserId();
        include $view;
    }

    public function imprimirInforme012B(): void
    {
        $this->requireAuth();

        $formId = trim((string)($_GET['form_id'] ?? ''));
        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));

        if ($formId === '' || $hcNumber === '') {
            http_response_code(400);
            echo '<p class="text-danger">Faltan parámetros para generar el informe.</p>';
            return;
        }
        try {
            $result = $this->renderInforme012B($formId, $hcNumber);
            $this->emitPdf($result['pdf'], $result['filename'], true);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'imagenes_informes',
                'Informe 012B falló',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                    'form_id' => $formId,
                ]
            );
            http_response_code(500);
            echo '<p class="text-danger">No se pudo generar el informe (ref: ' . $errorId . ').</p>';
        }
    }

    public function imprimirInforme012BPaquete(): void
    {
        $this->requireAuth();

        $formId = trim((string)($_GET['form_id'] ?? ''));
        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));

        if ($formId === '' || $hcNumber === '') {
            http_response_code(400);
            echo '<p class="text-danger">Faltan parámetros para generar el paquete.</p>';
            return;
        }

        try {
            $result = $this->buildPaqueteInformes([
                ['form_id' => $formId, 'hc_number' => $hcNumber],
            ]);
            $this->emitPdf($result['pdf'], $result['filename'], false);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'imagenes_informes',
                'Paquete 012B falló',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                ]
            );
            http_response_code(500);
            echo '<p class="text-danger">No se pudo generar el paquete (ref: ' . $errorId . ').</p>';
        }
    }

    public function imprimirInforme012BPaqueteSeleccion(): void
    {
        $this->requireAuth();

        $payload = $this->getRequestBody();
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if (empty($items)) {
            http_response_code(422);
            $this->json(['success' => false, 'error' => 'No se recibieron exámenes para el paquete.']);
            return;
        }

        try {
            $result = $this->buildPaqueteInformes($items);
            $this->emitPdf($result['pdf'], $result['filename'], false);
        } catch (RuntimeException $e) {
            http_response_code(422);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'imagenes_informes',
                'Paquete 012B falló',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );
            http_response_code(500);
            $this->json(['success' => false, 'error' => 'No se pudo generar el paquete (ref: ' . $errorId . ').']);
        }
    }

    public function imprimirCobertura012A(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));
        $formId = trim((string)($_GET['form_id'] ?? ''));
        $examenId = isset($_GET['examen_id']) ? (int)$_GET['examen_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            http_response_code(400);
            echo '<p class="text-danger">Faltan parámetros para generar el formulario 012A.</p>';
            return;
        }

        $attachment = $this->buildCobertura012AAttachment($formId, $hcNumber, $examenId);
        if (!$attachment || empty($attachment['path']) || !is_file($attachment['path'])) {
            http_response_code(500);
            echo '<p class="text-danger">No se pudo generar el formulario 012A.</p>';
            return;
        }

        $filename = $attachment['name'] ?? ('012A_' . $hcNumber . '_' . date('Ymd_His') . '.pdf');
        $content = @file_get_contents($attachment['path']);
        @unlink($attachment['path']);

        if ($content === false || $content === '') {
            http_response_code(500);
            echo '<p class="text-danger">No se pudo leer el formulario 012A.</p>';
            return;
        }

        if (!headers_sent()) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Length: ' . strlen($content));
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('X-Content-Type-Options: nosniff');
        }

        echo $content;
    }

    /**
     * @return array{pdf: string, filename: string, fecha: string|null, hc_number: string, tipo_examen: string}
     */
    private function renderInforme012B(string $formId, string $hcNumber): array
    {
        $procedimiento = $this->examenModel->obtenerProcedimientoProyectadoPorFormHc($formId, $hcNumber);
        if (!$procedimiento) {
            throw new RuntimeException('No se encontró el procedimiento solicitado.');
        }

        $paciente = $this->pacienteService->getPatientDetails($hcNumber);
        $informe = $this->examenModel->obtenerInformeImagen($formId);

        $payload = null;
        if ($informe && isset($informe['payload_json'])) {
            $decoded = json_decode((string)$informe['payload_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $tipoExamen = trim((string)($procedimiento['procedimiento_proyectado'] ?? ($informe['tipo_examen'] ?? '')));
        $plantilla = $informe['plantilla'] ?? null;
        if ($plantilla === null && $tipoExamen !== '') {
            $plantilla = $this->mapearPlantillaInforme($tipoExamen);
        }

        $parsed = $this->parseProcedimientoImagen($tipoExamen);
        $descripcionBase = $parsed['texto'] !== '' ? $parsed['texto'] : $tipoExamen;
        $descripcion = $descripcionBase;

        $codigoTarifario = $this->extraerCodigoTarifario($descripcionBase);
        if ($codigoTarifario !== null) {
            $tarifario = $this->obtenerTarifarioPorCodigo($codigoTarifario);
            if ($tarifario) {
                $nombreTarifario = trim((string) ($tarifario['descripcion'] ?? ($tarifario['short_description'] ?? '')));
                if ($nombreTarifario !== '') {
                    $descripcion = $nombreTarifario . ' (' . $tarifario['codigo'] . ')';
                }
            }
        }

        if ($parsed['ojo'] !== '') {
            $descripcion = trim($descripcion . ' - ' . $parsed['ojo']);
        }

        $hallazgos = $this->construirHallazgosInforme($payload, $plantilla);
        $conclusiones = $this->construirConclusionesInforme($payload);

        [$fechaInforme, $horaInforme] = $this->resolverFechaHoraInforme(
            $informe['updated_at'] ?? null,
            $procedimiento['fecha'] ?? null,
            $procedimiento['hora'] ?? null
        );

        $fechaNacimiento = $paciente['fecha_nacimiento'] ?? ($paciente['dob'] ?? ($paciente['DOB'] ?? null));
        $edad = $this->pacienteService->calcularEdad($fechaNacimiento, $fechaInforme);

        $patient = [
            'afiliacion' => $procedimiento['afiliacion'] ?? ($paciente['afiliacion'] ?? ''),
            'hc_number' => $paciente['hc_number'] ?? $hcNumber,
            'archive_number' => $paciente['hc_number'] ?? $hcNumber,
            'lname' => $paciente['lname'] ?? '',
            'lname2' => $paciente['lname2'] ?? '',
            'fname' => $paciente['fname'] ?? '',
            'mname' => $paciente['mname'] ?? '',
            'sexo' => $paciente['sexo'] ?? ($paciente['sex'] ?? ''),
            'fecha_nacimiento' => $fechaNacimiento ?? '',
            'edad' => $edad !== null ? (string)$edad : '',
        ];

        $firmanteId = null;
        if (is_array($payload) && isset($payload['firmante_id'])) {
            $firmanteId = (int)$payload['firmante_id'];
        }
        if (!$firmanteId && !empty($informe['firmado_por'])) {
            $firmanteId = (int)$informe['firmado_por'];
        }

        $firmante = $this->obtenerDatosFirmante($firmanteId);

        $reportService = new ReportService();
        $filename = '012B_' . ($patient['hc_number'] ?: $hcNumber) . '_' . date('Ymd_His') . '.pdf';

        $pdf = $reportService->renderPdf('012B', [
            'patient' => $patient,
            'examen' => [
                'descripcion' => $descripcion,
                'tipo_examen' => $tipoExamen,
            ],
            'informe' => [
                'hallazgos' => $hallazgos,
                'conclusiones' => $conclusiones,
                'fecha' => $fechaInforme,
                'hora' => $horaInforme,
            ],
            'firmante' => $firmante,
        ], [
            'destination' => 'S',
            'filename' => $filename,
        ]);

        if (strncmp($pdf, '%PDF-', 5) !== 0) {
            throw new RuntimeException('El informe 012B no generó un PDF válido.');
        }

        return [
            'pdf' => $pdf,
            'filename' => $filename,
            'fecha' => $fechaInforme ?: null,
            'hc_number' => $patient['hc_number'] ?: $hcNumber,
            'tipo_examen' => $tipoExamen,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{pdf: string, filename: string}
     */
    private function buildPaqueteInformes(array $items): array
    {
        $normalizados = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $formId = trim((string)($item['form_id'] ?? ''));
            $hcNumber = trim((string)($item['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }
            $key = $formId . '|' . $hcNumber;
            $normalizados[$key] = ['form_id' => $formId, 'hc_number' => $hcNumber];
        }

        if (empty($normalizados)) {
            throw new RuntimeException('No se recibieron exámenes válidos.');
        }

        $items = array_values($normalizados);
        $hcBase = $items[0]['hc_number'];
        foreach ($items as $item) {
            if ($item['hc_number'] !== $hcBase) {
                throw new RuntimeException('Los exámenes seleccionados deben ser del mismo paciente.');
            }
        }

        $tempFiles = [];
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $fechaReferencia = null;
        $timestampRef = null;
        $shouldAppendProtocoloPrequirurgico = false;

        foreach ($items as $item) {
            $rendered = $this->renderInforme012B($item['form_id'], $item['hc_number']);

            $timestamp = $this->parseTimestamp($rendered['fecha'] ?? null);
            if ($timestamp !== null && ($timestampRef === null || $timestamp > $timestampRef)) {
                $timestampRef = $timestamp;
                $fechaReferencia = $rendered['fecha'];
            }

            $tmpPdf = $this->writeTempFile($rendered['pdf'], 'pdf');
            $tempFiles[] = $tmpPdf;
            $this->appendPdfFile($pdf, $tmpPdf);

            if ($this->debeAdjuntarProtocoloPrequirurgico($rendered['tipo_examen'] ?? '')) {
                $shouldAppendProtocoloPrequirurgico = true;
            }

            $files = $this->nasImagenesService->listFiles($item['hc_number'], $item['form_id']);
            foreach ($files as $file) {
                $name = $file['name'] ?? '';
                if ($name === '') {
                    continue;
                }
                $opened = $this->nasImagenesService->openFile($item['hc_number'], $item['form_id'], $name);
                if (!$opened) {
                    continue;
                }
                $tmpFile = $this->writeTempStream($opened['stream'], $opened['ext']);
                $tempFiles[] = $tmpFile;
                fclose($opened['stream']);

                if (($opened['ext'] ?? '') === 'pdf') {
                    $this->appendPdfFile($pdf, $tmpFile);
                } else {
                    $this->appendImageFile($pdf, $tmpFile);
                }
            }
        }

        if ($shouldAppendProtocoloPrequirurgico) {
            $protocoloPrequirurgicoPdf = $this->renderProtocoloPrequirurgicoPdf($hcBase);
            $tmpProtocolo = $this->writeTempFile($protocoloPrequirurgicoPdf, 'pdf');
            $tempFiles[] = $tmpProtocolo;
            $this->appendPdfFile($pdf, $tmpProtocolo);
        }

        $filename = $this->buildPaqueteFilename($hcBase, $fechaReferencia);
        $content = $pdf->Output($filename, 'S');

        foreach ($tempFiles as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        if (strncmp($content, '%PDF-', 5) !== 0) {
            throw new RuntimeException('El paquete no generó un PDF válido.');
        }

        return ['pdf' => $content, 'filename' => $filename];
    }

    private function renderProtocoloPrequirurgicoPdf(string $hcNumber): string
    {
        $reportService = new ReportService();
        $filename = 'PROTOCOLO_PREQUIRURGICO_' . $hcNumber . '_' . date('Ymd_His') . '.pdf';
        $branding = $this->buildProtocoloPrequirurgicoBranding();
        $firmanteProtocolo = $this->obtenerDatosFirmante(31);

        $pdf = $reportService->renderPdf('protocolo_prequirurgico_catarata', [
            'hc_number' => $hcNumber,
            'fecha_emision' => date('Y-m-d'),
            'header_image_data_uri' => $branding['header'],
            'footer_image_data_uri' => $branding['footer'],
            'firmante' => $firmanteProtocolo,
        ], [
            'destination' => 'S',
            'filename' => $filename,
        ]);

        if (strncmp($pdf, '%PDF-', 5) !== 0) {
            throw new RuntimeException('El protocolo prequirúrgico no generó un PDF válido.');
        }

        return $pdf;
    }

    /**
     * @return array{header: string, footer: string}
     */
    private function buildProtocoloPrequirurgicoBranding(): array
    {
        $projectRoot = dirname(__DIR__, 3);
        $headerEnv = trim((string)($_ENV['PROTOCOLO_PREQ_HEADER_IMAGE'] ?? ''));
        $footerEnv = trim((string)($_ENV['PROTOCOLO_PREQ_FOOTER_IMAGE'] ?? ''));

        $headerCandidates = array_filter([
            $headerEnv !== '' ? $headerEnv : null,
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_header.png',
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_header.jpg',
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_header.jpeg',
        ]);

        $footerCandidates = array_filter([
            $footerEnv !== '' ? $footerEnv : null,
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_footer.png',
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_footer.jpg',
            $projectRoot . '/public/images/reporting/protocolo_prequirurgico_footer.jpeg',
        ]);

        return [
            'header' => $this->firstImageDataUri($headerCandidates),
            'footer' => $this->firstImageDataUri($footerCandidates),
        ];
    }

    /**
     * @param array<int, string> $paths
     */
    private function firstImageDataUri(array $paths): string
    {
        foreach ($paths as $path) {
            $path = trim((string)$path);
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false || $content === '') {
                continue;
            }
            $mime = (string)(@mime_content_type($path) ?: '');
            if (!str_starts_with($mime, 'image/')) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = $ext === 'jpg' || $ext === 'jpeg'
                    ? 'image/jpeg'
                    : ($ext === 'gif' ? 'image/gif' : 'image/png');
            }
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }

        return '';
    }

    private function debeAdjuntarProtocoloPrequirurgico(?string $tipoExamen): bool
    {
        $texto = $this->normalizarTextoBusqueda((string)($tipoExamen ?? ''));
        if ($texto === '') {
            return false;
        }

        if (preg_match('/\b(281186|281197|281230|281010|76512|281032)\b/', $texto) === 1) {
            return true;
        }

        $keywords = [
            'topografia corneal',
            'recuento de celulas endoteliales',
            'microscopia especular',
            'biometria ocular',
            'biometria de inmersion',
            'ultrasonido de segmento anterior',
            'b scan',
            'ecografia modo b',
            'biomicroscopia de alta resolucion',
            'tomografia',
            'oct macular',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($texto, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarTextoBusqueda(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    private function emitPdf(string $content, string $filename, bool $inline): void
    {
        if (!headers_sent()) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Length: ' . strlen($content));
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
            header('X-Content-Type-Options: nosniff');
        }
        echo $content;
    }

    private function parseTimestamp(?string $value): ?int
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp !== false ? $timestamp : null;
    }

    private function buildPaqueteFilename(string $hcNumber, ?string $fechaReferencia): string
    {
        $timestamp = $this->parseTimestamp($fechaReferencia) ?? time();
        $mes = date('m', $timestamp);
        $anio = date('Y', $timestamp);
        return 'IMAGENES_' . $hcNumber . '_' . $mes . '-' . $anio . '.pdf';
    }

    private function writeTempFile(string $content, string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'imgpdf_');
        if ($base === false) {
            throw new RuntimeException('No se pudo crear archivo temporal.');
        }
        $path = $base . '.' . $ext;
        rename($base, $path);
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * @param resource $stream
     */
    private function writeTempStream($stream, string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'imgpdf_');
        if ($base === false) {
            throw new RuntimeException('No se pudo crear archivo temporal.');
        }
        $path = $base . '.' . $ext;
        rename($base, $path);
        $dest = fopen($path, 'wb');
        if (!$dest) {
            throw new RuntimeException('No se pudo escribir archivo temporal.');
        }
        stream_copy_to_stream($stream, $dest);
        fclose($dest);
        return $path;
    }

    private function appendPdfFile(Fpdi $pdf, string $path): void
    {
        $pageCount = $pdf->setSourceFile($path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);
        }
    }

    private function appendImageFile(Fpdi $pdf, string $path): void
    {
        $info = @getimagesize($path);
        $width = $info[0] ?? 0;
        $height = $info[1] ?? 0;
        $orientation = ($width > $height) ? 'L' : 'P';

        $pdf->AddPage($orientation);
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        if ($width > 0 && $height > 0) {
            $ratio = min($pageWidth / $width, $pageHeight / $height);
            $renderWidth = $width * $ratio;
            $renderHeight = $height * $ratio;
            $x = ($pageWidth - $renderWidth) / 2;
            $y = ($pageHeight - $renderHeight) / 2;
            $pdf->Image($path, $x, $y, $renderWidth, $renderHeight);
        } else {
            $pdf->Image($path, 0, 0, $pageWidth, $pageHeight);
        }
    }

    /**
     * @return array{texto: string, ojo: string}
     */
    private function parseProcedimientoImagen(?string $raw): array
    {
        $texto = trim((string)($raw ?? ''));
        $ojo = '';

        if ($texto !== '' && preg_match('/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i', $texto, $match)) {
            $ojo = strtoupper(trim($match[1]));
            $texto = trim(substr($texto, 0, -strlen($match[0])));
        }

        if ($texto !== '') {
            $partes = preg_split('/\s-\s/', $texto) ?: [];
            if (isset($partes[0]) && strcasecmp(trim($partes[0]), 'IMAGENES') === 0) {
                array_shift($partes);
            }
            if (isset($partes[0]) && preg_match('/^IMA[-_]/i', trim($partes[0]))) {
                array_shift($partes);
            }
            $texto = trim(implode(' - ', array_map('trim', $partes)));
        }

        $ojoMap = [
            'OD' => 'Derecho',
            'OI' => 'Izquierdo',
            'AO' => 'Ambos ojos',
            'DERECHO' => 'Derecho',
            'IZQUIERDO' => 'Izquierdo',
            'AMBOS OJOS' => 'Ambos ojos',
        ];

        return [
            'texto' => $texto,
            'ojo' => $ojoMap[$ojo] ?? $ojo,
        ];
    }

    private function construirHallazgosInforme(?array $payload, ?string $plantilla): string
    {
        if (!$payload || !is_array($payload)) {
            return '';
        }

        $plantilla = $plantilla ?? '';

        if ($plantilla === 'octm') {
            $defecto = 'Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.';
            $ctmOd = trim((string)($payload['inputOD'] ?? ''));
            $ctmOi = trim((string)($payload['inputOI'] ?? ''));
            $textOd = trim((string)($payload['textOD'] ?? ''));
            $textOi = trim((string)($payload['textOI'] ?? ''));

            if ($ctmOd !== '' && $textOd === '') {
                $textOd = $defecto;
            }
            if ($ctmOi !== '' && $textOi === '') {
                $textOi = $defecto;
            }

            $lines = [];
            if ($ctmOd !== '') {
                $lines[] = 'GROSOR FOVEAL PROMEDIO OJO DERECHO: ' . $ctmOd . 'um';
            }
            if ($ctmOi !== '') {
                $lines[] = 'GROSOR FOVEAL PROMEDIO OJO IZQUIERDO: ' . $ctmOi . 'um';
            }

            if ($textOd !== '' || $textOi !== '') {
                $lines[] = 'LAS IMÁGENES SON SUGESTIVAS DE:';
                if ($textOd !== '') {
                    $lines[] = '**Ojo Derecho: **' . $textOd;
                }
                if ($textOi !== '') {
                    $lines[] = '**Ojo Izquierdo: **' . $textOi;
                }
            }

            return trim(implode("\n", $lines));
        }

        if ($plantilla === 'octno') {
            $odValor = trim((string)($payload['inputOD'] ?? ''));
            $oiValor = trim((string)($payload['inputOI'] ?? ''));

            $odCuadrantes = $this->resolverCuadrantesOctNo($payload, 'od');
            $oiCuadrantes = $this->resolverCuadrantesOctNo($payload, 'oi');

            $lines = [];
            $odBloque = $this->buildOctNoEyeBlock('OD', $odValor, $odCuadrantes);
            $oiBloque = $this->buildOctNoEyeBlock('OI', $oiValor, $oiCuadrantes);

            if ($odBloque !== '') {
                $lines[] = $odBloque;
            }
            if ($oiBloque !== '') {
                $lines[] = $oiBloque;
            }

            return trim(implode("\n\n", $lines));
        }

        if ($plantilla === 'biometria') {
            $odCamara = trim((string)($payload['camaraOD'] ?? ''));
            $odCristalino = trim((string)($payload['cristalinoOD'] ?? ''));
            $odAxial = trim((string)($payload['axialOD'] ?? ''));
            $oiCamara = trim((string)($payload['camaraOI'] ?? ''));
            $oiCristalino = trim((string)($payload['cristalinoOI'] ?? ''));
            $oiAxial = trim((string)($payload['axialOI'] ?? ''));

            $lines = [];
            if ($odCamara !== '' || $odCristalino !== '' || $odAxial !== '') {
                $lines[] = '**Ojo Derecho:**';
                if ($odCamara !== '') {
                    $lines[] = 'Cámara anterior: ' . $odCamara;
                }
                if ($odCristalino !== '') {
                    $lines[] = 'Cristalino: ' . $odCristalino;
                }
                if ($odAxial !== '') {
                    $lines[] = 'Longitud axial: ' . $odAxial;
                }
            }

            if ($oiCamara !== '' || $oiCristalino !== '' || $oiAxial !== '') {
                $lines[] = '**Ojo Izquierdo:**';
                if ($oiCamara !== '') {
                    $lines[] = 'Cámara anterior: ' . $oiCamara;
                }
                if ($oiCristalino !== '') {
                    $lines[] = 'Cristalino: ' . $oiCristalino;
                }
                if ($oiAxial !== '') {
                    $lines[] = 'Longitud axial: ' . $oiAxial;
                }
            }

            return trim(implode("\n", $lines));
        }

        $od = trim((string)($payload['inputOD'] ?? ''));
        $oi = trim((string)($payload['inputOI'] ?? ''));

        if ($plantilla === 'angulo') {
            if ($od !== '' && !preg_match('/°$/', $od)) {
                $od .= '°';
            }
            if ($oi !== '' && !preg_match('/°$/', $oi)) {
                $oi .= '°';
            }
        }

        $lines = [];
        if ($od !== '') {
            $lines[] = '**Ojo Derecho: **' . $od;
        }
        if ($oi !== '') {
            $lines[] = '**Ojo Izquierdo: **' . $oi;
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function resolverCuadrantesOctNo(array $payload, string $eye): array
    {
        $eye = strtolower($eye) === 'oi' ? 'oi' : 'od';

        $map = [
            'INF' => [
                'octno_' . $eye . '_inf',
                'checkboxI' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'SUP' => [
                'octno_' . $eye . '_sup',
                'checkboxS' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'NAS' => [
                'octno_' . $eye . '_nas',
                'checkboxN' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'TEMP' => [
                'octno_' . $eye . '_temp',
                'checkboxT' . ($eye === 'oi' ? '_OI' : ''),
            ],
        ];

        $activos = [];
        foreach ($map as $label => $keys) {
            foreach ($keys as $key) {
                if ($this->payloadFlagEnabled($payload[$key] ?? null)) {
                    $activos[] = $label;
                    break;
                }
            }
        }

        return $activos;
    }

    /**
     * @param mixed $value
     */
    private function payloadFlagEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float)$value > 0;
        }
        $text = trim((string)$value);
        if ($text === '') {
            return false;
        }
        return in_array(strtolower($text), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
    }

    /**
     * @param array<int, string> $cuadrantes
     */
    private function buildOctNoEyeBlock(string $eye, string $valor, array $cuadrantes): string
    {
        $valorNum = (float)str_replace(',', '.', $valor);
        $tieneValor = trim($valor) !== '';
        $tieneCuadrantes = !empty($cuadrantes);

        if (!$tieneValor && !$tieneCuadrantes) {
            return '';
        }

        $clasificacion = 'AL BORDE DE LIMITES NORMALES';
        if ($tieneValor && $valorNum >= 85) {
            $clasificacion = 'DENTRO DE LIMITES NORMALES';
        } elseif ($tieneCuadrantes) {
            $clasificacion = 'FUERA DE LIMITES NORMALES';
        }

        $lines = [];
        $lines[] = $eye === 'OD' ? 'OJO DERECHO' : 'OJO IZQUIERDO';
        $lines[] = 'CONFIABILIDAD: BUENA';

        if ($tieneCuadrantes) {
            $lines[] = 'SE APRECIA DISMINUCIÓN DEL ESPESOR DE CAPA DE FIBRAS NERVIOSAS RETINALES EN CUADRANTES ' . implode(', ', $cuadrantes) . '.';
        }

        if ($tieneValor) {
            $lines[] = 'PROMEDIO ESPESOR CFNR ' . $eye . ': ' . $valor . 'UM';
        }

        $lines[] = 'CLASIFICACIÓN: ' . $clasificacion;

        return implode("\n", $lines);
    }

    private function construirConclusionesInforme(?array $payload): string
    {
        if (!$payload || !is_array($payload)) {
            return '';
        }

        $keys = [
            'conclusiones',
            'conclusion',
            'conclusion_general',
            'conclusionGeneral',
            'conclusiones_generales',
            'conclusion_texto',
            'conclusionTexto',
            'diagnostico',
            'observaciones',
        ];

        foreach ($keys as $key) {
            if (!isset($payload[$key])) {
                continue;
            }
            $value = trim((string)$payload[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverFechaHoraInforme(?string $updatedAt, ?string $fechaExamen, ?string $horaExamen): array
    {
        $fecha = '';
        $hora = '';

        if ($updatedAt) {
            $timestamp = strtotime($updatedAt);
            if ($timestamp !== false) {
                $fecha = date('Y-m-d', $timestamp);
                $hora = date('H:i', $timestamp);
            }
        }

        if ($fecha === '' && $fechaExamen) {
            $fecha = (string)$fechaExamen;
        }

        if ($hora === '' && $horaExamen) {
            $hora = substr((string)$horaExamen, 0, 5);
        }

        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        if ($hora === '') {
            $hora = date('H:i');
        }

        return [$fecha, $hora];
    }

    private function extraerCodigoTarifario(string $texto): ?string
    {
        if (preg_match('/\b(\d{3,})\b/', $texto, $match)) {
            return $match[1];
        }

        return null;
    }

    private function obtenerTarifarioPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT codigo, descripcion, short_description FROM tarifario_2014 WHERE codigo = :codigo LIMIT 1'
        );
        $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $codigoSinCeros = ltrim($codigo, '0');
        if ($codigoSinCeros === '' || $codigoSinCeros === $codigo) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT codigo, descripcion, short_description FROM tarifario_2014 WHERE codigo = :codigo LIMIT 1'
        );
        $stmt->bindValue(':codigo', $codigoSinCeros, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{nombres: string, apellido1: string, apellido2: string, documento: string}
     */
    private function obtenerDatosFirmante(?int $firmanteId): array
    {
        if (!$firmanteId) {
            return [
                'nombres' => '',
                'apellido1' => '',
                'apellido2' => '',
                'documento' => '',
                'registro' => '',
                'firma' => '',
                'signature_path' => '',
            ];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $firmanteId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $nombres = trim((string)($row['first_name'] ?? ''));
        $segundoNombre = trim((string)($row['middle_name'] ?? ''));
        if ($segundoNombre !== '') {
            $nombres = trim($nombres . ' ' . $segundoNombre);
        }

        $apellido1 = trim((string)($row['last_name'] ?? ''));
        $apellido2 = trim((string)($row['second_last_name'] ?? ''));

        $documento = trim((string)($row['cedula'] ?? ''));
        $registro = trim((string)($row['registro'] ?? ''));

        return [
            'nombres' => $nombres,
            'apellido1' => $apellido1,
            'apellido2' => $apellido2,
            'documento' => $documento,
            'registro' => $registro,
            'firma' => (string)($row['firma'] ?? ''),
            'signature_path' => (string)($row['signature_path'] ?? ''),
        ];
    }


    public function imagenesRealizadas(): void
    {
        $this->requireAuth();

        $filters = $this->buildImagenesRealizadasFilters();
        $rows = $this->examenModel->fetchImagenesRealizadas($filters);

        $this->render(
            __DIR__ . '/../views/imagenes_realizadas.php',
            [
                'pageTitle' => 'Imágenes · Procedimientos proyectados',
                'imagenesRealizadas' => $rows,
                'filters' => $filters,
            ]
        );
    }

    public function imagenesNasList(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));
        $formId = trim((string)($_GET['form_id'] ?? ''));

        if ($hcNumber === '' || $formId === '') {
            $this->json(['success' => false, 'error' => 'Faltan parámetros para consultar imágenes.'], 422);
            return;
        }

        if (!$this->nasImagenesService->isAvailable()) {
            $this->json([
                'success' => false,
                'error' => $this->nasImagenesService->getLastError() ?? 'NAS no disponible.',
            ], 500);
            return;
        }

        $files = $this->nasImagenesService->listFiles($hcNumber, $formId);
        $error = $this->nasImagenesService->getLastError();

        $files = array_map(function (array $file) use ($hcNumber, $formId) {
            $name = $file['name'] ?? '';
            $url = '';
            if ($name !== '') {
                $url = '/imagenes/examenes-realizados/nas/file?hc_number=' . rawurlencode($hcNumber)
                    . '&form_id=' . rawurlencode($formId)
                    . '&file=' . rawurlencode($name);
            }
            $file['url'] = $url;
            return $file;
        }, $files);

        $this->json([
            'success' => $error === null,
            'files' => $files,
            'error' => $error,
        ]);
    }

    public function imagenesNasFile(): void
    {
        $this->requireAuth();

        $hcNumber = trim((string)($_GET['hc_number'] ?? ''));
        $formId = trim((string)($_GET['form_id'] ?? ''));
        $filename = trim((string)($_GET['file'] ?? ''));

        if ($hcNumber === '' || $formId === '' || $filename === '') {
            http_response_code(422);
            echo 'Parámetros incompletos';
            return;
        }

        if (!$this->nasImagenesService->isAvailable()) {
            http_response_code(500);
            echo $this->nasImagenesService->getLastError() ?? 'NAS no disponible.';
            return;
        }

        $opened = $this->nasImagenesService->openFile($hcNumber, $formId, $filename);
        if (!$opened || empty($opened['stream'])) {
            http_response_code(404);
            echo $this->nasImagenesService->getLastError() ?? 'Archivo no encontrado.';
            return;
        }

        $type = $opened['type'] ?? 'application/octet-stream';
        $size = (int)($opened['size'] ?? 0);
        $name = $opened['name'] ?? $filename;
        $stream = $opened['stream'];

        if (!headers_sent()) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: ' . $type);
            if ($size > 0) {
                header('Content-Length: ' . $size);
            }
            header('Content-Disposition: inline; filename="' . $name . '"');
            header('X-Content-Type-Options: nosniff');
        }

        fpassthru($stream);
        fclose($stream);
    }

    /**
     * @return array{
     *     fecha_inicio: string,
     *     fecha_fin: string,
     *     afiliacion: string,
     *     tipo_examen: string,
     *     paciente: string,
     *     estado_agenda: string
     * }
     */
    private function buildImagenesRealizadasFilters(): array
    {
        $fechaInicio = trim((string)($_GET['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($_GET['fecha_fin'] ?? ''));

        $fechaInicio = $this->normalizeDateFilter($fechaInicio, 'first day of this month');
        $fechaFin = $this->normalizeDateFilter($fechaFin, 'last day of this month');

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'afiliacion' => trim((string)($_GET['afiliacion'] ?? '')),
            'tipo_examen' => trim((string)($_GET['tipo_examen'] ?? '')),
            'paciente' => trim((string)($_GET['paciente'] ?? '')),
            'estado_agenda' => trim((string)($_GET['estado_agenda'] ?? '')),
        ];
    }

    private function normalizeDateFilter(string $input, string $fallback): string
    {
        if ($input !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $input);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return (new \DateTime($fallback))->format('Y-m-d');
    }

    public function actualizarImagenRealizada(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $tipoExamen = trim((string)($payload['tipo_examen'] ?? ''));

        if ($id <= 0 || $tipoExamen === '') {
            $this->json(['success' => false, 'error' => 'Datos incompletos para actualizar'], 422);
            return;
        }

        try {
            $ok = $this->examenModel->actualizarProcedimientoProyectado($id, $tipoExamen);
            $this->json(['success' => $ok]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo actualizar el procedimiento'], 500);
        }
    }

    public function eliminarImagenRealizada(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $id = isset($payload['id']) ? (int)$payload['id'] : 0;

        if ($id <= 0) {
            $this->json(['success' => false, 'error' => 'ID inválido'], 422);
            return;
        }

        try {
            $ok = $this->examenModel->eliminarProcedimientoProyectado($id);
            $this->json(['success' => $ok]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'No se pudo eliminar el procedimiento'], 500);
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

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string|null>
     */
    private function sanitizeReportFilters(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $doctor = trim((string)($filters['doctor'] ?? ''));
        $afiliacion = trim((string)($filters['afiliacion'] ?? ''));
        $prioridad = trim((string)($filters['prioridad'] ?? ''));
        $estado = trim((string)($filters['estado'] ?? ''));

        $allowedPriorities = ['normal', 'pendiente', 'urgente'];
        if ($prioridad !== '' && !in_array(strtolower($prioridad), $allowedPriorities, true)) {
            $prioridad = '';
        }

        $dateFrom = $this->normalizeDateInput($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($filters['date_to'] ?? null);

        if (!$dateFrom && !$dateTo && !empty($filters['fechaTexto'])) {
            [$dateFrom, $dateTo] = $this->parseDateRange((string)$filters['fechaTexto']);
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

        $value = trim((string)$value);
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
                    ? mb_strtolower((string)$value, 'UTF-8')
                    : strtolower((string)$value);

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
                $estadoActual = $this->estadoService->normalizeSlug((string)$rawEstado);

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
                fn(array $row): bool => $this->estadoService->normalizeSlug((string)($row['kanban_estado'] ?? ($row['estado'] ?? ''))) === $estadoSlug
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
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
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
                $dias = max(0, (int)floor((time() - $dt->getTimestamp()) / 86400));
            }
        }

        $row['dias_transcurridos'] = $dias;

        if (!empty($row['derivacion_fecha_vigencia_sel']) && empty($row['derivacion_fecha_vigencia'])) {
            $row['derivacion_fecha_vigencia'] = $row['derivacion_fecha_vigencia_sel'];
        }
        $row['derivacion_status'] = $this->resolveDerivacionVigenciaStatus(
            isset($row['derivacion_fecha_vigencia']) && is_string($row['derivacion_fecha_vigencia'])
                ? $row['derivacion_fecha_vigencia']
                : null
        );

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function agruparExamenesPorSolicitud(array $examenes): array
    {
        $agrupados = [];

        foreach ($examenes as $examen) {
            $formId = trim((string)($examen['form_id'] ?? ''));
            $hcNumber = trim((string)($examen['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $key = $formId . '|' . $hcNumber;
            $estadoEstudio = $this->normalizarEstadoCoberturaEstudio((string)($examen['estado'] ?? ''));

            if (!isset($agrupados[$key])) {
                $agrupados[$key] = $examen;
                $agrupados[$key]['estudios'] = [];
                $agrupados[$key]['resumen_estudios'] = [
                    'total' => 0,
                    'aprobados' => 0,
                    'pendientes' => 0,
                    'rechazados' => 0,
                    'sin_respuesta' => 0,
                ];
                $agrupados[$key]['kanban_rank'] = $this->rankEstadoKanban((string)($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            }

            $agrupados[$key]['resumen_estudios']['total']++;
            if (isset($agrupados[$key]['resumen_estudios'][$estadoEstudio])) {
                $agrupados[$key]['resumen_estudios'][$estadoEstudio]++;
            }

            $agrupados[$key]['estudios'][] = [
                'id' => $examen['id'] ?? null,
                'codigo' => $examen['examen_codigo'] ?? null,
                'nombre' => $examen['examen_nombre'] ?? null,
                'estado' => $examen['estado'] ?? null,
                'estado_cobertura' => $estadoEstudio,
                'updated_at' => $examen['updated_at'] ?? ($examen['consulta_fecha'] ?? $examen['created_at'] ?? null),
            ];

            $rank = $this->rankEstadoKanban((string)($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            if ($rank < (int)$agrupados[$key]['kanban_rank']) {
                $agrupados[$key]['kanban_rank'] = $rank;
                $agrupados[$key]['estado'] = $examen['estado'] ?? $agrupados[$key]['estado'];
                $agrupados[$key]['kanban_estado'] = $examen['kanban_estado'] ?? $agrupados[$key]['kanban_estado'] ?? $agrupados[$key]['estado'];
                $agrupados[$key]['kanban_estado_label'] = $examen['kanban_estado_label'] ?? $agrupados[$key]['kanban_estado_label'] ?? $agrupados[$key]['estado'];
            }
        }

        foreach ($agrupados as &$item) {
            $total = (int)($item['resumen_estudios']['total'] ?? 0);
            $aprobados = (int)($item['resumen_estudios']['aprobados'] ?? 0);
            $pendientes = (int)($item['resumen_estudios']['pendientes'] ?? 0);
            $rechazados = (int)($item['resumen_estudios']['rechazados'] ?? 0);
            $sinRespuesta = (int)($item['resumen_estudios']['sin_respuesta'] ?? 0);
            $noAprobados = $pendientes + $rechazados + $sinRespuesta;

            if ($total > 0 && $aprobados > 0 && $noAprobados > 0) {
                $item['estado'] = 'Parcial';
                $item['kanban_estado'] = 'parcial';
                $item['kanban_estado_label'] = 'Parcial';
            } elseif ($total > 0 && $aprobados === $total) {
                $item['estado'] = 'Listo para agenda';
                $item['kanban_estado'] = 'listo-para-agenda';
                $item['kanban_estado_label'] = 'Listo para agenda';
            }

            $pendientesTotal = $pendientes + $sinRespuesta;
            $item['alert_pendientes_estudios'] = $pendientesTotal > 0;
            $item['pendientes_estudios_total'] = $pendientesTotal;
            unset($item['kanban_rank']);
        }
        unset($item);

        return array_values($agrupados);
    }

    private function normalizarEstadoCoberturaEstudio(string $estado): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($estado), 'UTF-8') : strtolower(trim($estado));
        if ($value === '') {
            return 'sin_respuesta';
        }
        if (str_contains($value, 'aprobad')) {
            return 'aprobados';
        }
        if (str_contains($value, 'rechaz')) {
            return 'rechazados';
        }
        if (str_contains($value, 'pend') || str_contains($value, 'revision') || str_contains($value, 'recibid')) {
            return 'pendientes';
        }

        return 'sin_respuesta';
    }

    private function rankEstadoKanban(string $estado): int
    {
        $slug = $this->estadoService->normalizeSlug($estado);
        $rank = [
            'recibido' => 0,
            'llamado' => 1,
            'revision-cobertura' => 2,
            'parcial' => 3,
            'listo-para-agenda' => 4,
            'completado' => 5,
        ];

        return $rank[$slug] ?? 99;
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

    private function resolveDerivacionVigenciaStatus(?string $fechaVigencia): ?string
    {
        if (!$fechaVigencia) {
            return null;
        }

        $dt = $this->parseFecha($fechaVigencia);
        if (!$dt) {
            return null;
        }

        $hoy = new \DateTimeImmutable('today');
        return $dt >= $hoy ? 'vigente' : 'vencida';
    }

    private function resolveEstadoPorDerivacion(?string $vigenciaStatus, string $estadoActual): ?string
    {
        if (!$vigenciaStatus) {
            return null;
        }

        $slug = $this->estadoService->normalizeSlug($estadoActual);
        if ($slug === '') {
            $slug = 'recibido';
        }
        if ($slug === 'completado') {
            return null;
        }

        if ($vigenciaStatus === 'vencida') {
            return $slug !== 'revision-cobertura' ? 'Revisión de cobertura' : null;
        }

        if ($vigenciaStatus === 'vigente') {
            if (in_array($slug, ['recibido', 'llamado', 'revision-cobertura'], true)) {
                return 'Listo para agenda';
            }
        }

        return null;
    }

    private function actualizarEstadoPorFormHc(
        string $formId,
        string $hcNumber,
        string $estado,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): void {
        if ($formId === '' || $hcNumber === '') {
            return;
        }

        $examenes = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        foreach ($examenes as $registro) {
            $id = isset($registro['id']) ? (int) $registro['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $this->actualizarExamenParcial($id, ['estado' => $estado], $changedBy, $origen, $observacion);
        }
    }

    private function ensureDerivacionPreseleccionAuto(string $hcNumber, string $formId, int $examenId): void
    {
        $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        if (!empty($seleccion['derivacion_pedido_id'])) {
            return;
        }

        $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        if (!empty($seleccion['derivacion_pedido_id'])) {
            $this->examenModel->guardarDerivacionPreseleccion($examenId, [
                'derivacion_codigo' => $seleccion['derivacion_codigo'] ?? null,
                'derivacion_pedido_id' => $seleccion['derivacion_pedido_id'] ?? null,
                'derivacion_lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
                'derivacion_fecha_vigencia_sel' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
                'derivacion_prefactura' => $seleccion['derivacion_prefactura'] ?? null,
            ]);
            return;
        }

        $script = BASE_PATH . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            return;
        }

        $cmd = sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $parsed = null;
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim((string) $output[$i]);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if (!$parsed) {
            return;
        }

        $grouped = $parsed['grouped'] ?? [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = $item['data'] ?? [];
            $options[] = [
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ];
        }

        if (count($options) !== 1) {
            return;
        }

        $option = $options[0];
        $pedidoId = trim((string) ($option['pedido_id_mas_antiguo'] ?? ''));
        $codigo = trim((string) ($option['codigo_derivacion'] ?? ''));

        if ($pedidoId === '' || $codigo === '') {
            return;
        }

        $this->examenModel->guardarDerivacionPreseleccion($examenId, [
            'derivacion_codigo' => $codigo,
            'derivacion_pedido_id' => $pedidoId,
            'derivacion_lateralidad' => $option['lateralidad'] ?? null,
            'derivacion_fecha_vigencia_sel' => $option['fecha_vigencia'] ?? null,
            'derivacion_prefactura' => $option['prefactura'] ?? null,
        ]);
    }

    /**
     * Verifica derivación; si no existe, intenta scrapear y reconsultar.
     */
    private function ensureDerivacion(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $seleccion = null;
        if ($examenId) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }
        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        $lookupFormId = $seleccion['derivacion_pedido_id'] ?? $formId;
        $hasSelection = !empty($seleccion['derivacion_pedido_id']);

        if ($hasSelection) {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId((string) $lookupFormId);
            if ($derivacion !== false && $derivacion) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($formId);
            if ($derivacion !== false && $derivacion) {
                return $derivacion;
            }
        }

        $script = BASE_PATH . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return null;
        }

        $cmd = sprintf(
            'python3 %s %s %s',
            escapeshellarg($script),
            escapeshellarg($lookupFormId),
            escapeshellarg($hcNumber)
        );

        try {
            @exec($cmd);
        } catch (Throwable $e) {
            // Silenciar para no romper flujo
        }

        $derivacion = $this->examenModel->obtenerDerivacionPorFormId((string) $lookupFormId);
        if ($derivacion === false) {
            return null;
        }
        return $derivacion ?: null;
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
            $estado = strtolower(trim((string)$estadoBase));
            $contadores[$estado] = ($contadores[$estado] ?? 0) + 1;

            if ($contadores[$estado] <= $limitePorColumna) {
                $filtrados[] = $examen;
            }
        }

        return $filtrados;
    }

    /**
     * @return string[]
     */
    private function parseCoberturaEmails(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $candidates = preg_split('/[;,]+/', $raw) ?: [];
        $emails = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = strtolower($candidate);
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array{path: string, name?: string, type?: string, size?: int}|null
     */
    private function buildCobertura012AAttachment(string $formId, string $hcNumber, ?int $examenId): ?array
    {
        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        try {
            $viewData = $this->obtenerDatosParaVista($hcNumber, $formId, $examenId);
        } catch (Throwable $e) {
            JsonLogger::log(
                'examenes_mail',
                'No se pudo construir datos para 012A',
                $e,
                [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'examen_id' => $examenId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );
            return null;
        }

        if (empty($viewData['examen'])) {
            return null;
        }

        $dxDerivacion = [];
        if (!empty($viewData['derivacion']['diagnostico'])) {
            $dxDerivacion[] = ['diagnostico' => $viewData['derivacion']['diagnostico']];
        }

        $payload = [
            'paciente' => $viewData['paciente'] ?? [],
            'consulta' => $viewData['consulta'] ?? [],
            'diagnostico' => $viewData['diagnostico'] ?? [],
            'dx_derivacion' => $dxDerivacion,
            'solicitud' => [
                'created_at' => $viewData['examen']['created_at'] ?? null,
                'created_at_date' => $viewData['examen']['created_at'] ?? null,
                'created_at_time' => $viewData['examen']['created_at'] ?? null,
            ],
            'examenes_relacionados' => $viewData['examenes_relacionados'] ?? [],
            'imagenes_solicitadas' => $viewData['imagenes_solicitadas'] ?? [],
        ];

        $filename = '012A_' . ($hcNumber !== '' ? $hcNumber : 'paciente') . '_' . date('Ymd_His') . '.pdf';
        $reportService = new ReportService();

        try {
            $pdf = $reportService->renderPdf('012A', $payload, [
                'destination' => 'S',
                'filename' => $filename,
            ]);
        } catch (Throwable $e) {
            JsonLogger::log(
                'examenes_mail',
                'No se pudo generar PDF 012A',
                $e,
                [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'examen_id' => $examenId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );
            return null;
        }

        if (strncmp($pdf, '%PDF-', 5) !== 0) {
            JsonLogger::log(
                'examenes_mail',
                'Contenido 012A no es PDF',
                null,
                [
                    'preview' => substr($pdf, 0, 200),
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'examen_id' => $examenId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );
            return null;
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'mf_012a_');
        if ($tmpBase === false) {
            return null;
        }
        $tmpPath = $tmpBase;
        if (!str_ends_with($tmpBase, '.pdf')) {
            $candidate = $tmpBase . '.pdf';
            if (@rename($tmpBase, $candidate)) {
                $tmpPath = $candidate;
            }
        }
        if (@file_put_contents($tmpPath, $pdf) === false) {
            @unlink($tmpPath);
            return null;
        }

        return [
            'path' => $tmpPath,
            'name' => $filename,
            'type' => 'application/pdf',
            'size' => strlen($pdf),
        ];
    }

    /**
     * @return array{path: string, name?: string, type?: string, size?: int}|null
     */
    private function getCoberturaAttachment(): ?array
    {
        if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return null;
        }

        $file = $_FILES['attachment'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $name = trim((string)($file['name'] ?? ''));
        $type = trim((string)($file['type'] ?? ''));

        $attachment = ['path' => $tmpName];
        if ($name !== '') {
            $attachment['name'] = $name;
        }
        if ($type !== '') {
            $attachment['type'] = $type;
        }
        if (isset($file['size'])) {
            $attachment['size'] = (int) $file['size'];
        }

        return $attachment;
    }

    private function formatCoberturaMailBodyText(string $body, bool $isHtml): string
    {
        if (!$isHtml) {
            return $body;
        }

        $text = trim(strip_tags($body));
        if ($text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
