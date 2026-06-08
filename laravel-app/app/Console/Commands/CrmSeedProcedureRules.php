<?php

namespace App\Console\Commands;

use App\Models\CrmProcedureRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmSeedProcedureRules extends Command
{
    protected $signature = 'crm:seed-procedure-rules
                            {--days=90 : Look back this many days in solicitud_procedimiento}
                            {--dry-run : Show what would be inserted without writing}';

    protected $description = 'Bootstrap crm_procedure_rules with stub entries for unclassified procedure codes';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        // Fetch raw strings with their usage frequency (most frequent first)
        $rawRows = DB::table('solicitud_procedimiento')
            ->select('procedimiento', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('procedimiento')
            ->where('procedimiento', '!=', '')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('procedimiento')
            ->orderByDesc('cnt')
            ->get();

        if ($rawRows->isEmpty()) {
            $this->info('No procedure strings found in the last ' . $days . ' days.');
            return self::SUCCESS;
        }

        // Parse each raw string; for duplicate codes keep the nombre from the most
        // frequent raw string (results are already ordered DESC by cnt)
        $codeMap    = []; // codigo => ['nombre' => string, 'cnt' => int]
        $unparseable = [];
        $examples   = []; // up to 30 for dry-run display

        foreach ($rawRows as $row) {
            $parsed = CrmProcedureRule::parseProcedureCode($row->procedimiento);
            if ($parsed === null) {
                $unparseable[] = $row->procedimiento;
                continue;
            }
            $codigo = $parsed['codigo'];
            if (!isset($codeMap[$codigo])) {
                $codeMap[$codigo] = ['nombre' => $parsed['nombre'], 'cnt' => $row->cnt];
            }
            if (count($examples) < 30) {
                $examples[] = [$row->procedimiento, $parsed['codigo'], $parsed['nombre']];
            }
        }

        $totalRaw    = $rawRows->count();
        $totalParsed = count($codeMap) + 0; // unique codigos
        $totalFailed = count($unparseable);

        // Which codes don't yet have a rule?
        $existing = CrmProcedureRule::whereIn('codigo', array_keys($codeMap))
            ->pluck('codigo')
            ->flip();
        $toInsert = array_filter($codeMap, fn ($_, $c) => !$existing->has($c), ARRAY_FILTER_USE_BOTH);

        // --- Output ---
        $this->line('');
        $this->line('=== PARSE SUMMARY ===');
        $this->line('  Raw strings (últimos ' . $days . ' días): ' . $totalRaw);
        $this->line('  Parseables:                               ' . ($totalRaw - $totalFailed));
        $this->line('  Códigos únicos parseados:                 ' . count($codeMap));
        $this->line('  No parseables:                            ' . $totalFailed);
        $this->line('  Ya tienen regla:                          ' . $existing->count());
        $this->line('  A insertar:                               ' . count($toInsert));
        $this->line('');

        if ($unparseable) {
            $this->warn('=== NO PARSEABLES ===');
            foreach ($unparseable as $u) {
                $this->line('  ✗ ' . $u);
            }
            $this->line('');
        }

        $this->line('=== 30 EJEMPLOS (raw → codigo → nombre) ===');
        foreach ($examples as [$raw, $codigo, $nombre]) {
            $this->line('  ' . $codigo . ' | ' . substr($nombre, 0, 55) . ' | raw: ' . substr($raw, 0, 70));
        }
        $this->line('');

        if (empty($toInsert)) {
            $this->info('Todos los códigos ya tienen regla. Nada que insertar.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Se insertarían ' . count($toInsert) . ' regla(s). Sin cambios.');
            return self::SUCCESS;
        }

        $now  = now();
        $rows = [];
        foreach ($toInsert as $codigo => $data) {
            $rows[] = [
                'codigo'             => $codigo,
                'grupo_codigo'       => null,
                'nombre'             => $data['nombre'],
                'tipo'               => 'unica',
                'ventana_dias'       => null,
                'agrupar_por_ojo'    => 1,
                'genera_oportunidad' => 1,
                'activo'             => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('crm_procedure_rules')->insert($rows);
        $this->info('Insertadas ' . count($rows) . ' regla(s). Clasifica cada stub antes de activar Phase 2.');

        return self::SUCCESS;
    }
}
