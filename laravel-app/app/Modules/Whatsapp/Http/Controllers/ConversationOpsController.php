<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacyPermissionResolver;
use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConversationOpsController
{
    public function __construct(
        private readonly ConversationOpsService $service = new ConversationOpsService(),
    ) {
    }

    public function listAgents(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listAgents(),
        ]);
    }

    public function agentSummary(Request $request): JsonResponse
    {
        $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);
        if (!$canSupervise) {
            return response()->json([
                'ok' => false,
                'error' => 'No tienes permisos para ver el resumen de agentes.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->service->summarizeAgentWorkload(),
        ]);
    }

    public function getPresence(Request $request): JsonResponse
    {
        $userId = LegacySessionAuth::userId($request) ?? 0;

        return response()->json([
            'ok' => true,
            'data' => [
                'user_id' => $userId,
                'status' => $this->service->getAgentPresence($userId),
            ],
        ]);
    }

    public function updatePresence(Request $request): JsonResponse
    {
        return $this->runAction(function () use ($request): array {
            $userId = LegacySessionAuth::userId($request) ?? 0;
            $status = (string) $request->input('status', 'available');

            return [
                'user_id' => $userId,
                'status' => $this->service->setAgentPresence($userId, $status),
            ];
        });
    }

    public function assign(int $conversationId, Request $request): JsonResponse
    {
        return $this->runAction(function () use ($conversationId, $request): array {
            $actorUserId = LegacySessionAuth::userId($request) ?? 0;
            $targetUserId = (int) $request->input('user_id', $actorUserId);
            $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);

            return $this->service->assignConversation($conversationId, $targetUserId, $actorUserId, $canSupervise);
        });
    }

    public function transfer(int $conversationId, Request $request): JsonResponse
    {
        return $this->runAction(function () use ($conversationId, $request): array {
            $actorUserId = LegacySessionAuth::userId($request) ?? 0;
            $targetUserId = (int) $request->input('user_id', 0);
            $note = trim((string) $request->input('note', ''));
            $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);

            return $this->service->transferConversation($conversationId, $targetUserId, $actorUserId, $canSupervise, $note !== '' ? $note : null);
        });
    }

    public function queueByRole(int $conversationId, Request $request): JsonResponse
    {
        return $this->runAction(function () use ($conversationId, $request): array {
            $actorUserId = LegacySessionAuth::userId($request) ?? 0;
            $roleId = (int) $request->input('role_id', 0);
            $note = trim((string) $request->input('note', ''));
            $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);

            return $this->service->enqueueConversationToRole($conversationId, $roleId, $actorUserId, $canSupervise, $note !== '' ? $note : null);
        });
    }

    public function close(int $conversationId, Request $request): JsonResponse
    {
        return $this->runAction(function () use ($conversationId, $request): array {
            $actorUserId = LegacySessionAuth::userId($request) ?? 0;
            $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);

            return $this->service->closeConversation($conversationId, $actorUserId, $canSupervise);
        });
    }

    public function requeueExpired(Request $request): JsonResponse
    {
        return $this->runAction(function () use ($request): array {
            $canSupervise = LegacyPermissionResolver::canAny($request, ['whatsapp.chat.supervise', 'whatsapp.manage', 'administrativo']);
            if (!$canSupervise) {
                throw new RuntimeException('No tienes permisos para reencolar handoffs vencidos.');
            }

            return $this->service->requeueExpired();
        });
    }

    /**
     * @param callable(): array<string, mixed> $callback
     */
    private function runAction(callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $callback(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible ejecutar la acción del chat en Laravel.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
