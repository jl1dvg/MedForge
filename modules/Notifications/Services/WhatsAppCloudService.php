<?php

namespace Modules\Notifications\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class WhatsAppCloudService
{
    private const DEFAULT_API_VERSION = 'v17.0';
    private const GRAPH_BASE_URL = 'https://graph.facebook.com/';
    private const MAX_MESSAGE_LENGTH = 4096;

    private PDO $pdo;
    private ?SettingsModel $settingsModel = null;
    private ?array $configCache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
        }
    }

    public function isEnabled(): bool
    {
        return $this->getConfig()['enabled'];
    }

    public function getBrandName(): string
    {
        return $this->getConfig()['brand'];
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
    public function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $config = [
            'enabled' => false,
            'phone_number_id' => '',
            'business_account_id' => '',
            'access_token' => '',
            'api_version' => self::DEFAULT_API_VERSION,
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

        return $this->configCache = $config;
    }

    /**
     * @param string|array<int, string> $recipients
     */
    public function sendTextMessage($recipients, string $message, array $options = []): bool
    {
        $config = $this->getConfig();
        if (!$config['enabled']) {
            return false;
        }

        $message = $this->sanitizeMessage($message);
        if ($message === '') {
            return false;
        }

        $recipients = $this->normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => (bool) ($options['preview_url'] ?? false),
                    'body' => $message,
                ],
            ];

            $allSucceeded = $this->dispatchRequest($config, $payload) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param array{
     *     enabled: bool,
     *     phone_number_id: string,
     *     access_token: string,
     *     api_version: string
     * } $config
     * @param array<string, mixed> $payload
     */
    private function dispatchRequest(array $config, array $payload): bool
    {
        $endpoint = self::GRAPH_BASE_URL . rtrim($config['api_version'], '/') . '/' . $config['phone_number_id'] . '/messages';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            error_log('No fue posible codificar el payload de WhatsApp Cloud API.');

            return false;
        }

        $handle = curl_init($endpoint);
        if ($handle === false) {
            error_log('No fue posible iniciar la solicitud cURL para WhatsApp Cloud API.');

            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);
            error_log('Error en la solicitud a WhatsApp Cloud API: ' . $error);

            return false;
        }

        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('WhatsApp Cloud API respondió con código ' . $httpCode . ': ' . $response);

            return false;
        }

        return true;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, string> $config
     *
     * @return array<int, string>
     */
    private function normalizeRecipients($recipients, array $config): array
    {
        $list = is_array($recipients) ? $recipients : [$recipients];
        $normalized = [];

        foreach ($list as $recipient) {
            $formatted = $this->formatPhoneNumber((string) $recipient, $config);
            if ($formatted !== null) {
                $normalized[$formatted] = $formatted;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, string> $config
     */
    private function formatPhoneNumber(string $phone, array $config): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            $digits = '+' . preg_replace('/\D+/', '', substr($phone, 1));

            return strlen($digits) > 1 ? $digits : null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $country = preg_replace('/\D+/', '', $config['default_country_code'] ?? '');
        if ($country !== '' && !str_starts_with($digits, $country)) {
            $digits = $country . $digits;
        }

        return '+' . $digits;
    }

    private function sanitizeMessage(string $message): string
    {
        $message = str_replace("\r", '', $message);
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH - 1) . '…';
        }

        return $message;
    }
}
