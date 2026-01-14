<?php

namespace Modules\Solicitudes\Controllers;

use Core\BaseController;
use Helpers\JsonLogger;
use DateInterval;
use DateTimeImmutable;
use Models\SolicitudModel;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Pacientes\Services\PacienteService;
use Modules\Solicitudes\Services\SolicitudCrmService;
use Modules\Solicitudes\Services\SolicitudReminderService;
use Modules\Solicitudes\Services\SolicitudEstadoService;
use Modules\Solicitudes\Services\CalendarBlockService;
use Modules\Solicitudes\Services\SolicitudReportExcelService;
use Modules\Solicitudes\Services\SolicitudSettingsService;
use Modules\Reporting\Services\ReportService;
use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class SolicitudController extends BaseController
{
    private const TERMINAL_STATES = [
        'atendido', 'atendida', 'cancelado', 'cancelada', 'cerrado', 'cerrada',
        'suspendido', 'suspendida', 'facturado', 'facturada', 'reprogramado', 'reprogramada',
        'pagado', 'pagada', 'no procede', 'no_procede', 'no-procede', 'cerrado sin atención',
        'facturada-cerrada', 'protocolo-completo', 'completado',
    ];

    private SolicitudModel $solicitudModel;
    private PacienteService $pacienteService;
    private SolicitudCrmService $crmService;
    private CalendarBlockService $calendarBlocks;
    private SolicitudEstadoService $estadoService;
    private LeadConfigurationService $leadConfig;
    private PusherConfigService $pusherConfig;
    private SolicitudSettingsService $settingsService;
    private ?SettingsModel $settings = null;
    private ?array $bodyCache = null;
    private ?array $turneroStateMap = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->crmService = new SolicitudCrmService($pdo);
        $this->estadoService = new SolicitudEstadoService($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->pusherConfig = new PusherConfigService($pdo);
        $this->calendarBlocks = new CalendarBlockService($pdo);
        $this->settingsService = new SolicitudSettingsService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/solicitudes.php',
            [
                'pageTitle' => 'Solicitudes Quirúrgicas',
                'kanbanColumns' => $this->estadoService->getColumns(),
                'kanbanStages' => $this->estadoService->getStages(),
                'realtime' => $this->pusherConfig->getPublicConfig(),
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
                'pageTitle' => 'Turnero Coordinación Quirúrgica',
                'turneroContext' => 'Coordinación Quirúrgica',
                'turneroEmptyMessage' => 'No hay pacientes en cola para coordinación quirúrgica.',
                'bodyClass' => 'turnero-body',
                'turneroRefreshMs' => $this->settingsService->getTurneroRefreshMs(),
            ],
            'layout-turnero.php'
        );
    }

    public function turneroUnificado(): void
    {
        $this->requireAuth();

        $options = $this->loadTurneroSettings();

        $this->render(
            __DIR__ . '/../views/turnero-unificado.php',
            [
                'pageTitle' => 'Turneros quirúrgicos y de exámenes',
                'bodyClass' => 'turnero-body',
                'turneroSettings' => $options,
            ],
            'layout-turnero.php'
        );
    }

    public function obtenerEstadosPorHc(string $hcNumber): array
    {
        $solicitudes = $this->solicitudModel->obtenerEstadosPorHc($hcNumber);

        return [
            'success' => true,
            'hcNumber' => $hcNumber,
            'total' => count($solicitudes),
            'solicitudes' => $solicitudes,
        ];
    }

    public function actualizarSolicitudParcial(int $id, array $campos): array
    {
        return $this->solicitudModel->actualizarSolicitudParcial($id, $campos);
    }

    private function settings(): SettingsModel
    {
        if (!($this->settings instanceof SettingsModel)) {
            $this->settings = new SettingsModel($this->pdo);
        }

        return $this->settings;
    }

    private function loadTurneroSettings(): array
    {
        $defaults = [
            'soundEnabled' => true,
            'volume' => 0.7,
            'bellStyle' => 'classic',
            'quiet' => [
                'enabled' => false,
                'start' => '22:00',
                'end' => '06:00',
            ],
            'ttsEnabled' => true,
            'ttsRepeat' => false,
            'speakOnNew' => true,
            'voice' => '',
            'fullscreenDefault' => false,
        ];

        try {
            $options = $this->settings()->getOptions([
                'turnero_sound_enabled',
                'turnero_bell_style',
                'turnero_sound_volume',
                'turnero_quiet_enabled',
                'turnero_quiet_start',
                'turnero_quiet_end',
                'turnero_tts_enabled',
                'turnero_tts_repeat',
                'turnero_speak_on_new',
                'turnero_voice_preference',
                'turnero_fullscreen_default',
            ]);
        } catch (Throwable) {
            return $defaults;
        }

        $bool = static fn($value, $fallback = false) => in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true) ? true : $fallback;
        $volume = (float)($options['turnero_sound_volume'] ?? $defaults['volume']);

        return [
            'soundEnabled' => $bool($options['turnero_sound_enabled'] ?? $defaults['soundEnabled'], $defaults['soundEnabled']),
            'volume' => max(0, min(1, $volume)),
            'quiet' => [
                'enabled' => $bool($options['turnero_quiet_enabled'] ?? $defaults['quiet']['enabled'], $defaults['quiet']['enabled']),
                'start' => $options['turnero_quiet_start'] ?? $defaults['quiet']['start'],
                'end' => $options['turnero_quiet_end'] ?? $defaults['quiet']['end'],
            ],
            'ttsEnabled' => $bool($options['turnero_tts_enabled'] ?? $defaults['ttsEnabled'], $defaults['ttsEnabled']),
            'ttsRepeat' => $bool($options['turnero_tts_repeat'] ?? $defaults['ttsRepeat'], $defaults['ttsRepeat']),
            'speakOnNew' => $bool($options['turnero_speak_on_new'] ?? $defaults['speakOnNew'], $defaults['speakOnNew']),
            'bellStyle' => in_array($options['turnero_bell_style'] ?? '', ['classic', 'soft', 'bright'], true)
                ? $options['turnero_bell_style']
                : $defaults['bellStyle'],
            'voice' => trim((string)($options['turnero_voice_preference'] ?? $defaults['voice'])),
            'fullscreenDefault' => $bool($options['turnero_fullscreen_default'] ?? $defaults['fullscreenDefault'], $defaults['fullscreenDefault']),
        ];
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

        if ($filtros['fechaTexto'] === '') {
            $hoy = new DateTimeImmutable('today');
            $inicio = $hoy->sub(new DateInterval('P30D'));
            $filtros['fechaTexto'] = sprintf(
                '%s - %s',
                $inicio->format('d-m-Y'),
                $hoy->format('d-m-Y')
            );
        }

        $kanbanPreferences = $this->leadConfig->getKanbanPreferences();
        $pipelineStages = $this->leadConfig->getPipelineStages();

        try {
            $solicitudes = $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filtros);
            $solicitudes = $this->estadoService->enrichSolicitudes($solicitudes, $this->currentPermissions());
            $solicitudes = array_map([$this, 'transformSolicitudRow'], $solicitudes);
            $solicitudes = $this->ordenarSolicitudes($solicitudes, $kanbanPreferences['sort'] ?? 'fecha_desc');
            $solicitudes = $this->limitarSolicitudesPorEstado($solicitudes, (int)($kanbanPreferences['column_limit'] ?? 0));
            $metrics = $this->buildOperationalMetrics($solicitudes);

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
                    'metrics' => $metrics,
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
                    'metrics' => [
                        'sla' => [],
                        'alerts' => [],
                        'prioridad' => [],
                        'teams' => [],
                    ],
                ],
                'error' => 'No se pudo cargar la información de solicitudes',
            ], 500);
        }
    }

    public function reportePdf(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['error' => 'Sesión expirada'], 401);
            return;
        }

        if (!$this->hasPermission(['reportes.export', 'reportes.view', 'solicitudes.view'])) {
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
            $solicitudes = $reportData['rows'];
            $filtersSummary = $reportData['filtersSummary'];
            $metricLabel = $reportData['metricLabel'];
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');
            $filename = 'solicitudes_' . date('Ymd_His') . '.pdf';

            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('solicitudes_kanban', [
                'titulo' => 'Reporte de solicitudes',
                'generatedAt' => $generatedAt,
                'filters' => $filtersSummary,
                'total' => count($solicitudes),
                'rows' => $solicitudes,
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
                    'solicitudes_reportes',
                    'Reporte PDF de solicitudes devolvió contenido no-PDF',
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
                'solicitudes_reportes',
                'Reporte PDF de solicitudes falló',
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

        if (!$this->hasPermission(['reportes.export', 'reportes.view', 'solicitudes.view'])) {
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
            $solicitudes = $reportData['rows'];
            $filtersSummary = $reportData['filtersSummary'];
            $metricLabel = $reportData['metricLabel'];
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');
            $filename = 'solicitudes_' . date('Ymd_His') . '.xlsx';

            $excelService = new SolicitudReportExcelService();
            $content = $excelService->render($solicitudes, $filtersSummary, [
                'title' => 'Reporte de solicitudes',
                'generated_at' => $generatedAt,
                'metric_label' => $metricLabel,
                'total' => count($solicitudes),
            ]);

            if ($content === '') {
                JsonLogger::log(
                    'solicitudes_reportes',
                    'Reporte Excel de solicitudes devolvió contenido vacío',
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
                    'solicitudes_reportes',
                    'Reporte Excel de solicitudes devolvió contenido no-ZIP',
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
                'solicitudes_reportes',
                'Reporte Excel de solicitudes falló',
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

    public function crmResumen(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        try {
            $resumen = $this->crmService->obtenerResumen($solicitudId);
            $this->json(['success' => true, 'data' => $resumen]);
        } catch (RuntimeException $e) {
            JsonLogger::log(
                'crm',
                'CRM ▶ Resumen no encontrado o incompleto',
                $e,
                [
                    'solicitud_id' => $solicitudId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $message = trim($e->getMessage()) !== '' ? $e->getMessage() : 'Solicitud no encontrada';
            $status = strcasecmp($message, 'Solicitud no encontrada') === 0 ? 404 : 422;
            $this->json(['success' => false, 'error' => $message], $status);
        } catch (\Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'crm',
                'CRM ▶ Error al obtener resumen',
                $e,
                [
                    'solicitud_id' => $solicitudId,
                    'user_id' => $this->getCurrentUserId(),
                    'error_id' => $errorId,
                ]
            );

            $message = 'No se pudo cargar el detalle CRM';
            if (trim((string)$e->getMessage()) !== '') {
                $message .= sprintf(' (ref: %s)', $errorId);
            }

            $this->json(['success' => false, 'error' => $message], 500);
        }
    }

    public function crmBootstrap(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $resultado = $this->crmService->bootstrapChecklist(
                $solicitudId,
                $payload,
                $this->getCurrentUserId(),
                $this->currentPermissions()
            );
            $this->json(['success' => true] + $resultado);
        } catch (RuntimeException $e) {
            error_log('CRM ▶ Bootstrap checklist error: ' . ($e->getMessage() ?: get_class($e)));
            $status = (int)($e->getCode() ?: 422);
            if ($status < 400 || $status >= 500) {
                $status = 422;
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            error_log('CRM ▶ Bootstrap checklist exception: ' . ($e->getMessage() ?: get_class($e)));
            $this->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500);
        }
    }

    public function crmChecklistState(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        try {
            $resultado = $this->crmService->checklistState(
                $solicitudId,
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
            error_log('CRM ▶ Checklist state exception: ' . ($e->getMessage() ?: get_class($e)));
            $this->json(['success' => false, 'error' => 'No se pudo cargar el checklist'], 500);
        }
    }

    public function crmActualizarChecklist(int $solicitudId): void
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
                $solicitudId,
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
            ]);
        } catch (RuntimeException $e) {
            error_log('CRM ▶ Checklist sync error: ' . ($e->getMessage() ?: get_class($e)));
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('CRM ▶ Checklist sync exception: ' . ($e->getMessage() ?: get_class($e)));
            $this->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500);
        }
    }

    public function crmRegistrarBloqueo(int $solicitudId): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();

        try {
            $resultado = $this->crmService->registrarBloqueoAgenda(
                $solicitudId,
                $payload,
                $this->getCurrentUserId()
            );

            $this->json(['success' => true, 'data' => $resultado]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage() ?: 'No se pudo registrar el bloqueo de agenda',
            ], 500);
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
            $status = (int)($e->getCode() ?: 0);
            if ($status >= 400 && $status < 500) {
                $this->json([
                    'success' => false,
                    'error' => $e->getMessage() ?: 'No se pudieron guardar los cambios del CRM',
                ], $status);
                return;
            }

            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'crm',
                'No se pudieron guardar los cambios del CRM',
                $e,
                [
                    'solicitud_id' => $solicitudId,
                    'usuario_id' => $this->getCurrentUserId(),
                    'payload' => $payload,
                    'trace' => $e->getTraceAsString(),
                    'error_id' => $errorId,
                ]
            );

            $this->json([
                'success' => false,
                'error' => sprintf(
                    'No se pudieron guardar los cambios del CRM (ref: %s)',
                    $errorId
                ),
            ], 500);
        }
    }

    public function crmAgregarNota(int $solicitudId): void
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
        $tareaId = isset($payload['tarea_id']) ? (int)$payload['tarea_id'] : 0;
        $estado = isset($payload['estado']) ? (string)$payload['estado'] : '';

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
        if ((int)($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
            $this->json(['success' => false, 'error' => 'El archivo es inválido'], 422);
            return;
        }

        $descripcion = isset($_POST['descripcion']) ? trim((string)$_POST['descripcion']) : null;
        $nombreOriginal = (string)($archivo['name'] ?? 'adjunto');
        $mimeType = isset($archivo['type']) ? (string)$archivo['type'] : null;
        $tamano = isset($archivo['size']) ? (int)$archivo['size'] : null;

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

    public function apiEstadoGet(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $hcNumber = $_GET['hcNumber'] ?? $_GET['hc_number'] ?? null;

        if (!$hcNumber) {
            $this->json(
                ['success' => false, 'message' => 'Parámetro hcNumber requerido'],
                400
            );
            return;
        }

        try {
            $response = $this->obtenerEstadosPorHc((string)$hcNumber);
            $this->json($response);
        } catch (\Throwable $e) {
            error_log('apiEstadoGet error: ' . $e->getMessage());
            $this->json(
                ['success' => false, 'message' => 'Error al obtener la solicitud', 'error' => $e->getMessage()],
                500
            );
        }
    }

    public function apiEstadoPost(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->getRequestBody();
        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        if (!$id && isset($payload['solicitud_id'])) {
            $id = (int)$payload['solicitud_id'];
        }

        if (!$id) {
            $this->json(
                ['success' => false, 'message' => 'Parámetro id requerido para actualizar la solicitud'],
                400
            );
            return;
        }

        error_log('apiEstadoPost payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $campos = [
            'estado' => $payload['estado'] ?? null,
            'doctor' => $payload['doctor'] ?? null,
            'fecha' => $payload['fecha'] ?? null,
            'prioridad' => $payload['prioridad'] ?? null,
            'observacion' => $payload['observacion'] ?? null,
            'procedimiento' => $payload['procedimiento'] ?? null,
            'producto' => $payload['producto'] ?? null,
            'ojo' => $payload['ojo'] ?? null,
            'afiliacion' => $payload['afiliacion'] ?? null,
            'duracion' => $payload['duracion'] ?? null,
            'lente_id' => $payload['lente_id'] ?? null,
            'lente_nombre' => $payload['lente_nombre'] ?? null,
            'lente_poder' => $payload['lente_poder'] ?? null,
            'lente_observacion' => $payload['lente_observacion'] ?? null,
            'incision' => $payload['incision'] ?? null,
        ];

        try {
            $resultado = $this->actualizarSolicitudParcial($id, $campos);
            error_log('apiEstadoPost resultado: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE));
            $status = (!is_array($resultado) || ($resultado['success'] ?? false) === false) ? 422 : 200;
            $this->json(is_array($resultado) ? $resultado : ['success' => false], $status);
        } catch (\Throwable $e) {
            error_log('apiEstadoPost error: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al actualizar la solicitud', 'error' => $e->getMessage()], 500);
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
     * @param array<int, array<string, mixed>> $solicitudes
     * @return array<int, array<string, mixed>>
     */
    private function applySearchFilter(array $solicitudes, string $search): array
    {
        $term = mb_strtolower(trim($search));
        if ($term === '') {
            return $solicitudes;
        }

        $keys = [
            'full_name',
            'hc_number',
            'procedimiento',
            'doctor',
            'afiliacion',
            'estado',
            'crm_pipeline_stage',
        ];

        return array_values(array_filter($solicitudes, static function (array $row) use ($term, $keys) {
            foreach ($keys as $key) {
                $value = $row[$key] ?? '';
                if ($value !== '' && str_contains(mb_strtolower((string)$value), $term)) {
                    return true;
                }
            }
            return false;
        }));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getQuickMetricConfig(string $quickMetric): array
    {
        $map = $this->settingsService->getQuickMetrics();

        return $map[$quickMetric] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $solicitudes
     * @param array<string, string> $metricConfig
     * @return array<int, array<string, mixed>>
     */
    private function applyQuickMetricFilter(array $solicitudes, array $metricConfig): array
    {
        if (isset($metricConfig['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug($metricConfig['estado']);
            return array_values(array_filter(
                $solicitudes,
                static fn(array $row) => ($row['estado'] ?? '') === $estadoSlug
            ));
        }

        if (isset($metricConfig['sla_status'])) {
            return array_values(array_filter(
                $solicitudes,
                static fn(array $row) => ($row['sla_status'] ?? '') === $metricConfig['sla_status']
            ));
        }

        return $solicitudes;
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

        $solicitudes = $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filters);
        $solicitudes = $this->estadoService->enrichSolicitudes($solicitudes, $this->currentPermissions());
        $solicitudes = array_map([$this, 'transformSolicitudRow'], $solicitudes);
        $solicitudes = $this->applySearchFilter($solicitudes, $filters['search'] ?? '');

        if (!empty($filters['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug($filters['estado']);
            $solicitudes = array_values(array_filter(
                $solicitudes,
                static fn(array $row) => ($row['estado'] ?? '') === $estadoSlug
            ));
        }

        $metricConfig = $this->getQuickMetricConfig($quickMetric);
        $metricLabel = $metricConfig['label'] ?? null;
        if (!empty($metricConfig)) {
            $solicitudes = $this->applyQuickMetricFilter($solicitudes, $metricConfig);
        }

        $filtersSummary = $this->buildReportFiltersSummary($filters, $metricLabel);

        return [
            'filters' => $filters,
            'rows' => $solicitudes,
            'filtersSummary' => $filtersSummary,
            'metricLabel' => $metricLabel,
        ];
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    private function transformSolicitudRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);
        if (empty($row['fecha_programada']) && !empty($row['derivacion_fecha_vigencia'])) {
            $row['fecha_programada'] = $row['derivacion_fecha_vigencia'];
        }

        return array_merge($row, $this->computeOperationalMetadata($row));
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function computeOperationalMetadata(array $row): array
    {
        $now = new DateTimeImmutable('now');
        $estado = strtolower(trim((string)($row['estado'] ?? '')));
        $isTerminal = $estado !== '' && in_array($estado, self::TERMINAL_STATES, true);
        $slaWarningHours = $this->settingsService->getSlaWarningHours();
        $slaCriticalHours = $this->settingsService->getSlaCriticalHours();

        $fechaProgramada = $this->parseDate($row['fecha_programada'] ?? ($row['fecha'] ?? null));
        $createdAt = $this->parseDate($row['created_at'] ?? null);

        $deadline = $fechaProgramada ?? $createdAt;
        $hoursRemaining = null;
        $slaStatus = 'sin_fecha';

        if ($deadline instanceof DateTimeImmutable) {
            $hoursRemaining = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;

            if ($isTerminal) {
                $slaStatus = 'cerrado';
            } elseif ($hoursRemaining < 0) {
                $slaStatus = 'vencido';
            } elseif ($hoursRemaining <= $slaCriticalHours) {
                $slaStatus = 'critico';
            } elseif ($hoursRemaining <= $slaWarningHours) {
                $slaStatus = 'advertencia';
            } else {
                $slaStatus = 'en_rango';
            }
        } elseif ($createdAt instanceof DateTimeImmutable) {
            $elapsed = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
            if ($elapsed >= $slaWarningHours) {
                $slaStatus = 'advertencia';
            }
        }

        $autoPriority = 'normal';
        if (in_array($slaStatus, ['vencido', 'critico'], true)) {
            $autoPriority = 'urgente';
        } elseif ($slaStatus === 'advertencia') {
            $autoPriority = 'pendiente';
        }

        $autoPriorityLabel = match ($autoPriority) {
            'urgente' => 'Urgente',
            'pendiente' => 'Pendiente',
            default => 'Normal',
        };

        $prioridadManual = trim((string)($row['prioridad'] ?? ''));
        $prioridadMostrada = $prioridadManual !== '' ? $prioridadManual : $autoPriorityLabel;

        $fechaCaducidad = $this->parseDate($row['fecha_caducidad'] ?? null);
        $alertReprogramacion = !$isTerminal
            && $fechaProgramada instanceof DateTimeImmutable
            && $fechaProgramada < $now->sub(new DateInterval('PT2H'));

        $alertConsentimiento = !$isTerminal
            && ($fechaCaducidad === null || $fechaCaducidad <= $now);

        $adjuntos = (int)($row['crm_total_adjuntos'] ?? 0);
        $tareasPendientes = (int)($row['crm_tareas_pendientes'] ?? 0);
        $alertDocumentos = !$isTerminal && $adjuntos === 0;
        $alertAutorizacion = !$isTerminal
            && !empty($row['afiliacion'])
            && ($tareasPendientes > 0 || $alertDocumentos);

        $alerts = [];
        if ($alertReprogramacion) {
            $alerts[] = 'Requiere reprogramación';
        }
        if ($alertConsentimiento) {
            $alerts[] = 'Pendiente de consentimiento';
        }
        if ($alertDocumentos) {
            $alerts[] = 'Faltan documentos de soporte';
        }
        if ($alertAutorizacion) {
            $alerts[] = 'Autorización pendiente';
        }

        return [
            'prioridad' => $prioridadMostrada,
            'prioridad_origen' => $prioridadManual !== '' ? 'manual' : 'automatico',
            'prioridad_automatica' => $autoPriority,
            'prioridad_automatica_label' => $autoPriorityLabel,
            'sla_status' => $slaStatus,
            'sla_deadline' => $deadline instanceof DateTimeImmutable ? $deadline->format(DateTimeImmutable::ATOM) : null,
            'sla_hours_remaining' => $hoursRemaining !== null ? round($hoursRemaining, 2) : null,
            'fecha_programada_iso' => $fechaProgramada instanceof DateTimeImmutable ? $fechaProgramada->format(DateTimeImmutable::ATOM) : null,
            'created_at_iso' => $createdAt instanceof DateTimeImmutable ? $createdAt->format(DateTimeImmutable::ATOM) : null,
            'alert_reprogramacion' => $alertReprogramacion,
            'alert_pendiente_consentimiento' => $alertConsentimiento,
            'alert_documentos_faltantes' => $alertDocumentos,
            'alert_autorizacion_pendiente' => $alertAutorizacion,
            'alertas_operativas' => $alerts,
        ];
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            return new DateTimeImmutable((string)$value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $solicitudes
     * @return array<string, mixed>
     */
    private function buildOperationalMetrics(array $solicitudes): array
    {
        $metrics = [
            'sla' => [
                'en_rango' => 0,
                'advertencia' => 0,
                'critico' => 0,
                'vencido' => 0,
                'sin_fecha' => 0,
                'cerrado' => 0,
            ],
            'alerts' => [
                'requiere_reprogramacion' => 0,
                'pendiente_consentimiento' => 0,
                'documentos_faltantes' => 0,
                'autorizacion_pendiente' => 0,
            ],
            'prioridad' => [
                'urgente' => 0,
                'pendiente' => 0,
                'normal' => 0,
            ],
            'teams' => [],
        ];

        foreach ($solicitudes as $row) {
            $sla = (string)($row['sla_status'] ?? 'sin_fecha');
            if (!array_key_exists($sla, $metrics['sla'])) {
                $metrics['sla'][$sla] = 0;
            }
            $metrics['sla'][$sla] += 1;

            $autoPriority = (string)($row['prioridad_automatica'] ?? '');
            if ($autoPriority !== '') {
                if (!array_key_exists($autoPriority, $metrics['prioridad'])) {
                    $metrics['prioridad'][$autoPriority] = 0;
                }
                $metrics['prioridad'][$autoPriority] += 1;
            }

            if (!empty($row['alert_reprogramacion'])) {
                $metrics['alerts']['requiere_reprogramacion'] += 1;
            }
            if (!empty($row['alert_pendiente_consentimiento'])) {
                $metrics['alerts']['pendiente_consentimiento'] += 1;
            }
            if (!empty($row['alert_documentos_faltantes'])) {
                $metrics['alerts']['documentos_faltantes'] += 1;
            }
            if (!empty($row['alert_autorizacion_pendiente'])) {
                $metrics['alerts']['autorizacion_pendiente'] += 1;
            }

            $teamKey = (string)($row['crm_responsable_id'] ?? 'sin_asignar');
            if (!isset($metrics['teams'][$teamKey])) {
                $metrics['teams'][$teamKey] = [
                    'responsable_id' => $row['crm_responsable_id'] ?? null,
                    'responsable_nombre' => $row['crm_responsable_nombre'] ?? 'Sin responsable',
                    'total' => 0,
                    'vencido' => 0,
                    'critico' => 0,
                    'advertencia' => 0,
                    'reprogramar' => 0,
                    'sin_consentimiento' => 0,
                    'documentos' => 0,
                    'autorizaciones' => 0,
                ];
            }

            $metrics['teams'][$teamKey]['total'] += 1;

            if ($sla === 'vencido') {
                $metrics['teams'][$teamKey]['vencido'] += 1;
            }
            if ($sla === 'critico') {
                $metrics['teams'][$teamKey]['critico'] += 1;
            }
            if ($sla === 'advertencia') {
                $metrics['teams'][$teamKey]['advertencia'] += 1;
            }
            if (!empty($row['alert_reprogramacion'])) {
                $metrics['teams'][$teamKey]['reprogramar'] += 1;
            }
            if (!empty($row['alert_pendiente_consentimiento'])) {
                $metrics['teams'][$teamKey]['sin_consentimiento'] += 1;
            }
            if (!empty($row['alert_documentos_faltantes'])) {
                $metrics['teams'][$teamKey]['documentos'] += 1;
            }
            if (!empty($row['alert_autorizacion_pendiente'])) {
                $metrics['teams'][$teamKey]['autorizaciones'] += 1;
            }
        }

        uasort($metrics['teams'], static function (array $a, array $b): int {
            $scoreA = ($a['vencido'] * 3) + ($a['critico'] * 2) + $a['advertencia'];
            $scoreB = ($b['vencido'] * 3) + ($b['critico'] * 2) + $b['advertencia'];

            if ($scoreA === $scoreB) {
                return strcmp((string)$a['responsable_nombre'], (string)$b['responsable_nombre']);
            }

            return $scoreB <=> $scoreA;
        });

        return $metrics;
    }

    public function actualizarEstado(): void
    {
        $logDir = realpath(__DIR__ . '/../../..') . '/storage/logs';
        $logFile = $logDir . '/solicitudes_estado.log';
        $log = static function (string $message) use ($logDir, $logFile): void {
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $prefix = '[' . date('c') . '] ';
            @file_put_contents($logFile, $prefix . $message . PHP_EOL, FILE_APPEND);
        };

        if (!$this->isAuthenticated()) {
            $log('auth_failed');
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $formId = isset($payload['form_id']) ? (int)$payload['form_id'] : 0;
        $estado = trim($payload['estado'] ?? '');
        $nota = isset($payload['nota']) ? trim((string)$payload['nota']) : null;
        $completado = isset($payload['completado']) ? (bool)$payload['completado'] : true;
        $force = isset($payload['force']) ? (bool)$payload['force'] : false;

        if ($id <= 0 && $formId > 0) {
            $id = (int)$this->solicitudModel->findIdByFormId($formId);
        }

        if ($id <= 0 || $estado === '') {
            $log('invalid_payload id=' . json_encode($id) . ' estado=' . json_encode($estado) . ' raw=' . json_encode($payload));
            $this->json(['success' => false, 'error' => 'Datos incompletos'], 422);
            return;
        }

        try {
            $esApto = in_array($estado, ['apto-oftalmologo', 'apto-anestesia'], true);

            // Botón de confirmación debe marcar (nunca desmarcar) la etapa apto
            if ($esApto && !$completado) {
                $completado = true;
                $force = true;
                $payload['completado'] = true;
                $payload['force'] = true;
            }

            if ($esApto) {
                $log(sprintf(
                    'actualizarEstado solicitud_id=%d etapa=%s completado=%s force=%s user=%s payload=%s',
                    $id,
                    $estado,
                    $completado ? '1' : '0',
                    $force ? '1' : '0',
                    $this->getCurrentUserId() ?? 'anon',
                    json_encode($payload)
                ));
            }

            $resultado = $this->estadoService->actualizarEtapa(
                $id,
                $estado,
                $completado,
                $this->getCurrentUserId(),
                $this->currentPermissions(),
                $force,
                $nota
            );

            $this->pusherConfig->trigger(
                $resultado + [
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                null,
                PusherConfigService::EVENT_STATUS_UPDATED
            );

            $log(sprintf(
                'estado_actualizado_ok solicitud_id=%d estado=%s kanban=%s checklist=%s',
                $id,
                $estado,
                $resultado['kanban_estado'] ?? $estado,
                json_encode($resultado['checklist_progress'] ?? [])
            ));

            $this->json([
                'success' => true,
                'estado' => $resultado['kanban_estado'] ?? $estado,
                'estado_label' => $resultado['kanban_estado_label'] ?? $resultado['kanban_estado'] ?? $estado,
                'turno' => $resultado['turno'] ?? null,
                'checklist' => $resultado['checklist'] ?? [],
                'checklist_progress' => $resultado['checklist_progress'] ?? [],
                'estado_anterior' => $resultado['estado_anterior'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            $log(sprintf(
                'estado_actualizado_runtime_error solicitud_id=%d estado=%s error=%s',
                $id,
                $estado,
                $e->getMessage()
            ));
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $log(sprintf(
                'estado_actualizado_error solicitud_id=%d estado=%s error=%s',
                $id,
                $estado,
                $e->getMessage()
            ));

            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'solicitudes_estado',
                'Error al actualizar estado de solicitud',
                $e,
                [
                    'error_id' => $errorId,
                    'solicitud_id' => $id,
                    'estado' => $estado,
                    'usuario' => $this->getCurrentUserId(),
                ]
            );

            $this->json([
                'success' => false,
                'error' => 'Error interno (ref: ' . $errorId . ')',
            ], 500);
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
        $horasPasadas = isset($payload['horas_pasadas']) ? (int)$payload['horas_pasadas'] : 48;

        $scheduler = new SolicitudReminderService($this->pdo, $this->pusherConfig);
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
            $requested = array_values(array_filter(array_map('trim', explode(',', (string)$_GET['estado']))));
            foreach ($requested as $estado) {
                $normalizado = $this->normalizarEstadoTurnero($estado);
                if ($normalizado !== null) {
                    $estados[] = $normalizado;
                }
            }
            $estados = array_values(array_unique($estados));
        }

        try {
            $solicitudes = $this->solicitudModel->fetchTurneroSolicitudes($estados);

            foreach ($solicitudes as &$solicitud) {
                $nombreCompleto = trim((string)($solicitud['full_name'] ?? ''));
                $solicitud['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
                $solicitud['turno'] = isset($solicitud['turno']) ? (int)$solicitud['turno'] : null;
                $estadoNormalizado = $this->normalizarEstadoTurnero((string)($solicitud['estado'] ?? ''));
                $solicitud['estado'] = $estadoNormalizado ?? ($solicitud['estado'] ?? null);

                $solicitud['hora'] = null;
                $solicitud['fecha'] = null;

                if (!empty($solicitud['created_at'])) {
                    $timestamp = strtotime((string)$solicitud['created_at']);
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
                'turnero_solicitudes',
                'Error cargando turnero de solicitudes',
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

        if (!$this->hasPermission(['solicitudes.turnero', 'solicitudes.update', 'solicitudes.view'])) {
            JsonLogger::log(
                'turnero_solicitudes',
                'Intento sin permisos en turnero de solicitudes',
                null,
                [
                    'user_id' => $this->getCurrentUserId(),
                    'payload' => [
                        'id' => isset($payload['id']) ? (int)$payload['id'] : null,
                        'turno' => isset($payload['turno']) ? (int)$payload['turno'] : null,
                    ],
                    'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
                ]
            );
            $this->json(['success' => false, 'error' => 'No autorizado'], 403);
            return;
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        $turno = isset($payload['turno']) ? (int)$payload['turno'] : null;
        $estadoSolicitado = isset($payload['estado'])
            ? trim((string)$payload['estado'])
            : $this->settingsService->getTurneroDefaultState();
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
                    null,
                    PusherConfigService::EVENT_TURNERO_UPDATED
                );
            } catch (Throwable $notificationError) {
                JsonLogger::log(
                    'turnero_solicitudes',
                    'No se pudo notificar la actualización del turnero de solicitudes',
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
                'turnero_solicitudes',
                'Error al llamar turno del turnero de solicitudes',
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
        $estadoLimpio = trim($estado);
        if ($estadoLimpio === '') {
            return null;
        }

        $clave = $this->normalizarTurneroClave($estadoLimpio);
        $mapa = $this->getTurneroStateMap();

        return $mapa[$clave] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function getTurneroStateMap(): array
    {
        if ($this->turneroStateMap !== null) {
            return $this->turneroStateMap;
        }

        $map = [];
        $allowedStates = $this->settingsService->getTurneroAllowedStates();
        foreach ($allowedStates as $state) {
            $label = trim((string)$state);
            if ($label === '') {
                continue;
            }
            $key = $this->normalizarTurneroClave($label);
            if ($key === '') {
                continue;
            }
            if (!isset($map[$key])) {
                $map[$key] = $label;
            }
        }

        $this->turneroStateMap = $map;

        return $this->turneroStateMap;
    }

    private function normalizarTurneroClave(string $estado): string
    {
        $value = trim($estado);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}/u', '', $normalized) ?? $value;
            }
        }

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ]);

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function isQuickMetricAllowed(string $quickMetric): bool
    {
        $metrics = $this->settingsService->getQuickMetrics();
        return array_key_exists($quickMetric, $metrics);
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
        $slaLabels = $this->settingsService->getSlaLabels();
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
        // 1) Cargar primero la data principal del modal (NO depende de derivación)
        $solicitud = $this->solicitudModel->obtenerDatosYCirujanoSolicitud($form_id, $hc);
        $paciente = $this->pacienteService->getPatientDetails($hc);
        $diagnostico = $this->solicitudModel->obtenerDxDeSolicitud($form_id);
        $consulta = $this->solicitudModel->obtenerConsultaDeSolicitud($form_id);

        // 2) Derivación es opcional: solo intentamos cuando aplica por afiliación
        $afiliacion = '';
        if (is_array($solicitud)) {
            $afiliacion = (string)($solicitud['afiliacion'] ?? $solicitud['afiliacion_nombre'] ?? $solicitud['aseguradora'] ?? '');
        }
        $afiliacion = strtoupper(trim($afiliacion));

        $derivacion = null;
        if ($this->shouldFetchDerivacion($afiliacion)) {
            $derivacion = $this->ensureDerivacion((string)$form_id, (string)$hc);
        }

        return [
            'derivacion' => $derivacion,
            'solicitud' => $solicitud,
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
        ];
    }

    /**
     * Verifica derivación; si no existe, intenta scrapear y reconsultar.
     */
    private function ensureDerivacion(string $formId, string $hcNumber, bool $forceScrape = false): ?array
    {
        $derivacion = $this->solicitudModel->obtenerDerivacionPorFormId($formId);
        if ($derivacion && !$forceScrape) {
            return $derivacion;
        }

        $script = BASE_PATH . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return null;
        }

        $cmd = sprintf(
            'python3 %s %s %s',
            escapeshellarg($script),
            escapeshellarg($formId),
            escapeshellarg($hcNumber)
        );

        // Ejecutar scraping para poblar derivaciones_form_id cuando falte.
        try {
            @exec($cmd, $out, $code);

            if ($code !== 0) {
                JsonLogger::log(
                    'derivaciones',
                    'Scrape derivación devolvió código no-cero',
                    null,
                    [
                        'form_id' => $formId,
                        'hc_number' => $hcNumber,
                        'exit_code' => $code,
                        'output_preview' => is_array($out) ? implode("\n", array_slice($out, 0, 10)) : null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // silenciar para no romper flujo de prefactura
        }

        return $this->solicitudModel->obtenerDerivacionPorFormId($formId);
    }

    private function shouldFetchDerivacion(string $afiliacion): bool
    {
        $raw = trim($afiliacion);
        if ($raw === '') {
            return false;
        }

        // Normalizamos (minúsculas, sin tildes, sin símbolos) para comparar de forma robusta.
        // Reutilizamos la misma normalización que usa el turnero para evitar duplicar lógica.
        $key = $this->normalizarTurneroClave($raw);
        if ($key === '') {
            return false;
        }

        // Afiliaciones que realmente pertenecen a IESS (en tu data NO siempre viene la palabra "IESS").
        $iess = [
            'contribuyente voluntario',
            'conyuge',
            'conyuge pensionista',
            'seguro campesino',
            'seguro campesino jubilado',
            'seguro general',
            'seguro general jubilado',
            'seguro general por montepio',
            'seguro general tiempo parcial',
        ];

        // Normalizar lista IESS una sola vez por llamada (lista corta, costo despreciable).
        $iessKeys = array_map(fn($v) => $this->normalizarTurneroClave($v), $iess);

        if (in_array($key, $iessKeys, true)) {
            return true;
        }

        // Otros convenios donde existe derivación.
        // Nota: aquí comparamos también en clave normalizada.
        return in_array($key, ['msp', 'issfa', 'isspol'], true);
    }

    public function rescrapeDerivacion(): void
    {
        $this->requireAuth();

        $payload = $this->getRequestBody();
        $hcNumber = (string)($payload['hc_number'] ?? $payload['hcNumber'] ?? $_POST['hc_number'] ?? $_POST['hcNumber'] ?? '');
        $formId = (string)($payload['form_id'] ?? $payload['formId'] ?? $_POST['form_id'] ?? $_POST['formId'] ?? '');

        if ($hcNumber === '' || $formId === '') {
            $this->json(['success' => false, 'error' => 'Faltan parámetros (hc_number, form_id)'], 400);
            return;
        }

        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, true);

            $this->json([
                'success' => true,
                'derivacion' => $derivacion,
                'message' => $derivacion ? 'Derivación actualizada.' : 'No se encontró derivación para este caso.',
            ]);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'derivaciones',
                'Error al re-scrapear derivación',
                $e,
                [
                    'error_id' => $errorId,
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $this->json([
                'success' => false,
                'error' => 'No se pudo re-scrapear derivación (ref: ' . $errorId . ')',
            ], 500);
        }
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

        $timestamp = strtotime((string)$valor);

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
