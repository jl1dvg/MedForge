<?php

namespace App\Console\Commands;

use App\Listeners\CrmOpportunityListener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills crm_opportunities.afiliacion_tipo from solicitud_procedimiento.
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

        DB::table('crm_opportunities')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($dryRun, &$total, &$updated, &$skipped): void {
                foreach ($rows as $opp) {
                    $total++;

                    // Find the most recent solicitud linked via activities or direct source
                    $solicitudId = DB::table('crm_activities')
                        ->where('opportunity_id', $opp->id)
                        ->where('source_type', 'solicitud_procedimiento')
                        ->whereNotNull('source_id')
                        ->orderByDesc('id')
                        ->value('source_id');

                    if ($solicitudId === null && $opp->source_type === 'solicitud_procedimiento') {
                        $solicitudId = $opp->source_id;
                    }

                    if ($solicitudId === null) {
                        $skipped++;
                        continue;
                    }

                    $afiliacion = DB::table('solicitud_procedimiento')
                        ->where('id', (int) $solicitudId)
                        ->value('afiliacion');

                    if ($afiliacion === null) {
                        $skipped++;
                        continue;
                    }

                    $tipo = CrmOpportunityListener::classifyAfiliacion((string) $afiliacion);

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
}
