<?php

namespace App\Console\Commands;

use App\Models\CrmProcedureRule;
use Illuminate\Console\Command;

class CrmClassifyProcedureRules extends Command
{
    protected $signature = 'crm:classify-procedure-rules
                            {--dry-run : Show changes without applying them}';

    protected $description = 'Apply initial classification to crm_procedure_rules (Phase 0 governance)';

    /**
     * Initial classification rules.
     * Each entry: list of codigos → attributes to set.
     * Only attributes that differ from the stub defaults need to be listed.
     */
    private function classifications(): array
    {
        return [
            [
                'label'   => 'Inyección intravítrea',
                'codigos' => ['67028', 'CYP-RVI-009'],
                'attrs'   => [
                    'tipo'              => 'recurrente',
                    'grupo_codigo'      => 'inyeccion_intravitrea',
                    'ventana_dias'      => 90,
                    'genera_oportunidad'=> 1,
                    'agrupar_por_ojo'   => 1,
                ],
            ],
            [
                'label'   => 'Láser retina',
                'codigos' => ['CYP-RVI-007', 'CYP-RVI-008', '281351', '281340'],
                'attrs'   => [
                    'tipo'              => 'recurrente',
                    'grupo_codigo'      => 'laser_retina',
                    'ventana_dias'      => 90,
                    'genera_oportunidad'=> 1,
                    'agrupar_por_ojo'   => 1,
                ],
            ],
            [
                'label'   => 'IPL ojo seco',
                'codigos' => ['CYP-OCU-035'],
                'attrs'   => [
                    'tipo'              => 'recurrente',
                    'grupo_codigo'      => 'ipl_ojo_seco',
                    'ventana_dias'      => 30,
                    'genera_oportunidad'=> 1,
                    'agrupar_por_ojo'   => 1,
                ],
            ],
            [
                'label'   => 'YAG láser',
                'codigos' => ['CYP-CCA-009', '281339'],
                'attrs'   => [
                    'tipo'              => 'recurrente',
                    'grupo_codigo'      => 'yag_laser',
                    'ventana_dias'      => 180,
                    'genera_oportunidad'=> 1,
                    'agrupar_por_ojo'   => 1,
                ],
            ],
            [
                'label'   => 'Consultas diagnósticas',
                'codigos' => ['SER-OFT-005', 'SER-OFT-006'],
                'attrs'   => [
                    'tipo'              => 'diagnostico',
                    'grupo_codigo'      => null,
                    'ventana_dias'      => null,
                    'genera_oportunidad'=> 0,
                    'agrupar_por_ojo'   => 0,
                ],
            ],
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $allCodigos = collect($this->classifications())->flatMap(fn ($g) => $g['codigos'])->all();

        // --- Validate all codes exist ---
        $existing = CrmProcedureRule::whereIn('codigo', $allCodigos)->pluck('codigo')->flip();
        $missing  = array_filter($allCodigos, fn ($c) => !$existing->has($c));

        if ($missing) {
            $this->error('The following codes are not in crm_procedure_rules and cannot be classified:');
            foreach ($missing as $c) {
                $this->line('  ✗ ' . $c);
            }
            $this->line('Run php artisan crm:seed-procedure-rules first.');
            return self::FAILURE;
        }

        $rows = CrmProcedureRule::whereIn('codigo', $allCodigos)
            ->orderBy('codigo')
            ->get()
            ->keyBy('codigo');

        // --- BEFORE / AFTER table ---
        $this->line('');
        $this->line(str_pad('CÓDIGO', 16) . str_pad('BEFORE tipo', 14) . str_pad('AFTER tipo', 14)
            . str_pad('grupo_codigo', 26) . str_pad('ventana', 10) . str_pad('gen_opp', 9) . 'agr_ojo');
        $this->line(str_repeat('─', 100));

        $totalUpdates = 0;

        foreach ($this->classifications() as $group) {
            $this->line('  ── ' . $group['label']);
            foreach ($group['codigos'] as $codigo) {
                $rule   = $rows[$codigo];
                $before = $rule->tipo;
                $after  = $group['attrs']['tipo'];
                $changed = ($before !== $after)
                    || ($rule->grupo_codigo !== $group['attrs']['grupo_codigo'])
                    || ($rule->ventana_dias !== $group['attrs']['ventana_dias'])
                    || ($rule->genera_oportunidad !== $group['attrs']['genera_oportunidad'])
                    || ($rule->agrupar_por_ojo !== $group['attrs']['agrupar_por_ojo']);

                $marker = $changed ? '→' : '=';
                $this->line(
                    '  ' . str_pad($codigo, 14)
                    . str_pad($before, 14)
                    . $marker . ' ' . str_pad($after, 12)
                    . str_pad((string) ($group['attrs']['grupo_codigo'] ?? 'null'), 26)
                    . str_pad((string) ($group['attrs']['ventana_dias'] ?? 'null'), 10)
                    . str_pad((string) $group['attrs']['genera_oportunidad'], 9)
                    . $group['attrs']['agrupar_por_ojo']
                );

                if ($changed) {
                    $totalUpdates++;
                }
            }
        }

        $this->line('');
        $this->line('Rows that will change: ' . $totalUpdates . ' of ' . count($allCodigos));

        if ($dryRun) {
            $this->line('');
            $this->info('[DRY RUN] No changes applied.');
            return self::SUCCESS;
        }

        // --- Apply ---
        $this->line('');
        $applied = 0;
        foreach ($this->classifications() as $group) {
            $count = CrmProcedureRule::whereIn('codigo', $group['codigos'])
                ->update(array_merge($group['attrs'], ['updated_at' => now()]));
            $applied += $count;
            $this->line('  ✓ ' . $group['label'] . ': ' . $count . ' row(s) updated');

            // Bust cache for each affected code
            foreach ($group['codigos'] as $codigo) {
                CrmProcedureRule::clearCache($codigo);
            }
        }

        $this->line('');
        $this->info('Done. ' . $applied . ' row(s) updated. Cache cleared for all classified codes.');

        return self::SUCCESS;
    }
}
