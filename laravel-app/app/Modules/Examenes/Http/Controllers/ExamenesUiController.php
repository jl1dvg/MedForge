<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ExamenesReportingService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExamenesUiController
{
    private ExamenesReportingService $reportingService;

    /**
     * @var array<string,array{label:string,color:string}>
     */
    private const KANBAN_COLUMNS = [
        'recibido' => ['label' => 'Recibido', 'color' => 'primary'],
        'llamado' => ['label' => 'Llamado', 'color' => 'warning'],
        'revision-cobertura' => ['label' => '⚠️ Cobertura', 'color' => 'info'],
        'parcial' => ['label' => '🌓 Parcial', 'color' => 'warning'],
        'listo-para-agenda' => ['label' => '✅ Listo', 'color' => 'dark'],
        'completado' => ['label' => 'Completado', 'color' => 'secondary'],
    ];

    /**
     * @var array<int,array{slug:string,label:string,order:int,column:string,required:bool}>
     */
    private const KANBAN_STAGES = [
        ['slug' => 'recibido', 'label' => 'Recibido', 'order' => 10, 'column' => 'recibido', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'revision-cobertura', 'label' => '⚠ Revisión de cobertura', 'order' => 30, 'column' => 'revision-cobertura', 'required' => true],
        ['slug' => 'parcial', 'label' => '🌓 Parcial', 'order' => 35, 'column' => 'parcial', 'required' => false],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 40, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'completado', 'label' => 'Completado', 'order' => 50, 'column' => 'completado', 'required' => false],
    ];

    /**
     * @var array<string,string>
     */
    private const REALTIME_EVENTS = [
        'new_request' => 'kanban.nueva-examen',
        'status_updated' => 'kanban.estado-actualizado',
        'crm_updated' => 'crm.detalles-actualizados',
        'exam_reminder' => 'recordatorio-examen',
        'crm_task_reminder' => 'crm.task-reminder',
        'turnero_updated' => 'turnero.turno-actualizado',
    ];

    public function __construct()
    {
        $this->reportingService = new ExamenesReportingService();
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('examenes.v2-index', [
            'pageTitle' => 'Solicitudes de Exámenes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'kanbanColumns' => self::KANBAN_COLUMNS,
            'kanbanStages' => self::KANBAN_STAGES,
            'realtime' => $this->buildRealtimeConfig(),
            'reporting' => $this->reportingService->reportingConfig(),
            'forceV2ReadsEnabled' => true,
            'forceV2WritesEnabled' => true,
        ]);
    }

    public function turnero(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('examenes.v2-turnero', [
            'pageTitle' => 'Turnero de Exámenes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'realtime' => $this->buildRealtimeConfig(),
            'turneroContext' => 'Coordinación de Exámenes',
            'turneroEmptyMessage' => 'No hay pacientes en cola para coordinación de exámenes.',
            'forceV2ReadsEnabled' => true,
            'forceV2WritesEnabled' => true,
        ]);
    }

    /**
     * @return array{
     *     enabled:bool,
     *     key:string,
     *     cluster:string,
     *     channel:string,
     *     event:string,
     *     desktop_notifications:bool,
     *     auto_dismiss_seconds:int,
     *     toast_auto_dismiss_seconds:int,
     *     panel_retention_days:int,
     *     events:array<string,string>,
     *     channels:array{email:bool,sms:bool,daily_summary:bool}
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
            'channel' => 'examenes-kanban',
            'event' => self::REALTIME_EVENTS['new_request'],
            'desktop_notifications' => ((string) ($options['desktop_notifications'] ?? '0')) === '1',
            'auto_dismiss_seconds' => max(0, (int) ($options['auto_dismiss_desktop_notifications_after'] ?? 0)),
            'toast_auto_dismiss_seconds' => max(0, (int) ($options['notifications_toast_auto_dismiss_seconds'] ?? 4)),
            'panel_retention_days' => max(0, (int) ($options['notifications_panel_retention_days'] ?? 7)),
            'events' => self::REALTIME_EVENTS,
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
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        try {
            $rows = DB::select(
                'SELECT name, value FROM settings WHERE name IN (' . $placeholders . ')',
                array_values($keys)
            );
        } catch (Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $name = (string) ($row->name ?? '');
            if ($name === '') {
                continue;
            }
            $options[$name] = (string) ($row->value ?? '');
        }

        return $options;
    }
}
