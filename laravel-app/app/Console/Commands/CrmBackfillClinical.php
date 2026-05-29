<?php

namespace App\Console\Commands;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmBackfillClinical extends Command
{
    protected $signature = 'crm:backfill-clinical
                            {--source=all : all | solicitudes | examenes}
                            {--dry-run : Solo reporta, no escribe}
                            {--limit=500 : Máximo registros a procesar por fuente}';

    protected $description = 'Migra solicitudes y examenes históricos al CRM centralizado';

    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = (string) $this->option('source');
        $limit  = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        $totalMigrated = 0;
        $totalSkipped  = 0;

        if (in_array($source, ['all', 'solicitudes'], true)) {
            [$m, $s] = $this->backfillSolicitudes($dryRun, $limit);
            $totalMigrated += $m;
            $totalSkipped  += $s;
        }

        if (in_array($source, ['all', 'examenes'], true)) {
            [$m, $s] = $this->backfillExamenes($dryRun, $limit);
            $totalMigrated += $m;
            $totalSkipped  += $s;
        }

        $this->info("Total migrados: {$totalMigrated} | Saltados: {$totalSkipped}");

        return 0;
    }

    /** @return array{int, int} [migrated, skipped] */
    private function backfillSolicitudes(bool $dryRun, int $limit): array
    {
        $this->info("── Solicitudes ──");

        $rows = DB::table('solicitud_procedimiento as sp')
            ->leftJoin('patient_data as pd', 'pd.hc_number', '=', 'sp.hc_number')
            ->whereNull('sp.crm_opportunity_id')
            ->select([
                'sp.id',
                'sp.hc_number',
                'sp.tipo',
                'sp.procedimiento',
                'sp.fecha',
                DB::raw("CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) as patient_name"),
                'pd.celular as phone',
                'pd.id as patient_data_id',
            ])
            ->orderBy('sp.id')
            ->limit($limit)
            ->get();

        $this->info("  Solicitudes sin CRM (muestra {$limit}): {$rows->count()}");

        $migrated = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $phone = CrmContactResolverService::normalizePhone((string) ($row->phone ?? ''));
            $name  = trim((string) ($row->patient_name ?? ''));
            if ($name === '' || $name === ' ') {
                $name = 'HC ' . $row->hc_number;
            }
            $patientId = $row->patient_data_id ? (int) $row->patient_data_id : null;

            if ($phone === '' && $patientId === null) {
                $this->warn("  Skip solicitud #{$row->id} — sin teléfono ni patient_id");
                $skipped++;
                continue;
            }

            $title = mb_substr('Solicitud: ' . ($row->procedimiento ?: $row->tipo ?: 'Procedimiento médico'), 0, 200);

            if ($dryRun) {
                $this->line("  [dry] Solicitud #{$row->id} hc={$row->hc_number} → {$title}");
                $migrated++;
                continue;
            }

            try {
                $contact = $this->contactResolver->resolve(
                    phone: $phone ?: 'hc' . $row->hc_number,
                    name: $name,
                    cedula: null,
                    source: 'solicitud',
                    patientId: $patientId,
                );

                $opp = $this->opportunityService->createFromEvent(
                    contact: $contact,
                    title: $title,
                    source: 'solicitud',
                    sourceId: (int) $row->id,
                    sourceType: 'solicitud_procedimiento',
                );

                DB::table('solicitud_procedimiento')
                    ->where('id', $row->id)
                    ->update(['crm_opportunity_id' => $opp->id]);

                $migrated++;
            } catch (\Throwable $e) {
                $this->error("  Error en solicitud #{$row->id}: " . $e->getMessage());
                $skipped++;
            }
        }

        $this->info("  Solicitudes migradas: {$migrated} | Saltadas: {$skipped}");
        return [$migrated, $skipped];
    }

    /** @return array{int, int} [migrated, skipped] */
    private function backfillExamenes(bool $dryRun, int $limit): array
    {
        $this->info("── Exámenes ──");

        $rows = DB::table('consulta_examenes as ce')
            ->leftJoin('patient_data as pd', 'pd.hc_number', '=', 'ce.hc_number')
            ->whereNull('ce.crm_opportunity_id')
            ->select([
                'ce.id',
                'ce.hc_number',
                'ce.examen_nombre',
                'ce.examen_codigo',
                'ce.consulta_fecha',
                DB::raw("CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) as patient_name"),
                'pd.celular as phone',
                'pd.id as patient_data_id',
            ])
            ->orderBy('ce.id')
            ->limit($limit)
            ->get();

        $this->info("  Exámenes sin CRM (muestra {$limit}): {$rows->count()}");

        $migrated = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $phone = CrmContactResolverService::normalizePhone((string) ($row->phone ?? ''));
            $name  = trim((string) ($row->patient_name ?? ''));
            if ($name === '' || $name === ' ') {
                $name = 'HC ' . $row->hc_number;
            }
            $patientId = $row->patient_data_id ? (int) $row->patient_data_id : null;

            if ($phone === '' && $patientId === null) {
                $this->warn("  Skip examen #{$row->id} — sin teléfono ni patient_id");
                $skipped++;
                continue;
            }

            $title = mb_substr('Examen: ' . ($row->examen_nombre ?: $row->examen_codigo ?: 'Examen solicitado'), 0, 200);

            if ($dryRun) {
                $this->line("  [dry] Examen #{$row->id} hc={$row->hc_number} → {$title}");
                $migrated++;
                continue;
            }

            try {
                $contact = $this->contactResolver->resolve(
                    phone: $phone ?: 'hc' . $row->hc_number,
                    name: $name,
                    cedula: null,
                    source: 'examen',
                    patientId: $patientId,
                );

                $opp = $this->opportunityService->createFromEvent(
                    contact: $contact,
                    title: $title,
                    source: 'examen',
                    sourceId: (int) $row->id,
                    sourceType: 'consulta_examenes',
                );

                DB::table('consulta_examenes')
                    ->where('id', $row->id)
                    ->update(['crm_opportunity_id' => $opp->id]);

                $migrated++;
            } catch (\Throwable $e) {
                $this->error("  Error en examen #{$row->id}: " . $e->getMessage());
                $skipped++;
            }
        }

        $this->info("  Exámenes migrados: {$migrated} | Saltados: {$skipped}");
        return [$migrated, $skipped];
    }
}
