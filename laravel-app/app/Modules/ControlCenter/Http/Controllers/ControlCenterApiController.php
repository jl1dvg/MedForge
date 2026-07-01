<?php

namespace App\Modules\ControlCenter\Http\Controllers;

use App\Modules\ControlCenter\Services\ControlCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlCenterApiController
{
    public function __construct(private readonly ControlCenterService $service)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->overview()]);
    }

    public function clients(Request $request): JsonResponse
    {
        $clients = $this->service->clients($request);

        return response()->json([
            'ok' => true,
            'data' => $clients->items(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function client(int $id): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->client($id)]);
    }

    public function changeState(int $id, Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->changeState($id, $request)]);
    }

    public function features(int $id): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => ['features' => $this->service->features($id)]]);
    }

    public function updateFeatures(int $id, Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->updateFeatures($id, $request)]);
    }

    public function services(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => ['services' => $this->service->services()]]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => ['plans' => $this->service->plans()]]);
    }

    public function deployments(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => ['deployments' => $this->service->deployments()]]);
    }

    public function usage(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => ['usage' => $this->service->usage()]]);
    }

    public function audit(Request $request): JsonResponse
    {
        $clientId = $request->integer('client_id') ?: null;
        $limit = min(max($request->integer('limit', 50), 1), 100);

        return response()->json(['ok' => true, 'data' => ['audit' => $this->service->audit($clientId, $limit)]]);
    }
}
