<?php

namespace Modules\WhatsApp\Config;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class WhatsAppSettings
{
    private PDO $pdo;
    private ?SettingsModel $settingsModel = null;
    private ?array $cache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
            error_log('No fue posible inicializar SettingsModel para WhatsApp: ' . $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     enabled: bool,
     *     phone_number_id: string,
     *     business_account_id: string,
     *     access_token: string,
     *     api_version: string,
     *     default_country_code: string,
     *     brand: string
     * }
     */
    public function get(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $config = [
            'enabled' => false,
            'phone_number_id' => '',
            'business_account_id' => '',
            'access_token' => '',
            'api_version' => 'v17.0',
            'default_country_code' => '',
            'brand' => 'MedForge',
        ];

        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $options = $this->settingsModel->getOptions([
                    'whatsapp_cloud_enabled',
                    'whatsapp_cloud_phone_number_id',
                    'whatsapp_cloud_business_account_id',
                    'whatsapp_cloud_access_token',
                    'whatsapp_cloud_api_version',
                    'whatsapp_cloud_default_country_code',
                    'companyname',
                ]);

                $config['enabled'] = ($options['whatsapp_cloud_enabled'] ?? '0') === '1';
                $config['phone_number_id'] = trim((string) ($options['whatsapp_cloud_phone_number_id'] ?? ''));
                $config['business_account_id'] = trim((string) ($options['whatsapp_cloud_business_account_id'] ?? ''));
                $config['access_token'] = trim((string) ($options['whatsapp_cloud_access_token'] ?? ''));

                $apiVersion = trim((string) ($options['whatsapp_cloud_api_version'] ?? ''));
                if ($apiVersion !== '') {
                    $config['api_version'] = $apiVersion;
                }

                $countryCode = preg_replace('/\D+/', '', (string) ($options['whatsapp_cloud_default_country_code'] ?? ''));
                $config['default_country_code'] = $countryCode ?? '';

                $brand = trim((string) ($options['companyname'] ?? ''));
                if ($brand !== '') {
                    $config['brand'] = $brand;
                }
            } catch (Throwable $exception) {
                error_log('No fue posible cargar la configuración de WhatsApp Cloud API: ' . $exception->getMessage());
            }
        }

        $config['enabled'] = $config['enabled']
            && $config['phone_number_id'] !== ''
            && $config['access_token'] !== '';

        return $this->cache = $config;
    }

    public function isEnabled(): bool
    {
        return $this->get()['enabled'];
    }

    public function getBrandName(): string
    {
        return $this->get()['brand'];
    }
}
