<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmActivityController
{
    public function __construct(private readonly CrmActivityService $activityService) {}

    public function store(Request $request, int $opportunityId): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        if (!CrmOpportunity::query()->find($opportunityId)) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        $validated = $request->validate([
            'type'        => 'required|string|in:nota,llamada,email',
            'description' => 'required|string|max:2000',
        ]);
        $activity = $this->activityService->log(
            opportunityId: $opportunityId,
            type: $validated['type'],
            description: $validated['description'],
            userId: Auth::id(),
        );
        return response()->json(['data' => $activity], 201);
    }
}
