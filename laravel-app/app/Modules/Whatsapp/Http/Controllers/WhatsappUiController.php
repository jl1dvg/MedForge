<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use App\Modules\Whatsapp\Services\ConversationReadService;
use App\Modules\Whatsapp\Services\CampaignService;
use App\Modules\Whatsapp\Services\FlowAiAgentPreviewService;
use App\Modules\Whatsapp\Services\FlowmakerService;
use App\Modules\Whatsapp\Services\KnowledgeBaseService;
use App\Modules\Whatsapp\Services\KpiDashboardService;
use App\Modules\Whatsapp\Services\ProductivityToolkitService;
use App\Modules\Whatsapp\Services\TemplateCatalogService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Throwable;

class WhatsappUiController
{
    private const REALTIME_EVENTS = [
        'inbound_message' => 'whatsapp.inbound-message',
        'conversation_updated' => 'whatsapp.conversation-updated',
    ];

    public function __construct(
        private readonly ConversationReadService $conversationReadService = new \App\Modules\Whatsapp\Services\ConversationReadService(),
        private readonly ConversationOpsService $conversationOpsService = new \App\Modules\Whatsapp\Services\ConversationOpsService(),
        private readonly CampaignService $campaignService = new \App\Modules\Whatsapp\Services\CampaignService(),
        private readonly TemplateCatalogService $templateCatalogService = new \App\Modules\Whatsapp\Services\TemplateCatalogService(),
        private readonly KpiDashboardService $kpiDashboardService = new \App\Modules\Whatsapp\Services\KpiDashboardService(),
        private readonly FlowmakerService $flowmakerService = new \App\Modules\Whatsapp\Services\FlowmakerService(),
        private readonly FlowAiAgentPreviewService $aiAgentPreviewService = new \App\Modules\Whatsapp\Services\FlowAiAgentPreviewService(),
        private readonly KnowledgeBaseService $knowledgeBaseService = new \App\Modules\Whatsapp\Services\KnowledgeBaseService(),
        private readonly ProductivityToolkitService $productivityToolkitService = new \App\Modules\Whatsapp\Services\ProductivityToolkitService(),
    ) {
    }

    public function chat(Request $request): View|Factory
    {
        $currentUser = $this->resolveCurrentUser();
        $permissions = $this->resolvePermissions();
        $selectedConversationId = max(0, (int) $request->query('conversation', 0));
        $filter = trim((string) $request->query('filter', 'all'));
        $search = trim((string) $request->query('search', ''));
        $selectedAgentId = $this->nullableIntQuery($request, 'agent_id');
        $selectedRoleId = $this->nullableIntQuery($request, 'role_id');
        $dateFrom = $this->nullableDateQuery($request, 'date_from');
        $dateTo = $this->nullableDateQuery($request, 'date_to');
        $canSupervise = in_array('administrativo', $permissions, true)
            || in_array('whatsapp.manage', $permissions, true)
            || in_array('whatsapp.chat.supervise', $permissions, true);
        $canOperateConversation = $canSupervise
            || in_array('whatsapp.chat.assign', $permissions, true)
            || in_array('whatsapp.chat.send', $permissions, true);
        if (!$canSupervise) {
            $selectedAgentId = null;
            $selectedRoleId = null;
        }

        $paginator = $this->conversationReadService->paginateConversations(
            $search,
            null,
            $filter !== '' ? $filter : 'all',
            is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null,
            $canSupervise,
            $selectedAgentId,
            $selectedRoleId,
            $dateFrom,
            $dateTo
        );

        $selectedConversation = $selectedConversationId > 0
            ? $this->conversationReadService->findConversationWithMessages(
                $selectedConversationId,
                150,
                is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null,
                $canSupervise,
                $selectedAgentId,
                $selectedRoleId
            )
            : null;

        $agents = $this->conversationOpsService->listAgents();
        $agentSummary = $canSupervise
            ? $this->conversationOpsService->summarizeAgentWorkload()
            : ['agents' => [], 'totals' => []];
        $roleOptions = collect($agents)
            ->filter(fn (array $agent): bool => !empty($agent['role_id']) && !empty($agent['role_name']))
            ->map(fn (array $agent): array => [
                'id' => (int) $agent['role_id'],
                'name' => (string) $agent['role_name'],
            ])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();

        return view('whatsapp.v2-chat', [
            'pageTitle' => 'WhatsApp V2 - Chat',
            'currentUser' => $currentUser,
            'canSupervise' => $canSupervise,
            'canOperateConversation' => $canOperateConversation,
            'presenceStatus' => $this->conversationOpsService->getAgentPresence(
                is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : 0
            ),
            'selectedFilter' => $filter !== '' ? $filter : 'all',
            'search' => $search,
            'dateFrom' => $dateFrom?->format('Y-m-d'),
            'dateTo' => $dateTo?->format('Y-m-d'),
            'selectedAgentId' => $selectedAgentId,
            'selectedRoleId' => $selectedRoleId,
            'listData' => $this->conversationReadService->serializeConversationPage(
                $paginator,
                is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null
            ),
            'tabCounts' => $this->conversationReadService->getTabCounts(
                is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null,
                $canSupervise,
                $selectedAgentId,
                $selectedRoleId,
                $dateFrom,
                $dateTo
            ),
            'agents' => $agents,
            'agentSummary' => $agentSummary,
            'roleOptions' => $roleOptions,
            'selectedConversation' => $selectedConversation !== null
                ? $this->conversationReadService->serializeConversationDetail(
                    $selectedConversation,
                    is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null
                )
                : null,
            'quickReplies' => $this->productivityToolkitService->listQuickReplies(limit: 12),
            'templateOptions' => $this->campaignService->listTemplateOptions(),
            'conversationNotes' => $selectedConversation !== null
                ? $this->productivityToolkitService->listConversationNotes((int) $selectedConversation->id, 12)
                : [],
        ] + $this->buildWhatsappNotificationViewData($request, [
            'currentConversationId' => (int) ($selectedConversation['id'] ?? 0),
            'canSupervise' => $canSupervise,
            'scope' => 'chat',
        ]));
    }

    public function templates(Request $request): View
    {
        $catalog = $this->templateCatalogService->getTemplateCatalog([
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'category' => trim((string) $request->query('category', '')),
            'language' => trim((string) $request->query('language', '')),
            'limit' => (int) $request->query('limit', 100),
        ]);

        return view('whatsapp.v2-templates', [
            'pageTitle' => 'WhatsApp V2 - Templates',
            'templates' => $catalog['templates'],
            'availableCategories' => $catalog['available_categories'],
            'availableLanguages' => $catalog['available_languages'],
            'integration' => $catalog['integration'],
            'source' => $catalog['source'],
            'filters' => [
                'search' => trim((string) $request->query('search', '')),
                'status' => trim((string) $request->query('status', '')),
                'category' => trim((string) $request->query('category', '')),
                'language' => trim((string) $request->query('language', '')),
            ],
        ] + $this->buildWhatsappNotificationViewData($request, [
            'scope' => 'templates',
        ]));
    }

    public function dashboard(Request $request): View
    {
        $today = new DateTimeImmutable('today');
        $dateFrom = trim((string) $request->query('date_from', $today->modify('-29 days')->format('Y-m-d')));
        $dateTo = trim((string) $request->query('date_to', $today->format('Y-m-d')));
        $roleId = $this->nullableIntQuery($request, 'role_id');
        $agentId = $this->nullableIntQuery($request, 'agent_id');
        $slaTargetMinutes = $this->nullableIntQuery($request, 'sla_target_minutes') ?? 15;

        $dashboard = $this->kpiDashboardService->buildDashboard(
            new DateTimeImmutable($dateFrom),
            new DateTimeImmutable($dateTo),
            $roleId,
            $agentId,
            $slaTargetMinutes
        );

        return view('whatsapp.v2-dashboard', [
            'pageTitle' => 'WhatsApp V2 - Dashboard',
            'dashboard' => $dashboard,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'role_id' => $roleId,
                'agent_id' => $agentId,
                'sla_target_minutes' => $slaTargetMinutes,
            ],
        ] + $this->buildWhatsappNotificationViewData($request, [
            'scope' => 'dashboard',
        ]));
    }

    public function flowmaker(Request $request): View
    {
        return view('whatsapp.v2-flowmaker', [
            'pageTitle' => 'WhatsApp V2 - Flowmaker',
            'flowmaker' => $this->flowmakerService->getOverview(),
            'contract' => $this->flowmakerService->getContract(),
            'aiAgentPreview' => $this->aiAgentPreviewService->overview(),
            'knowledgeBase' => $this->knowledgeBaseService->overview(),
            'templates' => $this->campaignService->listTemplateOptions(),
        ] + $this->buildWhatsappNotificationViewData($request, [
            'scope' => 'flowmaker',
        ]));
    }

    public function campaigns(Request $request): View
    {
        return view('whatsapp.v2-campaigns', [
            'pageTitle' => 'WhatsApp V2 - Campañas',
            'campaigns' => $this->campaignService->listCampaigns(),
            'templates' => $this->campaignService->listTemplateOptions(),
            'audienceSuggestions' => $this->campaignService->audienceSuggestions(),
        ] + $this->buildWhatsappNotificationViewData($request, [
            'scope' => 'campaigns',
        ]));
    }

    public function hub(Request $request): View
    {
        return $this->renderSection('dashboard', $request);
    }

    private function renderSection(string $section, ?Request $request = null): View
    {
        $sections = [
            'chat' => [
                'title' => 'Chat',
                'goal' => 'El chat sigue operando en legacy mientras el resto del stack de WhatsApp migra a Laravel V2.',
                'scope' => [
                    'Inbox y conversación siguen en /whatsapp/chat',
                    'Se mantiene handoff, presencia y reglas actuales del chat legacy',
                    'No se fuerza operación del inbox V2 en esta fase',
                    'V2 se usa para dashboard, templates, campañas y flowmaker',
                ],
            ],
            'campaigns' => [
                'title' => 'Campañas',
                'goal' => 'Operar campañas y dry-runs desde Laravel V2 sin depender del stack legacy.',
                'scope' => [
                    'Creación y listado de campañas',
                    'Selección de audiencia sugerida',
                    'Dry-run operativo',
                    'Uso de templates oficiales publicados',
                ],
            ],
            'templates' => [
                'title' => 'Templates',
                'goal' => 'Mover sync, preview, creación y versionado de plantillas oficiales a Laravel.',
                'scope' => [
                    'Listado filtrable y sincronización con Meta',
                    'Editor con preview y variables',
                    'Media headers y revisiones',
                    'Validaciones de ventana y categorías',
                ],
            ],
            'dashboard' => [
                'title' => 'Dashboard',
                'goal' => 'Reponer KPIs operativos y drilldown usando datos generados por Laravel.',
                'scope' => [
                    'Atención, pérdida y primera respuesta',
                    'KPIs por agente y por equipo',
                    'Drilldown exportable',
                    'Reportes de tráfico y ownership',
                ],
            ],
            'flowmaker' => [
                'title' => 'Flowmaker',
                'goal' => 'Construir un editor usable sobre los modelos de autoresponder y versiones ya existentes.',
                'scope' => [
                    'Editor visual por pasos y transiciones',
                    'Publicación por versión',
                    'Schedules y filtros de audiencia',
                    'Validación antes de publicar',
                ],
            ],
        ];

        $statusCards = [
            [
                'label' => 'Chat legacy',
                'state' => 'Operativo',
                'tone' => 'success',
                'detail' => 'El inbox y la conversación siguen en /whatsapp/chat mientras estabilizamos V2.',
            ],
            [
                'label' => 'WhatsApp V2',
                'state' => 'Operativo parcial',
                'tone' => 'warning',
                'detail' => 'Dashboard, templates, campañas y flowmaker listos para operación en Laravel.',
            ],
            [
                'label' => 'Chat V2',
                'state' => 'Standby',
                'tone' => 'info',
                'detail' => 'Se mantiene disponible para pruebas y ajuste, pero no como inbox principal.',
            ],
        ];

        $phases = [
            'Fase 1: Core WhatsApp y webhook',
            'Fase 2: Templates y campañas en V2',
            'Fase 3: KPI y reportes en V2',
            'Fase 4: Flowmaker y automatización en V2',
            'Fase 5: Chat V2 cuando cierre validación operativa',
        ];

        return view('whatsapp.v2-hub', [
            'pageTitle' => 'WhatsApp V2',
            'section' => $section,
            'sectionMeta' => $sections[$section] ?? $sections['chat'],
            'statusCards' => $statusCards,
            'phases' => $phases,
            'planDocPath' => '/docs/strangler/whatsapp-migration-plan-2026-04-10.md',
        ] + ($request instanceof Request
            ? $this->buildWhatsappNotificationViewData($request, ['scope' => 'hub'])
            : []));
    }

    private function nullableIntQuery(Request $request, string $key): ?int
    {
        if (!$request->query->has($key)) {
            return null;
        }

        $value = $request->query($key);
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function nullableDateQuery(Request $request, string $key): ?CarbonImmutable
    {
        if (!$request->query->has($key)) {
            return null;
        }

        $value = trim((string) $request->query($key));
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
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
            'channel' => 'whatsapp-ops',
            'event' => self::REALTIME_EVENTS['inbound_message'],
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
     * @param array<string,mixed> $runtimeOverrides
     * @return array<string,mixed>
     */
    private function buildWhatsappNotificationViewData(Request $request, array $runtimeOverrides = []): array
    {
        $currentUser = $this->resolveCurrentUser();
        $notificationsEnabled = $this->canCurrentUserReceiveWhatsappNotifications($request);

        return [
            'whatsappNotificationPanelEnabled' => $notificationsEnabled,
            'realtimeConfig' => $this->buildRealtimeConfig(),
            'whatsappNotificationCurrentUser' => [
                'id' => (int) ($currentUser['id'] ?? 0),
                'name' => (string) ($currentUser['display_name'] ?? $currentUser['nombre'] ?? $currentUser['username'] ?? 'Usuario'),
            ],
            'whatsappRealtimeRuntime' => array_merge([
                'currentConversationId' => 0,
                'canSupervise' => false,
                'scope' => 'general',
            ], $runtimeOverrides),
            'whatsappAssetVersion' => (string) max(
                @filemtime(public_path('js/pages/whatsapp/v2-notifications.js')) ?: 0,
                @filemtime(resource_path('views/layouts/partials/notification_panel.blade.php')) ?: 0,
                @filemtime(resource_path('views/layouts/medforge.blade.php')) ?: 0,
                @filemtime(resource_path('views/whatsapp/v2-chat.blade.php')) ?: 0,
            ),
        ];
    }

    private function canCurrentUserReceiveWhatsappNotifications(Request $request): bool
    {
        $permissions = $this->resolvePermissions();
        $userId = (int) ($this->resolveCurrentUser()['id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        $hasPermission = LegacyPermissionCatalog::containsAny($permissions, [
            'administrativo',
            'whatsapp.manage',
            'whatsapp.chat.supervise',
            'whatsapp.notifications.receive',
        ]);

        if (!$hasPermission) {
            return false;
        }

        try {
            return (bool) DB::table('users')
                ->where('id', $userId)
                ->value('whatsapp_notify');
        } catch (Throwable) {
            return false;
        }
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

    /**
     * @return array<string,mixed>
     */
    private function resolveCurrentUser(): array
    {
        $userId = $this->actorUserId();
        if ($userId <= 0) {
            return [
                'id' => 0,
                'display_name' => 'Usuario',
                'role_name' => 'Usuario',
                'profile_photo_url' => null,
            ];
        }

        $row = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select(['u.id', 'u.username', 'u.nombre', 'u.email', 'u.profile_photo', 'r.name as role_name'])
            ->where('u.id', $userId)
            ->first();

        $displayName = trim((string) ($row->nombre ?? $row->username ?? 'Usuario'));
        if ($displayName === '') {
            $displayName = 'Usuario';
        }

        $profilePhoto = trim((string) ($row->profile_photo ?? ''));

        return [
            'id' => (int) ($row->id ?? $userId),
            'display_name' => $displayName,
            'role_name' => (string) ($row->role_name ?? 'Usuario'),
            'profile_photo_url' => $profilePhoto !== '' ? '/' . ltrim($profilePhoto, '/') : null,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function resolvePermissions(): array
    {
        $userId = $this->actorUserId();
        if ($userId <= 0) {
            return [];
        }

        try {
            $row = DB::table('users as u')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                ->select(['u.permisos as user_permissions', 'r.permissions as role_permissions'])
                ->where('u.id', $userId)
                ->first();

            return LegacyPermissionCatalog::merge(
                [],
                $row->user_permissions ?? [],
                $row->role_permissions ?? []
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function actorUserId(): int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : 0;
    }
}
