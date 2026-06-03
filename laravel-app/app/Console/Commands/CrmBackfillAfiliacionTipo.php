<?php

namespace App\Console\Commands;

use App\Listeners\CrmOpportunityListener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills crm_opportunities.afiliacion_tipo from patient_data.afiliacion.
 *
 * Resolution order per opportunity:
 *   1. Contact cedula → patient_data.hc_number
 *   2. Examen source → consulta_examenes.hc_number → patient_data.hc_number
 *
 * Usage:
 *   php artisan crm:backfill-afiliacion            # process all
 *   php artisan crm:backfill-afiliacion --dry-run  # preview counts only
 *   php artisan crm:backfill-afiliacion --chunk=500
 */
class CrmBackfillAfiliacionTipo extends Command
{
    protected $signature = 'crm:backfill-afiliacion
                            {--dry-run : Preview without writing}
                            {--chunk=200 : Records per batch}';

    protected $description = 'Backfill crm_opportunities.afiliacion_tipo from patient_data.afiliacion';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        $total   = 0;
        $updated = 0;
        $skipped = 0;

        // Pre-load contact cedulas to avoid per-row queries (contact_id → cedula)
        $contactCedulas = DB::table('crm_contacts')
            ->whereNotNull('cedula')
            ->pluck('cedula', 'id')
            ->all();

        DB::table('crm_opportunities')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($dryRun, $contactCedulas, &$total, &$updated, &$skipped): void {
                foreach ($rows as $opp) {
                    $total++;

                    $afiliacion = $this->resolveAfiliacion($opp, $contactCedulas);

                    if ($afiliacion === null) {
                        $skipped++;
                        continue;
                    }

                    $tipo = CrmOpportunityListener::classifyAfiliacion($afiliacion);

                    if ($opp->afiliacion_tipo === $tipo) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('crm_opportunities')
                            ->where('id', $opp->id)
                            ->update(['afiliacion_tipo' => $tipo]);
                    }

                    $updated++;
                }
            });

        $this->info(sprintf(
            '%s | Total: %d | Updated: %d | Skipped (no patient_data/no change): %d',
            $dryRun ? '[DRY RUN]' : '[APPLIED]',
            $total,
            $updated,
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function resolveAfiliacion(object $opp, array $contactCedulas): ?string
    {
        $hcForPatient = $contactCedulas[(int) ($opp->contact_id ?? 0)] ?? null;

        if ($hcForPatient !== null) {
            $afiliacion = DB::table('patient_data')
                ->where('hc_number', $hcForPatient)
                ->value('afiliacion');

            if ($afiliacion !== null) {
                return (string) $afiliacion;
            }
        }

        if ($opp->source_type === 'consulta_examenes' && $opp->source_id !== null) {
            $hcForPatient = DB::table('consulta_examenes')
                ->where('id', (int) $opp->source_id)
                ->value('hc_number');

            if ($hcForPatient !== null) {
                $afiliacion = DB::table('patient_data')
                    ->where('hc_number', $hcForPatient)
                    ->value('afiliacion');

                if ($afiliacion !== null) {
                    return (string) $afiliacion;
                }
            }
        }

        return null;
    }
}
