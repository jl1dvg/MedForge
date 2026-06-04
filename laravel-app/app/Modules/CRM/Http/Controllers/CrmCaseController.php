<?php

declare(strict_types=1);

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\CRM\Services\CrmCaseService;
use Illuminate\Http\JsonResponse;
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
        try {
            return response()->json([
                'success' => true,
                'data' => $this->caseService->show($sourceType, $sourceId),
            ]);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $status = str_contains(mb_strtolower($message), 'no encontrado') ? 404 : 422;

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

    public function update(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeContact(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeNote(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function deleteNote(string $sourceType, int $sourceId, int $noteId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeTask(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function updateTask(string $sourceType, int $sourceId, int $taskId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function sendWhatsapp(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function sendEmail(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function catalogCodes(): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function catalogPackages(): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function storeProposal(string $sourceType, int $sourceId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function proposalPdf(int $proposalId): Response
    {
        return response('Accion V3 no disponible', 501);
    }

    public function sendProposalEmail(int $proposalId): JsonResponse
    {
        return $this->unavailableJson();
    }

    public function sendProposalWhatsapp(int $proposalId): JsonResponse
    {
        return $this->unavailableJson();
    }

    private function unavailableJson(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Accion V3 no disponible',
        ], 501);
    }
}
