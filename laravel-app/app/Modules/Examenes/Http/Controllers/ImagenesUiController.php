<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ImagenesUiService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
        $payload  = $this->service->imagenesDashboard($request->query());
        $db       = $payload['dashboard'];
        $meta     = $db['meta'] ?? [];
        $charts   = $db['charts'] ?? [];
        $filters  = $payload['filters'];

        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate   = $filters['end_date']   ?? date('Y-m-d');
        $sedeFilter = $filters['sede'] ?? '';

        $sedeOptions = $payload['sedeOptions'] ?? [['value' => '', 'label' => 'Todas las sedes']];
        $sedeLabel   = 'Todas las sedes';
        foreach ($sedeOptions as $opt) {
            if ($opt['value'] === $sedeFilter) { $sedeLabel = $opt['label']; break; }
        }

        $realizados       = (int)($meta['solicitudes_realizadas'] ?? 0);
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
        $sla48Pct         = null;
        foreach ($db['cards'] ?? [] as $card) {
            if (($card['label'] ?? '') === 'SLA informe <= 48h') {
                $raw = $card['value'] ?? '—';
                if ($raw !== '—') { $sla48Pct = (float)str_replace('%', '', $raw); }
                break;
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

        // Insurance breakdown
        $insLabels = $charts['analisis_seguro']['labels'] ?? [];
        $insValues = $charts['analisis_seguro']['values'] ?? [];
        $porConvenio = [];
        foreach ($insLabels as $j => $lbl) {
            $porConvenio[] = ['label' => $lbl, 'total' => $insValues[$j] ?? 0];
        }

        // Exec map
        $fmtMoney = fn(float $v) => '$' . number_format($v, 0, '.', ',');
        $exec = [
            'kpis' => [
                ['cls' => 'primary',  'source' => 'Solicitudes', 'label' => 'Solicitados',          'value' => number_format($solicTotal),    'hint' => 'Origen de la demanda'],
                ['cls' => 'blue',     'source' => 'Agenda',      'label' => 'Realizados',            'value' => number_format($realizados),    'hint' => 'Atendidos en el período'],
                ['cls' => 'success',  'source' => 'Billing',     'label' => 'Facturados',            'value' => number_format($facturados),    'hint' => 'Con billing emitido'],
                ['cls' => 'warning',  'source' => 'Backlog',     'label' => 'Pendiente facturar',    'value' => number_format($pendFact),      'hint' => 'Sin billing aún'],
                ['cls' => 'money',    'source' => 'Producción',  'label' => 'Facturado real',        'value' => $fmtMoney($produccionFact),    'hint' => 'Monto cobrado'],
                ['cls' => 'money',    'source' => 'Oportunidad', 'label' => 'Pendiente estimado',    'value' => $fmtMoney($montoPend),         'hint' => 'Estimado por código'],
            ],
            'flow' => [
                ['key' => 'sol',   'cls' => 'neutral', 'label' => 'Solicitudes', 'value' => $solicTotal,   'context' => 'Origen de demanda',
                 'leak' => ['label' => 'Sin agenda', 'count' => (int)($meta['solicitudes_sin_agenda'] ?? 0), 'amount' => 0]],
                ['key' => 'real',  'cls' => 'primary', 'label' => 'Realizados',  'value' => $realizados,   'context' => 'Atendidos',
                 'leak' => ['label' => 'Ausentes/cancel.', 'count' => (int)($meta['solicitudes_ausentes'] ?? 0), 'amount' => 0]],
                ['key' => 'fact',  'cls' => 'success', 'label' => 'Facturados',  'value' => $facturados,   'context' => 'Billing emitido',
                 'leak' => ['label' => 'Pend. facturar', 'count' => $pendFact, 'amount' => 0]],
                ['key' => 'cobro', 'cls' => 'money',   'label' => 'Cobrado est.','value' => (int)round($produccionFact), 'context' => 'Producción real', 'leak' => null],
            ],
            'links' => [
                ['pct' => $solicTotal > 0 ? round($realizados / $solicTotal * 100) : 0],
                ['pct' => $realizados > 0 ? round($facturados / $realizados * 100) : 0],
                ['pct' => 100],
            ],
            'summary' => [
                'oportunidad' => $fmtMoney($produccionFact + $montoPend),
                'arrastre'    => number_format($pendFact) . ' estudios',
                'sla'         => $sla48Pct !== null ? round($sla48Pct) . '% ≤48h' : '—',
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
            ],
            'produccionMensual' => $produccionMensual,
            'trazabilidad'      => $trazabilidad,
            'topExamenes'       => $topExamenes,
            'topDoctores'       => $topDoctores,
            'porConvenio'       => $porConvenio,
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
