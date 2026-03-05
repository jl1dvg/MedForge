<?php

namespace Modules\Notifications\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class ReminderConfigService
{
    private const OPTION_ENABLED_EVENTS = 'notifications_reminder_enabled_events';
    private const OPTION_CUSTOM_RULES = 'notifications_custom_reminder_rules';
    private const OPTION_HANDOFF_ACTIONS = 'notifications_handoff_enabled_actions';
    private const OPTION_CRM_TASK_CREATED_NOTIFY = 'notifications_crm_task_creation_notify';
    private const OPTION_CRM_TASK_AUTO_REMINDER = 'notifications_crm_task_auto_reminder_enabled';
    private const OPTION_CRM_TASK_DUE_OFFSETS = 'notifications_crm_task_due_offsets';
    private const OPTION_CRM_ESCALATION_GRACE_MINUTES = 'notifications_crm_task_escalation_grace_minutes';
    private const OPTION_CRM_ESCALATION_RECIPIENTS = 'notifications_crm_task_escalation_recipients';
    private const OPTION_CRM_ESCALATION_CHANNELS = 'notifications_crm_task_escalation_channels';

    /**
     * @var array<int, string>
     */
    private const DEFAULT_ENABLED_EVENTS = [
        'preop',
        'surgery_24h',
        'surgery_2h',
        'surgery',
        'postop',
        'postconsulta',
        'exams',
        'exam',
        'crm_task',
        'crm_task_escalation',
    ];

    /**
     * @var array<int, string>
     */
    private const DEFAULT_HANDOFF_ACTIONS = [
        'requested',
        'assigned',
        'transferred',
        'requeued',
        'resolved',
    ];

    /**
     * @var array<int, int>
     */
    private const DEFAULT_CRM_TASK_DUE_OFFSETS_MINUTES = [
        -1440, // 24h antes
        -120,  // 2h antes
        0,     // al vencer
    ];

    private const DEFAULT_CRM_ESCALATION_GRACE_MINUTES = 120;

    /**
     * @var array<int, string>
     */
    private const DEFAULT_CRM_ESCALATION_RECIPIENTS = [
        'supervisors',
        'creator',
    ];

    /**
     * @var array<int, string>
     */
    private const DEFAULT_CRM_ESCALATION_CHANNELS = [
        'in_app',
        'email',
    ];

    private ?SettingsModel $settingsModel = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $optionsCache = null;

    public function __construct(PDO $pdo)
    {
        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
        } catch (Throwable $exception) {
            $this->settingsModel = null;
            error_log('No fue posible inicializar SettingsModel para ReminderConfigService: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    public function getEnabledReminderEvents(): array
    {
        $options = $this->loadOptions();

        return $this->decodeJsonList(
            $options[self::OPTION_ENABLED_EVENTS] ?? null,
            self::DEFAULT_ENABLED_EVENTS,
            self::DEFAULT_ENABLED_EVENTS
        );
    }

    public function isReminderEventEnabled(string $eventKey): bool
    {
        $normalized = $this->normalizeToken($eventKey);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->getEnabledReminderEvents(), true);
    }

    /**
     * @return array<string, array{
     *     id: string,
     *     event: string,
     *     label: string,
     *     context: string,
     *     source: string,
     *     min_offset_hours: float,
     *     max_offset_hours: float
     * }>
     */
    public function getCustomReminderRules(): array
    {
        $options = $this->loadOptions();

        return $this->decodeCustomRules($options[self::OPTION_CUSTOM_RULES] ?? null);
    }

    /**
     * @return array<int, string>
     */
    public function getEnabledHandoffActions(): array
    {
        $options = $this->loadOptions();

        return $this->decodeJsonList(
            $options[self::OPTION_HANDOFF_ACTIONS] ?? null,
            self::DEFAULT_HANDOFF_ACTIONS,
            self::DEFAULT_HANDOFF_ACTIONS
        );
    }

    public function isHandoffActionEnabled(string $action): bool
    {
        $normalized = $this->normalizeToken($action);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->getEnabledHandoffActions(), true);
    }

    public function isCrmTaskCreationNotificationEnabled(): bool
    {
        $options = $this->loadOptions();

        return $this->decodeBoolean(
            $options[self::OPTION_CRM_TASK_CREATED_NOTIFY] ?? null,
            true
        );
    }

    public function isCrmTaskAutoReminderEnabled(): bool
    {
        $options = $this->loadOptions();

        return $this->decodeBoolean(
            $options[self::OPTION_CRM_TASK_AUTO_REMINDER] ?? null,
            true
        );
    }

    /**
     * @return array<int, int>
     */
    public function getCrmTaskDueReminderOffsetsMinutes(): array
    {
        $options = $this->loadOptions();

        return $this->decodeOffsetMinutes(
            $options[self::OPTION_CRM_TASK_DUE_OFFSETS] ?? null,
            self::DEFAULT_CRM_TASK_DUE_OFFSETS_MINUTES
        );
    }

    public function getCrmTaskEscalationGraceMinutes(): int
    {
        $options = $this->loadOptions();
        $raw = $options[self::OPTION_CRM_ESCALATION_GRACE_MINUTES] ?? null;
        if (!is_numeric($raw)) {
            return self::DEFAULT_CRM_ESCALATION_GRACE_MINUTES;
        }

        return max(0, (int) $raw);
    }

    /**
     * @return array<int, string>
     */
    public function getCrmTaskEscalationRecipients(): array
    {
        $options = $this->loadOptions();

        return $this->decodeJsonList(
            $options[self::OPTION_CRM_ESCALATION_RECIPIENTS] ?? null,
            self::DEFAULT_CRM_ESCALATION_RECIPIENTS,
            ['supervisors', 'creator', 'assignee']
        );
    }

    /**
     * @return array<int, string>
     */
    public function getCrmTaskEscalationChannels(): array
    {
        $options = $this->loadOptions();

        return $this->decodeJsonList(
            $options[self::OPTION_CRM_ESCALATION_CHANNELS] ?? null,
            self::DEFAULT_CRM_ESCALATION_CHANNELS,
            ['in_app', 'email']
        );
    }

    public function isCrmTaskEscalationChannelEnabled(string $channel): bool
    {
        $normalized = $this->normalizeToken($channel);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->getCrmTaskEscalationChannels(), true);
    }

    /**
     * @return array<string, string>
     */
    private function loadOptions(): array
    {
        if ($this->optionsCache !== null) {
            return $this->optionsCache;
        }

        $options = [];
        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $options = $this->settingsModel->getOptions([
                    self::OPTION_ENABLED_EVENTS,
                    self::OPTION_CUSTOM_RULES,
                    self::OPTION_HANDOFF_ACTIONS,
                    self::OPTION_CRM_TASK_CREATED_NOTIFY,
                    self::OPTION_CRM_TASK_AUTO_REMINDER,
                    self::OPTION_CRM_TASK_DUE_OFFSETS,
                    self::OPTION_CRM_ESCALATION_GRACE_MINUTES,
                    self::OPTION_CRM_ESCALATION_RECIPIENTS,
                    self::OPTION_CRM_ESCALATION_CHANNELS,
                ]);
            } catch (Throwable $exception) {
                error_log('No fue posible cargar opciones de reminders: ' . $exception->getMessage());
            }
        }

        if (!is_array($options)) {
            $options = [];
        }

        $this->optionsCache = array_map(static fn($value): string => (string) $value, $options);

        return $this->optionsCache;
    }

    /**
     * @param mixed $raw
     * @param array<int, string> $default
     * @param array<int, string> $allowed
     * @return array<int, string>
     */
    private function decodeJsonList($raw, array $default, array $allowed = []): array
    {
        $explicitInput = false;
        $items = null;

        if (is_array($raw)) {
            $items = $raw;
            $explicitInput = true;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $explicitInput = true;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\s,;\n\r\t]+/', $raw) ?: [];
            }
        }

        if (!is_array($items)) {
            $items = $default;
            $explicitInput = false;
        }

        $normalized = [];
        foreach ($items as $item) {
            $token = $this->normalizeToken((string) $item);
            if ($token === '') {
                continue;
            }
            $normalized[$token] = $token;
        }

        if ($normalized === []) {
            if ($explicitInput) {
                return [];
            }

            return $default;
        }

        if ($allowed !== []) {
            $allowedMap = [];
            foreach ($allowed as $allowedItem) {
                $token = $this->normalizeToken($allowedItem);
                if ($token === '') {
                    continue;
                }
                $allowedMap[$token] = true;
            }

            $filtered = array_intersect_key($normalized, $allowedMap);
            if ($filtered === []) {
                return $default;
            }

            return array_values($filtered);
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $raw
     * @return array<string, array{
     *     id: string,
     *     event: string,
     *     label: string,
     *     context: string,
     *     source: string,
     *     min_offset_hours: float,
     *     max_offset_hours: float
     * }>
     */
    private function decodeCustomRules($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rules = [];
        $index = 0;
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $enabled = $entry['enabled'] ?? true;
            if (is_bool($enabled)) {
                if ($enabled === false) {
                    continue;
                }
            } else {
                $enabledValue = filter_var((string) $enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($enabledValue === false) {
                    continue;
                }
            }

            $event = trim((string) ($entry['event'] ?? ''));
            if ($event === '') {
                continue;
            }

            $id = $this->normalizeToken((string) ($entry['id'] ?? ''));
            if ($id === '') {
                $index++;
                $id = 'custom_rule_' . $index;
            }

            $source = $this->normalizeToken((string) ($entry['source'] ?? 'scheduled'));
            if (!in_array($source, ['scheduled', 'expiration'], true)) {
                $source = 'scheduled';
            }

            $min = is_numeric($entry['min_offset_hours'] ?? null)
                ? (float) $entry['min_offset_hours']
                : 0.0;
            $max = is_numeric($entry['max_offset_hours'] ?? null)
                ? (float) $entry['max_offset_hours']
                : 24.0;
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                $label = $id;
            }

            $rules[$id] = [
                'id' => $id,
                'event' => $event,
                'label' => $label,
                'context' => trim((string) ($entry['context'] ?? '')),
                'source' => $source,
                'min_offset_hours' => $min,
                'max_offset_hours' => $max,
            ];
        }

        return $rules;
    }

    /**
     * @param mixed $raw
     * @param array<int, int> $default
     * @return array<int, int>
     */
    private function decodeOffsetMinutes($raw, array $default): array
    {
        $explicitInput = false;
        $items = null;

        if (is_array($raw)) {
            $items = $raw;
            $explicitInput = true;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $explicitInput = true;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\s,;\n\r\t]+/', $raw) ?: [];
            }
        }

        if (!is_array($items)) {
            $items = $default;
            $explicitInput = false;
        }

        $normalized = [];
        foreach ($items as $item) {
            $minutes = $this->parseOffsetToMinutes((string) $item);
            if ($minutes === null) {
                continue;
            }
            $normalized[$minutes] = $minutes;
        }

        if ($normalized === []) {
            if ($explicitInput) {
                return [];
            }

            return $default;
        }

        $values = array_values($normalized);
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * @param mixed $raw
     */
    private function decodeBoolean($raw, bool $default): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return ((int) $raw) !== 0;
        }

        if (is_string($raw)) {
            $value = trim($raw);
            if ($value === '') {
                return $default;
            }

            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed === null) {
                return $default;
            }

            return $parsed;
        }

        return $default;
    }

    private function parseOffsetToMinutes(string $value): ?int
    {
        $token = strtolower(trim($value));
        if ($token === '') {
            return null;
        }

        if (!preg_match('/^([+-]?\d+)\s*([dhm])?$/', $token, $matches)) {
            return null;
        }

        $amount = (int) ($matches[1] ?? 0);
        $unit = strtolower((string) ($matches[2] ?? 'h'));

        $multiplier = 60;
        if ($unit === 'm') {
            $multiplier = 1;
        } elseif ($unit === 'd') {
            $multiplier = 1440;
        }

        return $amount * $multiplier;
    }

    private function normalizeToken(string $value): string
    {
        $token = strtolower(trim($value));
        $token = preg_replace('/[^a-z0-9_.-]+/', '_', $token);

        return is_string($token) ? trim($token, '_') : '';
    }
}
