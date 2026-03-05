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

    private function normalizeToken(string $value): string
    {
        $token = strtolower(trim($value));
        $token = preg_replace('/[^a-z0-9_.-]+/', '_', $token);

        return is_string($token) ? trim($token, '_') : '';
    }
}
