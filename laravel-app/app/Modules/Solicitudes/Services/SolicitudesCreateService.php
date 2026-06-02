<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SolicitudesCreateService
{
    /**
     * Crea una o más solicitudes de procedimiento quirúrgico.
     *
     * @param array{
     *     hcNumber: string,
     *     form_id: string,
     *     solicitudes: array<int, array<string,mixed>>
     * } $data
     * @return array{success: bool, message: string, ids?: list<int>}
     */
    public function guardar(array $data): array
    {
        if (empty($data['hcNumber']) || empty($data['form_id']) || !isset($data['solicitudes']) || !is_array($data['solicitudes'])) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        $hcNumber    = trim((string) $data['hcNumber']);
        $formId      = trim((string) $data['form_id']);
        $solicitudes = $data['solicitudes'];

        if ($solicitudes === []) {
            return ['success' => false, 'message' => 'No se recibieron solicitudes para guardar'];
        }

        $now = now()->toDateTimeString();

        try {
            $rows = $this->buildRows($hcNumber, $formId, $solicitudes, $now);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($rows === []) {
            return ['success' => false, 'message' => 'No se recibieron solicitudes válidas para guardar'];
        }

        try {
            DB::transaction(function () use ($rows): void {
                DB::table('solicitud_procedimiento')->upsert(
                    $rows,
                    ['hc_number', 'form_id', 'secuencia'],
                    [
                        'tipo',
                        'afiliacion',
                        'procedimiento',
                        'doctor',
                        'fecha',
                        'duracion',
                        'ojo',
                        'prioridad',
                        'producto',
                        'observacion',
                        'sesiones',
                        'lente_id',
                        'lente_nombre',
                        'lente_poder',
                        'lente_observacion',
                        'incision',
                    ],
                );
            });
        } catch (\Throwable $e) {
            Log::error('SolicitudesCreateService::guardar error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error interno al guardar la solicitud. Por favor intente nuevamente.'];
        }

        $ids = DB::table('solicitud_procedimiento')
            ->where('hc_number', $hcNumber)
            ->where('form_id', $formId)
            ->whereIn('secuencia', array_column($rows, 'secuencia'))
            ->orderBy('secuencia')
            ->pluck('id')
            ->map(static fn(mixed $id): int => (int) $id)
            ->values()
            ->all();

        return [
            'success' => true,
            'message' => count($rows) . ' solicitud(es) guardada(s) exitosamente',
            'ids'     => $ids,
        ];
    }

    /**
     * @param array<int, mixed> $solicitudes
     * @return list<array<string, mixed>>
     */
    private function buildRows(string $hcNumber, string $formId, array $solicitudes, string $now): array
    {
        $rows = [];
        $missing = [];

        foreach ($solicitudes as $idx => $sol) {
            if (!is_array($sol)) {
                continue;
            }

            $procedimiento = $this->clean($sol['procedimiento'] ?? null);
            $secuencia = isset($sol['secuencia']) && is_numeric($sol['secuencia'])
                ? (int) $sol['secuencia']
                : ($idx + 1);

            if ($procedimiento === null) {
                $missing[] = $secuencia;
                continue;
            }

            [$lenteId, $lenteNombre, $lentePoder, $lenteObs, $incision, $ojoFallback] = $this->extractDetalleFields($sol);
            $ojo = $this->normOjo($sol['ojo'] ?? null) ?? $ojoFallback;

            $rows[] = [
                'hc_number'         => $hcNumber,
                'form_id'           => $formId,
                'secuencia'         => $secuencia,
                'tipo'              => $this->clean($sol['tipo'] ?? null),
                'afiliacion'        => $this->clean($sol['afiliacion'] ?? null),
                'procedimiento'     => $procedimiento,
                'doctor'            => $this->clean($sol['doctor'] ?? null),
                'fecha'             => $this->normFecha($sol['fecha'] ?? null),
                'duracion'          => $this->normInt($sol['duracion'] ?? null),
                'ojo'               => $ojo,
                'prioridad'         => $this->normPrioridad($sol['prioridad'] ?? null),
                'producto'          => $this->clean($sol['producto'] ?? null),
                'observacion'       => $this->clean($sol['observacion'] ?? null),
                'sesiones'          => $this->clean($sol['sesiones'] ?? null),
                'lente_id'          => $this->clean($sol['lente_id'] ?? null) ?? $lenteId,
                'lente_nombre'      => $this->clean($sol['lente_nombre'] ?? null) ?? $lenteNombre,
                'lente_poder'       => $this->clean($sol['lente_poder'] ?? null) ?? $lentePoder,
                'lente_observacion' => $this->clean($sol['lente_observacion'] ?? null) ?? $lenteObs,
                'incision'          => $this->clean($sol['incision'] ?? null) ?? $incision,
                'estado'            => 'recibida',
                'created_at'        => $now,
            ];
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException('El procedimiento es obligatorio en todas las solicitudes (faltante en: ' . implode(', ', $missing) . ')');
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $sol
     * @return array{?string, ?string, ?string, ?string, ?string, ?string}
     */
    private function extractDetalleFields(array $sol): array
    {
        $detalles = $sol['detalles'] ?? null;
        if (!is_array($detalles)) {
            return [null, null, null, null, null, null];
        }

        $detallePlano = null;
        foreach ($detalles as $detalle) {
            if (!is_array($detalle)) {
                continue;
            }
            $detallePlano = $detalle;
            if (!empty($detalle['principal']) || !empty($detalle['tipo'])) {
                break;
            }
        }

        if (!is_array($detallePlano)) {
            return [null, null, null, null, null, null];
        }

        return [
            $this->clean($detallePlano['id_lente_intraocular'] ?? ($detallePlano['lente_id'] ?? null)),
            $this->clean($detallePlano['lente'] ?? ($detallePlano['lente_nombre'] ?? null)),
            $this->clean($detallePlano['poder'] ?? ($detallePlano['lente_poder'] ?? null)),
            $this->clean($detallePlano['observaciones'] ?? ($detallePlano['lente_observacion'] ?? null)),
            $this->clean($detallePlano['incision'] ?? null),
            $this->clean($detallePlano['lateralidad'] ?? null),
        ];
    }

    private function clean(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || in_array(mb_strtoupper($v), ['SELECCIONE', 'NINGUNO'], true)) {
            return null;
        }
        return $v;
    }

    private function normPrioridad(mixed $v): string
    {
        $v = is_string($v) ? mb_strtoupper(trim($v)) : $v;
        return ($v === 'SI' || $v === 1 || $v === '1' || $v === true) ? 'SI' : 'NO';
    }

    private function normInt(mixed $v): ?int
    {
        if (is_numeric($v)) {
            return (int) $v;
        }
        return null;
    }

    private function normOjo(mixed $v): ?string
    {
        if (is_array($v)) {
            $values = array_values(array_filter(array_map(fn(mixed $item): ?string => $this->clean($item), $v)));
            return $values === [] ? null : implode(',', $values);
        }

        return $this->clean($v);
    }

    private function normFecha(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        if (!$v) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
            return $v;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $v)) {
            $fmt = strlen($v) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
            $dt = \DateTime::createFromFormat($fmt, $v);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        foreach (['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $v);
            if ($dt instanceof \DateTime) {
                return $dt->format(str_contains($fmt, ' ') ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }
        return null;
    }
}
