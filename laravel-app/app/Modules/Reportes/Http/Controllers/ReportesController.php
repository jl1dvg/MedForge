<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Cirugias\Services\CirugiasDashboardService;
use App\Modules\Examenes\Services\ImagenesUiService;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class ReportesController
{
    private CirugiasDashboardService $cirugiasDashboard;
    private ImagenesUiService $imagenesDashboard;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->cirugiasDashboard = new CirugiasDashboardService($pdo);
        $this->imagenesDashboard = new ImagenesUiService();
    }

    public function index(Request $request): View
    {
        $sedeOptions = [
            ['value' => 'MATRIZ', 'label' => 'Matriz'],
            ['value' => 'CEIBOS', 'label' => 'Ceibos'],
        ];

        return view('reportes.v2-unified', [
            'sedeOptions' => $sedeOptions,
        ]);
    }

    public function apiCirugias(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesion expirada'], 401);
        }

        $start = trim((string) $request->query('start', ''));
        $end   = trim((string) $request->query('end', ''));
        $sede  = trim((string) $request->query('sede', ''));

        if ($start === '') {
            $start = now()->startOfMonth()->format('Y-m-d');
        }
        if ($end === '') {
            $end = now()->endOfMonth()->format('Y-m-d');
        }

        try {
            $report = $this->cirugiasDashboard->buildReportPayload($start, $end, $sede);
        } catch (Throwable $e) {
            Log::error('reportes.api.cirugias.error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo generar el reporte'], 500);
        }

        // Enrich period labels
        $report['period']['key']       = $this->detectPeriodKey($start, $end);
        $report['period']['fromLabel'] = (new DateTimeImmutable($start))->format('d/m/Y');
        $report['period']['toLabel']   = (new DateTimeImmutable($end))->format('d/m/Y');
        $report['sede']                = [
            'id'    => $sede ?: 'todas',
            'label' => $sede ?: 'Todas las sedes',
        ];

        return response()->json($report);
    }

    public function apiImagenes(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesion expirada'], 401);
        }

        $start = trim((string) $request->query('start', ''));
        $end   = trim((string) $request->query('end', ''));
        $sede  = trim((string) $request->query('sede', ''));

        if ($start === '') {
            $start = now()->startOfMonth()->format('Y-m-d');
        }
        if ($end === '') {
            $end = now()->endOfMonth()->format('Y-m-d');
        }

        try {
            $query = [
                'fecha_inicio' => $start,
                'fecha_fin'    => $end,
                'sede'         => $sede,
            ];
            $payload = $this->imagenesDashboard->buildExecutiveReportPayload($query);
        } catch (Throwable $e) {
            Log::error('reportes.api.imagenes.error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'No se pudo generar el reporte'], 500);
        }

        $report = $this->transformImagenesPayload($payload, $start, $end, $sede);

        return response()->json($report);
    }

    private function detectPeriodKey(string $start, string $end): string
    {
        try {
            $from = new DateTimeImmutable($start);
            $to   = new DateTimeImmutable($end);
            $diff = (int) $from->diff($to)->days;
            if ($diff <= 32)  return 'mes';
            if ($diff <= 95)  return 'trim';
            if ($diff <= 185) return 'sem';
        } catch (Throwable) { /* ignore */ }
        return 'ano';
    }

    /**
     * Transform the ImagenesUiService executive payload into the unified React format.
     *
     * @param array<string,mixed> $payload
     */
    private function transformImagenesPayload(array $payload, string $start, string $end, string $sede): array
    {
        $db     = $payload['dashboard']   ?? [];
        $meta   = $db['meta']             ?? [];
        $charts = $db['charts']           ?? [];
        $tiempoAcceso = $payload['tiempoAcceso'] ?? [];

        $solicitudes    = (int)($meta['solicitudes_total']            ?? 0);
        $agendadas      = (int)($meta['solicitudes_agendadas']        ?? 0);
        $realizadas     = (int)($meta['solicitudes_realizadas']       ?? $meta['solicitudes_realizadas_al_corte'] ?? 0);
        $informadas     = (int)($meta['solicitudes_informadas']       ?? 0);
        $facturadas     = (int)($meta['facturados']                   ?? $meta['solicitudes_facturadas'] ?? 0);
        $facturadoReal  = (float)($meta['produccion_facturada']       ?? 0);
        $pendFact       = (int)($meta['pendientes_facturar']          ?? 0);
        $montoPend      = (float)($meta['monto_pendiente_estimado']   ?? 0);
        $sinAgenda      = (int)($meta['solicitudes_sin_agenda']       ?? 0);
        $sinAgendaMonto = (float)($meta['solicitudes_sin_agenda_monto_estimado'] ?? 0);
        $pendPago       = (int)($meta['pendientes_pago']              ?? 0);
        $sinTarifa      = (int)($meta['pendientes_facturar_sin_tarifa'] ?? 0);
        $cumplPct       = $meta['cumplimiento_realizacion_al_corte_pct'] ?? null;
        $cumplimiento   = $cumplPct !== null ? (float)$cumplPct : ($solicitudes > 0 ? round($realizadas / $solicitudes * 100, 1) : 0);
        $ticketProm     = (float)($meta['ticket_promedio_facturado']  ?? 0);
        $tatProm        = $meta['tat_promedio_horas'] ?? null;
        $tatP90         = $meta['tat_p90_horas']      ?? null;

        // SLA 48h from cards
        $sla48 = null;
        foreach ($db['cards'] ?? [] as $card) {
            if (($card['label'] ?? '') === 'SLA informe <= 48h') {
                $raw = $card['value'] ?? '';
                if ($raw !== '—') { $sla48 = (float)str_replace('%', '', (string)$raw); }
            }
        }

        $money   = static fn(float $v): string => '$' . number_format((int)$v, 0, '.', ',');
        $moneyK  = static fn(float $v): string => $v >= 1000 ? '$' . number_format($v / 1000, $v >= 10000 ? 0 : 1) + 'k' : '$' . (int)$v;
        $pct     = static fn(int $a, int $b): float => $b > 0 ? round($a / $b * 100, 1) : 0.0;

        // Flow stages
        $flow = [
            ['key' => 'solicitudes', 'label' => 'Solicitudes', 'value' => $solicitudes, 'context' => 'Demanda de estudios registrada',          'cls' => 'health', 'leak' => null],
            ['key' => 'agendadas',   'label' => 'Agendadas',   'value' => $agendadas,   'context' => $pct($agendadas, $solicitudes) . '% de solicitudes', 'cls' => 'health',
             'leak' => ['label' => 'Sin agenda', 'count' => $sinAgenda, 'amount' => $sinAgendaMonto]],
            ['key' => 'realizadas',  'label' => 'Realizadas',  'value' => $realizadas,  'context' => $cumplimiento . '% cumplimiento al corte', 'cls' => 'good',
             'leak' => ['label' => 'Ausentes', 'count' => max(0, $agendadas - $realizadas), 'amount' => 0]],
            ['key' => 'informadas',  'label' => 'Informadas',  'value' => $informadas,  'context' => 'SLA ≤48h: ' . ($sla48 !== null ? $sla48 . '%' : '—'), 'cls' => 'warning',
             'leak' => ['label' => 'Pend. informe', 'count' => max(0, $realizadas - $informadas), 'amount' => 0]],
            ['key' => 'facturadas',  'label' => 'Facturadas',  'value' => $facturadas,  'context' => $money($facturadoReal) . ' facturado', 'cls' => 'bill',
             'leak' => ['label' => 'Pend. facturar', 'count' => $pendFact, 'amount' => $montoPend]],
        ];

        $links = [
            ['pct' => $pct($agendadas, $solicitudes)],
            ['pct' => $pct($realizadas, $agendadas)],
            ['pct' => $pct($informadas, $realizadas)],
            ['pct' => $pct($facturadas, $informadas)],
        ];

        $kpis = [
            ['label' => 'Facturado real',         'value' => $money($facturadoReal), 'hint' => $facturadas . ' estudios facturados',        'source' => 'Etapa · Facturadas',         'cls' => 'good'],
            ['label' => 'Pendiente de facturar',  'value' => $money($montoPend),     'hint' => $pendFact . ' realizados sin billing',       'source' => 'Realizadas → Facturadas',    'cls' => 'warning'],
            ['label' => 'Pérdida por no agendar', 'value' => $money($sinAgendaMonto),'hint' => $sinAgenda . ' solicitudes sin agenda',      'source' => 'Solicitudes → Agendadas',    'cls' => 'danger'],
            ['label' => 'Pendiente de pago',      'value' => number_format($pendPago),'hint' => 'Billing emitido en cartera',               'source' => 'Facturadas → Cobro',         'cls' => 'amber'],
            ['label' => 'Cumplimiento al corte',  'value' => $cumplimiento . '%',    'hint' => $realizadas . ' de ' . $solicitudes,        'source' => 'Solicitudes → Realizadas',   'cls' => $cumplimiento >= 85 ? 'good' : ($cumplimiento >= 70 ? 'warning' : 'danger')],
        ];

        $actions = [];
        if ($sinTarifa > 0)   $actions[] = ['severity' => 'critical', 'title' => 'Tarifas faltantes bloquean facturación', 'metric' => $sinTarifa . ' casos',       'owner' => 'Facturación',       'action' => 'Completar tarifa por código / categoría.'];
        if ($sinAgenda > 0)   $actions[] = ['severity' => 'critical', 'title' => 'Solicitudes sin agenda generan pérdida', 'metric' => $money($sinAgendaMonto),      'owner' => 'Agendamiento',      'action' => 'Agendar o cerrar causa de no conversión.'];
        if ($pendFact > 0)    $actions[] = ['severity' => 'warning',  'title' => 'Backlog realizado sin billing',          'metric' => $money($montoPend),            'owner' => 'Facturación',       'action' => 'Priorizar emisión de billing real.'];
        if ($realizadas - $informadas > 0) $actions[] = ['severity' => 'warning', 'title' => 'Informes fuera de SLA 48h', 'metric' => ($realizadas - $informadas) . ' pendientes', 'owner' => 'Lectura / informes', 'action' => 'Asignar lectura para liberar facturación.'];
        if ($pendPago > 0)    $actions[] = ['severity' => 'warning',  'title' => 'Cartera pendiente de cobro',             'metric' => $pendPago . ' registros',      'owner' => 'Cobranzas',         'action' => 'Revisar pagos y estados en cartera.'];

        // Serie diaria
        $serieLabels    = is_array($charts['serie_diaria']['labels']    ?? null) ? $charts['serie_diaria']['labels']    : [];
        $serieRealizados= is_array($charts['serie_diaria']['realizados'] ?? null) ? $charts['serie_diaria']['realizados'] : [];
        $serieDiaria = array_map(
            static fn(string $lbl, int $i): array => ['label' => $lbl, 'value' => $serieRealizados[$i] ?? 0],
            $serieLabels,
            array_keys($serieLabels)
        );

        // Agenda vs cierre — build monthly aggregation from serie_diaria if no monthly chart
        $agendaVsCierre = [];
        $cvr = $charts['citas_vs_realizados'] ?? [];
        if (!empty($cvr['labels'])) {
            // Not directly useful for trend; skip, React handles gracefully with empty array
        }

        // Top exámenes (mix_codigos)
        $mixLabels  = is_array($charts['mix_codigos']['labels'] ?? null) ? $charts['mix_codigos']['labels'] : [];
        $mixValues  = is_array($charts['mix_codigos']['values'] ?? null) ? $charts['mix_codigos']['values'] : [];
        $topExamenesRealizados = array_map(
            static fn(string $lbl, int $i): array => ['label' => $lbl, 'total' => $mixValues[$i] ?? 0],
            $mixLabels, array_keys($mixLabels)
        );

        // Top exámenes solicitados
        $topSolLabels = is_array($charts['top_examenes_solicitados']['labels'] ?? null) ? $charts['top_examenes_solicitados']['labels'] : [];
        $topSolValues = is_array($charts['top_examenes_solicitados']['values'] ?? null) ? $charts['top_examenes_solicitados']['values'] : [];
        $topExamenesSolicitados = array_map(
            static fn(string $lbl, int $i): array => ['label' => $lbl, 'total' => $topSolValues[$i] ?? 0],
            $topSolLabels, array_keys($topSolLabels)
        );

        // Top médicos solicitantes
        $docLabels = is_array($charts['top_doctores_solicitantes']['labels'] ?? null) ? $charts['top_doctores_solicitantes']['labels'] : [];
        $docValues = is_array($charts['top_doctores_solicitantes']['values'] ?? null) ? $charts['top_doctores_solicitantes']['values'] : [];
        $topMedicos = array_map(
            static fn(string $lbl, int $i): array => ['name' => $lbl, 'total' => $docValues[$i] ?? 0],
            $docLabels, array_keys($docLabels)
        );

        // Tráfico por día
        $tdLabels = is_array($charts['trafico_dia_semana']['labels'] ?? null) ? $charts['trafico_dia_semana']['labels'] : [];
        $tdValues = is_array($charts['trafico_dia_semana']['values'] ?? null) ? $charts['trafico_dia_semana']['values'] : [];
        $traficoPorDia = array_map(
            static fn(string $lbl, int $i): array => ['label' => $lbl, 'value' => $tdValues[$i] ?? 0],
            $tdLabels, array_keys($tdLabels)
        );

        // Trazabilidad
        $trazabilidad = [
            ['label' => 'Facturadas',     'total' => $facturadas,                         'color' => '#05825f'],
            ['label' => 'Pend. facturar', 'total' => $pendFact,                            'color' => '#ffa800'],
            ['label' => 'Pend. informe',  'total' => max(0, $realizadas - $informadas),    'color' => '#ee3158'],
            ['label' => 'En agenda',      'total' => max(0, $agendadas - $realizadas),     'color' => '#c8c9ee'],
        ];
        $trazabilidad = array_values(array_filter($trazabilidad, static fn(array $d): bool => $d['total'] > 0));

        // Rendimiento económico
        $rendimientoEconomico = [
            ['label' => 'Facturado',    'value' => (int)$facturadoReal, 'color' => '#05825f'],
            ['label' => 'Sin billing',  'value' => (int)$montoPend,     'color' => '#ffa800'],
            ['label' => 'No agendado',  'value' => (int)$sinAgendaMonto,'color' => '#ee3158'],
        ];

        // Backlog por categoría
        $pendPublico   = (int)($meta['pendientes_facturar_publico']  ?? 0);
        $pendPrivado   = (int)($meta['pendientes_facturar_privado']  ?? 0);
        $pendOtros     = (int)($meta['pendientes_facturar_otros']    ?? 0);
        $backlogCategoria = [
            ['label' => 'Pública',    'count' => $pendPublico, 'color' => '#5156be'],
            ['label' => 'Privada',    'count' => $pendPrivado, 'color' => '#3596f7'],
            ['label' => 'Particular', 'count' => $pendOtros,   'color' => '#05825f'],
            ['label' => 'Sin tarifa', 'count' => $sinTarifa,   'color' => '#ee3158'],
        ];

        // Reconciliación
        $factPublico  = (int)($meta['facturados_publico']  ?? 0);
        $factPrivado  = (int)($meta['facturados_privado']  ?? 0);
        $factOtros    = (int)($meta['facturados_otros']    ?? 0);
        $reconciliacion = [
            ['cat' => 'Pública',              'fact' => $factPublico, 'pend' => $pendPublico, 'estimado' => $pendPublico * $ticketProm],
            ['cat' => 'Privada',              'fact' => $factPrivado, 'pend' => $pendPrivado, 'estimado' => $pendPrivado * $ticketProm],
            ['cat' => 'Particular / otros',   'fact' => $factOtros,   'pend' => $pendOtros,   'estimado' => $pendOtros   * $ticketProm],
        ];

        // Por convenio — from analisis_seguro chart
        $convLabels = is_array($charts['analisis_seguro']['labels'] ?? null) ? $charts['analisis_seguro']['labels'] : [];
        $convValues = is_array($charts['analisis_seguro']['values'] ?? null) ? $charts['analisis_seguro']['values'] : [];
        $porConvenio = array_map(
            static fn(string $lbl, int $i): array => ['label' => $lbl, 'total' => $convValues[$i] ?? 0],
            $convLabels, array_keys($convLabels)
        );

        // Synth
        $synth = [
            ['label' => 'Estudios realizados',  'value' => number_format($realizadas), 'delta' => 0],
            ['label' => 'Facturado real',        'value' => $money($facturadoReal),     'delta' => 0],
            ['label' => 'SLA informe ≤48h',     'value' => $sla48 ?? '—', 'unit' => '%', 'delta' => 0, 'deltaSuffix' => ' pts'],
            ['label' => 'Cumplimiento al corte', 'value' => $cumplimiento, 'unit' => '%', 'delta' => 0, 'deltaSuffix' => ' pts'],
        ];

        $periodKey = $this->detectPeriodKey($start, $end);

        return [
            'unit'      => 'imagenes',
            'unitLabel' => 'Imágenes',
            'unitIcon'  => 'mdi-radiology-box-outline',
            'generatedAt' => now()->format('d/m/Y H:i'),
            'period'    => [
                'key'       => $periodKey,
                'label'     => $start . ' → ' . $end,
                'fromLabel' => (new DateTimeImmutable($start))->format('d/m/Y'),
                'toLabel'   => (new DateTimeImmutable($end))->format('d/m/Y'),
            ],
            'sede'      => ['id' => $sede ?: 'todas', 'label' => $sede ?: 'Todas las sedes'],
            'synth'     => $synth,
            'exec'      => [
                'flow'    => $flow,
                'links'   => $links,
                'kpis'    => $kpis,
                'actions' => array_slice($actions, 0, 5),
                'summary' => [
                    'oportunidad' => $money($montoPend + $sinAgendaMonto),
                    'arrastre'    => max(0, $agendadas - $realizadas) . ' agendas abiertas',
                    'sla'         => 'SLA ≤48h ' . ($sla48 !== null ? $sla48 . '%' : '—') . ($tatP90 !== null ? ' · TAT P90 ' . round((float)$tatP90) . 'h' : ''),
                ],
                'ledger'  => [
                    ['label' => 'Facturado',    'value' => $money($facturadoReal)],
                    ['label' => 'Sin billing',  'value' => $money($montoPend),     'tone' => 'warn'],
                    ['label' => 'No agendado',  'value' => $money($sinAgendaMonto),'tone' => 'danger'],
                ],
            ],
            'metrics'   => [
                'solicitudes'       => $solicitudes,
                'agendadas'         => $agendadas,
                'realizadas'        => $realizadas,
                'informadas'        => $informadas,
                'facturadas'        => $facturadas,
                'facturadoReal'     => $facturadoReal,
                'pendienteFacturarN' => $pendFact,
                'pendientePagoN'    => $pendPago,
                'cumplimiento'      => $cumplimiento,
                'sla48'             => $sla48,
                'tatProm'           => $tatProm !== null ? (float)$tatProm : null,
                'tatP90'            => $tatP90  !== null ? (float)$tatP90  : null,
                'ticketProm'        => $ticketProm,
                'pendientesSinTarifa' => $sinTarifa,
            ],
            'serieDiaria'           => $serieDiaria,
            'agendaVsCierre'        => $agendaVsCierre,
            'topExamenesRealizados' => $topExamenesRealizados,
            'topExamenesSolicitados'=> $topExamenesSolicitados,
            'topMedicos'            => $topMedicos,
            'traficoPorDia'         => $traficoPorDia,
            'porConvenio'           => $porConvenio,
            'reconciliacion'        => $reconciliacion,
            'backlogCategoria'      => $backlogCategoria,
            'trazabilidad'          => $trazabilidad,
            'rendimientoEconomico'  => $rendimientoEconomico,
        ];
    }
}
