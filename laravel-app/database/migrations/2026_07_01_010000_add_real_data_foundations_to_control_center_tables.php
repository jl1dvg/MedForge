<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('control_center_organizations', function (Blueprint $table): void {
            $this->stringColumn($table, 'source', 'manual', true);
            $this->timestampColumn($table, 'last_verified_at');
        });

        $this->table('control_center_instances', function (Blueprint $table): void {
            $this->stringColumn($table, 'source', 'manual', true);
            $this->timestampColumn($table, 'last_verified_at');
            $this->timestampColumn($table, 'last_seen_at');
            $this->timestampColumn($table, 'last_backup_at');
            $this->stringColumn($table, 'telemetry_status', 'pending', true);
            $this->stringColumn($table, 'telemetry_token_hash');
            $this->jsonColumn($table, 'telemetry_json');
        });

        foreach (['control_center_plans', 'control_center_contracts', 'control_center_services'] as $tableName) {
            $this->table($tableName, function (Blueprint $table): void {
                $this->stringColumn($table, 'source', 'manual', true);
                $this->timestampColumn($table, 'last_verified_at');
            });
        }

        $this->table('control_center_service_snapshots', function (Blueprint $table): void {
            $this->stringColumn($table, 'source', 'manual', true);
            $this->booleanColumn($table, 'is_stale', false, true);
            $this->timestampColumn($table, 'last_verified_at');
        });

        $this->table('control_center_usage_metrics', function (Blueprint $table): void {
            $this->stringColumn($table, 'source', 'manual', true);
            $this->stringColumn($table, 'source_ref');
            $this->stringColumn($table, 'idempotency_key')->unique();
            $this->timestampColumn($table, 'measured_at');
            $this->timestampColumn($table, 'last_verified_at');
        });

        $this->table('control_center_deployments', function (Blueprint $table): void {
            $this->stringColumn($table, 'source', 'manual', true);
            $this->stringColumn($table, 'commit_sha', null, true);
            $this->stringColumn($table, 'idempotency_key')->unique();
            $this->timestampColumn($table, 'last_verified_at');
        });
    }

    public function down(): void
    {
        $columns = [
            'control_center_deployments' => ['last_verified_at', 'idempotency_key', 'commit_sha', 'source'],
            'control_center_usage_metrics' => ['last_verified_at', 'measured_at', 'idempotency_key', 'source_ref', 'source'],
            'control_center_service_snapshots' => ['last_verified_at', 'is_stale', 'source'],
            'control_center_services' => ['last_verified_at', 'source'],
            'control_center_contracts' => ['last_verified_at', 'source'],
            'control_center_plans' => ['last_verified_at', 'source'],
            'control_center_instances' => ['telemetry_json', 'telemetry_token_hash', 'telemetry_status', 'last_backup_at', 'last_seen_at', 'last_verified_at', 'source'],
            'control_center_organizations' => ['last_verified_at', 'source'],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $tableColumns): void {
                foreach ($tableColumns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function table(string $name, callable $callback): void
    {
        if (Schema::hasTable($name)) {
            Schema::table($name, $callback);
        }
    }

    private function stringColumn(Blueprint $table, string $column, ?string $default = null, bool $index = false): mixed
    {
        if (Schema::hasColumn($table->getTable(), $column)) {
            return null;
        }

        $definition = $table->string($column)->nullable();
        if ($default !== null) {
            $definition->default($default);
        }

        if ($index) {
            $definition->index();
        }

        return $definition;
    }

    private function timestampColumn(Blueprint $table, string $column): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $table->timestamp($column)->nullable();
        }
    }

    private function jsonColumn(Blueprint $table, string $column): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $table->json($column)->nullable();
        }
    }

    private function booleanColumn(Blueprint $table, string $column, bool $default, bool $index = false): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $definition = $table->boolean($column)->default($default);
            if ($index) {
                $definition->index();
            }
        }
    }
};
