<?php

namespace App\Console\Commands;

use App\Models\CrmProcedureRule;
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

        $rawStrings = DB::table('solicitud_procedimiento')
            ->select('procedimiento', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('procedimiento')
            ->where('procedimiento', '!=', '')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('procedimiento')
            ->get();

        // Parse raw strings → unique codigos; collect unparseable separately
        $codigos     = [];
        $unparseable = [];
        foreach ($rawStrings as $row) {
            $parsed = CrmProcedureRule::parseProcedureCode($row->procedimiento);
            if ($parsed === null) {
                $unparseable[] = $row->procedimiento;
            } else {
                $codigos[$parsed['codigo']] = true;
            }
        }
        $codigos = array_keys($codigos);

        if (empty($codigos) && empty($unparseable)) {
            $this->info('✓ No procedure codes found in the last ' . $days . ' days.');
            return self::SUCCESS;
        }

        // Find which parsed codigos have no active rule
        $existing = CrmProcedureRule::whereIn('codigo', $codigos)
            ->where('activo', 1)
            ->pluck('codigo')
            ->flip();

        $gaps = array_filter($codigos, fn ($c) => !$existing->has($c));

        if (empty($gaps) && empty($unparseable)) {
            $this->info('✓ All ' . count($codigos) . ' procedure codes in the last ' . $days . ' days have active rules. Ready for Phase 2.');
            return self::SUCCESS;
        }

        $total = count($gaps) + count($unparseable);
        $this->error($total . ' issue(s) found — Phase 2 is NOT safe to activate:');

        if ($gaps) {
            $this->line('');
            $this->line('  Codes with no active rule (' . count($gaps) . '):');
            foreach ($gaps as $codigo) {
                $this->line('    - ' . $codigo);
            }
        }

        if ($unparseable) {
            $this->line('');
            $this->line('  Unparseable raw strings (' . count($unparseable) . '):');
            foreach ($unparseable as $u) {
                $this->line('    - ' . $u);
            }
        }

        $this->newLine();
        $this->line('Run: php artisan crm:seed-procedure-rules --dry-run');
        $this->line('Then classify each stub in crm_procedure_rules before activating CRM_OPPORTUNITY_MODEL=intent');

        return self::FAILURE;
    }
}
