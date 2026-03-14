<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use Illuminate\Support\Facades\DB;

class CodesDeduplicationService
{
    private const MAX_ISSUES = 60;

    /**
     * @return array{
     *     dry_run:bool,
     *     groups_total:int,
     *     canonical_codes:int,
     *     duplicate_rows:int,
     *     deleted_codes:int,
     *     tables_updated:array<int, array{label:string, affected:int}>,
     *     cleanup:array<int, array{label:string, affected:int}>,
     *     issues:array<int, array{row:null, message:string}>,
     *     issues_count:int
     * }
     */
    public function run(bool $dryRun = true): array
    {
        $groups = $this->duplicateGroups();
        $summary = $this->buildSummary($groups, $dryRun);

        if ($dryRun || $groups === []) {
            return $summary;
        }

        $mapping = $this->buildMapping($groups);
        $duplicateIds = array_keys($mapping);

        return DB::transaction(function () use ($summary, $mapping, $duplicateIds): array {
            $summary['tables_updated'] = [
                ['label' => 'prices.code_id', 'affected' => $this->bulkRemap('prices', 'code_id', $mapping)],
                ['label' => 'code_external_map.code_id', 'affected' => $this->bulkRemap('code_external_map', 'code_id', $mapping)],
                ['label' => 'code_tax_rate.code_id', 'affected' => $this->bulkRemap('code_tax_rate', 'code_id', $mapping)],
                ['label' => 'crm_package_items.code_id', 'affected' => $this->bulkRemap('crm_package_items', 'code_id', $mapping)],
                ['label' => 'crm_proposal_items.code_id', 'affected' => $this->bulkRemap('crm_proposal_items', 'code_id', $mapping)],
                ['label' => 'codes_history.code_id', 'affected' => $this->bulkRemap('codes_history', 'code_id', $mapping)],
                ['label' => 'related_codes.code_id', 'affected' => $this->bulkRemap('related_codes', 'code_id', $mapping)],
                ['label' => 'related_codes.related_code_id', 'affected' => $this->bulkRemap('related_codes', 'related_code_id', $mapping)],
            ];

            $summary['cleanup'] = [
                ['label' => 'prices duplicados', 'affected' => $this->deleteDuplicateRows('prices', ['code_id', 'level_key'])],
                ['label' => 'code_external_map duplicados', 'affected' => $this->deleteDuplicateRows('code_external_map', ['code_id', 'external_code_id', 'relation_type'], true)],
                ['label' => 'code_tax_rate duplicados', 'affected' => $this->deleteDuplicateRows('code_tax_rate', ['code_id', 'rate_key'])],
                ['label' => 'related_codes autoreferencias', 'affected' => DB::delete('DELETE FROM `related_codes` WHERE `code_id` = `related_code_id`')],
                ['label' => 'related_codes duplicados', 'affected' => $this->deleteDuplicateRows('related_codes', ['code_id', 'related_code_id', 'relation_type'], true)],
            ];

            $summary['deleted_codes'] = $this->deleteDuplicateCodes($duplicateIds);

            return $summary;
        });
    }

    /**
     * @return array<int, array{
     *     codigo:string,
     *     canonical_id:int,
     *     duplicate_ids:array<int, int>
     * }>
     */
    private function duplicateGroups(): array
    {
        $duplicateCodes = DB::table('tarifario_2014')
            ->select('codigo')
            ->whereNotNull('codigo')
            ->whereRaw("TRIM(codigo) <> ''")
            ->groupBy('codigo')
            ->havingRaw('COUNT(*) > 1');

        $rows = DB::table('tarifario_2014 as t')
            ->joinSub($duplicateCodes, 'd', static function ($join): void {
                $join->on('d.codigo', '=', 't.codigo');
            })
            ->select(['t.id', 't.codigo'])
            ->orderBy('t.codigo')
            ->orderBy('t.id')
            ->get();

        /** @var array<string, array<int, int>> $idsByCode */
        $idsByCode = [];
        foreach ($rows as $row) {
            $codigo = trim((string) ($row->codigo ?? ''));
            if ($codigo === '') {
                continue;
            }

            $idsByCode[$codigo] ??= [];
            $idsByCode[$codigo][] = (int) ($row->id ?? 0);
        }

        $groups = [];
        foreach ($idsByCode as $codigo => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            sort($ids, SORT_NUMERIC);
            $canonicalId = (int) array_shift($ids);

            $groups[] = [
                'codigo' => $codigo,
                'canonical_id' => $canonicalId,
                'duplicate_ids' => array_values(array_map('intval', $ids)),
            ];
        }

        return $groups;
    }

    /**
     * @param array<int, array{codigo:string, canonical_id:int, duplicate_ids:array<int, int>}> $groups
     * @return array{
     *     dry_run:bool,
     *     groups_total:int,
     *     canonical_codes:int,
     *     duplicate_rows:int,
     *     deleted_codes:int,
     *     tables_updated:array<int, array{label:string, affected:int}>,
     *     cleanup:array<int, array{label:string, affected:int}>,
     *     issues:array<int, array{row:null, message:string}>,
     *     issues_count:int
     * }
     */
    private function buildSummary(array $groups, bool $dryRun): array
    {
        $duplicateRows = 0;
        $issues = [];
        $issuesCount = 0;

        foreach ($groups as $group) {
            $duplicateRows += count($group['duplicate_ids']);
            $issuesCount++;

            if (count($issues) >= self::MAX_ISSUES) {
                continue;
            }

            $issues[] = [
                'row' => null,
                'message' => sprintf(
                    'El codigo %s conservara el ID %d y eliminara %s.',
                    $group['codigo'],
                    $group['canonical_id'],
                    implode(', ', $group['duplicate_ids'])
                ),
            ];
        }

        return [
            'dry_run' => $dryRun,
            'groups_total' => count($groups),
            'canonical_codes' => count($groups),
            'duplicate_rows' => $duplicateRows,
            'deleted_codes' => 0,
            'tables_updated' => [],
            'cleanup' => [],
            'issues' => $issues,
            'issues_count' => $issuesCount,
        ];
    }

    /**
     * @param array<int, array{codigo:string, canonical_id:int, duplicate_ids:array<int, int>}> $groups
     * @return array<int, int>
     */
    private function buildMapping(array $groups): array
    {
        $mapping = [];

        foreach ($groups as $group) {
            foreach ($group['duplicate_ids'] as $duplicateId) {
                $mapping[(int) $duplicateId] = (int) $group['canonical_id'];
            }
        }

        return $mapping;
    }

    /**
     * @param array<int, int> $mapping
     */
    private function bulkRemap(string $table, string $column, array $mapping): int
    {
        if ($mapping === []) {
            return 0;
        }

        $affected = 0;

        foreach (array_chunk($mapping, 250, true) as $chunk) {
            $cases = [];
            $ids = [];

            foreach ($chunk as $fromId => $toId) {
                $from = (int) $fromId;
                $to = (int) $toId;

                if ($from < 1 || $to < 1 || $from === $to) {
                    continue;
                }

                $cases[] = sprintf('WHEN %d THEN %d', $from, $to);
                $ids[] = $from;
            }

            if ($cases === [] || $ids === []) {
                continue;
            }

            $affected += DB::update(
                sprintf(
                    'UPDATE `%s` SET `%s` = CASE `%s` %s ELSE `%s` END WHERE `%s` IN (%s)',
                    $table,
                    $column,
                    $column,
                    implode(' ', $cases),
                    $column,
                    $column,
                    implode(',', $ids)
                )
            );
        }

        return $affected;
    }

    /**
     * @param array<int, string> $columns
     */
    private function deleteDuplicateRows(string $table, array $columns, bool $nullSafe = false): int
    {
        $comparisons = [];

        foreach ($columns as $column) {
            $operator = $nullSafe ? '<=>' : '=';
            $comparisons[] = sprintf('a.`%s` %s b.`%s`', $column, $operator, $column);
        }

        return DB::delete(
            sprintf(
                'DELETE a FROM `%s` a INNER JOIN `%s` b ON %s AND a.`id` > b.`id`',
                $table,
                $table,
                implode(' AND ', $comparisons)
            )
        );
    }

    /**
     * @param array<int, int> $duplicateIds
     */
    private function deleteDuplicateCodes(array $duplicateIds): int
    {
        $deleted = 0;

        foreach (array_chunk(array_values(array_map('intval', $duplicateIds)), 250) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $deleted += DB::table('tarifario_2014')
                ->whereIn('id', $chunk)
                ->delete();
        }

        return $deleted;
    }
}
