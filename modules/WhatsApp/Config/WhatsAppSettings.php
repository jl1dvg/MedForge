<?php

namespace Modules\WhatsApp\Config;

use Models\SettingsModel;
use Modules\WhatsApp\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Support\DataProtectionCopy;
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
     *     webhook_verify_token: string,
     *     webhook_url: string,
     *     brand: string,
     *     registry_lookup_url: string,
     *     registry_token: string,
     *     registry_timeout: int,
     *     data_terms_url: string,
     *     data_consent_message: string,
     *     data_consent_yes_keywords: array<int, string>,
     *     data_consent_no_keywords: array<int, string>,
     *     data_protection_flow: array<string, mixed>
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
            'webhook_verify_token' => '',
            'webhook_url' => rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/whatsapp/webhook',
            'brand' => 'MedForge',
            'registry_lookup_url' => '',
            'registry_token' => '',
            'registry_timeout' => 10,
            'data_terms_url' => '',
            'data_consent_message' => 'Confirmamos tu identidad y protegemos tus datos personales. ¿Autorizas el uso de tu información para gestionar tus servicios médicos?',
            'data_consent_yes_keywords' => ['si', 'acepto', 'confirmo', 'confirmar'],
            'data_consent_no_keywords' => ['no', 'rechazo', 'no autorizo'],
            'data_protection_flow' => DataProtectionCopy::defaults('MedForge'),
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
                    'whatsapp_webhook_verify_token',
                    'whatsapp_registry_lookup_url',
                    'whatsapp_registry_token',
                    'whatsapp_registry_timeout',
                    'whatsapp_data_terms_url',
                    'whatsapp_data_consent_message',
                    'whatsapp_data_consent_yes_keywords',
                    'whatsapp_data_consent_no_keywords',
                    'whatsapp_autoresponder_flow',
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

                $webhookVerifyToken = trim((string) ($options['whatsapp_webhook_verify_token'] ?? ''));
                $config['webhook_verify_token'] = $webhookVerifyToken;

                $registryUrl = trim((string) ($options['whatsapp_registry_lookup_url'] ?? ''));
                if ($registryUrl !== '') {
                    $config['registry_lookup_url'] = $registryUrl;
                }

                $registryToken = trim((string) ($options['whatsapp_registry_token'] ?? ''));
                if ($registryToken !== '') {
                    $config['registry_token'] = $registryToken;
                }

                $timeout = (int) ($options['whatsapp_registry_timeout'] ?? 10);
                if ($timeout > 0) {
                    $config['registry_timeout'] = $timeout;
                }

                $termsUrl = trim((string) ($options['whatsapp_data_terms_url'] ?? ''));
                if ($termsUrl !== '') {
                    $config['data_terms_url'] = $termsUrl;
                }

                $consentMessage = trim((string) ($options['whatsapp_data_consent_message'] ?? ''));
                if ($consentMessage !== '') {
                    $config['data_consent_message'] = $consentMessage;
                }

                $yesKeywords = $this->normalizeKeywordList($options['whatsapp_data_consent_yes_keywords'] ?? null);
                if (!empty($yesKeywords)) {
                    $config['data_consent_yes_keywords'] = $yesKeywords;
                }

                $noKeywords = $this->normalizeKeywordList($options['whatsapp_data_consent_no_keywords'] ?? null);
                if (!empty($noKeywords)) {
                    $config['data_consent_no_keywords'] = $noKeywords;
                }

                $brand = trim((string) ($options['companyname'] ?? ''));
                if ($brand !== '') {
                    $config['brand'] = $brand;
                }

                $flowOverrides = [];
                try {
                    $flowRepository = new AutoresponderFlowRepository($this->pdo);
                    $activeFlow = $flowRepository->loadActive();
                    if (!empty($activeFlow['consent']) && is_array($activeFlow['consent'])) {
                        $flowOverrides = $activeFlow['consent'];
                    }
                } catch (Throwable $exception) {
                    error_log('No fue posible cargar el flujo activo de autorespuesta: ' . $exception->getMessage());
                }

                if (empty($flowOverrides)) {
                    $rawFlow = $options['whatsapp_autoresponder_flow'] ?? '';
                    if (is_string($rawFlow) && $rawFlow !== '') {
                        $decodedFlow = json_decode($rawFlow, true);
                        if (is_array($decodedFlow) && isset($decodedFlow['consent']) && is_array($decodedFlow['consent'])) {
                            $flowOverrides = $decodedFlow['consent'];
                        }
                    }
                }

                $config['data_protection_flow'] = DataProtectionCopy::resolve($config['brand'], $flowOverrides);
                if (isset($config['data_protection_flow']['consent_prompt'])) {
                    $config['data_consent_message'] = (string) $config['data_protection_flow']['consent_prompt'];
                }
            } catch (Throwable $exception) {
                error_log('No fue posible cargar la configuración de WhatsApp Cloud API: ' . $exception->getMessage());
            }
        }

        if (empty($config['data_protection_flow'])) {
            $config['data_protection_flow'] = DataProtectionCopy::defaults($config['brand']);
        }

        if ($config['webhook_verify_token'] === '') {
            $config['webhook_verify_token'] = (string) (
                $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN']
                ?? $_ENV['WHATSAPP_VERIFY_TOKEN']
                ?? getenv('WHATSAPP_WEBHOOK_VERIFY_TOKEN')
                ?? getenv('WHATSAPP_VERIFY_TOKEN')
                ?? 'medforge-whatsapp'
            );
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

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeKeywordList($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $raw = preg_split('/[,\n]/', $raw) ?: [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $keywords = [];
        foreach ($raw as $value) {
            if (!is_string($value)) {
                continue;
            }

            $clean = trim($value);
            if ($clean === '') {
                continue;
            }

            $keywords[] = mb_strtolower($clean, 'UTF-8');
        }

        return array_values(array_unique($keywords));
    }
}
