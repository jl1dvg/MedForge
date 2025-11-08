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

    public function __construct(PDO $pdo)
    {
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
        if (!$this->settings instanceof SettingsModel) {
            return [];
        }

        $raw = $this->settings->getOption(self::OPTION_KEY);
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function save(array $flow): bool
    {
        if (!$this->settings instanceof SettingsModel) {
            return false;
        }

        $encoded = json_encode($flow, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return false;
        }

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

            return false;
        }

        return true;
    }
}
