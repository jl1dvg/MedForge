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

    public function organizations(Request $request): JsonResponse
    {
        $organizations = $this->service->organizations($request);

        return response()->json([
            'ok' => true,
            'data' => $organizations->items(),
            'meta' => [
                'current_page' => $organizations->currentPage(),
                'last_page' => $organizations->lastPage(),
                'per_page' => $organizations->perPage(),
                'total' => $organizations->total(),
            ],
        ]);
    }

    public function instances(Request $request): JsonResponse
    {
        $instances = $this->service->instances($request);

        return response()->json([
            'ok' => true,
            'data' => $instances->items(),
            'meta' => [
                'current_page' => $instances->currentPage(),
                'last_page' => $instances->lastPage(),
                'per_page' => $instances->perPage(),
                'total' => $instances->total(),
            ],
        ]);
    }

    public function organization(int $id): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->organization($id)]);
    }

    public function instance(int $id): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->service->instance($id)]);
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
        $organizationId = $request->integer('organization_id') ?: null;
        $instanceId = $request->integer('instance_id') ?: null;
        $limit = min(max($request->integer('limit', 50), 1), 100);

        return response()->json(['ok' => true, 'data' => ['audit' => $this->service->audit($organizationId, $instanceId, $limit)]]);
    }
}
