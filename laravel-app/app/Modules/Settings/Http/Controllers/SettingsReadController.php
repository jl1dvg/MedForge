<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Services\SettingsService;
use Helpers\SettingsHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsReadController
{
    private SettingsService $service;

    public function __construct()
    {
        $this->service = new SettingsService();
    }

    public function all(): JsonResponse
    {
        return response()->json($this->service->getAll());
    }

    public function byCategory(string $category): JsonResponse
    {
        $sections = SettingsHelper::definitions();
        if (!isset($sections[$category])) {
            return response()->json(['error' => 'Sección no válida.'], 404);
        }

        return response()->json($this->service->getByCategory($category));
    }

    public function byKey(Request $request, string $name): JsonResponse
    {
        $name = trim($name);
        if ($name === '') {
            return response()->json(['error' => 'Clave no válida.'], 400);
        }

        $value = $this->service->getCached($name);

        return response()->json(['name' => $name, 'value' => $value]);
    }
}
