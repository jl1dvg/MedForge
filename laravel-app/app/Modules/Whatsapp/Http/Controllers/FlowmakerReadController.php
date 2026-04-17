<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\FlowmakerService;
use App\Modules\Whatsapp\Services\FlowAiAgentPreviewService;
use App\Modules\Whatsapp\Services\FlowRuntimePreviewService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowCompareService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowObserverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlowmakerReadController
{
    public function __construct(
        private readonly FlowmakerService $service = new FlowmakerService(),
        private readonly FlowAiAgentPreviewService $aiAgentService = new FlowAiAgentPreviewService(),
        private readonly FlowRuntimePreviewService $previewService = new FlowRuntimePreviewService(),
        private readonly FlowRuntimeShadowCompareService $compareService = new FlowRuntimeShadowCompareService(),
        private readonly FlowRuntimeShadowObserverService $shadowObserver = new FlowRuntimeShadowObserverService(),
    ) {
    }

    public function contract(): JsonResponse
    {
        return response()->json($this->service->getContract());
    }

    public function simulate(Request $request): JsonResponse
    {
        $context = $this->decodeContext($request->input('context'));

        return response()->json($this->previewService->simulate([
            'wa_number' => (string) $request->input('wa_number', ''),
            'text' => (string) $request->input('text', ''),
            'context' => $context,
        ]));
    }

    public function compare(Request $request): JsonResponse
    {
        $context = $this->decodeContext($request->input('context'));

        return response()->json($this->compareService->compare([
            'wa_number' => (string) $request->input('wa_number', ''),
            'text' => (string) $request->input('text', ''),
            'context' => $context,
        ]));
    }

    public function shadowRuns(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 25);
        $mismatchesOnly = $request->boolean('mismatches_only', false);

        return response()->json([
            'ok' => true,
            'data' => $this->shadowObserver->recent($limit, $mismatchesOnly),
        ]);
    }

    public function shadowSummary(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 250);

        return response()->json([
            'ok' => true,
            'data' => $this->shadowObserver->summary($limit),
        ]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 250);

        return response()->json([
            'ok' => true,
            'data' => $this->shadowObserver->readiness($limit),
        ]);
    }

    public function aiRuns(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 8);

        return response()->json([
            'ok' => true,
            'data' => $this->aiAgentService->recent($limit),
        ]);
    }

    /**
     * @param mixed $context
     * @return array<string, mixed>
     */
    private function decodeContext(mixed $context): array
    {
        if (is_string($context) && trim($context) !== '') {
            $decoded = json_decode($context, true);
            $context = is_array($decoded) ? $decoded : [];
        }

        return is_array($context) ? $context : [];
    }
}
