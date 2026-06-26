<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappHandoff;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappHandoffAutoAssignService
{
    private const HOT_TOPICS = [
        'captacion_agendar',
        'agenda_sin_disponibilidad',
        'faq_escalada',
        'operacion_cita_vigente',
        'operacion_reagenda',
    ];

    /** @var array<int,bool>|null */
    private ?array $requeuedCache = null;

    public function __construct(
        private readonly WhatsappRealtimeService $realtime = new WhatsappRealtimeService(),
        private readonly WhatsappOperationalDecisionService $decisionService = new WhatsappOperationalDecisionService(),
    ) {
    }

    /**
     * @param array{dry_run?:bool,limit?:int,max_age_hours?:int} $options
     * @return array{eligible:int,assigned:int,would_assign:int,supervisor:int,skipped:int,by_topic:array<string,int>,rows:array<int,array<string,mixed>>,error?:string}
     */
    public function run(array $options = []): array
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_handoffs')) {
            return $this->emptyResult('Tablas de conversaciones o handoffs no disponibles.');
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $limit = max(1, min(500, (int) ($options['limit'] ?? 100)));
        $maxAgeHours = max(1, min(720, (int) ($options['max_age_hours'] ?? 72)));
        $businessOpen = $dryRun || $this->businessWindowOpen();
        $agents = $businessOpen ? $this->availableAgents() : [];
        $candidates = $this->candidateRows($limit, $maxAgeHours);
        $this->requeuedCache = $this->preloadRequeuedHandoffIds($candidates);

        $result = [
            'eligible' => 0,
            'assigned' => 0,
            'would_assign' => 0,
            'supervisor' => 0,
            'skipped' => 0,
            'by_topic' => [],
            'rows' => [],
        ];
        if (!$businessOpen) {
            $result['error'] = 'Autoasignación pausada: el canal está fuera del horario laboral configurado.';
        }

        foreach ($candidates as $candidate) {
            $priority = $this->resolvePriority($candidate);
            $reason = $this->resolveReason($candidate);
            $topic = (string) ($candidate->topic ?? 'faq_escalada');

            // ── Operational bucket guard ──────────────────────────────────────
            // Only hot_open + assign_now candidates may be auto-assigned.
            // RESCUE / BACKLOG / LOST are excluded even if requeued recently.
            $eligibility = $this->decisionService->evaluateForAutoAssign($candidate);
            if (!$eligibility['eligible']) {
                $result['skipped']++;
                $result['rows'][] = [
                    'conversation_id' => (int) $candidate->conversation_id,
                    'handoff_id'      => (int) $candidate->handoff_id,
                    'topic'           => $topic,
                    'priority'        => $priority,
                    'reason'          => $reason,
                    'assigned_to'     => null,
                    'status'          => 'skipped',
                    'skip_reason'     => $eligibility['skip_reason'],
                    'bucket'          => $eligibility['bucket'],
                ];
                continue;
            }

            $result['eligible']++;
            $result['by_topic'][$topic] = (int) ($result['by_topic'][$topic] ?? 0) + 1;

            $agent = $agents[0] ?? null;
            $row = [
                'conversation_id' => (int) $candidate->conversation_id,
                'handoff_id' => (int) $candidate->handoff_id,
                'topic' => $topic,
                'priority' => $priority,
                'reason' => $reason,
                'assigned_to' => null,
                'status' => $dryRun ? 'dry_run' : 'pending',
            ];

            if (!is_array($agent)) {
                $row['status'] = 'supervisor';
                $result['supervisor']++;
                $result['rows'][] = $row;
                continue;
            }

            if ($dryRun) {
                $row['assigned_to'] = [
                    'id' => (int) $agent['id'],
                    'name' => (string) $agent['name'],
                ];
                $result['would_assign']++;
                $result['rows'][] = $row;
                continue;
            }

            $assigned = $this->assignCandidate($candidate, $agent, $priority, $reason);
            if ($assigned) {
                $row['status'] = 'assigned';
                $row['assigned_to'] = [
                    'id' => (int) $agent['id'],
                    'name' => (string) $agent['name'],
                ];
                $result['assigned']++;
                $agents[0]['assigned_open_count'] = (int) ($agents[0]['assigned_open_count'] ?? 0) + 1;
                usort($agents, $this->agentSort(...));
            } else {
                $row['status'] = 'skipped';
                $result['skipped']++;
            }

            $result['rows'][] = $row;
        }

        return $result;
    }

    /**
     * @param array<int,object> $candidates
     * @return array<int,bool>
     */
    private function preloadRequeuedHandoffIds(array $candidates): array
    {
        if ($candidates === [] || !Schema::hasTable('whatsapp_handoff_events')) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (object $candidate): int => (int) ($candidate->handoff_id ?? 0),
            $candidates
        ))));
        if ($ids === []) {
            return [];
        }

        return DB::table('whatsapp_handoff_events')
            ->whereIn('handoff_id', $ids)
            ->whereIn('event_type', ['requeued', 'expired'])
            ->pluck('handoff_id')
            ->mapWithKeys(static fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @return array{eligible:int,assigned:int,would_assign:int,supervisor:int,skipped:int,by_topic:array<string,int>,rows:array<int,array<string,mixed>>,error?:string}
     */
    private function emptyResult(string $error): array
    {
        return [
            'eligible' => 0,
            'assigned' => 0,
            'would_assign' => 0,
            'supervisor' => 0,
            'skipped' => 0,
            'by_topic' => [],
            'rows' => [],
            'error' => $error,
        ];
    }

    /**
     * @return array<int,object>
     */
    private function candidateRows(int $limit, int $maxAgeHours): array
    {
        $query = DB::table('whatsapp_handoffs as h')
            ->join('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
            ->select([
                'h.id as handoff_id',
                'h.conversation_id',
                'h.wa_number',
                'h.topic',
                'h.priority',
                'h.queued_at',
                'h.assigned_agent_id',
                'h.notes',
                'c.patient_hc_number',
                'c.patient_full_name',
                'c.assigned_user_id',
                'c.last_message_at',
                'c.last_message_direction',
                'c.handoff_requested_at',
                'c.created_at as conversation_created_at',
            ])
            ->where('h.status', 'queued')
            ->whereNull('h.assigned_agent_id')
            ->where('c.needs_human', true)
            ->whereNull('c.assigned_user_id')
            ->whereIn('h.topic', self::HOT_TOPICS)
            ->where(function ($query) use ($maxAgeHours): void {
                $query->where('h.queued_at', '>=', now()->subHours($maxAgeHours))
                    ->orWhere(function ($fallback) use ($maxAgeHours): void {
                        $fallback->whereNull('h.queued_at')
                            ->where('h.created_at', '>=', now()->subHours($maxAgeHours));
                    });
            })
            ->orderByRaw("CASE h.priority WHEN 'critical' THEN 0 WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 ELSE 3 END")
            ->orderBy('h.queued_at')
            ->limit($limit);

        if (Schema::hasTable('whatsapp_conversation_attributions')) {
            $query->leftJoin('whatsapp_conversation_attributions as a', 'a.conversation_id', '=', 'c.id')
                ->addSelect(['a.initial_intent', 'a.patient_segment']);
        } else {
            $query->addSelect([
                DB::raw('NULL as initial_intent'),
                DB::raw('NULL as patient_segment'),
            ]);
        }

        if (Schema::hasTable('whatsapp_messages')) {
            $latestInbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MAX(COALESCE(message_timestamp, created_at)) AS latest_inbound_at')
                ->where('direction', 'inbound')
                ->groupBy('conversation_id');
            $query->leftJoinSub($latestInbound, 'latest_inbound', 'latest_inbound.conversation_id', '=', 'c.id')
                ->addSelect(['latest_inbound.latest_inbound_at']);
        } else {
            $query->addSelect([DB::raw('NULL AS latest_inbound_at')]);
        }

        if (Schema::hasColumn('whatsapp_conversations', 'closed_at')) {
            $query->whereNull('c.closed_at');
        }

        return $query->get()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function availableAgents(): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $presenceMap = [];
        if (Schema::hasTable('whatsapp_agent_presence')) {
            $presenceMap = DB::table('whatsapp_agent_presence')
                ->pluck('status', 'user_id')
                ->map(static fn ($value): string => is_string($value) && $value !== '' ? $value : 'available')
                ->all();
        }

        $loadByAgent = $this->openLoadByAgent();
        $agents = User::query()
            ->with('role')
            ->orderBy('nombre')
            ->get()
            ->filter(function (User $user): bool {
                $permissions = LegacyPermissionCatalog::merge($user->permisos, $user->role?->permissions);

                return LegacyPermissionCatalog::containsAny($permissions, [
                    'whatsapp.chat.send',
                    'whatsapp.chat.supervise',
                    'whatsapp.manage',
                ]);
            })
            ->map(function (User $user) use ($presenceMap, $loadByAgent): array {
                $displayName = trim((string) $user->nombre);
                if ($displayName === '') {
                    $displayName = trim((string) $user->first_name . ' ' . (string) $user->last_name);
                }
                if ($displayName === '') {
                    $displayName = (string) $user->username;
                }

                $load = $loadByAgent[(int) $user->id] ?? ['assigned_open_count' => 0, 'unread_open_count' => 0];

                return [
                    'id' => (int) $user->id,
                    'name' => $displayName,
                    'presence_status' => $presenceMap[$user->id] ?? 'available',
                    'assigned_open_count' => (int) ($load['assigned_open_count'] ?? 0),
                    'unread_open_count' => (int) ($load['unread_open_count'] ?? 0),
                ];
            })
            ->filter(static fn (array $agent): bool => (string) ($agent['presence_status'] ?? 'available') === 'available')
            ->values()
            ->all();

        usort($agents, $this->agentSort(...));

        return $agents;
    }

    /**
     * @return array<int,array{assigned_open_count:int,unread_open_count:int}>
     */
    private function openLoadByAgent(): array
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return [];
        }

        $rows = DB::table('whatsapp_conversations')
            ->selectRaw('assigned_user_id, COUNT(*) as assigned_open_count, SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END) as unread_open_count')
            ->where('needs_human', true)
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->get();

        $load = [];
        foreach ($rows as $row) {
            $agentId = (int) ($row->assigned_user_id ?? 0);
            if ($agentId <= 0) {
                continue;
            }

            $load[$agentId] = [
                'assigned_open_count' => (int) ($row->assigned_open_count ?? 0),
                'unread_open_count' => (int) ($row->unread_open_count ?? 0),
            ];
        }

        return $load;
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    private function agentSort(array $a, array $b): int
    {
        $loadA = ((int) ($a['assigned_open_count'] ?? 0) * 100) + (int) ($a['unread_open_count'] ?? 0);
        $loadB = ((int) ($b['assigned_open_count'] ?? 0) * 100) + (int) ($b['unread_open_count'] ?? 0);

        return $loadA <=> $loadB ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    }

    private function resolvePriority(object $candidate): string
    {
        $priority = strtolower(trim((string) ($candidate->priority ?? 'normal')));
        $urgentSignals = 0;

        if (trim((string) ($candidate->patient_hc_number ?? '')) !== '') {
            $urgentSignals++;
        }
        if ($this->metaWindowClosingSoon($candidate)) {
            $urgentSignals++;
        }
        if ($this->wasRequeued((int) $candidate->handoff_id)) {
            $urgentSignals++;
        }
        if (in_array(strtolower(trim((string) ($candidate->initial_intent ?? ''))), ['booking', 'appointment', 'schedule'], true)) {
            $urgentSignals++;
        }
        if (in_array(strtolower(trim((string) ($candidate->patient_segment ?? ''))), ['retorno', 'returning', 'patient_return'], true)) {
            $urgentSignals++;
        }

        if ($urgentSignals >= 2) {
            return 'urgent';
        }

        return in_array($priority, ['critical', 'urgent', 'high', 'normal'], true) ? $priority : 'high';
    }

    private function resolveReason(object $candidate): string
    {
        if ($this->wasRequeued((int) $candidate->handoff_id)) {
            return 'requeued_hot_opportunity';
        }

        if ($this->metaWindowClosingSoon($candidate)) {
            return 'meta_window_closing';
        }

        return 'hot_opportunity';
    }

    private function metaWindowClosingSoon(object $candidate): bool
    {
        if (strtolower(trim((string) ($candidate->last_message_direction ?? ''))) !== 'inbound') {
            return false;
        }

        $lastMessageAt = $candidate->last_message_at !== null ? CarbonImmutable::parse((string) $candidate->last_message_at) : null;
        if ($lastMessageAt === null) {
            return false;
        }

        $minutesLeft = now()->diffInMinutes($lastMessageAt->copy()->addHours(24), false);

        return $minutesLeft >= 0 && $minutesLeft <= 120;
    }

    private function wasRequeued(int $handoffId): bool
    {
        if (is_array($this->requeuedCache)) {
            return isset($this->requeuedCache[$handoffId]);
        }

        if ($handoffId <= 0 || !Schema::hasTable('whatsapp_handoff_events')) {
            return false;
        }

        return DB::table('whatsapp_handoff_events')
            ->where('handoff_id', $handoffId)
            ->whereIn('event_type', ['requeued', 'expired'])
            ->exists();
    }

    private function businessWindowOpen(): bool
    {
        if (!Schema::hasTable('app_settings')) {
            return true;
        }

        $settings = DB::table('app_settings')
            ->whereIn('name', [
                'whatsapp_handoff_business_timezone',
                'whatsapp_handoff_business_schedule',
                'whatsapp_handoff_business_holidays',
                'whatsapp_handoff_business_start',
                'whatsapp_handoff_business_end',
            ])
            ->pluck('value', 'name');

        if (!$settings instanceof Collection || $settings->isEmpty()) {
            return true;
        }

        $timezone = trim((string) ($settings['whatsapp_handoff_business_timezone'] ?? 'America/Guayaquil')) ?: 'America/Guayaquil';
        $now = CarbonImmutable::now($timezone);
        $holidays = array_filter(array_map('trim', explode(',', (string) ($settings['whatsapp_handoff_business_holidays'] ?? ''))));
        if (in_array($now->toDateString(), $holidays, true)) {
            return false;
        }

        $schedule = json_decode((string) ($settings['whatsapp_handoff_business_schedule'] ?? ''), true);
        if (!is_array($schedule)) {
            $schedule = [];
        }

        if ($schedule === []) {
            $schedule = [
                'monday' => [
                    'enabled' => true,
                    'start' => (string) ($settings['whatsapp_handoff_business_start'] ?? '08:00'),
                    'end' => (string) ($settings['whatsapp_handoff_business_end'] ?? '18:00'),
                ],
                'tuesday' => [
                    'enabled' => true,
                    'start' => (string) ($settings['whatsapp_handoff_business_start'] ?? '08:00'),
                    'end' => (string) ($settings['whatsapp_handoff_business_end'] ?? '18:00'),
                ],
                'wednesday' => [
                    'enabled' => true,
                    'start' => (string) ($settings['whatsapp_handoff_business_start'] ?? '08:00'),
                    'end' => (string) ($settings['whatsapp_handoff_business_end'] ?? '18:00'),
                ],
                'thursday' => [
                    'enabled' => true,
                    'start' => (string) ($settings['whatsapp_handoff_business_start'] ?? '08:00'),
                    'end' => (string) ($settings['whatsapp_handoff_business_end'] ?? '18:00'),
                ],
                'friday' => [
                    'enabled' => true,
                    'start' => (string) ($settings['whatsapp_handoff_business_start'] ?? '08:00'),
                    'end' => (string) ($settings['whatsapp_handoff_business_end'] ?? '18:00'),
                ],
            ];
        }

        $day = $schedule[strtolower($now->englishDayOfWeek)] ?? null;
        if (!is_array($day) || empty($day['enabled'])) {
            return false;
        }

        $start = trim((string) ($day['start'] ?? ''));
        $end = trim((string) ($day['end'] ?? ''));
        if ($start === '' || $end === '') {
            return false;
        }

        $current = $now->format('H:i');

        return $current >= $start && $current <= $end;
    }

    /**
     * @param array<string,mixed> $agent
     */
    private function assignCandidate(object $candidate, array $agent, string $priority, string $reason): bool
    {
        $conversationId = (int) $candidate->conversation_id;
        $handoffId = (int) $candidate->handoff_id;
        $agentId = (int) ($agent['id'] ?? 0);
        if ($conversationId <= 0 || $handoffId <= 0 || $agentId <= 0) {
            return false;
        }

        return DB::transaction(function () use ($conversationId, $handoffId, $agent, $agentId, $priority, $reason): bool {
            $conversation = WhatsappConversation::query()
                ->where('id', $conversationId)
                ->where('needs_human', true)
                ->whereNull('assigned_user_id')
                ->lockForUpdate()
                ->first();
            $handoff = WhatsappHandoff::query()
                ->where('id', $handoffId)
                ->where('status', 'queued')
                ->whereNull('assigned_agent_id')
                ->lockForUpdate()
                ->first();

            if (!$conversation instanceof WhatsappConversation || !$handoff instanceof WhatsappHandoff) {
                return false;
            }

            $now = now();
            $conversation->fill([
                'assigned_user_id' => $agentId,
                'assigned_at' => $now,
                'needs_human' => true,
            ])->save();

            $handoff->fill([
                'status' => 'assigned',
                'priority' => $priority,
                'assigned_agent_id' => $agentId,
                'assigned_at' => $now,
                'assigned_until' => $now->copy()->addHours(24),
                'last_activity_at' => $now,
            ])->save();

            if (Schema::hasTable('whatsapp_handoff_events')) {
                DB::table('whatsapp_handoff_events')->insert([
                    'handoff_id' => $handoff->id,
                    'event_type' => 'auto_assigned',
                    'actor_user_id' => null,
                    'notes' => $reason,
                    'created_at' => $now,
                ]);
            }

            $this->realtime->broadcastHandoffOperationalEvent([
                'event' => 'handoff.auto_assigned',
                'conversation_id' => (int) $conversation->id,
                'priority' => $priority,
                'topic' => (string) ($handoff->topic ?? 'faq_escalada'),
                'reason' => $reason,
                'assigned_to' => [
                    'id' => $agentId,
                    'name' => (string) ($agent['name'] ?? ('Agente ' . $agentId)),
                ],
                'timestamp' => $now->toISOString(),
            ]);

            return true;
        });
    }
}
