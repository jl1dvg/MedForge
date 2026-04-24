<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class KnowledgeBaseWriteController
{
    public function __construct(
        private readonly KnowledgeBaseService $service = new KnowledgeBaseService(),
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->service->createDocument($request->all(), $this->actorUserId()),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible guardar el documento de Knowledge Base.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    private function actorUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }
}
