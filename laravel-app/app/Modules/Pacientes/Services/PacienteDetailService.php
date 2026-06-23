<?php

namespace App\Modules\Pacientes\Services;

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

        return [
            'patientData' => $patientData,
            'afiliacionesDisponibles' => $this->getAfiliacionesDisponibles(),
            'diagnosticos' => $this->safe(fn(): array => $this->getDiagnosticosPorPaciente($hcNumber)),
            'medicos' => $this->safe(fn(): array => $this->getDoctoresAsignados($hcNumber)),
            'timelineItems' => $this->ordenarTimeline(array_merge(
                $this->safe(fn(): array => $this->getSolicitudesPorPaciente($hcNumber, $timelineLimit)),
                $this->safe(fn(): array => $this->getPrefacturasPorPaciente($hcNumber, $timelineLimit))
            )),
            'eventos' => $this->safe(fn(): array => $this->getEventosTimeline($hcNumber)),
            'documentos' => $this->safe(fn(): array => $this->getDocumentosDescargables($hcNumber)),
            'estadisticas' => $this->safe(fn(): array => $this->getEstadisticasProcedimientos($hcNumber)),
            'patientAge' => $this->calcularEdad($patientData['fecha_nacimiento'] ?? null),
            'coverageStatus' => $this->verificarCoberturaPaciente($hcNumber),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getDetalleSolicitud(string $hcNumber, string $formId): array
    {
        $rows = $this->selectRows(
            <<<'SQL'
            SELECT sp.*, cd.*
            FROM solicitud_procedimiento sp
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            WHERE sp.hc_number = ? AND sp.form_id = ?
            LIMIT 1
            SQL,
            [$hcNumber, $formId]
        );

        return $rows[0] ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    public function getPatientDetails(string $hcNumber): array
    {
        $row = DB::table('patient_data')->where('hc_number', $hcNumber)->first();
        if (!$row) {
            return [];
        }

        $data = (array) $row;
        $data['cedula'] = $data['hc_number'] ?? $hcNumber;
        $data['telefono_alt'] = $data['telefono_alt'] ?? '';
        $data['medico_tratante_id'] = $data['medico_tratante_id'] ?? '';
        $data['sede_principal'] = $data['sede_principal'] ?? '';
        $data['medico_tratante'] = $this->resolveMedicoTratante($hcNumber, (string) $data['medico_tratante_id']);
        $data['sede_info'] = $this->normalizeSedePrincipal((string) $data['sede_principal'])
            ?? (new SedePacienteResolver())->resolve($hcNumber);
        $data['sede'] = $data['sede_info']['id'] ?? '';
        $data['proxima_cita'] = $this->getProximaCita($hcNumber);

        return $data;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveMedicoTratante(string $hcNumber, string $manualId): ?array
    {
        $manualId = trim($manualId);
        if ($manualId !== '' && Schema::hasTable('users')) {
            $columns = ['id'];
            foreach (['nombre', 'full_name', 'especialidad', 'subespecialidad'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            $row = DB::table('users')->select($columns)->where('id', $manualId)->first();
            if ($row) {
                $nombre = trim((string) (($row->nombre ?? '') ?: ($row->full_name ?? '')));
                $especialidad = trim((string) (($row->especialidad ?? '') ?: ($row->subespecialidad ?? '')));
                if ($nombre !== '') {
                    return [
                        'id' => (int) ($row->id ?? 0),
                        'nombre' => $nombre,
                        'especialidad' => $especialidad,
                        'procedimientos_count' => 0,
                        'ultima_fecha' => null,
                        'confirmado' => true,
                        'origen' => 'manual',
                    ];
                }
            }
        }

        return (new MedicoTratanteResolver())->resolve($hcNumber);
    }

    /**
     * @return array{fecha:string,hora:string,tipo:string,medico:string}|null
     */
    private function getProximaCita(string $hcNumber): ?array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return null;
        }

        $row = DB::table('procedimiento_proyectado')
            ->where('hc_number', $hcNumber)
            ->where(function ($query): void {
                $query->whereNull('sigcenter_present')->orWhere('sigcenter_present', 1);
            })
            ->whereDate('fecha', '>=', date('Y-m-d'))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->first();

        if (!$row || empty($row->fecha)) {
            return null;
        }

        return [
            'fecha' => (string) ($row->fecha ?? ''),
            'hora' => (string) ($row->hora ?? ''),
            'tipo' => (string) ($row->procedimiento_proyectado ?? 'consulta'),
            'medico' => trim((string) ($row->doctor ?? '')),
        ];
    }

    /**
     * @return array{id:string,nombre:string,origen:string}|null
     */
    private function normalizeSedePrincipal(string $sede): ?array
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
     * @return array<string,array{idDiagnostico:string,fecha:?string}>
     */
    private function getDiagnosticosPorPaciente(string $hcNumber): array
    {
        $uniqueDiagnoses = [];

        if (Schema::hasTable('prefactura_detalle_diagnosticos') && Schema::hasTable('prefactura_paciente')) {
            foreach ($this->selectRows(
                <<<'SQL'
                SELECT
                    d.diagnostico_codigo,
                    d.descripcion,
                    pp.fecha_creacion,
                    pp.fecha_registro
                FROM prefactura_detalle_diagnosticos d
                INNER JOIN prefactura_paciente pp ON pp.id = d.prefactura_id
                WHERE pp.hc_number = ?
                ORDER BY pp.fecha_creacion DESC, d.posicion ASC
                SQL,
                [$hcNumber]
            ) as $row) {
                $codigo = (string) ($row['diagnostico_codigo'] ?: ($row['descripcion'] ?? ''));
                if ($codigo === '' || isset($uniqueDiagnoses[$codigo])) {
                    continue;
                }

                $fechaEvento = $row['fecha_creacion'] ?? $row['fecha_registro'] ?? null;
                $timestamp = $fechaEvento ? strtotime((string) $fechaEvento) : false;
                $uniqueDiagnoses[$codigo] = [
                    'idDiagnostico' => (string) ($row['diagnostico_codigo'] ?: $codigo),
                    'fecha' => $timestamp ? date('d M Y', $timestamp) : null,
                ];
            }
        }

        if (!Schema::hasTable('consulta_data')) {
            return $uniqueDiagnoses;
        }

        foreach ($this->selectRows(
            'SELECT fecha, diagnosticos FROM consulta_data WHERE hc_number = ? ORDER BY fecha DESC',
            [$hcNumber]
        ) as $row) {
            $diagnosticos = json_decode((string) ($row['diagnosticos'] ?? ''), true) ?: [];
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            $fecha = $timestamp ? date('d M Y', $timestamp) : null;

            foreach ($diagnosticos as $diagnostico) {
                $id = (string) ($diagnostico['idDiagnostico'] ?? '');
                if ($id !== '' && !isset($uniqueDiagnoses[$id])) {
                    $uniqueDiagnoses[$id] = [
                        'idDiagnostico' => $id,
                        'fecha' => $fecha,
                    ];
                }
            }
        }

        return $uniqueDiagnoses;
    }

    /**
     * @return array<string,array{doctor:string,form_id:mixed}>
     */
    private function getDoctoresAsignados(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $doctores = [];
        foreach ($this->selectRows(
            "SELECT doctor, form_id FROM procedimiento_proyectado WHERE hc_number = ? AND COALESCE(sigcenter_present, 1) = 1 AND doctor IS NOT NULL AND doctor != '' AND doctor NOT LIKE '%optometría%' ORDER BY form_id DESC",
            [$hcNumber]
        ) as $row) {
            $doctor = (string) ($row['doctor'] ?? '');
            if ($doctor !== '' && !isset($doctores[$doctor])) {
                $doctores[$doctor] = [
                    'doctor' => $doctor,
                    'form_id' => $row['form_id'] ?? null,
                ];
            }
        }

        return $doctores;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getSolicitudesPorPaciente(string $hcNumber, int $limit = 50): array
    {
        if (!Schema::hasTable('solicitud_procedimiento')) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'nombre' => $row['procedimiento'],
                'fecha' => $row['created_at'],
                'tipo' => strtolower((string) ($row['tipo'] ?? 'otro')),
                'form_id' => $row['form_id'],
                'origen' => 'Solicitud',
            ],
            $this->selectRows(
                "SELECT procedimiento, created_at, tipo, form_id FROM solicitud_procedimiento WHERE hc_number = ? AND procedimiento != '' AND procedimiento != 'SELECCIONE' ORDER BY created_at DESC LIMIT ?",
                [$hcNumber, $limit]
            )
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getDocumentosDescargables(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $protocolos = Schema::hasTable('protocolo_data')
            ? $this->selectRows(
                <<<'SQL'
                SELECT
                    pd.form_id,
                    pd.hc_number,
                    COALESCE(NULLIF(pd.membrete, ''), pp.procedimiento_proyectado, 'Procedimiento quirúrgico') AS membrete,
                    COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_inicio
                FROM protocolo_data pd
                LEFT JOIN (
                    SELECT
                        hc_number,
                        form_id,
                        MAX(procedimiento_proyectado) AS procedimiento_proyectado,
                        MAX(fecha) AS fecha
                    FROM procedimiento_proyectado
                    WHERE COALESCE(sigcenter_present, 1) = 1
                    GROUP BY hc_number, form_id
                ) pp
                  ON pp.hc_number = pd.hc_number
                 AND pp.form_id = pd.form_id
                WHERE pd.hc_number = ?
                SQL,
                [$hcNumber]
            )
            : [];

        $procedimientos = $this->selectRows(
            <<<'SQL'
            SELECT
                pp.form_id,
                pp.hc_number,
                MAX(pp.procedimiento_proyectado) AS procedimiento,
                MAX(pp.fecha) AS created_at
            FROM procedimiento_proyectado pp
            WHERE pp.hc_number = ?
              AND COALESCE(pp.sigcenter_present, 1) = 1
              AND pp.form_id IS NOT NULL
              AND TRIM(pp.form_id) <> ''
              AND pp.procedimiento_proyectado IS NOT NULL
              AND TRIM(pp.procedimiento_proyectado) <> ''
              AND UPPER(TRIM(pp.procedimiento_proyectado)) <> 'SELECCIONE'
              AND (
                  UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'PNI%'
                  OR UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'CIRUGIAS%'
              )
            GROUP BY pp.hc_number, pp.form_id
            SQL,
            [$hcNumber]
        );

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
        return $texto !== '' && (str_starts_with($texto, 'PNI') || str_starts_with($texto, 'CIRUGIAS'));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getEventosTimeline(string $hcNumber): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')) {
            return [];
        }

        $eventos = [];
        foreach ($this->selectRows(
            <<<'SQL'
            SELECT pp.procedimiento_proyectado, pp.form_id, pp.hc_number,
                   COALESCE(cd.fecha, pr.fecha_inicio) AS fecha,
                   cd.motivo_consulta,
                   cd.enfermedad_actual,
                   cd.plan,
                   cd.examen_fisico,
                   pr.membrete
            FROM procedimiento_proyectado pp
            LEFT JOIN consulta_data cd ON pp.hc_number = cd.hc_number AND pp.form_id = cd.form_id
            LEFT JOIN protocolo_data pr ON pp.hc_number = pr.hc_number AND pp.form_id = pr.form_id
            WHERE pp.hc_number = ? AND COALESCE(pp.sigcenter_present, 1) = 1 AND pp.procedimiento_proyectado NOT LIKE '%optometría%'
            ORDER BY fecha ASC
            SQL,
            [$hcNumber]
        ) as $row) {
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
        foreach ($this->selectRows(
            'SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE hc_number = ? AND COALESCE(sigcenter_present, 1) = 1',
            [$hcNumber]
        ) as $row) {
            $parts = explode(' - ', (string) ($row['procedimiento_proyectado'] ?? ''));
            $categoria = strtoupper((string) ($parts[0] ?? ''));
            $nombre = in_array($categoria, ['CIRUGIAS', 'PNI', 'IMAGENES'], true) ? $categoria : ($parts[2] ?? $categoria);

            if ($nombre === '') {
                continue;
            }

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

        $row = $this->selectRows(
            <<<'SQL'
            SELECT cod_derivacion, fecha_vigencia
            FROM prefactura_paciente
            WHERE hc_number = ?
              AND cod_derivacion IS NOT NULL AND cod_derivacion != ''
            ORDER BY fecha_vigencia DESC
            LIMIT 1
            SQL,
            [$hcNumber]
        )[0] ?? null;

        if (!$row) {
            return 'N/A';
        }

        $fechaVigencia = strtotime((string) ($row['fecha_vigencia'] ?? ''));
        return $fechaVigencia && $fechaVigencia >= time() ? 'Con Cobertura' : 'Sin Cobertura';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPrefacturasPorPaciente(string $hcNumber, int $limit = 50): array
    {
        if (!Schema::hasTable('prefactura_paciente')) {
            return [];
        }

        $prefacturas = [];
        foreach ($this->selectRows(
            <<<'SQL'
            SELECT *
            FROM prefactura_paciente
            WHERE hc_number = ?
              AND cod_derivacion IS NOT NULL
              AND cod_derivacion != ''
            ORDER BY fecha_creacion DESC
            LIMIT ?
            SQL,
            [$hcNumber, $limit]
        ) as $row) {
            $detalles = $this->obtenerProcedimientosNormalizados((int) ($row['id'] ?? 0));
            $procedimientos = [];

            if ($detalles !== null) {
                $procedimientos = $detalles;
                $row['procedimientos_detalle'] = $detalles;
            } elseif (!empty($row['procedimientos']) && is_string($row['procedimientos'])) {
                $procedimientos = json_decode($row['procedimientos'], true) ?: [];
            }

            $nombreProcedimientos = '';
            if (is_array($procedimientos) && $procedimientos !== []) {
                foreach ($procedimientos as $index => $proc) {
                    $descripcion = $proc['descripcion'] ?? $proc['procedimiento'] ?? $proc['procInterno'] ?? $proc['procDetalle'] ?? $proc['codigo'] ?? 'Procedimiento';
                    $linea = ($index + 1) . '. ' . $descripcion;

                    $lateralidad = $proc['lateralidad'] ?? $proc['ojoId'] ?? null;
                    if (!empty($lateralidad)) {
                        $linea .= ' - Ojo: ' . $lateralidad;
                    }

                    if (!empty($proc['observaciones'])) {
                        $linea .= ' (' . $proc['observaciones'] . ')';
                    }

                    $nombreProcedimientos .= $linea . "\n";
                }
            } else {
                $nombreProcedimientos = 'Procedimientos no disponibles';
            }

            $prefacturas[] = [
                'nombre' => "Prefactura\n" . $nombreProcedimientos,
                'fecha' => $row['fecha_creacion'],
                'tipo' => 'prefactura',
                'form_id' => $row['form_id'] ?? null,
                'detalle' => $row,
                'origen' => 'Prefactura',
            ];
        }

        return $prefacturas;
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    private function obtenerProcedimientosNormalizados(int $prefacturaId): ?array
    {
        if ($prefacturaId === 0 || !Schema::hasTable('prefactura_detalle_procedimientos')) {
            return null;
        }

        return $this->selectRows(
            <<<'SQL'
            SELECT
                posicion,
                external_id,
                proc_interno,
                codigo,
                descripcion,
                lateralidad,
                observaciones,
                precio_base,
                precio_tarifado
            FROM prefactura_detalle_procedimientos
            WHERE prefactura_id = ?
            ORDER BY posicion ASC
            SQL,
            [$prefacturaId]
        );
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
        if (Schema::hasTable('sigcenter_afiliaciones')) {
            $items = DB::table('sigcenter_afiliaciones')
                ->where(function ($query): void {
                    $query->whereNull('activo')->orWhere('activo', true);
                })
                ->whereNotNull('nombre')
                ->whereRaw("TRIM(nombre) <> ''")
                ->orderBy('nombre')
                ->pluck('nombre')
                ->map(fn(mixed $value): string => trim((string) $value))
                ->filter(fn(string $value): bool => $this->isCatalogAfiliacion($value))
                ->unique()
                ->values()
                ->all();

            if ($items !== []) {
                return $items;
            }
        }

        if (!Schema::hasTable('patient_data') || !Schema::hasColumn('patient_data', 'afiliacion')) {
            return [];
        }

        return DB::table('patient_data')
            ->distinct()
            ->whereNotNull('afiliacion')
            ->whereRaw("TRIM(afiliacion) <> ''")
            ->orderBy('afiliacion')
            ->pluck('afiliacion')
            ->map(fn(mixed $value): string => trim((string) $value))
            ->filter(fn(string $value): bool => $this->isCatalogAfiliacion($value))
            ->values()
            ->all();
    }

    private function isCatalogAfiliacion(string $value): bool
    {
        return $value !== '' && preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T|array<int,mixed>
     */
    private function safe(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    private function selectRows(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn(object $row): array => (array) $row,
            DB::select($sql, $bindings)
        );
    }
}
