<?php

namespace App\Modules\Pacientes\Services;

use PDO;

class PacientesFlujoService
{
    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerFlujoPacientesPorVisita(?string $fecha = null): array
    {
        $fecha = $this->normalizeDate($fecha);

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
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $visitaIds = array_values(array_filter(array_map(static fn ($row) => (string) ($row['visita_id'] ?? ''), $visitas)));
        if ($visitaIds === []) {
            return $visitas;
        }

        $placeholders = implode(',', array_fill(0, count($visitaIds), '?'));
        $sqlTrayectos = <<<SQL
            SELECT
                pp.id,
                pp.form_id,
                pp.visita_id,
                pp.procedimiento_proyectado AS procedimiento,
                pp.estado_agenda AS estado,
                pp.fecha AS fecha_cambio,
                pp.hora AS hora,
                pp.doctor AS doctor,
                pp.afiliacion AS afiliacion
            FROM procedimiento_proyectado pp
            WHERE pp.visita_id IN ($placeholders)
            ORDER BY pp.hora ASC
            SQL;
        $stmtTrayectos = $this->db->prepare($sqlTrayectos);
        $stmtTrayectos->execute($visitaIds);
        $trayectos = $stmtTrayectos->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $formIds = array_values(array_unique(array_filter(array_map(static fn ($row) => (string) ($row['form_id'] ?? ''), $trayectos))));
        $historiales = $this->obtenerHistorialesPorFormId($formIds);

        /** @var array<string, array<int, array<string, mixed>>> $trayectosPorVisita */
        $trayectosPorVisita = [];
        foreach ($trayectos as $trayecto) {
            $formId = (string) ($trayecto['form_id'] ?? '');
            $trayecto['historial_estados'] = $historiales[$formId] ?? [];
            $key = (string) ($trayecto['visita_id'] ?? '');
            $trayectosPorVisita[$key][] = $trayecto;
        }

        foreach ($visitas as &$visita) {
            $trayectosVisita = $trayectosPorVisita[(string) ($visita['visita_id'] ?? '')] ?? [];
            $horaLlegada = isset($visita['hora_llegada']) ? (string) $visita['hora_llegada'] : null;

            foreach ($trayectosVisita as &$trayecto) {
                $historial = is_array($trayecto['historial_estados'] ?? null) ? $trayecto['historial_estados'] : [];
                $fechaCambio = trim((string) ($trayecto['fecha_cambio'] ?? ''));
                $hora = trim((string) ($trayecto['hora'] ?? ''));
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
                pp.doctor AS doctor,
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
            WHERE 1
            SQL;
        $params = [];

        if ($fecha !== null) {
            $sql .= ' AND v.fecha_visita = ?';
            $params[] = $fecha;
        }

        $sql .= ' ORDER BY pp.fecha DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $formIds = array_values(array_unique(array_filter(array_map(static fn ($row) => (string) ($row['form_id'] ?? ''), $solicitudes))));
        $historiales = $this->obtenerHistorialesPorFormId($formIds);

        foreach ($solicitudes as &$solicitud) {
            $formId = (string) ($solicitud['form_id'] ?? '');
            $historial = $historiales[$formId] ?? [];
            $solicitud['historial_estados'] = $historial;

            $fechaCambio = trim((string) ($solicitud['fecha_cambio'] ?? ''));
            $hora = trim((string) ($solicitud['hora'] ?? ''));
            $citaProgramada = ($fechaCambio !== '' && $hora !== '') ? ($fechaCambio . ' ' . $hora) : null;

            $detalle = $this->construirLineaTiempo(
                $historial,
                $citaProgramada,
                isset($solicitud['hora_llegada']) ? (string) $solicitud['hora_llegada'] : null
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
        $desde = trim((string) ($desde ?? ''));

        if ($desde !== '' && $this->tableHasColumn('procedimiento_proyectado', 'updated_at')) {
            $stmt = $this->db->prepare('SELECT * FROM procedimiento_proyectado WHERE updated_at > ?');
            $stmt->execute([$desde]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $this->db->query('SELECT * FROM procedimiento_proyectado');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        return [
            'pacientes' => $rows,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
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
        $stmt = $this->db->prepare(
            "SELECT form_id, estado, fecha_hora_cambio
             FROM procedimiento_proyectado_estado
             WHERE form_id IN ($placeholders)
             ORDER BY form_id ASC, fecha_hora_cambio ASC"
        );
        $stmt->execute($formIds);

        $historiales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $formId = (string) ($row['form_id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $historiales[$formId][] = [
                'estado' => (string) ($row['estado'] ?? ''),
                'fecha_hora_cambio' => (string) ($row['fecha_hora_cambio'] ?? ''),
            ];
        }

        return $historiales;
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

            $estado = (string) ($evento['estado'] ?? '');
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
            'metricas' => array_filter($metricas, static fn ($valor) => $valor !== null),
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
        $value = trim((string) ($value ?? ''));
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
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
            $stmt->execute([':column' => $column]);
            $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $exists = false;
        }

        $this->columnCache[$cacheKey] = $exists;

        return $exists;
    }
}
