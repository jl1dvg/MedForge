<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardUiController
{
    private DashboardParityService $service;

    public function __construct()
    {
        $this->service = new DashboardParityService();
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));
        $uiPayload = [
            'summary' => [
                'data' => [],
                'meta' => ['date_range' => ['start' => '', 'end' => '', 'label' => '']],
            ],
            'date_range' => ['start' => '', 'end' => '', 'label' => ''],
            'cirugias_recientes' => [],
            'plantillas' => [],
            'diagnosticos_frecuentes' => [],
            'solicitudes_quirurgicas' => ['solicitudes' => [], 'total' => 0],
            'doctores_top' => [],
            'estadisticas_afiliacion' => ['afiliaciones' => ['No data'], 'totales' => [0]],
            'kpi_cards' => [],
            'ai_summary' => [
                'provider' => '',
                'provider_configured' => false,
                'features' => ['consultas_enfermedad' => false, 'consultas_plan' => false],
            ],
        ];

        try {
            $uiPayload = $this->service->buildUiPayload($startDate, $endDate);
        } catch (\Throwable $e) {
            Log::error('dashboard.ui.summary.error', [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => LegacySessionAuth::userId($request),
            ]);
        }

        return view('dashboard.v2', [
            'summaryEndpoint' => '/v2/dashboard/summary',
            'startDate' => $startDate,
            'endDate' => $endDate,
            ...$uiPayload,
        ]);
    }
}
