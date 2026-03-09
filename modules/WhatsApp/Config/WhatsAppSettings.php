<?php

namespace Modules\WhatsApp\Config;

use Models\SettingsModel;
use Modules\Autoresponder\Repositories\AutoresponderFlowRepository;
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
     *     data_protection_flow: array<string, mixed>,
     *     template_languages: string,
     *     template_queue_days: int,
     *     chat_group_gap_minutes: int,
     *     chat_require_assignment_to_reply: bool,
     *     handoff_ttl_hours: int,
     *     handoff_default_role_id: int,
     *     handoff_sla_target_minutes: int,
     *     handoff_notify_in_app: bool,
     *     handoff_notify_agents: bool,
     *     handoff_escalation_enabled: bool,
     *     handoff_escalation_minutes: int,
     *     handoff_escalation_role_id: int,
     *     handoff_escalation_notify_in_app: bool,
     *     handoff_escalation_notify_agents: bool,
     *     handoff_agent_message: string,
     *     handoff_button_take_label: string,
     *     handoff_button_ignore_label: string,
     *     autoresponder_action_catalog: array<int, array{value:string,label:string,help:string,simple:bool}>
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
            'template_languages' => '',
            'template_queue_days' => 30,
            'chat_group_gap_minutes' => 8,
            'chat_require_assignment_to_reply' => true,
            'handoff_ttl_hours' => 24,
            'handoff_default_role_id' => 0,
            'handoff_sla_target_minutes' => 15,
            'handoff_notify_in_app' => true,
            'handoff_notify_agents' => false,
            'handoff_escalation_enabled' => true,
            'handoff_escalation_minutes' => 30,
            'handoff_escalation_role_id' => 0,
            'handoff_escalation_notify_in_app' => true,
            'handoff_escalation_notify_agents' => false,
            'handoff_agent_message' => "Paciente {{contact}} necesita asistencia.\nToca para tomar ✅\n\nNota: {{notes}}",
            'handoff_button_take_label' => 'Tomar',
            'handoff_button_ignore_label' => 'Ignorar',
            'autoresponder_action_catalog' => self::defaultAutoresponderActionCatalog(),
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
                    'whatsapp_template_languages',
                    'whatsapp_chat_template_queue_days',
                    'whatsapp_chat_group_gap_minutes',
                    'whatsapp_chat_require_assignment_to_reply',
                    'whatsapp_handoff_ttl_hours',
                    'whatsapp_handoff_default_role_id',
                    'whatsapp_handoff_sla_target_minutes',
                    'whatsapp_handoff_notify_in_app',
                    'whatsapp_handoff_notify_agents',
                    'whatsapp_handoff_escalation_enabled',
                    'whatsapp_handoff_escalation_minutes',
                    'whatsapp_handoff_escalation_role_id',
                    'whatsapp_handoff_escalation_notify_in_app',
                    'whatsapp_handoff_escalation_notify_agents',
                    'whatsapp_handoff_agent_message',
                    'whatsapp_handoff_button_take_label',
                    'whatsapp_handoff_button_ignore_label',
                    'whatsapp_autoresponder_action_catalog',
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

                $templateLanguages = $options['whatsapp_template_languages'] ?? '';
                if (is_string($templateLanguages) && trim($templateLanguages) !== '') {
                    $config['template_languages'] = $templateLanguages;
                }

                $templateQueueDays = (int) ($options['whatsapp_chat_template_queue_days'] ?? 30);
                $config['template_queue_days'] = max(0, min(365, $templateQueueDays));

                $chatGroupGapMinutes = (int) ($options['whatsapp_chat_group_gap_minutes'] ?? 8);
                $config['chat_group_gap_minutes'] = max(0, min(120, $chatGroupGapMinutes));
                $config['chat_require_assignment_to_reply'] = ($options['whatsapp_chat_require_assignment_to_reply'] ?? '1') === '1';

                $ttl = (int) ($options['whatsapp_handoff_ttl_hours'] ?? 24);
                if ($ttl > 0) {
                    $config['handoff_ttl_hours'] = $ttl;
                }
                $handoffDefaultRoleId = (int) ($options['whatsapp_handoff_default_role_id'] ?? 0);
                $config['handoff_default_role_id'] = max(0, $handoffDefaultRoleId);

                $slaTarget = (int) ($options['whatsapp_handoff_sla_target_minutes'] ?? 15);
                if ($slaTarget > 0) {
                    $config['handoff_sla_target_minutes'] = min(1440, $slaTarget);
                }

                $config['handoff_notify_in_app'] = ($options['whatsapp_handoff_notify_in_app'] ?? '1') === '1';
                $config['handoff_notify_agents'] = ($options['whatsapp_handoff_notify_agents'] ?? '0') === '1';
                $config['handoff_escalation_enabled'] = ($options['whatsapp_handoff_escalation_enabled'] ?? '1') === '1';

                $handoffEscalationMinutes = (int) ($options['whatsapp_handoff_escalation_minutes'] ?? 30);
                if ($handoffEscalationMinutes > 0) {
                    $config['handoff_escalation_minutes'] = max(5, min(1440, $handoffEscalationMinutes));
                }

                $handoffEscalationRoleId = (int) ($options['whatsapp_handoff_escalation_role_id'] ?? 0);
                $config['handoff_escalation_role_id'] = max(0, $handoffEscalationRoleId);
                $config['handoff_escalation_notify_in_app'] = ($options['whatsapp_handoff_escalation_notify_in_app'] ?? '1') === '1';
                $config['handoff_escalation_notify_agents'] = ($options['whatsapp_handoff_escalation_notify_agents'] ?? '0') === '1';

                $agentMessage = trim((string) ($options['whatsapp_handoff_agent_message'] ?? ''));
                if ($agentMessage !== '') {
                    $config['handoff_agent_message'] = $agentMessage;
                }

                $takeLabel = trim((string) ($options['whatsapp_handoff_button_take_label'] ?? ''));
                if ($takeLabel !== '') {
                    $config['handoff_button_take_label'] = $takeLabel;
                }

                $ignoreLabel = trim((string) ($options['whatsapp_handoff_button_ignore_label'] ?? ''));
                if ($ignoreLabel !== '') {
                    $config['handoff_button_ignore_label'] = $ignoreLabel;
                }

                $actionCatalog = $this->parseActionCatalogOption($options['whatsapp_autoresponder_action_catalog'] ?? null);
                if ($actionCatalog !== []) {
                    $config['autoresponder_action_catalog'] = $actionCatalog;
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
     * @return array<int, array{value:string,label:string,help:string,simple:bool}>
     */
    public static function defaultAutoresponderActionCatalog(): array
    {
        return [
            ['value' => 'send_message', 'label' => 'Enviar mensaje o multimedia', 'help' => 'Entrega un mensaje simple, imagen, documento o ubicación.', 'simple' => true],
            ['value' => 'send_sequence', 'label' => 'Enviar secuencia de mensajes', 'help' => 'Combina varios mensajes consecutivos en una sola acción.', 'simple' => false],
            ['value' => 'send_buttons', 'label' => 'Enviar botones', 'help' => 'Presenta botones interactivos para guiar la respuesta.', 'simple' => true],
            ['value' => 'send_list', 'label' => 'Enviar lista interactiva', 'help' => 'Muestra un menú desplegable con secciones y múltiples opciones.', 'simple' => false],
            ['value' => 'send_template', 'label' => 'Enviar plantilla aprobada', 'help' => 'Usa una plantilla autorizada por Meta con variables predefinidas.', 'simple' => false],
            ['value' => 'set_state', 'label' => 'Actualizar estado', 'help' => 'Actualiza el estado del flujo para controlar próximos pasos.', 'simple' => true],
            ['value' => 'set_context', 'label' => 'Guardar en contexto', 'help' => 'Almacena pares clave-valor disponibles en mensajes futuros.', 'simple' => false],
            ['value' => 'store_consent', 'label' => 'Guardar consentimiento', 'help' => 'Registra si el paciente aceptó o rechazó la autorización.', 'simple' => true],
            ['value' => 'lookup_patient', 'label' => 'Validar cédula en BD', 'help' => 'Busca al paciente usando la cédula o historia clínica proporcionada.', 'simple' => true],
            ['value' => 'handoff_agent', 'label' => 'Derivar a agente', 'help' => 'Marca la conversación para atención humana y define el equipo responsable.', 'simple' => true],
            ['value' => 'conditional', 'label' => 'Condicional', 'help' => 'Divide el flujo en acciones alternativas según una condición.', 'simple' => false],
            ['value' => 'goto_menu', 'label' => 'Redirigir al menú', 'help' => 'Envía nuevamente el mensaje de menú configurado más abajo.', 'simple' => true],
            ['value' => 'upsert_patient_from_context', 'label' => 'Guardar paciente con datos actuales', 'help' => 'Crea o actualiza el paciente con los datos capturados en contexto.', 'simple' => false],
        ];
    }

    /**
     * @return array<int, array{value:string,label:string,help:string,simple:bool}>
     */
    private function parseActionCatalogOption(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $defaults = self::defaultAutoresponderActionCatalog();
        $allowed = [];
        $defaultByValue = [];
        foreach ($defaults as $entry) {
            $value = (string) ($entry['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $allowed[$value] = true;
            $defaultByValue[$value] = $entry;
        }

        $catalog = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $value = trim((string) ($entry['value'] ?? ''));
            if ($value === '' || empty($allowed[$value])) {
                continue;
            }

            $fallback = $defaultByValue[$value] ?? ['label' => $value, 'help' => '', 'simple' => false];
            $label = trim((string) ($entry['label'] ?? $fallback['label']));
            $help = trim((string) ($entry['help'] ?? $fallback['help']));
            $simple = isset($entry['simple']) ? (bool) $entry['simple'] : (bool) $fallback['simple'];

            $catalog[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : (string) $fallback['label'],
                'help' => $help !== '' ? $help : (string) $fallback['help'],
                'simple' => $simple,
            ];
        }

        return $catalog;
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
