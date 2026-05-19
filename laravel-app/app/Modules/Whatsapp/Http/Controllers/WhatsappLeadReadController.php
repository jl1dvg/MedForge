<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappLeadReadController
{
    public function __construct(
        private readonly WhatsappLeadService $service = new WhatsappLeadService()
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->list(
            status:  trim((string) $request->query('status', '')),
            search:  trim((string) $request->query('search', '')),
            page:    max(1, (int) $request->query('page', 1)),
            perPage: min(200, max(1, (int) $request->query('per_page', 50))),
        );

        return response()->json(['ok' => true, 'data' => $data]);
    }
}
