<?php

namespace App\Modules\Agenda\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AgendaV3Controller
{
    public function shell(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('agenda.v3-shell', [
            'user' => Auth::user(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        try {
            $this->syncMedicosFromPP();

            $sedes   = DB::table('agenda_sedes')->where('activo', true)->get()->map(fn ($s) => [
                'id'       => $s->id,
                'label'    => $s->label,
                'abrev'    => $s->abrev,
                'apertura' => substr($s->apertura, 0, 5),
                'cierre'   => substr($s->cierre, 0, 5),
            ]);

            $medicos = DB::table('agenda_medicos')->where('activo', true)->orderBy('nombre')->get()->map(fn ($m) => [
                'id'         => $m->id,
                'nombre'     => $m->nombre,
                'esp'        => $m->especialidad,
                'areas'      => json_decode($m->areas ?? '[]', true) ?: [],
                'sede'       => $m->sede_id,
                'color'      => $m->color,
                'iniciales'  => $m->iniciales,
            ]);

            $salas = DB::table('agenda_salas')->where('activo', true)->get()->map(fn ($s) => [
                'id'    => $s->id,
                'sede'  => $s->sede_id,
                'label' => $s->label,
                'tipo'  => $s->tipo,
                'area'  => $s->area,
                'cap'   => (int) $s->cap,
            ]);

            $tipos = DB::table('agenda_tipos_cita')->where('activo', true)->get()->map(fn ($t) => [
                'id'               => $t->id,
                'label'            => $t->label,
                'area'             => $t->area,
                'dur'              => (int) $t->dur_minutos,
                'requiereTipoSala' => json_decode($t->requiere_tipo_sala, true),
            ]);

            $horarios = DB::table('agenda_horarios')->where('activo', true)->get()->map(fn ($h) => [
                'medico' => $h->medico_id,
                'dia'    => (int) $h->dia_semana,
                'ini'    => substr($h->hora_ini, 0, 5),
                'fin'    => substr($h->hora_fin, 0, 5),
                'sede'   => $h->sede_id,
            ]);

            return response()->json([
                'HOY'       => now()->toDateString(),
                'AREAS'     => $this->catalogAreas(),
                'SEDES'     => $sedes,
                'MEDICOS'   => $medicos,
                'SALAS'     => $salas,
                'TIPOS'     => $tipos,
                'HORARIOS'  => $horarios,
                'ESTADOS'   => $this->catalogEstados(),
                'AFILIACIONES' => ['IESS', 'ISSPOL', 'ISSFA', 'Particular', 'MSP'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo cargar configuración', 'detail' => $e->getMessage()], 500);
        }
    }

    public function listCitas(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $fecha  = (string) $request->query('fecha', now()->toDateString());
        $sedeId = (string) $request->query('sede', '');

        try {
            $this->syncMedicosFromPP();

            $query = DB::table('agenda_citas_v3 as c')
                ->leftJoin('agenda_tipos_cita as tp', 'tp.id', '=', 'c.tipo_id')
                ->select([
                    'c.id', 'c.fecha', 'c.sede_id', 'c.medico_id', 'c.sala_id', 'c.tipo_id',
                    'c.paciente', 'c.hc_number', 'c.edad', 'c.afiliacion', 'c.tel',
                    'c.hora_ini', 'c.hora_fin', 'c.estado', 'c.whatsapp_estado',
                    'c.hora_llegada', 'c.hora_sala', 'c.hora_consulta', 'c.hora_fin_atencion',
                    'c.notas', 'c.sobreturno', 'c.hc_llena', 'c.hc_data',
                    'tp.area', 'tp.dur_minutos',
                ])
                ->whereNull('c.deleted_at')
                ->where('c.fecha', $fecha);

            if ($sedeId !== '') {
                $query->where('c.sede_id', $sedeId);
            }

            $v3Citas  = $query->orderBy('c.hora_ini')->get()->map(fn ($c) => $this->normalizeCita($c))->toArray();
            $medDir   = $this->buildMedicosDirectory();
            $ppCitas  = $this->fetchPPCitas($fecha, $sedeId, $medDir);

            // Merge: V3 citas take precedence; deduplicate by hc_number+hora_ini if same patient appears in both
            $v3Keys = [];
            foreach ($v3Citas as $c) {
                if ($c['hc_number'] !== '') {
                    $v3Keys[$c['hc_number'] . '|' . $c['hora_ini']] = true;
                }
            }
            $filteredPP = array_filter($ppCitas, function (array $pp) use ($v3Keys): bool {
                if ($pp['hc_number'] === '') return true;
                return !isset($v3Keys[$pp['hc_number'] . '|' . $pp['hora_ini']]);
            });

            $all = array_merge($v3Citas, array_values($filteredPP));
            usort($all, fn ($a, $b) => strcmp($a['hora_ini'], $b['hora_ini']));

            return response()->json(['data' => $all]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudieron cargar las citas', 'detail' => $e->getMessage()], 500);
        }
    }

    public function createCita(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $v = Validator::make($request->all(), [
            'fecha'      => ['required', 'date_format:Y-m-d'],
            'sede_id'    => ['required', 'string', 'max:32'],
            'medico_id'  => ['required', 'string', 'max:32'],
            'sala_id'    => ['required', 'string', 'max:32'],
            'tipo_id'    => ['required', 'string', 'max:32'],
            'paciente'   => ['required', 'string', 'max:200'],
            'hc_number'  => ['nullable', 'string', 'max:64'],
            'edad'       => ['nullable', 'integer', 'min:0', 'max:150'],
            'afiliacion' => ['nullable', 'string', 'max:64'],
            'tel'        => ['nullable', 'string', 'max:32'],
            'hora_ini'   => ['required', 'date_format:H:i'],
            'sobreturno' => ['nullable', 'boolean'],
            'notas'      => ['nullable', 'string', 'max:1000'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        try {
            $tipo = DB::table('agenda_tipos_cita')->find($data['tipo_id']);
            if (!$tipo) {
                return response()->json(['ok' => false, 'error' => 'Tipo de cita no encontrado'], 422);
            }

            $id = DB::table('agenda_citas_v3')->insertGetId([
                'fecha'           => $data['fecha'],
                'sede_id'         => $data['sede_id'],
                'medico_id'       => $data['medico_id'],
                'sala_id'         => $data['sala_id'],
                'tipo_id'         => $data['tipo_id'],
                'paciente'        => $data['paciente'],
                'hc_number'       => $data['hc_number'] ?? '',
                'edad'            => $data['edad'] ?? null,
                'afiliacion'      => $data['afiliacion'] ?? '',
                'tel'             => $data['tel'] ?? '',
                'hora_ini'        => $data['hora_ini'],
                'hora_fin'        => $this->calcFin($data['hora_ini'], (int) $tipo->dur_minutos),
                'estado'          => 'agendado',
                'whatsapp_estado' => 'na',
                'sobreturno'      => (bool) ($data['sobreturno'] ?? false),
                'notas'           => $data['notas'] ?? null,
                'creado_por'      => Auth::id(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return response()->json(['ok' => true, 'data' => $this->fetchCitaById($id)], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo crear la cita', 'detail' => $e->getMessage()], 500);
        }
    }

    public function updateCita(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $v = Validator::make($request->all(), [
            'fecha'      => ['sometimes', 'date_format:Y-m-d'],
            'sede_id'    => ['sometimes', 'string', 'max:32'],
            'medico_id'  => ['sometimes', 'string', 'max:32'],
            'sala_id'    => ['sometimes', 'string', 'max:32'],
            'tipo_id'    => ['sometimes', 'string', 'max:32'],
            'paciente'   => ['sometimes', 'string', 'max:200'],
            'hc_number'  => ['nullable', 'string', 'max:64'],
            'edad'       => ['nullable', 'integer', 'min:0', 'max:150'],
            'afiliacion' => ['nullable', 'string', 'max:64'],
            'tel'        => ['nullable', 'string', 'max:32'],
            'hora_ini'   => ['sometimes', 'date_format:H:i'],
            'sobreturno' => ['nullable', 'boolean'],
            'notas'      => ['nullable', 'string', 'max:1000'],
            'estado'     => ['sometimes', 'string', 'max:32'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $existing = DB::table('agenda_citas_v3')->whereNull('deleted_at')->find($id);
            if (!$existing) {
                return response()->json(['ok' => false, 'error' => 'Cita no encontrada'], 404);
            }

            $data   = $v->validated();
            $patch  = array_filter($data, fn ($v) => $v !== null);

            $tipoId  = $data['tipo_id'] ?? $existing->tipo_id;
            $horaIni = $data['hora_ini'] ?? substr((string) $existing->hora_ini, 0, 5);
            $tipo    = DB::table('agenda_tipos_cita')->find($tipoId);
            if ($tipo) {
                $patch['hora_fin'] = $this->calcFin($horaIni, (int) $tipo->dur_minutos);
            }

            $patch['updated_at'] = now();
            DB::table('agenda_citas_v3')->where('id', $id)->update($patch);

            return response()->json(['ok' => true, 'data' => $this->fetchCitaById($id)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar la cita', 'detail' => $e->getMessage()], 500);
        }
    }

    public function avanzarCita(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        try {
            $existing = DB::table('agenda_citas_v3')->whereNull('deleted_at')->find($id);
            if (!$existing) {
                return response()->json(['ok' => false, 'error' => 'Cita no encontrada'], 404);
            }

            $force  = $request->input('force', null);
            $order  = ['agendado', 'confirmado', 'en_sala', 'en_consulta', 'completado'];
            $now    = now()->format('H:i');
            $patch  = ['updated_at' => now()];

            if ($force === 'ausente') {
                $patch['estado'] = 'ausente';
            } else {
                $i = array_search($existing->estado, $order, true);
                if ($i === false || $i >= count($order) - 1) {
                    return response()->json(['ok' => false, 'error' => 'No se puede avanzar desde este estado'], 422);
                }
                $next           = $order[$i + 1];
                $patch['estado'] = $next;
                if ($next === 'confirmado')   $patch['whatsapp_estado'] = 'confirmado';
                if ($next === 'en_sala')      $patch['hora_sala']       = $now;
                if ($next === 'en_consulta')  $patch['hora_consulta']   = $now;
                if ($next === 'completado')   $patch['hora_fin_atencion'] = $now;
            }

            if (!$existing->hora_llegada) {
                $patch['hora_llegada'] = $now;
            }

            DB::table('agenda_citas_v3')->where('id', $id)->update($patch);
            return response()->json(['ok' => true, 'data' => $this->fetchCitaById($id)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo avanzar la cita', 'detail' => $e->getMessage()], 500);
        }
    }

    public function cancelarCita(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        try {
            $affected = DB::table('agenda_citas_v3')->whereNull('deleted_at')->where('id', $id)
                ->update(['estado' => 'cancelado', 'updated_at' => now()]);

            if (!$affected) {
                return response()->json(['ok' => false, 'error' => 'Cita no encontrada'], 404);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo cancelar la cita', 'detail' => $e->getMessage()], 500);
        }
    }

    public function finalizarConsulta(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        try {
            DB::table('agenda_citas_v3')->whereNull('deleted_at')->where('id', $id)->update([
                'estado'           => 'completado',
                'hc_llena'         => true,
                'hc_data'          => json_encode($request->input('hc_data', [])),
                'hora_fin_atencion' => now()->format('H:i'),
                'updated_at'       => now(),
            ]);

            return response()->json(['ok' => true, 'data' => $this->fetchCitaById($id)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listBloqueos(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $fecha = (string) $request->query('fecha', now()->toDateString());

        try {
            $rows = DB::table('agenda_bloqueos')->where('fecha', $fecha)->orderBy('hora_ini')->get();
            return response()->json(['data' => $rows->map(fn ($b) => $this->normalizeBloqueo($b))]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudieron cargar los bloqueos', 'detail' => $e->getMessage()], 500);
        }
    }

    public function createBloqueo(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $v = Validator::make($request->all(), [
            'scope'    => ['required', 'in:medico,sala'],
            'ref_id'   => ['required', 'string', 'max:32'],
            'fecha'    => ['required', 'date_format:Y-m-d'],
            'hora_ini' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
            'motivo'   => ['nullable', 'string', 'max:200'],
            'tipo'     => ['nullable', 'in:reunion,mantenimiento,ausencia,almuerzo,otro'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        try {
            $id = DB::table('agenda_bloqueos')->insertGetId([
                'scope'      => $data['scope'],
                'ref_id'     => $data['ref_id'],
                'fecha'      => $data['fecha'],
                'hora_ini'   => $data['hora_ini'],
                'hora_fin'   => $data['hora_fin'],
                'motivo'     => $data['motivo'] ?? '',
                'tipo'       => $data['tipo'] ?? 'otro',
                'creado_por' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $b = DB::table('agenda_bloqueos')->find($id);
            return response()->json(['ok' => true, 'data' => $this->normalizeBloqueo($b)], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo crear el bloqueo', 'detail' => $e->getMessage()], 500);
        }
    }

    public function deleteBloqueo(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        try {
            $deleted = DB::table('agenda_bloqueos')->where('id', $id)->delete();
            if (!$deleted) {
                return response()->json(['ok' => false, 'error' => 'Bloqueo no encontrado'], 404);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------

    public function forceSync(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        Cache::forget('agenda_v3.medicos_synced');
        $this->syncMedicosFromPP();

        $count = DB::table('agenda_medicos')->where('activo', true)->count();

        return response()->json([
            'ok'      => true,
            'medicos' => $count,
            'mensaje' => "Sync completado: {$count} médicos activos.",
        ]);
    }

    private function syncMedicosFromPP(): void
    {
        if (Cache::has('agenda_v3.medicos_synced')) {
            return;
        }

        try {
            // Fuente de verdad: users con especialidad médica registrados en el sistema
            $usersRaw = DB::select(
                "SELECT id, TRIM(COALESCE(nombre,'')) AS nombre,
                        TRIM(COALESCE(especialidad,'')) AS especialidad,
                        TRIM(COALESCE(subespecialidad,'')) AS subespecialidad,
                        TRIM(COALESCE(sede,'')) AS sede
                 FROM users
                 WHERE especialidad IS NOT NULL AND TRIM(especialidad) != ''
                   AND nombre IS NOT NULL AND TRIM(nombre) != ''
                 ORDER BY nombre ASC"
            );

            $sedesMap    = DB::table('agenda_sedes')->where('activo', true)->pluck('id', 'id')->toArray();
            $defaultSede = array_key_first($sedesMap) ?? 'ceibos';
            $colors      = ['#5156be', '#2ca361', '#d34b5b', '#d59623', '#3d7ac7', '#7c5fc2', '#4a9a9e', '#b55c32'];
            $idx         = 0;
            $syncedIds   = [];

            foreach ($usersRaw as $u) {
                $label = $this->formatDoctorLabel((string) $u->nombre);
                if ($label === '') { continue; }

                $id = 'usr_' . $u->id;

                $userSede = mb_strtolower(trim((string) ($u->sede ?? '')), 'UTF-8');
                $sedeId   = $defaultSede;
                foreach (array_keys($sedesMap) as $sid) {
                    if ($userSede !== '' && str_contains($userSede, mb_strtolower($sid, 'UTF-8'))) {
                        $sedeId = $sid; break;
                    }
                }

                $espLabel  = trim((string) ($u->subespecialidad ?: $u->especialidad)) ?: 'Oftalmología';
                $iniciales = $this->getIniciales($label);

                DB::table('agenda_medicos')->updateOrInsert(
                    ['id' => $id],
                    [
                        'nombre'       => $label,
                        'especialidad' => $espLabel,
                        'areas'        => '["consulta","quirurgico","imagenes"]',
                        'sede_id'      => $sedeId,
                        'color'        => $colors[$idx % count($colors)],
                        'iniciales'    => substr($iniciales, 0, 3),
                        'user_id'      => (int) $u->id,
                        'activo'       => true,
                    ]
                );

                $syncedIds[] = $id;
                $idx++;
            }

            if (!empty($syncedIds)) {
                DB::table('agenda_medicos')->whereNotIn('id', $syncedIds)->update(['activo' => false]);
            }

            Cache::put('agenda_v3.medicos_synced', true, 1800);
        } catch (\Throwable $e) {
            Log::warning('Agenda V3 doctor sync failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string,string>  normalized-name → agenda_medicos.id */
    private function buildMedicosDirectory(): array
    {
        $dir = [];
        $medicos = DB::table('agenda_medicos')->where('activo', true)->get(['id', 'nombre']);
        foreach ($medicos as $m) {
            $norm = $this->normalizeDoctorName((string) $m->nombre);
            if ($norm !== '') { $dir[$norm] = (string) $m->id; }
        }
        return $dir;
    }

    private function normalizeDoctorName(string $name): string
    {
        if (trim($name) === '') { return ''; }
        $v = mb_strtolower($name, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        $v = is_string($ascii) && $ascii !== '' ? $ascii : $v;
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v) ?? $v;
        return trim((string) $v);
    }

    private function normalizeWhitespace(string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    private function formatDoctorLabel(string $name): string
    {
        $lower = mb_strtolower(trim($name), 'UTF-8');
        return $lower !== '' ? mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8') : '';
    }

    private function fallbackMedicoId(string $sedeId): string
    {
        $id = DB::table('agenda_medicos')
            ->where('activo', true)
            ->where('sede_id', $sedeId)
            ->orderBy('nombre')
            ->value('id');

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $any = DB::table('agenda_medicos')
            ->where('activo', true)
            ->orderBy('nombre')
            ->value('id');

        return is_string($any) && $any !== '' ? $any : '';
    }

    private function fallbackTipoId(string $area): string
    {
        $id = DB::table('agenda_tipos_cita')
            ->where('activo', true)
            ->where('area', $area)
            ->orderBy('id')
            ->value('id');

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $any = DB::table('agenda_tipos_cita')
            ->where('activo', true)
            ->orderBy('id')
            ->value('id');

        return is_string($any) && $any !== '' ? $any : '';
    }

    private function fallbackSalaId(string $sedeId, string $area): string
    {
        $id = DB::table('agenda_salas')
            ->where('activo', true)
            ->where('sede_id', $sedeId)
            ->where('area', $area)
            ->orderBy('id')
            ->value('id');

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $any = DB::table('agenda_salas')
            ->where('activo', true)
            ->where('sede_id', $sedeId)
            ->orderBy('id')
            ->value('id');

        return is_string($any) && $any !== '' ? $any : '';
    }

    private function hhmmFromValue(mixed $value, string $default = '08:00'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $default;
        }

        if (preg_match('/(\d{2}):(\d{2})/', $raw, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return $default;
    }

    private function legacyPatientName(object $pp): string
    {
        $parts = [
            $pp->fname ?? null,
            $pp->mname ?? null,
            $pp->lname ?? null,
            $pp->lname2 ?? null,
        ];

        $name = $this->normalizeWhitespace(implode(' ', array_filter(array_map(
            static fn ($part) => trim((string) $part),
            $parts
        ))));

        return $name !== '' ? $name : trim((string) ($pp->hc_number ?? 'Paciente'));
    }

    /** Fetch procedimiento_proyectado rows for a date as read-only V3-shaped citas. */
    /** @param array<string,string> $medDir  normalized-name → agenda_medicos.id */
    private function fetchPPCitas(string $fecha, string $sedeId, array $medDir = []): array
    {
        // Build sede label → slug map for filtering
        $sedesMap = [];
        DB::table('agenda_sedes')->get(['id', 'label'])->each(function ($s) use (&$sedesMap) {
            $lower = mb_strtolower(trim($s->label), 'UTF-8');
            $upper = mb_strtoupper(trim($s->label), 'UTF-8');
            $sedesMap[$lower]  = $s->id;
            $sedesMap[$upper]  = $s->id;
            $slug = preg_replace('/\s+/', '', $lower);
            $sedesMap[$slug]   = $s->id;
        });

        $query = DB::table('procedimiento_proyectado as pp')
            ->leftJoin('patient_data as pd', 'pd.hc_number', '=', 'pp.hc_number')
            ->leftJoin('visitas as v', 'v.id', '=', 'pp.visita_id')
            ->select([
                'pp.id',
                'pp.hc_number',
                'pd.fname',
                'pd.mname',
                'pd.lname',
                'pd.lname2',
                'pp.procedimiento_proyectado as procedimiento',
                'pp.doctor',
                'pp.hora',
                'pp.fecha',
                'pp.sede_departamento as sede_raw',
                'pp.estado_agenda',
                'pp.afiliacion',
                'pp.visita_id',
                'v.fecha_visita',
                'v.hora_llegada',
            ])
            ->whereRaw('COALESCE(pp.sigcenter_present, 1) = 1')
            ->where(function ($q) use ($fecha): void {
                $q->whereDate('pp.fecha', $fecha)
                    ->orWhere('v.fecha_visita', $fecha);
            });

        if ($sedeId !== '') {
            $sedeLabel = DB::table('agenda_sedes')->where('id', $sedeId)->value('label');
            if ($sedeLabel) {
                $query->whereRaw('UPPER(TRIM(pp.sede_departamento)) LIKE UPPER(?)', ['%' . trim((string) $sedeLabel) . '%']);
            }
        }

        try {
            $rows = $query->orderBy('pp.hora')->orderBy('pp.id')->limit(500)->get();
        } catch (\Throwable $e) {
            Log::warning('Agenda V3 legacy citas fetch failed', [
                'fecha' => $fecha,
                'sede' => $sedeId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        return $rows->map(function (object $pp) use ($sedesMap, $fecha, $medDir): array {
            $horaIni = $this->hhmmFromValue($pp->hora ?? null);

            $sedeRawOrig  = trim((string) ($pp->sede_raw ?? ''));
            $sedeRawLower = mb_strtolower($sedeRawOrig, 'UTF-8');
            $sedeRawUpper = mb_strtoupper($sedeRawOrig, 'UTF-8');
            $sedeRawSlug  = preg_replace('/\s+/', '', $sedeRawLower) ?? $sedeRawLower;
            $sedeSlug = $sedesMap[$sedeRawLower]
                     ?? $sedesMap[$sedeRawUpper]
                     ?? $sedesMap[$sedeRawSlug]
                     ?? array_values($sedesMap)[0]
                     ?? 'ceibos';
            $doctor  = trim((string) ($pp->doctor ?? ''));
            $medSlug = $doctor !== '' ? ($medDir[$this->normalizeDoctorName($doctor)] ?? '') : '';
            if ($medSlug === '') {
                $medSlug = $this->fallbackMedicoId($sedeSlug);
            }

            $procedimiento = (string) ($pp->procedimiento ?? '');
            $tipoParts     = explode(' - ', $procedimiento, 2);
            $tipoLabel     = trim($tipoParts[0] ?? $procedimiento);
            $area           = 'consulta';
            $tipoId         = $this->fallbackTipoId($area);
            $salaId         = $this->fallbackSalaId($sedeSlug, $area);

            return [
                'id'                => $pp->id,
                'fecha'             => $fecha,
                'sede_id'           => $sedeSlug,
                'medico_id'         => $medSlug,
                'sala_id'           => $salaId,
                'tipo_id'           => $tipoId,
                'paciente'          => $this->legacyPatientName($pp),
                'hc_number'         => (string) ($pp->hc_number ?? ''),
                'edad'              => null,
                'afiliacion'        => (string) ($pp->afiliacion ?? ''),
                'tel'               => '',
                'hora_ini'          => $horaIni,
                'hora_fin'          => $this->calcFin($horaIni, 20),
                'area'              => $area,
                'dur_minutos'       => 20,
                'estado'            => $this->mapEstadoAgenda((string) ($pp->estado_agenda ?? '')),
                'whatsapp_estado'   => 'na',
                'hora_llegada'      => $pp->hora_llegada ? $this->hhmmFromValue($pp->hora_llegada, '') : null,
                'hora_sala'         => null,
                'hora_consulta'     => null,
                'hora_fin_atencion' => null,
                'notas'             => trim($tipoLabel . ($doctor !== '' ? ' · SigCenter doctor: ' . $doctor : '')),
                'sobreturno'        => false,
                'hc_llena'          => false,
                'hc_data'           => null,
                '_source'           => 'pp',
                '_readonly'         => true,
            ];
        })->toArray();
    }

    private function mapEstadoAgenda(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'atendido', 'completado'                                    => 'completado',
            'no presente', 'no_presente', 'no show', 'noshow', 'ausente' => 'ausente',
            'cancelado', 'anulado', 'cancelada'                         => 'cancelado',
            'en consulta', 'en_consulta'                                => 'en_consulta',
            'en sala', 'en_sala'                                        => 'en_sala',
            'confirmado', 'confirmada'                                  => 'confirmado',
            default                                                     => 'agendado',
        };
    }

    private function doctorSlug(string $name): string
    {
        $v = mb_strtolower($name, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        $v = is_string($ascii) && $ascii !== '' ? $ascii : $v;
        $v = preg_replace('/[^a-z0-9]+/', '_', $v) ?? $v;
        $v = trim($v, '_');
        return 'md_' . substr($v, 0, 27);
    }

    private function isDoctorLikeName(string $name): bool
    {
        if (preg_match('/\d{1,2}:\d{2}/', $name)) return false;
        if (preg_match('/^\d/', $name))            return false;
        $blocked = ['/\bRETINOGRAFIA\b/ui', '/\bNERVIO OPTICO\b/ui', '/\bCONSULTA\b/ui', '/\bPROCEDIMIENTO\b/ui', '/\bOPTOMETRIA\b/ui'];
        foreach ($blocked as $p) {
            if (preg_match($p, $name)) return false;
        }
        $tokens = array_filter(preg_split('/\s+/', trim($name)) ?: [], fn ($t) => strlen($t) > 1);
        return count($tokens) >= 2;
    }

    private function formatDoctorName(string $name): string
    {
        $lower = mb_strtolower($name, 'UTF-8');
        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }

    private function getIniciales(string $name): string
    {
        $skip = ['de', 'del', 'la', 'los', 'las', 'y'];
        $tokens = preg_split('/\s+/', $name) ?: [];
        $ini = '';
        foreach ($tokens as $t) {
            if (strlen($t) > 1 && !in_array(strtolower($t), $skip, true)) {
                $ini .= mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8');
            }
            if (strlen($ini) >= 3) break;
        }
        return substr($ini, 0, 3);
    }

    private function fetchCitaById(int $id): array
    {
        $c = DB::table('agenda_citas_v3 as c')
            ->leftJoin('agenda_tipos_cita as tp', 'tp.id', '=', 'c.tipo_id')
            ->select([
                'c.id', 'c.fecha', 'c.sede_id', 'c.medico_id', 'c.sala_id', 'c.tipo_id',
                'c.paciente', 'c.hc_number', 'c.edad', 'c.afiliacion', 'c.tel',
                'c.hora_ini', 'c.hora_fin', 'c.estado', 'c.whatsapp_estado',
                'c.hora_llegada', 'c.hora_sala', 'c.hora_consulta', 'c.hora_fin_atencion',
                'c.notas', 'c.sobreturno', 'c.hc_llena', 'c.hc_data',
                'tp.area', 'tp.dur_minutos',
            ])
            ->where('c.id', $id)->first();

        return $this->normalizeCita($c);
    }

    private function normalizeCita(object $c): array
    {
        return [
            'id'                => $c->id,
            'fecha'             => $c->fecha,
            'sede_id'           => $c->sede_id,
            'medico_id'         => $c->medico_id,
            'sala_id'           => $c->sala_id,
            'tipo_id'           => $c->tipo_id,
            'paciente'          => $c->paciente,
            'hc_number'         => $c->hc_number ?? '',
            'edad'              => $c->edad,
            'afiliacion'        => $c->afiliacion ?? '',
            'tel'               => $c->tel ?? '',
            'hora_ini'          => substr((string) $c->hora_ini, 0, 5),
            'hora_fin'          => substr((string) $c->hora_fin, 0, 5),
            'area'              => $c->area ?? '',
            'dur_minutos'       => (int) ($c->dur_minutos ?? 20),
            'estado'            => $c->estado,
            'whatsapp_estado'   => $c->whatsapp_estado ?? 'na',
            'hora_llegada'      => $c->hora_llegada      ? substr((string) $c->hora_llegada, 0, 5)      : null,
            'hora_sala'         => $c->hora_sala          ? substr((string) $c->hora_sala, 0, 5)          : null,
            'hora_consulta'     => $c->hora_consulta      ? substr((string) $c->hora_consulta, 0, 5)      : null,
            'hora_fin_atencion' => $c->hora_fin_atencion  ? substr((string) $c->hora_fin_atencion, 0, 5)  : null,
            'notas'             => $c->notas ?? '',
            'sobreturno'        => (bool) $c->sobreturno,
            'hc_llena'          => (bool) $c->hc_llena,
            'hc_data'           => $c->hc_data ? json_decode((string) $c->hc_data, true) : null,
        ];
    }

    private function normalizeBloqueo(object $b): array
    {
        return [
            'id'     => 'b' . $b->id,
            'db_id'  => $b->id,
            'scope'  => $b->scope,
            'ref'    => $b->ref_id,
            'fecha'  => $b->fecha,
            'ini'    => substr((string) $b->hora_ini, 0, 5),
            'fin'    => substr((string) $b->hora_fin, 0, 5),
            'motivo' => $b->motivo,
            'tipo'   => $b->tipo,
        ];
    }

    private function calcFin(string $horaIni, int $durMin): string
    {
        [$h, $m] = explode(':', $horaIni);
        $total = (int) $h * 60 + (int) $m + $durMin;
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    /** @return array<int,array<string,string>> */
    private function catalogAreas(): array
    {
        return [
            ['id' => 'consulta',   'label' => 'Consulta',   'icon' => 'mdi-stethoscope',            'color' => '#1f9d7a', 'bg' => '#dff5ee', 'fg' => '#17654f'],
            ['id' => 'quirurgico', 'label' => 'Quirúrgico', 'icon' => 'mdi-hospital-box-outline',   'color' => '#d34b5b', 'bg' => '#fde2e7', 'fg' => '#9f2d3e'],
            ['id' => 'imagenes',   'label' => 'Imágenes',   'icon' => 'mdi-radiology-box-outline',  'color' => '#3d7ac7', 'bg' => '#e3edf9', 'fg' => '#2e5e99'],
            ['id' => 'comercial',  'label' => 'Comercial',  'icon' => 'mdi-tag-text-outline',       'color' => '#d59623', 'bg' => '#fff0d1', 'fg' => '#8a5d0a'],
        ];
    }

    /** @return array<int,array<string,string>> */
    private function catalogEstados(): array
    {
        return [
            ['id' => 'agendado',    'label' => 'Agendado',     'icon' => 'mdi-calendar-blank-outline', 'tone' => 'info',    'desc' => 'Cita creada, sin confirmar'],
            ['id' => 'confirmado',  'label' => 'Confirmado',   'icon' => 'mdi-calendar-check-outline', 'tone' => 'primary', 'desc' => 'Paciente confirmó asistencia'],
            ['id' => 'en_sala',     'label' => 'En sala',      'icon' => 'mdi-door-open',              'tone' => 'warning', 'desc' => 'Paciente ingresó a sala/consultorio'],
            ['id' => 'en_consulta', 'label' => 'En consulta',  'icon' => 'mdi-stethoscope',            'tone' => 'warning', 'desc' => 'Atención en curso'],
            ['id' => 'completado',  'label' => 'Completado',   'icon' => 'mdi-check-circle-outline',   'tone' => 'success', 'desc' => 'Atención finalizada'],
            ['id' => 'ausente',     'label' => 'Ausente',      'icon' => 'mdi-account-cancel-outline', 'tone' => 'danger',  'desc' => 'No se presentó (no-show)'],
            ['id' => 'cancelado',   'label' => 'Cancelado',    'icon' => 'mdi-close-circle-outline',   'tone' => 'danger',  'desc' => 'Cita anulada'],
            ['id' => 'reagendado',  'label' => 'Reagendado',   'icon' => 'mdi-calendar-sync-outline',  'tone' => 'light',   'desc' => 'Movida a otra fecha/hora'],
        ];
    }
}
