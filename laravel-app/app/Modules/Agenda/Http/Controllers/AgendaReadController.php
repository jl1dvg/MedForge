<?php

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaReadController
{
    public function index(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $today = now()->toDateString();
        $start = (string) $request->query('fecha_inicio', $today);
        $end = (string) $request->query('fecha_fin', $start);
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $doctor = trim((string) $request->query('doctor', ''));
        $estado = trim((string) $request->query('estado', ''));
        $sede = trim((string) $request->query('sede', ''));
        $soloConVisita = (string) $request->query('solo_con_visita', '0') !== '0';

        $sql = "SELECT
                    pp.id, pp.form_id, pp.hc_number,
                    TRIM(CONCAT_WS(' ', pd.fname, pd.mname, pd.lname, pd.lname2)) AS paciente,
                    pp.procedimiento_proyectado AS procedimiento,
                    pp.doctor, pp.fecha, pp.hora, pp.estado_agenda,
                    pp.sede_departamento, pp.id_sede, pp.afiliacion,
                    v.id AS visita_id, v.fecha_visita, v.hora_llegada,
                    COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
                FROM procedimiento_proyectado pp
                LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                LEFT JOIN visitas v ON v.id = pp.visita_id
                WHERE COALESCE(DATE(pp.fecha), v.fecha_visita) BETWEEN ? AND ?";
        $bind = [$start, $end];

        if ($soloConVisita) {
            $sql .= " AND pp.visita_id IS NOT NULL";
        }
        if ($doctor !== '') {
            $sql .= " AND pp.doctor = ?";
            $bind[] = $doctor;
        }
        if ($estado !== '') {
            $sql .= " AND pp.estado_agenda = ?";
            $bind[] = $estado;
        }
        if ($sede !== '') {
            $sql .= " AND (pp.id_sede = ? OR pp.sede_departamento = ?)";
            $bind[] = $sede;
            $bind[] = $sede;
        }

        $sql .= " ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC LIMIT 1000";

        try {
            $rows = DB::select($sql, $bind);
            $estados = DB::select("SELECT DISTINCT estado_agenda FROM procedimiento_proyectado WHERE estado_agenda IS NOT NULL AND estado_agenda != '' ORDER BY estado_agenda");
            $doctores = DB::select("SELECT DISTINCT doctor FROM procedimiento_proyectado WHERE doctor IS NOT NULL AND doctor != '' ORDER BY doctor");

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'filters' => [
                        'fecha_inicio' => $start,
                        'fecha_fin' => $end,
                        'doctor' => $doctor !== '' ? $doctor : null,
                        'estado' => $estado !== '' ? $estado : null,
                        'sede' => $sede !== '' ? $sede : null,
                        'solo_con_visita' => $soloConVisita,
                    ],
                    'estados_disponibles' => array_map(fn ($r) => (string) ($r->estado_agenda ?? ''), $estados),
                    'doctores_disponibles' => array_map(fn ($r) => (string) ($r->doctor ?? ''), $doctores),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo cargar agenda', 'detail' => $e->getMessage()], 500);
        }
    }

    public function visita(Request $request, int $visitaId): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        if ($visitaId <= 0) {
            return response()->json(['error' => 'Encuentro no encontrado'], 404);
        }

        try {
            $visita = DB::selectOne(
                "SELECT v.id, v.hc_number, v.fecha_visita, v.hora_llegada, v.usuario_registro,
                        pd.fname, pd.mname, pd.lname, pd.lname2, pd.afiliacion, pd.celular, pd.fecha_nacimiento
                 FROM visitas v
                 LEFT JOIN patient_data pd ON pd.hc_number = v.hc_number
                 WHERE v.id = ? LIMIT 1",
                [$visitaId]
            );

            if (!$visita) {
                return response()->json(['error' => 'Encuentro no encontrado'], 404);
            }

            $procedimientos = DB::select(
                "SELECT pp.id, pp.form_id, pp.procedimiento_proyectado AS procedimiento, pp.doctor, pp.fecha, pp.hora,
                        pp.estado_agenda, pp.afiliacion, pp.sede_departamento, pp.id_sede, v.hora_llegada,
                        COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE pp.visita_id = ?
                 ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC",
                [$visitaId]
            );

            return response()->json([
                'data' => [
                    'visita' => $visita,
                    'procedimientos' => $procedimientos,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo cargar la visita', 'detail' => $e->getMessage()], 500);
        }
    }
}
