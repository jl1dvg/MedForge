<?php

namespace Modules\Solicitudes\Services;

use DateInterval;
use DateTimeImmutable;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Examenes\Models\ExamenesModel;
use PDO;

class ExamenesReminderService
{
    private const CACHE_FILENAME = '/storage/cache/surgery_reminders.json';

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
            'event' => PusherConfigService::EVENT_PREOP_REMINDER,
            'label' => 'Preparación preoperatoria',
            'context' => 'Revisar checklist, confirmar ayuno y adjuntar consentimientos previos.',
            'minOffsetHours' => 24.0,
            'maxOffsetHours' => 72.0,
            'source' => 'scheduled',
        ],
        'surgery' => [
            'event' => PusherConfigService::EVENT_SURGERY_REMINDER,
            'label' => 'Recordatorio de cirugía',
            'context' => 'Verificar disponibilidad de quirófano y equipo para la intervención.',
            'minOffsetHours' => 0.0,
            'maxOffsetHours' => 24.0,
            'source' => 'scheduled',
        ],
        'postop' => [
            'event' => PusherConfigService::EVENT_POSTOP_REMINDER,
            'label' => 'Control postoperatorio',
            'context' => 'Agendar control, confirmar indicaciones y gestionar incidencias reportadas.',
            'minOffsetHours' => -48.0,
            'maxOffsetHours' => -6.0,
            'source' => 'scheduled',
        ],
        'exams' => [
            'event' => PusherConfigService::EVENT_EXAMS_EXPIRING,
            'label' => 'Exámenes por vencer',
            'context' => 'Validar vigencia de biometría, topografía o consentimientos del paciente.',
            'minOffsetHours' => 0.0,
            'maxOffsetHours' => 336.0,
            'source' => 'expiration',
        ],
    ];

    private PDO $pdo;
    private PusherConfigService $pusher;
    private ExamenesModel $solicitudModel;
    private string $cachePath;

    public function __construct(PDO $pdo, PusherConfigService $pusher)
    {
        $this->pdo = $pdo;
        $this->pusher = $pusher;
        $this->solicitudModel = new ExamenesModel($pdo);
        $this->cachePath = BASE_PATH . self::CACHE_FILENAME;
    }

    /**
     * Busca procedimientos programados y fechas de caducidad relevantes para disparar
     * recordatorios operativos (preoperatorio, cirugía, postoperatorio y vigencias).
     *
     * El servicio calcula ventanas relativas según cada escenario y evita duplicados
     * mediante caché en disco.
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
                    'channels' => $this->pusher->getNotificationChannels(),
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
}
