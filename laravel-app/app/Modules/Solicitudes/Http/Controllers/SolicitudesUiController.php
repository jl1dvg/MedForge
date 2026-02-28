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
        if (!$this->isV2UiEnabled()) {
            return $this->redirectLegacy($request, '/solicitudes');
        }

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

        return $this->redirectLegacy($request, '/cirugias/dashboard');
    }

    public function turnero(Request $request): View|RedirectResponse
    {
        if (!$this->isV2UiEnabled()) {
            return $this->redirectLegacy($request, '/solicitudes/turnero');
        }

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

    private function redirectLegacy(Request $request, string $path): RedirectResponse
    {
        $queryString = trim((string) $request->getQueryString());
        if ($queryString !== '') {
            $path .= '?' . $queryString;
        }

        return redirect($path);
    }

    private function isV2UiEnabled(): bool
    {
        $rawFlag = $this->readFlagFromEnvFiles('SOLICITUDES_V2_UI_ENABLED');
        if ($rawFlag === null || trim($rawFlag) === '') {
            $raw = env('SOLICITUDES_V2_UI_ENABLED');
            if ($raw === null) {
                $raw = getenv('SOLICITUDES_V2_UI_ENABLED');
            }
            $rawFlag = $raw !== false && $raw !== null ? (string) $raw : null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    }

    private function readFlagFromEnvFiles(string $key): ?string
    {
        $paths = [
            base_path('../.env'),
            base_path('.env'),
        ];

        foreach ($paths as $path) {
            if (!is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$candidateKey, $value] = array_pad(explode('=', $line, 2), 2, '');
                if (trim($candidateKey) !== $key) {
                    continue;
                }

                return trim($value, " \t\n\r\0\x0B\"'");
            }
        }

        return null;
    }
}
