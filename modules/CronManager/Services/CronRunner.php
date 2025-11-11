<?php

declare(strict_types=1);

namespace Modules\CronManager\Services;

use DateInterval;
use DateTimeImmutable;
use DatePeriod;
use Models\BillingMainModel;
use Modules\CronManager\Repositories\CronTaskRepository;
use Modules\CiveExtension\Services\HealthCheckService;
use Modules\IdentityVerification\Models\VerificationModel;
use Modules\IdentityVerification\Services\MissingEvidenceEscalationService;
use Modules\IdentityVerification\Services\VerificationPolicyService;
use Modules\KPI\Services\KpiCalculationService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Solicitudes\Services\ExamenesReminderService;
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
        }
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string,interval:int,callback:callable}>
     */
    private function definitions(): array
    {
        return [
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
        $model = BASE_PATH . '/modules/solicitudes/models/ExamenesModel.php';

        if (is_file($bootstrap)) {
            require_once $bootstrap;
        } elseif (is_file($model)) {
            require_once $model;
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
