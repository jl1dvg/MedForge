<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmValidateProcedureRules extends Command
{
    protected $signature = 'crm:validate-procedure-rules
                            {--days=90 : Look-back window in days}';

    protected $description = 'List procedure codes from solicitud_procedimiento with no active crm_procedure_rules entry. Must return 0 gaps before Phase 2 activation.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $gaps = DB::table('solicitud_procedimiento as sp')
            ->selectRaw('DISTINCT sp.procedimiento')
            ->leftJoin('crm_procedure_rules as cpr', function ($join): void {
                $join->on('cpr.codigo', '=', 'sp.procedimiento')
                     ->where('cpr.activo', '=', 1);
            })
            ->whereNotNull('sp.procedimiento')
            ->where('sp.procedimiento', '!=', '')
            ->where('sp.created_at', '>=', now()->subDays($days))
            ->whereNull('cpr.id')
            ->pluck('procedimiento');

        if ($gaps->isEmpty()) {
            $this->info('✓ All procedure codes in the last ' . $days . ' days have active rules. Ready for Phase 2.');
            return self::SUCCESS;
        }

        $this->error($gaps->count() . ' procedure code(s) have no active rule (Phase 2 is NOT safe to activate):');
        foreach ($gaps as $codigo) {
            $this->line('  - ' . $codigo);
        }
        $this->newLine();
        $this->line('Run: php artisan crm:seed-procedure-rules --dry-run');
        $this->line('Then classify each stub in crm_procedure_rules before activating CRM_OPPORTUNITY_MODEL=intent');

        return self::FAILURE;
    }
}
