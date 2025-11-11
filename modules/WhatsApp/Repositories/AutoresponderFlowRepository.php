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

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->fallbackPath = BASE_PATH . '/storage/whatsapp_autoresponder_flow.json';
        $this->hasFlowTables = $this->detectFlowTables();

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
