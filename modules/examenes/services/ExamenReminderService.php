<?php

namespace Modules\Examenes\Services;

use DateInterval;
use DateTimeImmutable;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Examenes\Models\ExamenModel;
use PDO;

class ExamenReminderService
{
    private const CACHE_FILENAME = '/storage/cache/exam_reminders.json';

    private PDO $pdo;
    private PusherConfigService $pusher;
    private ExamenModel $model;
    private string $cachePath;

    public function __construct(PDO $pdo, PusherConfigService $pusher)
    {
        $this->pdo = $pdo;
        $this->pusher = $pusher;
        $this->model = new ExamenModel($pdo);
        $this->cachePath = BASE_PATH . self::CACHE_FILENAME;
    }

    public function dispatchUpcoming(int $hoursAhead = 24): array
    {
        if ($hoursAhead <= 0) {
            $hoursAhead = 24;
        }

        $ahora = new DateTimeImmutable('now');
        $hasta = $ahora->add(new DateInterval(sprintf('PT%dH', $hoursAhead)));

        $programados = $this->model->buscarExamenesProgramados($ahora, $hasta);
        if (empty($programados)) {
            return [];
        }

        $cache = $this->loadCache();
        $enviados = [];

        foreach ($programados as $examen) {
            $fecha = isset($examen['consulta_fecha'])
                ? new DateTimeImmutable((string) $examen['consulta_fecha'])
                : null;

            if (!$fecha) {
                continue;
            }

            $dedupeKey = $examen['id'] . '@' . $fecha->format('Y-m-d H:i');
            if (isset($cache[$dedupeKey])) {
                $ultimaVez = new DateTimeImmutable($cache[$dedupeKey]);
                if ($ultimaVez > $ahora->sub(new DateInterval('PT6H'))) {
                    continue;
                }
            }

            $payload = [
                'id' => (int) $examen['id'],
                'form_id' => $examen['form_id'] ?? null,
                'hc_number' => $examen['hc_number'] ?? null,
                'full_name' => $examen['full_name'] ?? null,
                'examen_nombre' => $examen['examen_nombre'] ?? null,
                'doctor' => $examen['doctor'] ?? null,
                'prioridad' => $examen['prioridad'] ?? null,
                'estado' => $examen['estado'] ?? null,
                'turno' => $examen['turno'] ?? null,
                'fecha_programada' => $fecha->format('c'),
                'channels' => $this->pusher->getNotificationChannels(),
            ];

            $eventAlias = PusherConfigService::EVENT_SURGERY_REMINDER;
            $examReminderKey = PusherConfigService::class . '::EVENT_EXAM_REMINDER';
            if (defined($examReminderKey)) {
                $eventAlias = constant($examReminderKey);
            }

            $ok = $this->pusher->trigger(
                $payload,
                null,
                $eventAlias
            );

            if ($ok) {
                $cache[$dedupeKey] = $ahora->format('c');
                $enviados[] = $payload;
            }
        }

        if (!empty($enviados)) {
            $this->storeCache($cache);
        }

        return $enviados;
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
