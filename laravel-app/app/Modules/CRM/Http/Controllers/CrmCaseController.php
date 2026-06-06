<?php

declare(strict_types=1);

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\CRM\Services\CrmCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

class CrmCaseController
{
    public function __construct(
        private readonly CrmCaseService $caseService,
    ) {}

    public function show(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(fn (): array => $this->caseService->show($sourceType, $sourceId));
    }

    public function update(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeContact(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeNote(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId): array {
            return $this->caseService->storeNote(
                $sourceType,
                $sourceId,
                (string) $request->input('body', $request->input('nota', '')),
                $request->user()?->id,
            );
        });
    }

    public function deleteNote(Request $request, string $sourceType, int $sourceId, int $noteId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId, $noteId): array {
            $user = $request->user();
            $isAdmin = $user !== null && method_exists($user, 'can') && $user->can('crm.manage');

            return $this->caseService->deleteNote($sourceType, $sourceId, $noteId, $user?->id, $isAdmin);
        });
    }

    public function storeTask(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId): array {
            return $this->caseService->storeTask($sourceType, $sourceId, $request->all(), $request->user()?->id);
        });
    }

    public function updateTask(Request $request, string $sourceType, int $sourceId, int $taskId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId, $taskId): array {
            return $this->caseService->updateTask($sourceType, $sourceId, $taskId, $request->all());
        });
    }

    public function sendWhatsapp(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId): array {
            return $this->caseService->sendWhatsapp($sourceType, $sourceId, $request->all(), $request->user()?->id);
        });
    }

    public function sendEmail(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId): array {
            return $this->caseService->sendEmail($sourceType, $sourceId, $request->all(), $request->user()?->id);
        });
    }

    public function catalogCodes(Request $request): JsonResponse
    {
        return $this->jsonAction(fn (): array => $this->caseService->catalogCodes(
            trim((string) $request->query('q', '')),
            trim((string) $request->query('affiliation', $request->query('afiliacion', ''))),
            max(1, min(50, (int) $request->query('limit', 20))),
        ));
    }

    public function catalogPackages(Request $request): JsonResponse
    {
        return $this->jsonAction(fn (): array => $this->caseService->catalogPackages(
            trim((string) $request->query('q', '')),
            trim((string) $request->query('affiliation', $request->query('afiliacion', ''))),
            max(1, min(50, (int) $request->query('limit', 20))),
        ));
    }

    public function storeProposal(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        return $this->jsonAction(function () use ($request, $sourceType, $sourceId): array {
            return $this->caseService->storeProposal($sourceType, $sourceId, $request->all(), $request->user()?->id);
        });
    }

    public function proposalPdf(Request $request, int $proposalId): Response|JsonResponse
    {
        return app(CrmProposalController::class)->pdf($request, $proposalId);
    }

    public function sendProposalEmail(Request $request, int $proposalId): JsonResponse
    {
        return app(CrmProposalController::class)->sendEmail($request, $proposalId);
    }

    public function sendProposalWhatsapp(Request $request, int $proposalId): JsonResponse
    {
        return app(CrmProposalController::class)->sendWhatsapp($request, $proposalId);
    }

    private function unavailableJson(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Accion V3 no disponible',
        ], 501);
    }

    /**
     * @param callable(): array<string, mixed> $action
     */
    private function jsonAction(callable $action): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $action(),
            ]);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $lowerMessage = mb_strtolower($message);
            $status = str_contains($lowerMessage, 'no encontrado') || str_contains($lowerMessage, 'no encontrada') ? 404 : 422;

            return response()->json([
                'success' => false,
                'error' => $message,
            ], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'Error interno CRM V3',
            ], 500);
        }
    }
}
