<?php

namespace Modules\Cirugias\Controllers;

use Core\BaseController;
use DateTimeImmutable;
use DateTimeInterface;
use Modules\Cirugias\Services\CirugiasDashboardService;
use PDO;

class CirugiasDashboardController extends BaseController
{
    private CirugiasDashboardService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new CirugiasDashboardService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission([
            'cirugias.dashboard.view',
            'administrativo',
            'admin.usuarios.manage',
            'admin.roles.manage',
            'admin.usuarios',
            'admin.roles',
        ]);

        $dateRange = $this->resolveDateRange();
        $startSql = $dateRange['start']->format('Y-m-d 00:00:00');
        $endSql = $dateRange['end']->format('Y-m-d 23:59:59');

        $totalCirugias = $this->service->getTotalCirugias($startSql, $endSql);
        $sinFacturar = $this->service->getCirugiasSinFacturar($startSql, $endSql);
        $duracionPromedio = $this->service->getDuracionPromedioMinutos($startSql, $endSql);
        $estadoProtocolos = $this->service->getEstadoProtocolos($startSql, $endSql);
        $cirugiasPorMes = $this->service->getCirugiasPorMes($startSql, $endSql);
        $topProcedimientos = $this->service->getTopProcedimientos($startSql, $endSql);
        $topCirujanos = $this->service->getTopCirujanos($startSql, $endSql);
        $cirugiasPorConvenio = $this->service->getCirugiasPorConvenio($startSql, $endSql);
        $programacionKpis = $this->service->getProgramacionKpis($startSql, $endSql);
        $reingresoMismoDiagnostico = $this->service->getReingresoMismoDiagnostico($startSql, $endSql);

        $data = [
            'pageTitle' => 'Dashboard quirúrgico',
            'date_range' => $this->formatDateRangeForView($dateRange),
            'total_cirugias' => $totalCirugias,
            'cirugias_sin_facturar' => $sinFacturar,
            'duracion_promedio' => $this->formatMinutes($duracionPromedio),
            'estado_protocolos' => $estadoProtocolos,
            'cirugias_por_mes' => $cirugiasPorMes,
            'top_procedimientos' => $topProcedimientos,
            'top_cirujanos' => $topCirujanos,
            'cirugias_por_convenio' => $cirugiasPorConvenio,
            'programacion_kpis' => $programacionKpis,
            'reingreso_mismo_diagnostico' => $reingresoMismoDiagnostico,
        ];

        $this->render('modules/Cirugias/views/dashboard.php', $data);
    }

    private function resolveDateRange(): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-30 days');
        $end = $today;

        if (!empty($_GET['start_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $_GET['start_date']);
            if ($parsed instanceof DateTimeImmutable) {
                $start = $parsed;
            }
        }

        if (!empty($_GET['end_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $_GET['end_date']);
            if ($parsed instanceof DateTimeImmutable) {
                $end = $parsed;
            }
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function formatDateRangeForView(array $range): array
    {
        $start = $range['start'];
        $end = $range['end'];

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        ];
    }

    private function formatMinutes(float $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        $totalMinutes = (int) round($minutes);
        $hours = intdiv($totalMinutes, 60);
        $mins = $totalMinutes % 60;

        return sprintf('%dh %02dm', $hours, $mins);
    }
}
