<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Whatsapp\Services\ConversationReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ConversationReadController
{
    public function __construct(
        private readonly ConversationReadService $service = new ConversationReadService()
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 25);
        $filter = trim((string) $request->query('filter', 'all'));
        $assignedUserId = $this->nullableIntQuery($request, 'agent_id');
        $roleId = $this->nullableIntQuery($request, 'role_id');
        $viewerUserId = $this->actorUserId();
        $canViewAssignedOthers = $this->canViewAssignedOthers();
        if (!$canViewAssignedOthers) {
            $assignedUserId = null;
            $roleId = null;
        }

        $paginator = $this->service->paginateConversations(
            $search,
            $perPage,
            $filter,
            $viewerUserId,
            $canViewAssignedOthers,
            $assignedUserId,
            $roleId
        );

        return response()->json([
            'ok' => true,
            'data' => $this->service->serializeConversationPage($paginator, $viewerUserId),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'filter' => $filter !== '' ? $filter : 'all',
                'agent_id' => $assignedUserId,
                'role_id' => $roleId,
                'viewer_user_id' => $viewerUserId,
                'can_view_assigned_others' => $canViewAssignedOthers,
                'tab_counts' => $this->service->getTabCounts($viewerUserId, $canViewAssignedOthers, $assignedUserId, $roleId),
                'compare_with_legacy' => config('whatsapp.migration.compare_with_legacy', true),
            ],
        ]);
    }

    public function show(int $conversationId, Request $request): JsonResponse
    {
        $limit = (int) $request->query('message_limit', 150);
        $assignedUserId = $this->nullableIntQuery($request, 'agent_id');
        $roleId = $this->nullableIntQuery($request, 'role_id');
        $viewerUserId = $this->actorUserId();
        $canViewAssignedOthers = $this->canViewAssignedOthers();
        if (!$canViewAssignedOthers) {
            $assignedUserId = null;
            $roleId = null;
        }

        $conversation = $this->service->findConversationWithMessages(
            $conversationId,
            $limit,
            $viewerUserId,
            $canViewAssignedOthers,
            $assignedUserId,
            $roleId
        );

        if ($conversation === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Conversation not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->service->serializeConversationDetail($conversation, $viewerUserId),
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

    private function actorUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }

    private function canViewAssignedOthers(): bool
    {
        $userId = $this->actorUserId();
        if ($userId === null || $userId <= 0) {
            return false;
        }

        try {
            $row = DB::table('users as u')
                ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                ->select(['u.permisos as user_permissions', 'r.permissions as role_permissions'])
                ->where('u.id', $userId)
                ->first();

            $permissions = LegacyPermissionCatalog::merge(
                [],
                $row->user_permissions ?? [],
                $row->role_permissions ?? []
            );

            return LegacyPermissionCatalog::containsAny($permissions, [
                'whatsapp.chat.supervise',
                'whatsapp.manage',
                'administrativo',
            ]);
        } catch (Throwable) {
            return false;
        }
    }
}
