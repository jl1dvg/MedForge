<?php

declare(strict_types=1);

namespace App\Modules\Search\Http\Controllers;

use App\Modules\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchController
{
    private const HISTORY_KEY = 'global_search_history';
    private const HISTORY_LIMIT = 8;

    public function __construct(private readonly GlobalSearchService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query   = trim((string) $request->query('q', ''));
        $history = $this->getHistory($request);

        if ($query === '') {
            return response()->json([
                'ok'      => true,
                'data'    => [],
                'history' => $history,
            ]);
        }

        if ($this->length($query) < 2) {
            return response()->json([
                'ok'      => true,
                'data'    => [],
                'history' => $history,
                'message' => 'Ingresa al menos 2 caracteres para buscar.',
            ]);
        }

        try {
            $sections = $this->service->search($query);
            $history  = $this->pushHistory($request, $query);

            return response()->json([
                'ok'      => true,
                'data'    => $sections,
                'history' => $history,
            ]);
        } catch (Throwable $exception) {
            Log::error('Global search failed: ' . $exception->getMessage());

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo completar la búsqueda en este momento.',
            ], 500);
        }
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $request->session()->forget(self::HISTORY_KEY);

        return response()->json([
            'ok'      => true,
            'history' => [],
        ]);
    }

    private function getHistory(Request $request): array
    {
        $history = $request->session()->get(self::HISTORY_KEY, []);

        if (!is_array($history)) {
            return [];
        }

        $filtered = [];
        foreach ($history as $value) {
            if (is_string($value) && $value !== '') {
                $filtered[] = $value;
            }
        }

        return $filtered;
    }

    private function pushHistory(Request $request, string $query): array
    {
        $history    = $this->getHistory($request);
        $normalized = $this->normalize($query);

        $history = array_values(array_filter($history, function ($item) use ($normalized) {
            return $this->normalize($item) !== $normalized;
        }));

        array_unshift($history, $query);

        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, 0, self::HISTORY_LIMIT);
        }

        $request->session()->put(self::HISTORY_KEY, $history);

        return $history;
    }

    private function normalize(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
