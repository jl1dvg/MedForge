<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\FlowmakerSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * REST controller for the Flowmaker sandbox (draft testing) environment.
 *
 * Endpoints (all under /whatsapp/api):
 *   GET    /flowmaker/sandbox              — current sandbox status
 *   POST   /flowmaker/sandbox/draft        — save a draft version
 *   PUT    /flowmaker/sandbox/numbers      — replace whitelist
 *   POST   /flowmaker/sandbox/numbers      — add a single number
 *   DELETE /flowmaker/sandbox/numbers/{n}  — remove a single number
 *   DELETE /flowmaker/sandbox              — clear sandbox completely
 */
class FlowmakerSandboxController
{
    public function __construct(
        private readonly FlowmakerSandboxService $sandbox = new FlowmakerSandboxService(),
    ) {
    }

    /**
     * GET /flowmaker/sandbox
     * Returns the current sandbox status: draft version info + whitelisted numbers.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->sandbox->getStatus(),
        ]);
    }

    /**
     * POST /flowmaker/sandbox/draft
     *
     * Body (JSON):
     *   { "flow": { ...same payload as /flowmaker/publish... },
     *     "wa_numbers": ["593999123456"],   // optional
     *     "changelog": "Fix doctor search"  // optional
     *   }
     *
     * Saves a 'ready' (non-published) version and registers it as the sandbox draft.
     * Only numbers in wa_numbers will route to this draft; all others use production.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $flow = $request->input('flow');
        if (!is_array($flow)) {
            return response()->json(['ok' => false, 'error' => 'El campo flow es requerido y debe ser un objeto.'], 422);
        }

        $options = array_filter([
            'wa_numbers' => $request->input('wa_numbers'),
            'changelog' => $request->input('changelog'),
        ], static fn (mixed $v): bool => $v !== null);

        try {
            $result = $this->sandbox->saveDraft(
                ['flow' => $flow],
                $options ?: null,
                $request->user()?->id
            );
            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /flowmaker/sandbox/numbers
     *
     * Body: { "wa_numbers": ["593999123456", "593988654321"] }
     *
     * Replaces the entire sandbox number whitelist.
     */
    public function setNumbers(Request $request): JsonResponse
    {
        $numbers = $request->input('wa_numbers', []);
        if (!is_array($numbers)) {
            return response()->json(['ok' => false, 'error' => 'wa_numbers debe ser un array.'], 422);
        }

        try {
            return response()->json($this->sandbox->setNumbers($numbers));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /flowmaker/sandbox/numbers
     *
     * Body: { "wa_number": "593999123456" }
     *
     * Adds a single number to the whitelist without replacing existing ones.
     */
    public function addNumber(Request $request): JsonResponse
    {
        $number = (string) $request->input('wa_number', '');
        if (trim($number) === '') {
            return response()->json(['ok' => false, 'error' => 'El campo wa_number es requerido.'], 422);
        }

        try {
            return response()->json($this->sandbox->addNumber($number));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /flowmaker/sandbox/numbers/{waNumber}
     *
     * Removes a single number from the whitelist.
     */
    public function removeNumber(string $waNumber): JsonResponse
    {
        return response()->json($this->sandbox->removeNumber($waNumber));
    }

    /**
     * DELETE /flowmaker/sandbox
     *
     * Deactivates the sandbox: removes config and deletes the draft version.
     * Production flow is completely unaffected.
     */
    public function clear(): JsonResponse
    {
        return response()->json($this->sandbox->clear());
    }
}
