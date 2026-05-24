<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController
{
    public function __construct(
        private readonly WebhookService $service = new WebhookService(),
    ) {
    }

    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub.mode') ?? $request->query('hub_mode') ?? '');
        $token = (string) ($request->query('hub.verify_token') ?? $request->query('hub_verify_token') ?? '');
        $challenge = (string) ($request->query('hub.challenge') ?? $request->query('hub_challenge') ?? '');

        if ($mode === 'subscribe' && $token !== '' && hash_equals($this->service->verifyToken(), $token)) {
            return response($challenge, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return response('Verification token mismatch', 403, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function receive(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (!is_array($payload)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
            ], 400);
        }

        try {
            $result = $this->service->process($payload);

            return response()->json([
                'ok' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'error' => 'No fue posible procesar el webhook desde Laravel.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }
}
