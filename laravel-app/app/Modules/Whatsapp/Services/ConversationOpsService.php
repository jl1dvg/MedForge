<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappHandoff;
use App\Models\WhatsappHandoffEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use RuntimeException;

class ConversationOpsService
{
    public function __construct(
        private readonly WhatsappRealtimeService $realtime = new WhatsappRealtimeService(),
    ) {
    }

    /**
     * @return array{ids:array<int,int>,count:int}
     */
    public function previewExpiredHandoffs(): array
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return ['ids' => [], 'count' => 0];
        }

        $ids = WhatsappHandoff::query()
            ->where('status', 'assigned')
            ->whereNotNull('assigned_until')
            ->where('assigned_until', '<=', now())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'ids' => $ids,
            'count' => count($ids),
        ];
    }

    public function getAgentPresence(int $userId): string
    {
        if ($userId <= 0 || !Schema::hasTable('whatsapp_agent_presence')) {
            return 'available';
        }

        $status = DB::table('whatsapp_agent_presence')
            ->where('user_id', $userId)
            ->value('status');

        return $this->normalizePresenceStatus(is_string($status) ? $status : 'available');
    }

    public function setAgentPresence(int $userId, string $status): string
    {
        if ($userId <= 0) {
            throw new RuntimeException('Usuario no válido.');
        }

        if (!Schema::hasTable('whatsapp_agent_presence')) {
            throw new RuntimeException('La tabla whatsapp_agent_presence no está disponible.');
        }

        $normalizedStatus = $this->normalizePresenceStatus($status);

        DB::table('whatsapp_agent_presence')->updateOrInsert(
            ['user_id' => $userId],
            ['status' => $normalizedStatus, 'updated_at' => now()]
        );

        return $normalizedStatus;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAgents(): array
    {
        $presenceMap = [];
        if (Schema::hasTable('whatsapp_agent_presence')) {
            $presenceMap = DB::table('whatsapp_agent_presence')
                ->pluck('status', 'user_id')
                ->map(fn ($value) => is_string($value) && $value !== '' ? $value : 'available')
                ->all();
        }

        return User::query()
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
            ->map(function (User $user) use ($presenceMap): array {
                $displayName = trim((string) $user->nombre);
                if ($displayName === '') {
                    $displayName = trim((string) $user->first_name . ' ' . (string) $user->last_name);
                }
                if ($displayName === '') {
                    $displayName = (string) $user->username;
                }

                return [
                    'id' => (int) $user->id,
                    'name' => $displayName,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $user->role?->name,
                    'presence_status' => $presenceMap[$user->id] ?? 'available',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{agents:array<int,array<string,mixed>>,totals:array<string,int>}
     */
    public function summarizeAgentWorkload(): array
    {
        $agents = $this->listAgents();
        if ($agents === []) {
            return [
                'agents' => [],
                'totals' => [
                    'queued_open_count' => 0,
                    'assigned_open_count' => 0,
                    'unread_open_count' => 0,
                    'expiring_soon_count' => 0,
                ],
            ];
        }

        $conversationStats = [];
        $totals = [
            'queued_open_count' => 0,
            'assigned_open_count' => 0,
            'unread_open_count' => 0,
            'expiring_soon_count' => 0,
        ];

        if (Schema::hasTable('whatsapp_conversations')) {
            $conversationRows = DB::table('whatsapp_conversations')
                ->selectRaw('assigned_user_id, COUNT(*) as total_open, SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END) as unread_open')
                ->where('needs_human', true)
                ->groupBy('assigned_user_id')
                ->get();

            foreach ($conversationRows as $row) {
                $key = $row->assigned_user_id === null ? 'queue' : (string) (int) $row->assigned_user_id;
                $conversationStats[$key] = [
                    'total_open' => (int) ($row->total_open ?? 0),
                    'unread_open' => (int) ($row->unread_open ?? 0),
                ];
            }

            $totals['queued_open_count'] = (int) (($conversationStats['queue']['total_open'] ?? 0));
            $totals['assigned_open_count'] = collect($conversationStats)
                ->reject(fn ($value, $key) => $key === 'queue')
                ->sum('total_open');
            $totals['unread_open_count'] = collect($conversationStats)
                ->reject(fn ($value, $key) => $key === 'queue')
                ->sum('unread_open');
        }

        $expiringStats = [];
        if (Schema::hasTable('whatsapp_handoffs')) {
            $expiringRows = DB::table('whatsapp_handoffs')
                ->selectRaw('assigned_agent_id, COUNT(*) as total_expiring')
                ->where('status', 'assigned')
                ->whereNotNull('assigned_agent_id')
                ->whereNotNull('assigned_until')
                ->where('assigned_until', '<=', now()->addHours(2))
                ->groupBy('assigned_agent_id')
                ->get();

            foreach ($expiringRows as $row) {
                $expiringStats[(int) $row->assigned_agent_id] = (int) ($row->total_expiring ?? 0);
            }

            $totals['expiring_soon_count'] = array_sum($expiringStats);
        }

        $agents = collect($agents)
            ->map(function (array $agent) use ($conversationStats, $expiringStats): array {
                $agentId = (int) ($agent['id'] ?? 0);
                $conversation = $conversationStats[(string) $agentId] ?? ['total_open' => 0, 'unread_open' => 0];

                $agent['assigned_open_count'] = (int) ($conversation['total_open'] ?? 0);
                $agent['unread_open_count'] = (int) ($conversation['unread_open'] ?? 0);
                $agent['expiring_soon_count'] = (int) ($expiringStats[$agentId] ?? 0);

                return $agent;
            })
            ->sortByDesc(fn (array $agent): int => (($agent['assigned_open_count'] ?? 0) * 100) + ($agent['unread_open_count'] ?? 0))
            ->values()
            ->all();

        return [
            'agents' => $agents,
            'totals' => $totals,
        ];
    }

    /**
     * @return array{count:int,ids:array<int,int>}
     */
    public function requeueExpired(): array
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return ['count' => 0, 'ids' => []];
        }

        $expiredIds = $this->previewExpiredHandoffs()['ids'];
        $expired = WhatsappHandoff::query()->whereIn('id', $expiredIds)->get();

        $count = 0;
        $ids = [];

        DB::transaction(function () use ($expired, &$count, &$ids): void {
            foreach ($expired as $handoff) {
                $handoff->fill([
                    'status' => 'queued',
                    'assigned_agent_id' => null,
                    'assigned_at' => null,
                    'assigned_until' => null,
                    'queued_at' => now(),
                    'last_activity_at' => now(),
                ])->save();

                $conversation = WhatsappConversation::query()->find($handoff->conversation_id);
                if ($conversation instanceof WhatsappConversation) {
                    $conversation->fill([
                        'needs_human' => true,
                        'assigned_user_id' => null,
                        'assigned_at' => null,
                        'handoff_notes' => $handoff->notes,
                        'handoff_role_id' => $handoff->handoff_role_id,
                    ])->save();
                }

                $this->insertHandoffEvent($handoff->id, 'expired', null, 'TTL vencido');
                $this->insertHandoffEvent($handoff->id, 'requeued', null, $this->sanitizeNotes($handoff->notes));

                $count++;
                $ids[] = (int) $handoff->id;
            }
        });

        return ['count' => $count, 'ids' => $ids];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignConversation(int $conversationId, int $targetUserId, int $actorUserId, bool $canSupervise): array
    {
        if ($targetUserId <= 0) {
            throw new RuntimeException('Debes indicar un agente válido.');
        }

        if ($actorUserId <= 0) {
            throw new RuntimeException('Usuario no válido.');
        }

        if ($targetUserId !== $actorUserId && !$canSupervise) {
            throw new RuntimeException('No tienes permisos para asignar a otro agente.');
        }

        $conversation = $this->findConversation($conversationId);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);

        if ($assignedUserId > 0 && $assignedUserId !== $targetUserId && !$canSupervise) {
            throw new RuntimeException('La conversación ya está asignada a otro agente.');
        }

        DB::transaction(function () use ($conversation, $targetUserId, $actorUserId): void {
            $conversation->fill([
                'assigned_user_id' => $targetUserId,
                'assigned_at' => $conversation->assigned_at ?? now(),
                'needs_human' => true,
            ])->save();

            $handoff = $this->upsertActiveHandoff($conversation, [
                'status' => 'assigned',
                'assigned_agent_id' => $targetUserId,
                'assigned_at' => now(),
                'assigned_until' => $this->resolveAssignedUntil()->toDateTimeString(),
                'last_activity_at' => now(),
                'queued_at' => now(),
            ]);

            $this->insertHandoffEvent($handoff?->id, 'assigned', $actorUserId, 'Asignación desde Laravel V2');
        });

        $freshConversation = $this->findConversation($conversationId)->fresh();
        if ($freshConversation instanceof WhatsappConversation) {
            $this->realtime->broadcastConversationUpdate($freshConversation, 'assigned', $actorUserId);
        }

        return $this->serializeConversation($freshConversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function transferConversation(int $conversationId, int $targetUserId, int $actorUserId, bool $canSupervise, ?string $note = null): array
    {
        if ($targetUserId <= 0) {
            throw new RuntimeException('Debes indicar un agente para transferir.');
        }

        $conversation = $this->findConversation($conversationId);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);

        if ($assignedUserId <= 0 && !$canSupervise) {
            throw new RuntimeException('Debes tomar la conversación antes de derivarla.');
        }

        if ($assignedUserId > 0 && $assignedUserId !== $actorUserId && !$canSupervise) {
            throw new RuntimeException('Solo el agente asignado puede transferir esta conversación.');
        }

        DB::transaction(function () use ($conversation, $targetUserId, $note, $actorUserId): void {
            $conversation->fill([
                'assigned_user_id' => $targetUserId,
                'assigned_at' => now(),
                'needs_human' => true,
                'handoff_notes' => $this->sanitizeNotes($note),
            ])->save();

            $handoff = $this->upsertActiveHandoff($conversation, [
                'status' => 'assigned',
                'assigned_agent_id' => $targetUserId,
                'assigned_at' => now(),
                'assigned_until' => $this->resolveAssignedUntil()->toDateTimeString(),
                'last_activity_at' => now(),
                'notes' => $this->sanitizeNotes($note),
                'queued_at' => now(),
            ]);

            $this->insertHandoffEvent($handoff?->id, 'transferred', $actorUserId, $this->sanitizeNotes($note));
        });

        $freshConversation = $this->findConversation($conversationId)->fresh();
        if ($freshConversation instanceof WhatsappConversation) {
            $this->realtime->broadcastConversationUpdate($freshConversation, 'transferred', $actorUserId, $note);
        }

        return $this->serializeConversation($freshConversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function enqueueConversationToRole(
        int $conversationId,
        int $roleId,
        int $actorUserId,
        bool $canSupervise,
        ?string $note = null,
    ): array {
        if ($roleId <= 0) {
            throw new RuntimeException('Debes indicar un rol para enviar la conversación a la cola.');
        }

        $conversation = $this->findConversation($conversationId);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);

        if ($assignedUserId > 0 && $assignedUserId !== $actorUserId && !$canSupervise) {
            throw new RuntimeException('Solo el agente asignado o un supervisor pueden devolver la conversación a una cola.');
        }

        DB::transaction(function () use ($conversation, $roleId, $note, $actorUserId): void {
            $sanitizedNote = $this->sanitizeNotes($note);

            $conversation->fill([
                'assigned_user_id' => null,
                'assigned_at' => null,
                'needs_human' => true,
                'handoff_role_id' => $roleId,
                'handoff_notes' => $sanitizedNote,
                'handoff_requested_at' => now(),
            ])->save();

            $handoff = $this->upsertActiveHandoff($conversation, [
                'status' => 'queued',
                'handoff_role_id' => $roleId,
                'assigned_agent_id' => null,
                'assigned_at' => null,
                'assigned_until' => null,
                'last_activity_at' => now(),
                'notes' => $sanitizedNote,
                'queued_at' => now(),
            ]);

            $this->insertHandoffEvent($handoff?->id, 'queued', $actorUserId, $sanitizedNote);
        });

        $freshConversation = $this->findConversation($conversationId)->fresh();
        if ($freshConversation instanceof WhatsappConversation) {
            $this->realtime->broadcastConversationUpdate($freshConversation, 'queued', $actorUserId, $note);
        }

        return $this->serializeConversation($freshConversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function closeConversation(int $conversationId, int $actorUserId, bool $canSupervise): array
    {
        $conversation = $this->findConversation($conversationId);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);

        if ($assignedUserId > 0 && $assignedUserId !== $actorUserId && !$canSupervise) {
            throw new RuntimeException('Solo el agente asignado puede cerrar esta conversación.');
        }

        DB::transaction(function () use ($conversation, $actorUserId): void {
            $conversation->fill([
                'needs_human' => false,
                'handoff_notes' => null,
                'handoff_role_id' => null,
                'assigned_user_id' => null,
                'assigned_at' => null,
                'unread_count' => 0,
            ])->save();

            $activeHandoff = $this->findActiveHandoff($conversation->id);
            if ($activeHandoff instanceof WhatsappHandoff) {
                $activeHandoff->fill([
                    'status' => 'resolved',
                    'assigned_until' => null,
                    'last_activity_at' => now(),
                ])->save();

                $this->insertHandoffEvent($activeHandoff->id, 'resolved', $actorUserId, 'Cerrado desde Laravel V2');
            }
        });

        $freshConversation = $this->findConversation($conversationId)->fresh();
        if ($freshConversation instanceof WhatsappConversation) {
            $this->realtime->broadcastConversationUpdate($freshConversation, 'closed', $actorUserId);
        }

        return $this->serializeConversation($freshConversation);
    }

    private function findConversation(int $conversationId): WhatsappConversation
    {
        $conversation = WhatsappConversation::query()->find($conversationId);
        if (!$conversation instanceof WhatsappConversation) {
            throw new RuntimeException('Conversación no encontrada.');
        }

        return $conversation;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertActiveHandoff(WhatsappConversation $conversation, array $attributes): ?WhatsappHandoff
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return null;
        }

        $handoff = $this->findActiveHandoff($conversation->id);
        if (!$handoff instanceof WhatsappHandoff) {
            $handoff = new WhatsappHandoff([
                'conversation_id' => $conversation->id,
                'wa_number' => $conversation->wa_number,
                'status' => 'assigned',
                'priority' => 'normal',
                'handoff_role_id' => $conversation->handoff_role_id,
                'notes' => $conversation->handoff_notes,
            ]);
        }

        $handoff->fill($attributes);
        if (!$handoff->exists && !$handoff->queued_at) {
            $handoff->queued_at = now();
        }
        $handoff->save();

        return $handoff;
    }

    private function findActiveHandoff(int $conversationId): ?WhatsappHandoff
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return null;
        }

        return WhatsappHandoff::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('status', ['queued', 'assigned'])
            ->orderByDesc('id')
            ->first();
    }

    private function insertHandoffEvent(?int $handoffId, string $eventType, ?int $actorUserId, ?string $notes): void
    {
        if ($handoffId === null || $handoffId <= 0 || !Schema::hasTable('whatsapp_handoff_events')) {
            return;
        }

        $payload = [
            'handoff_id' => $handoffId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'notes' => $this->sanitizeNotes($notes),
        ];

        if (Schema::hasColumn('whatsapp_handoff_events', 'created_at')) {
            $payload['created_at'] = now();
        }

        if (Schema::hasColumn('whatsapp_handoff_events', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('whatsapp_handoff_events')->insert($payload);
    }

    private function resolveAssignedUntil(): CarbonImmutable
    {
        return now()->toImmutable()->addHours(24);
    }

    private function normalizePresenceStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['available', 'away', 'offline'], true) ? $status : 'available';
    }

    private function sanitizeNotes(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note !== '' ? mb_substr($note, 0, 255) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(?WhatsappConversation $conversation): array
    {
        if (!$conversation instanceof WhatsappConversation) {
            return [];
        }

        return [
            'id' => $conversation->id,
            'wa_number' => $conversation->wa_number,
            'display_name' => $conversation->display_name,
            'patient_hc_number' => $conversation->patient_hc_number,
            'patient_full_name' => $conversation->patient_full_name,
            'needs_human' => (bool) $conversation->needs_human,
            'handoff_notes' => $conversation->handoff_notes,
            'handoff_role_id' => $conversation->handoff_role_id,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_at' => optional($conversation->assigned_at)?->toISOString(),
            'handoff_requested_at' => optional($conversation->handoff_requested_at)?->toISOString(),
            'unread_count' => (int) $conversation->unread_count,
            'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
            'last_message_direction' => $conversation->last_message_direction,
            'last_message_type' => $conversation->last_message_type,
            'last_message_preview' => $conversation->last_message_preview,
            'source' => 'laravel-v2',
        ];
    }
}
