<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmCaseActivityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forCase(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud') {
            return [];
        }

        $events = array_merge(
            $this->noteEvents($sourceId),
            $this->taskEvents($sourceId),
        );

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_values($events);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notesForCase(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud' || !$this->hasColumns('solicitud_crm_notas', ['solicitud_id'])) {
            return [];
        }

        $query = DB::table('solicitud_crm_notas')->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_notas', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $rows = $query
            ->orderBy($this->orderColumn('solicitud_crm_notas'), 'desc')
            ->get();

        return $rows->map(function (object $row) use ($sourceId): array {
            $item = (array) $row;
            $authorId = isset($item['autor_id']) ? (int) $item['autor_id'] : null;

            return [
                'id' => isset($item['id']) ? (int) $item['id'] : null,
                'source_type' => 'solicitud',
                'source_id' => $sourceId,
                'body' => $item['nota'] ?? $item['body'] ?? null,
                'author_id' => $authorId,
                'author_name' => $this->userName($authorId),
                'created_at' => $item['created_at'] ?? null,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tasksForCase(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud' || !Schema::hasTable('crm_tasks')) {
            return [];
        }

        $query = DB::table('crm_tasks');
        $hasAnyLink = false;

        $query->where(function ($linked) use ($sourceId, &$hasAnyLink): void {
            if ($this->hasColumns('crm_tasks', ['source_type', 'source_id'])) {
                $hasAnyLink = true;
                $linked->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_id', $sourceId);
                });
            }

            if ($this->hasColumns('crm_tasks', ['source_module', 'source_ref_id'])) {
                $hasAnyLink = true;
                $linked->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_module', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_ref_id', (string) $sourceId);
                });
            }

            if ($this->hasColumns('crm_tasks', ['entity_type', 'entity_id'])) {
                $hasAnyLink = true;
                $linked->orWhere(function ($entity) use ($sourceId): void {
                    $entity->whereIn('entity_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('entity_id', (string) $sourceId);
                });
            }

            if (Schema::hasColumn('crm_tasks', 'form_id')) {
                $hasAnyLink = true;
                $linked->orWhere('form_id', $sourceId);
            }
        });

        if (!$hasAnyLink) {
            return [];
        }

        $rows = $query
            ->orderBy($this->orderColumn('crm_tasks'), 'desc')
            ->get();

        return $rows->map(function (object $row): array {
            $item = (array) $row;
            $assignedTo = isset($item['assigned_to']) ? (int) $item['assigned_to'] : null;
            $createdBy = isset($item['created_by']) ? (int) $item['created_by'] : null;

            return [
                'id' => isset($item['id']) ? (int) $item['id'] : null,
                'title' => $item['title'] ?? null,
                'description' => $item['description'] ?? null,
                'status' => $item['status'] ?? null,
                'priority' => $item['priority'] ?? null,
                'assigned_to' => $assignedTo,
                'assigned_name' => $this->userName($assignedTo),
                'created_by' => $createdBy,
                'created_by_name' => $this->userName($createdBy),
                'due_at' => $item['due_at'] ?? $item['due_date'] ?? null,
                'completed_at' => $item['completed_at'] ?? null,
                'created_at' => $item['created_at'] ?? null,
                'updated_at' => $item['updated_at'] ?? null,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function noteEvents(int $sourceId): array
    {
        return array_map(static function (array $note): array {
            $noteId = isset($note['id']) ? (int) $note['id'] : null;

            return [
                'id' => 'note:' . ($noteId ?? ''),
                'type' => 'note_created',
                'occurred_at' => $note['created_at'] ?? null,
                'author' => $note['author_name'] ?? 'Sistema',
                'description' => $note['body'] ?? null,
                'reference' => [
                    'note_id' => $noteId,
                ],
            ];
        }, $this->notesForCase('solicitud', $sourceId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskEvents(int $sourceId): array
    {
        $completedTasks = array_filter($this->tasksForCase('solicitud', $sourceId), static function (array $task): bool {
            return trim((string) ($task['completed_at'] ?? '')) !== ''
                || in_array(strtolower(trim((string) ($task['status'] ?? ''))), ['completada', 'completado', 'completed', 'done', 'finalizada', 'finalizado'], true);
        });

        return array_map(static function (array $task): array {
            $taskId = isset($task['id']) ? (int) $task['id'] : null;

            return [
                'id' => 'task:' . ($taskId ?? ''),
                'type' => 'task_completed',
                'occurred_at' => $task['updated_at'] ?? $task['created_at'] ?? null,
                'author' => $task['created_by_name'] ?? 'Sistema',
                'description' => $task['title'] ?? null,
                'reference' => [
                    'task_id' => $taskId,
                ],
            ];
        }, array_values($completedTasks));
    }

    private function userName(?int $userId): string
    {
        if ($userId === null || $userId <= 0 || !Schema::hasTable('users')) {
            return 'Sistema';
        }

        $select = ['id'];
        foreach (['name', 'username', 'nombre'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $select[] = $column;
            }
        }

        $user = DB::table('users')->select($select)->where('id', $userId)->first();
        if ($user === null) {
            return 'Usuario';
        }

        foreach (['name', 'username', 'nombre'] as $column) {
            $value = trim((string) ($user->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Usuario';
    }

    /**
     * @param array<int, string> $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function orderColumn(string $table): string
    {
        foreach (['updated_at', 'created_at', 'id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'rowid';
    }
}
