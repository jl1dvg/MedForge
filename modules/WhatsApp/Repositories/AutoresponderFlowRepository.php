<?php

namespace Modules\WhatsApp\Repositories;

use Models\SettingsModel;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class AutoresponderFlowRepository
{
    private const OPTION_KEY = 'whatsapp_autoresponder_flow';
    private const DEFAULT_FLOW_KEY = 'primary';

    private PDO $pdo;
    private ?SettingsModel $settings = null;
    private string $fallbackPath;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->fallbackPath = BASE_PATH . '/storage/whatsapp_autoresponder_flow.json';

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
        $fromDatabase = $this->loadActive();
        if (!empty($fromDatabase)) {
            return $fromDatabase;
        }

        $legacy = $this->loadFromLegacyOption();
        if (!empty($legacy)) {
            return $legacy;
        }

        return $this->loadFromFallback();
    }

    /**
     * @return array<string, mixed>
     */
    public function loadActive(): array
    {
        try {
            $query = $this->pdo->prepare(
                'SELECT v.entry_settings AS settings
                 FROM whatsapp_autoresponder_flows f
                 INNER JOIN whatsapp_autoresponder_flow_versions v ON v.id = f.active_version_id
                 WHERE f.flow_key = :flow_key
                 LIMIT 1'
            );
            $query->execute(['flow_key' => self::DEFAULT_FLOW_KEY]);
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if ($row && isset($row['settings'])) {
                $decoded = json_decode((string) $row['settings'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            $fallbackQuery = $this->pdo->prepare(
                'SELECT v.entry_settings AS settings
                 FROM whatsapp_autoresponder_flows f
                 INNER JOIN whatsapp_autoresponder_flow_versions v ON v.flow_id = f.id
                 WHERE f.flow_key = :flow_key
                 ORDER BY (v.status = "published") DESC, v.version DESC
                 LIMIT 1'
            );
            $fallbackQuery->execute(['flow_key' => self::DEFAULT_FLOW_KEY]);
            $row = $fallbackQuery->fetch(PDO::FETCH_ASSOC);

            if ($row && isset($row['settings'])) {
                $decoded = json_decode((string) $row['settings'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Throwable $exception) {
            error_log('No fue posible cargar el flujo activo de autorespuesta desde la base de datos: ' . $exception->getMessage());
        }

        return [];
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

        $stored = $this->persistToDatabase($flow, $encoded);

        $this->persistLegacyOption($encoded);
        $this->saveToFallback($encoded);

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromLegacyOption(): array
    {
        if (!$this->settings instanceof SettingsModel) {
            return [];
        }

        try {
            $raw = $this->settings->getOption(self::OPTION_KEY);
        } catch (Throwable $exception) {
            error_log('No fue posible cargar el flujo de autorespuesta legado: ' . $exception->getMessage());

            return [];
        }

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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

    private function persistLegacyOption(string $encoded): void
    {
        if (!$this->settings instanceof SettingsModel) {
            return;
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
            error_log('No fue posible sincronizar el flujo de autorespuesta legado: ' . $exception->getMessage());
        }
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

    private function persistToDatabase(array $flow, string $encoded): bool
    {
        try {
            $this->pdo->beginTransaction();

            $flowId = $this->upsertFlow($flow);
            if ($flowId === null) {
                $this->pdo->rollBack();

                return false;
            }

            $versionNumber = $this->nextVersionNumber($flowId);
            $userId = $this->currentUserId();

            $insertVersion = $this->pdo->prepare(
                'INSERT INTO whatsapp_autoresponder_flow_versions (
                    flow_id, version, status, changelog, audience_filters, entry_settings,
                    published_at, published_by, created_by
                ) VALUES (
                    :flow_id, :version, :status, :changelog, :audience_filters, :entry_settings,
                    :published_at, :published_by, :created_by
                )'
            );

            $publishedAt = gmdate('Y-m-d H:i:s');
            $status = 'published';
            $changelog = $flow['changelog'] ?? 'Actualización desde el panel de control.';
            $audienceFilters = null;
            if (isset($flow['audience_filters'])) {
                $filters = json_encode($flow['audience_filters'], JSON_UNESCAPED_UNICODE);
                if ($filters !== false) {
                    $audienceFilters = $filters;
                }
            }

            $insertVersion->execute([
                'flow_id' => $flowId,
                'version' => $versionNumber,
                'status' => $status,
                'changelog' => is_string($changelog) ? $changelog : 'Actualización desde el panel de control.',
                'audience_filters' => $audienceFilters,
                'entry_settings' => $encoded,
                'published_at' => $publishedAt,
                'published_by' => $userId,
                'created_by' => $userId,
            ]);

            $versionId = (int) $this->pdo->lastInsertId();

            $this->persistVersionDetails($flow, $versionId);

            $updateFlow = $this->pdo->prepare(
                'UPDATE whatsapp_autoresponder_flows
                 SET active_version_id = :version_id,
                     status = :status,
                     updated_by = :updated_by
                 WHERE id = :flow_id'
            );

            $updateFlow->execute([
                'version_id' => $versionId,
                'status' => 'active',
                'updated_by' => $userId,
                'flow_id' => $flowId,
            ]);

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('No fue posible guardar el flujo de autorespuesta en la base de datos: ' . $exception->getMessage());

            return false;
        }
    }

    private function upsertFlow(array $flow): ?int
    {
        $flowKey = $this->extractFlowKey($flow);
        $name = $this->extractFlowName($flow);
        $description = $this->extractFlowDescription($flow);
        $timezone = $this->extractTimezone($flow);
        $userId = $this->currentUserId();

        try {
            $select = $this->pdo->prepare(
                'SELECT id FROM whatsapp_autoresponder_flows WHERE flow_key = :flow_key LIMIT 1 FOR UPDATE'
            );
            $select->execute(['flow_key' => $flowKey]);
            $existingId = $select->fetchColumn();

            if ($existingId !== false) {
                $update = $this->pdo->prepare(
                    'UPDATE whatsapp_autoresponder_flows
                     SET name = :name,
                         description = :description,
                         timezone = :timezone,
                         updated_by = :updated_by
                     WHERE id = :id'
                );

                $update->execute([
                    'name' => $name,
                    'description' => $description,
                    'timezone' => $timezone,
                    'updated_by' => $userId,
                    'id' => (int) $existingId,
                ]);

                return (int) $existingId;
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO whatsapp_autoresponder_flows (
                    flow_key, name, description, status, timezone, created_by, updated_by
                ) VALUES (
                    :flow_key, :name, :description, :status, :timezone, :created_by, :updated_by
                )'
            );

            $insert->execute([
                'flow_key' => $flowKey,
                'name' => $name,
                'description' => $description,
                'status' => 'draft',
                'timezone' => $timezone,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            error_log('No fue posible insertar o actualizar el flujo de autorespuesta: ' . $exception->getMessage());

            return null;
        }
    }

    private function nextVersionNumber(int $flowId): int
    {
        try {
            $query = $this->pdo->prepare(
                'SELECT MAX(version) AS max_version FROM whatsapp_autoresponder_flow_versions WHERE flow_id = :flow_id'
            );
            $query->execute(['flow_id' => $flowId]);
            $max = $query->fetchColumn();

            return ((int) $max) + 1;
        } catch (Throwable $exception) {
            error_log('No fue posible determinar la versión del flujo de autorespuesta: ' . $exception->getMessage());

            return 1;
        }
    }

    private function extractFlowKey(array $flow): string
    {
        $meta = $flow['meta'] ?? [];
        if (is_array($meta)) {
            $candidate = $meta['flow_key'] ?? $meta['key'] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return substr(trim($candidate), 0, 100);
            }
        }

        if (isset($flow['flow_key']) && is_string($flow['flow_key'])) {
            $candidate = trim($flow['flow_key']);
            if ($candidate !== '') {
                return substr($candidate, 0, 100);
            }
        }

        return self::DEFAULT_FLOW_KEY;
    }

    private function extractFlowName(array $flow): string
    {
        $meta = $flow['meta'] ?? [];
        if (is_array($meta) && isset($meta['name']) && is_string($meta['name'])) {
            $name = trim($meta['name']);
            if ($name !== '') {
                return substr($name, 0, 191);
            }
        }

        if (isset($flow['name']) && is_string($flow['name'])) {
            $name = trim($flow['name']);
            if ($name !== '') {
                return substr($name, 0, 191);
            }
        }

        return 'Flujo principal de WhatsApp';
    }

    private function extractFlowDescription(array $flow): ?string
    {
        $meta = $flow['meta'] ?? [];
        if (is_array($meta) && isset($meta['description']) && is_string($meta['description'])) {
            $description = trim($meta['description']);
            if ($description !== '') {
                return $description;
            }
        }

        if (isset($flow['description']) && is_string($flow['description'])) {
            $description = trim($flow['description']);
            if ($description !== '') {
                return $description;
            }
        }

        return 'Configuración del flujo de autorespuesta gestionada desde el panel.';
    }

    private function extractTimezone(array $flow): ?string
    {
        if (isset($flow['timezone']) && is_string($flow['timezone'])) {
            $timezone = trim($flow['timezone']);

            return $timezone !== '' ? $timezone : null;
        }

        $meta = $flow['meta'] ?? [];
        if (is_array($meta) && isset($meta['timezone']) && is_string($meta['timezone'])) {
            $timezone = trim($meta['timezone']);

            return $timezone !== '' ? $timezone : null;
        }

        return null;
    }

    private function currentUserId(): ?int
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId === null) {
            return null;
        }

        $userId = (int) $userId;

        return $userId > 0 ? $userId : null;
    }

    private function persistVersionDetails(array $flow, int $versionId): void
    {
        $this->clearVersionDetails($versionId);

        $orderIndex = 0;
        $stepReferences = [];

        $entryStepId = $this->createMessageStep(
            $versionId,
            'entry',
            $flow['entry'] ?? null,
            $orderIndex++,
            true
        );

        if ($entryStepId !== null) {
            $stepReferences['entry'] = $entryStepId;
        }

        $optionTransitions = [];
        if (isset($flow['options']) && is_array($flow['options'])) {
            foreach ($flow['options'] as $index => $option) {
                $stepId = $this->createMessageStep(
                    $versionId,
                    $this->normalizeStepKey((string) ($option['id'] ?? 'option_' . ($index + 1))),
                    $option,
                    $orderIndex++,
                    false
                );

                if ($stepId === null) {
                    continue;
                }

                $optionTransitions[] = [
                    'step_id' => $stepId,
                    'option' => $option,
                    'index' => $index,
                ];
            }
        }

        $fallbackStepId = $this->createMessageStep(
            $versionId,
            'fallback',
            $flow['fallback'] ?? null,
            $orderIndex++,
            false
        );

        if ($fallbackStepId !== null) {
            $stepReferences['fallback'] = $fallbackStepId;
        }

        if (isset($flow['scenarios']) && is_array($flow['scenarios'])) {
            foreach ($flow['scenarios'] as $scenarioIndex => $scenario) {
                $stepId = $this->createScenarioStep(
                    $versionId,
                    $scenario,
                    $orderIndex++,
                    $scenarioIndex
                );

                if ($stepId !== null && isset($scenario['id'])) {
                    $stepReferences['scenario:' . (string) $scenario['id']] = $stepId;
                }
            }
        }

        if (isset($stepReferences['entry'])) {
            $priority = 0;
            foreach ($optionTransitions as $transition) {
                $option = is_array($transition['option']) ? $transition['option'] : [];
                $title = $this->stringValue($option['title'] ?? null, 'Opción ' . ($transition['index'] + 1));
                $keywords = $this->stringArray($option['keywords'] ?? []);
                $payload = null;
                if (!empty($keywords)) {
                    $payload = ['keywords' => $keywords];
                }

                $this->createTransition(
                    $stepReferences['entry'],
                    $transition['step_id'],
                    'match',
                    $title,
                    $payload,
                    $priority++
                );
            }

            if (isset($stepReferences['fallback'])) {
                $fallbackKeywords = $this->stringArray($flow['fallback']['keywords'] ?? []);
                $payload = null;
                if (!empty($fallbackKeywords)) {
                    $payload = ['keywords' => $fallbackKeywords];
                }

                $this->createTransition(
                    $stepReferences['entry'],
                    $stepReferences['fallback'],
                    'fallback',
                    'Fallback',
                    $payload,
                    $priority++
                );
            }
        }
    }

    private function clearVersionDetails(int $versionId): void
    {
        $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_step_actions
             WHERE step_id IN (
                 SELECT id FROM whatsapp_autoresponder_steps WHERE flow_version_id = :version_id
             )'
        )->execute(['version_id' => $versionId]);

        $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_step_transitions
             WHERE step_id IN (
                 SELECT id FROM whatsapp_autoresponder_steps WHERE flow_version_id = :version_id
             )'
        )->execute(['version_id' => $versionId]);

        $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_version_filters WHERE flow_version_id = :version_id'
        )->execute(['version_id' => $versionId]);

        $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_schedules WHERE flow_version_id = :version_id'
        )->execute(['version_id' => $versionId]);

        $this->pdo->prepare(
            'DELETE FROM whatsapp_autoresponder_steps WHERE flow_version_id = :version_id'
        )->execute(['version_id' => $versionId]);
    }

    private function createMessageStep(
        int $versionId,
        string $stepKey,
        $section,
        int $orderIndex,
        bool $isEntryPoint
    ): ?int {
        if (!is_array($section)) {
            return null;
        }

        $name = $this->stringValue($section['title'] ?? null, $isEntryPoint ? 'Mensaje de bienvenida' : 'Mensaje');
        $description = $this->stringValue($section['description'] ?? null);
        $settings = [];

        $keywords = $this->stringArray($section['keywords'] ?? []);
        if (!empty($keywords)) {
            $settings['keywords'] = $keywords;
        }

        $followup = $this->stringValue($section['followup'] ?? null);
        if ($followup !== null) {
            $settings['followup'] = $followup;
        }

        $stepId = $this->createStep(
            $versionId,
            $stepKey,
            'message',
            $name,
            $description,
            $orderIndex,
            $isEntryPoint,
            $settings
        );

        if ($stepId === null) {
            return null;
        }

        $this->insertMessagesAsActions($stepId, $section['messages'] ?? []);

        return $stepId;
    }

    private function createScenarioStep(
        int $versionId,
        $scenario,
        int $orderIndex,
        int $position
    ): ?int {
        if (!is_array($scenario)) {
            return null;
        }

        $scenarioId = $this->normalizeStepKey((string) ($scenario['id'] ?? 'scenario_' . ($position + 1)));
        $name = $this->stringValue($scenario['name'] ?? null, 'Escenario ' . ($position + 1));
        $description = $this->stringValue($scenario['description'] ?? null);

        $settings = [];
        if (isset($scenario['conditions']) && is_array($scenario['conditions']) && !empty($scenario['conditions'])) {
            $settings['conditions'] = $scenario['conditions'];
        }
        if (isset($scenario['stage']) && is_string($scenario['stage'])) {
            $settings['stage'] = $scenario['stage'];
        }
        if (isset($scenario['intercept_menu'])) {
            $settings['intercept_menu'] = (bool) $scenario['intercept_menu'];
        }

        $stepId = $this->createStep(
            $versionId,
            $scenarioId,
            'condition',
            $name,
            $description,
            $orderIndex,
            false,
            $settings
        );

        if ($stepId === null) {
            return null;
        }

        $this->insertScenarioActions($stepId, $scenario['actions'] ?? []);

        return $stepId;
    }

    private function createStep(
        int $versionId,
        string $stepKey,
        string $type,
        string $name,
        ?string $description,
        int $orderIndex,
        bool $isEntryPoint,
        array $settings
    ): ?int {
        $normalizedKey = $this->normalizeStepKey($stepKey);
        if ($normalizedKey === '') {
            $normalizedKey = $type . '_' . $orderIndex;
        }

        $encodedSettings = $this->encodeJson(!empty($settings) ? $settings : null);

        $insert = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_steps (
                flow_version_id, step_key, step_type, name, description, order_index, is_entry_point, settings
            ) VALUES (
                :flow_version_id, :step_key, :step_type, :name, :description, :order_index, :is_entry_point, :settings
            )'
        );

        $insert->execute([
            'flow_version_id' => $versionId,
            'step_key' => $normalizedKey,
            'step_type' => $type,
            'name' => $name,
            'description' => $description,
            'order_index' => $orderIndex,
            'is_entry_point' => $isEntryPoint ? 1 : 0,
            'settings' => $encodedSettings,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertMessagesAsActions(int $stepId, $messages): void
    {
        if (!is_array($messages)) {
            return;
        }

        $orderIndex = 0;
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            foreach ($this->normalizeMessageAction($message) as $action) {
                $this->createAction(
                    $stepId,
                    $action['action_type'],
                    $action['message_body'] ?? null,
                    $action['media_url'] ?? null,
                    $action['delay_seconds'] ?? null,
                    $action['metadata'] ?? null,
                    $orderIndex++,
                    $action['template_revision_id'] ?? null
                );
            }
        }
    }

    private function insertScenarioActions(int $stepId, $actions): void
    {
        if (!is_array($actions)) {
            return;
        }

        $orderIndex = 0;
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            foreach ($this->normalizeScenarioAction($action) as $normalized) {
                $this->createAction(
                    $stepId,
                    $normalized['action_type'],
                    $normalized['message_body'] ?? null,
                    $normalized['media_url'] ?? null,
                    $normalized['delay_seconds'] ?? null,
                    $normalized['metadata'] ?? null,
                    $orderIndex++,
                    $normalized['template_revision_id'] ?? null
                );
            }
        }
    }

    private function createAction(
        int $stepId,
        string $actionType,
        ?string $messageBody,
        ?string $mediaUrl,
        ?int $delaySeconds,
        ?array $metadata,
        int $orderIndex,
        ?int $templateRevisionId
    ): void {
        $insertSql = <<<'SQL'
INSERT INTO whatsapp_autoresponder_step_actions (
    step_id, action_type, template_revision_id, message_body, media_url, delay_seconds, metadata, order_index
) VALUES (
    :step_id, :action_type, :template_revision_id, :message_body, :media_url, :delay_seconds, :metadata, :order_index
)
SQL;

        $insert = $this->pdo->prepare($insertSql);

        $insert->execute([
            'step_id' => $stepId,
            'action_type' => $actionType,
            'template_revision_id' => $templateRevisionId,
            'message_body' => $messageBody,
            'media_url' => $mediaUrl,
            'delay_seconds' => $delaySeconds ?? 0,
            'metadata' => $this->encodeJson($metadata),
            'order_index' => $orderIndex,
        ]);
    }

    private function createTransition(
        int $stepId,
        int $targetStepId,
        string $conditionType,
        ?string $conditionLabel,
        ?array $conditionPayload,
        int $priority
    ): void {
        $insert = $this->pdo->prepare(
            'INSERT INTO whatsapp_autoresponder_step_transitions (
                step_id, target_step_id, condition_label, condition_type, condition_payload, priority
            ) VALUES (
                :step_id, :target_step_id, :condition_label, :condition_type, :condition_payload, :priority
            )'
        );

        $insert->execute([
            'step_id' => $stepId,
            'target_step_id' => $targetStepId,
            'condition_label' => $conditionLabel,
            'condition_type' => $conditionType,
            'condition_payload' => $this->encodeJson($conditionPayload),
            'priority' => $priority,
        ]);
    }

    private function normalizeMessageAction(array $message): array
    {
        $type = isset($message['type']) && is_string($message['type'])
            ? strtolower($message['type'])
            : 'text';

        $metadata = $message;
        $metadata['source'] = 'section_message';

        $body = $this->stringValue($message['body'] ?? null);
        $mediaUrl = null;

        if ($type === 'image' || $type === 'document') {
            $mediaUrl = $this->stringValue($message['link'] ?? null);
        }

        if ($type === 'template') {
            return [[
                'action_type' => 'send_template',
                'message_body' => $body,
                'media_url' => $mediaUrl,
                'metadata' => $this->withScenarioType($metadata, 'template'),
            ]];
        }

        return [[
            'action_type' => 'send_session_message',
            'message_body' => $body,
            'media_url' => $mediaUrl,
            'metadata' => $this->withScenarioType($metadata, $type),
        ]];
    }

    private function normalizeScenarioAction(array $action): array
    {
        $type = isset($action['type']) && is_string($action['type'])
            ? strtolower($action['type'])
            : '';

        if ($type === '') {
            return [];
        }

        switch ($type) {
            case 'send_message':
            case 'send_buttons':
            case 'send_list':
                $message = isset($action['message']) && is_array($action['message']) ? $action['message'] : [];

                return [[
                    'action_type' => 'send_session_message',
                    'message_body' => $this->stringValue($message['body'] ?? null),
                    'media_url' => $this->stringValue($message['link'] ?? null),
                    'metadata' => $this->withScenarioType($message, $type),
                ]];
            case 'send_sequence':
                $messages = isset($action['messages']) && is_array($action['messages']) ? $action['messages'] : [];
                $normalized = [];
                foreach ($messages as $sequenceIndex => $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $metadata = $this->withScenarioType($message, $type);
                    $metadata['sequence_index'] = $sequenceIndex;
                    $normalized[] = [
                        'action_type' => 'send_session_message',
                        'message_body' => $this->stringValue($message['body'] ?? null),
                        'media_url' => $this->stringValue($message['link'] ?? null),
                        'metadata' => $metadata,
                    ];
                }

                return $normalized;
            case 'send_template':
                $template = isset($action['template']) && is_array($action['template']) ? $action['template'] : [];

                return [[
                    'action_type' => 'send_template',
                    'message_body' => $this->stringValue($template['body'] ?? null),
                    'metadata' => $this->withScenarioType($template, $type),
                ]];
            case 'handoff_agent':
                return [[
                    'action_type' => 'handoff',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
            case 'assign_tag':
                return [[
                    'action_type' => 'assign_tag',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
            case 'remove_tag':
                return [[
                    'action_type' => 'remove_tag',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
            case 'mark_opt_out':
                return [[
                    'action_type' => 'mark_opt_out',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
            case 'call_webhook':
                return [[
                    'action_type' => 'webhook',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
            default:
                return [[
                    'action_type' => 'update_field',
                    'metadata' => $this->withScenarioType($action, $type),
                ]];
        }
    }

    private function encodeJson($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    private function normalizeStepKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9_\-]/', '_', $normalized);

        return $normalized !== null ? substr($normalized, 0, 100) : '';
    }

    private function stringValue($value, ?string $fallback = null): ?string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $fallback;
        }

        return $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private function stringArray($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return array_values(array_unique($normalized));
    }

    private function withScenarioType(array $data, string $type): array
    {
        if (!isset($data['source'])) {
            $data['source'] = 'scenario_action';
        }

        $data['scenario_action'] = $type;

        return $data;
    }
}
