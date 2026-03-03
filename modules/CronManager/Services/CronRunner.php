<?php

declare(strict_types=1);

namespace Modules\CronManager\Services;

use Core\Permissions;
use Controllers\DerivacionController;
use DateInterval;
use DateTimeImmutable;
use DatePeriod;
use Models\BillingMainModel;
use Modules\CronManager\Repositories\CronTaskRepository;
use Modules\CiveExtension\Services\HealthCheckService;
use Modules\Derivaciones\Services\DerivacionesSyncService;
use Modules\IdentityVerification\Models\VerificationModel;
use Modules\IdentityVerification\Services\MissingEvidenceEscalationService;
use Modules\IdentityVerification\Services\VerificationPolicyService;
use Modules\KPI\Services\KpiCalculationService;
use Modules\Mail\Services\NotificationMailer;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Reporting\Services\AsyncReportQueueService;
use Modules\Solicitudes\Services\SolicitudCrmService;
use Modules\Solicitudes\Services\ExamenesReminderService;
use Modules\WhatsApp\Services\HandoffService;
use PDO;
use RuntimeException;
use Throwable;

class CronRunner
{
    private CronTaskRepository $repository;
    private bool $solicitudesLoaded = false;
    private ?HealthCheckService $civeHealthService = null;

    public function __construct(private PDO $pdo)
    {
        $this->repository = new CronTaskRepository($pdo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runAll(bool $force = false): array
    {
        $results = [];

        foreach ($this->definitions() as $definition) {
            $results[] = $this->runDefinition($definition, $force);
        }

        return $results;
    }

    public function runBySlug(string $slug, bool $force = false): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['slug'] === $slug) {
                return $this->runDefinition($definition, $force);
            }
        }

        return null;
    }

    /**
     * @param array{slug:string,name:string,description:string,interval:int,callback:callable} $definition
     * @return array{slug:string,name:string,status:string,message:string,details:?array,ran:bool}
     */
    private function runDefinition(array $definition, bool $force): array
    {
        $task = $this->repository->ensureTask($definition);
        $now = new DateTimeImmutable('now');

        if (isset($task['is_active']) && (int) $task['is_active'] === 0) {
            return [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'status' => 'skipped',
                'message' => 'La tarea está desactivada.',
                'details' => null,
                'ran' => false,
            ];
        }

        $nextRunAt = null;
        if (!empty($task['next_run_at'])) {
            try {
                $nextRunAt = new DateTimeImmutable((string) $task['next_run_at']);
            } catch (Throwable) {
                $nextRunAt = null;
            }
        }

        if (!$force && $nextRunAt instanceof DateTimeImmutable && $now < $nextRunAt) {
            return [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'status' => 'skipped',
                'message' => sprintf('Próxima ejecución programada para %s', $nextRunAt->format('Y-m-d H:i')), 
                'details' => null,
                'ran' => false,
            ];
        }

        $lockKey = $this->buildLockKey($definition['slug']);
        if (!$this->acquireLock($lockKey)) {
            return [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'status' => 'skipped',
                'message' => 'La tarea ya se está ejecutando en otro proceso.',
                'details' => null,
                'ran' => false,
            ];
        }

        $logId = $this->repository->startLog((int) $task['id'], $now);
        $startedAt = microtime(true);

        try {
            $result = call_user_func($definition['callback']);
            $status = is_array($result) && isset($result['status']) ? (string) $result['status'] : 'success';
            $message = is_array($result) && isset($result['message']) ? (string) $result['message'] : 'Tarea completada correctamente.';
            $details = is_array($result) && isset($result['details']) && is_array($result['details']) ? $result['details'] : null;

            $finishedAt = new DateTimeImmutable('now');
            $durationMs = $this->calculateDuration($startedAt);

            if ($status === 'skipped') {
                $this->repository->finishLog($logId, 'skipped', $finishedAt, $message, $details, null, $durationMs);
                $this->repository->markSkipped((int) $task['id'], $finishedAt, (int) $definition['interval'], $message, $details, $durationMs);
            } else {
                $this->repository->finishLog($logId, 'success', $finishedAt, $message, $details, null, $durationMs);
                $this->repository->markSuccess((int) $task['id'], $finishedAt, (int) $definition['interval'], $message, $details, $durationMs);
            }

            return [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'status' => $status,
                'message' => $message,
                'details' => $details,
                'ran' => true,
            ];
        } catch (Throwable $exception) {
            $finishedAt = new DateTimeImmutable('now');
            $durationMs = $this->calculateDuration($startedAt);
            $message = $exception->getMessage();
            $details = [
                'exception' => get_class($exception),
            ];

            $this->repository->finishLog($logId, 'failed', $finishedAt, $message, $details, $exception->getTraceAsString(), $durationMs);
            $this->repository->markFailure((int) $task['id'], $finishedAt, (int) $definition['interval'], $message, $details, $durationMs);

            return [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'status' => 'failed',
                'message' => $message,
                'details' => $details,
                'ran' => true,
            ];
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string,interval:int,callback:callable}>
     */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'cive-index-admisiones-sync',
                'name' => 'Scraping index-admisiones',
                'description' => 'Sincroniza pacientes y procedimientos desde el index-admisiones de CIVE.',
                'interval' => 86400,
                'callback' => function (): array {
                    return $this->runIndexAdmisionesSyncTask();
                },
            ],
            [
                'slug' => 'solicitudes-overdue',
                'name' => 'Actualizar solicitudes atrasadas',
                'description' => 'Marca como atrasadas las solicitudes quirúrgicas cuyo agendamiento ya venció.',
                'interval' => 300,
                'callback' => function (): array {
                    return $this->runOverdueSolicitudesTask();
                },
            ],
            [
                'slug' => 'solicitudes-reminders',
                'name' => 'Recordatorios de cirugías',
                'description' => 'Envía notificaciones automáticas para cirugías próximas.',
                'interval' => 600,
                'callback' => function (): array {
                    return $this->runRemindersTask();
                },
            ],
            [
                'slug' => 'crm-task-reminders',
                'name' => 'Recordatorios de tareas CRM',
                'description' => 'Dispara avisos internos y por correo cuando vence remind_at de una tarea CRM.',
                'interval' => 60,
                'callback' => function (): array {
                    return $this->runCrmTaskRemindersTask();
                },
            ],
            [
                'slug' => 'crm-task-supervisor-escalations',
                'name' => 'Escalamientos de tareas CRM',
                'description' => 'Notifica a supervisores cuando una tarea CRM sigue sin trámite tras su vencimiento.',
                'interval' => 300,
                'callback' => function (): array {
                    return $this->runCrmTaskSupervisorEscalationsTask();
                },
            ],
            [
                'slug' => 'whatsapp-handoff-requeue',
                'name' => 'Reencolar handoffs de WhatsApp',
                'description' => 'Reencola conversaciones cuyo tiempo de asignación expiró.',
                'interval' => 300,
                'callback' => function (): array {
                    return $this->runWhatsappHandoffRequeueTask();
                },
            ],
            [
                'slug' => 'billing-autocreation',
                'name' => 'Prefacturación automática',
                'description' => 'Crea registros en billing_main para solicitudes listas para facturación.',
                'interval' => 900,
                'callback' => function (): array {
                    return $this->runBillingTask();
                },
            ],
            [
                'slug' => 'stats-refresh',
                'name' => 'Actualización de estadísticas diarias',
                'description' => 'Recalcula métricas operativas para paneles y reportes.',
                'interval' => 3600,
                'callback' => function (): array {
                    return $this->runStatisticsTask();
                },
            ],
            [
                'slug' => 'kpi-refresh',
                'name' => 'Snapshots de KPIs',
                'description' => 'Recalcula los indicadores agregados para dashboards y reportes.',
                'interval' => 3600,
                'callback' => function (): array {
                    $today = new DateTimeImmutable('today');
                    $yesterday = $today->sub(new DateInterval('P1D'));

                    $period = new DatePeriod($yesterday, new DateInterval('P1D'), $today->add(new DateInterval('P1D')));
                    $service = new KpiCalculationService($this->pdo);
                    $service->recalculateRange($period);

                    return [
                        'status' => 'success',
                        'message' => 'KPIs recalculados para los últimos dos días.',
                        'details' => [
                            'from' => $yesterday->format('Y-m-d'),
                            'to' => $today->format('Y-m-d'),
                        ],
                    ];
                },
            ],
            [
                'slug' => 'ai-sync',
                'name' => 'Sincronización de analítica IA',
                'description' => 'Ejecuta procesos de análisis en Python y sincroniza resultados.',
                'interval' => 1800,
                'callback' => function (): array {
                    return $this->runAiSyncTask();
                },
            ],
            [
                'slug' => 'cive-extension-health',
                'name' => 'Supervisión API CIVE Extension',
                'description' => 'Verifica periódicamente la disponibilidad de los endpoints críticos usados por la extensión.',
                'interval' => 900,
                'callback' => function (): array {
                    return $this->runCiveHealthTask();
                },
            ],
            [
                'slug' => 'identity-verification-expiration',
                'name' => 'Caducidad de certificaciones biométricas',
                'description' => 'Marca certificaciones vencidas según la vigencia configurada y notifica al equipo.',
                'interval' => 86400,
                'callback' => function (): array {
                    return $this->runIdentityVerificationExpirationTask();
                },
            ],
            [
                'slug' => 'iess-derivaciones-sync',
                'name' => 'Sincronización de derivaciones IESS',
                'description' => 'Obtiene códigos de derivación y vincula formID en el esquema normalizado.',
                'interval' => 900,
                'callback' => function (): array {
                    $service = new DerivacionesSyncService($this->pdo);
                    return $service->syncFromLegacyDerivaciones();
                },
            ],
            [
                'slug' => 'iess-derivaciones-scrape-missing',
                'name' => 'Scraping de derivaciones faltantes',
                'description' => 'Ejecuta el scraper para formularios sin código de derivación en billing_main.',
                'interval' => 900,
                'callback' => function (): array {
                    $service = new DerivacionesSyncService($this->pdo);
                    return $service->scrapeMissingDerivationsBatch();
                },
            ],
            [
                'slug' => 'iess-billing-sync',
                'name' => 'Sincronización de facturas IESS',
                'description' => 'Replica facturación asociada a derivaciones hacia la tabla derivaciones_invoices.',
                'interval' => 900,
                'callback' => function (): array {
                    $service = new DerivacionesSyncService($this->pdo);
                    return $service->syncInvoicesFromBilling();
                },
            ],
            [
                'slug' => 'solicitudes-crm-sync',
                'name' => 'Sincronización CRM de solicitudes',
                'description' => 'Reintenta vincular solicitudes sin lead CRM y refresca su checklist.',
                'interval' => 1800,
                'callback' => function (): array {
                    return $this->runSolicitudesCrmSyncTask();
                },
            ],
            [
                'slug' => 'solicitudes-derivaciones-refresh',
                'name' => 'Refresco de derivaciones en solicitudes',
                'description' => 'Actualiza derivaciones y vigencias para solicitudes con afiliación estatal sin derivación.',
                'interval' => 900,
                'callback' => function (): array {
                    return $this->runSolicitudesDerivacionesRefreshTask();
                },
            ],
            [
                'slug' => 'reporting-async-queue',
                'name' => 'Procesamiento asíncrono de reportes PDF',
                'description' => 'Procesa la cola de generación asíncrona de reportes PDF con IA.',
                'interval' => 60,
                'callback' => function (): array {
                    return $this->runReportingAsyncQueueTask();
                },
            ],
        ];
    }


    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runReportingAsyncQueueTask(): array
    {
        $service = new AsyncReportQueueService($this->pdo);
        $result = $service->processPending(2);

        if (($result['processed'] ?? 0) === 0) {
            return [
                'status' => 'skipped',
                'message' => 'No hay jobs pendientes en la cola de reportes.',
                'details' => $result,
            ];
        }

        return [
            'status' => ($result['failed'] ?? 0) > 0 ? 'warning' : 'success',
            'message' => sprintf(
                'Cola de reportes procesada. Total: %d, completados: %d, fallidos permanentes: %d.',
                (int) ($result['processed'] ?? 0),
                (int) ($result['completed'] ?? 0),
                (int) ($result['failed'] ?? 0)
            ),
            'details' => $result,
        ];
    }

    private function runCiveHealthTask(): array
    {
        $service = $this->civeHealthService();
        $result = $service->runScheduledChecks();

        return [
            'status' => $result['status'],
            'message' => $result['message'],
            'details' => $result['details'],
        ];
    }

    private function civeHealthService(): HealthCheckService
    {
        if (!($this->civeHealthService instanceof HealthCheckService)) {
            $this->civeHealthService = new HealthCheckService($this->pdo);
        }

        return $this->civeHealthService;
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runOverdueSolicitudesTask(): array
    {
        $terminalStatuses = [
            'atendido', 'atendida', 'cancelado', 'cancelada', 'cerrado', 'cerrada',
            'suspendido', 'suspendida', 'facturado', 'facturada', 'reprogramado', 'reprogramada',
            'pagado', 'pagada', 'no procede'
        ];

        $placeholders = implode(', ', array_fill(0, count($terminalStatuses), '?'));
        $cutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = "SELECT sp.id
                FROM solicitud_procedimiento sp
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE COALESCE(cd.fecha, sp.fecha) IS NOT NULL
                  AND COALESCE(cd.fecha, sp.fecha) < ?
                  AND (sp.estado IS NULL OR sp.estado = '' OR LOWER(sp.estado) NOT IN ($placeholders))
                  AND LOWER(COALESCE(sp.estado, '')) <> 'atrasada'
                LIMIT 200";

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$cutoff], $this->toLower($terminalStatuses));
        $stmt->execute($params);

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($ids)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontraron solicitudes vencidas para actualizar.',
            ];
        }

        $updateSql = 'UPDATE solicitud_procedimiento SET estado = ? WHERE id IN ('
            . implode(', ', array_fill(0, count($ids), '?')) . ')';

        $update = $this->pdo->prepare($updateSql);
        $update->execute(array_merge(['Atrasada'], $ids));

        return [
            'message' => sprintf('Se actualizaron %d solicitudes a estado "Atrasada".', count($ids)),
            'details' => [
                'affected' => count($ids),
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runRemindersTask(): array
    {
        $pusher = new PusherConfigService($this->pdo);
        $config = $pusher->getConfig();

        if (empty($config['enabled'])) {
            return [
                'status' => 'skipped',
                'message' => 'Las notificaciones en tiempo real están deshabilitadas.',
            ];
        }

        $this->ensureSolicitudModuleLoaded();
        $service = new ExamenesReminderService($this->pdo, $pusher);
        $sent = $service->dispatchUpcoming(72, 48);

        return [
            'message' => sprintf('Se procesaron %d recordatorios automáticos.', count($sent)),
            'details' => [
                'sent' => count($sent),
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runCrmTaskRemindersTask(): array
    {
        $now = new DateTimeImmutable('now');
        $sql = <<<'SQL'
            SELECT
                r.id AS reminder_id,
                r.task_id,
                r.company_id,
                r.remind_at,
                r.channel,
                t.title,
                t.description,
                t.status,
                t.assigned_to,
                t.source_module,
                t.source_ref_id,
                t.entity_type,
                t.entity_id,
                t.hc_number,
                t.form_id,
                t.due_at,
                t.due_date,
                u.nombre AS assigned_name,
                u.email AS assigned_email
            FROM crm_task_reminders r
            INNER JOIN crm_tasks t
                ON t.id = r.task_id
               AND t.company_id = r.company_id
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE r.remind_at <= :now
              AND COALESCE(t.status, 'pendiente') NOT IN ('completada', 'cancelada')
            ORDER BY r.remind_at ASC
            LIMIT 120
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':now' => $now->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return [
                'status' => 'skipped',
                'message' => 'No hay recordatorios de tareas CRM pendientes para enviar.',
            ];
        }

        $pusher = new PusherConfigService($this->pdo);
        $pusherConfig = $pusher->getConfig();
        $mailer = new NotificationMailer($this->pdo);
        $mailerConfigured = $mailer->isConfigured();
        $notificationChannels = $pusher->getNotificationChannels();

        $deleteStmt = $this->pdo->prepare('DELETE FROM crm_task_reminders WHERE id = :id');

        $sentInApp = 0;
        $sentEmail = 0;
        $skippedNoAssignee = 0;
        $skippedNoEmail = 0;
        $skippedInAppDisabled = 0;
        $skippedEmailDisabled = 0;
        $failed = 0;
        $deleted = 0;
        $errors = [];

        foreach ($rows as $row) {
            $reminderId = (int) ($row['reminder_id'] ?? 0);
            if ($reminderId <= 0) {
                continue;
            }

            $channel = strtolower(trim((string) ($row['channel'] ?? 'in_app')));
            $assignedTo = (int) ($row['assigned_to'] ?? 0);
            $assignedEmail = trim((string) ($row['assigned_email'] ?? ''));

            $isSent = false;
            $shouldDelete = false;

            if ($channel === 'in_app') {
                if ($assignedTo <= 0) {
                    $skippedNoAssignee++;
                    $shouldDelete = true;
                } elseif (empty($pusherConfig['enabled'])) {
                    $skippedInAppDisabled++;
                    $shouldDelete = true;
                } else {
                    $payload = $this->buildCrmTaskReminderPayload($row, $notificationChannels);
                    $isSent = $pusher->trigger($payload, null, PusherConfigService::EVENT_CRM_TASK_REMINDER);
                    if ($isSent) {
                        $sentInApp++;
                        $shouldDelete = true;
                    } else {
                        $failed++;
                        if (count($errors) < 8) {
                            $errors[] = [
                                'reminder_id' => $reminderId,
                                'task_id' => (int) ($row['task_id'] ?? 0),
                                'channel' => 'in_app',
                                'message' => 'No se pudo emitir el evento Pusher para el recordatorio interno.',
                            ];
                        }
                    }
                }
            } elseif ($channel === 'email') {
                if ($assignedEmail === '' || filter_var($assignedEmail, FILTER_VALIDATE_EMAIL) === false) {
                    $skippedNoEmail++;
                    $shouldDelete = true;
                } elseif (!$mailerConfigured) {
                    $skippedEmailDisabled++;
                    $shouldDelete = true;
                } else {
                    $subject = 'Recordatorio de tarea CRM: ' . trim((string) ($row['title'] ?? 'Tarea'));
                    $body = $this->buildCrmTaskReminderMailBody($row);
                    $result = $mailer->sendPatientUpdate($assignedEmail, $subject, $body);
                    $isSent = (bool) ($result['success'] ?? false);

                    if ($isSent) {
                        $sentEmail++;
                        $shouldDelete = true;
                    } else {
                        $failed++;
                        if (count($errors) < 8) {
                            $errors[] = [
                                'reminder_id' => $reminderId,
                                'task_id' => (int) ($row['task_id'] ?? 0),
                                'channel' => 'email',
                                'message' => $result['error'] ?? 'No se pudo enviar el correo de recordatorio.',
                            ];
                        }
                    }
                }
            } else {
                // Canal no implementado en este flujo: se descarta para evitar reintentos infinitos.
                $shouldDelete = true;
            }

            if ($shouldDelete) {
                $deleteStmt->execute([':id' => $reminderId]);
                $deleted++;
            }
        }

        $totalSent = $sentInApp + $sentEmail;
        $status = 'success';
        if ($totalSent === 0 && $failed === 0) {
            $status = 'skipped';
        } elseif ($totalSent === 0 && $failed > 0) {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'message' => sprintf(
                'Recordatorios CRM procesados: in-app=%d, email=%d, omitidos=%d, fallidos=%d.',
                $sentInApp,
                $sentEmail,
                $skippedNoAssignee + $skippedNoEmail + $skippedInAppDisabled + $skippedEmailDisabled,
                $failed
            ),
            'details' => [
                'processed' => count($rows),
                'sent_in_app' => $sentInApp,
                'sent_email' => $sentEmail,
                'skipped_no_assignee' => $skippedNoAssignee,
                'skipped_no_email' => $skippedNoEmail,
                'skipped_in_app_disabled' => $skippedInAppDisabled,
                'skipped_email_disabled' => $skippedEmailDisabled,
                'failed' => $failed,
                'deleted' => $deleted,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runCrmTaskSupervisorEscalationsTask(): array
    {
        $now = new DateTimeImmutable('now');
        $graceMinutes = 120;
        $cutoff = $now->sub(new DateInterval('PT' . $graceMinutes . 'M'));

        $sql = <<<'SQL'
            SELECT
                t.id AS task_id,
                t.company_id,
                t.title,
                t.description,
                t.status,
                t.assigned_to,
                t.created_by,
                t.source_module,
                t.source_ref_id,
                t.entity_type,
                t.entity_id,
                t.hc_number,
                t.form_id,
                t.due_at,
                t.due_date,
                t.remind_at,
                assignee.nombre AS assigned_name,
                assignee.email AS assigned_email,
                creator.nombre AS created_name,
                creator.email AS created_email
            FROM crm_tasks t
            LEFT JOIN users assignee ON assignee.id = t.assigned_to
            LEFT JOIN users creator ON creator.id = t.created_by
            WHERE COALESCE(t.status, 'pendiente') NOT IN ('completada', 'cancelada')
              AND COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'), t.remind_at) IS NOT NULL
              AND COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'), t.remind_at) <= :cutoff
              AND NOT EXISTS (
                  SELECT 1
                  FROM crm_task_evidence e
                  WHERE e.task_id = t.id
                    AND e.company_id = t.company_id
                    AND e.evidence_type = 'supervisor_escalation'
              )
            ORDER BY COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'), t.remind_at) ASC
            LIMIT 80
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cutoff' => $cutoff->format('Y-m-d H:i:s')]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [
                'status' => 'skipped',
                'message' => 'No se pudo ejecutar el escalamiento de tareas CRM. Verifica migraciones de CRM.',
                'details' => [
                    'error' => $exception->getMessage(),
                ],
            ];
        }

        if ($rows === []) {
            return [
                'status' => 'skipped',
                'message' => 'No hay tareas CRM vencidas para escalar.',
            ];
        }

        $supervisors = $this->resolveCrmTaskSupervisors();
        $pusher = new PusherConfigService($this->pdo);
        $pusherConfig = $pusher->getConfig();
        $notificationChannels = $pusher->getNotificationChannels();
        $mailer = new NotificationMailer($this->pdo);
        $mailerConfigured = $mailer->isConfigured();

        $escalated = 0;
        $sentInApp = 0;
        $sentEmails = 0;
        $skippedNoSupervisor = 0;
        $skippedNoEmail = 0;
        $skippedInAppDisabled = 0;
        $skippedEmailDisabled = 0;
        $failed = 0;
        $evidenceLogged = 0;
        $errors = [];

        foreach ($rows as $row) {
            $taskId = (int) ($row['task_id'] ?? 0);
            $companyId = (int) ($row['company_id'] ?? 0);
            if ($taskId <= 0 || $companyId <= 0) {
                continue;
            }

            $recipients = $this->resolveCrmTaskEscalationRecipients($row, $supervisors);
            if ($recipients === []) {
                $skippedNoSupervisor++;
                continue;
            }

            $supervisorIds = array_values(array_unique(array_map(static function (array $recipient): int {
                return (int) ($recipient['id'] ?? 0);
            }, $recipients)));
            $supervisorIds = array_values(array_filter($supervisorIds, static fn(int $id): bool => $id > 0));

            $sentInAppForTask = false;
            if (!empty($pusherConfig['enabled']) && $supervisorIds !== []) {
                $payload = $this->buildCrmTaskSupervisorEscalationPayload($row, $supervisorIds, $notificationChannels, $graceMinutes, $now);
                $sentInAppForTask = $pusher->trigger($payload, null, PusherConfigService::EVENT_CRM_TASK_REMINDER);
                if ($sentInAppForTask) {
                    $sentInApp++;
                } else {
                    $failed++;
                    if (count($errors) < 8) {
                        $errors[] = [
                            'task_id' => $taskId,
                            'channel' => 'in_app',
                            'message' => 'No se pudo emitir la alerta de escalación por Pusher.',
                        ];
                    }
                }
            } elseif (empty($pusherConfig['enabled'])) {
                $skippedInAppDisabled++;
            }

            $sentEmailsForTask = 0;
            if ($mailerConfigured) {
                foreach ($recipients as $recipient) {
                    $email = trim((string) ($recipient['email'] ?? ''));
                    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $skippedNoEmail++;
                        continue;
                    }

                    $subject = sprintf('Escalación CRM: tarea pendiente #%d', $taskId);
                    $body = $this->buildCrmTaskSupervisorEscalationMailBody(
                        $row,
                        (string) ($recipient['name'] ?? ''),
                        $graceMinutes,
                        $now
                    );
                    $result = $mailer->sendPatientUpdate($email, $subject, $body);

                    if ((bool) ($result['success'] ?? false)) {
                        $sentEmails++;
                        $sentEmailsForTask++;
                    } else {
                        $failed++;
                        if (count($errors) < 8) {
                            $errors[] = [
                                'task_id' => $taskId,
                                'channel' => 'email',
                                'recipient' => $email,
                                'message' => $result['error'] ?? 'No se pudo enviar la escalación por correo.',
                            ];
                        }
                    }
                }
            } else {
                $skippedEmailDisabled++;
            }

            if ($sentInAppForTask || $sentEmailsForTask > 0) {
                $payload = [
                    'escalated_at' => $now->format('Y-m-d H:i:s'),
                    'grace_minutes' => $graceMinutes,
                    'supervisor_ids' => $supervisorIds,
                    'in_app_sent' => $sentInAppForTask,
                    'emails_sent' => $sentEmailsForTask,
                ];
                if ($this->insertCrmTaskEscalationEvidence($taskId, $companyId, $payload)) {
                    $evidenceLogged++;
                }
                $escalated++;
            }
        }

        $status = 'success';
        if ($escalated === 0 && $failed === 0) {
            $status = 'skipped';
        } elseif ($escalated === 0 && $failed > 0) {
            $status = 'failed';
        }

        $skipped = $skippedNoSupervisor + $skippedNoEmail + $skippedInAppDisabled + $skippedEmailDisabled;

        return [
            'status' => $status,
            'message' => sprintf(
                'Escalamientos CRM procesados: escaladas=%d, in-app=%d, emails=%d, omitidos=%d, fallidos=%d.',
                $escalated,
                $sentInApp,
                $sentEmails,
                $skipped,
                $failed
            ),
            'details' => [
                'processed' => count($rows),
                'escalated' => $escalated,
                'sent_in_app' => $sentInApp,
                'sent_emails' => $sentEmails,
                'skipped_no_supervisor' => $skippedNoSupervisor,
                'skipped_no_email' => $skippedNoEmail,
                'skipped_in_app_disabled' => $skippedInAppDisabled,
                'skipped_email_disabled' => $skippedEmailDisabled,
                'failed' => $failed,
                'evidence_logged' => $evidenceLogged,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array<int, array{id:int,name:string,email:string}>
     */
    private function resolveCrmTaskSupervisors(): array
    {
        $sql = 'SELECT u.id, u.nombre, u.email, u.permisos, r.permissions AS role_permissions '
            . 'FROM users u LEFT JOIN roles r ON r.id = u.role_id';

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }

        $supervisors = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $permissions = Permissions::merge($row['permisos'] ?? [], $row['role_permissions'] ?? []);
            if (!Permissions::containsAny($permissions, ['crm.tasks.manage', 'crm.manage', 'administrativo'])) {
                continue;
            }

            $name = trim((string) ($row['nombre'] ?? ''));
            if ($name === '') {
                $name = 'Supervisor #' . $id;
            }

            $supervisors[$id] = [
                'id' => $id,
                'name' => $name,
                'email' => trim((string) ($row['email'] ?? '')),
            ];
        }

        return array_values($supervisors);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array{id:int,name:string,email:string}> $supervisors
     * @return array<int, array{id:int,name:string,email:string}>
     */
    private function resolveCrmTaskEscalationRecipients(array $row, array $supervisors): array
    {
        $assigneeId = (int) ($row['assigned_to'] ?? 0);
        $recipients = [];

        foreach ($supervisors as $supervisor) {
            $id = (int) ($supervisor['id'] ?? 0);
            if ($id <= 0 || $id === $assigneeId) {
                continue;
            }

            $recipients[$id] = [
                'id' => $id,
                'name' => (string) ($supervisor['name'] ?? ('Supervisor #' . $id)),
                'email' => (string) ($supervisor['email'] ?? ''),
            ];
        }

        if ($recipients === []) {
            $createdBy = (int) ($row['created_by'] ?? 0);
            if ($createdBy > 0 && $createdBy !== $assigneeId) {
                $name = trim((string) ($row['created_name'] ?? ''));
                if ($name === '') {
                    $name = 'Supervisor #' . $createdBy;
                }

                $recipients[$createdBy] = [
                    'id' => $createdBy,
                    'name' => $name,
                    'email' => trim((string) ($row['created_email'] ?? '')),
                ];
            }
        }

        return array_values($recipients);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int> $supervisorIds
     * @param array<string, bool> $channels
     * @return array<string, mixed>
     */
    private function buildCrmTaskSupervisorEscalationPayload(
        array $row,
        array $supervisorIds,
        array $channels,
        int $graceMinutes,
        DateTimeImmutable $now
    ): array {
        $payload = $this->buildCrmTaskReminderPayload($row, $channels);
        $payload['audience_user_ids'] = $supervisorIds;
        $payload['escalated'] = true;
        $payload['escalated_at'] = $now->format(DateTimeImmutable::ATOM);
        $payload['reminder_type'] = 'crm_task_escalation';
        $payload['reminder_label'] = 'Escalación: tarea CRM sin trámite';
        $payload['reminder_context'] = sprintf(
            'La tarea sigue pendiente después de %d minutos del vencimiento.',
            $graceMinutes
        );

        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildCrmTaskSupervisorEscalationMailBody(
        array $row,
        string $supervisorName,
        int $graceMinutes,
        DateTimeImmutable $now
    ): string {
        $taskTitle = trim((string) ($row['title'] ?? 'Tarea CRM'));
        $assignedName = trim((string) ($row['assigned_name'] ?? ''));
        $sourceModule = trim((string) ($row['source_module'] ?? ''));
        $sourceRefId = trim((string) ($row['source_ref_id'] ?? ''));
        $status = trim((string) ($row['status'] ?? 'pendiente'));

        $dueAt = $this->formatReminderDate($row['due_at'] ?? null, 'd/m/Y H:i');
        if ($dueAt === null) {
            $dueAt = $this->formatReminderDate($row['due_date'] ?? null, 'd/m/Y');
        }
        if ($dueAt === null) {
            $dueAt = $this->formatReminderDate($row['remind_at'] ?? null, 'd/m/Y H:i');
        }

        $lines = [];
        $lines[] = 'Hola' . ($supervisorName !== '' ? ' ' . $supervisorName : '') . ',';
        $lines[] = '';
        $lines[] = 'Se escaló una tarea CRM porque no recibió trámite en el tiempo esperado.';
        $lines[] = 'Tarea: ' . $taskTitle;
        $lines[] = 'Responsable actual: ' . ($assignedName !== '' ? $assignedName : 'Sin responsable asignado');
        if ($sourceModule !== '') {
            $lines[] = 'Módulo: ' . strtoupper($sourceModule);
        }
        if ($sourceRefId !== '') {
            $lines[] = 'Referencia: ' . $sourceRefId;
        }
        if ($status !== '') {
            $lines[] = 'Estado actual: ' . $status;
        }
        if ($dueAt !== null) {
            $lines[] = 'Vencimiento: ' . $dueAt;
        }
        $lines[] = sprintf('Escalada tras %d minutos sin actualización.', $graceMinutes);
        $lines[] = 'Fecha de escalación: ' . $now->format('d/m/Y H:i');
        $lines[] = 'Acceso: ' . $this->buildCrmTaskReminderUrl($row);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertCrmTaskEscalationEvidence(int $taskId, int $companyId, array $payload): bool
    {
        if ($taskId <= 0 || $companyId <= 0) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO crm_task_evidence (task_id, company_id, evidence_type, payload, created_by)
                VALUES (:task_id, :company_id, :evidence_type, :payload, NULL)
            ');
            $stmt->execute([
                ':task_id' => $taskId,
                ':company_id' => $companyId,
                ':evidence_type' => 'supervisor_escalation',
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, bool> $channels
     * @return array<string, mixed>
     */
    private function buildCrmTaskReminderPayload(array $row, array $channels): array
    {
        $taskId = (int) ($row['task_id'] ?? 0);
        $reminderId = (int) ($row['reminder_id'] ?? 0);
        $sourceRefId = trim((string) ($row['source_ref_id'] ?? ''));
        $dueAt = trim((string) ($row['due_at'] ?? ''));
        if ($dueAt === '') {
            $dueDate = trim((string) ($row['due_date'] ?? ''));
            $dueAt = $dueDate !== '' ? $dueDate . ' 23:59:59' : '';
        }
        $dueAtIso = $this->formatReminderDate($dueAt, DateTimeImmutable::ATOM);
        $remindAtIso = $this->formatReminderDate($row['remind_at'] ?? null, DateTimeImmutable::ATOM);

        return [
            'id' => $sourceRefId !== '' ? $sourceRefId : $taskId,
            'full_name' => trim((string) ($row['assigned_name'] ?? '')) ?: 'Tarea CRM',
            'task_id' => $taskId,
            'reminder_id' => $reminderId,
            'title' => trim((string) ($row['title'] ?? 'Tarea CRM')),
            'description' => trim((string) ($row['description'] ?? '')),
            'estado' => trim((string) ($row['status'] ?? 'pendiente')),
            'assigned_to' => (int) ($row['assigned_to'] ?? 0),
            'assigned_name' => trim((string) ($row['assigned_name'] ?? '')),
            'source_module' => trim((string) ($row['source_module'] ?? '')),
            'source_ref_id' => $sourceRefId,
            'hc_number' => trim((string) ($row['hc_number'] ?? '')),
            'form_id' => trim((string) ($row['form_id'] ?? '')),
            'entity_type' => trim((string) ($row['entity_type'] ?? '')),
            'entity_id' => trim((string) ($row['entity_id'] ?? '')),
            'due_at' => $dueAtIso,
            'remind_at' => $remindAtIso,
            'reminder_type' => 'crm_task',
            'reminder_label' => 'Recordatorio de tarea CRM',
            'reminder_context' => 'Revisa la tarea y actualiza su estado en el CRM.',
            'task_url' => $this->buildCrmTaskReminderUrl($row),
            'channels' => $channels,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildCrmTaskReminderMailBody(array $row): string
    {
        $assignedName = trim((string) ($row['assigned_name'] ?? ''));
        $taskTitle = trim((string) ($row['title'] ?? 'Tarea CRM'));
        $sourceModule = trim((string) ($row['source_module'] ?? ''));
        $sourceRefId = trim((string) ($row['source_ref_id'] ?? ''));
        $status = trim((string) ($row['status'] ?? 'pendiente'));

        $remindAt = $this->formatReminderDate($row['remind_at'] ?? null, 'd/m/Y H:i');
        $dueAt = $this->formatReminderDate($row['due_at'] ?? null, 'd/m/Y H:i');
        if ($dueAt === null) {
            $dueAt = $this->formatReminderDate($row['due_date'] ?? null, 'd/m/Y');
        }

        $lineas = [];
        $lineas[] = 'Hola' . ($assignedName !== '' ? ' ' . $assignedName : '') . ',';
        $lineas[] = '';
        $lineas[] = 'Tienes un recordatorio de tarea CRM pendiente.';
        $lineas[] = 'Tarea: ' . $taskTitle;
        if ($sourceModule !== '') {
            $lineas[] = 'Módulo: ' . strtoupper($sourceModule);
        }
        if ($sourceRefId !== '') {
            $lineas[] = 'Referencia: ' . $sourceRefId;
        }
        if ($status !== '') {
            $lineas[] = 'Estado actual: ' . $status;
        }
        if ($dueAt !== null) {
            $lineas[] = 'Fecha límite: ' . $dueAt;
        }
        if ($remindAt !== null) {
            $lineas[] = 'Recordatorio: ' . $remindAt;
        }
        $lineas[] = 'Acceso: ' . $this->buildCrmTaskReminderUrl($row);

        return implode("\n", $lineas);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildCrmTaskReminderUrl(array $row): string
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $sourceModule = strtolower(trim((string) ($row['source_module'] ?? '')));
        $sourceRefId = trim((string) ($row['source_ref_id'] ?? ''));

        $path = '/crm';
        if ($sourceModule === 'solicitudes' && $sourceRefId !== '') {
            $path = '/solicitudes/' . rawurlencode($sourceRefId) . '/crm';
        } elseif ($sourceModule === 'examenes' && $sourceRefId !== '') {
            $path = '/examenes/' . rawurlencode($sourceRefId) . '/crm';
        }

        return $base !== '' ? ($base . $path) : $path;
    }

    private function formatReminderDate(mixed $value, string $format): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format($format);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runWhatsappHandoffRequeueTask(): array
    {
        $service = new HandoffService($this->pdo);
        $result = $service->requeueExpired();
        $count = $result['count'] ?? 0;

        if ($count === 0) {
            return [
                'status' => 'skipped',
                'message' => 'No hay handoffs vencidos para reencolar.',
                'details' => $result,
            ];
        }

        return [
            'message' => sprintf('Se reencolaron %d handoffs vencidos.', $count),
            'details' => $result,
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runBillingTask(): array
    {
        $eligibleStatuses = [
            'docs completos', 'facturacion', 'facturación', 'prefactura', 'prefacturación',
            'cobertura aprobada', 'para facturar', 'lista para facturar', 'prefactura lista'
        ];

        $placeholders = implode(', ', array_fill(0, count($eligibleStatuses), '?'));
        $sql = "SELECT sp.form_id, sp.hc_number, COALESCE(cd.fecha, sp.fecha) AS fecha_programada
                FROM solicitud_procedimiento sp
                LEFT JOIN billing_main bm ON bm.form_id = sp.form_id
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE bm.form_id IS NULL
                  AND sp.form_id IS NOT NULL AND sp.form_id <> ''
                  AND sp.hc_number IS NOT NULL AND sp.hc_number <> ''
                  AND LOWER(sp.estado) IN ($placeholders)
                ORDER BY COALESCE(cd.fecha, sp.fecha) ASC
                LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->toLower($eligibleStatuses));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontraron solicitudes listas para prefacturar.',
            ];
        }

        $model = new BillingMainModel($this->pdo);
        $created = 0;

        foreach ($rows as $row) {
            $formId = (string) ($row['form_id'] ?? '');
            $hcNumber = (string) ($row['hc_number'] ?? '');

            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            try {
                $billingId = $model->insert($hcNumber, $formId);
                $created++;

                $fecha = $row['fecha_programada'] ?? null;
                if (!empty($fecha)) {
                    $model->updateFechaCreacion($billingId, (string) $fecha);
                }
            } catch (Throwable) {
                // Ignorar duplicados u otros errores y continuar con los siguientes registros
                continue;
            }
        }

        if ($created === 0) {
            return [
                'status' => 'skipped',
                'message' => 'No se generaron nuevos registros de prefactura.',
            ];
        }

        return [
            'message' => sprintf('Se crearon %d registros en billing_main.', $created),
            'details' => [
                'created' => $created,
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runStatisticsTask(): array
    {
        $terminalStatuses = [
            'atendido', 'atendida', 'cancelado', 'cancelada', 'cerrado', 'cerrada',
            'suspendido', 'suspendida', 'facturado', 'facturada', 'reprogramado', 'reprogramada',
            'pagado', 'pagada', 'no procede', 'atrasada'
        ];

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $now = new DateTimeImmutable('now');
        $nextDay = $now->add(new DateInterval('PT24H'))->format('Y-m-d H:i:s');

        $stats = [
            'solicitudes_total' => (int) $this->fetchScalar('SELECT COUNT(*) FROM solicitud_procedimiento'),
            'solicitudes_atrasadas' => (int) $this->fetchScalar(
                "SELECT COUNT(*) FROM solicitud_procedimiento WHERE LOWER(estado) = 'atrasada'"
            ),
            'solicitudes_pendientes' => (int) $this->fetchScalar(
                "SELECT COUNT(*) FROM solicitud_procedimiento
                 WHERE estado IS NULL OR estado = '' OR LOWER(estado) NOT IN (" . implode(', ', array_fill(0, count($terminalStatuses), '?')) . ")",
                $this->toLower($terminalStatuses)
            ),
            'cirugias_hoy' => (int) $this->fetchScalar(
                'SELECT COUNT(*) FROM protocolo_data WHERE DATE(fecha_inicio) = :today',
                [':today' => $today]
            ),
            'solicitudes_proximas_24h' => (int) $this->fetchScalar(
                'SELECT COUNT(*) FROM solicitud_procedimiento sp
                 LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                 WHERE COALESCE(cd.fecha, sp.fecha) BETWEEN :desde AND :hasta',
                [
                    ':desde' => $now->format('Y-m-d H:i:s'),
                    ':hasta' => $nextDay,
                ]
            ),
            'facturas_mes' => (int) $this->fetchScalar(
                'SELECT COUNT(*) FROM billing_main bm
                 LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
                 LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
                 WHERE COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :inicio AND :fin',
                [
                    ':inicio' => $monthStart,
                    ':fin' => $monthEnd,
                ]
            ),
        ];

        return [
            'message' => 'Estadísticas operativas actualizadas.',
            'details' => [
                'stats' => $stats,
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runAiSyncTask(): array
    {
        $script = BASE_PATH . '/tools/ai_batch.py';

        if (!is_file($script)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontró el script de sincronización IA.',
            ];
        }

        $command = 'python3 ' . escapeshellarg($script);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, BASE_PATH);

        if (!is_resource($process)) {
            throw new RuntimeException('No fue posible iniciar el proceso de Python.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($process);

        if ($code !== 0) {
            $message = trim($stderr !== '' ? $stderr : $stdout);
            if ($message === '') {
                $message = sprintf('El script de Python finalizó con código %d.', $code);
            }
            throw new RuntimeException($message);
        }

        $decoded = json_decode($stdout, true);
        $details = is_array($decoded) ? $decoded : ['output' => trim($stdout)];

        return [
            'message' => 'Sincronización IA completada correctamente.',
            'details' => $details,
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runIndexAdmisionesSyncTask(): array
    {
        $script = BASE_PATH . '/scrapping/sync_index_admisiones.py';

        if (!is_file($script)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontró el script de index-admisiones.',
            ];
        }

        $range = $this->resolveIndexAdmisionesRange();
        if (isset($range['status']) && $range['status'] === 'skipped') {
            return $range;
        }

        $apiUrl = getenv('MEDFORGE_API_URL') ?: 'https://asistentecive.consulmed.me';
        $command = sprintf(
            'python3 %s --start %s --end %s --api-url %s --quiet',
            escapeshellarg($script),
            escapeshellarg($range['start']),
            escapeshellarg($range['end']),
            escapeshellarg($apiUrl)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, BASE_PATH);

        if (!is_resource($process)) {
            throw new RuntimeException('No fue posible iniciar el proceso de index-admisiones.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($process);

        if ($code !== 0) {
            $message = trim($stderr !== '' ? $stderr : $stdout);
            if ($message === '') {
                $message = sprintf('El script de index-admisiones finalizó con código %d.', $code);
            }
            throw new RuntimeException($message);
        }

        $decoded = json_decode($stdout, true);
        $details = is_array($decoded) ? $decoded : ['output' => trim($stdout)];

        return [
            'message' => 'Scraping de index-admisiones completado correctamente.',
            'details' => $details,
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runSolicitudesCrmSyncTask(): array
    {
        $this->ensureSolicitudModuleLoaded();

        $terminalStatuses = [
            'atendido', 'atendida', 'cancelado', 'cancelada', 'cerrado', 'cerrada',
            'suspendido', 'suspendida', 'facturado', 'facturada', 'reprogramado', 'reprogramada',
            'pagado', 'pagada', 'no procede'
        ];

        $placeholders = implode(', ', array_fill(0, count($terminalStatuses), '?'));
        $sql = "SELECT sp.id, sp.hc_number, sp.form_id
                FROM solicitud_procedimiento sp
                LEFT JOIN solicitud_crm_detalles scd ON scd.solicitud_id = sp.id
                WHERE sp.hc_number IS NOT NULL
                  AND sp.hc_number <> ''
                  AND (scd.crm_lead_id IS NULL OR scd.crm_lead_id = 0)
                  AND (sp.estado IS NULL OR sp.estado = '' OR LOWER(sp.estado) NOT IN ($placeholders))
                ORDER BY sp.created_at DESC
                LIMIT 25";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->toLower($terminalStatuses));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontraron solicitudes pendientes de sincronización CRM.',
            ];
        }

        $service = new SolicitudCrmService($this->pdo);
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $row) {
            $solicitudId = (int) ($row['id'] ?? 0);
            $hcNumber = trim((string) ($row['hc_number'] ?? ''));

            if ($solicitudId <= 0 || $hcNumber === '') {
                $failed++;
                continue;
            }

            try {
                $service->bootstrapChecklist($solicitudId, ['hc_number' => $hcNumber], null, []);
                $synced++;
            } catch (Throwable $exception) {
                $failed++;
                if (count($errors) < 5) {
                    $errors[] = [
                        'solicitud_id' => $solicitudId,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        }

        return [
            'message' => sprintf('Se sincronizaron %d solicitudes con CRM.', $synced),
            'details' => [
                'processed' => count($rows),
                'synced' => $synced,
                'failed' => $failed,
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runSolicitudesDerivacionesRefreshTask(): array
    {
        $script = BASE_PATH . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontró el script de derivaciones.',
            ];
        }

        $afiliaciones = ['iess', 'isspol', 'issfa', 'msp'];
        $placeholders = implode(', ', array_fill(0, count($afiliaciones), '?'));
        $sql = "SELECT sp.form_id, sp.hc_number, sp.afiliacion
                FROM solicitud_procedimiento sp
                LEFT JOIN derivaciones_forms df ON df.iess_form_id = sp.form_id
                LEFT JOIN derivaciones_form_id dfl ON dfl.form_id = sp.form_id AND dfl.hc_number = sp.hc_number
                WHERE sp.form_id IS NOT NULL
                  AND sp.form_id <> ''
                  AND sp.hc_number IS NOT NULL
                  AND sp.hc_number <> ''
                  AND LOWER(sp.afiliacion) IN ($placeholders)
                  AND df.id IS NULL
                  AND dfl.form_id IS NULL
                ORDER BY sp.created_at DESC
                LIMIT 10";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($afiliaciones);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return [
                'status' => 'skipped',
                'message' => 'No se encontraron solicitudes sin derivación para refrescar.',
            ];
        }

        $processed = 0;
        $saved = 0;
        $failed = 0;
        $samples = [];

        foreach ($rows as $row) {
            $formId = trim((string) ($row['form_id'] ?? ''));
            $hcNumber = trim((string) ($row['hc_number'] ?? ''));

            if ($formId === '' || $hcNumber === '') {
                $failed++;
                continue;
            }

            $result = $this->scrapeSolicitudDerivacion($script, $formId, $hcNumber);
            $processed++;

            if (!empty($result['saved'])) {
                $saved++;
            } else {
                $failed++;
            }

            if (count($samples) < 5) {
                $samples[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'saved' => !empty($result['saved']),
                ];
            }
        }

        return [
            'message' => sprintf('Se refrescaron derivaciones en %d solicitudes.', $saved),
            'details' => [
                'processed' => $processed,
                'saved' => $saved,
                'failed' => $failed,
                'samples' => $samples,
            ],
        ];
    }

    /**
     * @return array{saved:bool,derivacion_id:int|null,payload:?array,raw_output:string,exit_code:int}
     */
    private function scrapeSolicitudDerivacion(string $script, string $formId, string $hcNumber): array
    {
        $cmd = sprintf(
            'python3 %s %s %s --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($formId),
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
            return [
                'saved' => false,
                'derivacion_id' => null,
                'payload' => null,
                'raw_output' => $rawOutput,
                'exit_code' => $exitCode,
            ];
        }

        $codDerivacion = trim((string) ($parsed['codigo_derivacion'] ?? ''));
        $archivoPath = trim((string) ($parsed['archivo_path'] ?? ''));
        $derivacionId = null;
        $saved = false;

        if ($codDerivacion !== '' && $archivoPath !== '') {
            $derivacionController = new DerivacionController($this->pdo);
            $derivacionId = $derivacionController->guardarDerivacion(
                $codDerivacion,
                $formId,
                $hcNumber,
                $parsed['fecha_registro'] ?? null,
                $parsed['fecha_vigencia'] ?? null,
                $parsed['referido'] ?? null,
                $parsed['diagnostico'] ?? null,
                $parsed['sede'] ?? null,
                $parsed['parentesco'] ?? null,
                $archivoPath
            );
            $saved = $derivacionId !== false && $derivacionId !== null;
        }

        return [
            'saved' => $saved,
            'derivacion_id' => $derivacionId !== null ? (int) $derivacionId : null,
            'payload' => $parsed,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @return array{start:string,end:string,status?:string,message?:string,details?:array}
     */
    private function resolveIndexAdmisionesRange(): array
    {
        $today = new DateTimeImmutable('today');
        $defaultStart = $today->sub(new DateInterval('P1D'));
        $defaultEnd = $today->add(new DateInterval('P1D'));

        $task = $this->repository->findBySlug('cive-index-admisiones-sync');
        $settings = $this->decodeJson($task['settings'] ?? null);

        $start = $defaultStart;
        $end = $defaultEnd;

        if (is_array($settings) && !empty($settings['date_start']) && !empty($settings['date_end'])) {
            try {
                $start = new DateTimeImmutable((string) $settings['date_start']);
                $end = new DateTimeImmutable((string) $settings['date_end']);
            } catch (Throwable) {
                return [
                    'status' => 'skipped',
                    'message' => 'Rango manual inválido en la configuración del cron.',
                    'details' => [
                        'settings' => $settings,
                    ],
                ];
            }
        }

        if ($start > $end) {
            return [
                'status' => 'skipped',
                'message' => 'El rango configurado es inválido (inicio mayor que fin).',
                'details' => [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                ],
            ];
        }

        $days = $start->diff($end)->days ?? 0;
        if ($days > 31) {
            return [
                'status' => 'skipped',
                'message' => 'El rango configurado supera el máximo permitido de 31 días.',
                'details' => [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'days' => $days,
                ],
            ];
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'details' => [
                'auto_default' => [
                    'start' => $defaultStart->format('Y-m-d'),
                    'end' => $defaultEnd->format('Y-m-d'),
                ],
            ],
        ];
    }

    private function decodeJson(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runIdentityVerificationExpirationTask(): array
    {
        $policy = new VerificationPolicyService($this->pdo);
        $validity = $policy->getValidityDays();

        if ($validity <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'La vigencia automática de certificaciones está deshabilitada.',
            ];
        }

        $verifications = new VerificationModel($this->pdo);
        $result = $verifications->expireOlderThan($validity);

        if (($result['expired'] ?? 0) === 0) {
            return [
                'status' => 'success',
                'message' => 'No se encontraron certificaciones para marcar como vencidas.',
                'details' => ['expired' => 0],
            ];
        }

        $escalation = new MissingEvidenceEscalationService($this->pdo, $policy);
        foreach ($result['certifications'] as $certification) {
            $escalation->escalate($certification, 'expired_certification', [
                'metadata' => [
                    'vigencia_dias' => $validity,
                    'ultima_verificacion' => $certification['last_verification_at'] ?? null,
                ],
                'patient_name' => $certification['full_name'] ?? null,
            ]);
        }

        return [
            'status' => 'success',
            'message' => sprintf('Se marcaron %d certificaciones como vencidas.', (int) $result['expired']),
            'details' => ['expired' => (int) $result['expired']],
        ];
    }

    private function buildLockKey(string $slug): string
    {
        return 'medforge:cron:' . $slug;
    }

    private function acquireLock(string $lockKey): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT GET_LOCK(:key, 0)');
            $stmt->execute([':key' => $lockKey]);
            $result = $stmt->fetchColumn();

            return (int) $result === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function releaseLock(string $lockKey): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:key)');
            $stmt->execute([':key' => $lockKey]);
        } catch (Throwable) {
            // No need to bubble up release errors
        }
    }

    private function calculateDuration(float $startedAt): int
    {
        $elapsed = microtime(true) - $startedAt;

        return $elapsed <= 0 ? 0 : (int) round($elapsed * 1000);
    }

    private function ensureSolicitudModuleLoaded(): void
    {
        if ($this->solicitudesLoaded) {
            return;
        }

        $bootstrap = BASE_PATH . '/modules/solicitudes/index.php';
        $model = BASE_PATH . '/modules/solicitudes/models/SolicitudModel.php';
        $legacyModel = BASE_PATH . '/modules/solicitudes/models/ExamenesModel.php';

        if (is_file($bootstrap)) {
            require_once $bootstrap;
        } elseif (is_file($model)) {
            require_once $model;
        } elseif (is_file($legacyModel)) {
            require_once $legacyModel;
        }

        $this->solicitudesLoaded = true;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function toLower(array $values): array
    {
        return array_map(function (string $value): string {
            return function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
        }, $values);
    }

    private function fetchScalar(string $sql, array $params = []): float
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            return 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
