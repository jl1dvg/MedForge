<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardParityService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardUiController
{
    private DashboardParityService $service;

    public function __construct()
    {
        $this->service = new DashboardParityService();
    }

    public function index(Request $request, string $view = 'dashboard.v2'): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $startDate = trim((string) $request->query('start_date', ''));
        $endDate   = trim((string) $request->query('end_date', ''));
        $sede      = trim((string) $request->query('sede', ''));
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
            $uiPayload = $this->service->buildUiPayload($startDate, $endDate, $sede);
        } catch (\Throwable $e) {
            Log::error('dashboard.ui.summary.error', [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => Auth::id(),
            ]);
        }

        $currentUser = LegacyCurrentUser::resolve($request);

        return view($view, [
            'pageTitle' => 'Dashboard',
            'summaryEndpoint' => '/v2/dashboard/summary',
            'startDate' => $startDate,
            'endDate'   => $endDate,
            'sede'      => $sede,
            'currentUser' => $currentUser,
            ...$uiPayload,
        ]);
    }

    /**
     * Modern, single-screen "Centro de operaciones" redesign of the dashboard.
     * Delegates to index() with a v3 view name so both routes share the
     * same data pipeline; data wiring lives in DashboardParityService.
     */
    public function indexV3(Request $request): View|RedirectResponse
    {
        return $this->index($request, 'dashboard.v3');
    }

    /**
     * JSON endpoint for real-time polling from the V3 dashboard.
     * Returns only the V3-specific payload so the JS can diff and redraw panels.
     */
    public function dataV3(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $startDate = trim((string) $request->query('start_date', ''));
        $endDate   = trim((string) $request->query('end_date', ''));
        $sede      = trim((string) $request->query('sede', ''));

        try {
            $payload = $this->service->buildUiPayload($startDate, $endDate, $sede);
            return response()->json([
                'dashboard_v3' => $payload['dashboard_v3'] ?? [],
                'ts' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('dashboard.v3.data.error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
