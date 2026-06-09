<?php

namespace App\Console\Commands;

use App\Models\CrmOpportunity;
use App\Models\CrmProcedureRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmIntentMonitor extends Command
{
    protected $signature = 'crm:intent-monitor
                            {--date= : Fecha a analizar (YYYY-MM-DD, default hoy)}';

    protected $description = 'Monitorea el motor de episodios CRM intent — uso durante la primera semana de activación';

    public function handle(): int
    {
        $date = $this->option('date') ?? today()->toDateString();

        $this->line('');
        $this->info("CRM Intent Monitor — {$date}");
        $this->line(str_repeat('=', 42));

        $this->sectionOppsHoy($date);
        $this->sectionByType($date);
        $this->sectionByGroup($date);
        $this->sectionNullGroup($date);
        $this->sectionNullType($date);
        $this->sectionDuplicatesActive();
        $this->sectionContinuity($date);
        $this->sectionZombieAlert();
        $this->sectionRecurrenteNullVentana();

        $this->line('');

        return self::SUCCESS;
    }

    // =========================================================================
    // Sections
    // =========================================================================

    private function sectionOppsHoy(string $date): void
    {
        $total          = CrmOpportunity::query()->whereDate('created_at', $date)->count();
        $uniqueContacts = CrmOpportunity::query()->whereDate('created_at', $date)
            ->distinct('contact_id')->count('contact_id');

        $this->line('');
        $this->comment('── Resumen del día ──────────────────────────');
        $this->line("  Oportunidades creadas:   <fg=white>{$total}</>");
        $this->line("  Contactos únicos:        <fg=white>{$uniqueContacts}</>");

        if ($total > 0 && $uniqueContacts > 0) {
            $ratio = round($total / $uniqueContacts, 2);
            $color = $ratio > 2.0 ? 'yellow' : 'green';
            $this->line("  Ratio episodios/paciente: <fg={$color}>{$ratio}</>");
        }
    }

    private function sectionByType(string $date): void
    {
        $rows = CrmOpportunity::query()
            ->whereDate('created_at', $date)
            ->select('opportunity_type', DB::raw('COUNT(*) as n'))
            ->groupBy('opportunity_type')
            ->orderByDesc('n')
            ->get();

        $this->line('');
        $this->comment('── Por opportunity_type ─────────────────────');
        foreach ($rows as $row) {
            $label = $row->opportunity_type ?? '<fg=yellow>(null — legacy o sin regla)</>';
            $this->line(sprintf('  %-40s %d', $label, $row->n));
        }
    }

    private function sectionByGroup(string $date): void
    {
        $rows = CrmOpportunity::query()
            ->whereDate('created_at', $date)
            ->whereNotNull('procedure_group')
            ->select('procedure_group', DB::raw('COUNT(*) as n'))
            ->groupBy('procedure_group')
            ->orderByDesc('n')
            ->get();

        $this->line('');
        $this->comment('── Por procedure_group ──────────────────────');
        foreach ($rows as $row) {
            $this->line(sprintf('  %-40s %d', $row->procedure_group, $row->n));
        }
    }

    private function sectionNullGroup(string $date): void
    {
        $rows = CrmOpportunity::query()
            ->whereDate('created_at', $date)
            ->whereNull('procedure_group')
            ->get(['id', 'contact_id', 'source', 'opportunity_type', 'created_at']);

        $count = $rows->count();
        $this->line('');
        $this->comment('── procedure_group = NULL ───────────────────');

        if ($count === 0) {
            $this->line('  <fg=green>Ninguno ✓</>');
            return;
        }

        $this->warn("  {$count} oportunidad(es) sin procedure_group (procedimiento no parseable o legacy):");
        foreach ($rows->take(10) as $row) {
            $this->line(sprintf(
                '  opp#%d  contact#%d  source=%-12s  type=%-12s  %s',
                $row->id,
                $row->contact_id,
                $row->source,
                $row->opportunity_type ?? 'null',
                $row->created_at->toDateTimeString(),
            ));
        }
        if ($count > 10) {
            $this->line("  ... y " . ($count - 10) . " más");
        }
    }

    private function sectionNullType(string $date): void
    {
        $rows = CrmOpportunity::query()
            ->whereDate('created_at', $date)
            ->whereNull('opportunity_type')
            ->get(['id', 'contact_id', 'source', 'procedure_group', 'created_at']);

        $count = $rows->count();
        $this->line('');
        $this->comment('── opportunity_type = NULL ──────────────────');

        if ($count === 0) {
            $this->line('  <fg=green>Ninguno ✓</>');
            return;
        }

        $this->warn("  {$count} oportunidad(es) sin opportunity_type:");
        foreach ($rows->take(10) as $row) {
            $this->line(sprintf(
                '  opp#%d  contact#%d  source=%-12s  group=%-25s  %s',
                $row->id,
                $row->contact_id,
                $row->source,
                $row->procedure_group ?? 'null',
                $row->created_at->toDateTimeString(),
            ));
        }
        if ($count > 10) {
            $this->line("  ... y " . ($count - 10) . " más");
        }
    }

    private function sectionDuplicatesActive(): void
    {
        $dupes = CrmOpportunity::query()
            ->active()
            ->whereNotNull('procedure_group')
            ->whereNotNull('opportunity_type')
            ->select('contact_id', 'procedure_group', 'lateralidad', DB::raw('COUNT(*) as n'))
            ->groupBy('contact_id', 'procedure_group', 'lateralidad')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->line('');
        $this->comment('── Duplicados activos (contact+group+lateralidad) ──');

        if ($dupes->isEmpty()) {
            $this->line('  <fg=green>Ninguno ✓</>');
            return;
        }

        $this->error("  ¡{$dupes->count()} combinación(es) con episodios duplicados activos!");
        foreach ($dupes as $d) {
            $lat = $d->lateralidad ?? 'null';
            $this->line("  contact#{$d->contact_id}  group={$d->procedure_group}  lat={$lat}  → {$d->n} opps activas");
        }
    }

    private function sectionContinuity(string $date): void
    {
        $rows = CrmOpportunity::query()
            ->whereDate('created_at', $date)
            ->where('continuity_flag', 1)
            ->get(['id', 'contact_id', 'procedure_group', 'lateralidad', 'previous_opportunity_id']);

        $this->line('');
        $this->comment('── continuity_flag = 1 (episodios encadenados) ──');

        if ($rows->isEmpty()) {
            $this->line('  Ninguno hoy');
            return;
        }

        $this->line("  {$rows->count()} episodio(s) continuación:");
        foreach ($rows as $row) {
            $lat = $row->lateralidad ?? 'AO';
            $this->line(sprintf(
                '  opp#%d  contact#%d  group=%-25s  lat=%-4s  prev=#%d',
                $row->id,
                $row->contact_id,
                $row->procedure_group ?? 'null',
                $lat,
                $row->previous_opportunity_id ?? 0,
            ));
        }
    }

    private function sectionZombieAlert(): void
    {
        // Opps that are active but would not pass the zombie cutoff for their type
        $zombies = CrmOpportunity::query()
            ->active()
            ->whereNotNull('opportunity_type')
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    // unica: idle > 180 days
                    $q2->where('opportunity_type', 'unica')
                        ->where('last_activity_at', '<', now()->subDays(180));
                })->orWhere(function ($q2): void {
                    // diagnostico: idle > 30 days
                    $q2->where('opportunity_type', 'diagnostico')
                        ->where('last_activity_at', '<', now()->subDays(30));
                })->orWhere(function ($q2): void {
                    // recurrente with ventana_dias=null: idle > 90 days (default fallback)
                    $q2->where('opportunity_type', 'recurrente')
                        ->whereNull('last_activity_at');
                });
            })
            ->count();

        // recurrente with ventana_dias: need a join — approximated with 90-day fallback for count
        $zombiesRecurrente = DB::table('crm_opportunities')
            ->join('crm_procedure_rules', 'crm_opportunities.procedure_group', '=', 'crm_procedure_rules.grupo_codigo')
            ->where('crm_procedure_rules.tipo', 'recurrente')
            ->whereNotNull('crm_procedure_rules.ventana_dias')
            ->whereNotIn('crm_opportunities.stage', [CrmOpportunity::STAGE_GANADO, CrmOpportunity::STAGE_PERDIDO])
            ->whereRaw('crm_opportunities.last_activity_at < DATE_SUB(NOW(), INTERVAL crm_procedure_rules.ventana_dias DAY)')
            ->count();

        $total = $zombies + $zombiesRecurrente;

        $this->line('');
        $this->comment('── Episodios zombie (activos pero caducados) ───');

        if ($total === 0) {
            $this->line('  <fg=green>Ninguno ✓</>');
            return;
        }

        $this->warn("  {$total} episodio(s) activo(s) que serían ignorados por findActiveCompatible():");
        $this->line("  (No se cierran automáticamente — solo no se reutilizan como episodio base)");
    }

    private function sectionRecurrenteNullVentana(): void
    {
        $rules = CrmProcedureRule::query()
            ->where('tipo', 'recurrente')
            ->where('activo', 1)
            ->whereNull('ventana_dias')
            ->get(['codigo', 'grupo_codigo', 'nombre']);

        $this->line('');
        $this->comment('── ⚠ Reglas recurrentes sin ventana_dias ──────');

        if ($rules->isEmpty()) {
            $this->line('  <fg=green>Ninguna — todas las reglas recurrentes tienen ventana_dias ✓</>');
            return;
        }

        $this->warn("  {$rules->count()} regla(s) recurrente(s) activas con ventana_dias = NULL:");
        $this->line('  Se usará fallback de 90 días para el zombie cutoff.');
        foreach ($rules as $rule) {
            $grupo = $rule->grupo_codigo ? " (grupo: {$rule->grupo_codigo})" : '';
            $this->line("  [{$rule->codigo}]{$grupo} — {$rule->nombre}");
        }
        $this->line('  → Considera ejecutar: php artisan crm:seed-procedure-rules para completarlas');
    }
}
