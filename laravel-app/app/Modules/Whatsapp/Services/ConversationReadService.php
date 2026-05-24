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
            'requires_attention',
            'in_progress',
            'waiting_patient',
            'scheduled',
            'closed',
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
     * Returns the full operational trail (trazabilidad) for a conversation.
     * Combines: conversation creation, handoff events (filtered), and template messages.
     * Sorted chronologically by timestamp then id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConversationTrail(int $conversationId): array
    {
        $entries = [];

        // ── 0. Attribution / origen (ad, orgánico, campaña) ──────────────────
        $attr = null;
        if (Schema::hasTable('whatsapp_conversation_attributions')) {
            $attr = DB::table('whatsapp_conversation_attributions')
                ->where('conversation_id', $conversationId)
                ->first();
        }

        // Supplement with referral data from the first inbound message raw_payload
        $firstInbound = DB::table('whatsapp_messages')
            ->where('conversation_id', $conversationId)
            ->where('direction', 'inbound')
            ->orderBy('id')
            ->first(['id', 'raw_payload', 'message_timestamp', 'created_at']);

        $referral = [];
        if ($firstInbound) {
            $raw = is_string($firstInbound->raw_payload)
                ? json_decode($firstInbound->raw_payload, true)
                : (is_array($firstInbound->raw_payload) ? $firstInbound->raw_payload : []);
            $referral = is_array($raw['referral'] ?? null) ? $raw['referral'] : [];
        }

        // Resolve the best available ad/origin data
        $sourceCategory  = trim((string) ($attr->source_category ?? ''));
        $headline        = trim((string) ($attr->headline ?? $referral['headline'] ?? ''));
        $sourceId        = trim((string) ($attr->source_id ?? $referral['source_id'] ?? ''));
        $welcomeMsg      = trim((string) ($attr->welcome_message_text ?? ''));
        $initialIntent   = trim((string) ($attr->initial_intent ?? ''));
        $patientSegment  = trim((string) ($attr->patient_segment ?? ''));
        $conversationType = trim((string) ($attr->conversation_type ?? ''));
        $firstSeenAt     = $attr->first_seen_at ?? ($firstInbound->message_timestamp ?? ($firstInbound->created_at ?? null));

        // Map source_category → human label + icon
        $sourceLabels = [
            'ad'                  => ['label' => 'Anuncio de Meta Ads',       'icon' => 'ad'],
            'organic_direct'      => ['label' => 'Contacto orgánico directo', 'icon' => 'organic'],
            'campaign_outbound'   => ['label' => 'Campaña saliente',          'icon' => 'campaign'],
            'support_operational' => ['label' => 'Soporte / seguimiento',     'icon' => 'support'],
        ];
        $sourceMeta = $sourceLabels[$sourceCategory] ?? null;

        if ($sourceMeta !== null || $headline !== '' || $sourceCategory !== '') {
            $originLabel = $sourceMeta['label'] ?? ucfirst(str_replace('_', ' ', $sourceCategory));
            $originIcon  = $sourceMeta['icon'] ?? 'start';

            $originNotes = [];
            if ($headline !== '') {
                $originNotes[] = '📢 ' . $headline;
            }
            if ($sourceId !== '') {
                $originNotes[] = 'ID: ' . $sourceId;
            }
            if ($welcomeMsg !== '') {
                $originNotes[] = '💬 "' . mb_substr($welcomeMsg, 0, 200) . '"';
            }

            $entries[] = [
                'id'          => -1,
                'sort_key'    => ($firstSeenAt ?? '1970-01-01 00:00:00') . '_origin',
                'event_type'  => 'origin',
                'event_label' => 'Origen: ' . $originLabel,
                'icon'        => $originIcon,
                'notes'       => $originNotes !== [] ? implode("\n", $originNotes) : null,
                'actor_name'  => null,
                'created_at'  => $firstSeenAt,
            ];
        }

        // Bot intent / classification entry
        $intentLabels = [
            'booking'          => 'Agendar cita',
            'information'      => 'Consulta de información',
            'reschedule'       => 'Re-agendar cita',
            'cancel'           => 'Cancelar cita',
            'results'          => 'Resultados',
            'human_help'       => 'Solicitud de agente humano',
            'campaign_response'=> 'Respuesta a campaña',
        ];
        $segmentLabels = [
            'new_patient'      => 'Paciente nuevo',
            'existing_patient' => 'Paciente existente',
            'unknown'          => null,
        ];

        $intentLabel   = $intentLabels[$initialIntent] ?? null;
        $segmentLabel  = $segmentLabels[$patientSegment] ?? null;

        if ($intentLabel !== null || $segmentLabel !== null || $conversationType !== '') {
            $intentNotes = array_filter([
                $intentLabel  ? '🎯 Intención: ' . $intentLabel : null,
                $segmentLabel ? '👤 ' . $segmentLabel : null,
                $conversationType !== '' ? '🏷 Tipo: ' . $conversationType : null,
            ]);

            $entries[] = [
                'id'          => -2,
                'sort_key'    => ($firstSeenAt ?? '1970-01-01 00:00:00') . '_intent',
                'event_type'  => 'intent_detected',
                'event_label' => 'Clasificación del bot',
                'icon'        => 'intent',
                'notes'       => implode("\n", $intentNotes),
                'actor_name'  => 'Bot',
                'created_at'  => $firstSeenAt,
            ];
        }

        // ── 1. Conversation creation ──────────────────────────────────────────
        $conv = DB::table('whatsapp_conversations')
            ->select('created_at', 'display_name', 'wa_number')
            ->where('id', $conversationId)
            ->first();

        if ($conv) {
            $entries[] = [
                'id'          => 0,
                'sort_key'    => $conv->created_at . '_0000000',
                'event_type'  => 'conversation_created',
                'event_label' => 'Conversación iniciada',
                'icon'        => 'start',
                'notes'       => null,
                'actor_name'  => null,
                'created_at'  => $conv->created_at,
            ];
        }

        // ── 2. Handoff events (only operationally relevant ones) ──────────────
        // Skip internal notify/system chatter: notify_started, notify_selection,
        // notified, notify_failed, conversation_update_failed.
        $relevantTypes = ['requested', 'queued', 'requeued', 'assigned', 'transferred', 'expired', 'resolved'];
        $placeholders  = implode(',', array_fill(0, count($relevantTypes), '?'));

        $handoffRows = DB::select(
            "SELECT
                whe.id,
                whe.event_type,
                whe.notes,
                whe.created_at,
                whe.actor_user_id,
                TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS actor_name,
                wh.topic,
                wh.handoff_role_id,
                r.name AS role_name,
                wh.assigned_agent_id,
                TRIM(CONCAT(COALESCE(ua.first_name,''),' ',COALESCE(ua.last_name,''))) AS assigned_agent_name
            FROM whatsapp_handoffs wh
            JOIN whatsapp_handoff_events whe ON whe.handoff_id = wh.id
            LEFT JOIN users u  ON u.id  = whe.actor_user_id
            LEFT JOIN users ua ON ua.id = wh.assigned_agent_id
            LEFT JOIN roles r  ON r.id  = wh.handoff_role_id
            WHERE wh.conversation_id = ?
              AND whe.event_type IN ($placeholders)
            ORDER BY whe.created_at ASC, whe.id ASC",
            array_merge([$conversationId], $relevantTypes)
        );

        $topicLabels = [
            'captacion_agendar'      => 'Captación · Agendar',
            'captacion_informacion'  => 'Captación · Información',
            'operacion_cita_vigente' => 'Operación · Cita vigente',
            'operacion_resultados'   => 'Operación · Resultados',
            'faq_escalada'           => 'Información · Escalada',
            'promociones'            => 'Información · Promociones',
            'caso_especial'          => 'Caso especial',
        ];

        $eventMeta = [
            'requested'   => ['label' => 'Solicitud de agente',   'icon' => 'requested'],
            'queued'      => ['label' => 'En cola',                'icon' => 'queued'],
            'requeued'    => ['label' => 'Re-encolado',            'icon' => 'queued'],
            'assigned'    => ['label' => 'Asignado a agente',      'icon' => 'assigned'],
            'transferred' => ['label' => 'Derivado',               'icon' => 'transferred'],
            'expired'     => ['label' => 'Expirado sin atender',   'icon' => 'expired'],
            'resolved'    => ['label' => 'Resuelto',               'icon' => 'resolved'],
        ];

        foreach ($handoffRows as $row) {
            $eventType  = (string) ($row->event_type ?? '');
            $actorName  = trim((string) ($row->actor_name ?? ''));
            $topic      = (string) ($row->topic ?? '');
            $meta       = $eventMeta[$eventType] ?? ['label' => ucfirst($eventType), 'icon' => 'default'];

            // Build a human-readable description
            $description = null;
            if ($eventType === 'assigned') {
                $agent = trim((string) ($row->assigned_agent_name ?? ''));
                $description = $agent !== '' ? "Agente: {$agent}" : null;
            } elseif ($eventType === 'transferred') {
                $agent = trim((string) ($row->assigned_agent_name ?? ''));
                $role  = trim((string) ($row->role_name ?? ''));
                if ($agent !== '') {
                    $description = "A: {$agent}";
                } elseif ($role !== '') {
                    $description = "Cola: {$role}";
                }
            } elseif (in_array($eventType, ['queued', 'requeued', 'requested'], true)) {
                $rawNotes = trim((string) ($row->notes ?? ''));
                // Extract human-readable reason from Flowmaker notes
                if (str_contains($rawNotes, ':')) {
                    $parts = explode(':', $rawNotes, 2);
                    $description = trim($parts[1]);
                } elseif ($rawNotes !== '') {
                    $description = $rawNotes;
                }
                if ($topic !== '') {
                    $topicLabel  = $topicLabels[$topic] ?? $topic;
                    $description = $description ? "{$description} · {$topicLabel}" : $topicLabel;
                }
            } elseif ($eventType === 'expired') {
                $description = 'Sin respuesta del agente';
            }

            $entries[] = [
                'id'          => (int) $row->id,
                'sort_key'    => $row->created_at . '_' . str_pad((string) $row->id, 7, '0', STR_PAD_LEFT),
                'event_type'  => $eventType,
                'event_label' => $meta['label'],
                'icon'        => $meta['icon'],
                'notes'       => $description,
                'actor_name'  => $actorName !== '' ? $actorName : null,
                'created_at'  => $row->created_at,
            ];
        }

        // ── 3. Template messages sent during the conversation ─────────────────
        $templates = DB::select(
            "SELECT id, message_timestamp, created_at, body, direction
             FROM whatsapp_messages
             WHERE conversation_id = ?
               AND message_type = 'template'
             ORDER BY id ASC",
            [$conversationId]
        );

        foreach ($templates as $tpl) {
            $ts         = $tpl->message_timestamp ?? $tpl->created_at;
            $senderName = null;

            $entries[] = [
                'id'          => (int) $tpl->id * -1, // negative to avoid id collision
                'sort_key'    => $ts . '_t' . str_pad((string) $tpl->id, 7, '0', STR_PAD_LEFT),
                'event_type'  => 'template_sent',
                'event_label' => 'Plantilla enviada',
                'icon'        => 'template',
                'notes'       => trim((string) ($tpl->body ?? '')),
                'actor_name'  => $senderName,
                'created_at'  => $ts,
            ];
        }

        // ── Sort chronologically ──────────────────────────────────────────────
        usort($entries, fn (array $a, array $b): int => strcmp($a['sort_key'], $b['sort_key']));

        // ── Serialize timestamps as UTC ISO for frontend ──────────────────────
        $appTz = config('app.timezone', 'UTC');

        return array_values(array_map(function (array $entry) use ($appTz): array {
            unset($entry['sort_key']);
            $entry['created_at'] = CarbonImmutable::parse(
                (string) ($entry['created_at'] ?? ''),
                $appTz
            )->toISOString();

            return $entry;
        }, $entries));
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
        $operationalStatus = $this->resolveOperationalStatus($conversation, $viewerUserId);
        $priorityScore = $this->resolvePriorityScore($conversation, $viewerUserId);
        $priorityLevel = $this->resolvePriorityLevel($priorityScore);

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
            'last_message_actor_label' => $this->lastMessageActorLabel((string) ($conversation->last_message_direction ?? ''), (string) ($conversation->last_message_type ?? '')),
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
            'operational_status' => $operationalStatus,
            'operational_status_label' => $this->resolveOperationalStatusLabel($operationalStatus),
            'priority_score' => $priorityScore,
            'priority_level' => $priorityLevel,
            'priority_level_label' => $this->resolvePriorityLevelLabel($priorityLevel),
            'priority_reasons' => $this->resolvePriorityReasons($conversation, $viewerUserId),
            'ownership_state' => $this->resolveOwnershipState($conversation, $viewerUserId),
            'ownership_label' => $this->resolveOwnershipLabel($conversation, $viewerUserId, $assignedUser, $handoffRoleName),
            'messaging_window_state' => $this->resolveMessagingWindowState($conversation),
            'messaging_window_label' => $this->resolveMessagingWindowLabel($conversation),
            'can_send_freeform' => $this->resolveMessagingWindowState($conversation) === 'window_open',
            'assigned_at' => optional($conversation->assigned_at)?->toISOString(),
            'handoff_requested_at' => optional($conversation->handoff_requested_at)?->toISOString(),
            'closed_at' => optional($conversation->closed_at)?->toISOString(),
            'closed_by_user_id' => $conversation->closed_by_user_id,
            'close_reason' => $conversation->close_reason,
            'close_reason_label' => $this->closeReasonLabel((string) ($conversation->close_reason ?? '')),
            'unread_count' => (int) $conversation->unread_count,
            'source' => 'laravel-v2',
            // Attribution / origen
            'attribution_source_category' => ($v = $this->scalarString($conversation->getAttribute('attribution_source_category'))) !== '' ? $v : null,
            'attribution_headline'         => ($v = $this->scalarString($conversation->getAttribute('attribution_headline'))) !== '' ? $v : null,
            'attribution_source_id'        => ($v = $this->scalarString($conversation->getAttribute('attribution_source_id'))) !== '' ? $v : null,
            'attribution_initial_intent'   => ($v = $this->scalarString($conversation->getAttribute('attribution_initial_intent'))) !== '' ? $v : null,
            'attribution_patient_segment'  => ($v = $this->scalarString($conversation->getAttribute('attribution_patient_segment'))) !== '' ? $v : null,
            'attribution_welcome_message'  => ($v = $this->scalarString($conversation->getAttribute('attribution_welcome_message'))) !== '' ? $v : null,
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

        if (Schema::hasTable('whatsapp_sigcenter_bookings')) {
            $query->selectSub(function ($subquery): void {
                $subquery
                    ->from('whatsapp_sigcenter_bookings as wsb')
                    ->selectRaw('1')
                    ->whereColumn('wsb.conversation_id', 'whatsapp_conversations.id')
                    ->whereIn('wsb.status', ['created', 'confirmed'])
                    ->limit(1);
            }, 'has_sigcenter_booking');
        } else {
            $query->addSelect(DB::raw('0 as has_sigcenter_booking'));
        }

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
                ->where('whatsapp_conversations.display_name', 'like', '%' . $search . '%')
                ->orWhere('whatsapp_conversations.patient_full_name', 'like', '%' . $search . '%')
                ->orWhere('whatsapp_conversations.patient_hc_number', 'like', '%' . $search . '%')
                ->orWhere('whatsapp_conversations.wa_number', 'like', '%' . $search . '%')
                ->orWhere('whatsapp_conversations.last_message_preview', 'like', '%' . $search . '%');
        });
    }

    private function applyFilter(Builder $query, string $filter, ?int $viewerUserId): Builder
    {
        return match (trim($filter) !== '' ? trim($filter) : 'all') {
            'requires_attention' => $this->applyOperationalStatusFilter($query, 'requires_attention', $viewerUserId),
            'in_progress' => $this->applyOperationalStatusFilter($query, 'in_progress', $viewerUserId),
            'waiting_patient' => $this->applyOperationalStatusFilter($query, 'waiting_patient', $viewerUserId),
            'scheduled' => $this->applyOperationalStatusFilter($query, 'scheduled', $viewerUserId),
            'closed' => $this->applyOperationalStatusFilter($query, 'closed', $viewerUserId),
            'new' => $this->applyOperationalStatusFilter($query, 'new', $viewerUserId),
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

    private function applyOperationalStatusFilter(Builder $query, string $status, ?int $viewerUserId): Builder
    {
        return match ($status) {
            'requires_attention' => $this->excludeScheduled($query->where('needs_human', true)->whereNull('assigned_user_id')),
            'in_progress' => $this->excludeScheduled($query
                ->where('needs_human', true)
                ->whereNotNull('assigned_user_id')
                ->where('last_message_direction', 'inbound')),
            'waiting_patient' => $this->excludeScheduled($query
                ->where('needs_human', true)
                ->whereNotNull('assigned_user_id')
                ->where(function (Builder $builder): void {
                    $builder
                        ->where('last_message_direction', '<>', 'inbound')
                        ->orWhereNull('last_message_direction');
                })),
            'scheduled' => $this->applyScheduledFilter($query),
            'closed' => $this->applyClosedFilter($query),
            'new' => $this->applyNewConversationFilter($query),
            default => $query,
        };
    }

    private function applyNewConversationFilter(Builder $query): Builder
    {
        $query
            ->where('needs_human', false)
            ->whereNull('assigned_user_id')
            ->whereNotExists(function ($subquery): void {
                $subquery
                    ->selectRaw('1')
                    ->from('whatsapp_messages as wm')
                    ->whereColumn('wm.conversation_id', 'whatsapp_conversations.id');
            });

        if (Schema::hasColumn('whatsapp_conversations', 'close_reason')) {
            $query->whereNull('close_reason');
        }

        return $query;
    }

    private function applyScheduledFilter(Builder $query): Builder
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($subquery): void {
            $subquery
                ->selectRaw('1')
                ->from('whatsapp_sigcenter_bookings as wsb')
                ->whereColumn('wsb.conversation_id', 'whatsapp_conversations.id')
                ->whereIn('wsb.status', ['created', 'confirmed']);
        });
    }

    private function applyClosedFilter(Builder $query): Builder
    {
        $query->where('needs_human', false);

        if (Schema::hasColumn('whatsapp_conversations', 'close_reason')) {
            $query->whereIn('close_reason', [
                'resolved',
                'followup_closed',
                'not_interested',
                'no_response',
                'duplicate',
                'scheduled_elsewhere',
            ]);
        }

        return $query;
    }

    private function excludeScheduled(Builder $query): Builder
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $query;
        }

        return $query->whereNotExists(function ($subquery): void {
            $subquery
                ->selectRaw('1')
                ->from('whatsapp_sigcenter_bookings as wsb')
                ->whereColumn('wsb.conversation_id', 'whatsapp_conversations.id')
                ->whereIn('wsb.status', ['created', 'confirmed']);
        });
    }

    private function applyPriorityOrdering(Builder $query, ?int $viewerUserId): Builder
    {
        $viewerUserId = $viewerUserId !== null && $viewerUserId > 0 ? $viewerUserId : 0;

        return $query
            ->orderByRaw($this->priorityScoreSql($viewerUserId) . ' DESC')
            ->orderByDesc('unread_count')
            ->orderByDesc('last_message_at');
    }

    private function priorityScoreSql(int $viewerUserId): string
    {
        $criticalThreshold = now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s');
        $soonThreshold = now()->toImmutable()->subHours(20)->format('Y-m-d H:i:s');
        $queuedAtSql = $this->activeHandoffQueuedAtSql();
        $prioritySql = $this->activeHandoffPrioritySql();
        $scheduledSql = $this->scheduledExistsSql();

        return sprintf(
            'CASE
                WHEN needs_human = 0 THEN 0
                WHEN %1$s THEN 40
                WHEN needs_human = 1 AND assigned_user_id IS NULL AND %2$s IS NOT NULL AND %2$s <= "%3$s" THEN 180
                WHEN needs_human = 1 AND assigned_user_id IS NULL AND %4$s = "high" THEN 170
                WHEN needs_human = 1 AND assigned_user_id IS NULL AND unread_count > 0 THEN 160
                WHEN needs_human = 1 AND assigned_user_id IS NULL THEN 150
                WHEN %5$d > 0 AND needs_human = 1 AND assigned_user_id = %5$d AND last_message_direction = "inbound" AND unread_count > 0 THEN 140
                WHEN needs_human = 1 AND assigned_user_id IS NOT NULL AND last_message_direction = "inbound" AND unread_count > 0 THEN 130
                WHEN needs_human = 1 AND assigned_user_id IS NOT NULL AND last_message_direction = "inbound" THEN 120
                WHEN needs_human = 1 AND latest_inbound_at IS NOT NULL AND latest_inbound_at <= "%6$s" THEN 90
                WHEN needs_human = 1 AND assigned_user_id IS NOT NULL THEN 70
                WHEN needs_human = 1 THEN 50
                ELSE 0
            END',
            $scheduledSql,
            $queuedAtSql,
            $criticalThreshold,
            $prioritySql,
            $viewerUserId,
            $soonThreshold
        );
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
                'wa_attr.headline as attribution_headline',
                'wa_attr.source_id as attribution_source_id',
                'wa_attr.welcome_message_text as attribution_welcome_message',
                'wa_attr.first_seen_at as attribution_first_seen_at',
                'wa_attr.ctwa_clid as attribution_ctwa_clid',
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
        $topicSql = $this->activeHandoffTopicSql();
        $sourceSql = $this->attributionColumnSql('source_category');
        $patientSegmentSql = $this->attributionColumnSql('patient_segment');
        $initialIntentSql = $this->attributionColumnSql('initial_intent');
        $conversationTypeSql = $this->attributionColumnSql('conversation_type');

        return match ($queue) {
            'captacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $topicSql = $this->activeHandoffTopicSql();
                $sourceSql = $this->attributionColumnSql('source_category');
                $patientSegmentSql = $this->attributionColumnSql('patient_segment');
                $initialIntentSql = $this->attributionColumnSql('initial_intent');

                $builder
                    ->whereRaw($topicSql . ' LIKE ?', ['captacion_%'])
                    ->orWhere(function (Builder $captacion): void {
                        $sourceSql = $this->attributionColumnSql('source_category');
                        $patientSegmentSql = $this->attributionColumnSql('patient_segment');

                        $captacion
                            ->whereIn(DB::raw($sourceSql), ['ad', 'organic_direct'])
                            ->where(DB::raw($patientSegmentSql), 'new_patient');
                    })
                    ->orWhere(DB::raw($initialIntentSql), 'booking');
            })->whereRaw('NOT (' . $this->criticalBacklogSql() . ')', [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]),
            'operacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $topicSql = $this->activeHandoffTopicSql();
                $sourceSql = $this->attributionColumnSql('source_category');
                $conversationTypeSql = $this->attributionColumnSql('conversation_type');

                $builder
                    ->whereRaw($topicSql . ' LIKE ?', ['operacion_%'])
                    ->orWhereIn(DB::raw($sourceSql), ['support_operational', 'campaign_outbound'])
                    ->orWhereIn(DB::raw($conversationTypeSql), ['reschedule', 'cancel', 'results', 'human_help', 'campaign_response']);
            })->whereRaw('NOT (' . $this->criticalBacklogSql() . ')', [now()->toImmutable()->subHours(24)->format('Y-m-d H:i:s')]),
            'informacion' => $query->where('needs_human', true)->where(function (Builder $builder): void {
                $topicSql = $this->activeHandoffTopicSql();
                $initialIntentSql = $this->attributionColumnSql('initial_intent');
                $conversationTypeSql = $this->attributionColumnSql('conversation_type');
                $sourceSql = $this->attributionColumnSql('source_category');

                $builder
                    ->whereIn(DB::raw($topicSql), ['faq_escalada', 'promociones', 'caso_especial'])
                    ->orWhere(function (Builder $fallback): void {
                        $topicSql = $this->activeHandoffTopicSql();
                        $initialIntentSql = $this->attributionColumnSql('initial_intent');
                        $conversationTypeSql = $this->attributionColumnSql('conversation_type');
                        $sourceSql = $this->attributionColumnSql('source_category');

                        $fallback
                            ->whereRaw($topicSql . ' = ""')
                            ->whereRaw($initialIntentSql . ' NOT IN ("booking")')
                            ->whereRaw($conversationTypeSql . ' NOT IN ("reschedule", "cancel", "results", "human_help", "campaign_response")')
                            ->whereRaw($sourceSql . ' NOT IN ("ad", "organic_direct", "support_operational", "campaign_outbound")');
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
            // Inbound timestamps are stored as UTC (from WhatsApp Unix epoch via createFromTimestampUTC).
            // Outbound timestamps are stored in the app local timezone (from now()).
            // Eloquent reads both without timezone info, so we must parse the raw DB value
            // with the correct timezone to produce a valid UTC ISO string for the frontend.
            'message_timestamp' => $this->serializeMessageTimestamp($message),
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

    private function serializeMessageTimestamp(WhatsappMessage $message): ?string
    {
        $raw = $message->getRawOriginal('message_timestamp');
        if ($raw === null || $raw === '') {
            return null;
        }

        // All timestamps in the DB are stored in the server's configured timezone.
        // Parse explicitly to avoid Eloquent re-interpreting the raw value,
        // then emit as UTC ISO so the frontend can convert to any browser timezone.
        return CarbonImmutable::parse($raw, config('app.timezone'))->toISOString();
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

        // latest_inbound_at is stored as UTC in MySQL — parse explicitly as UTC
        // to avoid misinterpretation when APP_TIMEZONE is not UTC.
        $latestInboundAt = CarbonImmutable::parse((string) $latestInbound, 'UTC');

        return $latestInboundAt->greaterThanOrEqualTo($this->windowThreshold())
            ? 'window_open'
            : 'needs_template';
    }

    private function resolveOperationalStatus(WhatsappConversation $conversation, ?int $viewerUserId): string
    {
        $closeReason = $this->scalarString($conversation->close_reason ?? null);
        $needsHuman = (bool) $conversation->needs_human;
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $lastDirection = trim((string) ($conversation->last_message_direction ?? ''));

        if ($this->hasSuccessfulBooking($conversation) && $closeReason === '') {
            return 'scheduled';
        }

        if (!$needsHuman) {
            return match ($closeReason) {
                'followup_closed' => 'closed_followup',
                'resolved' => 'resolved',
                'not_interested', 'no_response', 'duplicate', 'scheduled_elsewhere' => 'closed_other',
                default => $lastDirection === '' ? 'new' : 'resolved',
            };
        }

        if ($assignedUserId <= 0) {
            return 'requires_attention';
        }

        return $lastDirection === 'inbound' ? 'in_progress' : 'waiting_patient';
    }

    private function resolveOperationalStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Nuevo',
            'requires_attention' => 'Requiere atención',
            'in_progress' => 'En gestión',
            'waiting_patient' => 'Esperando paciente',
            'scheduled' => 'Agendado',
            'resolved' => 'Resuelto',
            'closed_followup' => 'Seguimiento cerrado',
            'closed_other' => 'Cerrado',
            default => 'Sin estado',
        };
    }

    private function resolvePriorityScore(WhatsappConversation $conversation, ?int $viewerUserId): int
    {
        if (!(bool) $conversation->needs_human) {
            return 0;
        }

        $score = 0;
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $unreadCount = (int) ($conversation->unread_count ?? 0);
        $lastDirection = trim((string) ($conversation->last_message_direction ?? ''));
        $queueAge = $this->resolveQueueAgeMinutes($conversation);

        if ($assignedUserId <= 0) {
            $score += 100;
        }
        if ($lastDirection === 'inbound' && $unreadCount > 0) {
            $score += 80;
        }
        if ($assignedUserId <= 0) {
            $score += 70;
        }
        if ($queueAge !== null && $queueAge >= 30) {
            $score += 60;
        }
        if (in_array($this->resolveOperationalQueue($conversation), ['critical_backlog', 'operacion', 'captacion'], true)) {
            $score += 50;
        }
        if ($this->latestInboundNearWindowClose($conversation)) {
            $score += 40;
        }
        if ($viewerUserId !== null && $viewerUserId > 0 && $assignedUserId === $viewerUserId && $lastDirection === 'inbound') {
            $score += 30;
        }
        if ($lastDirection !== 'inbound') {
            $score += 10;
        }

        return $score;
    }

    private function resolvePriorityLevel(int $score): string
    {
        return match (true) {
            $score >= 170 => 'critical',
            $score >= 90 => 'high',
            $score > 0 => 'normal',
            default => 'low',
        };
    }

    private function resolvePriorityLevelLabel(string $level): string
    {
        return match ($level) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'normal' => 'Normal',
            default => 'Baja',
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolvePriorityReasons(WhatsappConversation $conversation, ?int $viewerUserId): array
    {
        if (!(bool) $conversation->needs_human) {
            return [];
        }

        $reasons = [];
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $unreadCount = (int) ($conversation->unread_count ?? 0);
        $lastDirection = trim((string) ($conversation->last_message_direction ?? ''));
        $queueAge = $this->resolveQueueAgeMinutes($conversation);

        if ($assignedUserId <= 0) {
            $reasons[] = 'Sin agente asignado';
        }
        if ($lastDirection === 'inbound' && $unreadCount > 0) {
            $reasons[] = 'Paciente escribió y está sin leer';
        }
        if ($queueAge !== null && $queueAge >= 30) {
            $reasons[] = 'Más de 30 min en cola';
        }
        if ($this->resolveOperationalQueue($conversation) === 'critical_backlog') {
            $reasons[] = 'Backlog crítico';
        }
        if ($this->latestInboundNearWindowClose($conversation)) {
            $reasons[] = 'Ventana WhatsApp próxima a cerrar';
        }
        if ($viewerUserId !== null && $viewerUserId > 0 && $assignedUserId === $viewerUserId && $lastDirection === 'inbound') {
            $reasons[] = 'Asignada a ti con respuesta pendiente';
        }
        if ($lastDirection !== 'inbound') {
            $reasons[] = 'Esperando respuesta del paciente';
        }

        return array_values(array_unique($reasons));
    }

    private function lastMessageActorLabel(string $direction, string $messageType = ''): string
    {
        return match (trim(strtolower($direction))) {
            'inbound' => 'Paciente',
            'outbound' => trim(strtolower($messageType)) === 'template' ? 'Bot/plantilla' : 'Equipo/Bot',
            default => 'Sin mensajes',
        };
    }

    private function hasSuccessfulBooking(WhatsappConversation $conversation): bool
    {
        return (int) ($conversation->getAttribute('has_sigcenter_booking') ?? 0) > 0;
    }

    private function latestInboundNearWindowClose(WhatsappConversation $conversation): bool
    {
        $latestInbound = $conversation->getAttribute('latest_inbound_at');
        if ($latestInbound === null || $latestInbound === '') {
            return false;
        }

        try {
            $expiresAt = CarbonImmutable::parse((string) $latestInbound, 'UTC')->addHours(24);
            $minutesLeft = (int) now()->toImmutable()->diffInMinutes($expiresAt, false);

            return $minutesLeft > 0 && $minutesLeft <= 240;
        } catch (\Throwable) {
            return false;
        }
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

    private function closeReasonLabel(string $reason): string
    {
        return match ($reason) {
            'resolved' => 'Resuelto',
            'followup_closed' => 'Seguimiento cerrado',
            'not_interested' => 'No interesado',
            'no_response' => 'Sin respuesta',
            'duplicate' => 'Duplicado',
            'scheduled_elsewhere' => 'Agendado por otro canal',
            default => 'Cerrado',
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
        return $this->activeHandoffQueuedAtSql() . ' <= ?';
    }

    private function activeHandoffQueuedAtSql(): string
    {
        return Schema::hasTable('whatsapp_handoffs')
            ? 'COALESCE(wh_active_handoff.queued_at, whatsapp_conversations.handoff_requested_at)'
            : 'whatsapp_conversations.handoff_requested_at';
    }

    private function activeHandoffPrioritySql(): string
    {
        return Schema::hasTable('whatsapp_handoffs')
            ? 'COALESCE(wh_active_handoff.priority, "")'
            : '""';
    }

    private function activeHandoffTopicSql(): string
    {
        return Schema::hasTable('whatsapp_handoffs')
            ? 'COALESCE(wh_active_handoff.topic, "")'
            : '""';
    }

    private function scheduledExistsSql(): string
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return '0 = 1';
        }

        return 'EXISTS (
            SELECT 1 FROM whatsapp_sigcenter_bookings wsb
            WHERE wsb.conversation_id = whatsapp_conversations.id
              AND wsb.status IN ("created", "confirmed")
        )';
    }

    private function attributionColumnSql(string $column): string
    {
        return Schema::hasTable('whatsapp_conversation_attributions')
            ? 'COALESCE(wa_attr.' . $column . ', "")'
            : '""';
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
        if ($this->resolveMessagingWindowState($conversation) !== 'window_open') {
            return 'Requiere plantilla';
        }

        $latestInbound = $conversation->getAttribute('latest_inbound_at');
        if ($latestInbound === null || $latestInbound === '') {
            return '24h abierta';
        }

        // latest_inbound_at is stored as UTC — parse explicitly to avoid timezone drift.
        $expiresAt = CarbonImmutable::parse((string) $latestInbound, 'UTC')->addHours(24);
        $minutesLeft = (int) now()->toImmutable()->diffInMinutes($expiresAt, false);

        if ($minutesLeft <= 0) {
            return 'Ventana cerrada';
        }

        if ($minutesLeft < 60) {
            return "Cierra en {$minutesLeft}min";
        }

        $hoursLeft = floor($minutesLeft / 60);
        $minsLeft  = $minutesLeft % 60;

        return $minsLeft > 0
            ? "Cierra en {$hoursLeft}h {$minsLeft}min"
            : "Cierra en {$hoursLeft}h";
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
