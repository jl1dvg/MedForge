<?php

namespace App\Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves valor_estimado for CRM opportunities dynamically from the existing tarifario.
 * This is an estimated value — it reflects the current tarifario at query time and may
 * change as tariffs or afiliacion mappings are updated. No column is persisted.
 */
class CrmOpportunityValueResolver
{
    /**
     * Bulk-resolves valor_estimado for a collection of solicitud_procedimiento source IDs.
     * Uses at most 3 queries regardless of collection size — no N+1.
     *
     * Returns array keyed by solicitud_procedimiento.id. Missing IDs return null.
     *
     * @param  Collection<int, int>  $sourceIds
     * @return array<int, array{procedimiento:string|null, ojo:string|null, doctor:string|null, valor_estimado:float}>
     */
    public function resolveForSolicitudes(Collection $sourceIds): array
    {
        if ($sourceIds->isEmpty()) {
            return [];
        }

        // Query 1: fetch all SP rows needed
        $rows = DB::table('solicitud_procedimiento')
            ->whereIn('id', $sourceIds)
            ->select(['id', 'procedimiento', 'ojo', 'doctor', 'afiliacion', 'derivacion_codigo'])
            ->get();

        // Determine effective code per row: derivacion_codigo first, then procedimiento
        $effectiveCodes = [];
        foreach ($rows as $row) {
            $effectiveCodes[$row->id] =
                $this->extractProcedureCode($row->derivacion_codigo)
                ?? $this->extractProcedureCode($row->procedimiento);
        }

        $codes = array_values(array_filter(array_unique($effectiveCodes)));

        // Query 2: tarifario + prices for all unique codes
        $tarifarioPrices = [];
        if ($codes !== []) {
            DB::table('tarifario_2014 as t')
                ->leftJoin('prices as pr', 'pr.code_id', '=', 't.id')
                ->whereIn('t.codigo', $codes)
                ->select(['t.codigo', 'pr.level_key', 'pr.price', 't.valor_facturar_nivel1'])
                ->get()
                ->each(function (object $row) use (&$tarifarioPrices): void {
                    $tarifarioPrices[$row->codigo][] = $row;
                });
        }

        // Query 3: full afiliacion_categoria_map (typically small table)
        $afiliacionNorm = DB::table('afiliacion_categoria_map')
            ->select(['afiliacion_raw', 'afiliacion_norm'])
            ->get()
            ->mapWithKeys(fn (object $r): array => [
                strtolower(trim($r->afiliacion_raw ?? '')) => $r->afiliacion_norm,
            ])
            ->all();

        // Build result
        $result = [];
        foreach ($rows as $row) {
            $code     = $effectiveCodes[$row->id] ?? null;
            $normKey  = strtolower(trim($row->afiliacion ?? ''));
            $levelKey = $afiliacionNorm[$normKey] ?? null;

            $valorEstimado = 0.0;
            if ($code !== null && isset($tarifarioPrices[$code])) {
                $valorEstimado = $this->pickBestPrice($tarifarioPrices[$code], $levelKey);
            }

            $result[$row->id] = [
                'procedimiento'  => $row->procedimiento,
                'ojo'            => $row->ojo,
                'doctor'         => $row->doctor,
                'valor_estimado' => $valorEstimado,
            ];
        }

        return $result;
    }

    /**
     * Extracts the first valid procedure code from a mixed-format procedure string.
     *
     * Handles:
     *   - CYP internal codes: CYP-CCA-009, CYP-OCU-035, CYP-EST-001 …
     *   - 5-digit numeric CPT/derivación codes: 66984, 66710, 65426 …
     *   - Prefixes like "CIRUGIAS -", "CIRUGIA -", "CIRUGÍA -" are stripped first.
     *
     * Returns null if no valid code is found. Never throws.
     */
    public function extractProcedureCode(?string $procedimiento): ?string
    {
        if ($procedimiento === null || trim($procedimiento) === '') {
            return null;
        }

        $str = trim($procedimiento, " \t\n\r\0\x0B\"'");

        // Strip non-tarifario category prefixes
        $str = (string) preg_replace('/^CIRUG[IÍ]AS?\s*[-–]\s*/iu', '', $str);

        // Priority 1: CYP internal code (CYP-ABC-000)
        if (preg_match('/\b(CYP-[A-Z]{3}-\d{3})\b/i', $str, $m)) {
            return strtoupper($m[1]);
        }

        // Priority 2: 5-digit numeric CPT/derivación code
        if (preg_match('/\b(\d{5})\b/', $str, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Picks the best available price from a set of tarifario entries.
     * Prefers an exact level_key match, falls back to valor_facturar_nivel1, then 0.
     *
     * @param  array<object>  $entries
     */
    private function pickBestPrice(array $entries, ?string $levelKey): float
    {
        if ($levelKey !== null) {
            foreach ($entries as $entry) {
                if (($entry->level_key ?? null) === $levelKey && $entry->price !== null) {
                    return (float) $entry->price;
                }
            }
        }

        foreach ($entries as $entry) {
            if (($entry->valor_facturar_nivel1 ?? null) !== null) {
                return (float) $entry->valor_facturar_nivel1;
            }
        }

        return 0.0;
    }
}
