<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderFlow;
use App\Models\WhatsappAutoresponderFlowVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages the Flowmaker sandbox (draft testing) environment.
 *
 * How it works:
 *  1. Save a draft version of the flow (status = 'ready') via saveDraft().
 *  2. Register one or more WhatsApp numbers to test with via setNumbers().
 *  3. The runtime checks isSandboxNumber($waNumber) before loading the flow.
 *     If true, it loads the draft version instead of the published one.
 *  4. All other real patients continue to use the published version unchanged.
 *  5. When satisfied, call clear() and then publish normally via FlowmakerService.
 *
 * Config is stored in app_settings with key 'whatsapp_flowmaker_sandbox':
 *  { "version_id": 110, "wa_numbers": ["593999123456"], "created_at": "..." }
 */
class FlowmakerSandboxService
{
    private const SETTINGS_KEY = 'whatsapp_flowmaker_sandbox';
    private const DEFAULT_FLOW_KEY = 'default';

    // -------------------------------------------------------------------------
    // Public read API
    // -------------------------------------------------------------------------

    /**
     * Returns the sandbox status for the UI.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $config = $this->loadConfig();

        if ($config === null) {
            return [
                'active' => false,
                'version_id' => null,
                'version_number' => null,
                'wa_numbers' => [],
                'draft_flow' => null,
                'created_at' => null,
            ];
        }

        $versionId = (int) ($config['version_id'] ?? 0);
        $version = $versionId > 0
            ? WhatsappAutoresponderFlowVersion::query()->find($versionId)
            : null;

        $entry = is_array($version?->entry_settings)
            ? $version->entry_settings
            : null;
        $draftFlow = isset($entry['flow']) && is_array($entry['flow'])
            ? $entry['flow']
            : (is_array($entry) ? $entry : null);

        return [
            'active' => $version !== null,
            'version_id' => $versionId ?: null,
            'version_number' => $version ? (int) $version->version : null,
            'wa_numbers' => array_values(array_filter((array) ($config['wa_numbers'] ?? []))),
            'draft_flow' => $draftFlow,
            'created_at' => $config['created_at'] ?? null,
        ];
    }

    /**
     * Returns whether a given WhatsApp number should run against the sandbox flow.
     */
    public function isSandboxNumber(string $waNumber): bool
    {
        if (trim($waNumber) === '') {
            return false;
        }

        $config = $this->loadConfig();
        if ($config === null) {
            return false;
        }

        $numbers = array_map('strval', (array) ($config['wa_numbers'] ?? []));

        return in_array($waNumber, $numbers, true);
    }

    /**
     * Returns the sandbox flow payload for the given number, or null if not applicable.
     *
     * @return array<string, mixed>|null
     */
    public function getFlowPayload(string $waNumber): ?array
    {
        if (!$this->isSandboxNumber($waNumber)) {
            return null;
        }

        $config = $this->loadConfig();
        $versionId = (int) ($config['version_id'] ?? 0);
        if ($versionId === 0) {
            return null;
        }

        $version = WhatsappAutoresponderFlowVersion::query()->find($versionId);
        if ($version === null) {
            return null;
        }

        $entry = is_array($version->entry_settings) ? $version->entry_settings : null;
        if ($entry === null) {
            return null;
        }

        $flow = $entry['flow'] ?? $entry;

        return is_array($flow) && isset($flow['scenarios']) ? $flow : null;
    }

    // -------------------------------------------------------------------------
    // Public write API
    // -------------------------------------------------------------------------

    /**
     * Saves a new draft version of the flow (status = 'ready') and stores it
     * as the current sandbox version. If a previous sandbox version exists it
     * is deleted (unless it is still referenced).
     *
     * @param array<string, mixed> $flowPayload  The 'flow' payload (same shape sent to publish)
     * @param array<string, mixed>|null $options  Optional: { wa_numbers: [...], changelog: "..." }
     * @param int|null $userId
     * @return array<string, mixed>
     */
    public function saveDraft(array $flowPayload, ?array $options = null, ?int $userId = null): array
    {
        $flow = $flowPayload['flow'] ?? $flowPayload;
        if (!is_array($flow) || empty($flow['scenarios'])) {
            throw new InvalidArgumentException('El payload debe contener al menos un escenario.');
        }

        return DB::transaction(function () use ($flow, $options, $userId): array {
            $flowRecord = WhatsappAutoresponderFlow::query()
                ->where('flow_key', self::DEFAULT_FLOW_KEY)
                ->first();

            if ($flowRecord === null) {
                throw new InvalidArgumentException('No existe el flujo principal. Publica al menos una versión primero.');
            }

            // Delete previous sandbox draft (if it was not published/archived)
            $oldConfig = $this->loadConfig();
            $oldVersionId = (int) ($oldConfig['version_id'] ?? 0);
            if ($oldVersionId > 0) {
                $oldVersion = WhatsappAutoresponderFlowVersion::query()->find($oldVersionId);
                if ($oldVersion !== null && $oldVersion->status === 'ready') {
                    $oldVersion->delete();
                }
            }

            // Determine next version number (sandbox drafts use a decimal-like suffix
            // approach, but since version is INT we just use max+1)
            $nextVersion = (int) WhatsappAutoresponderFlowVersion::query()
                ->where('flow_id', $flowRecord->id)
                ->max('version') + 1;

            $version = WhatsappAutoresponderFlowVersion::query()->create([
                'flow_id' => $flowRecord->id,
                'version' => $nextVersion,
                'status' => 'ready',
                'changelog' => (string) ($options['changelog'] ?? 'Borrador sandbox — no publicado'),
                'entry_settings' => ['flow' => $flow],
                'audience_filters' => ['sandbox' => true],
                'created_by' => $userId,
            ]);

            // Merge new numbers with existing ones if provided
            $existingNumbers = (array) ($oldConfig['wa_numbers'] ?? []);
            $newNumbers = isset($options['wa_numbers']) && is_array($options['wa_numbers'])
                ? $options['wa_numbers']
                : $existingNumbers;

            $this->persistConfig([
                'version_id' => $version->id,
                'wa_numbers' => array_values(array_unique(array_map('strval', $newNumbers))),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => true,
                'message' => 'Borrador guardado. Agrega tu número de WhatsApp para activar el sandbox.',
                'version_id' => $version->id,
                'version_number' => $nextVersion,
                'wa_numbers' => $newNumbers,
            ];
        });
    }

    /**
     * Replaces the sandbox number whitelist.
     *
     * @param string[] $numbers  Array of E.164 numbers (e.g. "593999123456")
     * @return array<string, mixed>
     */
    public function setNumbers(array $numbers): array
    {
        $config = $this->loadConfig();
        if ($config === null) {
            throw new InvalidArgumentException('No hay un borrador sandbox activo. Guarda un borrador primero.');
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $n): string => preg_replace('/\D+/', '', $n),
            array_filter(array_map('strval', $numbers))
        )));

        $config['wa_numbers'] = $normalized;
        $this->persistConfig($config);

        return [
            'ok' => true,
            'wa_numbers' => $normalized,
            'version_id' => $config['version_id'] ?? null,
        ];
    }

    /**
     * Adds a single number to the sandbox whitelist (non-destructive).
     *
     * @return array<string, mixed>
     */
    public function addNumber(string $waNumber): array
    {
        $waNumber = preg_replace('/\D+/', '', $waNumber);
        if ($waNumber === '') {
            throw new InvalidArgumentException('Número de WhatsApp inválido.');
        }

        $config = $this->loadConfig();
        if ($config === null) {
            throw new InvalidArgumentException('No hay un borrador sandbox activo. Guarda un borrador primero.');
        }

        $numbers = array_values(array_unique(array_merge(
            (array) ($config['wa_numbers'] ?? []),
            [$waNumber]
        )));

        $config['wa_numbers'] = $numbers;
        $this->persistConfig($config);

        return [
            'ok' => true,
            'added' => $waNumber,
            'wa_numbers' => $numbers,
            'version_id' => $config['version_id'] ?? null,
        ];
    }

    /**
     * Removes a single number from the sandbox whitelist.
     *
     * @return array<string, mixed>
     */
    public function removeNumber(string $waNumber): array
    {
        $waNumber = preg_replace('/\D+/', '', $waNumber);

        $config = $this->loadConfig();
        if ($config === null) {
            return ['ok' => true, 'wa_numbers' => []];
        }

        $config['wa_numbers'] = array_values(array_filter(
            (array) ($config['wa_numbers'] ?? []),
            static fn (string $n): bool => $n !== $waNumber
        ));
        $this->persistConfig($config);

        return [
            'ok' => true,
            'removed' => $waNumber,
            'wa_numbers' => $config['wa_numbers'],
        ];
    }

    /**
     * Clears the sandbox: removes config and optionally deletes the draft version.
     *
     * @return array<string, mixed>
     */
    public function clear(): array
    {
        $config = $this->loadConfig();

        if ($config !== null) {
            $versionId = (int) ($config['version_id'] ?? 0);
            if ($versionId > 0) {
                $version = WhatsappAutoresponderFlowVersion::query()->find($versionId);
                if ($version !== null && $version->status === 'ready') {
                    $version->delete();
                }
            }
        }

        DB::table('app_settings')->where('name', self::SETTINGS_KEY)->delete();

        return [
            'ok' => true,
            'message' => 'Sandbox desactivado. Los pacientes solo ven el flujo publicado.',
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfig(): ?array
    {
        $row = DB::table('app_settings')
            ->where('name', self::SETTINGS_KEY)
            ->value('value');

        if (!is_string($row) || trim($row) === '') {
            return null;
        }

        $decoded = json_decode($row, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function persistConfig(array $config): void
    {
        DB::table('app_settings')->upsert(
            [
                'name' => self::SETTINGS_KEY,
                'value' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'category' => 'whatsapp',
                'type' => 'json',
                'autoload' => 0,
            ],
            ['name'],
            ['value', 'updated_at']
        );
    }
}
