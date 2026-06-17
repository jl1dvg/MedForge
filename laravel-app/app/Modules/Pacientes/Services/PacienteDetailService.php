<?php

namespace App\Modules\Pacientes\Services;

use App\Models\PatientDatum;
use Carbon\CarbonInterface;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PacienteDetailService
{
    /**
     * @return array<string,mixed>
     */
    public function obtenerContextoPaciente(string $hcNumber): array
    {
        $patientData = $this->getPatientDetails($hcNumber);

        if ($patientData === []) {
            return [];
        }

        $timelineLimit = 100;
        $solicitudes = $this->getSolicitudesPorPaciente($hcNumber, $timelineLimit);
        $prefacturas = $this->getPrefacturasPorPaciente($hcNumber, $timelineLimit);

        return [
            'patientData' => $patientData,
            'afiliacionesDisponibles' => $this->getAfiliacionesDisponibles(),
            'diagnosticos' => $this->getDiagnosticosPorPaciente($hcNumber),
            'medicos' => $this->getDoctoresAsignados($hcNumber),
            'timelineItems' => $this->ordenarTimeline(array_merge($solicitudes, $prefacturas)),
            'eventos' => $this->getEventosTimeline($hcNumber),
            'documentos' => $this->getDocumentosDescargables($hcNumber),
            'estadisticas' => $this->getEstadisticasProcedimientos($hcNumber),
            'patientAge' => $this->calcularEdad($patientData['fecha_nacimiento'] ?? null),
            'coverageStatus' => $this->verificarCoberturaPaciente($hcNumber),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function getPatientDetails(string $hcNumber): array
    {
        $patient = PatientDatum::query()
            ->where('hc_number', $hcNumber)
            ->first();

        if (!$patient instanceof PatientDatum) {
            return [];
        }

        $data = $patient->getAttributes();
        $data['fecha_nacimiento'] = $this->dateString($patient->fecha_nacimiento);
        $data['fecha_caducidad'] = $this->dateString($patient->fecha_caducidad ?? null);
        $data['created_at'] = $this->dateTimeString($patient->created_at);
        $data['updated_at'] = $this->dateTimeString($patient->updated_at);

        if (trim((string) ($data['cedula'] ?? '')) === '') {
            $data['cedula'] = $hcNumber;
        }

        return $data;
    }

    /**
     * @return array<string,array<string,string|null>>
     */
    private function getDiagnosticosPorPaciente(string $hcNumber): array
    {
        $uniqueDiagnoses = [];

        if (Schema::hasTable('prefactura_detalle_diagnosticos') && Schema::hasTable('prefactura_paciente')) {
            $rows = DB::table('prefactura_detalle_diagnosticos as d')
                ->join('prefactura_paciente as pp', 'pp.id', '=', 'd.prefactura_id')
                ->select('d.diagnostico_codigo', 'd.descripcion', 'pp.fecha_creacion', 'pp.fecha_registro')
                ->where('pp.hc_number', $hcNumber)
                ->orderByDesc('pp.fecha_creacion')
                ->orderBy('d.posicion')
                ->get();

            foreach ($rows as $row) {
                $codigo = $row->diagnostico_codigo ?: ($row->descripcion ?? null);
                if (!$codigo) {
                    continue;
                }

                $key = (string) $codigo;
                if (!isset($uniqueDiagnoses[$key])) {
                    $fechaEvento = $row->fecha_creacion ?? $row->fecha_registro ?? null;
                    $timestamp = $fechaEvento ? strtotime((string) $fechaEvento) : false;
                    $uniqueDiagnoses[$key] = [
                        'idDiagnostico' => (string) ($row->diagnostico_codigo ?: $key),
                        'fecha' => $timestamp ? date('d M Y', $timestamp) : null,
                    ];
                }
            }
        }

        if (!Schema::hasTable('consulta_data')) {
            return $uniqueDiagnoses;
        }

        $rows = DB::table('consulta_data')
            ->select('fecha', 'diagnosticos')
            ->where('hc_number', $hcNumber)
            ->orderByDesc('fecha')
            ->get();

        foreach ($rows as $row) {
            $diagnosticos = json_decode((string) $row->diagnosticos, true) ?: [];
            $timestamp = strtotime((string) $row->fecha);
            $fecha = $timestamp ? date('d M Y', $timestamp) : null;

            foreach ($diagnosticos as $diagnostico) {
                $id = $diagnostico['idDiagnostico'] ?? null;
                if ($id && !isset($uniqueDiagnoses[$id])) {
                    $uniqueDiagnoses[$id] = [
                        'idDiagnostico' => (string) $id,
                        'fecha' => $fecha,
                    ];
                }
            }
        }

        return $uniqueDiagnoses;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function getDoctoresAsignados(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $rows = DB::table('procedimiento_proyectado')
            ->select('doctor', 'form_id')
            ->where('hc_number', $hcNumber)
            ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
            ->whereNotNull('doctor')
            ->whereRaw("TRIM(doctor) <> ''")
            ->where('doctor', 'not like', '%optometría%')
            ->orderByDesc('form_id')
            ->get();

        $doctores = [];
        foreach ($rows as $row) {
            $doctor = (string) $row->doctor;
            if (!isset($doctores[$doctor])) {
                $doctores[$doctor] = [
                    'doctor' => $doctor,
                    'form_id' => $row->form_id,
                ];
            }
        }

        return $doctores;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getSolicitudesPorPaciente(string $hcNumber, int $limit): array
    {
        if (!Schema::hasTable('solicitud_procedimiento')) {
            return [];
        }

        return DB::table('solicitud_procedimiento')
            ->select('procedimiento', 'created_at', 'tipo', 'form_id')
            ->where('hc_number', $hcNumber)
            ->where('procedimiento', '!=', '')
            ->where('procedimiento', '!=', 'SELECCIONE')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static fn(object $row): array => [
                'nombre' => $row->procedimiento,
                'fecha' => $row->created_at,
                'tipo' => strtolower((string) ($row->tipo ?? 'otro')),
                'form_id' => $row->form_id,
                'origen' => 'Solicitud',
            ])
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPrefacturasPorPaciente(string $hcNumber, int $limit): array
    {
        if (!Schema::hasTable('prefactura_paciente')) {
            return [];
        }

        $rows = DB::table('prefactura_paciente')
            ->where('hc_number', $hcNumber)
            ->whereNotNull('cod_derivacion')
            ->where('cod_derivacion', '!=', '')
            ->orderByDesc('fecha_creacion')
            ->limit($limit)
            ->get();

        $prefacturas = [];
        foreach ($rows as $row) {
            $rowData = (array) $row;
            $detalles = $this->obtenerProcedimientosNormalizados((int) ($row->id ?? 0));
            $procedimientos = [];

            if ($detalles !== null) {
                $procedimientos = $detalles;
                $rowData['procedimientos_detalle'] = $detalles;
            } elseif (!empty($row->procedimientos) && is_string($row->procedimientos)) {
                $procedimientos = json_decode($row->procedimientos, true) ?: [];
            }

            $nombreProcedimientos = $this->formatProcedimientosPrefactura($procedimientos);

            $prefacturas[] = [
                'nombre' => "Prefactura\n" . $nombreProcedimientos,
                'fecha' => $row->fecha_creacion,
                'tipo' => 'prefactura',
                'form_id' => $row->form_id ?? null,
                'detalle' => $rowData,
                'origen' => 'Prefactura',
            ];
        }

        return $prefacturas;
    }

    /**
     * @param array<int,array<string,mixed>> $procedimientos
     */
    private function formatProcedimientosPrefactura(array $procedimientos): string
    {
        if ($procedimientos === []) {
            return 'Procedimientos no disponibles';
        }

        $nombreProcedimientos = '';
        foreach ($procedimientos as $index => $proc) {
            $linea = ($index + 1) . '. ';
            $descripcion = $proc['descripcion']
                ?? $proc['procedimiento']
                ?? $proc['procInterno']
                ?? $proc['procDetalle']
                ?? $proc['codigo']
                ?? 'Procedimiento';
            $linea .= $descripcion;

            $lateralidad = $proc['lateralidad'] ?? $proc['ojoId'] ?? null;
            if (!empty($lateralidad)) {
                $linea .= ' - Ojo: ' . $lateralidad;
            }

            if (!empty($proc['observaciones'])) {
                $linea .= ' (' . $proc['observaciones'] . ')';
            }

            $nombreProcedimientos .= $linea . "\n";
        }

        return $nombreProcedimientos;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getDocumentosDescargables(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado') || !Schema::hasTable('protocolo_data')) {
            return [];
        }

        $protocolos = DB::table('protocolo_data as pd')
            ->leftJoinSub(
                DB::table('procedimiento_proyectado')
                    ->select('hc_number', 'form_id')
                    ->selectRaw('MAX(procedimiento_proyectado) AS procedimiento_proyectado')
                    ->selectRaw('MAX(fecha) AS fecha')
                    ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
                    ->groupBy('hc_number', 'form_id'),
                'pp',
                static function ($join): void {
                    $join->on('pp.hc_number', '=', 'pd.hc_number')
                        ->on('pp.form_id', '=', 'pd.form_id');
                }
            )
            ->select('pd.form_id', 'pd.hc_number')
            ->selectRaw("COALESCE(NULLIF(pd.membrete, ''), pp.procedimiento_proyectado, 'Procedimiento quirúrgico') AS membrete")
            ->selectRaw('COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_inicio')
            ->where('pd.hc_number', $hcNumber)
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();

        $procedimientos = DB::table('procedimiento_proyectado as pp')
            ->select('pp.form_id', 'pp.hc_number')
            ->selectRaw('MAX(pp.procedimiento_proyectado) AS procedimiento')
            ->selectRaw('MAX(pp.fecha) AS created_at')
            ->where('pp.hc_number', $hcNumber)
            ->where(static fn($query) => $query->where('pp.sigcenter_present', true)->orWhereNull('pp.sigcenter_present'))
            ->whereNotNull('pp.form_id')
            ->whereRaw("TRIM(pp.form_id) <> ''")
            ->whereNotNull('pp.procedimiento_proyectado')
            ->whereRaw("TRIM(pp.procedimiento_proyectado) <> ''")
            ->whereRaw("UPPER(TRIM(pp.procedimiento_proyectado)) <> 'SELECCIONE'")
            ->where(static function ($query): void {
                $query->whereRaw("UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'PNI%'")
                    ->orWhereRaw("UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'CIRUGIAS%'");
            })
            ->groupBy('pp.hc_number', 'pp.form_id')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();

        $documentosByForm = [];
        foreach ($procedimientos as $documento) {
            if (!$this->esProcedimientoPniOCirugia($documento['procedimiento'] ?? null)) {
                continue;
            }
            $formId = trim((string) ($documento['form_id'] ?? ''));
            $key = $formId !== '' ? 'form:' . $formId : 'proc:' . md5(json_encode($documento) ?: '');
            $documentosByForm[$key] = $documento;
        }

        foreach ($protocolos as $documento) {
            $formId = trim((string) ($documento['form_id'] ?? ''));
            $key = $formId !== '' ? 'form:' . $formId : 'proto:' . md5(json_encode($documento) ?: '');
            $documentosByForm[$key] = $documento;
        }

        $documentos = array_values($documentosByForm);
        usort($documentos, static function (array $a, array $b): int {
            $fechaA = $a['fecha_inicio'] ?? $a['created_at'] ?? null;
            $fechaB = $b['fecha_inicio'] ?? $b['created_at'] ?? null;
            return strtotime((string) ($fechaB ?? 'now')) <=> strtotime((string) ($fechaA ?? 'now'));
        });

        return $documentos;
    }

    private function esProcedimientoPniOCirugia(mixed $procedimiento): bool
    {
        $texto = strtoupper(trim((string) $procedimiento));
        if ($texto === '') {
            return false;
        }

        return str_starts_with($texto, 'PNI') || str_starts_with($texto, 'CIRUGIAS');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getEventosTimeline(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $hasConsulta = Schema::hasTable('consulta_data');
        $hasProtocolo = Schema::hasTable('protocolo_data');

        $query = DB::table('procedimiento_proyectado as pp')
            ->select('pp.procedimiento_proyectado', 'pp.form_id', 'pp.hc_number')
            ->where('pp.hc_number', $hcNumber)
            ->where(static fn($query) => $query->where('pp.sigcenter_present', true)->orWhereNull('pp.sigcenter_present'))
            ->where('pp.procedimiento_proyectado', 'not like', '%optometría%');

        if ($hasConsulta) {
            $query->leftJoin('consulta_data as cd', static function ($join): void {
                $join->on('pp.hc_number', '=', 'cd.hc_number')
                    ->on('pp.form_id', '=', 'cd.form_id');
            });
        }

        if ($hasProtocolo) {
            $query->leftJoin('protocolo_data as pr', static function ($join): void {
                $join->on('pp.hc_number', '=', 'pr.hc_number')
                    ->on('pp.form_id', '=', 'pr.form_id');
            });
        }

        $fechaExpression = match (true) {
            $hasConsulta && $hasProtocolo => 'COALESCE(cd.fecha, pr.fecha_inicio)',
            $hasConsulta => 'cd.fecha',
            $hasProtocolo => 'pr.fecha_inicio',
            default => 'pp.fecha',
        };

        $query->selectRaw($fechaExpression . ' AS fecha')
            ->selectRaw($hasConsulta ? 'cd.motivo_consulta' : 'NULL AS motivo_consulta')
            ->selectRaw($hasConsulta ? 'cd.enfermedad_actual' : 'NULL AS enfermedad_actual')
            ->selectRaw($hasConsulta ? 'cd.plan' : 'NULL AS plan')
            ->selectRaw($hasConsulta ? 'cd.examen_fisico' : 'NULL AS examen_fisico')
            ->selectRaw($hasProtocolo ? 'pr.membrete' : 'NULL AS membrete')
            ->orderBy('fecha');

        $eventos = [];
        foreach ($query->get() as $rawRow) {
            $row = (array) $rawRow;
            if (empty($row['fecha']) || !strtotime((string) $row['fecha'])) {
                continue;
            }

            $motivo = trim((string) ($row['motivo_consulta'] ?? ''));
            $enfermedad = trim((string) ($row['enfermedad_actual'] ?? ''));
            $plan = trim((string) ($row['plan'] ?? ''));
            $examenFisico = trim((string) ($row['examen_fisico'] ?? ''));
            $fallback = $examenFisico !== '' ? $examenFisico : trim((string) ($row['membrete'] ?? ''));

            $contenido = $fallback;
            if ($motivo !== '' || $enfermedad !== '' || $plan !== '' || $examenFisico !== '') {
                $contenido = trim(implode("\n\n", [
                    'Motivo: ' . ($motivo !== '' ? $motivo : '—'),
                    'Enfermedad Actual: ' . ($enfermedad !== '' ? $enfermedad : '—'),
                    'Examen Físico: ' . ($examenFisico !== '' ? $examenFisico : '—'),
                    'Plan: ' . ($plan !== '' ? $plan : '—'),
                ]));
            }

            $row['motivo_consulta'] = $motivo;
            $row['enfermedad_actual'] = $enfermedad;
            $row['examen_fisico'] = $examenFisico;
            $row['plan'] = $plan;
            $row['contenido'] = $contenido;
            $eventos[] = $row;
        }

        return $eventos;
    }

    /**
     * @return array<string,float>
     */
    private function getEstadisticasProcedimientos(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $procedimientos = [];
        $rows = DB::table('procedimiento_proyectado')
            ->select('procedimiento_proyectado')
            ->where('hc_number', $hcNumber)
            ->where(static fn($query) => $query->where('sigcenter_present', true)->orWhereNull('sigcenter_present'))
            ->get();

        foreach ($rows as $row) {
            $parts = explode(' - ', (string) $row->procedimiento_proyectado);
            $categoria = strtoupper((string) ($parts[0] ?? ''));
            $nombre = in_array($categoria, ['CIRUGIAS', 'PNI', 'IMAGENES'], true)
                ? $categoria
                : ($parts[2] ?? $categoria);

            $procedimientos[$nombre] = ($procedimientos[$nombre] ?? 0) + 1;
        }

        $total = array_sum($procedimientos);
        if ($total === 0) {
            return [];
        }

        $porcentajes = [];
        foreach ($procedimientos as $nombre => $cantidad) {
            $porcentajes[$nombre] = ($cantidad / $total) * 100;
        }

        return $porcentajes;
    }

    private function calcularEdad(?string $fechaNacimiento): ?int
    {
        if (!$fechaNacimiento) {
            return null;
        }

        try {
            return (new DateTime())->diff(new DateTime($fechaNacimiento))->y;
        } catch (\Throwable) {
            return null;
        }
    }

    private function verificarCoberturaPaciente(string $hcNumber): string
    {
        if (!Schema::hasTable('prefactura_paciente')) {
            return 'N/A';
        }

        $row = DB::table('prefactura_paciente')
            ->select('cod_derivacion', 'fecha_vigencia')
            ->where('hc_number', $hcNumber)
            ->whereNotNull('cod_derivacion')
            ->where('cod_derivacion', '!=', '')
            ->orderByDesc('fecha_vigencia')
            ->first();

        if (!$row) {
            return 'N/A';
        }

        $fechaVigencia = strtotime((string) $row->fecha_vigencia);

        return $fechaVigencia >= time() ? 'Con Cobertura' : 'Sin Cobertura';
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function ordenarTimeline(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            return strtotime((string) ($b['fecha'] ?? '')) <=> strtotime((string) ($a['fecha'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array<int,string>
     */
    private function getAfiliacionesDisponibles(): array
    {
        return PatientDatum::query()
            ->whereNotNull('afiliacion')
            ->whereRaw("TRIM(afiliacion) <> ''")
            ->pluck('afiliacion')
            ->map(static fn(mixed $afiliacion): string => trim((string) $afiliacion))
            ->filter(static fn(string $afiliacion): bool => $afiliacion !== '' && preg_match('/^[A-Za-z]/', $afiliacion) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    private function obtenerProcedimientosNormalizados(int $prefacturaId): ?array
    {
        if ($prefacturaId === 0 || !Schema::hasTable('prefactura_detalle_procedimientos')) {
            return null;
        }

        return DB::table('prefactura_detalle_procedimientos')
            ->select(
                'posicion',
                'external_id',
                'proc_interno',
                'codigo',
                'descripcion',
                'lateralidad',
                'observaciones',
                'precio_base',
                'precio_tarifado'
            )
            ->where('prefactura_id', $prefacturaId)
            ->orderBy('posicion')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();
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
}
