<?php

namespace Modules\WhatsApp\Repositories;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class AutoresponderFlowRepository
{
    private const OPTION_KEY = 'whatsapp_autoresponder_flow';

    private ?SettingsModel $settings = null;
    private string $fallbackPath;

    public function __construct(PDO $pdo)
    {
        $this->fallbackPath = BASE_PATH . '/storage/whatsapp_autoresponder_flow.json';

        try {
            $this->settings = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settings = null;
            error_log('No fue posible inicializar SettingsModel para el flujo de autorespuesta: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if ($this->settings instanceof SettingsModel) {
            $raw = $this->settings->getOption(self::OPTION_KEY);
            if ($raw !== null && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return $this->loadFromFallback();
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function save(array $flow): bool
    {
        $encoded = json_encode($flow, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return false;
        }

        if ($this->settings instanceof SettingsModel) {
            try {
                $this->settings->updateOptions([
                    self::OPTION_KEY => [
                        'value' => $encoded,
                        'category' => 'whatsapp',
                        'autoload' => false,
                    ],
                ]);
            } catch (Throwable $exception) {
                error_log('No fue posible guardar el flujo de autorespuesta: ' . $exception->getMessage());

                return $this->saveToFallback($encoded);
            }

            $this->saveToFallback($encoded);

            return true;
        }

        return $this->saveToFallback($encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromFallback(): array
    {
        if (!is_file($this->fallbackPath)) {
            return [];
        }

        $contents = file_get_contents($this->fallbackPath);
        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveToFallback(string $encoded): bool
    {
        $directory = dirname($this->fallbackPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('No fue posible crear el directorio para el respaldo del flujo de autorespuesta: ' . $directory);

            return false;
        }

        $bytes = @file_put_contents($this->fallbackPath, $encoded);
        if ($bytes === false) {
            error_log('No fue posible escribir el respaldo del flujo de autorespuesta en ' . $this->fallbackPath);

            return false;
        }

        return true;
    }
}
