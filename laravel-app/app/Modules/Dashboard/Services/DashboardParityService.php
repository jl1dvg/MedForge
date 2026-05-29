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
            'dashboard_v3' => $this->buildDashboardV3Payload($start, $end, $summaryData),
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
     * @param array<string, mixed> $summaryData
     * @return array<string, mixed>
     */
    private function buildDashboardV3Payload(CarbonImmutable $start, CarbonImmutable $end, array $summaryData): array
    {
        $today = CarbonImmutable::today();

        return [
            'hero_kpis'     => $this->getDashboardV3HeroKpis($today, $summaryData),
            'agenda'        => $this->getDashboardV3Agenda($today),
            'flujo_columns' => $this->getDashboardV3FlujoColumns($today),
            'salas'         => $this->getDashboardV3Salas($today),
            'ops'           => $this->getDashboardV3Ops($today, $start, $end, $summaryData),
            'referidos_hoy'     => $this->getReferidosHoyStats($today),
            'congestion_medicos' => $this->getCongestionMedicosHoy($today),
            'ia_suggestions'    => [],
        ];
    }

    /**
     * @param array<string, mixed> $summaryData
     * @return array<int, array<string, mixed>>
     */
    private function getDashboardV3HeroKpis(CarbonImmutable $today, array $summaryData): array
    {
        $patients       = $this->getPatientsTodayStats($today);
        $cirugias       = $this->getCirugiasTodayStats($today);
        $solicitudesHoy = $this->getSolicitudesHoyStats($today);
        $whatsapp       = $this->getWhatsappUnansweredStats();

        return [
            [
                'icon' => 'mdi-account-multiple-outline',
                'tone' => 'primary',
                'label' => 'Pacientes hoy',
                'value' => (int) $patients['total'],
                'trend' => 'Agenda y admisión de hoy',
                'breakdown' => [
                    ['dot' => 'success', 'n' => (int) $patients['atendidos'], 'label' => 'Atendidos'],
                    ['dot' => 'warning', 'n' => (int) $patients['en_sala'], 'label' => 'En sala'],
                    ['dot' => 'info', 'n' => (int) $patients['esperando'], 'label' => 'Sin llegar'],
                ],
            ],
            [
                'icon' => 'mdi-hospital-box-outline',
                'tone' => 'danger',
                'label' => 'Cirugías hoy',
                'value' => (int) $cirugias['total'],
                'trend' => 'Agenda quirúrgica de hoy',
                'breakdown' => [
                    ['dot' => 'success', 'n' => (int) $cirugias['realizadas'], 'label' => 'Realizadas'],
                    ['dot' => 'info', 'n' => (int) $cirugias['programadas'], 'label' => 'Programadas'],
                    ['dot' => 'warning', 'n' => (int) $cirugias['sin_protocolo'], 'label' => 'Sin protocolo'],
                ],
            ],
            [
                'icon' => 'mdi-clipboard-text-clock-outline',
                'tone' => 'info',
                'label' => 'Solicitudes quirúrgicas',
                'value' => (int) $solicitudesHoy['total'],
                'trend' => 'Nuevas hoy',
                'breakdown' => [
                    ['dot' => 'danger',  'n' => (int) $solicitudesHoy['urgentes'],   'label' => 'Urgentes'],
                    ['dot' => 'info',    'n' => (int) $solicitudesHoy['recibidas'],  'label' => 'Recibidas'],
                    ['dot' => 'warning', 'n' => (int) $solicitudesHoy['en_gestion'], 'label' => 'En gestión'],
                ],
            ],
            [
                'icon' => 'mdi-whatsapp',
                'tone' => 'success',
                'label' => 'WhatsApp pendientes',
                'value' => (int) $whatsapp['unanswered'],
                'trend' => (string) $whatsapp['source_label'],
                'breakdown' => [
                    ['dot' => 'danger',  'n' => (int) $whatsapp['sin_asignar'],        'label' => 'Sin asignar'],
                    ['dot' => 'warning', 'n' => (int) $whatsapp['en_progreso'],         'label' => 'En progreso'],
                    ['dot' => 'info',    'n' => (int) $whatsapp['esperando_paciente'],  'label' => 'Esp. paciente'],
                ],
            ],
        ];
    }

    /**
     * @return array{total:int, atendidos:int, en_sala:int, esperando:int}
     */
    private function getPatientsTodayStats(CarbonImmutable $today): array
    {
        try {
            $row = DB::selectOne(
                'SELECT
                    COUNT(DISTINCT pp.hc_number) AS total,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM consulta_data cd
                        WHERE cd.form_id = pp.form_id AND cd.hc_number = pp.hc_number
                    ) OR LOWER(COALESCE(pp.estado_agenda, "")) REGEXP "realiz|atendid|complet" THEN 1 ELSE 0 END) AS atendidos,
                    SUM(CASE WHEN v.hora_llegada IS NOT NULL
                        AND NOT EXISTS (
                            SELECT 1 FROM consulta_data cd
                            WHERE cd.form_id = pp.form_id AND cd.hc_number = pp.hc_number
                        ) THEN 1 ELSE 0 END) AS en_sala
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) = ?',
                [$today->format('Y-m-d')]
            );
        } catch (Throwable) {
            return ['total' => 0, 'atendidos' => 0, 'en_sala' => 0, 'esperando' => 0];
        }

        $total = (int) ($row->total ?? 0);
        $atendidos = (int) ($row->atendidos ?? 0);
        $enSala = (int) ($row->en_sala ?? 0);

        return [
            'total' => $total,
            'atendidos' => $atendidos,
            'en_sala' => $enSala,
            'esperando' => max(0, $total - $atendidos - $enSala),
        ];
    }

    /**
     * @return array{total:int, realizadas:int, programadas:int, sin_protocolo:int}
     */
    private function getCirugiasTodayStats(CarbonImmutable $today): array
    {
        $date = $today->format('Y-m-d');
        try {
            $row = DB::selectOne(
                'SELECT
                    COUNT(*) AS programadas,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM protocolo_data pr
                        WHERE pr.form_id = pp.form_id AND pr.hc_number = pp.hc_number
                    ) THEN 1 ELSE 0 END) AS realizadas
                 FROM procedimiento_proyectado pp
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND DATE(pp.fecha) = ?
                   AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) LIKE "CIRUGIAS%"',
                [$date]
            );
        } catch (Throwable) {
            return ['total' => 0, 'realizadas' => 0, 'programadas' => 0, 'sin_protocolo' => 0];
        }

        $programadas = (int) ($row->programadas ?? 0);
        $realizadas  = (int) ($row->realizadas ?? 0);

        return [
            'total'        => $programadas,
            'realizadas'   => $realizadas,
            'programadas'  => $programadas,
            'sin_protocolo' => max(0, $programadas - $realizadas),
        ];
    }

    /**
     * @return array{total:int, urgentes:int, con_turno:int, con_cirugia:int}
     */
    /**
     * @return array{total: int, breakdown: array<int, array{label: string, n: int, pct: float}>}
     */
    /**
     * @return array<int, array{doctor:string, total_agenda:int, en_espera:int, atendidos:int, avg_espera_min:int|null}>
     */
    private function getCongestionMedicosHoy(CarbonImmutable $today): array
    {
        $date = $today->format('Y-m-d');
        $doneStates   = ['ATENDIDAS', 'TERMINADO', 'ATENDIDO', 'CONSULTA_TERMINADO'];
        $arrivedStates = ['LLEGADO', 'CONFIRMADO', 'CONSULTA', 'OPTOMETRIA'];
        $inDone    = implode(',', array_fill(0, count($doneStates), '?'));
        $inArrived = implode(',', array_fill(0, count($arrivedStates), '?'));

        try {
            $rows = DB::select(
                "SELECT
                    pp.doctor,
                    COUNT(*) AS total_agenda,
                    SUM(CASE WHEN UPPER(TRIM(COALESCE(pp.estado_agenda,''))) IN ($inDone)
                             THEN 1 ELSE 0 END) AS atendidos,
                    SUM(CASE WHEN UPPER(TRIM(COALESCE(pp.estado_agenda,''))) IN ($inArrived)
                             THEN 1 ELSE 0 END) AS en_espera,
                    AVG(CASE
                        WHEN UPPER(TRIM(COALESCE(pp.estado_agenda,''))) IN ($inArrived)
                             AND v.hora_llegada IS NOT NULL
                        THEN TIMESTAMPDIFF(MINUTE,
                             CONCAT(CURDATE(), ' ', v.hora_llegada),
                             NOW())
                        ELSE NULL
                    END) AS avg_espera_min
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) = ?
                   AND pp.doctor IS NOT NULL
                   AND TRIM(pp.doctor) != ''
                 GROUP BY pp.doctor
                 ORDER BY en_espera DESC, total_agenda DESC
                 LIMIT 10",
                array_merge($doneStates, $arrivedStates, $arrivedStates, [$date])
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn(object $r): array => [
            'doctor'        => trim((string) ($r->doctor ?? '')),
            'total_agenda'  => (int) ($r->total_agenda ?? 0),
            'atendidos'     => (int) ($r->atendidos ?? 0),
            'en_espera'     => (int) ($r->en_espera ?? 0),
            'avg_espera_min' => $r->avg_espera_min !== null ? (int) round((float) $r->avg_espera_min) : null,
        ], $rows);
    }

        private function getReferidosHoyStats(CarbonImmutable $today): array
    {
        try {
            $rows = DB::select(
                'SELECT
                    COALESCE(NULLIF(TRIM(referido_prefactura_por), ""), "Sin especificar") AS fuente,
                    COUNT(DISTINCT hc_number) AS total
                 FROM procedimiento_proyectado
                 WHERE COALESCE(sigcenter_present, 1) = 1
                   AND DATE(fecha) = ?
                 GROUP BY fuente
                 ORDER BY total DESC',
                [$today->format('Y-m-d')]
            );
        } catch (Throwable) {
            return ['total' => 0, 'breakdown' => []];
        }

        $grand = array_sum(array_column($rows, 'total'));
        if ($grand === 0) {
            return ['total' => 0, 'breakdown' => []];
        }

        $breakdown = [];
        foreach ($rows as $row) {
            $n = (int) $row->total;
            $breakdown[] = [
                'label' => (string) $row->fuente,
                'n'     => $n,
                'pct'   => round($n / $grand * 100, 1),
            ];
        }

        return ['total' => $grand, 'breakdown' => $breakdown];
    }

        private function getSolicitudesHoyStats(CarbonImmutable $today): array
    {
        try {
            $row = DB::selectOne(
                'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN UPPER(TRIM(COALESCE(sp.prioridad, ""))) = "URGENTE" THEN 1 ELSE 0 END) AS urgentes,
                    SUM(CASE WHEN UPPER(TRIM(COALESCE(sp.estado, ""))) = "RECIBIDO" THEN 1 ELSE 0 END) AS recibidas,
                    SUM(CASE WHEN TRIM(COALESCE(sp.estado, "")) != ""
                              AND UPPER(TRIM(sp.estado)) != "RECIBIDO" THEN 1 ELSE 0 END) AS en_gestion
                 FROM solicitud_procedimiento sp
                 WHERE sp.procedimiento IS NOT NULL
                   AND sp.procedimiento != ""
                   AND sp.procedimiento != "SELECCIONE"
                   AND DATE(COALESCE(sp.created_at, sp.fecha)) = ?',
                [$today->format('Y-m-d')]
            );
        } catch (Throwable) {
            return ['total' => 0, 'urgentes' => 0, 'recibidas' => 0, 'en_gestion' => 0];
        }

        return [
            'total'     => (int) ($row->total ?? 0),
            'urgentes'  => (int) ($row->urgentes ?? 0),
            'recibidas' => (int) ($row->recibidas ?? 0),
            'en_gestion' => (int) ($row->en_gestion ?? 0),
        ];
    }

        /**
     * @return array{unanswered:int, sin_asignar:int, en_progreso:int, esperando_paciente:int, source_label:string}
     */
    private function getWhatsappUnansweredStats(): array
    {
        if (!$this->tableExists('whatsapp_conversations')) {
            return [
                'unanswered' => 0,
                'sin_asignar' => 0,
                'en_progreso' => 0,
                'esperando_paciente' => 0,
                'source_label' => 'Sin fuente conectada',
            ];
        }

        try {
            $row = DB::selectOne(
                'SELECT
                    COUNT(*) AS unanswered,
                    SUM(CASE WHEN assigned_user_id IS NULL THEN 1 ELSE 0 END) AS sin_asignar,
                    SUM(CASE WHEN assigned_user_id IS NOT NULL
                              AND last_message_direction = "inbound" THEN 1 ELSE 0 END) AS en_progreso,
                    SUM(CASE WHEN assigned_user_id IS NOT NULL
                              AND (last_message_direction IS NULL
                                   OR last_message_direction != "inbound") THEN 1 ELSE 0 END) AS esperando_paciente
                 FROM whatsapp_conversations
                 WHERE needs_human = 1'
            );
        } catch (Throwable) {
            return [
                'unanswered' => 0,
                'sin_asignar' => 0,
                'en_progreso' => 0,
                'esperando_paciente' => 0,
                'source_label' => 'Sin métrica disponible',
            ];
        }

        return [
            'unanswered'        => (int) ($row->unanswered ?? 0),
            'sin_asignar'       => (int) ($row->sin_asignar ?? 0),
            'en_progreso'       => (int) ($row->en_progreso ?? 0),
            'esperando_paciente' => (int) ($row->esperando_paciente ?? 0),
            'source_label'      => 'Requieren atención humana',
        ];
    }

    /**
     * Returns all agenda items for today ordered by time, plus a pivot_index pointing
     * to the entry closest to the current clock time so the frontend can center the scroll.
     *
     * @return array{items: array<int, array<string, mixed>>, pivot_index: int}
     */
    private function getDashboardV3Agenda(CarbonImmutable $today): array
    {
        try {
            $rows = DB::select(
                'SELECT
                    pp.form_id,
                    pp.hc_number,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    pp.hora,
                    pp.estado_agenda,
                    COALESCE(NULLIF(TRIM(pp.sede_departamento), ""), NULLIF(TRIM(pp.id_sede), ""), "") AS sede,
                    TRIM(CONCAT_WS(" ", pd.fname, pd.mname, pd.lname, pd.lname2)) AS paciente
                 FROM procedimiento_proyectado pp
                 LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) = ?
                 ORDER BY pp.hora ASC, pp.form_id ASC
                 LIMIT 200',
                [$today->format('Y-m-d')]
            );
        } catch (Throwable) {
            return ['items' => [], 'pivot_index' => 0];
        }

        $nowMinutes = (int) date('H') * 60 + (int) date('i');
        $pivotIndex = 0;
        $pivotDiff = PHP_INT_MAX;

        $doneStates = ['ATENDIDAS', 'TERMINADO', 'ATENDIDO', 'CONSULTA_TERMINADO'];
        $liveStates = ['CONSULTA', 'OPTOMETRIA', 'LLEGADO', 'CONFIRMADO'];

        $items = [];
        foreach ($rows as $i => $row) {
            $estado = strtoupper(trim((string) ($row->estado_agenda ?? '')));

            if (in_array($estado, $doneStates, true)) {
                $state = 'done';
            } elseif (in_array($estado, $liveStates, true)) {
                $state = 'live';
            } else {
                $state = 'next';
            }

            $timeStr = substr((string) ($row->hora ?? ''), 0, 5);
            if ($timeStr !== '' && str_contains($timeStr, ':')) {
                [$h, $m] = explode(':', $timeStr);
                $diff = abs((int) $h * 60 + (int) $m - $nowMinutes);
                if ($diff < $pivotDiff) {
                    $pivotDiff = $diff;
                    $pivotIndex = $i;
                }
            }

            $items[] = [
                'time'  => $timeStr ?: '--:--',
                'state' => $state,
                'cat'   => $this->classifyDashboardV3Procedure((string) ($row->procedimiento_proyectado ?? '')),
                'name'  => trim((string) ($row->paciente ?? '')) ?: ('HC ' . (string) ($row->hc_number ?? '')),
                'doc'   => trim((string) ($row->doctor ?? '')) ?: 'Sin médico',
                'room'  => trim((string) ($row->sede ?? '')) ?: 'Sin sede',
            ];
        }

        return ['items' => $items, 'pivot_index' => $pivotIndex];
    }

    private function classifyDashboardV3Procedure(string $procedure): string
    {
        $upper = strtoupper(trim($procedure));
        if (str_starts_with($upper, 'CIRUGIAS')) {
            return 'cirugia';
        }
        if (str_starts_with($upper, 'IMAGENES')) {
            return 'examen';
        }
        if (str_contains($upper, 'OPTOMETRIA')) {
            return 'optometria';
        }
        return 'consulta';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDashboardV3FlujoColumns(CarbonImmutable $today): array
    {
        $date = $today->format('Y-m-d');
        $columns = [
            'espera'   => ['id' => 'espera',   'label' => 'Llegaron',     'count' => 0, 'sample' => []],
            'revision' => ['id' => 'revision', 'label' => 'Agendados',    'count' => 0, 'sample' => []],
            'sala'     => ['id' => 'sala',     'label' => 'Con consulta', 'count' => 0, 'sample' => []],
            'lista'    => ['id' => 'lista',    'label' => 'Quirúrgicos',  'count' => 0, 'sample' => []],
        ];

        try {
            $rows = DB::select(
                'SELECT
                    pp.hc_number,
                    pp.procedimiento_proyectado,
                    pp.estado_agenda,
                    TRIM(CONCAT_WS(" ", pd.fname, pd.lname)) AS paciente
                 FROM procedimiento_proyectado pp
                 LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) = ?
                 ORDER BY pp.hora ASC, pp.form_id ASC',
                [$date]
            );
        } catch (Throwable) {
            return array_values($columns);
        }

        $llegadoStates   = ['LLEGADO', 'CONFIRMADO', 'CONSULTA', 'OPTOMETRIA'];
        $consultaStates  = ['CONSULTA_TERMINADO', 'ATENDIDAS', 'TERMINADO', 'ATENDIDO'];

        foreach ($rows as $row) {
            $label  = trim((string) ($row->paciente ?? '')) ?: ('HC ' . (string) ($row->hc_number ?? ''));
            $line   = $label . ' · ' . (string) ($row->hc_number ?? '');
            $estado = strtoupper(trim((string) ($row->estado_agenda ?? '')));
            $proc   = strtoupper(trim((string) ($row->procedimiento_proyectado ?? '')));

            $columns['revision']['count']++;
            if (count($columns['revision']['sample']) < 2) {
                $columns['revision']['sample'][] = $line;
            }

            if (in_array($estado, $llegadoStates, true)) {
                $columns['espera']['count']++;
                if (count($columns['espera']['sample']) < 2) {
                    $columns['espera']['sample'][] = $line;
                }
            }

            if (in_array($estado, $consultaStates, true)) {
                $columns['sala']['count']++;
                if (count($columns['sala']['sample']) < 2) {
                    $columns['sala']['sample'][] = $line;
                }
            }

            if (str_starts_with($proc, 'CIRUGIAS')) {
                $columns['lista']['count']++;
                if (count($columns['lista']['sample']) < 2) {
                    $columns['lista']['sample'][] = $line;
                }
            }
        }

        return array_values($columns);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDashboardV3Salas(CarbonImmutable $today): array
    {
        $date = $today->format('Y-m-d');
        $presentStates = ['LLEGADO', 'CONFIRMADO', 'CONSULTA', 'OPTOMETRIA',
                          'CONSULTA_TERMINADO', 'ATENDIDAS', 'TERMINADO', 'ATENDIDO'];
        $inList = implode(',', array_fill(0, count($presentStates), '?'));

        try {
            $rows = DB::select(
                "SELECT
                    pp.hc_number,
                    pp.hora,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    pp.estado_agenda,
                    TRIM(CONCAT_WS(' ', pd.fname, pd.lname, pd.lname2)) AS paciente,
                    pr.form_id        AS protocolo_form_id,
                    pr.membrete       AS protocolo_membrete,
                    pr.cirujano_1     AS protocolo_cirujano,
                    pr.fecha_inicio   AS protocolo_fecha_inicio
                 FROM procedimiento_proyectado pp
                 LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 LEFT JOIN protocolo_data pr
                        ON pr.form_id    = pp.form_id
                       AND pr.hc_number  = pp.hc_number
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) = ?
                   AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE 'CIRUGIAS%'
                   AND (
                       UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) IN ($inList)
                       OR pr.form_id IS NOT NULL
                   )
                 ORDER BY pp.hora ASC, pp.form_id ASC",
                array_merge([$date], $presentStates)
            );
        } catch (Throwable) {
            return [];
        }

        $salas = [];
        foreach ($rows as $index => $row) {
            $hasProtocolo = $row->protocolo_form_id !== null;

            $proc = $hasProtocolo
                ? trim((string) ($row->protocolo_membrete ?? ''))
                : trim((string) ($row->procedimiento_proyectado ?? ''));

            $doc = $hasProtocolo
                ? trim((string) ($row->protocolo_cirujano ?? ''))
                : trim((string) ($row->doctor ?? ''));

            $time = '';
            if ($hasProtocolo && !empty($row->protocolo_fecha_inicio)) {
                $time = date('H:i', strtotime((string) $row->protocolo_fecha_inicio));
            } elseif (!empty($row->hora)) {
                $time = substr((string) $row->hora, 0, 5);
            }

            $salas[] = [
                'n'       => $index + 1,
                'state'   => $hasProtocolo ? 'realizada' : 'pendiente',
                'patient' => trim((string) ($row->paciente ?? '')) ?: ('HC ' . (string) ($row->hc_number ?? '')),
                'proc'    => $proc ?: 'Sin procedimiento',
                'doc'     => $doc ?: 'Sin cirujano',
                'time'    => $time ?: '--:--',
            ];
        }

        return $salas;
    }

    /**
     * @param array<string, mixed> $summaryData
     * @return array<int, array<string, mixed>>
     */
    private function getDashboardV3Ops(CarbonImmutable $today, CarbonImmutable $start, CarbonImmutable $end, array $summaryData): array
    {
        $crmBacklog = is_array($summaryData['crm_backlog'] ?? null) ? $summaryData['crm_backlog'] : [];

        return [
            [
                'icon' => 'mdi-cash-register',
                'tone' => 'warning',
                'module' => 'Facturación',
                'value' => $this->countDashboardV3Unbilled($today, $today),
                'label' => 'sin facturar',
                'sub' => 'Agendados hoy sin registro en facturación',
                'href' => '/v2/billing',
            ],
            [
                'icon' => 'mdi-microscope',
                'tone' => 'info',
                'module' => 'Exámenes',
                'value' => $this->countDashboardV3PendingExams($start, $end),
                'label' => 'pendientes',
                'sub' => 'consulta_examenes sin estado final',
                'href' => '/v2/examenes',
            ],
            [
                'icon' => 'mdi-pill-multiple',
                'tone' => 'danger',
                'module' => 'Farmacia',
                'value' => $this->countDashboardV3PharmacyPending($today, $today),
                'label' => 'medicamentos prescritos',
                'sub' => 'Ítems de receta de hoy',
                'href' => '/v2/farmacia',
            ],
            [
                'icon' => 'mdi-file-document-edit-outline',
                'tone' => 'primary',
                'module' => 'Protocolos',
                'value' => $this->countDashboardV3ProtocolosSinRevisar($start, $end),
                'label' => 'sin revisar',
                'sub' => 'Protocolos del período sin marcar como revisados',
                'href' => '/v2/cirugias',
            ],
            [
                'icon' => 'mdi-cash-fast',
                'tone' => 'success',
                'module' => 'CRM tareas',
                'value' => (int) ($crmBacklog['pendientes'] ?? 0),
                'label' => 'pendientes',
                'sub' => ((int) ($crmBacklog['vencidas'] ?? 0)) . ' vencidas · ' . ((int) ($crmBacklog['vencen_hoy'] ?? 0)) . ' vencen hoy',
                'href' => '/v2/crm/leads',
            ],
        ];
    }

    private function countDashboardV3Unbilled(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (!$this->tableExists('billing_facturacion_real')) {
            return 0;
        }

        try {
            return (int) (DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 LEFT JOIN billing_facturacion_real bfr ON bfr.form_id = pp.form_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) BETWEEN ? AND ?
                   AND bfr.id IS NULL',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )->total ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function countDashboardV3PendingExams(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (!$this->tableExists('imagenes_nas_index')) {
            return 0;
        }

        try {
            return (int) (DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 LEFT JOIN imagenes_nas_index idx ON idx.form_id = pp.form_id
                 WHERE COALESCE(pp.sigcenter_present, 1) = 1
                   AND COALESCE(DATE(pp.fecha), v.fecha_visita) BETWEEN ? AND ?
                   AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) LIKE "IMAGENES%"
                   AND idx.form_id IS NULL',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )->total ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function countDashboardV3PharmacyPending(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (!$this->tableExists('recetas_items')) {
            return 0;
        }

        try {
            return (int) (DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM recetas_items
                 WHERE DATE(created_at) BETWEEN ? AND ?',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )->total ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function countDashboardV3ProtocolosSinRevisar(CarbonImmutable $start, CarbonImmutable $end): int
    {
        try {
            return (int) (DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM protocolo_data
                 WHERE fecha_inicio BETWEEN ? AND ?
                   AND (status IS NULL OR status != 1)',
                [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
            )->total ?? 0);
        } catch (Throwable) {
            return 0;
        }
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

    private function tableExists(string $table): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT 1 AS found
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                 LIMIT 1',
                [$table]
            );
        } catch (Throwable) {
            return false;
        }

        return (int) ($row->found ?? 0) === 1;
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
             LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number AND COALESCE(pp.sigcenter_present, 1) = 1
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
