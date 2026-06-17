<?php

namespace App\Modules\Pacientes\Services;

use App\Models\PatientDatum;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PacienteReadService
{
    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array<string,int|null>}
     */
    public function obtenerPacientesReact(?int $limit = null, int $offset = 0): array
    {
        $limit = $limit !== null ? max(1, min(2000, $limit)) : null;
        $offset = max(0, $offset);

        $baseQuery = $this->validPatientsQuery();
        $total = (clone $baseQuery)->count();

        $rowsQuery = $this->orderByNumericHc((clone $baseQuery)->select('patient_data.*'));
        if ($limit !== null) {
            $rowsQuery->limit($limit)->offset($offset);
        }

        /** @var Collection<int,PatientDatum> $patients */
        $patients = $rowsQuery->get();
        $hcNumbers = $patients
            ->pluck('hc_number')
            ->map(static fn(mixed $hcNumber): string => (string) $hcNumber)
            ->filter()
            ->values()
            ->all();

        $medicosTratantes = $this->resolveMedicosTratantes($hcNumbers);
        $sedesPacientes = $this->resolveSedesPacientes($hcNumbers);
        $manualMedicos = $this->resolveManualMedicos($patients->pluck('medico_tratante_id')->all());
        $ultimaVisitas = $this->ultimaVisitas($hcNumbers);
        $proximasCitas = $this->proximasCitas($hcNumbers);
        $solicitudesActivas = $this->solicitudesActivas($hcNumbers);
        $alertas = $this->alertas($hcNumbers);
        $tipoAfiliacionResolver = new TipoAfiliacionResolver();

        $data = $patients->map(function (PatientDatum $patient) use (
            $medicosTratantes,
            $sedesPacientes,
            $manualMedicos,
            $ultimaVisitas,
            $proximasCitas,
            $solicitudesActivas,
            $alertas,
            $tipoAfiliacionResolver
        ): array {
            $hcNumber = (string) ($patient->hc_number ?? '');
            $manualMedicoId = trim((string) ($patient->medico_tratante_id ?? ''));
            $medicoTratante = $manualMedicoId !== '' && isset($manualMedicos[$manualMedicoId])
                ? $manualMedicos[$manualMedicoId]
                : ($medicosTratantes[$hcNumber] ?? null);
            $sedeInfo = $this->normalizarSedePrincipal((string) ($patient->sede_principal ?? ''))
                ?? ($sedesPacientes[$hcNumber] ?? null);
            $afiliacion = $this->normalizarAfiliacionListado((string) ($patient->afiliacion ?? ''));
            $tipoAfiliacion = $tipoAfiliacionResolver->classify($afiliacion);

            return [
                'hc_number' => $hcNumber,
                'fname' => (string) ($patient->fname ?? ''),
                'mname' => (string) ($patient->mname ?? ''),
                'lname' => (string) ($patient->lname ?? ''),
                'lname2' => (string) ($patient->lname2 ?? ''),
                'full_name' => $this->fullName($patient),
                'display_name' => $this->displayName($patient),
                'cedula' => trim((string) ($patient->cedula ?? '')) !== '' ? (string) $patient->cedula : $hcNumber,
                'telefono' => (string) ($patient->celular ?? ''),
                'telefono_alt' => (string) ($patient->telefono_alt ?? ''),
                'email' => (string) ($patient->email ?? ''),
                'afiliacion' => $afiliacion,
                'tipo_afiliacion' => $tipoAfiliacion,
                'afiliacion_info' => [
                    'nombre' => $afiliacion,
                    'tipo' => $tipoAfiliacion,
                ],
                'fecha_nacimiento' => $this->dateString($patient->fecha_nacimiento),
                'sexo' => (string) ($patient->sexo ?? ''),
                'direccion' => (string) ($patient->direccion ?? ''),
                'ciudad' => (string) ($patient->ciudad ?? ''),
                'medico' => $manualMedicoId !== '' ? $manualMedicoId : ($medicoTratante !== null ? (string) $medicoTratante['nombre'] : ''),
                'medico_tratante' => $medicoTratante,
                'sede' => $sedeInfo !== null ? (string) $sedeInfo['id'] : '',
                'sede_info' => $sedeInfo,
                'ultima_visita' => (string) ($ultimaVisitas[$hcNumber] ?? ''),
                'proxima_cita' => $proximasCitas[$hcNumber] ?? null,
                'solicitud_activa' => (int) ($solicitudesActivas[$hcNumber] ?? 0),
                'sol_activa' => (int) ($solicitudesActivas[$hcNumber] ?? 0),
                'deuda' => null,
                'alerta' => $alertas[$hcNumber] ?? null,
                'created_at' => $this->dateTimeString($patient->created_at),
            ];
        })->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'count' => count($data),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
    }

    /**
     * @return array{total_pacientes:int,pacientes_nuevos:int,citas_hoy:int,solicitudes_activas:int}
     */
    public function obtenerKpisReact(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateTimeString();

        return [
            'total_pacientes' => $this->validPatientsQuery()->count(),
            'pacientes_nuevos' => $this->validPatientsQuery()
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'citas_hoy' => Schema::hasTable('procedimiento_proyectado')
                ? DB::table('procedimiento_proyectado')
                    ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
                    ->whereDate('fecha', $today)
                    ->count()
                : 0,
            'solicitudes_activas' => Schema::hasTable('solicitud_procedimiento')
                ? DB::table('solicitud_procedimiento')
                    ->whereIn(DB::raw('LOWER(COALESCE(estado, ""))'), $this->activeSolicitudStates())
                    ->count()
                : 0,
        ];
    }

    /**
     * @return array{medicos:array<int,array<string,string>>,sedes:array<int,array<string,string>>,afiliaciones:array<int,array<string,string>>,tipos_afiliacion:array<int,array<string,string>>,aseguradoras:array<int,array<string,string>>}
     */
    public function obtenerCatalogosReact(): array
    {
        return [
            'medicos' => $this->catalogoMedicos(),
            'sedes' => [
                ['id' => 'ceibos', 'label' => 'CEIBOS', 'nombre' => 'CEIBOS'],
                ['id' => 'matriz', 'label' => 'MATRIZ', 'nombre' => 'MATRIZ'],
            ],
            'afiliaciones' => $this->catalogoAfiliaciones(),
            'tipos_afiliacion' => $this->catalogoTiposAfiliacion(),
            'aseguradoras' => [],
        ];
    }

    private function validPatientsQuery(): Builder
    {
        return PatientDatum::query()
            ->whereNotNull('hc_number')
            ->whereRaw("TRIM(hc_number) <> ''")
            ->where('hc_number', 'not like', '%-%')
            ->where('hc_number', 'not like', '% %');
    }

    private function orderByNumericHc(Builder $query): Builder
    {
        $cast = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(hc_number AS INTEGER)'
            : 'CAST(hc_number AS UNSIGNED)';

        return $query->orderByRaw($cast . ' DESC')->orderByDesc('hc_number');
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,array<string,mixed>>
     */
    private function resolveMedicosTratantes(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $users = $this->validUsersByNameTokens();
        if ($users === []) {
            return [];
        }

        $rows = DB::table('procedimiento_proyectado')
            ->select('hc_number', 'id as procedimiento_id', 'fecha', 'hora', 'doctor')
            ->whereIn('hc_number', $hcNumbers)
            ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
            ->whereNotNull('doctor')
            ->whereRaw("TRIM(doctor) <> ''")
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            $doctor = trim((string) ($row->doctor ?? ''));
            $user = $this->matchUser($doctor, $users);
            if ($hcNumber === '' || $user === null) {
                continue;
            }

            $key = $hcNumber . '|' . (string) $user['id'];
            $latestKey = $this->latestKey((array) $row);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'hc_number' => $hcNumber,
                    'id' => (int) $user['id'],
                    'nombre' => (string) $user['nombre'],
                    'especialidad' => (string) $user['especialidad'],
                    'procedimientos_count' => 0,
                    'ultima_fecha' => null,
                    '_latest_key' => '',
                ];
            }

            $groups[$key]['procedimientos_count']++;
            if ($latestKey > $groups[$key]['_latest_key']) {
                $groups[$key]['_latest_key'] = $latestKey;
                $groups[$key]['ultima_fecha'] = $row->fecha !== null ? (string) $row->fecha : null;
            }
        }

        $resolved = [];
        foreach ($groups as $group) {
            $hcNumber = $group['hc_number'];
            $current = $resolved[$hcNumber] ?? null;
            if (
                $current === null
                || $group['procedimientos_count'] > $current['procedimientos_count']
                || (
                    $group['procedimientos_count'] === $current['procedimientos_count']
                    && $group['_latest_key'] > $current['_latest_key']
                )
            ) {
                $resolved[$hcNumber] = $group;
            }
        }

        foreach ($resolved as &$row) {
            unset($row['hc_number'], $row['_latest_key']);
            $row['confirmado'] = true;
        }

        return $resolved;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,array{id:string,nombre:string,origen:string}>
     */
    private function resolveSedesPacientes(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $rows = DB::table('procedimiento_proyectado')
            ->select('hc_number', 'id_sede', 'sede_departamento')
            ->whereIn('hc_number', $hcNumbers)
            ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
            ->where(static function ($query): void {
                $query->whereRaw("COALESCE(NULLIF(TRIM(id_sede), ''), NULLIF(TRIM(sede_departamento), '')) IS NOT NULL");
            })
            ->orderBy('hc_number')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        $resolved = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            if ($hcNumber === '' || isset($resolved[$hcNumber])) {
                continue;
            }

            $sede = $this->normalizeSede((string) (($row->id_sede ?: $row->sede_departamento) ?? ''));
            if ($sede === null) {
                continue;
            }

            $resolved[$hcNumber] = [
                'id' => $sede['id'],
                'nombre' => $sede['nombre'],
                'origen' => 'primera_atencion',
            ];
        }

        return $resolved;
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<string,array<string,mixed>>
     */
    private function resolveManualMedicos(array $ids): array
    {
        $ids = $this->cleanStrings($ids);
        if ($ids === [] || !Schema::hasTable('users')) {
            return [];
        }

        $items = [];
        $rows = User::query()
            ->select('id', 'nombre', 'full_name', 'especialidad', 'subespecialidad')
            ->whereIn('id', $ids)
            ->get();

        foreach ($rows as $row) {
            $id = (string) ($row->id ?? '');
            $nombre = trim((string) ($row->nombre ?: ($row->full_name ?? '')));
            $especialidad = trim((string) (($row->especialidad ?? '') ?: ($row->subespecialidad ?? '')));
            if ($id === '' || $nombre === '') {
                continue;
            }

            $items[$id] = [
                'id' => (int) $id,
                'nombre' => $nombre,
                'especialidad' => $especialidad,
                'procedimientos_count' => 0,
                'ultima_fecha' => null,
                'confirmado' => true,
                'origen' => 'manual',
            ];
        }

        return $items;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,string>
     */
    private function ultimaVisitas(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('consulta_data')) {
            return [];
        }

        return DB::table('consulta_data')
            ->select('hc_number', DB::raw('MAX(fecha) as ultima_visita'))
            ->whereIn('hc_number', $hcNumbers)
            ->groupBy('hc_number')
            ->pluck('ultima_visita', 'hc_number')
            ->map(static fn(mixed $date): string => (string) $date)
            ->all();
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,array<string,string>>
     */
    private function proximasCitas(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $rows = DB::table('procedimiento_proyectado')
            ->select('hc_number', 'fecha', 'hora', 'procedimiento_proyectado', 'doctor', 'id')
            ->whereIn('hc_number', $hcNumbers)
            ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
            ->whereNotNull('fecha')
            ->whereDate('fecha', '>=', now()->toDateString())
            ->orderBy('hc_number')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            if ($hcNumber === '' || isset($items[$hcNumber])) {
                continue;
            }

            $items[$hcNumber] = [
                'fecha' => (string) ($row->fecha ?? ''),
                'hora' => (string) ($row->hora ?? ''),
                'tipo' => (string) ($row->procedimiento_proyectado ?? ''),
                'medico' => (string) ($row->doctor ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,int>
     */
    private function solicitudesActivas(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('solicitud_procedimiento')) {
            return [];
        }

        return DB::table('solicitud_procedimiento')
            ->select('hc_number', DB::raw('COUNT(*) as total'))
            ->whereIn('hc_number', $hcNumbers)
            ->whereIn(DB::raw('LOWER(COALESCE(estado, ""))'), $this->activeSolicitudStates())
            ->groupBy('hc_number')
            ->pluck('total', 'hc_number')
            ->map(static fn(mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,string>
     */
    private function alertas(array $hcNumbers): array
    {
        $hcNumbers = $this->cleanStrings($hcNumbers);
        if ($hcNumbers === [] || !Schema::hasTable('consulta_data')) {
            return [];
        }

        $rows = DB::table('consulta_data')
            ->select('hc_number', 'antecedente_alergico', 'id')
            ->whereIn('hc_number', $hcNumbers)
            ->whereNotNull('antecedente_alergico')
            ->whereRaw("TRIM(antecedente_alergico) <> ''")
            ->orderBy('hc_number')
            ->orderByDesc('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            if ($hcNumber !== '' && !isset($items[$hcNumber])) {
                $items[$hcNumber] = (string) $row->antecedente_alergico;
            }
        }

        return $items;
    }

    /**
     * @return array<int,array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}>
     */
    private function validUsersByNameTokens(): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $users = [];
        $rows = User::query()
            ->select('id', 'nombre', 'full_name', 'especialidad', 'subespecialidad')
            ->where(static function ($query): void {
                $query->whereRaw("(nombre IS NOT NULL AND TRIM(nombre) <> '')")
                    ->orWhereRaw("(full_name IS NOT NULL AND TRIM(full_name) <> '')");
            })
            ->get();

        foreach ($rows as $row) {
            $especialidad = trim((string) ($row->especialidad ?? ''));
            $subespecialidad = trim((string) ($row->subespecialidad ?? ''));
            if (!$this->isEspecialidadTratante($especialidad . ' ' . $subespecialidad)) {
                continue;
            }

            $nombre = trim((string) ($row->nombre ?: ($row->full_name ?? '')));
            $tokens = $this->nameTokens($nombre);
            if ($nombre === '' || count($tokens) < 2) {
                continue;
            }

            $users[] = [
                'id' => (int) ($row->id ?? 0),
                'nombre' => $nombre,
                'especialidad' => $especialidad !== '' ? $especialidad : $subespecialidad,
                'tokens' => $tokens,
            ];
        }

        return $users;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoMedicos(): array
    {
        $items = [];
        foreach ($this->validUsersByNameTokens() as $row) {
            $key = (string) $row['id'];
            $items[$key] = [
                'id' => $key,
                'full' => $row['nombre'],
                'nombre' => $row['nombre'],
                'esp' => $row['especialidad'],
                'especialidad' => $row['especialidad'],
                'sede' => '',
                'id_trabajador' => '',
            ];
        }

        uasort($items, static fn(array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        return array_values($items);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoAfiliaciones(): array
    {
        $resolver = new TipoAfiliacionResolver();
        $afiliaciones = PatientDatum::query()
            ->whereNotNull('afiliacion')
            ->whereRaw("TRIM(afiliacion) <> ''")
            ->pluck('afiliacion')
            ->map(static fn(mixed $afiliacion): string => trim((string) $afiliacion))
            ->filter(static fn(string $afiliacion): bool => $afiliacion !== '' && preg_match('/^[A-Za-z]/', $afiliacion) === 1)
            ->unique()
            ->sort()
            ->values();

        return $afiliaciones
            ->map(fn(string $afiliacion): array => [
                'id' => $this->catalogKey($afiliacion),
                'label' => $afiliacion,
                'nombre' => $afiliacion,
                'tipo_afiliacion' => $resolver->classify($afiliacion),
            ])
            ->all();
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoTiposAfiliacion(): array
    {
        $resolver = new TipoAfiliacionResolver();

        return array_map(
            static fn(string $tipo): array => $resolver->metadata($tipo),
            ['publico', 'privado', 'particular', 'fundacional', 'otros']
        );
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private function cleanStrings(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim((string) $value),
            $values
        ))));
    }

    private function normalizarAfiliacionListado(string $afiliacion): string
    {
        $afiliacion = trim($afiliacion);
        if ($afiliacion === '' || preg_match('/^\d+(?:\.\d+)?$/', $afiliacion) === 1) {
            return '';
        }

        return $afiliacion;
    }

    /**
     * @return array{id:string,nombre:string,origen:string}|null
     */
    private function normalizarSedePrincipal(string $sede): ?array
    {
        $value = strtolower(trim($sede));
        if ($value === '') {
            return null;
        }

        $plain = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $plain = preg_replace('/[^a-z0-9]+/', '', $plain) ?? $plain;

        if (str_contains($plain, 'ceibos') || $plain === '16') {
            return ['id' => 'ceibos', 'nombre' => 'CEIBOS', 'origen' => 'manual'];
        }

        if (str_contains($plain, 'matriz') || $plain === '1') {
            return ['id' => 'matriz', 'nombre' => 'MATRIZ', 'origen' => 'manual'];
        }

        return null;
    }

    /**
     * @return array{id:string,nombre:string}|null
     */
    private function normalizeSede(string $value): ?array
    {
        $normalized = strtolower(trim($value));
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? $normalized;

        if (str_contains($normalized, 'ceibos') || $compact === '16') {
            return ['id' => 'ceibos', 'nombre' => 'CEIBOS'];
        }

        if (str_contains($normalized, 'matriz') || $compact === '1') {
            return ['id' => 'matriz', 'nombre' => 'MATRIZ'];
        }

        return null;
    }

    /**
     * @param array<int,array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}> $users
     * @return array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}|null
     */
    private function matchUser(string $doctor, array $users): ?array
    {
        $doctorTokens = $this->nameTokens($doctor);
        if (count($doctorTokens) < 2) {
            return null;
        }

        $doctorSet = array_fill_keys($doctorTokens, true);
        foreach ($users as $user) {
            $matched = 0;
            foreach ($user['tokens'] as $token) {
                if (isset($doctorSet[$token])) {
                    $matched++;
                }
            }

            if ($matched === count($user['tokens'])) {
                return $user;
            }
        }

        return null;
    }

    private function isEspecialidadTratante(string $especialidad): bool
    {
        $value = strtolower(trim($especialidad));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        if (str_contains($value, 'optometr') || str_contains($value, 'administrativo')) {
            return false;
        }

        return str_contains($value, 'cirujano') && str_contains($value, 'oftalm');
    }

    /**
     * @return array<int,string>
     */
    private function nameTokens(string $name): array
    {
        $name = strtolower(trim($name));
        $name = strtr($name, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $tokens = preg_split('/[^a-z0-9]+/', $name) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static fn(string $token): bool => $token !== '')));
        sort($tokens);

        return $tokens;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function latestKey(array $row): string
    {
        return sprintf(
            '%s %s %012d',
            (string) ($row['fecha'] ?? ''),
            (string) ($row['hora'] ?? ''),
            (int) ($row['procedimiento_id'] ?? 0)
        );
    }

    private function fullName(PatientDatum $patient): string
    {
        return trim(implode(' ', array_filter([
            $patient->lname,
            $patient->lname2,
            $patient->fname,
            $patient->mname,
        ], static fn(mixed $part): bool => trim((string) $part) !== '')));
    }

    private function displayName(PatientDatum $patient): string
    {
        return trim(implode(' ', array_filter([
            $patient->fname,
            $patient->mname,
            $patient->lname,
            $patient->lname2,
        ], static fn(mixed $part): bool => trim((string) $part) !== '')));
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) ($value ?? '');
    }

    private function dateTimeString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return (string) ($value ?? '');
    }

    /**
     * @return array<int,string>
     */
    private function activeSolicitudStates(): array
    {
        return ['ingresada', 'cotizacion', 'cotización', 'en_proceso', 'en proceso', 'autorizada'];
    }

    private function catalogKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : md5($value);
    }
}
