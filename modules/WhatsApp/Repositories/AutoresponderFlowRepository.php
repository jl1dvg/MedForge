<?php

namespace Modules\WhatsApp\Repositories;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class AutoresponderFlowRepository
{
    private const OPTION_KEY = 'whatsapp_autoresponder_flow';
    private const DEFAULT_FLOW_KEY = 'default';

    private PDO $pdo;
    private ?SettingsModel $settings = null;
    private string $fallbackPath;
    private bool $hasFlowTables;
    private bool $hasStructureTables;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->fallbackPath = BASE_PATH . '/storage/whatsapp_autoresponder_flow.json';
        $this->hasFlowTables = $this->detectFlowTables();
        $this->hasStructureTables = $this->hasFlowTables && $this->detectStructureTables();

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
        if ($this->hasFlowTables) {
            $fromTables = $this->loadFromFlowTables();
            if ($fromTables !== null) {
                return $fromTables;
            }
        }

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

        if ($this->hasFlowTables) {
            try {
                if ($this->saveToFlowTables($flow)) {
                    $this->saveToFallback($encoded);

                    return true;
                }
            } catch (Throwable $exception) {
                error_log('No fue posible persistir el flujo en las tablas dedicadas: ' . $exception->getMessage());
            }
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

    private function detectFlowTables(): bool
    {
        return $this->tableExists('whatsapp_autoresponder_flows')
            && $this->tableExists('whatsapp_autoresponder_flow_versions');
    }

    private function detectStructureTables(): bool
    {
        $required = [
            'whatsapp_autoresponder_steps',
            'whatsapp_autoresponder_step_actions',
            'whatsapp_autoresponder_step_transitions',
            'whatsapp_autoresponder_version_filters',
            'whatsapp_autoresponder_schedules',
        ];

        foreach ($required as $table) {
            if (!$this->tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFromFlowTables(): ?array
    {
        $sql = <<<'SQL'
SELECT fv.entry_settings
FROM whatsapp_autoresponder_flow_versions fv
JOIN whatsapp_autoresponder_flows f ON f.id = fv.flow_id
WHERE f.flow_key = :flow_key AND (
    f.active_version_id = fv.id OR f.active_version_id IS NULL
)
ORDER BY (f.active_version_id = fv.id) DESC, fv.version DESC
LIMIT 1
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':flow_key' => self::DEFAULT_FLOW_KEY]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $entrySettings = $row['entry_settings'] ?? null;
        if (!is_string($entrySettings) || $entrySettings === '') {
            return null;
        }

        $decoded = json_decode($entrySettings, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['flow']) && is_array($decoded['flow'])) {
            return $decoded['flow'];
        }

        if (isset($decoded['config']) && is_array($decoded['config'])) {
            return $decoded['config'];
        }

        if (isset($decoded['scenarios']) && is_array($decoded['scenarios'])) {
            return $decoded;
        }

        return null;
    }

    private function saveToFlowTables(array $flow): bool
    {
        $this->pdo->beginTransaction();

        try {
            $flowRow = $this->resolveFlowRow();
            $flowId = (int) $flowRow['id'];

            $version = $this->nextVersionNumber($flowId);

            $payload = json_encode(['flow' => $flow], JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                throw new RuntimeException('No fue posible serializar el flujo para guardar la versión.');
            }

            $insertVersion = $this->pdo->prepare(
                'INSERT INTO whatsapp_autoresponder_flow_versions '
                . '(flow_id, version, status, entry_settings, created_at, updated_at) '
                . 'VALUES (:flow_id, :version, :status, :entry_settings, NOW(), NOW())'
            );

            $insertVersion->execute([
                ':flow_id' => $flowId,
                ':version' => $version,
                ':status' => 'published',
                ':entry_settings' => $payload,
            ]);

            $versionId = (int) $this->pdo->lastInsertId();

            $updateFlow = $this->pdo->prepare(
                'UPDATE whatsapp_autoresponder_flows SET active_version_id = :version_id, status = :status, updated_at = NOW() '
                . 'WHERE id = :flow_id'
            );
            $updateFlow->execute([
                ':version_id' => $versionId,
                ':status' => 'active',
                ':flow_id' => $flowId,
            ]);

            $archivePrevious = $this->pdo->prepare(
                'UPDATE whatsapp_autoresponder_flow_versions SET status = :status WHERE flow_id = :flow_id AND id <> :version_id'
            );
            $archivePrevious->execute([
                ':status' => 'archived',
                ':flow_id' => $flowId,
                ':version_id' => $versionId,
            ]);

            $this->syncFlowStructure($versionId, $flow);

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            error_log('No fue posible guardar la versión del flujo de autorespuesta: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFlowRow(): array
    {
        $select = $this->pdo->prepare(
            'SELECT id, active_version_id FROM whatsapp_autoresponder_flows WHERE flow_key = :flow_key LIMIT 1'
        );
        $select->execute([':flow_key' => self::DEFAULT_FLOW_KEY]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_flows '
            . '(flow_key, name, description, status, created_at, updated_at) '
            . 'VALUES (:flow_key, :name, :description, :status, NOW(), NOW())'
        );
        $insert->execute([
            ':flow_key' => self::DEFAULT_FLOW_KEY,
            ':name' => 'Flujo principal de WhatsApp',
            ':description' => 'Configuración del flujo de autorespuesta gestionada desde el editor web.',
            ':status' => 'draft',
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'active_version_id' => null,
        ];
    }

    /**
     * @param array<string, mixed> $flow
     */
    private function syncFlowStructure(int $versionId, array $flow): void
    {
        if (!$this->hasStructureTables) {
            return;
        }

        $this->purgeFlowStructure($versionId);

        $scenarios = $flow['scenarios'] ?? null;
        if (!is_array($scenarios) || $scenarios === []) {
            return;
        }

        $insertStep = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_steps '
            . '(flow_version_id, step_key, step_type, name, description, order_index, is_entry_point, settings, created_at, updated_at) '
            . 'VALUES (:version_id, :step_key, :step_type, :name, :description, :order_index, :is_entry_point, :settings, NOW(), NOW())'
        );

        $insertAction = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_step_actions '
            . '(step_id, action_type, template_revision_id, message_body, media_url, delay_seconds, metadata, order_index, created_at, updated_at) '
            . 'VALUES (:step_id, :action_type, :template_revision_id, :message_body, :media_url, :delay_seconds, :metadata, :order_index, NOW(), NOW())'
        );

        $insertTransition = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_step_transitions '
            . '(step_id, target_step_id, condition_label, condition_type, condition_payload, priority, created_at, updated_at) '
            . 'VALUES (:step_id, :target_step_id, :condition_label, :condition_type, :condition_payload, :priority, NOW(), NOW())'
        );

        $steps = [];

        foreach ($scenarios as $index => $scenario) {
            if (!is_array($scenario)) {
                continue;
            }

            $stage = $this->resolveStageValue($scenario);
            $stepKey = $this->resolveScenarioKey($scenario, $index);
            $stepType = $this->resolveStepType($scenario, $stage);

            $name = $this->stringValue($scenario['name'] ?? '');
            if ($name === '') {
                $name = 'Escenario ' . ($index + 1);
            }
            $name = $this->truncate($name, 191);

            $description = $this->stringValue($scenario['description'] ?? '');
            if ($description !== '') {
                $description = $this->truncate($description, 500);
            }

            $settingsPayload = json_encode([
                'scenario' => $scenario,
                'stage' => $stage,
                'exported_at' => gmdate('c'),
            ], JSON_UNESCAPED_UNICODE);
            if ($settingsPayload === false) {
                $settingsPayload = json_encode([
                    'scenario_id' => $stepKey,
                    'stage' => $stage,
                    'exported_at' => gmdate('c'),
                ], JSON_UNESCAPED_UNICODE);
            }

            $insertStep->execute([
                ':version_id' => $versionId,
                ':step_key' => $stepKey,
                ':step_type' => $stepType,
                ':name' => $name,
                ':description' => $description === '' ? null : $description,
                ':order_index' => $index,
                ':is_entry_point' => (!empty($scenario['intercept_menu']) || $stage === 'arrival') ? 1 : 0,
                ':settings' => $settingsPayload,
            ]);

            $stepId = (int) $this->pdo->lastInsertId();
            $steps[] = [
                'id' => $stepId,
                'stage' => $stage,
                'scenario' => $scenario,
                'index' => $index,
            ];

            $actions = $scenario['actions'] ?? [];
            if (!is_array($actions) || $actions === []) {
                continue;
            }

            $orderIndex = 0;
            foreach ($actions as $action) {
                if (!is_array($action)) {
                    continue;
                }

                $normalized = $this->normalizeActionForStorage($action);
                if ($normalized === null) {
                    continue;
                }

                $insertAction->execute([
                    ':step_id' => $stepId,
                    ':action_type' => $normalized['action_type'],
                    ':template_revision_id' => $normalized['template_revision_id'],
                    ':message_body' => $normalized['message_body'],
                    ':media_url' => $normalized['media_url'],
                    ':delay_seconds' => $normalized['delay_seconds'],
                    ':metadata' => $normalized['metadata'],
                    ':order_index' => $orderIndex,
                ]);

                $orderIndex++;
            }
        }

        $stepCount = count($steps);
        if ($stepCount < 2) {
            return;
        }

        for ($i = 0; $i < $stepCount - 1; $i++) {
            $current = $steps[$i];
            $next = $steps[$i + 1];

            $insertTransition->execute([
                ':step_id' => $current['id'],
                ':target_step_id' => $next['id'],
                ':condition_label' => null,
                ':condition_type' => 'always',
                ':condition_payload' => null,
                ':priority' => $i,
            ]);
        }
    }

    private function purgeFlowStructure(int $versionId): void
    {
        $deleteSteps = $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_steps WHERE flow_version_id = :version_id'
        );
        $deleteSteps->execute([':version_id' => $versionId]);

        $deleteFilters = $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_version_filters WHERE flow_version_id = :version_id'
        );
        $deleteFilters->execute([':version_id' => $versionId]);

        $deleteSchedules = $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_schedules WHERE flow_version_id = :version_id'
        );
        $deleteSchedules->execute([':version_id' => $versionId]);
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function resolveScenarioKey(array $scenario, int $index): string
    {
        $candidate = $this->stringValue($scenario['id'] ?? '');
        if ($candidate === '') {
            $candidate = 'scenario_' . ($index + 1);
        }

        $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $candidate) ?? $candidate;
        $normalized = strtolower(trim($normalized, '_-'));

        if ($normalized === '') {
            $normalized = 'scenario_' . ($index + 1);
        }

        return $this->truncate($normalized, 100);
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function resolveStageValue(array $scenario): string
    {
        $value = $this->stringValue($scenario['stage'] ?? ($scenario['stage_id'] ?? ($scenario['stageId'] ?? '')));
        $value = strtolower($value);

        $allowed = ['arrival', 'validation', 'consent', 'menu', 'scheduling', 'results', 'post', 'custom'];

        return in_array($value, $allowed, true) ? $value : 'custom';
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function resolveStepType(array $scenario, string $stage): string
    {
        if (!empty($scenario['intercept_menu']) || $stage === 'arrival') {
            return 'trigger';
        }

        if ($stage === 'validation') {
            return 'condition';
        }

        return 'message';
    }

    /**
     * @param array<string, mixed> $action
     * @return array{action_type: string, template_revision_id: ?int, message_body: ?string, media_url: ?string, delay_seconds: int, metadata: ?string}|null
     */
    private function normalizeActionForStorage(array $action): ?array
    {
        $type = strtolower($this->stringValue($action['type'] ?? ''));
        if ($type === '') {
            $type = 'send_message';
        }

        $messageBody = null;
        $mediaUrl = null;
        $delay = 0;
        $templateRevisionId = null;
        $metadata = ['original_type' => $type];

        switch ($type) {
            case 'send_message':
            case 'send_buttons':
            case 'send_list':
                $mapped = 'send_session_message';
                $message = is_array($action['message'] ?? null) ? $action['message'] : [];
                $metadata['message'] = $message;
                if (isset($message['body']) && is_string($message['body'])) {
                    $messageBody = $this->truncate($message['body'], 500);
                }
                if (isset($message['link']) && is_string($message['link'])) {
                    $mediaUrl = $this->truncate($message['link'], 500);
                }
                break;
            case 'send_sequence':
                $mapped = 'send_session_message';
                $messages = is_array($action['messages'] ?? null) ? array_values($action['messages']) : [];
                $metadata['messages'] = $messages;
                if (!empty($messages) && is_array($messages[0])) {
                    $first = $messages[0];
                    if (isset($first['body']) && is_string($first['body'])) {
                        $messageBody = $this->truncate($first['body'], 500);
                    }
                    if (isset($first['link']) && is_string($first['link'])) {
                        $mediaUrl = $this->truncate($first['link'], 500);
                    }
                }
                break;
            case 'send_template':
                $mapped = 'send_template';
                $metadata['template'] = $action['template'] ?? null;
                break;
            case 'wait':
                $mapped = 'wait';
                $delay = isset($action['seconds']) && is_numeric($action['seconds'])
                    ? max(0, (int) $action['seconds'])
                    : 0;
                $metadata['seconds'] = $delay;
                break;
            case 'handoff':
                $mapped = 'handoff';
                $metadata['payload'] = $action;
                break;
            case 'assign_tag':
                $mapped = 'assign_tag';
                $metadata['payload'] = $action;
                break;
            case 'remove_tag':
                $mapped = 'remove_tag';
                $metadata['payload'] = $action;
                break;
            case 'webhook':
                $mapped = 'webhook';
                $metadata['payload'] = $action;
                break;
            case 'mark_opt_out':
                $mapped = 'mark_opt_out';
                $metadata['payload'] = $action;
                break;
            case 'set_state':
            case 'set_context':
            case 'store_consent':
            case 'lookup_patient':
            case 'conditional':
            case 'goto_menu':
            case 'upsert_patient_from_context':
            case 'update_field':
                $mapped = 'update_field';
                $metadata['payload'] = $action;
                break;
            default:
                return null;
        }

        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        if ($encodedMetadata === false) {
            $encodedMetadata = null;
        }

        return [
            'action_type' => $mapped,
            'template_revision_id' => $templateRevisionId,
            'message_body' => $messageBody,
            'media_url' => $mediaUrl,
            'delay_seconds' => $delay,
            'metadata' => $encodedMetadata,
        ];
    }

    /**
     * @param mixed $value
     */
    private function stringValue($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function truncate(string $value, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($value) <= $length) {
                return $value;
            }

            return mb_substr($value, 0, $length);
        }

        if (strlen($value) <= $length) {
            return $value;
        }

        return substr($value, 0, $length);
    }

    private function nextVersionNumber(int $flowId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(version) FROM whatsapp_autoresponder_flow_versions WHERE flow_id = :flow_id'
        );
        $stmt->execute([':flow_id' => $flowId]);
        $current = (int) $stmt->fetchColumn();

        return $current + 1;
    }
}
