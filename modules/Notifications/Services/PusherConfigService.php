<?php

namespace Modules\Notifications\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class PusherConfigService
{
    public const EVENT_NEW_REQUEST = 'new_request';
    public const EVENT_STATUS_UPDATED = 'status_updated';
    public const EVENT_CRM_UPDATED = 'crm_updated';
    public const EVENT_SURGERY_REMINDER = 'surgery_reminder';
    public const EVENT_PREOP_REMINDER = 'preop_reminder';
    public const EVENT_POSTOP_REMINDER = 'postop_reminder';
    public const EVENT_EXAMS_EXPIRING = 'exams_expiring';
    public const EVENT_EXAM_REMINDER = 'exam_reminder';
    public const EVENT_TURNERO_UPDATED = 'turnero_updated';

    private const DEFAULT_CHANNEL = 'solicitudes-kanban';
    private const DEFAULT_EVENTS = [
        self::EVENT_NEW_REQUEST => 'kanban.nueva-solicitud',
        self::EVENT_STATUS_UPDATED => 'kanban.estado-actualizado',
        self::EVENT_CRM_UPDATED => 'crm.detalles-actualizados',
        self::EVENT_SURGERY_REMINDER => 'recordatorio-cirugia',
        self::EVENT_PREOP_REMINDER => 'recordatorio-preop',
        self::EVENT_POSTOP_REMINDER => 'recordatorio-postop',
        self::EVENT_EXAMS_EXPIRING => 'alerta-examenes-por-vencer',
        self::EVENT_EXAM_REMINDER => 'recordatorio-examen',
        self::EVENT_TURNERO_UPDATED => 'turnero.turno-actualizado',
    ];

    private ?SettingsModel $settingsModel = null;
    private ?array $configCache = null;

    public function __construct(PDO $pdo)
    {
        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
        } catch (Throwable $exception) {
            $this->settingsModel = null;
            error_log('No fue posible inicializar SettingsModel para Pusher: ' . $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     enabled: bool,
     *     app_id: string,
     *     key: string,
     *     secret: string,
     *     cluster: string,
     *     channel: string,
     *     event: string,
     *     desktop_notifications: bool,
     *     auto_dismiss_seconds: int,
     *     events: array<string, string>,
     *     channels: array{email: bool, sms: bool, daily_summary: bool}
     * }
     */
    public function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $config = [
            'enabled' => false,
            'app_id' => '',
            'key' => '',
            'secret' => '',
            'cluster' => '',
            'channel' => self::DEFAULT_CHANNEL,
            'event' => self::DEFAULT_EVENTS[self::EVENT_NEW_REQUEST],
            'desktop_notifications' => false,
            'auto_dismiss_seconds' => 0,
            'events' => self::DEFAULT_EVENTS,
            'channels' => [
                'email' => false,
                'sms' => false,
                'daily_summary' => false,
            ],
        ];

        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $options = $this->settingsModel->getOptions([
                    'pusher_app_id',
                    'pusher_app_key',
                    'pusher_app_secret',
                    'pusher_cluster',
                    'pusher_realtime_notifications',
                    'desktop_notifications',
                    'auto_dismiss_desktop_notifications_after',
                    'notifications_email_enabled',
                    'notifications_sms_enabled',
                    'notifications_daily_summary',
                ]);

                $config['app_id'] = trim((string) ($options['pusher_app_id'] ?? ''));
                $config['key'] = trim((string) ($options['pusher_app_key'] ?? ''));
                $config['secret'] = trim((string) ($options['pusher_app_secret'] ?? ''));
                $config['cluster'] = trim((string) ($options['pusher_cluster'] ?? ''));
                $config['desktop_notifications'] = ($options['desktop_notifications'] ?? '0') === '1';
                $config['auto_dismiss_seconds'] = max(0, (int) ($options['auto_dismiss_desktop_notifications_after'] ?? 0));
                $config['enabled'] = ($options['pusher_realtime_notifications'] ?? '0') === '1';
                $config['channels'] = [
                    'email' => ($options['notifications_email_enabled'] ?? '0') === '1',
                    'sms' => ($options['notifications_sms_enabled'] ?? '0') === '1',
                    'daily_summary' => ($options['notifications_daily_summary'] ?? '0') === '1',
                ];
            } catch (Throwable $exception) {
                error_log('No fue posible cargar la configuración de Pusher: ' . $exception->getMessage());
            }
        }

        $config['enabled'] = $config['enabled']
            && $config['app_id'] !== ''
            && $config['key'] !== ''
            && $config['secret'] !== '';

        $this->configCache = $config;

        return $this->configCache;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     key: string,
     *     cluster: string,
     *     channel: string,
     *     event: string,
     *     desktop_notifications: bool,
     *     auto_dismiss_seconds: int,
     *     events: array<string, string>,
     *     channels: array{email: bool, sms: bool, daily_summary: bool}
     * }
     */
    public function getPublicConfig(): array
    {
        $config = $this->getConfig();

        return [
            'enabled' => $config['enabled'],
            'key' => $config['key'],
            'cluster' => $config['cluster'],
            'channel' => $config['channel'],
            'event' => $config['event'],
            'desktop_notifications' => $config['desktop_notifications'],
            'auto_dismiss_seconds' => $config['auto_dismiss_seconds'],
            'events' => $config['events'],
            'channels' => $config['channels'],
        ];
    }

    public function trigger(array $payload, ?string $channel = null, ?string $event = null): bool
    {
        $config = $this->getConfig();

        if (!$config['enabled']) {
            return false;
        }

        if (!class_exists(\Pusher\Pusher::class)) {
            error_log('La librería pusher/pusher-php-server no está disponible.');
            return false;
        }

        $channel = $channel ?: $config['channel'];
        $eventName = $this->resolveEventName($event, $config);

        if ($channel === '' || $eventName === '') {
            return false;
        }

        $options = ['useTLS' => true];
        if ($config['cluster'] !== '') {
            $options['cluster'] = $config['cluster'];
        }

        try {
            $pusher = new \Pusher\Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                $options
            );

            $pusher->trigger($channel, $eventName, $payload);

            return true;
        } catch (Throwable $exception) {
            error_log('Error enviando evento Pusher: ' . $exception->getMessage());

            return false;
        }
    }

    public function getEventName(string $alias): string
    {
        $config = $this->getConfig();

        return $this->resolveEventName($alias, $config);
    }

    public function getNotificationChannels(): array
    {
        $config = $this->getConfig();

        return $config['channels'];
    }

    /**
     * @param array{events: array<string, string>, event: string} $config
     */
    private function resolveEventName(?string $event, array $config): string
    {
        $events = $config['events'] ?? self::DEFAULT_EVENTS;

        if ($event === null || $event === '') {
            return $config['event'] ?? $events[self::EVENT_NEW_REQUEST];
        }

        if (isset($events[$event])) {
            return (string) $events[$event];
        }

        // Allow passing the raw event name already resolved.
        if (in_array($event, $events, true)) {
            return $event;
        }

        return $event;
    }
}
