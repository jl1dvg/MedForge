<?php

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\SettingsOptionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SolicitudesUiController
{
    private ?SettingsOptionResolver $settingsResolver = null;

    /**
     * @var array<int,array<string,string>>
     */
    private const KANBAN_COLUMNS = [
        ['slug' => 'recibida', 'label' => 'Recibida'],
        ['slug' => 'llamado', 'label' => 'Turno llamado'],
        ['slug' => 'revision-codigos', 'label' => 'Revisión códigos'],
        ['slug' => 'espera-documentos', 'label' => 'Documentación'],
        ['slug' => 'apto-oftalmologo', 'label' => 'Apto oftalmólogo'],
        ['slug' => 'apto-anestesia', 'label' => 'Apto anestesia'],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda'],
        ['slug' => 'programada', 'label' => 'Programada'],
        ['slug' => 'completado', 'label' => 'Completado'],
    ];

    /**
     * @var array<string,string>
     */
    private const DEFAULT_REALTIME_EVENTS = [
        'new_request' => 'kanban.nueva-solicitud',
        'status_updated' => 'kanban.estado-actualizado',
        'crm_updated' => 'crm.detalles-actualizados',
        'surgery_reminder' => 'recordatorio-cirugia',
        'surgery_precheck_24h' => 'recordatorio-cirugia-24h',
        'surgery_precheck_2h' => 'recordatorio-cirugia-2h',
        'preop_reminder' => 'recordatorio-preop',
        'postop_reminder' => 'recordatorio-postop',
        'post_consulta' => 'postconsulta',
        'exams_expiring' => 'alerta-examenes-por-vencer',
        'exam_reminder' => 'recordatorio-examen',
        'crm_task_reminder' => 'crm.task-reminder',
        'turnero_updated' => 'turnero.turno-actualizado',
        'whatsapp_handoff' => 'whatsapp.handoff',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $currentUser = LegacyCurrentUser::resolve($request);
        $realtimeConfig = $this->buildRealtimeConfig();

        return view('solicitudes.v2-index', [
            'pageTitle' => 'Solicitudes (Kanban)',
            'currentUser' => $currentUser,
            'kanbanColumns' => self::KANBAN_COLUMNS,
            'initialFilters' => [
                'search' => trim((string) $request->query('search', '')),
                'afiliacion' => trim((string) $request->query('afiliacion', '')),
                'afiliacion_categoria' => trim((string) $request->query('afiliacion_categoria', '')),
                'empresa_seguro' => trim((string) $request->query('empresa_seguro', '')),
                'sede' => trim((string) $request->query('sede', '')),
                'doctor' => trim((string) $request->query('doctor', '')),
                'prioridad' => trim((string) $request->query('prioridad', '')),
                'date_from' => trim((string) $request->query('date_from', '')),
                'date_to' => trim((string) $request->query('date_to', '')),
            ],
            'kanbanEndpoint' => '/v2/solicitudes/kanban-data',
            'actualizarEstadoEndpoint' => '/v2/solicitudes/actualizar-estado',
            'estadoEndpoint' => '/v2/solicitudes/api/estado',
            'realtimeConfig' => $realtimeConfig,
            'notificationStorageKey' => 'medf:notification-panel:u' . (int) ($currentUser['id'] ?? 0),
        ]);
    }

    public function indexV3(Request $request): View|RedirectResponse
    {
        $currentUser = LegacyCurrentUser::resolve($request);
        $realtimeConfig = $this->buildRealtimeConfig();

        $appConfig = [
            'kanbanColumns'             => self::KANBAN_COLUMNS,
            'kanbanEndpoint'            => '/v2/solicitudes/kanban-data',
            'actualizarEstadoEndpoint'  => '/v2/solicitudes/actualizar-estado',
            'estadoEndpoint'            => '/v2/solicitudes/api/estado',
            'realtimeConfig'            => $realtimeConfig,
            'initialFilters'            => [
                'search'              => trim((string) $request->query('search', '')),
                'afiliacion'          => trim((string) $request->query('afiliacion', '')),
                'doctor'              => trim((string) $request->query('doctor', '')),
                'prioridad'           => trim((string) $request->query('prioridad', '')),
                'sede'                => trim((string) $request->query('sede', '')),
                'date_from'           => trim((string) $request->query('date_from', '')),
                'date_to'             => trim((string) $request->query('date_to', '')),
            ],
        ];

        return view('solicitudes.v3-index', [
            'pageTitle' => 'Solicitudes V3',
            'currentUser' => $currentUser,
            'appConfig'   => $appConfig,
        ]);
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        return view('solicitudes.v2-dashboard', [
            'pageTitle' => 'Dashboard de Solicitudes v2',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'dashboardEndpoint' => '/v2/solicitudes/dashboard-data',
        ]);
    }

    public function turnero(Request $request): View|RedirectResponse
    {
        return view('solicitudes.v2-turnero', [
            'pageTitle' => 'Turnero Coordinación Quirúrgica',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'turneroEndpoint' => '/v2/solicitudes/turnero-data',
            'turneroRefreshMs' => 30000,
        ]);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     key: string,
     *     cluster: string,
     *     channel: string,
     *     event: string,
     *     desktop_notifications: bool,
     *     auto_dismiss_seconds: int,
     *     toast_auto_dismiss_seconds: int,
     *     panel_retention_days: int,
     *     events: array<string,string>,
     *     channels: array{email: bool, sms: bool, daily_summary: bool}
     * }
     */
    private function buildRealtimeConfig(): array
    {
        $options = $this->settingsOptions([
            'pusher_app_id',
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_cluster',
            'pusher_realtime_notifications',
            'desktop_notifications',
            'auto_dismiss_desktop_notifications_after',
            'notifications_toast_auto_dismiss_seconds',
            'notifications_panel_retention_days',
            'notifications_email_enabled',
            'notifications_sms_enabled',
            'notifications_daily_summary',
        ]);

        $appId = trim((string) ($options['pusher_app_id'] ?? ''));
        $appKey = trim((string) ($options['pusher_app_key'] ?? ''));
        $appSecret = trim((string) ($options['pusher_app_secret'] ?? ''));
        $cluster = trim((string) ($options['pusher_cluster'] ?? ''));
        $featureEnabled = ((string) ($options['pusher_realtime_notifications'] ?? '0')) === '1';

        return [
            'enabled' => $featureEnabled && $appId !== '' && $appKey !== '' && $appSecret !== '',
            'key' => $appKey,
            'cluster' => $cluster,
            'channel' => 'solicitudes-kanban',
            'event' => self::DEFAULT_REALTIME_EVENTS['new_request'],
            'desktop_notifications' => ((string) ($options['desktop_notifications'] ?? '0')) === '1',
            'auto_dismiss_seconds' => max(0, (int) ($options['auto_dismiss_desktop_notifications_after'] ?? 0)),
            'toast_auto_dismiss_seconds' => max(0, (int) ($options['notifications_toast_auto_dismiss_seconds'] ?? 4)),
            'panel_retention_days' => max(0, (int) ($options['notifications_panel_retention_days'] ?? 7)),
            'events' => self::DEFAULT_REALTIME_EVENTS,
            'channels' => [
                'email' => ((string) ($options['notifications_email_enabled'] ?? '0')) === '1',
                'sms' => ((string) ($options['notifications_sms_enabled'] ?? '0')) === '1',
                'daily_summary' => ((string) ($options['notifications_daily_summary'] ?? '0')) === '1',
            ],
        ];
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if ($this->settingsResolver === null) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver->getOptions($keys);
    }
}
