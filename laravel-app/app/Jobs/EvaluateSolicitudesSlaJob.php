<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Solicitudes\Services\SolicitudesSlaSettingsService;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Evalúa SLAs de solicitudes activas cada 30 min.
 * Por cada solicitud en estado crítico o vencido que no tenga tarea SLA abierta,
 * crea una tarea CRM asignada al responsable.
 */
class EvaluateSolicitudesSlaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    // Estados terminales — no evaluar SLA.
    private const SKIP_ESTADOS = ['completado', 'programada'];

    public function handle(): void
    {
        $now = new DateTimeImmutable();
        $slaService = new SolicitudesSlaSettingsService();
        $baseRules = $slaService->baseRules();

        $solicitudes = $this->fetchActiveSolicitudes();
        $created = 0;

        foreach ($solicitudes as $row) {
            try {
                $status = $this->computeSlaStatus($row, $now, $baseRules);
                if (!in_array($status, ['critico', 'vencido'], true)) {
                    continue;
                }

                if ($this->hasOpenSlaTask((int) $row->id)) {
                    continue;
                }

                $this->createSlaTask($row, $status, $baseRules);
                $created++;
            } catch (Throwable $e) {
                Log::warning('solicitudes.sla_job.row_error', [
                    'solicitud_id' => $row->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('solicitudes.sla_job.done', [
            'evaluated' => count($solicitudes),
            'tasks_created' => $created,
            'at' => now()->toDateTimeString(),
        ]);
    }

    /** @return list<object> */
    private function fetchActiveSolicitudes(): array
    {
        $skipPlaceholders = implode(',', array_fill(0, count(self::SKIP_ESTADOS), '?'));

        return DB::select("
            SELECT
                sp.id,
                sp.hc_number,
                sp.estado,
                sp.afiliacion,
                sp.created_at,
                sp.derivacion_fecha_vigencia_sel AS derivacion_fecha_vigencia,
                sp.fecha AS fecha_cirugia,
                sc.responsable_id   AS crm_responsable_id,
                sc.contacto_email   AS crm_contacto_email,
                sc.contacto_telefono AS crm_contacto_telefono,
                sol_cl.completado_at AS etapa_started_at
            FROM solicitud_procedimiento sp
            LEFT JOIN solicitud_crm_detalles sc ON sc.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id, MAX(completado_at) AS completado_at
                FROM solicitud_checklist
                WHERE checked = 1
                GROUP BY solicitud_id
            ) sol_cl ON sol_cl.solicitud_id = sp.id
            WHERE sp.estado NOT IN ({$skipPlaceholders})
              AND sp.estado IS NOT NULL
              AND sp.estado != ''
              AND sp.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ", self::SKIP_ESTADOS);
    }

    /** @param array<string,array<string,mixed>> $baseRules */
    private function computeSlaStatus(object $row, DateTimeImmutable $now, array $baseRules): string
    {
        $ruleKey = $this->resolveRuleKey($row);
        $rule = $baseRules[$ruleKey] ?? $baseRules['otros'];

        $createdAt = $this->parseDate($row->created_at) ?? $now;

        if ($ruleKey === 'publico') {
            $vigencia = $this->parseDateEndOfDay($row->derivacion_fecha_vigencia ?? null);
            $deadline = $vigencia ?? $createdAt->add(
                new DateInterval('PT' . max(1, (int) ($rule['missing_derivacion_hours'] ?? 4)) . 'H')
            );
        } else {
            $deadline = $createdAt->add(
                new DateInterval('PT' . max(1, (int) ($rule['hours'] ?? 48)) . 'H')
            );
        }

        $secondsRemaining = $deadline->getTimestamp() - $now->getTimestamp();
        $hoursRemaining = $secondsRemaining / 3600;

        if ($hoursRemaining < 0) {
            return 'vencido';
        }
        if ($hoursRemaining <= (int) ($rule['critical_hours'] ?? 6)) {
            return 'critico';
        }

        return 'ok';
    }

    private function resolveRuleKey(object $row): string
    {
        $afiliacion = strtoupper(trim((string) ($row->afiliacion ?? '')));

        if (preg_match('/\b(IESS|ISSFA|ISSPOL|MSP)\b/', $afiliacion)) {
            return 'publico';
        }
        if (str_contains($afiliacion, 'PARTICULAR') || preg_match('/\bPAR\b/', $afiliacion)) {
            return 'particular';
        }
        if (str_contains($afiliacion, 'FUNDACION') || str_contains($afiliacion, 'FUNDACIÓN')) {
            return 'fundacional';
        }
        if (preg_match('/\b(SEGURO|ASEGURADORA|PRIVAD)\b/i', $afiliacion)) {
            return 'privado';
        }

        return 'otros';
    }

    private function hasOpenSlaTask(int $solicitudId): bool
    {
        return DB::table('crm_tasks')
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('category', 'sla')
            ->whereNotIn('status', ['completada', 'cancelada'])
            ->exists();
    }

    /** @param array<string,array<string,mixed>> $baseRules */
    private function createSlaTask(object $row, string $status, array $baseRules): void
    {
        $ruleKey = $this->resolveRuleKey($row);
        $rule    = $baseRules[$ruleKey] ?? $baseRules['otros'];
        $label   = (string) ($rule['label'] ?? 'Seguimiento SLA');
        $action  = (string) ($rule['action'] ?? 'Revisar solicitud');
        $emoji   = $status === 'vencido' ? '🔴' : '🟠';

        $columns = DB::getSchemaBuilder()->getColumnListing('crm_tasks');

        $task = [];
        $this->setIfCol($task, $columns, 'source_module', 'solicitudes');
        $this->setIfCol($task, $columns, 'source_ref_id', (string) $row->id);
        $this->setIfCol($task, $columns, 'hc_number', (string) ($row->hc_number ?? ''));
        $this->setIfCol($task, $columns, 'title', "{$emoji} SLA {$status}: {$label}");
        $this->setIfCol($task, $columns, 'description', $action);
        $this->setIfCol($task, $columns, 'status', 'pendiente');
        $this->setIfCol($task, $columns, 'priority', $status === 'vencido' ? 'urgente' : 'alta');
        $this->setIfCol($task, $columns, 'category', 'sla');
        $this->setIfCol($task, $columns, 'assigned_to', $row->crm_responsable_id ?? null);
        $this->setIfCol($task, $columns, 'due_date', now()->toDateString());
        $this->setIfCol($task, $columns, 'due_at', now()->toDateTimeString());
        $this->setIfCol($task, $columns, 'created_at', now()->toDateTimeString());
        $this->setIfCol($task, $columns, 'updated_at', now()->toDateTimeString());

        DB::table('crm_tasks')->insert($task);
    }

    /**
     * @param array<string,mixed> $task
     * @param list<string> $columns
     */
    private function setIfCol(array &$task, array $columns, string $col, mixed $value): void
    {
        if (in_array($col, $columns, true)) {
            $task[$col] = $value;
        }
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateEndOfDay(?string $value): ?DateTimeImmutable
    {
        $date = $this->parseDate($value);
        if ($date === null) {
            return null;
        }
        return $date->setTime(23, 59, 59);
    }
}
