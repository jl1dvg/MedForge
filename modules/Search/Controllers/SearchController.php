<?php

namespace Modules\Search\Controllers;

use Core\BaseController;
use Modules\Search\Services\GlobalSearchService;
use Throwable;

class SearchController extends BaseController
{
    private const HISTORY_KEY = 'global_search_history';
    private const HISTORY_LIMIT = 8;

    public function index(): void
    {
        $this->requireAuth();

        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $history = $this->getHistory();

        if ($query === '') {
            $this->json([
                'ok' => true,
                'data' => [],
                'history' => $history,
            ]);

            return;
        }

        if ($this->length($query) < 2) {
            $this->json([
                'ok' => true,
                'data' => [],
                'history' => $history,
                'message' => 'Ingresa al menos 2 caracteres para buscar.',
            ]);

            return;
        }

        try {
            $service = new GlobalSearchService($this->pdo);
            $sections = $service->search($query);
            $history = $this->pushHistory($query);

            $this->json([
                'ok' => true,
                'data' => $sections,
                'history' => $history,
            ]);
        } catch (Throwable $exception) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }

            error_log('Global search failed: ' . $exception->getMessage());

            echo json_encode([
                'ok' => false,
                'message' => 'No se pudo completar la búsqueda en este momento.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function clearHistory(): void
    {
        $this->requireAuth();

        unset($_SESSION[self::HISTORY_KEY]);

        $this->json([
            'ok' => true,
            'history' => [],
        ]);
    }

    private function getHistory(): array
    {
        $history = $_SESSION[self::HISTORY_KEY] ?? [];
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

    private function pushHistory(string $query): array
    {
        $history = $this->getHistory();
        $normalized = $this->normalize($query);

        $history = array_values(array_filter($history, function ($item) use ($normalized) {
            return $this->normalize($item) !== $normalized;
        }));

        array_unshift($history, $query);

        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, 0, self::HISTORY_LIMIT);
        }

        $_SESSION[self::HISTORY_KEY] = $history;

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
