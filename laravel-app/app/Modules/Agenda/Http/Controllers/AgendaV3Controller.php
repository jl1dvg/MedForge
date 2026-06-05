<?php

namespace App\Modules\Agenda\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $sedes   = DB::table('agenda_sedes')->where('activo', true)->get()->map(fn ($s) => [
                'id'       => $s->id,
                'label'    => $s->label,
                'abrev'    => $s->abrev,
                'apertura' => substr($s->apertura, 0, 5),
                'cierre'   => substr($s->cierre, 0, 5),
            ]);

            $medicos = DB::table('agenda_medicos')->where('activo', true)->get()->map(fn ($m) => [
                'id'         => $m->id,
                'nombre'     => $m->nombre,
                'esp'        => $m->especialidad,
                'areas'      => json_decode($m->areas, true),
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

            $rows = $query->orderBy('c.hora_ini')->get();
            return response()->json(['data' => $rows->map(fn ($c) => $this->normalizeCita($c))]);
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
