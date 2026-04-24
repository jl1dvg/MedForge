<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\FlowmakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            $userId = $this->actorUserId();

            return response()->json($this->service->publish($payload, $userId));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function actorUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }
}
