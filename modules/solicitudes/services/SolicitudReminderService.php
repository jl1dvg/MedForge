<?php

namespace Modules\Solicitudes\Services;

use DateInterval;
use DateTimeImmutable;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Solicitudes\Models\SolicitudModel;
use PDO;

class SolicitudReminderService
{
    private const CACHE_FILENAME = '/storage/cache/surgery_reminders.json';

    private PDO $pdo;
    private PusherConfigService $pusher;
    private SolicitudModel $solicitudModel;
    private string $cachePath;

    public function __construct(PDO $pdo, PusherConfigService $pusher)
    {
        $this->pdo = $pdo;
        $this->pusher = $pusher;
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->cachePath = BASE_PATH . self::CACHE_FILENAME;
    }

    /**
     * Busca procedimientos programados dentro del rango indicado y envía recordatorios
     * a través de Pusher evitando duplicados recientes.
     *
     * @return array<int, array<string, mixed>> Lista de recordatorios enviados.
     */
    public function dispatchUpcoming(int $hoursAhead = 24): array
    {
        if ($hoursAhead <= 0) {
            $hoursAhead = 24;
        }

        $ahora = new DateTimeImmutable('now');
        $hasta = $ahora->add(new DateInterval(sprintf('PT%dH', $hoursAhead)));

        $programadas = $this->solicitudModel->buscarSolicitudesProgramadas($ahora, $hasta);
        if (empty($programadas)) {
            return [];
        }

        $cache = $this->loadCache();
        $enviadas = [];

        foreach ($programadas as $solicitud) {
            $fechaProgramada = isset($solicitud['fecha_programada'])
                ? new DateTimeImmutable((string) $solicitud['fecha_programada'])
                : null;

            if (!$fechaProgramada) {
                continue;
            }

            $dedupeKey = $solicitud['id'] . '@' . $fechaProgramada->format('Y-m-d H:i');
            if (isset($cache[$dedupeKey])) {
                $ultimaVez = new DateTimeImmutable($cache[$dedupeKey]);
                // Evitar reenviar dentro de las últimas 6 horas para la misma cirugía.
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
                'fecha_programada' => $fechaProgramada->format('c'),
                'channels' => $this->pusher->getNotificationChannels(),
            ];

            $ok = $this->pusher->trigger(
                $payload,
                null,
                PusherConfigService::EVENT_SURGERY_REMINDER
            );

            if ($ok) {
                $cache[$dedupeKey] = $ahora->format('c');
                $enviadas[] = $payload;
            }
        }

        if (!empty($enviadas)) {
            $this->storeCache($cache);
        }

        return $enviadas;
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
