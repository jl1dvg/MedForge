<?php

namespace App\Modules\Dashboard\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardParityService
{
    /**
     * @return array<string, mixed>
     */
    public function buildUiPayload(?string $startDate, ?string $endDate): array
    {
        [$start, $end, $label] = $this->resolveDateRange($startDate, $endDate);
        $summary = $this->buildSummary($startDate, $endDate);

        $summaryData = (array) ($summary['data'] ?? []);

        return [
            'summary' => $summary,
            'date_range' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $label,
            ],
            'cirugias_recientes' => $this->getCirugiasRecientes($start, $end),
            'plantillas' => $this->getPlantillasRecientes(20),
            'diagnosticos_frecuentes' => $this->getDiagnosticosFrecuentes(),
            'solicitudes_quirurgicas' => $this->getUltimasSolicitudes($start, $end, 5),
            'doctores_top' => $this->getTopDoctores($start, $end),
            'estadisticas_afiliacion' => $this->getEstadisticasPorAfiliacion($start, $end),
            'kpi_cards' => $this->buildKpiCardsFromSummary($summaryData),
            'ai_summary' => [
                'provider' => '',
                'provider_configured' => false,
                'features' => [
                    'consultas_enfermedad' => false,
                    'consultas_plan' => false,
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   data: array<string, mixed>,
     *   meta: array<string, mixed>
     * }
     */
    public function buildSummary(?string $startDate, ?string $endDate): array
    {
        [$start, $end, $label] = $this->resolveDateRange($startDate, $endDate);

        return [
            'data' => [
                'patients_total' => $this->countTable('patient_data'),
                'users_total' => $this->countTable('users'),
                'protocols_total' => $this->countTable('protocolo_data'),
                'total_cirugias_periodo' => $this->getTotalCirugias($start, $end),
                'procedimientos_dia' => $this->getProcedimientosPorDia($start, $end),
                'top_procedimientos' => $this->getTopProcedimientos($start, $end),
                'revision_estados' => $this->getEstadosRevisionProtocolos($start, $end),
                'solicitudes_funnel' => $this->getSolicitudesFunnel($start, $end),
                'crm_backlog' => $this->getCrmBacklogStats($start, $end),
            ],
            'meta' => [
                'strategy' => 'strangler-v2',
                'source' => 'sql-legacy-phase-1',
                'date_range' => [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'label' => $label,
                ],
            ],
        ];
    }

    private function countTable(string $table): int
    {
        return (int) (DB::selectOne("SELECT COUNT(*) AS total FROM {$table}")->total ?? 0);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveDateRange(?string $startParam, ?string $endParam): array
    {
        $today = CarbonImmutable::today();
        $defaultEnd = $today;
        $defaultStart = $today->subDays(29);

        $end = $this->parseDate($endParam) ?? $defaultEnd;
        $start = $this->parseDate($startParam) ?? $defaultStart;

        if ($start->greaterThan($end)) {
            $start = $end->subDays(29);
        }

        return [
            $start,
            $end,
            $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        ];
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $clean);
                if ($parsed !== false) {
                    return $parsed->startOfDay();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    private function getTotalCirugias(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $stmt = DB::selectOne(
            'SELECT COUNT(*) AS total
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN ? AND ?',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        return (int) ($stmt->total ?? 0);
    }

    /**
     * @return array{fechas: array<int, string>, totales: array<int, int>}
     */
    private function getProcedimientosPorDia(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::select(
            'SELECT DATE(fecha_inicio) AS fecha, COUNT(*) AS total_procedimientos
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN ? AND ?
             GROUP BY DATE(fecha_inicio)
             ORDER BY fecha ASC',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        if ($rows === []) {
            return [
                'fechas' => ['No data'],
                'totales' => [0],
            ];
        }

        $fechas = [];
        $totales = [];
        foreach ($rows as $row) {
            $fecha = (string) ($row->fecha ?? '');
            $fechas[] = $fecha !== '' ? date('Y-m-d', strtotime($fecha)) : 'No data';
            $totales[] = (int) ($row->total_procedimientos ?? 0);
        }

        return [
            'fechas' => $fechas,
            'totales' => $totales,
        ];
    }

    /**
     * @return array{membretes: array<int, string>, totales: array<int, int>}
     */
    private function getTopProcedimientos(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::select(
            'SELECT procedimiento_id, COUNT(*) AS total_procedimientos
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN ? AND ?
               AND procedimiento_id IS NOT NULL
               AND procedimiento_id != ""
             GROUP BY procedimiento_id
             ORDER BY total_procedimientos DESC
             LIMIT 5',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        if ($rows === []) {
            return [
                'membretes' => ['No data'],
                'totales' => [0],
            ];
        }

        $membretes = [];
        $totales = [];
        foreach ($rows as $row) {
            $membretes[] = (string) ($row->procedimiento_id ?? '');
            $totales[] = (int) ($row->total_procedimientos ?? 0);
        }

        return [
            'membretes' => $membretes,
            'totales' => $totales,
        ];
    }

    /**
     * @return array{incompletos: int, revisados: int, no_revisados: int}
     */
    private function getEstadosRevisionProtocolos(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::select(
            'SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio,
                    pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos,
                    pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                    pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante,
                    pr.anestesiologo, pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante
             FROM protocolo_data pr
             LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             WHERE pr.fecha_inicio BETWEEN ? AND ?
             ORDER BY pr.fecha_inicio DESC, pr.id DESC',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $incompletos = 0;
        $revisados = 0;
        $noRevisados = 0;
        $invalidValues = ['CENTER', 'undefined'];

        foreach ($rows as $row) {
            $status = (int) ($row->status ?? 0);
            if ($status === 1) {
                $revisados++;
                continue;
            }

            $required = [
                (string) ($row->membrete ?? ''),
                (string) ($row->dieresis ?? ''),
                (string) ($row->exposicion ?? ''),
                (string) ($row->hallazgo ?? ''),
                (string) ($row->operatorio ?? ''),
                (string) ($row->complicaciones_operatorio ?? ''),
                (string) ($row->datos_cirugia ?? ''),
                (string) ($row->procedimientos ?? ''),
                (string) ($row->lateralidad ?? ''),
                (string) ($row->tipo_anestesia ?? ''),
                (string) ($row->diagnosticos ?? ''),
                (string) ($row->procedimiento_proyectado ?? ''),
            ];

            $staff = [
                (string) ($row->cirujano_1 ?? ''),
                (string) ($row->instrumentista ?? ''),
                (string) ($row->cirujano_2 ?? ''),
                (string) ($row->circulante ?? ''),
                (string) ($row->primer_ayudante ?? ''),
                (string) ($row->anestesiologo ?? ''),
                (string) ($row->segundo_ayudante ?? ''),
                (string) ($row->ayudante_anestesia ?? ''),
                (string) ($row->tercer_ayudante ?? ''),
            ];

            $invalid = false;
            foreach ($required as $field) {
                foreach ($invalidValues as $value) {
                    if ($field !== '' && stripos($field, $value) !== false) {
                        $invalid = true;
                        break 2;
                    }
                }
            }

            $staffCount = 0;
            if ($staff[0] !== '') {
                foreach ($staff as $field) {
                    foreach ($invalidValues as $value) {
                        if ($field !== '' && stripos($field, $value) !== false) {
                            $invalid = true;
                            break 2;
                        }
                    }

                    if ($field !== '') {
                        $staffCount++;
                    }
                }
            } else {
                $invalid = true;
            }

            if (!$invalid && $staffCount >= 5) {
                $noRevisados++;
                continue;
            }

            $incompletos++;
        }

        return [
            'incompletos' => $incompletos,
            'revisados' => $revisados,
            'no_revisados' => $noRevisados,
        ];
    }

    /**
     * @return array{
     *   etapas: array<string, int>,
     *   totales: array<string, int|float>,
     *   prioridades: array<string, int>
     * }
     */
    private function getSolicitudesFunnel(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $etapas = [
            'recibido' => 0,
            'llamado' => 0,
            'en-atencion' => 0,
            'revision-codigos' => 0,
            'docs-completos' => 0,
            'aprobacion-anestesia' => 0,
            'listo-para-agenda' => 0,
            'otros' => 0,
        ];

        $totales = [
            'registradas' => 0,
            'agendadas' => 0,
            'urgentes_sin_turno' => 0,
        ];

        $prioridades = [
            'urgente' => 0,
            'alta' => 0,
            'normal' => 0,
            'otros' => 0,
        ];

        try {
            $rows = DB::select(
                'SELECT sp.estado, sp.prioridad, sp.turno, sp.id
                 FROM solicitud_procedimiento sp
                 WHERE sp.procedimiento IS NOT NULL
                   AND sp.procedimiento != ""
                   AND sp.procedimiento != "SELECCIONE"
                   AND COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );
        } catch (Throwable) {
            return [
                'etapas' => $etapas,
                'totales' => array_merge($totales, [
                    'con_cirugia' => 0,
                    'conversion_agendada' => 0.0,
                ]),
                'prioridades' => $prioridades,
            ];
        }

        foreach ($rows as $row) {
            $totales['registradas']++;

            $estadoSlug = $this->slugify((string) ($row->estado ?? ''));
            if ($estadoSlug === '' || !array_key_exists($estadoSlug, $etapas)) {
                $estadoSlug = 'otros';
            }
            $etapas[$estadoSlug]++;

            $prioridadSlug = $this->slugify((string) ($row->prioridad ?? ''));
            if (!array_key_exists($prioridadSlug, $prioridades)) {
                $prioridadSlug = 'otros';
            }
            $prioridades[$prioridadSlug]++;

            $turno = trim((string) ($row->turno ?? ''));
            if ($turno !== '') {
                $totales['agendadas']++;
            }

            if ($prioridadSlug === 'urgente' && $turno === '') {
                $totales['urgentes_sin_turno']++;
            }
        }

        $conversion = 0.0;
        if ($totales['registradas'] > 0) {
            $conversion = round(($totales['agendadas'] / $totales['registradas']) * 100, 1);
        }

        return [
            'etapas' => $etapas,
            'totales' => array_merge($totales, [
                'con_cirugia' => $this->countSolicitudesConCirugia($start, $end),
                'conversion_agendada' => $conversion,
            ]),
            'prioridades' => $prioridades,
        ];
    }

    private function countSolicitudesConCirugia(CarbonImmutable $start, CarbonImmutable $end): int
    {
        try {
            $row = DB::selectOne(
                'SELECT COUNT(DISTINCT sp.id) AS total
                 FROM solicitud_procedimiento sp
                 INNER JOIN protocolo_data pr ON pr.form_id = sp.form_id AND pr.hc_number = sp.hc_number
                 WHERE sp.procedimiento IS NOT NULL
                   AND sp.procedimiento != ""
                   AND sp.procedimiento != "SELECCIONE"
                   AND COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
                   AND pr.fecha_inicio BETWEEN ? AND ?',
                [
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59'),
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59'),
                ]
            );
        } catch (Throwable) {
            return 0;
        }

        return (int) ($row->total ?? 0);
    }

    /**
     * @return array{pendientes: int, completadas: int, vencidas: int, vencen_hoy: int, avance: float}
     */
    private function getCrmBacklogStats(CarbonImmutable $start, CarbonImmutable $end): array
    {
        try {
            $row = DB::selectOne(
                'SELECT
                    SUM(CASE WHEN t.estado IN ("pendiente", "en_progreso") THEN 1 ELSE 0 END) AS pendientes,
                    SUM(CASE WHEN t.estado = "completado" THEN 1 ELSE 0 END) AS completadas,
                    SUM(CASE WHEN t.estado IN ("pendiente", "en_progreso") AND t.due_date < CURDATE() THEN 1 ELSE 0 END) AS vencidas,
                    SUM(CASE WHEN t.estado IN ("pendiente", "en_progreso") AND DATE(t.due_date) = CURDATE() THEN 1 ELSE 0 END) AS vencen_hoy
                 FROM solicitud_crm_tareas t
                 INNER JOIN solicitud_procedimiento sp ON sp.id = t.solicitud_id
                 WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );
        } catch (Throwable) {
            return [
                'pendientes' => 0,
                'completadas' => 0,
                'vencidas' => 0,
                'vencen_hoy' => 0,
                'avance' => 0.0,
            ];
        }

        $pendientes = (int) ($row->pendientes ?? 0);
        $completadas = (int) ($row->completadas ?? 0);
        $vencidas = (int) ($row->vencidas ?? 0);
        $vencenHoy = (int) ($row->vencen_hoy ?? 0);
        $total = $pendientes + $completadas;
        $avance = $total > 0 ? round(($completadas / $total) * 100, 1) : 0.0;

        return [
            'pendientes' => $pendientes,
            'completadas' => $completadas,
            'vencidas' => $vencidas,
            'vencen_hoy' => $vencenHoy,
            'avance' => $avance,
        ];
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCirugiasRecientes(CarbonImmutable $start, CarbonImmutable $end, int $limit = 8): array
    {
        try {
            return array_map(
                static fn(object $row): array => (array) $row,
                DB::select(
                    'SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion,
                            pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
                     FROM patient_data p
                     INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                     WHERE p.afiliacion != "ALQUILER"
                       AND pr.fecha_inicio BETWEEN ? AND ?
                     ORDER BY pr.fecha_inicio DESC, pr.id DESC
                     LIMIT ' . (int) $limit,
                    [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
                )
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPlantillasRecientes(int $limit = 20): array
    {
        try {
            return array_map(
                static fn(object $row): array => (array) $row,
                DB::select(
                    'SELECT id, membrete, cirugia,
                            COALESCE(fecha_actualizacion, fecha_creacion) AS fecha,
                            CASE
                                WHEN fecha_actualizacion IS NOT NULL THEN "Modificado"
                                ELSE "Creado"
                            END AS tipo
                     FROM procedimientos
                     ORDER BY fecha DESC
                     LIMIT ' . (int) $limit
                )
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, int>
     */
    private function getDiagnosticosFrecuentes(): array
    {
        try {
            $rows = DB::select(
                'SELECT hc_number, diagnosticos
                 FROM consulta_data
                 WHERE diagnosticos IS NOT NULL
                   AND diagnosticos != ""'
            );
        } catch (Throwable) {
            return [];
        }

        $conteoDiagnosticos = [];
        foreach ($rows as $row) {
            $hc = (string) ($row->hc_number ?? '');
            $diagnosticos = json_decode((string) ($row->diagnosticos ?? ''), true);
            if (!is_array($diagnosticos)) {
                continue;
            }

            foreach ($diagnosticos as $dx) {
                if (!is_array($dx)) {
                    continue;
                }

                $id = isset($dx['idDiagnostico']) ? strtoupper(str_replace('.', '', (string) $dx['idDiagnostico'])) : 'SINID';
                $desc = array_key_exists('descripcion', $dx) ? (string) $dx['descripcion'] : 'Sin descripcion';
                if (stripos($id, 'Z') === 0) {
                    continue;
                }

                $key = ($id === 'H25' || $id === 'H251')
                    ? 'H25 | Catarata senil'
                    : ($id . ' | ' . $desc);

                if (!isset($conteoDiagnosticos[$key])) {
                    $conteoDiagnosticos[$key] = [];
                }
                $conteoDiagnosticos[$key][$hc] = true;
            }
        }

        $prevalencias = [];
        foreach ($conteoDiagnosticos as $key => $pacientes) {
            $prevalencias[(string) $key] = count((array) $pacientes);
        }

        arsort($prevalencias);
        return array_slice($prevalencias, 0, 9, true);
    }

    /**
     * @return array{solicitudes: array<int, array<string, mixed>>, total: int}
     */
    private function getUltimasSolicitudes(CarbonImmutable $start, CarbonImmutable $end, int $limit = 5): array
    {
        try {
            $rows = DB::select(
                'SELECT sp.id, sp.fecha, sp.procedimiento, p.fname, p.lname, p.hc_number, sp.estado, sp.prioridad,
                        sp.turno, detalles.pipeline_stage AS crm_pipeline_stage, detalles.responsable_id,
                        responsable.nombre AS responsable_nombre
                 FROM solicitud_procedimiento sp
                 JOIN patient_data p ON sp.hc_number COLLATE utf8mb4_unicode_ci = p.hc_number COLLATE utf8mb4_unicode_ci
                 LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
                 LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
                 WHERE sp.procedimiento IS NOT NULL
                   AND sp.procedimiento != ""
                   AND sp.procedimiento != "SELECCIONE"
                   AND COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
                 ORDER BY COALESCE(sp.created_at, sp.fecha) DESC
                 LIMIT ' . (int) $limit,
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );

            $countRow = DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM solicitud_procedimiento
                 WHERE procedimiento IS NOT NULL
                   AND procedimiento != ""
                   AND procedimiento != "SELECCIONE"
                   AND COALESCE(created_at, fecha) BETWEEN ? AND ?',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );
        } catch (Throwable) {
            return [
                'solicitudes' => [],
                'total' => 0,
            ];
        }

        return [
            'solicitudes' => array_map(static fn(object $row): array => (array) $row, $rows),
            'total' => (int) ($countRow->total ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTopDoctores(CarbonImmutable $start, CarbonImmutable $end): array
    {
        try {
            $rows = DB::select(
                'SELECT pr.cirujano_1, COUNT(*) AS total,
                        (
                            SELECT u.profile_photo
                            FROM users u
                            WHERE u.profile_photo IS NOT NULL
                              AND u.profile_photo <> ""
                              AND (
                                LOWER(TRIM(u.nombre)) = LOWER(TRIM(pr.cirujano_1))
                                OR LOWER(TRIM(pr.cirujano_1)) LIKE CONCAT("%", LOWER(TRIM(u.nombre)), "%")
                                OR LOWER(TRIM(u.username)) = LOWER(TRIM(pr.cirujano_1))
                                OR LOWER(TRIM(u.email)) = LOWER(TRIM(pr.cirujano_1))
                              )
                            ORDER BY u.id ASC
                            LIMIT 1
                        ) AS avatar_path
                 FROM protocolo_data pr
                 WHERE pr.cirujano_1 IS NOT NULL
                   AND pr.cirujano_1 != ""
                   AND pr.fecha_inicio BETWEEN ? AND ?
                 GROUP BY pr.cirujano_1
                 ORDER BY total DESC
                 LIMIT 5',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );
        } catch (Throwable) {
            return [];
        }

        $doctores = [];
        foreach ($rows as $row) {
            $doctor = (array) $row;
            $doctor['avatar'] = $this->formatProfilePhoto((string) ($doctor['avatar_path'] ?? ''));
            unset($doctor['avatar_path']);
            $doctores[] = $doctor;
        }

        return $doctores;
    }

    private function formatProfilePhoto(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @return array{afiliaciones: array<int, string>, totales: array<int, int>}
     */
    private function getEstadisticasPorAfiliacion(CarbonImmutable $start, CarbonImmutable $end): array
    {
        try {
            $rows = DB::select(
                'SELECT p.afiliacion, COUNT(*) AS total_procedimientos
                 FROM protocolo_data pr
                 INNER JOIN patient_data p ON pr.hc_number = p.hc_number
                 WHERE pr.fecha_inicio BETWEEN ? AND ?
                 GROUP BY p.afiliacion',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            );
        } catch (Throwable) {
            return [
                'afiliaciones' => ['No data'],
                'totales' => [0],
            ];
        }

        $afiliaciones = [];
        $totales = [];
        foreach ($rows as $row) {
            $afiliaciones[] = (string) ($row->afiliacion ?? '');
            $totales[] = (int) ($row->total_procedimientos ?? 0);
        }

        return [
            'afiliaciones' => $afiliaciones !== [] ? $afiliaciones : ['No data'],
            'totales' => $totales !== [] ? $totales : [0],
        ];
    }

    /**
     * @param array<string, mixed> $summaryData
     * @return array<int, array<string, mixed>>
     */
    private function buildKpiCardsFromSummary(array $summaryData): array
    {
        $registradas = (int) (($summaryData['solicitudes_funnel']['totales']['registradas'] ?? 0));
        $agendadas = (int) (($summaryData['solicitudes_funnel']['totales']['agendadas'] ?? 0));
        $conversion = (float) (($summaryData['solicitudes_funnel']['totales']['conversion_agendada'] ?? 0.0));
        $conCirugia = (int) (($summaryData['solicitudes_funnel']['totales']['con_cirugia'] ?? 0));
        $urgentesSinTurno = (int) (($summaryData['solicitudes_funnel']['totales']['urgentes_sin_turno'] ?? 0));
        $crmVencidas = (int) (($summaryData['crm_backlog']['vencidas'] ?? 0));
        $crmAvance = (float) (($summaryData['crm_backlog']['avance'] ?? 0.0));
        $protocolosNoRevisados = (int) (($summaryData['revision_estados']['no_revisados'] ?? 0));
        $protocolosIncompletos = (int) (($summaryData['revision_estados']['incompletos'] ?? 0));

        return [
            [
                'title' => 'Solicitudes registradas',
                'value' => $registradas,
                'description' => 'En este periodo',
                'icon' => 'svg-icon/color-svg/1.svg',
                'tag' => $conversion > 0 ? $conversion . '% agendadas' : 'Sin agenda registrada',
            ],
            [
                'title' => 'Agenda confirmada',
                'value' => $agendadas,
                'description' => 'Solicitudes con turno asignado',
                'icon' => 'svg-icon/color-svg/2.svg',
                'tag' => $conCirugia > 0 ? $conCirugia . ' con cirugía' : 'Sin cirugías vinculadas',
            ],
            [
                'title' => 'Urgentes sin turno',
                'value' => $urgentesSinTurno,
                'description' => 'Urgentes pendientes de agenda',
                'icon' => 'svg-icon/color-svg/3.svg',
                'tag' => $urgentesSinTurno > 0 ? 'Revisar backlog' : 'Todo al día',
            ],
            [
                'title' => 'Tareas CRM vencidas',
                'value' => $crmVencidas,
                'description' => 'Pendientes de seguimiento',
                'icon' => 'svg-icon/color-svg/4.svg',
                'tag' => $crmAvance . '% completadas',
            ],
            [
                'title' => 'Protocolos sin revisar',
                'value' => $protocolosNoRevisados,
                'description' => 'Listos para auditoría final',
                'icon' => 'svg-icon/color-svg/5.svg',
                'tag' => $protocolosIncompletos . ' incompletos',
            ],
            [
                'title' => 'Asistente IA',
                'value' => 'En migración',
                'description' => 'Paridad en construcción',
                'icon' => 'svg-icon/color-svg/6.svg',
                'tag' => 'Fase Dashboard',
            ],
        ];
    }
}
