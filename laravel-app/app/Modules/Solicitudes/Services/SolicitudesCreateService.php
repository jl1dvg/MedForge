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

        $ids = [];
        $now = now()->toDateTimeString();

        try {
            DB::transaction(function () use ($hcNumber, $formId, $solicitudes, $now, &$ids): void {
                foreach ($solicitudes as $sol) {
                    $procedimiento = $this->clean($sol['procedimiento'] ?? null);
                    $doctor        = $this->clean($sol['doctor'] ?? null);
                    $ojo           = $this->clean($sol['ojo'] ?? null);
                    $prioridad     = $this->normPrioridad($sol['prioridad'] ?? null);
                    $afiliacion    = $this->clean($sol['afiliacion'] ?? null);
                    $afiliacionCat = $this->clean($sol['afiliacion_categoria'] ?? null);
                    $empresaSeg    = $this->clean($sol['empresa_seguro'] ?? null);
                    $sede          = $this->clean($sol['sede'] ?? null);
                    $observacion   = $this->clean($sol['observacion'] ?? null);
                    $fecha         = $this->normFecha($sol['fecha'] ?? null);
                    $codeId        = isset($sol['code_id']) && is_numeric($sol['code_id']) ? (int) $sol['code_id'] : null;

                    $id = DB::table('solicitud_procedimiento')->insertGetId([
                        'hc_number'            => $hcNumber,
                        'form_id'              => $formId,
                        'procedimiento'        => $procedimiento,
                        'doctor'               => $doctor,
                        'ojo'                  => $ojo,
                        'prioridad'            => $prioridad,
                        'afiliacion'           => $afiliacion,
                        'afiliacion_categoria' => $afiliacionCat,
                        'empresa_seguro'       => $empresaSeg,
                        'sede'                 => $sede,
                        'observacion'          => $observacion,
                        'fecha'                => $fecha,
                        'code_id'              => $codeId,
                        'estado'               => 'recibida',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);

                    $ids[] = $id;
                }
            });
        } catch (\Throwable $e) {
            Log::error('SolicitudesCreateService::guardar error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error interno al guardar la solicitud. Por favor intente nuevamente.'];
        }

        return [
            'success' => true,
            'message' => count($ids) . ' solicitud(es) guardada(s) exitosamente',
            'ids'     => $ids,
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

    private function normFecha(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        if (!$v) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
            return $v;
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
