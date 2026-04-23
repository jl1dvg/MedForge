<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use Illuminate\Support\Facades\DB;

class SolicitudesChecklistBackfillService
{
    private SolicitudesStateMachineService $stateMachine;
    /** @var array<string,array<int,string>> */
    private array $columnsCache = [];

    public function __construct(?SolicitudesStateMachineService $stateMachine = null)
    {
        $this->stateMachine = $stateMachine ?? new SolicitudesStateMachineService();
    }

    /**
     * @return array{total:int,candidatas:int,esperadas_por_solicitud:int}
     */
    public function summary(): array
    {
        $total = (int) DB::table('solicitud_procedimiento')->count();
        $expected = count($this->stateMachine->stages());

        $candidatas = count($this->candidateSolicitudes());

        return [
            'total' => $total,
            'candidatas' => $candidatas,
            'esperadas_por_solicitud' => $expected,
        ];
    }

    /**
     * @return array{candidatas:int,procesadas:int,filas_insertadas:int,estados_actualizados:int}
     */
    public function run(bool $dryRun = true, int $limit = 0): array
    {
        $candidates = $this->candidateSolicitudes($limit);
        $columns = $this->tableColumns('solicitud_checklist');
        $stageSlugs = array_values(array_map(
            static fn(array $stage): string => (string) ($stage['slug'] ?? ''),
            $this->stateMachine->stages()
        ));

        $processed = 0;
        $inserted = 0;
        $updatedStates = 0;

        foreach ($candidates as $candidate) {
            $processed++;

            $solicitudId = (int) ($candidate['id'] ?? 0);
            $legacyState = (string) ($candidate['estado'] ?? '');
            $baseTimestamp = $this->bestTimestamp($candidate);
            $rows = $this->checklistRows($solicitudId);

            $resolved = $rows === []
                ? $this->stateMachine->buildChecklistContext($legacyState, [])
                : $this->stateMachine->resolvePersistedChecklistContext($rows);

            [$checklist, , $kanban] = $resolved;
            $existingBySlug = [];
            foreach ($rows as $row) {
                $slug = $this->stateMachine->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
                if ($slug !== '') {
                    $existingBySlug[$slug] = true;
                }
            }

            $missingPayloads = [];
            foreach ($checklist as $item) {
                $slug = (string) ($item['slug'] ?? '');
                if ($slug === '' || isset($existingBySlug[$slug]) || !in_array($slug, $stageSlugs, true)) {
                    continue;
                }

                $payload = [
                    'solicitud_id' => $solicitudId,
                    'etapa_slug' => $slug,
                ];

                if (in_array('completado_at', $columns, true)) {
                    $payload['completado_at'] = !empty($item['completed']) ? $baseTimestamp : null;
                }
                if (in_array('completado_por', $columns, true)) {
                    $payload['completado_por'] = null;
                }
                if (in_array('created_at', $columns, true)) {
                    $payload['created_at'] = $baseTimestamp;
                }
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = $baseTimestamp;
                }

                $missingPayloads[] = $payload;
            }

            if ($dryRun) {
                $inserted += count($missingPayloads);
                $nextState = (string) ($kanban['slug'] ?? '');
                if ($nextState !== '' && $nextState !== $legacyState) {
                    $updatedStates++;
                }
                continue;
            }

            DB::transaction(function () use ($missingPayloads, $solicitudId, $kanban, $legacyState, &$inserted, &$updatedStates): void {
                if ($missingPayloads !== []) {
                    DB::table('solicitud_checklist')->insert($missingPayloads);
                    $inserted += count($missingPayloads);
                }

                $nextState = (string) ($kanban['slug'] ?? '');
                if ($nextState !== '' && $nextState !== $legacyState) {
                    DB::table('solicitud_procedimiento')
                        ->where('id', $solicitudId)
                        ->update(['estado' => $nextState]);
                    $updatedStates++;
                }
            });
        }

        return [
            'candidatas' => count($candidates),
            'procesadas' => $processed,
            'filas_insertadas' => $inserted,
            'estados_actualizados' => $updatedStates,
        ];
    }

    /**
     * @return array<int,array{id:int,estado:string,created_at:mixed,updated_at:mixed,checklist_count:int}>
     */
    private function candidateSolicitudes(int $limit = 0): array
    {
        $expected = count($this->stateMachine->stages());
        $columns = $this->tableColumns('solicitud_procedimiento');

        $select = ['sp.id', 'sp.estado'];
        $groupBy = ['sp.id', 'sp.estado'];

        if (in_array('created_at', $columns, true)) {
            $select[] = 'sp.created_at';
            $groupBy[] = 'sp.created_at';
        }

        if (in_array('updated_at', $columns, true)) {
            $select[] = 'sp.updated_at';
            $groupBy[] = 'sp.updated_at';
        }

        $query = DB::table('solicitud_procedimiento as sp')
            ->leftJoin('solicitud_checklist as sc', 'sc.solicitud_id', '=', 'sp.id')
            ->selectRaw(implode(', ', $select) . ', COUNT(DISTINCT sc.etapa_slug) AS checklist_count')
            ->groupBy(...$groupBy)
            ->havingRaw('COUNT(DISTINCT sc.etapa_slug) < ?', [$expected])
            ->orderBy('sp.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return array_map(static fn(object $row): array => (array) $row, $query->get()->all());
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function checklistRows(int $solicitudId): array
    {
        return DB::table('solicitud_checklist')
            ->select(['etapa_slug', 'completado_at', 'nota'])
            ->where('solicitud_id', $solicitudId)
            ->orderBy('id')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        $this->columnsCache[$table] = array_map(
            static fn(object $row): string => (string) ($row->Field ?? ''),
            DB::select('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')
        );

        return $this->columnsCache[$table];
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function bestTimestamp(array $candidate): string
    {
        $updated = trim((string) ($candidate['updated_at'] ?? ''));
        if ($updated !== '' && $updated !== '0000-00-00 00:00:00') {
            return $updated;
        }

        $created = trim((string) ($candidate['created_at'] ?? ''));
        if ($created !== '' && $created !== '0000-00-00 00:00:00') {
            return $created;
        }

        return date('Y-m-d H:i:s');
    }
}
