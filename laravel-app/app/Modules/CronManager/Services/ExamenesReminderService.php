<?php

declare(strict_types=1);

namespace App\Modules\CronManager\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

/**
 * Dispatches surgical and examination reminders via Pusher.
 *
 * Depends on legacy SolicitudModel and PusherConfigService, both loaded via
 * the legacy bootstrap. These will be migrated in a later Onda.
 */
class ExamenesReminderService
{
    private const CACHE_FILENAME = '/storage/cache/surgery_reminders.json';
    private const COMPLETED_STATES = [
        'completado',
        'completada',
        'operada',
        'operado',
        'protocolo-completo',
        'facturado',
        'facturada',
        'facturada-cerrada',
        'cerrado',
        'cerrada',
    ];

    /**
     * @var array<string, array{
     *     event: string,
     *     label: string,
     *     context: string,
     *     minOffsetHours: float,
     *     maxOffsetHours: float,
     *     source: 'scheduled'|'expiration'
     * }>
     */
    private const SCENARIOS = [
        'preop' => [
            'event' => 'preop_reminder',
            'label' => 'Preparación preoperatoria',
            'context' => 'Revisar checklist, confirmar ayuno y adjuntar consentimientos previos.',
            'minOffsetHours' => 24.0,
            'maxOffsetHours' => 72.0,
            'source' => 'scheduled',
        ],
        'surgery' => [
            'event' => 'surgery_reminder',
            'label' => 'Recordatorio de cirugía',
            'context' => 'Verificar disponibilidad de quirófano y equipo para la intervención.',
            'minOffsetHours' => 0.0,
            'maxOffsetHours' => 24.0,
            'source' => 'scheduled',
        ],
        'postop' => [
            'event' => 'postop_reminder',
            'label' => 'Control postoperatorio',
            'context' => 'Agendar control, confirmar indicaciones y gestionar incidencias reportadas.',
            'minOffsetHours' => -48.0,
            'maxOffsetHours' => -6.0,
            'source' => 'scheduled',
        ],
        'exams' => [
            'event' => 'exams_expiring',
            'label' => 'Exámenes por vencer',
            'context' => 'Validar vigencia de biometría, topografía o consentimientos del paciente.',
            'minOffsetHours' => 0.0,
            'maxOffsetHours' => 336.0,
            'source' => 'expiration',
        ],
    ];

    private PDO $pdo;
    private object $pusher;
    private object $solicitudModel;
    private string $cachePath;

    public function __construct(PDO $pdo, object $pusher)
    {
        $this->pdo = $pdo;
        $this->pusher = $pusher;
        $this->solicitudModel = $this->buildSolicitudModel($pdo);
        $this->cachePath = $this->resolveBasePath() . self::CACHE_FILENAME;
    }

    /**
     * Busca procedimientos programados y fechas de caducidad relevantes para disparar
     * recordatorios operativos (preoperatorio, cirugía, postoperatorio y vigencias).
     *
     * @return array<int, array<string, mixed>> Lista de recordatorios enviados.
     */
    public function dispatchUpcoming(int $hoursAhead = 24, int $hoursBack = 48): array
    {
        if ($hoursAhead <= 0) {
            $hoursAhead = 24;
        }

        if ($hoursBack < 0) {
            $hoursBack = 0;
        }

        $ahora = new DateTimeImmutable('now');

        $rangoFuturo = $this->resolveMaxFutureOffset($hoursAhead);
        $rangoPasado = $this->resolveMaxPastOffset($hoursBack);

        $desde = $rangoPasado > 0
            ? $ahora->sub(new DateInterval(sprintf('PT%dH', (int) ceil($rangoPasado))))
            : $ahora;

        $hasta = $ahora->add(new DateInterval(sprintf('PT%dH', (int) ceil($rangoFuturo))));

        $programadas = $this->solicitudModel->buscarSolicitudesProgramadas($desde, $hasta);
        if (empty($programadas)) {
            return [];
        }

        $cache = $this->loadCache();
        $enviadas = [];

        foreach ($programadas as $solicitud) {
            $fechaProgramada = isset($solicitud['fecha_programada'])
                ? new DateTimeImmutable((string) $solicitud['fecha_programada'])
                : null;

            $fechaCaducidad = isset($solicitud['fecha_caducidad']) && trim((string) $solicitud['fecha_caducidad']) !== ''
                ? new DateTimeImmutable((string) $solicitud['fecha_caducidad'])
                : null;

            foreach (self::SCENARIOS as $scenarioKey => $scenario) {
                if (!$this->shouldDispatchScenario($scenarioKey, $solicitud)) {
                    continue;
                }

                $dueDate = $this->resolveDueDate($scenario, $fechaProgramada, $fechaCaducidad);
                if (!$dueDate) {
                    continue;
                }

                $diffHours = $this->calculateHoursUntil($dueDate, $ahora);
                if ($diffHours === null
                    || $diffHours < $scenario['minOffsetHours']
                    || $diffHours > $scenario['maxOffsetHours']
                ) {
                    continue;
                }

                $dedupeKey = sprintf(
                    '%s@%s@%s',
                    $solicitud['id'],
                    $scenarioKey,
                    $dueDate->format('Y-m-d H:i')
                );

                if (isset($cache[$dedupeKey])) {
                    $ultimaVez = new DateTimeImmutable($cache[$dedupeKey]);
                    if ($ultimaVez > $ahora->sub(new DateInterval('PT6H'))) {
                        continue;
                    }
                }

                $channels = method_exists($this->pusher, 'getNotificationChannels')
                    ? $this->pusher->getNotificationChannels()
                    : [];

                $payload = [
                    'id' => (int) $solicitud['id'],
                    'form_id' => $solicitud['form_id'] ?? null,
                    'hc_number' => $solicitud['hc_number'] ?? null,
                    'full_name' => $solicitud['full_name'] ?? null,
                    'procedimiento' => $solicitud['procedimiento'] ?? null,
                    'doctor' => $solicitud['doctor'] ?? null,
                    'prioridad' => $solicitud['prioridad'] ?? null,
                    'estado' => $solicitud['estado'] ?? null,
                    'tipo' => $solicitud['tipo'] ?? null,
                    'afiliacion' => $solicitud['afiliacion'] ?? null,
                    'turno' => $solicitud['turno'] ?? null,
                    'quirofano' => $solicitud['quirofano'] ?? null,
                    'fecha_programada' => $fechaProgramada?->format('c'),
                    'due_at' => $dueDate->format('c'),
                    'reminder_type' => $scenarioKey,
                    'reminder_label' => $scenario['label'],
                    'reminder_context' => $scenario['context'],
                    'channels' => $channels,
                ];

                if ($fechaCaducidad instanceof DateTimeImmutable) {
                    $payload['exam_expires_at'] = $fechaCaducidad->format('c');
                }

                $ok = $this->pusher->trigger(
                    $payload,
                    null,
                    $scenario['event']
                );

                if ($ok) {
                    $cache[$dedupeKey] = $ahora->format('c');
                    $enviadas[] = $payload;
                }
            }
        }

        if (!empty($enviadas)) {
            $this->storeCache($cache);
        }

        return $enviadas;
    }

    /**
     * @param array<string,mixed> $solicitud
     */
    private function shouldDispatchScenario(string $scenarioKey, array $solicitud): bool
    {
        $estado = strtolower(trim((string) ($solicitud['estado'] ?? '')));
        $isCompleted = in_array($estado, self::COMPLETED_STATES, true);

        if ($scenarioKey === 'postop') {
            return $isCompleted;
        }

        if (in_array($scenarioKey, ['preop', 'surgery'], true)) {
            return !$isCompleted;
        }

        return true;
    }

    private function resolveDueDate(array $scenario, ?DateTimeImmutable $fechaProgramada, ?DateTimeImmutable $fechaCaducidad): ?DateTimeImmutable
    {
        if ($scenario['source'] === 'scheduled') {
            return $fechaProgramada;
        }

        if ($scenario['source'] === 'expiration') {
            return $fechaCaducidad;
        }

        return null;
    }

    private function calculateHoursUntil(DateTimeImmutable $dueDate, DateTimeImmutable $reference): ?float
    {
        $seconds = $dueDate->getTimestamp() - $reference->getTimestamp();

        return $seconds / 3600;
    }

    private function resolveMaxFutureOffset(int $hoursAhead): float
    {
        $max = (float) $hoursAhead;

        foreach (self::SCENARIOS as $scenario) {
            $max = max($max, max(0.0, $scenario['minOffsetHours'], $scenario['maxOffsetHours']));
        }

        return $max;
    }

    private function resolveMaxPastOffset(int $hoursBack): float
    {
        $max = (float) $hoursBack;

        foreach (self::SCENARIOS as $scenario) {
            $minCandidate = min($scenario['minOffsetHours'], $scenario['maxOffsetHours'], 0.0);
            if ($minCandidate < 0) {
                $max = max($max, abs($minCandidate));
            }
        }

        return $max;
    }

    /**
     * @return array<string, string>
     */
    private function loadCache(): array
    {
        if (!is_file($this->cachePath)) {
            return [];
        }

        $contents = file_get_contents($this->cachePath);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function storeCache(array $cache): void
    {
        $directory = dirname($this->cachePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $this->cachePath,
            json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function resolveBasePath(): string
    {
        // Laravel base_path() points to laravel-app/; the cache lives one level up in the repo root.
        if (function_exists('base_path')) {
            return dirname(base_path());
        }

        return defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 6);
    }

    private function buildSolicitudModel(PDO $pdo): object
    {
        $this->ensureLegacyBootstrap();

        return new \Models\SolicitudModel($pdo);
    }

    private function ensureLegacyBootstrap(): void
    {
        if (class_exists(\Models\SolicitudModel::class)) {
            return;
        }

        $basePath = defined('BASE_PATH') ? (string) BASE_PATH : dirname(base_path());
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $basePath);
        }
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', $basePath . '/public');
        }

        $bootstrapFile = $basePath . '/bootstrap.php';
        if (is_file($bootstrapFile)) {
            require_once $bootstrapFile;
        }
    }
}
