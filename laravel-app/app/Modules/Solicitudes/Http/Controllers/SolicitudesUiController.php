<?php

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SolicitudesUiController
{
    /**
     * @var array<int,array<string,string>>
     */
    private const KANBAN_COLUMNS = [
        ['slug' => 'recibida', 'label' => 'Recibida'],
        ['slug' => 'llamado', 'label' => 'Llamado'],
        ['slug' => 'revision-codigos', 'label' => 'Revisión códigos'],
        ['slug' => 'espera-documentos', 'label' => 'Documentación'],
        ['slug' => 'apto-oftalmologo', 'label' => 'Apto oftalmólogo'],
        ['slug' => 'apto-anestesia', 'label' => 'Apto anestesia'],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda'],
        ['slug' => 'programada', 'label' => 'Programada'],
        ['slug' => 'completado', 'label' => 'Completado'],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('solicitudes.v2-index', [
            'pageTitle' => 'Solicitudes (Kanban)',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'kanbanColumns' => self::KANBAN_COLUMNS,
            'initialFilters' => [
                'search' => trim((string) $request->query('search', '')),
                'afiliacion' => trim((string) $request->query('afiliacion', '')),
                'doctor' => trim((string) $request->query('doctor', '')),
                'prioridad' => trim((string) $request->query('prioridad', '')),
                'date_from' => trim((string) $request->query('date_from', '')),
                'date_to' => trim((string) $request->query('date_to', '')),
            ],
            'kanbanEndpoint' => '/v2/solicitudes/kanban-data',
            'actualizarEstadoEndpoint' => '/v2/solicitudes/actualizar-estado',
            'estadoEndpoint' => '/v2/solicitudes/api/estado',
        ]);
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('solicitudes.v2-dashboard', [
            'pageTitle' => 'Dashboard de Solicitudes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'dashboardEndpoint' => '/v2/solicitudes/dashboard-data',
        ]);
    }

    public function turnero(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('solicitudes.v2-turnero', [
            'pageTitle' => 'Turnero Coordinación Quirúrgica',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'turneroEndpoint' => '/v2/solicitudes/turnero-data',
            'turneroRefreshMs' => 30000,
        ]);
    }
}

