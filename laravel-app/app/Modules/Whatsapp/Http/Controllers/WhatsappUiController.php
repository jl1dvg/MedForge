<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use App\Modules\Whatsapp\Services\ConversationReadService;
use App\Modules\Whatsapp\Services\CampaignService;
use App\Modules\Whatsapp\Services\FlowmakerService;
use App\Modules\Whatsapp\Services\KpiDashboardService;
use App\Modules\Whatsapp\Services\ProductivityToolkitService;
use App\Modules\Whatsapp\Services\TemplateCatalogService;
use DateTimeImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WhatsappUiController
{
    public function __construct(
        private readonly ConversationReadService $conversationReadService = new \App\Modules\Whatsapp\Services\ConversationReadService(),
        private readonly ConversationOpsService $conversationOpsService = new \App\Modules\Whatsapp\Services\ConversationOpsService(),
        private readonly CampaignService $campaignService = new \App\Modules\Whatsapp\Services\CampaignService(),
        private readonly TemplateCatalogService $templateCatalogService = new \App\Modules\Whatsapp\Services\TemplateCatalogService(),
        private readonly KpiDashboardService $kpiDashboardService = new \App\Modules\Whatsapp\Services\KpiDashboardService(),
        private readonly FlowmakerService $flowmakerService = new \App\Modules\Whatsapp\Services\FlowmakerService(),
        private readonly ProductivityToolkitService $productivityToolkitService = new \App\Modules\Whatsapp\Services\ProductivityToolkitService(),
    ) {
    }

    public function chat(Request $request): View|Factory
    {
        $currentUser = LegacyCurrentUser::resolve($request);
        $permissions = LegacyPermissionResolver::resolve($request);
        $selectedConversationId = max(0, (int) $request->query('conversation', 0));
        $filter = trim((string) $request->query('filter', 'all'));
        $search = trim((string) $request->query('search', ''));
        $selectedAgentId = $this->nullableIntQuery($request, 'agent_id');
        $selectedRoleId = $this->nullableIntQuery($request, 'role_id');
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
            25,
            $filter !== '' ? $filter : 'all',
            is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null,
            $canSupervise,
            $selectedAgentId,
            $selectedRoleId
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
                $selectedRoleId
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
            'conversationNotes' => $selectedConversation !== null
                ? $this->productivityToolkitService->listConversationNotes((int) $selectedConversation->id, 12)
                : [],
        ]);
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
        ]);
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
        ]);
    }

    public function flowmaker(): View
    {
        return view('whatsapp.v2-flowmaker', [
            'pageTitle' => 'WhatsApp V2 - Flowmaker',
            'flowmaker' => $this->flowmakerService->getOverview(),
            'contract' => $this->flowmakerService->getContract(),
        ]);
    }

    public function campaigns(): View
    {
        return view('whatsapp.v2-campaigns', [
            'pageTitle' => 'WhatsApp V2 - Campañas',
            'campaigns' => $this->campaignService->listCampaigns(),
            'templates' => $this->campaignService->listTemplateOptions(),
            'audienceSuggestions' => $this->campaignService->audienceSuggestions(),
        ]);
    }

    private function renderSection(string $section): View
    {
        $sections = [
            'chat' => [
                'title' => 'Chat',
                'goal' => 'Migrar primero el inbox operativo, conversaciones, mensajes, filtros, búsqueda y acciones rápidas.',
                'scope' => [
                    'Lista de conversaciones y detalle de conversación',
                    'Búsqueda por nombre, HC y número',
                    'Envío manual de texto y adjuntos',
                    'Contadores de cola, mis chats, sin leer y resueltos',
                    'Panel lateral con contexto clínico y acciones',
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
                'label' => 'Legacy MedForge',
                'state' => 'Operativo',
                'tone' => 'success',
                'detail' => 'Reglas reales de negocio, handoff, presencia, KPIs y contexto paciente.',
            ],
            [
                'label' => 'Whatsbox',
                'state' => 'Referencia',
                'tone' => 'info',
                'detail' => 'Fuente de patrones UX y modularidad para chat, templates, agentes, campañas y flowmaker.',
            ],
            [
                'label' => 'Laravel App',
                'state' => 'En migración',
                'tone' => 'warning',
                'detail' => 'Ya tiene modelos y tablas base; falta capa operativa, servicios, rutas y UI final.',
            ],
        ];

        $phases = [
            'Fase 1: Core WhatsApp y webhook',
            'Fase 2: Chat operativo',
            'Fase 3: Handoff, presencia y agentes',
            'Fase 4: Templates',
            'Fase 5: KPI y reportes',
            'Fase 6: Flowmaker y automatización',
        ];

        return view('whatsapp.v2-hub', [
            'pageTitle' => 'WhatsApp V2',
            'section' => $section,
            'sectionMeta' => $sections[$section] ?? $sections['chat'],
            'statusCards' => $statusCards,
            'phases' => $phases,
            'planDocPath' => '/docs/strangler/whatsapp-migration-plan-2026-04-10.md',
        ]);
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
}
