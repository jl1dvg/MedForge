<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Models\ImagenesBandejaPrioridad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API para la bandeja de prioridad de exámenes de imágenes.
 *
 * POST   /v2/imagenes/bandeja          → crear / actualizar entradas (bulk)
 * DELETE /v2/imagenes/bandeja/{id}     → quitar un procedimiento de la bandeja
 */
class ImagenesBandejaController
{
    /**
     * Crear o actualizar entradas en la bandeja.
     * Acepta uno o varios procedimiento_ids en el mismo request.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'procedimiento_ids'   => ['required', 'array', 'min:1'],
            'procedimiento_ids.*' => ['required', 'integer'],
            'form_ids'            => ['nullable', 'array'],
            'form_ids.*'          => ['nullable', 'string', 'max:64'],
            'prioridad'           => ['required', Rule::in(['urgente', 'pronto'])],
            'fecha_limite'        => ['nullable', 'date_format:Y-m-d'],
            'responsable'         => ['nullable', 'string', 'max:255'],
            'motivo'              => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $userName = $user?->name ?? $user?->username ?? 'Sistema';
        $userId = $user?->id;

        $ids = $data['procedimiento_ids'];
        $formIds = $data['form_ids'] ?? [];

        foreach ($ids as $i => $procedimientoId) {
            ImagenesBandejaPrioridad::updateOrCreate(
                ['procedimiento_id' => $procedimientoId],
                [
                    'form_id'           => $formIds[$i] ?? null,
                    'prioridad'         => $data['prioridad'],
                    'fecha_limite'      => $data['fecha_limite'] ?? null,
                    'responsable'       => $data['responsable'] ?? null,
                    'motivo'            => $data['motivo'],
                    'solicitado_por'    => $userId,
                    'solicitado_nombre' => $userName,
                ]
            );
        }

        return response()->json([
            'ok'    => true,
            'count' => count($ids),
        ]);
    }

    /**
     * Quitar un procedimiento de la bandeja.
     */
    public function destroy(int $procedimientoId): JsonResponse
    {
        ImagenesBandejaPrioridad::where('procedimiento_id', $procedimientoId)->delete();

        return response()->json(['ok' => true]);
    }
}
