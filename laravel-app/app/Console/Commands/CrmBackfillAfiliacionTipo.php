<?php

namespace App\Console\Commands;

use App\Listeners\CrmOpportunityListener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills crm_opportunities.afiliacion_tipo from solicitud_procedimiento.
 *
 * Resolution order per opportunity:
 *   1. Direct activity link (source_type=solicitud_procedimiento)
 *   2. Direct source on the opportunity
 *   3. Contact cedula → solicitud_procedimiento.hc_number  ← covers manual/whatsapp opps
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

    protected $description = 'Backfill crm_opportunities.afiliacion_tipo from the linked solicitud afiliacion';

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
            '%s | Total: %d | Updated: %d | Skipped (no solicitud/no change): %d',
            $dryRun ? '[DRY RUN]' : '[APPLIED]',
            $total,
            $updated,
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function resolveAfiliacion(object $opp, array $contactCedulas): ?string
    {
        // 1. Direct activity link to a solicitud
        $solicitudId = DB::table('crm_activities')
            ->where('opportunity_id', $opp->id)
            ->where('source_type', 'solicitud_procedimiento')
            ->whereNotNull('source_id')
            ->orderByDesc('id')
            ->value('source_id');

        // 2. Opportunity's own source
        if ($solicitudId === null && $opp->source_type === 'solicitud_procedimiento' && $opp->source_id !== null) {
            $solicitudId = $opp->source_id;
        }

        // 3. Contact cedula → most recent solicitud by hc_number
        if ($solicitudId === null && isset($opp->contact_id)) {
            $cedula = $contactCedulas[(int) $opp->contact_id] ?? null;
            if ($cedula !== null) {
                $solicitudId = DB::table('solicitud_procedimiento')
                    ->where('hc_number', $cedula)
                    ->orderByDesc('id')
                    ->value('id');
            }
        }

        if ($solicitudId === null) {
            return null;
        }

        $afiliacion = DB::table('solicitud_procedimiento')
            ->where('id', (int) $solicitudId)
            ->value('afiliacion');

        return $afiliacion !== null ? (string) $afiliacion : null;
    }
}
