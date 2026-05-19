<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Models\SolicitudEstadoLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cruza filas normalizadas de SigCenter con solicitud_procedimiento
 * para actualizar campos sigcenter_* y avanzar etapas kanban automáticamente.
 */
class SolicitudesSigcenterSyncService
{
    private const ESTADO_MAP = [
        // SigCenter estado_agenda  =>  etapa kanban destino
        'programado'   => 'programada',
        'agendado'     => 'programada',
        'pre-operatorio' => 'programada',
        'atendido'     => 'completado',
        'completado'   => 'completado',
        'realizado'    => 'completado',
    ];

    // Estos estados de SigCenter se loguean como nota pero NO cambian etapa kanban.
    private const ESTADOS_NOTA_ONLY = [
        'anulado',
        'no asistió',
        'no asistio',
        'cancelado',
        'suspendido',
    ];

    /** @var array<string,mixed>|null */
    private ?array $solicitudColumns = null;

    /**
     * Procesa un lote de filas ya normalizadas por IndexAdmisionesSyncService
     * y devuelve estadísticas del cruce.
     *
     * @param array<int,array<string,mixed>> $normalizedRows
     * @return array{matched:int, updated:int, advanced:int, noted:int, errors:int}
     */
    public function syncFromRows(array $normalizedRows): array
    {
        $stats = ['matched' => 0, 'updated' => 0, 'advanced' => 0, 'noted' => 0, 'errors' => 0];

        foreach ($normalizedRows as $row) {
            try {
                $result = $this->processRow($row);
                if ($result === null) {
                    continue;
                }
                $stats['matched']++;
                if ($result['updated']) {
                    $stats['updated']++;
                }
                if ($result['advanced']) {
                    $stats['advanced']++;
                }
                if ($result['noted']) {
                    $stats['noted']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('solicitudes.sigcenter_sync.row_error', [
                    'form_id'  => $row['form_id'] ?? null,
                    'hc'       => $row['hcNumber'] ?? null,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $row Fila normalizada de IndexAdmisionesSyncService
     * @return array{updated:bool,advanced:bool,noted:bool}|null  null = sin match
     */
    private function processRow(array $row): ?array
    {
        $formId   = trim((string) ($row['form_id'] ?? ''));
        $hc       = trim((string) ($row['hcNumber'] ?? ''));
        $estadoSC = strtolower(trim((string) ($row['estado_agenda'] ?? '')));

        if ($formId === '' || $hc === '') {
            return null;
        }

        $solicitud = DB::table('solicitud_procedimiento')
            ->where('form_id', $formId)
            ->where('hc_number', $hc)
            ->orderByDesc('id')
            ->first();

        if ($solicitud === null) {
            return null;
        }

        $solicitudId   = (int) $solicitud->id;
        $kanbanActual  = strtolower(trim((string) ($solicitud->estado ?? '')));
        $result        = ['updated' => false, 'advanced' => false, 'noted' => false];

        // — Actualizar campos sigcenter_* —
        $sigcenterUpdate = $this->buildSigcenterUpdate($row);
        if ($sigcenterUpdate !== []) {
            DB::table('solicitud_procedimiento')
                ->where('id', $solicitudId)
                ->update($sigcenterUpdate);
            $result['updated'] = true;
        }

        // — Avance de etapa kanban —
        if ($estadoSC !== '') {
            $etapaDestino = $this->resolveTargetStage($estadoSC);

            if ($etapaDestino !== null && !$this->isAlreadyAt($kanbanActual, $etapaDestino)) {
                $this->advanceStage($solicitudId, $kanbanActual, $etapaDestino);
                $result['advanced'] = true;
                $result['noted']    = true;
            } elseif ($this->isNotaOnly($estadoSC)) {
                $this->addNote($solicitudId, "SigCenter reportó estado: {$row['estado_agenda']}.");
                $result['noted'] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildSigcenterUpdate(array $row): array
    {
        $columns = $this->solicitudColumns();
        $update  = [];

        $map = [
            'sigcenter_fecha_inicio'      => $this->buildFechaInicio($row),
            'sigcenter_agenda_id'         => $row['form_id'] ?? null,
            'sigcenter_procedimiento_id'  => $row['procedimiento_proyectado'] ?? null,
            'sigcenter_last_seen_at'      => now()->toDateTimeString(),
        ];

        foreach ($map as $col => $value) {
            if (in_array($col, $columns, true) && $value !== null && $value !== '') {
                $update[$col] = $value;
            }
        }

        return $update;
    }

    private function buildFechaInicio(array $row): ?string
    {
        $fecha = trim((string) ($row['fecha'] ?? ''));
        $hora  = trim((string) ($row['hora'] ?? ''));
        if ($fecha === '') {
            return null;
        }

        return $hora !== '' ? "{$fecha} {$hora}" : $fecha;
    }

    private function resolveTargetStage(string $estadoSC): ?string
    {
        foreach (self::ESTADO_MAP as $keyword => $etapa) {
            if (str_contains($estadoSC, $keyword)) {
                return $etapa;
            }
        }

        return null;
    }

    private function isNotaOnly(string $estadoSC): bool
    {
        foreach (self::ESTADOS_NOTA_ONLY as $keyword) {
            if (str_contains($estadoSC, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isAlreadyAt(string $kanbanActual, string $etapaDestino): bool
    {
        // No retroceder etapas. 'completado' siempre es terminal.
        $order = [
            'recibida'          => 1,
            'llamado'           => 2,
            'en-atencion'       => 3,
            'revision-codigos'  => 4,
            'espera-documentos' => 5,
            'apto-oftalmologo'  => 6,
            'apto-anestesia'    => 7,
            'listo-para-agenda' => 8,
            'programada'        => 9,
            'completado'        => 10,
        ];

        $actual  = $order[$kanbanActual]  ?? 0;
        $destino = $order[$etapaDestino] ?? 0;

        return $actual >= $destino;
    }

    private function advanceStage(int $solicitudId, string $estadoAnterior, string $estadoNuevo): void
    {
        // Actualiza el campo estado directamente (sin pasar por el Parity Service
        // para no generar side-effects de checklist en un sync automático).
        DB::table('solicitud_procedimiento')
            ->where('id', $solicitudId)
            ->update([
                'estado'     => $estadoNuevo,
                'updated_at' => now()->toDateTimeString(),
            ]);

        // Registrar en audit trail.
        try {
            SolicitudEstadoLog::create([
                'solicitud_id'   => $solicitudId,
                'estado_anterior' => $estadoAnterior !== '' ? $estadoAnterior : null,
                'estado_nuevo'   => $estadoNuevo,
                'user_id'        => null,
                'nota'           => 'Avance automático desde SigCenter',
                'origen'         => 'sigcenter',
            ]);
        } catch (Throwable) {
            // Tabla puede no existir aún — nunca bloquear el sync.
        }

        $this->addNote(
            $solicitudId,
            "SigCenter actualizó estado a \"{$estadoNuevo}\". Etapa kanban avanzada automáticamente."
        );
    }

    private function addNote(int $solicitudId, string $nota): void
    {
        $columns = $this->noteColumns();
        if ($columns === []) {
            return;
        }

        $payload = ['solicitud_id' => $solicitudId, 'nota' => $nota];
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = now()->toDateTimeString();
        }

        DB::table('solicitud_crm_notas')->insert($payload);
    }

    /** @return list<string> */
    private function solicitudColumns(): array
    {
        if ($this->solicitudColumns === null) {
            try {
                $this->solicitudColumns = DB::getSchemaBuilder()->getColumnListing('solicitud_procedimiento');
            } catch (Throwable) {
                $this->solicitudColumns = [];
            }
        }

        return $this->solicitudColumns;
    }

    /** @return list<string> */
    private function noteColumns(): array
    {
        try {
            return DB::getSchemaBuilder()->getColumnListing('solicitud_crm_notas');
        } catch (Throwable) {
            return [];
        }
    }
}
