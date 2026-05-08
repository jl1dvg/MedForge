<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\FlowmakerService;
use App\Modules\Whatsapp\Services\FlowSigcenterAgendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class FlowmakerWriteController
{
    public function __construct(
        private readonly FlowmakerService $service = new FlowmakerService(),
        private readonly FlowSigcenterAgendaService $sigcenterAgendaService = new FlowSigcenterAgendaService(),
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

    public function executeSigcenterAgenda(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        $action = is_array($payload['action'] ?? null) ? $payload['action'] : $payload;
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $confirmed = $request->boolean('confirmed') || (bool) ($payload['confirmed'] ?? false);

        $result = $this->sigcenterAgendaService->execute($action, $context, $input, $confirmed);

        return response()->json($result, !empty($result['ok']) ? 200 : 422);
    }

    private function actorUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }
}
