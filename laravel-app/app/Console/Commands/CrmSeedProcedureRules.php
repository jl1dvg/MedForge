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

        $codes = DB::table('solicitud_procedimiento')
            ->whereNotNull('procedimiento')
            ->where('created_at', '>=', now()->subDays($days))
            ->distinct()
            ->pluck('procedimiento')
            ->filter(fn ($c) => $c !== '')
            ->values();

        if ($codes->isEmpty()) {
            $this->info('No procedure codes found in the last ' . $days . ' days.');
            return self::SUCCESS;
        }

        $existing = CrmProcedureRule::whereIn('codigo', $codes)->pluck('codigo')->flip();

        $toInsert = $codes->reject(fn ($c) => $existing->has($c));

        if ($toInsert->isEmpty()) {
            $this->info('All ' . $codes->count() . ' codes already have rules. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] Would insert' : 'Inserting') . ' ' . $toInsert->count() . ' stub rule(s):');
        $this->line($toInsert->implode(', '));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $now = now();
        $rows = $toInsert->map(fn ($codigo) => [
            'codigo'             => $codigo,
            'grupo_codigo'       => null,
            'nombre'             => $codigo, // stub — coordinator must update
            'tipo'               => 'unica',
            'ventana_dias'       => null,
            'agrupar_por_ojo'    => 1,
            'genera_oportunidad' => 1,
            'activo'             => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ])->values()->all();

        DB::table('crm_procedure_rules')->insert($rows);

        $this->info('Done. Review and classify stub rules in crm_procedure_rules before Phase 2 activation.');

        return self::SUCCESS;
    }
}
