<?php

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaWriteController
{
    public function actualizarEstado(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $estado = trim((string) $request->input('estado_agenda', ''));

        if ($formId === '' || $estado === '') {
            return response()->json(['ok' => false, 'error' => 'form_id y estado_agenda son requeridos'], 422);
        }

        try {
            $current = DB::selectOne('SELECT form_id, estado_agenda FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1', [$formId]);
            if (!$current) {
                return response()->json(['ok' => false, 'error' => 'Procedimiento no encontrado'], 404);
            }

            DB::table('procedimiento_proyectado')
                ->where('form_id', $formId)
                ->update(['estado_agenda' => $estado]);

            // Guardar historial cuando exista la tabla
            try {
                DB::table('procedimiento_proyectado_estado')->insert([
                    'form_id' => $formId,
                    'estado' => $estado,
                    'fecha_hora_cambio' => now(),
                ]);
            } catch (\Throwable) {
                // historial opcional según esquema
            }

            $updated = DB::selectOne('SELECT form_id, estado_agenda FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1', [$formId]);

            return response()->json([
                'ok' => true,
                'data' => [
                    'before' => $current,
                    'after' => $updated,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar estado agenda', 'detail' => $e->getMessage()], 500);
        }
    }
}
