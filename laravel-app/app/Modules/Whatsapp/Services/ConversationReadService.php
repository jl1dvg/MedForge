<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConversationReadService
{
    /**
     * @return LengthAwarePaginator<int, WhatsappConversation>
     */
    public function paginateConversations(
        string $search = '',
        ?int $perPage = null,
        string $filter = 'all',
        ?int $viewerUserId = null,
        bool $includeAssignedOthers = true,
        ?int $assignedUserId = null,
        ?int $roleId = null,
        ?CarbonImmutable $dateFrom = null,
        ?CarbonImmutable $dateTo = null,
    ): LengthAwarePaginator
    {
        $query = $this->baseVisibleQuery($viewerUserId, $includeAssignedOthers, $assignedUserId, $roleId);
        $query = $this->applyFilter($query, $filter, $viewerUserId);
        $query = $this->applySearch($query, $search);
        $query = $this->applyDateRange($query, $dateFrom, $dateTo);
        $query = $this->applyPriorityOrdering($query, $viewerUserId);

        if ($perPage === null || $perPage <= 0) {
            $perPage = max(1, (clone $query)->count());
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findConversationWithMessages(
        int $conversationId,
        int $messageLimit = 150,
        ?int $viewerUserId = null,
        bool $includeAssignedOthers = true,
        ?int $assignedUserId = null,
        ?int $roleId = null,
    ): ?WhatsappConversation
    {
        $messageLimit = max(1, min($messageLimit, 500));

        $conversation = $this->baseVisibleQuery($viewerUserId, $includeAssignedOthers, $assignedUserId, $roleId)
            ->with([
                'whatsapp_messages' => function ($query) use ($messageLimit): void {
                    $query->orderByDesc('id')->limit($messageLimit);
                },
            ])
            ->find($conversationId);

        if (!$conversation instanceof WhatsappConversation) {
            return null;
        }

        if ((int) $conversation->unread_count > 0) {
            $conversation->forceFill([
                'unread_count' => 0,
            ])->save();
            $conversation->refresh();
            $conversation->load([
                'whatsapp_messages' => function ($query) use ($messageLimit): void {
                    $query->orderByDesc('id')->limit($messageLimit);
                },
            ]);
        }

        return $conversation;
    }

    /**
     * @return array<string, int>
     */
    public function getTabCounts(
        ?int $viewerUserId = null,
        bool $includeAssignedOthers = true,
        ?int $assignedUserId = null,
        ?int $roleId = null,
        ?CarbonImmutable $dateFrom = null,
        ?CarbonImmutable $dateTo = null,
    ): array
    {
        $base = $this->baseVisibleQuery($viewerUserId, $includeAssignedOthers, $assignedUserId, $roleId);
        $base = $this->applyDateRange($base, $dateFrom, $dateTo);
        $filters = [
            'critical_backlog',
            'captacion',
            'operacion',
            'informacion',
            'mine',
            'handoff',
            'window_open',
            'unread',
            'needs_template',
            'resolved',
            'all',
        ];

        $counts = [];
        foreach ($filters as $filter) {
            $counts[$filter] = $this->applyFilter(clone $base, $filter, $viewerUserId)->count();
        }

        return $counts;
    }

    private function applyDateRange(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if ($dateFrom !== null) {
            $query->where('last_message_at', '>=', $dateFrom->startOfDay());
        }

        if ($dateTo !== null) {
            $query->where('last_message_at', '<=', $dateTo->endOfDay());
        }

        return $query;
    }

    /**
     * @param LengthAwarePaginator<int, WhatsappConversation> $paginator
     * @return array<int, array<string, mixed>>
     */
    public function serializeConversationPage(LengthAwarePaginator $paginator, ?int $viewerUserId = null): array
    {
        $assignedUsers = $this->resolveAssignedUsers($paginator->getCollection()->pluck('assigned_user_id')->all());
        $roleLabels = $this->resolveRoleLabels($paginator->getCollection()->pluck('handoff_role_id')->all());

        return $paginator->getCollection()
            ->map(fn (WhatsappConversation $conversation): array => $this->serializeConversationSummary($conversation, $viewerUserId, $assignedUsers, $roleLabels))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeConversationDetail(WhatsappConversation $conversation, ?int $viewerUserId = null): array
    {
        $messages = $conversation->whatsapp_messages
            ->sortBy('id')
            ->values()
            ->map(fn (WhatsappMessage $message): array => $this->serializeMessage($message))
            ->all();

        $assignedUsers = $this->resolveAssignedUsers([(int) $conversation->assigned_user_id]);
        $roleLabels = $this->resolveRoleLabels([(int) $conversation->handoff_role_id]);

        return array_merge(
            $this->serializeConversationSummary($conversation, $viewerUserId, $assignedUsers, $roleLabels),
            ['messages' => $messages]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversationSummary(
        WhatsappConversation $conversation,
        ?int $viewerUserId = null,
        array $assignedUsers = [],
        array $roleLabels = [],
    ): array
    {
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $assignedUser = $assignedUserId > 0 ? ($assignedUsers[$assignedUserId] ?? null) : null;
        $handoffRoleId = (int) ($conversation->handoff_role_id ?? 0);
        $handoffRoleName = $handoffRoleId > 0 ? ($roleLabels[$handoffRoleId] ?? null) : null;

        return [
            'id' => $conversation->id,
            'wa_number' => $conversation->wa_number,
            'display_name' => $conversation->display_name,
            'patient_hc_number' => $conversation->patient_hc_number,
            'patient_full_name' => $conversation->patient_full_name,
            'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
            'last_message_direction' => $conversation->last_message_direction,
            'last_message_type' => $conversation->last_message_type,
            'last_message_preview' => $conversation->last_message_preview,
            'needs_human' => (bool) $conversation->needs_human,
            'handoff_notes' => $conversation->handoff_notes,
            'handoff_role_id' => $conversation->handoff_role_id,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_user_name' => is_array($assignedUser) ? ($assignedUser['name'] ?? null) : null,
            'assigned_role_name' => is_array($assignedUser) ? ($assignedUser['role_name'] ?? null) : $handoffRoleName,
            'handoff_role_name' => $handoffRoleName,
            'handoff_priority' => $this->resolveConversationPriority($conversation),
            'handoff_priority_label' => $this->handoffPriorityLabel($this->resolveConversationPriority($conversation)),
            'handoff_topic' => $this->scalarString($conversation->getAttribute('active_handoff_topic')),
            'queue_bucket' => $this->resolveOperationalQueue($conversation),
            'queue_bucket_label' => $this->operationalQueueLabel($this->resolveOperationalQueue($conversation)),
            'queue_age_minutes' => $this->resolveQueueAgeMinutes($conversation),
            'ownership_state' => $this->resolveOwnershipState($conversation, $viewerUserId),
            'ownership_label' => $this->resolveOwnershipLabel($conversation, $viewerUserId, $assignedUser, $handoffRoleName),
            'messaging_window_state' => $this->resolveMessagingWindowState($conversation),
            'messaging_window_label' => $this->resolveMessagingWindowLabel($conversation),
            'can_send_freeform' => $this->resolveMessagingWindowState($conversation) === 'window_open',
            'assigned_at' => optional($conversation->assigned_at)?->toISOString(),
            'handoff_requested_at' => optional($conversation->handoff_requested_at)?->toISOString(),
            'unread_count' => (int) $conversation->unread_count,
            'source' => 'laravel-v2',
        ];
    }

    private function baseVisibleQuery(
        ?int $viewerUserId,
        bool $includeAssignedOthers,
        ?int $assignedUserId = null,
        ?int $roleId = null,
    ): Builder
    {
        $query = WhatsappConversation::query()
            ->select('whatsapp_conversations.*')
            ->withMax([
                'whatsapp_messages as latest_inbound_at' => function ($query): void {
                    $query->where('direction', 'inbound');
                },
            ], 'message_timestamp');

        $query = $this->applyActiveHandoffJoin($query);
        $query = $this->applyAttributionJoin($query);

        if (!$includeAssignedOthers && $viewerUserId !== null && $viewerUserId > 0) {
            $query->where(function (Builder $builder) use ($viewerUserId): void {
                $builder
                    ->whereNull('assigned_user_id')
                    ->orWhere('assigned_user_id', $viewerUserId);
            });
        }

        if ($assignedUserId !== null) {
            if ($assignedUserId <= 0) {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', $assignedUserId);
            }
        }

        if ($roleId !== null && $roleId > 0) {
            $query->where('handoff_role_id', $roleId);
        }

        return $query;
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('display_name', 'like', '%' . $search . '%')
                ->orWhere('patient_full_name', 'like', '%' . $search . '%')
                ->orWhere('patient_hc_number', 'like', '%' . $search . '%')
                ->orWhere('wa_number', 'like', '%' . $search . '%')
                ->orWhere('last_message_preview', 'like', '%' . $search . '%');
        });
    }

    private function applyFilter(Builder $query, string $filter, ?int $viewerUserId): Builder
    {
        return match (trim($filter) !== '' ? trim($filter) : 'all') {
            'critical_backlog' => $this->applyCriticalBacklogFilter($query),
            'captacion' => $this->applyOperationalQueueFilter($query, 'captacion'),
            'operacion' => $this->applyOperationalQueueFilter($query, 'operacion'),
            'informacion' => $this->applyOperationalQueueFilter($query, 'informacion'),
            'unread' => $query->where('unread_count', '>', 0),
            'mine' => $viewerUserId !== null && $viewerUserId > 0
                ? $query->where('assigned_user_id', $viewerUserId)->where('needs_human', true)
                : $query->whereRaw('1 = 0'),
            'handoff', 'pending' => $query->where('needs_human', true)->whereNull('assigned_user_id'),
            'window_open' => $this->applyWindowOpenFilter($query->where('needs_human', true)),
            'needs_template' => $this->applyNeedsTemplateFilter($query->where('needs_human', true)),
            'resolved' => $query->where('needs_human', false),
            default => $query,
        };
    }

    private function applyPriorityOrdering(Builder $query, ?int $viewerUserId): Builder
    {
        $threshold = $this->windowThreshold()->format('Y-m-d H:i:s');
        $criticalThreshold = now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s');
        $viewerUserId = $viewerUserId !== null && $viewerUserId > 0 ? $viewerUserId : 0;

        return $query
            ->orderByRaw(
                'CASE
                    WHEN needs_human = 1
                        AND assigned_user_id IS NULL
                        AND COALESCE(wh_active_handoff.queued_at, whatsapp_conversations.handoff_requested_at) IS NOT NULL
                        AND COALESCE(wh_active_handoff.queued_at, whatsapp_conversations.handoff_requested_at) <= ? THEN 140
                    WHEN needs_human = 1
                        AND assigned_user_id IS NULL
                        AND COALESCE(wh_active_handoff.priority, "") = "high" THEN 130
                    WHEN needs_human = 1 AND assigned_user_id IS NULL AND unread_count > 0 THEN 120
                    WHEN needs_human = 1 AND assigned_user_id IS NULL THEN 110
                    WHEN ? > 0 AND needs_human = 1 AND assigned_user_id = ? AND unread_count > 0 THEN 100
                    WHEN ? > 0 AND needs_human = 1 AND assigned_user_id = ? THEN 90
                    WHEN needs_human = 1 AND unread_count > 0 AND latest_inbound_at IS NOT NULL AND latest_inbound_at >= ? THEN 80
                    WHEN needs_human = 1 AND latest_inbound_at IS NOT NULL AND latest_inbound_at >= ? THEN 70
                    WHEN needs_human = 1 AND unread_count > 0 THEN 60
                    WHEN needs_human = 1 THEN 50
                    ELSE 10
                END DESC',
                [$criticalThreshold, $viewerUserId, $viewerUserId, $viewerUserId, $viewerUserId, $threshold, $threshold]
            )
            ->orderByDesc('unread_count')
            ->orderByDesc('last_message_at');
    }

    private function applyActiveHandoffJoin(Builder $query): Builder
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return $query;
        }

        $latestActiveHandoffs = DB::table('whatsapp_handoffs')
            ->selectRaw('MAX(id) AS id, conversation_id')
            ->whereIn('status', ['queued', 'assigned'])
            ->groupBy('conversation_id');

        return $query
            ->leftJoinSub($latestActiveHandoffs, 'wh_active_handoff_ids', function ($join): void {
                $join->on('wh_active_handoff_ids.conversation_id', '=', 'whatsapp_conversations.id');
            })
            ->leftJoin('whatsapp_handoffs as wh_active_handoff', 'wh_active_handoff.id', '=', 'wh_active_handoff_ids.id')
            ->addSelect([
                'wh_active_handoff.status as active_handoff_status',
                'wh_active_handoff.priority as active_handoff_priority',
                'wh_active_handoff.topic as active_handoff_topic',
                'wh_active_handoff.queued_at as active_handoff_queued_at',
                'wh_active_handoff.assigned_at as active_handoff_assigned_at',
            ]);
    }

    private function applyAttributionJoin(Builder $query): Builder
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return $query;
        }

        return $query
            ->leftJoin('whatsapp_conversation_attributions as wa_attr', 'wa_attr.conversation_id', '=', 'whatsapp_conversations.id')
            ->addSelect([
                'wa_attr.source_category as attribution_source_category',
                'wa_attr.initial_intent as attribution_initial_intent',
                'wa_attr.conversation_type as attribution_conversation_type',
                'wa_attr.patient_segment as attribution_patient_segment',
            ]);
    }

    private function applyCriticalBacklogFilter(Builder $query): Builder
    {
        return $query
            ->where('needs_human', true)
            ->whereNull('assigned_user_id')
            ->whereRaw($this->criticalBacklogSql(), [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]);
    }

    private function applyOperationalQueueFilter(Builder $query, string $queue): Builder
    {
        return match ($queue) {
            'captacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $builder
                    ->where('wh_active_handoff.topic', 'like', 'captacion_%')
                    ->orWhere(function (Builder $captacion): void {
                        $captacion
                            ->whereIn(DB::raw('COALESCE(wa_attr.source_category, "")'), ['ad', 'organic_direct'])
                            ->where(DB::raw('COALESCE(wa_attr.patient_segment, "unknown")'), 'new_patient');
                    })
                    ->orWhere(DB::raw('COALESCE(wa_attr.initial_intent, "")'), 'booking');
            })->whereRaw('NOT (' . $this->criticalBacklogSql() . ')', [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]),
            'operacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $builder
                    ->where('wh_active_handoff.topic', 'like', 'operacion_%')
                    ->orWhereIn(DB::raw('COALESCE(wa_attr.source_category, "")'), ['support_operational', 'campaign_outbound'])
                    ->orWhereIn(DB::raw('COALESCE(wa_attr.conversation_type, "")'), ['reschedule', 'cancel', 'results', 'human_help', 'campaign_response']);
            })->whereRaw('NOT (' . $this->criticalBacklogSql() . ')', [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]),
            'informacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $builder
                    ->whereIn(DB::raw('COALESCE(wh_active_handoff.topic, "")'), ['faq_escalada', 'promociones', 'caso_especial'])
                    ->orWhere(function (Builder $fallback): void {
                        $fallback
                            ->whereRaw('COALESCE(wh_active_handoff.topic, "") = ""')
                            ->whereRaw('COALESCE(wa_attr.initial_intent, "") NOT IN ("booking")')
                            ->whereRaw('COALESCE(wa_attr.conversation_type, "") NOT IN ("reschedule", "cancel", "results", "human_help", "campaign_response")')
                            ->whereRaw('COALESCE(wa_attr.source_category, "") NOT IN ("ad", "organic_direct", "support_operational", "campaign_outbound")');
                    });
            })->whereRaw('NOT (' . $this->criticalBacklogSql() . ')', [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]),
            default => $query,
        };
    }

    private function applyWindowOpenFilter(Builder $query): Builder
    {
        $threshold = $this->windowThreshold()->format('Y-m-d H:i:s');

        return $query->whereExists(function ($subquery) use ($threshold): void {
            $subquery
                ->selectRaw('1')
                ->from('whatsapp_messages as wm')
                ->whereColumn('wm.conversation_id', 'whatsapp_conversations.id')
                ->where('wm.direction', 'inbound')
                ->whereRaw('COALESCE(wm.message_timestamp, wm.created_at) >= ?', [$threshold]);
        });
    }

    private function applyNeedsTemplateFilter(Builder $query): Builder
    {
        $threshold = $this->windowThreshold()->format('Y-m-d H:i:s');

        return $query->whereNotExists(function ($subquery) use ($threshold): void {
            $subquery
                ->selectRaw('1')
                ->from('whatsapp_messages as wm')
                ->whereColumn('wm.conversation_id', 'whatsapp_conversations.id')
                ->where('wm.direction', 'inbound')
                ->whereRaw('COALESCE(wm.message_timestamp, wm.created_at) >= ?', [$threshold]);
        });
    }

    /**
     * @param array<int, int|null> $assignedUserIds
     * @return array<int, array{name:string,role_name:?string}>
     */
    private function resolveAssignedUsers(array $assignedUserIds): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $ids = collect($assignedUserIds)
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->with(Schema::hasTable('roles') ? ['role'] : [])
            ->whereIn('id', $ids->all())
            ->get();

        return $users
            ->mapWithKeys(function (User $user): array {
                $name = trim((string) $user->nombre);
                if ($name === '') {
                    $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);
                }
                if ($name === '') {
                    $name = (string) $user->username;
                }

                return [
                    (int) $user->id => [
                        'name' => $name,
                        'role_name' => $user->role?->name,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(WhatsappMessage $message): array
    {
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $messageType = (string) ($message->message_type ?? 'text');
        $media = is_array($rawPayload[$messageType] ?? null) ? $rawPayload[$messageType] : [];
        $isMedia = in_array($messageType, ['image', 'video', 'document', 'audio'], true);
        $mediaId = $isMedia ? trim((string) ($media['id'] ?? '')) : '';
        $directLink = $isMedia ? trim((string) ($media['link'] ?? '')) : '';
        $caption = trim((string) ($media['caption'] ?? ''));
        $filename = trim((string) ($media['filename'] ?? ''));
        $mimeType = trim((string) ($media['mime_type'] ?? ''));

        return [
            'id' => $message->id,
            'wa_message_id' => $message->wa_message_id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'status' => $message->status,
            'message_timestamp' => optional($message->message_timestamp)?->toISOString(),
            'sent_at' => optional($message->sent_at)?->toISOString(),
            'delivered_at' => optional($message->delivered_at)?->toISOString(),
            'read_at' => optional($message->read_at)?->toISOString(),
            'media' => $isMedia ? [
                'id' => $mediaId !== '' ? $mediaId : null,
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'filename' => $filename !== '' ? $filename : null,
                'caption' => $caption !== '' ? $caption : null,
                'voice' => (bool) ($media['voice'] ?? false),
                'download_url' => $mediaId !== '' || $directLink !== '' ? '/v2/whatsapp/api/messages/' . $message->id . '/media' : null,
            ] : null,
        ];
    }

    private function resolveOwnershipState(WhatsappConversation $conversation, ?int $viewerUserId): string
    {
        if (!(bool) $conversation->needs_human) {
            return 'resolved';
        }

        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        if ($assignedUserId <= 0) {
            return 'queue';
        }

        if ($viewerUserId !== null && $viewerUserId > 0 && $assignedUserId === $viewerUserId) {
            return 'mine';
        }

        return 'assigned';
    }

    private function resolveMessagingWindowState(WhatsappConversation $conversation): string
    {
        $latestInbound = $conversation->getAttribute('latest_inbound_at');
        if ($latestInbound === null || $latestInbound === '') {
            return 'needs_template';
        }

        $latestInboundAt = CarbonImmutable::parse((string) $latestInbound);

        return $latestInboundAt->greaterThanOrEqualTo($this->windowThreshold())
            ? 'window_open'
            : 'needs_template';
    }

    private function resolveOperationalQueue(WhatsappConversation $conversation): string
    {
        if (!(bool) $conversation->needs_human) {
            return 'resolved';
        }

        $handoffTopic = $this->scalarString($conversation->getAttribute('active_handoff_topic'));
        if ($handoffTopic !== '') {
            if (str_starts_with($handoffTopic, 'captacion_')) {
                return 'captacion';
            }
            if (str_starts_with($handoffTopic, 'operacion_')) {
                return 'operacion';
            }
            if (in_array($handoffTopic, ['faq_escalada', 'promociones', 'caso_especial'], true)) {
                return 'informacion';
            }
        }

        $queueAgeMinutes = $this->resolveQueueAgeMinutes($conversation);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        if ($assignedUserId <= 0 && $queueAgeMinutes !== null && $queueAgeMinutes >= (24 * 60)) {
            return 'critical_backlog';
        }

        $sourceCategory = $this->scalarString($conversation->getAttribute('attribution_source_category'));
        $conversationType = $this->scalarString($conversation->getAttribute('attribution_conversation_type'));
        $patientSegment = $this->scalarString($conversation->getAttribute('attribution_patient_segment'));
        $initialIntent = $this->scalarString($conversation->getAttribute('attribution_initial_intent'));

        if (in_array($sourceCategory, ['ad', 'organic_direct'], true) && $patientSegment === 'new_patient') {
            return 'captacion';
        }

        if ($initialIntent === 'booking') {
            return 'captacion';
        }

        if (in_array($sourceCategory, ['support_operational', 'campaign_outbound'], true)
            || in_array($conversationType, ['reschedule', 'cancel', 'results', 'human_help', 'campaign_response'], true)
        ) {
            return 'operacion';
        }

        return 'informacion';
    }

    private function operationalQueueLabel(string $queue): string
    {
        return match ($queue) {
            'critical_backlog' => 'Backlog >24h',
            'captacion' => 'Captación',
            'operacion' => 'Operación',
            'informacion' => 'Información',
            'resolved' => 'Resuelto',
            default => 'Sin clasificar',
        };
    }

    private function handoffPriorityLabel(string $priority): ?string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'normal' => 'Normal',
            default => null,
        };
    }

    private function resolveQueueAgeMinutes(WhatsappConversation $conversation): ?int
    {
        $reference = $conversation->getAttribute('active_handoff_queued_at') ?? $conversation->handoff_requested_at;
        if ($reference === null || $reference === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $reference)->diffInMinutes(now()->toImmutable());
        } catch (\Throwable) {
            return null;
        }
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function criticalBacklogSql(): string
    {
        return 'COALESCE(wh_active_handoff.queued_at, whatsapp_conversations.handoff_requested_at) <= ?';
    }

    private function resolveConversationPriority(WhatsappConversation $conversation): string
    {
        if ($this->resolveOperationalQueue($conversation) === 'critical_backlog') {
            return 'critical';
        }

        $priority = $this->scalarString($conversation->getAttribute('active_handoff_priority'));
        if (in_array($priority, ['critical', 'high', 'normal'], true)) {
            return $priority;
        }

        return match ($this->resolveOperationalQueue($conversation)) {
            'critical_backlog' => 'critical',
            'captacion', 'operacion' => 'high',
            'informacion' => 'normal',
            default => '',
        };
    }

    private function resolveMessagingWindowLabel(WhatsappConversation $conversation): string
    {
        return $this->resolveMessagingWindowState($conversation) === 'window_open'
            ? '24h abierta'
            : 'Requiere plantilla';
    }

    private function windowThreshold(): CarbonImmutable
    {
        return now()->toImmutable()->subHours(24);
    }

    /**
     * @param array{name:string,role_name:?string}|null $assignedUser
     */
    private function resolveOwnershipLabel(
        WhatsappConversation $conversation,
        ?int $viewerUserId,
        ?array $assignedUser,
        ?string $handoffRoleName,
    ): string {
        return match ($this->resolveOwnershipState($conversation, $viewerUserId)) {
            'resolved' => 'Resuelto',
            'queue' => $handoffRoleName !== null && trim($handoffRoleName) !== '' ? 'En cola · ' . trim($handoffRoleName) : 'En cola',
            'mine' => 'Asignado a mí',
            default => 'Asignado a ' . trim((string) ($assignedUser['name'] ?? ('#' . (int) $conversation->assigned_user_id))),
        };
    }

    /**
     * @param array<int, int|null> $roleIds
     * @return array<int, string>
     */
    private function resolveRoleLabels(array $roleIds): array
    {
        if (!Schema::hasTable('roles')) {
            return [];
        }

        $ids = collect($roleIds)
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Role::query()
            ->whereIn('id', $ids->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }
}
