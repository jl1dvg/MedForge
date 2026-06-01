<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmBackfillAfiliacion extends Command
{
    protected $signature = 'crm:backfill-afiliacion {--dry-run}';
    protected $description = 'Populate afiliacion_categoria on crm_contacts from solicitud_procedimiento';

    /** Classify raw afiliacion text into a category. */
    public static function classify(?string $afiliacion): ?string
    {
        if ($afiliacion === null || trim($afiliacion) === '') {
            return null;
        }
        $lower = mb_strtolower(trim($afiliacion), 'UTF-8');

        if (preg_match('/iess|issfa|isspol|msp|ministerio|salud\s*publica|red\s*publica/', $lower)) {
            return 'publico';
        }
        if (str_contains($lower, 'particular') || str_starts_with($lower, 'par -')) {
            return 'particular';
        }
        if (str_contains($lower, 'fundaci')) {
            return 'fundacional';
        }
        return 'privado';
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) $this->warn('Dry-run — no se escribirá nada.');

        // Batch-fetch: for each contact with cedula, get the most recent solicitud afiliacion
        $rows = DB::table('crm_contacts as cc')
            ->join(
                DB::raw('(SELECT hc_number, afiliacion FROM solicitud_procedimiento sp1
                          WHERE sp1.id = (SELECT MAX(id) FROM solicitud_procedimiento WHERE hc_number = sp1.hc_number)) AS sp'),
                'sp.hc_number', '=', 'cc.cedula'
            )
            ->whereNotNull('cc.cedula')
            ->where('cc.cedula', '!=', '')
            ->select('cc.id', 'cc.afiliacion_categoria', 'sp.afiliacion')
            ->get();

        $this->info("Contactos con solicitud: {$rows->count()}");

        $updated = 0;
        foreach ($rows as $row) {
            $cat = self::classify($row->afiliacion);
            if ($cat === $row->afiliacion_categoria) continue;

            if (!$dryRun) {
                DB::table('crm_contacts')->where('id', $row->id)->update(['afiliacion_categoria' => $cat]);
            }
            $updated++;
        }

        $this->info("Actualizados: {$updated}");
        return 0;
    }
}
