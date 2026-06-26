<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappOperationalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalQueuesController
{
    private const VALID_QUEUES      = ['assignment', 'supervisor', 'rescue', 'all'];
    private const VALID_CATEGORIES  = ['captacion', 'operacion', 'ambiguo', 'all'];

    public function __construct(
        private readonly WhatsappOperationalQueueService $queueService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // ── Validate queue ────────────────────────────────────────────────
        $queueParam = strtolower(trim((string) $request->query('queue', 'all')));
        if (!in_array($queueParam, self::VALID_QUEUES, true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Invalid queue value.',
                'errors'  => [
                    'queue' => ['Valores válidos: ' . implode(', ', self::VALID_QUEUES)],
                ],
            ], 422);
        }

        // ── Validate date ─────────────────────────────────────────────────
        $dateParam = trim((string) $request->query('date', ''));
        if ($dateParam !== '') {
            try {
                $asOf = \Illuminate\Support\Carbon::parse($dateParam)->endOfDay();
            } catch (\Throwable) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Invalid date value.',
                    'errors'  => [
                        'date' => ['El formato esperado es YYYY-MM-DD.'],
                    ],
                ], 422);
            }
        } else {
            $asOf = now()->endOfDay();
        }

        // ── Validate limit ────────────────────────────────────────────────
        $limitParam = $request->query('limit');
        $limit = null;
        if ($limitParam !== null && $limitParam !== '') {
            $limitInt = (int) $limitParam;
            if ($limitInt < 1) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Invalid limit value.',
                    'errors'  => [
                        'limit' => ['El límite debe ser un entero mayor o igual a 1.'],
                    ],
                ], 422);
            }
            $limit = $limitInt;
        }

        // ── Validate category ─────────────────────────────────────────────
        $categoryParam = strtolower(trim((string) $request->query('category', 'all')));
        if (!in_array($categoryParam, self::VALID_CATEGORIES, true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Invalid category value.',
                'errors'  => [
                    'category' => ['Valores válidos: ' . implode(', ', self::VALID_CATEGORIES)],
                ],
            ], 422);
        }

        // ── Build queue payload ───────────────────────────────────────────
        $summaryOnly = filter_var($request->query('summary_only', '0'), FILTER_VALIDATE_BOOLEAN);

        $result = $this->queueService->queues($asOf, [
            'queue'    => $queueParam,
            'limit'    => $limit,
            'category' => $categoryParam,
        ]);

        // ── Build response ────────────────────────────────────────────────
        if ($summaryOnly) {
            return response()->json([
                'ok'   => true,
                'data' => [
                    'date'         => $result['date'],
                    'generated_at' => $result['generated_at'],
                    'summary'      => $result['summary'],
                ],
                'meta' => [
                    'read_only' => true,
                    'source'    => 'WhatsappOperationalQueueService',
                ],
            ]);
        }

        $meta = [
            'read_only' => true,
            'queue'     => $queueParam,
            'category'  => $categoryParam,
        ];
        if ($limit !== null) {
            $meta['limit'] = $limit;
        }

        return response()->json([
            'ok'   => true,
            'data' => $result,
            'meta' => $meta,
        ]);
    }
}
