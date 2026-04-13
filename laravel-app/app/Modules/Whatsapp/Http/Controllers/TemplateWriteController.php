<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\TemplateCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class TemplateWriteController
{
    public function __construct(
        private readonly TemplateCatalogService $templateCatalogService = new TemplateCatalogService(),
    ) {
    }

    public function sync(Request $request): JsonResponse
    {
        try {
            $result = $this->templateCatalogService->syncTemplates([
                'limit' => (int) $request->input('limit', 100),
            ]);

            return response()->json([
                'ok' => true,
                'data' => [
                    'synced' => $result['synced'],
                    'templates' => $result['templates'],
                    'requested_by' => optional($request->user())->id ?? 'sistema',
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function syncLanding(): RedirectResponse
    {
        return redirect('/v2/whatsapp/templates');
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $template = $this->templateCatalogService->saveDraft(
                $request->all(),
                null,
                optional($request->user())->id
            );

            return response()->json([
                'ok' => true,
                'data' => [
                    'template' => $template,
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function clone(Request $request): JsonResponse
    {
        try {
            $template = $this->templateCatalogService->cloneTemplate(
                $request->all(),
                optional($request->user())->id
            );

            return response()->json([
                'ok' => true,
                'data' => [
                    'template' => $template,
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function update(int $templateId, Request $request): JsonResponse
    {
        try {
            $template = $this->templateCatalogService->saveDraft(
                $request->all(),
                $templateId,
                optional($request->user())->id
            );

            return response()->json([
                'ok' => true,
                'data' => [
                    'template' => $template,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function publish(int $templateId, Request $request): JsonResponse
    {
        try {
            $result = $this->templateCatalogService->publishDraft(
                $templateId,
                optional($request->user())->id
            );

            return response()->json([
                'ok' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }
}
