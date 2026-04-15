<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyFlowSourceService
{
    private const DEFAULT_FLOW_KEY = 'default';
    private const OPTION_KEY = 'whatsapp_autoresponder_flow';

    /**
     * @return array{flow: array<string, mixed>, source: string}
     */
    public function load(): array
    {
        $fromTables = $this->loadFromFlowTables();
        if ($fromTables !== null) {
            return ['flow' => $fromTables, 'source' => 'flow_tables'];
        }

        $fromSettings = $this->loadFromSettings();
        if ($fromSettings !== null) {
            return ['flow' => $fromSettings, 'source' => 'settings'];
        }

        $fromFallback = $this->loadFromFallback();
        if ($fromFallback !== null) {
            return ['flow' => $fromFallback, 'source' => 'fallback_file'];
        }

        return ['flow' => [], 'source' => 'empty'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFromFlowTables(): ?array
    {
        if (!$this->tableExists('whatsapp_autoresponder_flows') || !$this->tableExists('whatsapp_autoresponder_flow_versions')) {
            return null;
        }

        $row = DB::selectOne(<<<'SQL'
SELECT fv.entry_settings
FROM whatsapp_autoresponder_flow_versions fv
JOIN whatsapp_autoresponder_flows f ON f.id = fv.flow_id
WHERE f.flow_key = ? AND (
    f.active_version_id = fv.id OR f.active_version_id IS NULL
)
ORDER BY (f.active_version_id = fv.id) DESC, fv.version DESC
LIMIT 1
SQL, [self::DEFAULT_FLOW_KEY]);

        $entrySettings = is_object($row) ? ($row->entry_settings ?? null) : null;
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

        return isset($decoded['scenarios']) && is_array($decoded['scenarios']) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFromSettings(): ?array
    {
        [$table, $nameColumn, $valueColumn] = $this->resolveSettingsStorage();
        if ($table === null || $nameColumn === null || $valueColumn === null) {
            return null;
        }

        $row = DB::table($table)
            ->select($valueColumn)
            ->where($nameColumn, self::OPTION_KEY)
            ->first();

        $value = is_object($row) ? ($row->{$valueColumn} ?? null) : null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFromFallback(): ?array
    {
        $path = dirname(base_path()) . '/storage/whatsapp_autoresponder_flow.json';
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function resolveSettingsStorage(): array
    {
        foreach (['app_settings', 'settings', 'tbloptions', 'options'] as $candidate) {
            if (!$this->tableExists($candidate)) {
                continue;
            }

            $columns = Schema::getColumnListing($candidate);
            $nameColumn = null;
            $valueColumn = null;

            foreach ($columns as $column) {
                $field = is_string($column) ? $column : '';
                if ($field === '') {
                    continue;
                }

                if ($nameColumn === null && stripos($field, 'name') !== false) {
                    $nameColumn = $field;
                }

                if ($valueColumn === null && (stripos($field, 'value') !== false || stripos($field, 'setting') !== false)) {
                    $valueColumn = $field;
                }
            }

            if ($nameColumn !== null && $valueColumn !== null) {
                return [$candidate, $nameColumn, $valueColumn];
            }
        }

        return [null, null, null];
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
