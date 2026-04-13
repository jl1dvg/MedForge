<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\TemplateCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TemplateReadController
{
    public function __construct(
        private readonly TemplateCatalogService $templateCatalogService = new TemplateCatalogService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $catalog = $this->templateCatalogService->getTemplateCatalog([
                'search' => trim((string) $request->query('search', '')),
                'status' => trim((string) $request->query('status', '')),
                'category' => trim((string) $request->query('category', '')),
                'language' => trim((string) $request->query('language', '')),
                'limit' => (int) $request->query('limit', 100),
            ]);

            return response()->json([
                'ok' => true,
                'data' => $catalog['templates'],
                'meta' => [
                    'source' => $catalog['source'],
                    'integration' => $catalog['integration'],
                    'available_categories' => $catalog['available_categories'],
                    'available_languages' => $catalog['available_languages'],
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }
}
