<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Whatsapp\Services\FlowmakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FlowmakerWriteController
{
    public function __construct(
        private readonly FlowmakerService $service = new FlowmakerService(),
    ) {
    }

    public function publish(Request $request): JsonResponse
    {
        try {
            $payload = $request->json()->all();
            if (!is_array($payload) || $payload === []) {
                $payload = $request->all();
            }

            $currentUser = LegacyCurrentUser::resolve($request);
            $userId = is_numeric($currentUser['id'] ?? null) ? (int) $currentUser['id'] : null;

            return response()->json($this->service->publish($payload, $userId));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
