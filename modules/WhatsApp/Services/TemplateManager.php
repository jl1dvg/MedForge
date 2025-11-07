<?php

namespace Modules\WhatsApp\Services;

use Modules\WhatsApp\Config\WhatsAppSettings;
use PDO;
use RuntimeException;

class TemplateManager
{
    private const GRAPH_BASE_URL = 'https://graph.facebook.com/';

    private WhatsAppSettings $settings;

    public function __construct(PDO $pdo)
    {
        $this->settings = new WhatsAppSettings($pdo);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, paging?: array<string, mixed>}
     */
    public function listTemplates(array $filters = []): array
    {
        $config = $this->ensureConfigured();

        $query = [
            'limit' => isset($filters['limit']) ? max(1, (int) $filters['limit']) : 100,
            'fields' => 'id,name,category,language,status,quality_score,components,last_updated_time',
        ];

        $response = $this->request(
            'GET',
            $config['business_account_id'] . '/message_templates',
            null,
            $query,
            $config
        );

        $templates = [];
        foreach (($response['data'] ?? []) as $template) {
            if (!is_array($template)) {
                continue;
            }

            $templates[] = [
                'id' => (string) ($template['id'] ?? ''),
                'name' => (string) ($template['name'] ?? ''),
                'category' => (string) ($template['category'] ?? ''),
                'language' => (string) ($template['language'] ?? ''),
                'status' => (string) ($template['status'] ?? ''),
                'quality_score' => $template['quality_score']['score'] ?? $template['quality_score'] ?? null,
                'last_updated_time' => $template['last_updated_time'] ?? null,
                'components' => $template['components'] ?? [],
            ];
        }

        $templates = $this->applyFilters($templates, $filters);

        return [
            'data' => array_values($templates),
            'paging' => $response['paging'] ?? null,
        ];
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function listLanguages(): array
    {
        // Nota: No existe el endpoint /{waba_id}/message_templates_languages en Graph API.
        // Para el selector del UI usamos una lista estática de idiomas admitidos por WhatsApp Cloud API.
        // Puedes ampliar esta lista según lo necesites (ver documentación oficial).
        $languages = [
            ['code' => 'es',    'name' => 'Español'],
            ['code' => 'es_AR', 'name' => 'Español (Argentina)'],
            ['code' => 'es_ES', 'name' => 'Español (España)'],
            ['code' => 'en',    'name' => 'English'],
            ['code' => 'en_US', 'name' => 'English (US)'],
            ['code' => 'en_GB', 'name' => 'English (UK)'],
            ['code' => 'pt_BR', 'name' => 'Português (Brasil)'],
            ['code' => 'pt_PT', 'name' => 'Português (Portugal)'],
            ['code' => 'fr',    'name' => 'Français'],
            ['code' => 'de',    'name' => 'Deutsch'],
            ['code' => 'it',    'name' => 'Italiano'],
        ];

        usort($languages, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $languages;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createTemplate(array $payload): array
    {
        $config = $this->ensureConfigured();
        $body = $this->sanitizePayload($payload, true);

        return $this->request(
            'POST',
            $config['business_account_id'] . '/message_templates',
            $body,
            [],
            $config
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateTemplate(string $templateId, array $payload): array
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            throw new RuntimeException('El identificador de la plantilla es obligatorio.');
        }

        $body = $this->sanitizePayload($payload, false);

        $config = $this->ensureConfigured();

        return $this->request(
            'POST',
            'message_templates/' . $templateId,
            $body,
            [],
            $config
        );
    }

    public function deleteTemplate(string $templateId): bool
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            throw new RuntimeException('El identificador de la plantilla es obligatorio.');
        }

        $config = $this->ensureConfigured();

        $this->request('DELETE', 'message_templates/' . $templateId, null, [], $config);

        return true;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function availableCategories(): array
    {
        return [
            ['value' => 'AUTHENTICATION', 'label' => 'Autenticación'],
            ['value' => 'UTILITY', 'label' => 'Utilidad'],
            ['value' => 'MARKETING', 'label' => 'Marketing'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $templates, array $filters): array
    {
        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = strtolower((string) $filters['status']);
            $templates = array_filter($templates, static function (array $template) use ($status): bool {
                return strtolower((string) ($template['status'] ?? '')) === $status;
            });
        }

        if (isset($filters['category']) && $filters['category'] !== '') {
            $category = strtolower((string) $filters['category']);
            $templates = array_filter($templates, static function (array $template) use ($category): bool {
                return strtolower((string) ($template['category'] ?? '')) === $category;
            });
        }

        if (isset($filters['language']) && $filters['language'] !== '') {
            $language = strtolower((string) $filters['language']);
            $templates = array_filter($templates, static function (array $template) use ($language): bool {
                return strtolower((string) ($template['language'] ?? '')) === $language;
            });
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $needle = mb_strtolower((string) $filters['search']);
            $templates = array_filter($templates, static function (array $template) use ($needle): bool {
                $haystacks = [
                    (string) ($template['name'] ?? ''),
                    (string) ($template['language'] ?? ''),
                    (string) ($template['category'] ?? ''),
                    (string) ($template['status'] ?? ''),
                ];

                foreach ($haystacks as $haystack) {
                    if (mb_strpos(mb_strtolower($haystack), $needle) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload, bool $requireName): array
    {
        $result = [];

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ($name !== '') {
            $result['name'] = $name;
        } elseif ($requireName) {
            throw new RuntimeException('El nombre de la plantilla es obligatorio.');
        }

        $language = isset($payload['language']) ? trim((string) $payload['language']) : '';
        if ($language === '') {
            throw new RuntimeException('El idioma de la plantilla es obligatorio.');
        }
        $result['language'] = $language;

        $category = isset($payload['category']) ? trim((string) $payload['category']) : '';
        if ($category === '') {
            throw new RuntimeException('La categoría de la plantilla es obligatoria.');
        }
        $result['category'] = strtoupper($category);

        $components = $payload['components'] ?? [];
        if (!is_array($components)) {
            throw new RuntimeException('Los componentes de la plantilla deben enviarse como un arreglo.');
        }

        $result['components'] = array_values(array_filter($components, static function ($component): bool {
            return is_array($component) && isset($component['type']);
        }));

        if (empty($result['components'])) {
            throw new RuntimeException('La plantilla debe incluir al menos un componente válido.');
        }

        if (isset($payload['allow_category_change'])) {
            $result['allow_category_change'] = (bool) $payload['allow_category_change'];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureConfigured(): array
    {
        $config = $this->settings->get();

        if (!$config['enabled']) {
            throw new RuntimeException('WhatsApp Cloud API no está habilitado en la configuración.');
        }

        if (trim((string) $config['business_account_id']) === '') {
            throw new RuntimeException('Falta el Business Account ID de WhatsApp Cloud API.');
        }

        if (trim((string) $config['access_token']) === '') {
            throw new RuntimeException('Falta el token de acceso de WhatsApp Cloud API.');
        }

        if (trim((string) $config['api_version']) === '') {
            $config['api_version'] = 'v22.0'; // o superior estable
        }

        return $config;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = [], ?array $config = null): array
    {
        $config = $config ?? $this->ensureConfigured();

        if (trim((string) $config['api_version']) === '') {
            $config['api_version'] = 'v17.0';
        }

        $url = rtrim(self::GRAPH_BASE_URL, '/') . '/' . trim($config['api_version'], '/') . '/';
        $url .= ltrim($path, '/');

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('No fue posible iniciar la comunicación con WhatsApp Cloud API.');
        }

        $method = strtoupper($method);
        $headers = [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } elseif ($method === 'POST') {
            $options[CURLOPT_POST] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ($method !== 'GET') {
            if ($body !== null) {
                $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    curl_close($handle);
                    throw new RuntimeException('No fue posible codificar la solicitud a WhatsApp Cloud API.');
                }
                $options[CURLOPT_POSTFIELDS] = $encoded;
            } elseif ($method === 'POST') {
                $options[CURLOPT_POSTFIELDS] = '{}';
            }
        }

        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException('Error al comunicarse con WhatsApp Cloud API: ' . $error);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $response];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $decoded['error']['message'] ?? 'WhatsApp Cloud API respondió con un error.';
            $code = $decoded['error']['code'] ?? $statusCode;

            throw new RuntimeException($message, (int) $code);
        }

        return $decoded;
    }
}
