<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class SettingsOptionResolver
{
    private const TABLE_CANDIDATES = ['app_settings', 'settings', 'tbloptions', 'options'];

    private ?string $resolvedTable = null;

    /**
     * @var array{name:string,value:string}|null
     */
    private ?array $resolvedColumns = null;

    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    public function getOptions(array $keys): array
    {
        $keys = array_values(array_filter(array_map(static fn($key): string => trim((string) $key), $keys)));
        if ($keys === []) {
            return [];
        }

        $table = $this->resolveTable();
        if ($table === null) {
            return [];
        }

        $columns = $this->resolveColumns($table);
        if ($columns === null) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        try {
            $rows = DB::select(
                sprintf(
                    'SELECT %s, %s FROM %s WHERE %s IN (%s)',
                    $columns['name'],
                    $columns['value'],
                    $table,
                    $columns['name'],
                    $placeholders
                ),
                $keys
            );
        } catch (Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->{$columns['name']} ?? ''));
            if ($name === '') {
                continue;
            }

            $options[$name] = (string) ($row->{$columns['value']} ?? '');
        }

        return $options;
    }

    private function resolveTable(): ?string
    {
        if ($this->resolvedTable !== null) {
            return $this->resolvedTable;
        }

        foreach (self::TABLE_CANDIDATES as $candidate) {
            try {
                $exists = DB::selectOne(
                    'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$candidate]
                );
            } catch (Throwable) {
                return null;
            }

            if ((int) ($exists->total ?? 0) > 0) {
                $this->resolvedTable = $candidate;
                return $this->resolvedTable;
            }
        }

        return null;
    }

    /**
     * @return array{name:string,value:string}|null
     */
    private function resolveColumns(string $table): ?array
    {
        if ($this->resolvedColumns !== null) {
            return $this->resolvedColumns;
        }

        try {
            $rows = DB::select('SHOW COLUMNS FROM ' . $table);
        } catch (Throwable) {
            return null;
        }

        $nameColumn = null;
        $valueColumn = null;

        foreach ($rows as $row) {
            $field = (string) ($row->Field ?? '');
            if ($field === '') {
                continue;
            }

            if ($nameColumn === null && stripos($field, 'name') !== false) {
                $nameColumn = $field;
            }

            if (
                $valueColumn === null
                && (stripos($field, 'value') !== false || stripos($field, 'setting') !== false)
            ) {
                $valueColumn = $field;
            }
        }

        if ($nameColumn === null || $valueColumn === null) {
            return null;
        }

        $this->resolvedColumns = [
            'name' => $nameColumn,
            'value' => $valueColumn,
        ];

        return $this->resolvedColumns;
    }
}
