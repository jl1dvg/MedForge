<?php

namespace App\Modules\Pacientes\Services;

use Carbon\CarbonImmutable;
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

        $rows = $this->validPatientsQuery()
            ->select($this->patientSelectColumns())
            ->get()
            ->sort(function (object $a, object $b): int {
                return $this->compareHcNumbersDesc((string) ($a->hc_number ?? ''), (string) ($b->hc_number ?? ''));
            })
            ->values();

        $total = $rows->count();
        $pageRows = $limit !== null ? $rows->slice($offset, $limit)->values() : $rows;
        $hcNumbers = $pageRows->pluck('hc_number')->map(fn(mixed $value): string => (string) $value)->filter()->values()->all();

        $latestVisitByHc = $this->latestVisitByHc($hcNumbers);
        $appointmentsByHc = $this->appointmentsByHc($hcNumbers);
        $activeRequestsByHc = $this->activeRequestsByHc($hcNumbers);
        $alertsByHc = $this->alertsByHc($hcNumbers);
        $manualDoctors = $this->manualDoctorsById(
            $pageRows->pluck('medico_tratante_id')->map(fn(mixed $value): string => (string) $value)->filter()->values()->all()
        );
        $tipoResolver = new TipoAfiliacionResolver();

        $data = [];
        foreach ($pageRows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            $afiliacion = $this->normalizarAfiliacionListado((string) ($row->afiliacion ?? ''));
            $tipoAfiliacion = $tipoResolver->classify($afiliacion);
            $manualMedicoId = (string) ($row->medico_tratante_id ?? '');
            $appointment = $appointmentsByHc[$hcNumber] ?? [];
            $medicoTratante = $manualMedicoId !== '' && isset($manualDoctors[$manualMedicoId])
                ? $manualDoctors[$manualMedicoId]
                : ($appointment['medico_tratante'] ?? null);
            $sedeInfo = $this->normalizarSedePrincipal((string) ($row->sede_principal ?? ''))
                ?? ($appointment['sede_info'] ?? null);

            $data[] = [
                'hc_number' => $hcNumber,
                'fname' => (string) ($row->fname ?? ''),
                'mname' => (string) ($row->mname ?? ''),
                'lname' => (string) ($row->lname ?? ''),
                'lname2' => (string) ($row->lname2 ?? ''),
                'full_name' => $this->fullName($row, false),
                'display_name' => $this->fullName($row, true),
                'cedula' => $hcNumber,
                'telefono' => (string) ($row->celular ?? ''),
                'telefono_alt' => (string) ($row->telefono_alt ?? ''),
                'email' => (string) ($row->email ?? ''),
                'afiliacion' => $afiliacion,
                'tipo_afiliacion' => $tipoAfiliacion,
                'afiliacion_info' => [
                    'nombre' => $afiliacion,
                    'tipo' => $tipoAfiliacion,
                ],
                'fecha_nacimiento' => (string) ($row->fecha_nacimiento ?? ''),
                'sexo' => (string) ($row->sexo ?? ''),
                'direccion' => (string) ($row->direccion ?? ''),
                'ciudad' => (string) ($row->ciudad ?? ''),
                'medico' => $medicoTratante !== null ? (string) ($medicoTratante['nombre'] ?? '') : '',
                'medico_tratante' => $medicoTratante,
                'sede' => $sedeInfo !== null ? (string) ($sedeInfo['id'] ?? '') : '',
                'sede_info' => $sedeInfo,
                'ultima_visita' => (string) ($latestVisitByHc[$hcNumber] ?? ''),
                'proxima_cita' => $appointment['proxima_cita'] ?? null,
                'solicitud_activa' => (int) ($activeRequestsByHc[$hcNumber] ?? 0),
                'sol_activa' => (int) ($activeRequestsByHc[$hcNumber] ?? 0),
                'deuda' => null,
                'alerta' => $alertsByHc[$hcNumber] ?? null,
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

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
        $today = CarbonImmutable::now()->toDateString();
        $monthStart = CarbonImmutable::now()->startOfMonth()->toDateTimeString();

        return [
            'total_pacientes' => (int) $this->validPatientsQuery()->count(),
            'pacientes_nuevos' => (int) $this->validPatientsQuery()
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'citas_hoy' => $this->tableExists('procedimiento_proyectado')
                ? (int) DB::table('procedimiento_proyectado')
                    ->where(function ($query): void {
                        $query->whereNull('sigcenter_present')->orWhere('sigcenter_present', 1);
                    })
                    ->whereDate('fecha', $today)
                    ->count()
                : 0,
            'solicitudes_activas' => $this->tableExists('solicitud_procedimiento')
                ? (int) DB::table('solicitud_procedimiento')->whereIn(DB::raw("LOWER(COALESCE(estado, ''))"), $this->activeRequestStates())->count()
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
                ['id' => 'matriz', 'label' => 'MATRIZ', 'nombre' => 'MATRIZ'],
                ['id' => 'ceibos', 'label' => 'CEIBOS', 'nombre' => 'CEIBOS'],
            ],
            'afiliaciones' => $this->catalogoAfiliaciones(),
            'tipos_afiliacion' => $this->catalogoTiposAfiliacion(),
            'aseguradoras' => [],
        ];
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,data:array<int,array<string,mixed>>}
     */
    public function obtenerPacientesPaginados(
        int $start,
        int $length,
        string $search = '',
        string $orderColumn = 'hc_number',
        string $orderDir = 'ASC'
    ): array {
        $start = max(0, $start);
        $length = max(1, $length);
        $orderDirection = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $orderColumn = in_array($orderColumn, ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'], true)
            ? $orderColumn
            : 'hc_number';

        $baseQuery = $this->datatableBaseQuery();
        $recordsTotal = (int) (clone $baseQuery)->count();

        $filteredQuery = clone $baseQuery;
        $search = trim($search);
        if ($search !== '') {
            $filteredQuery->where(function ($query) use ($search): void {
                $query
                    ->where('p.hc_number', 'like', '%' . $search . '%')
                    ->orWhere('p.fname', 'like', '%' . $search . '%')
                    ->orWhere('p.mname', 'like', '%' . $search . '%')
                    ->orWhere('p.lname', 'like', '%' . $search . '%')
                    ->orWhere('p.lname2', 'like', '%' . $search . '%')
                    ->orWhere('p.afiliacion', 'like', '%' . $search . '%');
            });
        }

        $recordsFiltered = (int) (clone $filteredQuery)->count();
        $orderableMap = [
            'hc_number' => 'p.hc_number',
            'ultima_fecha' => 'ultima.ultima_fecha',
            'full_name' => 'p.fname',
            'afiliacion' => 'p.afiliacion',
        ];

        $rows = $filteredQuery
            ->select([
                'p.hc_number',
                'p.fname',
                'p.mname',
                'p.lname',
                'p.lname2',
                'p.afiliacion',
                'ultima.ultima_fecha',
            ])
            ->orderBy($orderableMap[$orderColumn] ?? 'p.hc_number', $orderDirection)
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            $ultimaFecha = '';
            if (!empty($row->ultima_fecha)) {
                $timestamp = strtotime((string) $row->ultima_fecha);
                $ultimaFecha = $timestamp ? date('d/m/Y', $timestamp) : '';
            }

            $data[] = [
                'hc_number' => $hcNumber,
                'ultima_fecha' => $ultimaFecha,
                'full_name' => trim(preg_replace('/\s+/', ' ', implode(' ', [
                    (string) ($row->fname ?? ''),
                    (string) ($row->lname ?? ''),
                    (string) ($row->lname2 ?? ''),
                ])) ?: ''),
                'afiliacion' => $this->normalizarAfiliacionListado((string) ($row->afiliacion ?? '')),
                'acciones_html' => "<a href='/v2/pacientes/detalles?hc_number=" . urlencode($hcNumber) . "' class='btn btn-sm btn-primary'>Ver</a>",
            ];
        }

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    private function validPatientsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('patient_data')
            ->whereNotNull('hc_number')
            ->whereRaw("TRIM(hc_number) <> ''")
            ->where('hc_number', 'not like', '%-%')
            ->where('hc_number', 'not like', '% %');
    }

    private function datatableBaseQuery(): \Illuminate\Database\Query\Builder
    {
        $latestVisits = DB::table('consulta_data')
            ->select('hc_number', DB::raw('MAX(fecha) AS ultima_fecha'))
            ->groupBy('hc_number');

        return DB::table('patient_data AS p')
            ->leftJoinSub($latestVisits, 'ultima', 'ultima.hc_number', '=', 'p.hc_number');
    }

    /**
     * @return array<int,string>
     */
    private function patientSelectColumns(): array
    {
        $columns = [
            'hc_number',
            'fname',
            'mname',
            'lname',
            'lname2',
            'afiliacion',
            'fecha_nacimiento',
            'sexo',
            'celular',
            'email',
            'direccion',
            'ciudad',
            'created_at',
        ];

        foreach (['telefono_alt', 'medico_tratante_id', 'sede_principal'] as $optional) {
            if ($this->columnExists('patient_data', $optional)) {
                $columns[] = $optional;
            }
        }

        return $columns;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,string>
     */
    private function latestVisitByHc(array $hcNumbers): array
    {
        if ($hcNumbers === [] || !$this->tableExists('consulta_data')) {
            return [];
        }

        return DB::table('consulta_data')
            ->select('hc_number', DB::raw('MAX(fecha) AS ultima_visita'))
            ->whereIn('hc_number', $hcNumbers)
            ->groupBy('hc_number')
            ->pluck('ultima_visita', 'hc_number')
            ->map(fn(mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,array<string,mixed>>
     */
    private function appointmentsByHc(array $hcNumbers): array
    {
        if ($hcNumbers === [] || !$this->tableExists('procedimiento_proyectado')) {
            return [];
        }

        $today = CarbonImmutable::now()->toDateString();
        $rows = DB::table('procedimiento_proyectado')
            ->whereIn('hc_number', $hcNumbers)
            ->where(function ($query): void {
                $query->whereNull('sigcenter_present')->orWhere('sigcenter_present', 1);
            })
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            if ($hcNumber === '') {
                continue;
            }

            $sedeInfo = $this->normalizarSedePrincipal((string) ($row->id_sede ?? ''))
                ?? $this->normalizarSedePrincipal((string) ($row->sede_departamento ?? ''));
            $doctor = trim((string) ($row->doctor ?? ''));
            if (!isset($items[$hcNumber])) {
                $items[$hcNumber] = [
                    'medico_tratante' => $doctor !== '' ? [
                        'id' => $this->catalogKey($doctor),
                        'nombre' => $doctor,
                        'especialidad' => '',
                        'procedimientos_count' => 0,
                        'ultima_fecha' => null,
                        'confirmado' => true,
                        'origen' => 'procedimiento',
                    ] : null,
                    'sede_info' => $sedeInfo,
                    'proxima_cita' => null,
                ];
            }

            $fecha = (string) ($row->fecha ?? '');
            if ($fecha !== '' && $fecha >= $today && ($items[$hcNumber]['proxima_cita'] ?? null) === null) {
                $items[$hcNumber]['proxima_cita'] = [
                    'fecha' => $fecha,
                    'hora' => (string) ($row->hora ?? ''),
                    'tipo' => (string) ($row->procedimiento_proyectado ?? ''),
                    'medico' => $doctor,
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,int>
     */
    private function activeRequestsByHc(array $hcNumbers): array
    {
        if ($hcNumbers === [] || !$this->tableExists('solicitud_procedimiento')) {
            return [];
        }

        return DB::table('solicitud_procedimiento')
            ->select('hc_number', DB::raw('COUNT(*) AS total'))
            ->whereIn('hc_number', $hcNumbers)
            ->whereIn(DB::raw("LOWER(COALESCE(estado, ''))"), $this->activeRequestStates())
            ->groupBy('hc_number')
            ->pluck('total', 'hc_number')
            ->map(fn(mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,string|null>
     */
    private function alertsByHc(array $hcNumbers): array
    {
        if ($hcNumbers === [] || !$this->tableExists('consulta_data') || !$this->columnExists('consulta_data', 'antecedente_alergico')) {
            return [];
        }

        $rows = DB::table('consulta_data')
            ->select('hc_number', 'antecedente_alergico')
            ->whereIn('hc_number', $hcNumbers)
            ->whereNotNull('antecedente_alergico')
            ->whereRaw("TRIM(antecedente_alergico) <> ''")
            ->orderByDesc('id')
            ->get();

        $alerts = [];
        foreach ($rows as $row) {
            $hcNumber = (string) ($row->hc_number ?? '');
            if ($hcNumber !== '' && !array_key_exists($hcNumber, $alerts)) {
                $alerts[$hcNumber] = trim((string) ($row->antecedente_alergico ?? '')) ?: null;
            }
        }

        return $alerts;
    }

    /**
     * @param array<int,string> $ids
     * @return array<string,array<string,mixed>>
     */
    private function manualDoctorsById(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || !$this->tableExists('users')) {
            return [];
        }

        $rows = DB::table('users')
            ->select('id', 'nombre', 'full_name', 'subespecialidad', 'especialidad')
            ->whereIn('id', $ids)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $id = (string) ($row->id ?? '');
            $nombre = trim((string) (($row->nombre ?? '') ?: ($row->full_name ?? '')));
            if ($id === '' || $nombre === '') {
                continue;
            }

            $items[$id] = [
                'id' => (int) $id,
                'nombre' => $nombre,
                'especialidad' => trim((string) (($row->especialidad ?? '') ?: ($row->subespecialidad ?? ''))),
                'procedimientos_count' => 0,
                'ultima_fecha' => null,
                'confirmado' => true,
                'origen' => 'manual',
            ];
        }

        return $items;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoMedicos(): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $rows = DB::table('users')
            ->select('id', 'nombre', 'full_name', 'subespecialidad', 'especialidad', 'sede', 'id_trabajador')
            ->where(function ($query): void {
                $query->whereNotNull('nombre')->orWhereNotNull('full_name');
            })
            ->orderBy('nombre')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $nombre = trim((string) (($row->nombre ?? '') ?: ($row->full_name ?? '')));
            $especialidadBase = trim((string) ($row->especialidad ?? ''));
            $subespecialidad = trim((string) ($row->subespecialidad ?? ''));
            $especialidad = $especialidadBase !== '' ? $especialidadBase : $subespecialidad;
            if ($nombre === '' || !$this->isEspecialidadTratante($especialidadBase . ' ' . $subespecialidad)) {
                continue;
            }

            $key = (string) ($row->id ?? $this->catalogKey($nombre));
            $items[$key] = [
                'id' => $key,
                'full' => $nombre,
                'nombre' => $nombre,
                'esp' => $especialidad,
                'especialidad' => $especialidad,
                'sede' => trim((string) ($row->sede ?? '')),
                'id_trabajador' => (string) ($row->id_trabajador ?? ''),
            ];
        }

        return array_values($items);
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function catalogoAfiliaciones(): array
    {
        $resolver = new TipoAfiliacionResolver();
        $afiliaciones = $this->getAfiliacionesCatalogo();
        if ($afiliaciones === []) {
            $afiliaciones = $this->getAfiliacionesPatientData();
        }

        return array_map(
            fn(string $afiliacion): array => [
                'id' => $this->catalogKey($afiliacion),
                'label' => $afiliacion,
                'nombre' => $afiliacion,
                'tipo_afiliacion' => $resolver->classify($afiliacion),
            ],
            $afiliaciones
        );
    }

    /**
     * @return array<int,string>
     */
    private function getAfiliacionesCatalogo(): array
    {
        if (!$this->tableExists('sigcenter_afiliaciones')) {
            return [];
        }

        $query = DB::table('sigcenter_afiliaciones')
            ->whereNotNull('nombre')
            ->whereRaw("TRIM(nombre) <> ''");
        if ($this->columnExists('sigcenter_afiliaciones', 'activo')) {
            $query->where(function ($query): void {
                $query->whereNull('activo')->orWhere('activo', true);
            });
        }

        return $query
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn(mixed $value): string => trim((string) $value))
            ->filter(fn(string $value): bool => $this->normalizarAfiliacionListado($value) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function getAfiliacionesPatientData(): array
    {
        return DB::table('patient_data')
            ->whereNotNull('afiliacion')
            ->whereRaw("TRIM(afiliacion) <> ''")
            ->orderBy('afiliacion')
            ->pluck('afiliacion')
            ->map(fn(mixed $value): string => $this->normalizarAfiliacionListado((string) $value))
            ->filter()
            ->unique()
            ->values()
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

    private function normalizarSedePrincipal(string $sede): ?array
    {
        $value = strtolower(trim($sede));
        if ($value === '') {
            return null;
        }

        $plain = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $plain = preg_replace('/[^a-z0-9]+/', '', $plain) ?? $plain;

        if (str_contains($plain, 'ceibos') || $plain === '16') {
            return ['id' => 'ceibos', 'nombre' => 'CEIBOS', 'origen' => 'laravel'];
        }

        if (str_contains($plain, 'matriz') || $plain === '1') {
            return ['id' => 'matriz', 'nombre' => 'MATRIZ', 'origen' => 'laravel'];
        }

        return null;
    }

    private function normalizarAfiliacionListado(string $afiliacion): string
    {
        $afiliacion = trim($afiliacion);
        if ($afiliacion === '' || preg_match('/^\d+(?:\.\d+)?$/', $afiliacion) === 1) {
            return '';
        }

        return $afiliacion;
    }

    private function fullName(object $row, bool $displayOrder): string
    {
        $parts = $displayOrder
            ? [$row->fname ?? '', $row->mname ?? '', $row->lname ?? '', $row->lname2 ?? '']
            : [$row->lname ?? '', $row->lname2 ?? '', $row->fname ?? '', $row->mname ?? ''];

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_map(static fn(mixed $value): string => trim((string) $value), $parts))) ?: '');
    }

    private function compareHcNumbersDesc(string $left, string $right): int
    {
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);
        if ($leftNumeric && $rightNumeric) {
            return (int) $right <=> (int) $left;
        }

        if ($leftNumeric !== $rightNumeric) {
            return $leftNumeric ? -1 : 1;
        }

        return strcmp($right, $left);
    }

    /**
     * @return array<int,string>
     */
    private function activeRequestStates(): array
    {
        return ['ingresada', 'cotizacion', 'cotización', 'en_proceso', 'en proceso', 'autorizada'];
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

    private function catalogKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : md5($value);
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
