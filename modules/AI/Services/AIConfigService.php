<?php

namespace Modules\AI\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class AIConfigService
{
    public const PROVIDER_OPENAI = 'openai';

    public const FEATURE_CONSULTAS_ENFERMEDAD = 'consultas_enfermedad';
    public const FEATURE_CONSULTAS_PLAN = 'consultas_plan';

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
     *     provider: string,
     *     providers: array<string, array{
     *         api_key: string,
     *         endpoint: string,
     *         model: string,
     *         max_output_tokens: int,
     *         organization: string,
     *         headers: array<string, string>
     *     }>,
     *     features: array<string, bool>
     * }
     */
    public function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $config = [
            'provider' => '',
            'providers' => [
                self::PROVIDER_OPENAI => [
                    'api_key' => '',
                    'endpoint' => 'https://api.openai.com/v1/responses',
                    'model' => 'gpt-4o-mini',
                    'max_output_tokens' => 400,
                    'organization' => '',
                    'headers' => [],
                ],
            ],
            'features' => [
                self::FEATURE_CONSULTAS_ENFERMEDAD => true,
                self::FEATURE_CONSULTAS_PLAN => true,
            ],
        ];

        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $options = $this->settingsModel->getOptions([
                    'ai_provider',
                    'ai_openai_api_key',
                    'ai_openai_endpoint',
                    'ai_openai_model',
                    'ai_openai_max_output_tokens',
                    'ai_openai_organization',
                    'ai_enable_consultas_enfermedad',
                    'ai_enable_consultas_plan',
                ]);

                $provider = strtolower(trim((string) ($options['ai_provider'] ?? '')));
                if ($provider !== '') {
                    $config['provider'] = $provider;
                }

                $openai = &$config['providers'][self::PROVIDER_OPENAI];
                $apiKey = trim((string) ($options['ai_openai_api_key'] ?? ''));
                if ($apiKey !== '') {
                    $openai['api_key'] = $apiKey;
                }

                $endpoint = trim((string) ($options['ai_openai_endpoint'] ?? ''));
                if ($endpoint !== '') {
                    $openai['endpoint'] = $endpoint;
                }

                $model = trim((string) ($options['ai_openai_model'] ?? ''));
                if ($model !== '') {
                    $openai['model'] = $model;
                }

                $maxTokensRaw = $options['ai_openai_max_output_tokens'] ?? null;
                if ($maxTokensRaw !== null && $maxTokensRaw !== '') {
                    $maxTokens = (int) $maxTokensRaw;
                    if ($maxTokens > 0) {
                        $openai['max_output_tokens'] = $maxTokens;
                    }
                }

                $organization = trim((string) ($options['ai_openai_organization'] ?? ''));
                if ($organization !== '') {
                    $openai['organization'] = $organization;
                    $openai['headers']['OpenAI-Organization'] = $organization;
                }

                $config['features'][self::FEATURE_CONSULTAS_ENFERMEDAD] = ($options['ai_enable_consultas_enfermedad'] ?? '1') === '1';
                $config['features'][self::FEATURE_CONSULTAS_PLAN] = ($options['ai_enable_consultas_plan'] ?? '1') === '1';
            } catch (Throwable $exception) {
                error_log('No fue posible cargar la configuración de IA: ' . $exception->getMessage());
            }
        }

        $this->configCache = $config;

        return $this->configCache;
    }

    public function clearCache(): void
    {
        $this->configCache = null;
    }

    public function getActiveProvider(): string
    {
        $config = $this->getConfig();
        $provider = $config['provider'];

        if ($provider === self::PROVIDER_OPENAI && $this->isProviderConfigured(self::PROVIDER_OPENAI)) {
            return self::PROVIDER_OPENAI;
        }

        return '';
    }

    public function getProviderConfig(string $provider): array
    {
        $config = $this->getConfig();

        return $config['providers'][$provider] ?? [];
    }

    public function getOpenAIConfig(): array
    {
        return $this->getProviderConfig(self::PROVIDER_OPENAI);
    }

    public function isProviderConfigured(string $provider): bool
    {
        $config = $this->getConfig();

        if (!isset($config['providers'][$provider])) {
            return false;
        }

        if ($provider === self::PROVIDER_OPENAI) {
            $openai = $config['providers'][self::PROVIDER_OPENAI];

            return $openai['api_key'] !== '' && $openai['endpoint'] !== '';
        }

        return false;
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $config = $this->getConfig();

        return $config['features'][$feature] ?? false;
    }
}

