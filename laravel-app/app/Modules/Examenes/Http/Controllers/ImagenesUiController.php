<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ImagenesUiService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImagenesUiController
{
    private ImagenesUiService $service;

    public function __construct()
    {
        $this->service = new ImagenesUiService();
    }

    public function realizadas(Request $request): View
    {
        $payload = $this->service->imagenesRealizadas($request->query());

        return view('examenes.v2-imagenes-realizadas', [
            'pageTitle' => 'Imágenes · Procedimientos proyectados',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'imagenesRealizadas' => $payload['rows'],
            'filters' => $payload['filters'],
            'afiliacionOptions' => $payload['afiliacionOptions'],
            'afiliacionCategoriaOptions' => $payload['afiliacionCategoriaOptions'],
            'seguroOptions' => $payload['seguroOptions'],
        ]);
    }

    public function dashboard(Request $request): View
    {
        $payload = $this->service->imagenesDashboard($request->query());

        return view('examenes.v2-imagenes-dashboard', [
            'pageTitle' => 'Dashboard de Imágenes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'filters' => $payload['filters'],
            'dashboard' => $payload['dashboard'],
            'rows' => $payload['rows'],
            'afiliacionOptions' => $payload['afiliacionOptions'],
            'afiliacionCategoriaOptions' => $payload['afiliacionCategoriaOptions'],
            'seguroOptions' => $payload['seguroOptions'],
            'sedeOptions' => $payload['sedeOptions'],
        ]);
    }

    public function dashboardReport(Request $request): View
    {
        $query = $request->query();
        // El toolbar del reporte ejecutivo envía start_date/end_date; buildFilters() espera fecha_inicio/fecha_fin.
        if (!empty($query['start_date']) && empty($query['fecha_inicio'])) {
            $query['fecha_inicio'] = $query['start_date'];
        }
        if (!empty($query['end_date']) && empty($query['fecha_fin'])) {
            $query['fecha_fin'] = $query['end_date'];
        }

        $payload  = $this->service->buildExecutiveReportPayload($query);
        $db       = $payload['dashboard'];
        $meta     = $db['meta'] ?? [];
        $charts   = $db['charts'] ?? [];
        $filters  = $payload['filters'];
        $tiempoAcceso = $payload['tiempoAcceso'] ?? [];

        Log::info('imagenes.executive_report.timings', [
            'timings' => $payload['timings'] ?? [],
            'row_count' => $payload['rowCount'] ?? 0,
            'tiempo_acceso_ms' => $payload['timings']['tiempo_acceso'] ?? null,
            'tiempo_acceso_sample' => $tiempoAcceso['muestra'] ?? 0,
            'tiempo_acceso_confiable' => $tiempoAcceso['fuente_confiable'] ?? 0,
            'tiempo_acceso_fallback' => $tiempoAcceso['fuente_fallback'] ?? 0,
        ]);

        $startDate = $filters['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate   = $filters['fecha_fin']   ?? date('Y-m-d');
        $sedeFilter = $filters['sede'] ?? '';

        $sedeOptions = $payload['sedeOptions'] ?? [['value' => '', 'label' => 'Todas las sedes']];
        $sedeLabel   = 'Todas las sedes';
        foreach ($sedeOptions as $opt) {
            if ($opt['value'] === $sedeFilter) { $sedeLabel = $opt['label']; break; }
        }

        $realizados           = (int)($meta['solicitudes_realizadas'] ?? 0);
        $solicitudesFacturadas = (int)($meta['solicitudes_facturadas'] ?? 0);
        $facturados       = (int)($meta['facturados'] ?? 0);
        $pendFact         = (int)($meta['pendientes_facturar'] ?? 0);
        $pendPago         = (int)($meta['pendientes_pago'] ?? 0);
        $produccionFact   = (float)($meta['produccion_facturada'] ?? 0);
        $montoPend        = (float)($meta['monto_pendiente_estimado'] ?? 0);
        $ticket           = (float)($meta['ticket_promedio_facturado'] ?? 0);
        $tatProm          = $meta['tat_promedio_horas'] ?? null;
        $tatMed           = $meta['tat_mediana_horas'] ?? null;
        $tatP90           = $meta['tat_p90_horas'] ?? null;
        $solicTotal       = (int)($meta['solicitudes_total'] ?? 0);
        $solAgendadas     = (int)($meta['solicitudes_agendadas'] ?? 0);
        $cumplPct         = $meta['cumplimiento_realizacion_al_corte_pct'] ?? null;
        $sinAgenda        = (int)($meta['solicitudes_sin_agenda'] ?? 0);
        $sinAgendaMonto   = (float)($meta['solicitudes_sin_agenda_monto_estimado'] ?? 0);
        $conAgendaNoReal  = (int)($meta['solicitudes_ausentes'] ?? 0) + (int)($meta['solicitudes_canceladas'] ?? 0);
        $arrastreCorte    = max(0, $solicTotal - $realizados);
        $accesoP90        = $tiempoAcceso['p90_dias'] ?? null;
        $accesoMuestra    = (int)($tiempoAcceso['muestra'] ?? 0);
        $sla48Pct         = null;
        $atendidos        = 0;
        foreach ($db['cards'] ?? [] as $card) {
            if (($card['label'] ?? '') === 'SLA informe <= 48h') {
                $raw = $card['value'] ?? '—';
                if ($raw !== '—') { $sla48Pct = (float)str_replace('%', '', $raw); }
            }
            if (($card['label'] ?? '') === 'Atendidos') {
                $atendidos = (int)($card['value'] ?? 0);
            }
        }

        // Monthly trend from serie_diaria (aggregate by month)
        $serieLabels   = $charts['serie_diaria']['labels']    ?? [];
        $serieReal     = $charts['serie_diaria']['realizados'] ?? [];
        $serieInform   = $charts['serie_diaria']['informados'] ?? [];
        $monthlyBucket = [];
        foreach ($serieLabels as $i => $dateStr) {
            $month = substr((string)$dateStr, 0, 7);
            if (!isset($monthlyBucket[$month])) $monthlyBucket[$month] = ['realizados' => 0, 'informados' => 0];
            $monthlyBucket[$month]['realizados'] += (int)($serieReal[$i] ?? 0);
            $monthlyBucket[$month]['informados']  += (int)($serieInform[$i] ?? 0);
        }
        $produccionMensual = array_map(
            fn($m, $v) => ['label' => $m, 'realizados' => $v['realizados'], 'informados' => $v['informados']],
            array_keys($monthlyBucket), array_values($monthlyBucket)
        );

        // Top exámenes
        $topExLabels = $charts['top_examenes_solicitados']['labels'] ?? [];
        $topExValues = $charts['top_examenes_solicitados']['values'] ?? [];
        $topExamenes = [];
        foreach ($topExLabels as $j => $lbl) {
            $topExamenes[] = ['label' => $lbl, 'total' => $topExValues[$j] ?? 0];
        }

        // Top doctores solicitantes
        $topDocLabels = $charts['top_doctores_solicitantes']['labels'] ?? [];
        $topDocValues = $charts['top_doctores_solicitantes']['values'] ?? [];
        $topDoctores  = [];
        foreach ($topDocLabels as $j => $lbl) {
            $topDoctores[] = ['label' => $lbl, 'total' => $topDocValues[$j] ?? 0];
        }

        // Trazabilidad (donut)
        $trazColors = ['#5156be', '#05825f', '#d59623', '#d34b5b'];
        $trazLabels = $charts['trazabilidad']['labels'] ?? ['Atendidos', 'Facturados', 'Pendiente pago', 'Cancelados'];
        $trazValues = $charts['trazabilidad']['values'] ?? [0, 0, 0, 0];
        $trazabilidad = array_map(
            fn($l, $v, $c) => ['label' => $l, 'total' => $v, 'color' => $c],
            $trazLabels, $trazValues, $trazColors
        );

        // Insurance breakdown (legacy, ya no se renderiza en Sección 04; se mantiene por si otra vista lo consume)
        $insLabels = $charts['analisis_seguro']['labels'] ?? [];
        $insValues = $charts['analisis_seguro']['values'] ?? [];
        $porConvenio = [];
        foreach ($insLabels as $j => $lbl) {
            $porConvenio[] = ['label' => $lbl, 'total' => $insValues[$j] ?? 0];
        }

        // Rentabilidad y oportunidad por convenio (Sección 04)
        $rentConvenioLabels = $charts['rentabilidad_convenio']['labels'] ?? [];
        $rentConvenioProduccion = $charts['rentabilidad_convenio']['produccion'] ?? [];
        $rentConvenioOportunidad = $charts['rentabilidad_convenio']['oportunidad'] ?? [];
        $produccionVsOportunidad = [];
        foreach ($rentConvenioLabels as $j => $lbl) {
            $produccionVsOportunidad[] = [
                'label' => $lbl,
                'produccion' => (float)($rentConvenioProduccion[$j] ?? 0),
                'oportunidad' => (float)($rentConvenioOportunidad[$j] ?? 0),
            ];
        }
        $convenioLider = ['label' => '—', 'produccion' => 0.0];
        foreach ($produccionVsOportunidad as $item) {
            if ($item['produccion'] > $convenioLider['produccion']) {
                $convenioLider = ['label' => $item['label'], 'produccion' => $item['produccion']];
            }
        }

        // Exámenes con mayor oportunidad (Sección 04)
        $oportExLabels = $charts['top_examenes_oportunidad']['labels'] ?? [];
        $oportExValues = $charts['top_examenes_oportunidad']['values'] ?? [];
        $examenesOportunidad = [];
        foreach ($oportExLabels as $j => $lbl) {
            $examenesOportunidad[] = ['label' => $lbl, 'total' => (float)($oportExValues[$j] ?? 0)];
        }

        // Exec map
        $fmtMoney = fn(float $v) => '$' . number_format($v, 0, '.', ',');
        $exec = [
            'kpis' => [
                ['cls' => 'primary',  'source' => 'Solicitudes', 'label' => 'Solicitados',          'value' => number_format($solicTotal),    'hint' => 'Origen de la demanda'],
                ['cls' => 'blue',     'source' => 'Agenda',      'label' => 'Atendidos',             'value' => number_format($atendidos),     'hint' => 'Producción real ejecutada'],
                ['cls' => 'success',  'source' => 'Billing',     'label' => 'Facturados',            'value' => number_format($facturados),    'hint' => 'Con billing emitido'],
                ['cls' => 'warning',  'source' => 'Backlog',     'label' => 'Pendiente facturar',    'value' => number_format($pendFact),      'hint' => 'Sin billing aún'],
                ['cls' => 'money',    'source' => 'Producción',  'label' => 'Facturado real',        'value' => $fmtMoney($produccionFact),    'hint' => 'Monto cobrado'],
                ['cls' => 'money',    'source' => 'Oportunidad', 'label' => 'Pendiente estimado',    'value' => $fmtMoney($montoPend),         'hint' => 'Estimado por código'],
            ],
            'flow' => [
                ['key' => 'sol',   'cls' => 'neutral', 'label' => 'Solicitudes', 'value' => $solicTotal,   'context' => 'Origen de demanda',
                 'leak' => ['label' => 'Sin agenda', 'count' => (int)($meta['solicitudes_sin_agenda'] ?? 0), 'amount' => 0]],
                ['key' => 'real',  'cls' => 'primary', 'label' => 'Realizadas',  'value' => $realizados,   'context' => 'Solicitudes ejecutadas',
                 'leak' => ['label' => 'Ausentes/cancel.', 'count' => (int)($meta['solicitudes_ausentes'] ?? 0) + (int)($meta['solicitudes_canceladas'] ?? 0), 'amount' => 0]],
                ['key' => 'fact',  'cls' => 'success', 'label' => 'Facturadas',  'value' => $solicitudesFacturadas, 'context' => 'Solicitudes con billing emitido',
                 'leak' => ['label' => 'Realizadas sin facturar', 'count' => max(0, $realizados - $solicitudesFacturadas), 'amount' => 0]],
            ],
            'links' => [
                ['pct' => $solicTotal > 0 ? round($realizados / $solicTotal * 100) : 0],
                ['pct' => $realizados > 0 ? round($solicitudesFacturadas / $realizados * 100) : 0],
            ],
            'summary' => [
                // Compatibilidad legacy (no usados cuando 'rows' está presente).
                'oportunidad' => $fmtMoney($sinAgendaMonto),
                'arrastre'    => number_format($arrastreCorte) . ' solicitudes',
                'sla'         => $sla48Pct !== null ? round($sla48Pct) . '% ≤48h' : '—',
                'rows' => [
                    ['icon' => 'mdi-cash-multiple', 'label' => 'Oportunidad estimada', 'value' => $fmtMoney($sinAgendaMonto), 'hint' => 'Solicitudes sin agenda valorizadas'],
                    ['icon' => 'mdi-progress-clock', 'label' => 'Arrastre al corte', 'value' => number_format($arrastreCorte) . ' solicitudes', 'hint' => 'Solicitudes aún no concretadas'],
                    ['icon' => 'mdi-timer-sand', 'label' => 'Acceso al examen', 'value' => $accesoP90 !== null ? round($accesoP90) . ' días' : '—', 'hint' => $accesoP90 !== null ? 'P90 · ' . number_format($accesoMuestra) . ' casos analizados · 90% de los pacientes accede en ≤' . round($accesoP90) . ' días' : 'Sin datos suficientes'],
                    ['icon' => 'mdi-calendar-remove', 'label' => 'Sin agenda', 'value' => number_format($sinAgenda) . ' solicitudes', 'hint' => 'Aún no agendadas'],
                    ['icon' => 'mdi-account-cancel', 'label' => 'Agendadas no realizadas', 'value' => number_format($conAgendaNoReal) . ' solicitudes', 'hint' => 'Ausentes o canceladas'],
                ],
            ],
            'actions' => [
                ['severity' => 'high',   'title' => 'Cerrar backlog de facturación', 'metric' => number_format($pendFact) . ' estudios', 'owner' => 'Billing', 'action' => 'Emitir billing para recuperar ' . $fmtMoney($montoPend)],
                ['severity' => 'medium', 'title' => 'Reducir TAT de informe',        'metric' => ($tatP90 ? round($tatP90) . 'h P90' : '—'),  'owner' => 'Radiología', 'action' => 'Objetivo ≤48h SLA (actual: ' . ($sla48Pct !== null ? round($sla48Pct) . '%' : '—') . ')'],
                ['severity' => 'medium', 'title' => 'Resolver pendientes de pago',   'metric' => number_format($pendPago) . ' registros',     'owner' => 'Tesorería', 'action' => 'Seguimiento de cartera facturada sin cobro'],
            ],
            'ledger' => [
                ['label' => 'Producción facturada',    'value' => $fmtMoney($produccionFact)],
                ['label' => 'Pendiente estimado',       'value' => $fmtMoney($montoPend),       'tone' => 'warn'],
                ['label' => 'Ticket promedio',          'value' => $fmtMoney($ticket)],
                ['label' => 'Pendiente de pago',        'value' => number_format($pendPago) . ' reg.', 'tone' => $pendPago > 50 ? 'danger' : ''],
            ],
        ];

        $report = [
            'unit'         => 'imagenes',
            'unitLabel'    => 'Imágenes',
            'unitIcon'     => 'mdi-radiology-box',
            'generatedAt'  => now()->format('d/m/Y H:i'),
            'period'       => ['label' => "$startDate → $endDate", 'fromLabel' => date('d/m/Y', strtotime($startDate)), 'toLabel' => date('d/m/Y', strtotime($endDate))],
            'sede'         => ['label' => $sedeLabel],
            'synth'        => [
                ['label' => 'Realizados',        'value' => number_format($realizados),         'delta' => null],
                ['label' => 'Facturados',        'value' => number_format($facturados),         'delta' => null],
                ['label' => 'Producción fact.',  'value' => $fmtMoney($produccionFact),         'delta' => null],
                ['label' => 'SLA informe ≤48h',  'value' => $sla48Pct !== null ? round($sla48Pct) . '%' : '—', 'delta' => null],
            ],
            'exec'         => $exec,
            'metrics'      => [
                'realizados'      => $realizados,
                'facturados'      => $facturados,
                'pendFact'        => $pendFact,
                'pendPago'        => $pendPago,
                'solicTotal'      => $solicTotal,
                'solAgendadas'    => $solAgendadas,
                'cumplPct'        => $cumplPct,
                'produccionFact'  => $produccionFact,
                'montoPend'       => $montoPend,
                'ticket'          => $ticket,
                'tatProm'         => $tatProm,
                'tatMed'          => $tatMed,
                'tatP90'          => $tatP90,
                'sla48Pct'        => $sla48Pct,
                'arrastreCorte'   => $arrastreCorte,
                'sinAgendaMonto'  => $sinAgendaMonto,
            ],
            'produccionMensual' => $produccionMensual,
            'trazabilidad'      => $trazabilidad,
            'topExamenes'       => $topExamenes,
            'topDoctores'       => $topDoctores,
            'porConvenio'       => $porConvenio,
            'produccionVsOportunidad' => $produccionVsOportunidad,
            'convenioLider'           => $convenioLider,
            'examenesOportunidad'     => $examenesOportunidad,
        ];

        return view('examenes.v2-imagenes-dashboard-report', [
            'report'      => $report,
            'sedeOptions' => $sedeOptions,
            'startDate'   => $startDate,
            'endDate'     => $endDate,
            'sedeFilter'  => $sedeFilter,
        ]);
    }
}
