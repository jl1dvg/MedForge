<?php

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AgendaReadController
{
    public function index(Request $request): JsonResponse|View|RedirectResponse|Response
    {
        $shouldReturnJson = $this->shouldReturnJson($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($shouldReturnJson) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $filters = $this->resolveFilters($request);

        try {
            $payload = $this->buildAgendaPayload($filters);
        } catch (\Throwable $e) {
            if ($shouldReturnJson) {
                return response()->json(['error' => 'No se pudo cargar agenda', 'detail' => $e->getMessage()], 500);
            }

            return response()->view('agenda.v2-index', [
                'pageTitle' => 'Agenda',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'agendaRows' => [],
                'agendaMeta' => $this->emptyMeta($filters),
                'loadError' => 'No se pudo cargar la agenda con los filtros solicitados.',
            ], 500);
        }

        if (!$shouldReturnJson) {
            return view('agenda.v2-index', [
                'pageTitle' => 'Agenda',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'agendaRows' => $payload['data'],
                'agendaMeta' => $payload['meta'],
                'loadError' => null,
            ]);
        }

        return response()->json($payload);
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

    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('v2/api/*');
    }

    /**
     * @return array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * }
     */
    private function resolveFilters(Request $request): array
    {
        $today = now()->toDateString();
        $start = trim((string) $request->query('fecha_inicio', $today));
        if ($start === '') {
            $start = $today;
        }

        $end = trim((string) $request->query('fecha_fin', $start));
        if ($end === '') {
            $end = $start;
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return [
            'fecha_inicio' => $start,
            'fecha_fin' => $end,
            'doctor' => trim((string) $request->query('doctor', '')),
            'estado' => trim((string) $request->query('estado', '')),
            'sede' => trim((string) $request->query('sede', '')),
            'solo_con_visita' => (string) $request->query('solo_con_visita', '0') !== '0',
        ];
    }

    /**
     * @param array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * } $filters
     * @return array{data:array<int,object>,meta:array<string,mixed>}
     */
    private function buildAgendaPayload(array $filters): array
    {
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
        $bind = [$filters['fecha_inicio'], $filters['fecha_fin']];

        if ($filters['solo_con_visita']) {
            $sql .= " AND pp.visita_id IS NOT NULL";
        }
        if ($filters['doctor'] !== '') {
            $sql .= " AND pp.doctor = ?";
            $bind[] = $filters['doctor'];
        }
        if ($filters['estado'] !== '') {
            $sql .= " AND pp.estado_agenda = ?";
            $bind[] = $filters['estado'];
        }
        if ($filters['sede'] !== '') {
            $sql .= " AND (pp.id_sede = ? OR pp.sede_departamento = ?)";
            $bind[] = $filters['sede'];
            $bind[] = $filters['sede'];
        }

        $sql .= " ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC LIMIT 1000";

        $rows = DB::select($sql, $bind);
        $estados = DB::select("SELECT DISTINCT estado_agenda FROM procedimiento_proyectado WHERE estado_agenda IS NOT NULL AND estado_agenda != '' ORDER BY estado_agenda");
        $doctores = DB::select("SELECT DISTINCT doctor FROM procedimiento_proyectado WHERE doctor IS NOT NULL AND doctor != '' ORDER BY doctor");

        return [
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
                'filters' => [
                    'fecha_inicio' => $filters['fecha_inicio'],
                    'fecha_fin' => $filters['fecha_fin'],
                    'doctor' => $filters['doctor'] !== '' ? $filters['doctor'] : null,
                    'estado' => $filters['estado'] !== '' ? $filters['estado'] : null,
                    'sede' => $filters['sede'] !== '' ? $filters['sede'] : null,
                    'solo_con_visita' => $filters['solo_con_visita'],
                ],
                'estados_disponibles' => array_map(fn ($r) => (string) ($r->estado_agenda ?? ''), $estados),
                'doctores_disponibles' => array_map(fn ($r) => (string) ($r->doctor ?? ''), $doctores),
            ],
        ];
    }

    /**
     * @param array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * } $filters
     * @return array<string,mixed>
     */
    private function emptyMeta(array $filters): array
    {
        return [
            'count' => 0,
            'filters' => [
                'fecha_inicio' => $filters['fecha_inicio'],
                'fecha_fin' => $filters['fecha_fin'],
                'doctor' => $filters['doctor'] !== '' ? $filters['doctor'] : null,
                'estado' => $filters['estado'] !== '' ? $filters['estado'] : null,
                'sede' => $filters['sede'] !== '' ? $filters['sede'] : null,
                'solo_con_visita' => $filters['solo_con_visita'],
            ],
            'estados_disponibles' => [],
            'doctores_disponibles' => [],
        ];
    }
}
