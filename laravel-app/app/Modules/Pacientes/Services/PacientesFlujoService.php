<?php

namespace App\Modules\Pacientes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PacientesFlujoService
{
    /** @var array<string, bool> */
    private array $columnCache = [];

    /** @var array<string, bool> */
    private array $tableCache = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerFlujoPacientesPorVisita(?string $fecha = null): array
    {
        $fecha = $this->normalizeDate($fecha);

        // 1) Visitas reales
        $sql = <<<'SQL'
            SELECT
                v.id AS visita_id,
                v.hc_number,
                v.fecha_visita,
                v.hora_llegada,
                v.usuario_registro,
                v.observaciones,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2
            FROM visitas v
            INNER JOIN patient_data pd ON v.hc_number = pd.hc_number
            WHERE 1
            SQL;
        $params = [];

        if ($fecha !== null) {
            $sql .= ' AND v.fecha_visita = ?';
            $params[] = $fecha;
        }

        $sql .= ' ORDER BY v.hora_llegada ASC';
        $visitas = $this->selectRows($sql, $params);

        $visitaIds = array_values(array_filter(array_map(
            static fn($row) => (string)($row['visita_id'] ?? ''),
            $visitas
        )));

        // 2) Traer trayectos con visita_id y también huérfanos del día
        $whereParts = [];
        $paramsTrayectos = [];

        if ($visitaIds !== []) {
            $placeholders = implode(',', array_fill(0, count($visitaIds), '?'));
            $whereParts[] = "pp.visita_id IN ($placeholders)";
            $paramsTrayectos = array_merge($paramsTrayectos, $visitaIds);
        }

        if ($fecha !== null) {
            $whereParts[] = "(pp.visita_id IS NULL AND pp.fecha = ?)";
            $paramsTrayectos[] = $fecha;
        }

        if ($whereParts === []) {
            return $visitas;
        }

        $whereSql = implode(' OR ', $whereParts);

        $sqlTrayectos = <<<SQL
            SELECT
                pp.id,
                pp.form_id,
                pp.visita_id,
                pp.hc_number,
                pp.procedimiento_proyectado AS procedimiento,
                pp.estado_agenda AS estado,
                pp.fecha AS fecha_cambio,
                pp.hora AS hora,
                pp.doctor AS doctor_original,
                COALESCE(u.full_name, u.nombre, pp.doctor) AS doctor,
                COALESCE(u.nombre_norm, u.nombre_norm_rev, pp.doctor) AS doctor_key,
                u.id AS doctor_user_id,
                pp.afiliacion AS afiliacion,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pp.hc_number = pd.hc_number
            LEFT JOIN users u
              ON UPPER(pp.doctor) = UPPER(u.nombre_norm)
              OR UPPER(pp.doctor) = UPPER(u.nombre_norm_rev)
            WHERE ($whereSql)
              AND COALESCE(pp.sigcenter_present, 1) = 1
            ORDER BY pp.hora ASC
            SQL;
        $trayectos = $this->selectRows($sqlTrayectos, $paramsTrayectos);

        $formIds = array_values(array_unique(array_filter(array_map(static fn($row) => (string)($row['form_id'] ?? ''), $trayectos))));
        $historiales = $this->obtenerHistorialesPorFormId($formIds);

        $trayectosPorVisita = [];
        $visitaIndex = [];

        foreach ($visitas as $i => $visita) {
            $visitaIndex[(string)($visita['visita_id'] ?? '')] = $i;
        }

        foreach ($trayectos as $trayecto) {
            $formId = (string)($trayecto['form_id'] ?? '');
            $trayecto['historial_estados'] = $historiales[$formId] ?? [];

            $visitaId = trim((string)($trayecto['visita_id'] ?? ''));
            $hcNumber = trim((string)($trayecto['hc_number'] ?? ''));
            $fechaCambio = trim((string)($trayecto['fecha_cambio'] ?? ''));

            if ($visitaId !== '') {
                $key = $visitaId;
            } else {
                // 3) Visita virtual: agrupa form_id por paciente + fecha
                $key = 'virtual_' . $hcNumber . '_' . $fechaCambio;

                if (!isset($visitaIndex[$key])) {
                    $visitas[] = [
                        'visita_id' => $key,
                        'hc_number' => $hcNumber,
                        'fecha_visita' => $fechaCambio,
                        'hora_llegada' => $trayecto['hora'] ?? null,
                        'usuario_registro' => null,
                        'observaciones' => 'Visita virtual generada desde procedimiento_proyectado sin visita_id.',
                        'fname' => $trayecto['fname'] ?? null,
                        'mname' => $trayecto['mname'] ?? null,
                        'lname' => $trayecto['lname'] ?? null,
                        'lname2' => $trayecto['lname2'] ?? null,
                    ];

                    $visitaIndex[$key] = array_key_last($visitas);
                }
            }

            unset($trayecto['fname'], $trayecto['mname'], $trayecto['lname'], $trayecto['lname2']);

            $trayectosPorVisita[$key][] = $trayecto;
        }

        foreach ($visitas as &$visita) {
            $key = (string)($visita['visita_id'] ?? '');
            $trayectosVisita = $trayectosPorVisita[$key] ?? [];
            $horaLlegada = isset($visita['hora_llegada']) ? (string)$visita['hora_llegada'] : null;

            foreach ($trayectosVisita as &$trayecto) {
                $historial = is_array($trayecto['historial_estados'] ?? null) ? $trayecto['historial_estados'] : [];
                $fechaCambio = trim((string)($trayecto['fecha_cambio'] ?? ''));
                $hora = trim((string)($trayecto['hora'] ?? ''));
                $citaProgramada = ($fechaCambio !== '' && $hora !== '') ? ($fechaCambio . ' ' . $hora) : null;

                $detalle = $this->construirLineaTiempo($historial, $citaProgramada, $horaLlegada);
                $trayecto['linea_tiempo'] = $detalle['linea_tiempo'];
                $trayecto['metricas'] = $detalle['metricas'];
                $trayecto['primeras_marcas'] = $detalle['primeras_marcas'];
            }
            unset($trayecto);

            $visita['trayectos'] = $trayectosVisita;
        }
        unset($visita);

        usort($visitas, static function (array $a, array $b): int {
            return strcmp((string)($a['hora_llegada'] ?? ''), (string)($b['hora_llegada'] ?? ''));
        });

        return $visitas;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerFlujoPacientes(?string $fecha = null): array
    {
        $fecha = $this->normalizeDate($fecha);

        $sql = <<<'SQL'
            SELECT
                pp.id,
                pp.form_id,
                pp.hc_number,
                pp.procedimiento_proyectado AS procedimiento,
                pp.estado_agenda AS estado,
                pp.fecha AS fecha_cambio,
                pp.hora AS hora,
                pp.doctor AS doctor_original,
                COALESCE(u.full_name, u.nombre, pp.doctor) AS doctor,
                COALESCE(u.nombre_norm, u.nombre_norm_rev, pp.doctor) AS doctor_key,
                u.id AS doctor_user_id,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2,
                pp.afiliacion,
                v.id AS visita_id,
                v.fecha_visita,
                v.hora_llegada
            FROM procedimiento_proyectado pp
            INNER JOIN patient_data pd ON pp.hc_number = pd.hc_number
            LEFT JOIN visitas v ON pp.visita_id = v.id
            LEFT JOIN users u
              ON UPPER(pp.doctor) = UPPER(u.nombre_norm)
              OR UPPER(pp.doctor) = UPPER(u.nombre_norm_rev)
            WHERE COALESCE(pp.sigcenter_present, 1) = 1
            SQL;
        $params = [];

        if ($fecha !== null) {
            $sql .= ' AND v.fecha_visita = ?';
            $params[] = $fecha;
        }

        $sql .= ' ORDER BY pp.fecha DESC';
        $solicitudes = $this->selectRows($sql, $params);

        $formIds = array_values(array_unique(array_filter(array_map(static fn($row) => (string)($row['form_id'] ?? ''), $solicitudes))));
        $historiales = $this->obtenerHistorialesPorFormId($formIds);

        foreach ($solicitudes as &$solicitud) {
            $formId = (string)($solicitud['form_id'] ?? '');
            $historial = $historiales[$formId] ?? [];
            $solicitud['historial_estados'] = $historial;

            $fechaCambio = trim((string)($solicitud['fecha_cambio'] ?? ''));
            $hora = trim((string)($solicitud['hora'] ?? ''));
            $citaProgramada = ($fechaCambio !== '' && $hora !== '') ? ($fechaCambio . ' ' . $hora) : null;

            $detalle = $this->construirLineaTiempo(
                $historial,
                $citaProgramada,
                isset($solicitud['hora_llegada']) ? (string)$solicitud['hora_llegada'] : null
            );

            $solicitud['linea_tiempo'] = $detalle['linea_tiempo'];
            $solicitud['metricas'] = $detalle['metricas'];
            $solicitud['primeras_marcas'] = $detalle['primeras_marcas'];
        }
        unset($solicitud);

        return $solicitudes;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerCambiosRecientes(?string $desde = null): array
    {
        $desde = trim((string)($desde ?? ''));

        if ($desde !== '' && $this->tableHasColumn('procedimiento_proyectado', 'updated_at')) {
            $rows = $this->selectRows(
                'SELECT * FROM procedimiento_proyectado WHERE COALESCE(sigcenter_present, 1) = 1 AND updated_at > ?',
                [$desde]
            );
        } else {
            $rows = $this->selectRows('SELECT * FROM procedimiento_proyectado WHERE COALESCE(sigcenter_present, 1) = 1');
        }

        return [
            'pacientes' => $rows,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actualizarEstadoTrayecto(string $formId, string $estado): array
    {
        $formId = trim($formId);
        $estado = trim($estado);

        if ($formId === '' || $estado === '') {
            return [
                'success' => false,
                'message' => 'form_id y estado son requeridos.',
            ];
        }

        $current = DB::selectOne(
            'SELECT form_id, estado_agenda
             FROM procedimiento_proyectado
             WHERE form_id = ? AND COALESCE(sigcenter_present, 1) = 1
             LIMIT 1',
            [$formId]
        );

        if (!$current) {
            return [
                'success' => false,
                'message' => 'Trayecto no encontrado.',
            ];
        }

        try {
            DB::transaction(function () use ($formId, $estado): void {
                DB::update(
                    'UPDATE procedimiento_proyectado
                     SET estado_agenda = ?
                     WHERE form_id = ?',
                    [$estado, $formId]
                );

                $this->registrarHistorialProcedimientoSiCambio($formId, $estado);
            });

            return [
                'success' => true,
                'message' => 'Estado actualizado.',
                'data' => [
                    'form_id' => $formId,
                    'previous_state' => data_get($current, 'estado_agenda'),
                    'current_state' => $estado,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'No se pudo actualizar el trayecto: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<int, string> $formIds
     * @return array<string, array<int, array<string, string>>>
     */
    private function obtenerHistorialesPorFormId(array $formIds): array
    {
        $formIds = array_values(array_unique(array_filter(array_map('strval', $formIds))));
        if ($formIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $rows = $this->selectRows(
            "SELECT form_id, estado, fecha_hora_cambio
             FROM procedimiento_proyectado_estado
             WHERE form_id IN ($placeholders)
             ORDER BY form_id ASC, fecha_hora_cambio ASC",
            $formIds
        );

        $historiales = [];
        foreach ($rows as $row) {
            $formId = (string)($row['form_id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $historiales[$formId][] = [
                'estado' => (string)($row['estado'] ?? ''),
                'fecha_hora_cambio' => (string)($row['fecha_hora_cambio'] ?? ''),
            ];
        }

        return $historiales;
    }

    private function slugEstado(string $estado): string
    {
        $estado = trim($estado);
        if ($estado === '') {
            return '';
        }

        $lower = mb_strtolower($estado, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        $ascii = $ascii !== false ? $ascii : $lower;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
        $slug = trim($slug, '-');

        return match ($slug) {
            'en-quirofano', 'quirofano', 'en-qui-afano' => 'en-quirofano',
            'recuperacion', 'recuperacion-postoperatoria' => 'recuperacion',
            default => $slug,
        };
    }

    private function registrarHistorialProcedimientoSiCambio(string $formId, string $estado): void
    {
        if (!$this->tableExists('procedimiento_proyectado_estado')) {
            return;
        }

        $ultimo = DB::selectOne(
            'SELECT estado FROM procedimiento_proyectado_estado
             WHERE form_id = ?
             ORDER BY fecha_hora_cambio DESC
             LIMIT 1',
            [$formId]
        );
        $ultimoEstado = data_get($ultimo, 'estado');

        if ($ultimoEstado !== null && $this->slugEstado((string)$ultimoEstado) === $this->slugEstado($estado)) {
            return;
        }

        DB::insert(
            'INSERT INTO procedimiento_proyectado_estado (form_id, estado, fecha_hora_cambio)
             VALUES (?, ?, NOW())',
            [$formId, $estado]
        );
    }

    /**
     * @param array<int, array<string, string>> $historial
     * @return array<string, mixed>
     */
    private function construirLineaTiempo(array $historial, ?string $citaProgramada, ?string $horaLlegada): array
    {
        $lineaTiempo = [];
        $primerasMarcas = [];
        $referenciaAnterior = $horaLlegada ?? $citaProgramada;

        foreach ($historial as $evento) {
            $marca = $evento['fecha_hora_cambio'] ?? null;
            if (!$marca) {
                continue;
            }

            $estado = (string)($evento['estado'] ?? '');
            $lineaTiempo[] = [
                'estado' => $estado,
                'fecha_hora_cambio' => $marca,
                'minutos_desde_cita' => $this->minutosEntreFechas($citaProgramada, $marca),
                'minutos_desde_llegada' => $this->minutosEntreFechas($horaLlegada, $marca),
                'minutos_desde_anterior' => $this->minutosEntreFechas($referenciaAnterior, $marca),
            ];

            if (!isset($primerasMarcas[$estado])) {
                $primerasMarcas[$estado] = $marca;
            }

            $referenciaAnterior = $marca;
        }

        $metricas = [
            'espera_desde_cita' => $this->minutosEntreFechas(
                $citaProgramada,
                $primerasMarcas['LLEGADO'] ?? $primerasMarcas['OPTOMETRIA'] ?? null
            ),
            'espera_hasta_optometria' => $this->minutosEntreFechas(
                $horaLlegada ?? $primerasMarcas['LLEGADO'] ?? null,
                $primerasMarcas['OPTOMETRIA'] ?? null
            ),
            'duracion_optometria' => $this->minutosEntreFechas(
                $primerasMarcas['OPTOMETRIA'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? $primerasMarcas['DILATAR'] ?? null
            ),
            'tiempo_total' => $this->minutosEntreFechas(
                $horaLlegada ?? $primerasMarcas['LLEGADO'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? $primerasMarcas['DILATAR'] ?? null
            ),
            'duracion_dilatacion' => $this->minutosEntreFechas(
                $primerasMarcas['DILATAR'] ?? null,
                $primerasMarcas['OPTOMETRIA_TERMINADO'] ?? null
            ),
        ];

        return [
            'linea_tiempo' => $lineaTiempo,
            'metricas' => array_filter($metricas, static fn($valor) => $valor !== null),
            'primeras_marcas' => $primerasMarcas,
        ];
    }

    private function minutosEntreFechas(?string $inicio, ?string $fin): ?float
    {
        if (!$inicio || !$fin) {
            return null;
        }

        $inicioTs = strtotime($inicio);
        $finTs = strtotime($fin);

        if (!$inicioTs || !$finTs || $finTs < $inicioTs) {
            return null;
        }

        return round(($finTs - $inicioTs) / 60, 2);
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        try {
            $exists = Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            $exists = false;
        }

        $this->columnCache[$cacheKey] = $exists;

        return $exists;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $exists = Schema::hasTable($table);
        } catch (\Throwable) {
            $exists = false;
        }

        $this->tableCache[$table] = $exists;

        return $exists;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    private function selectRows(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn(object|array $row): array => (array) $row,
            DB::select($sql, $bindings)
        );
    }
}
