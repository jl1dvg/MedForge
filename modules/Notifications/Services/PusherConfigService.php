<?php

namespace Modules\Notifications\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class PusherConfigService
{
    private const DEFAULT_CHANNEL = 'solicitudes-kanban';
    private const DEFAULT_EVENT = 'nueva-solicitud';

    private ?SettingsModel $settingsModel = null;
    private ?array $configCache = null;

    public function __construct(PDO $pdo)
    {
        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
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
     *     auto_dismiss_seconds: int
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
            'event' => self::DEFAULT_EVENT,
            'desktop_notifications' => false,
            'auto_dismiss_seconds' => 0,
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
                ]);

                $config['app_id'] = trim((string) ($options['pusher_app_id'] ?? ''));
                $config['key'] = trim((string) ($options['pusher_app_key'] ?? ''));
                $config['secret'] = trim((string) ($options['pusher_app_secret'] ?? ''));
                $config['cluster'] = trim((string) ($options['pusher_cluster'] ?? ''));
                $config['desktop_notifications'] = ($options['desktop_notifications'] ?? '0') === '1';
                $config['auto_dismiss_seconds'] = max(0, (int) ($options['auto_dismiss_desktop_notifications_after'] ?? 0));
                $config['enabled'] = ($options['pusher_realtime_notifications'] ?? '0') === '1';
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
     *     auto_dismiss_seconds: int
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
        $event = $event ?: $config['event'];

        if ($channel === '' || $event === '') {
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

            $pusher->trigger($channel, $event, $payload);

            return true;
        } catch (Throwable $exception) {
            error_log('Error enviando evento Pusher: ' . $exception->getMessage());

            return false;
        }
    }
}
