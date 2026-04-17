<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseReadController
{
    public function __construct(
        private readonly KnowledgeBaseService $service = new KnowledgeBaseService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listDocuments(
                trim((string) $request->query('search', '')),
                [
                    'status' => trim((string) $request->query('status', '')),
                    'source_type' => trim((string) $request->query('source_type', '')),
                ],
                (int) $request->query('limit', 25),
            ),
            'stats' => $this->service->overview()['stats'],
        ]);
    }
}
