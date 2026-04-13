<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ConversationReadService
{
    /**
     * @return LengthAwarePaginator<int, WhatsappConversation>
     */
    public function paginateConversations(
        string $search = '',
        int $perPage = 25,
        string $filter = 'all',
        ?int $viewerUserId = null,
        bool $includeAssignedOthers = true,
        ?int $assignedUserId = null,
        ?int $roleId = null,
    ): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        $query = $this->baseVisibleQuery($viewerUserId, $includeAssignedOthers, $assignedUserId, $roleId);
        $query = $this->applyFilter($query, $filter, $viewerUserId);
        $query = $this->applySearch($query, $search);

        return $query
            ->orderByDesc('last_message_at')
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
    ): array
    {
        $base = $this->baseVisibleQuery($viewerUserId, $includeAssignedOthers, $assignedUserId, $roleId);

        return [
            'all' => (clone $base)->count(),
            'unread' => (clone $base)->where('unread_count', '>', 0)->count(),
            'mine' => $viewerUserId !== null && $viewerUserId > 0
                ? (clone $base)->where('assigned_user_id', $viewerUserId)->count()
                : 0,
            'handoff' => (clone $base)->where('needs_human', true)->count(),
            'resolved' => (clone $base)->where('needs_human', false)->count(),
        ];
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
            ->map(fn (WhatsappMessage $message): array => [
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
            ])
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
            'ownership_state' => $this->resolveOwnershipState($conversation, $viewerUserId),
            'ownership_label' => $this->resolveOwnershipLabel($conversation, $viewerUserId, $assignedUser, $handoffRoleName),
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
        $query = WhatsappConversation::query();

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
            'unread' => $query->where('unread_count', '>', 0),
            'mine' => $viewerUserId !== null && $viewerUserId > 0
                ? $query->where('assigned_user_id', $viewerUserId)
                : $query->whereRaw('1 = 0'),
            'handoff' => $query->where('needs_human', true),
            'resolved' => $query->where('needs_human', false),
            default => $query,
        };
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
