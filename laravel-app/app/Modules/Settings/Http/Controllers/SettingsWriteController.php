<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Services\SettingsService;
use App\Modules\Solicitudes\Services\SolicitudesSlaSettingsService;
use Helpers\SettingsHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SettingsWriteController
{
    private SettingsService $service;

    public function __construct()
    {
        $this->service = new SettingsService();
    }

    public function saveCategory(Request $request, string $category): JsonResponse
    {
        $requestId = $this->requestId($request);

        $sections = SettingsHelper::definitions();
        if (!isset($sections[$category])) {
            return response()
                ->json(['success' => false, 'error' => 'Sección no válida.'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $section = $sections[$category];
            $payload = SettingsHelper::extractSectionPayload($section, $request->all());
            $payload = $this->applyUploadedFiles($section, $request, $payload);

            $this->service->upsertBatch($payload, $category);

            if ($category === 'crm_pipeline') {
                $slaSettings = new SolicitudesSlaSettingsService();
                $baseRules = $request->input('base_rules', []);
                $stageRules = $request->input('stage_rules', []);
                $slaSettings->saveBaseRules(is_array($baseRules) ? $baseRules : []);
                $slaSettings->saveStageRules(is_array($stageRules) ? $stageRules : []);
            }
        } catch (RuntimeException $e) {
            return response()
                ->json(['success' => false, 'error' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            return response()
                ->json(['success' => false, 'error' => 'Error al guardar la configuración.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()
            ->json(['success' => true, 'message' => 'Configuración guardada correctamente.'])
            ->header('X-Request-Id', $requestId);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $key = trim((string) $request->input('key', ''));

        if ($key === '' || !$request->hasFile('file')) {
            return response()
                ->json(['success' => false, 'error' => 'Faltan parámetros.'], 400)
                ->header('X-Request-Id', $requestId);
        }

        $file = $request->file('file');
        if ($file === null || !$file->isValid()) {
            return response()
                ->json(['success' => false, 'error' => 'Archivo no válido.'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $path = $this->storeSettingsImage(
                $file->getRealPath(),
                (string) $file->getClientOriginalName(),
                $file->getSize(),
                $key
            );
        } catch (RuntimeException $e) {
            return response()
                ->json(['success' => false, 'error' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        }

        return response()
            ->json(['success' => true, 'path' => $path])
            ->header('X-Request-Id', $requestId);
    }

    /**
     * @param array<string,mixed> $section
     * @param array<string,string> $payload
     * @return array<string,string>
     */
    private function applyUploadedFiles(array $section, Request $request, array $payload): array
    {
        foreach ($section['groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'file') {
                    continue;
                }

                $key = (string) ($field['key'] ?? '');
                if ($key === '' || !$request->hasFile($key . '_file')) {
                    continue;
                }

                $file = $request->file($key . '_file');
                if ($file === null || !$file->isValid()) {
                    continue;
                }

                $payload[$key] = $this->storeSettingsImage(
                    $file->getRealPath(),
                    (string) $file->getClientOriginalName(),
                    $file->getSize(),
                    $key
                );
            }
        }

        return $payload;
    }

    private function storeSettingsImage(?string $tmpName, string $originalName, int $size, string $key): string
    {
        if ($tmpName === null || $tmpName === '') {
            throw new RuntimeException('El archivo subido no es válido.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('El logo debe ser PNG, JPG, WEBP, GIF o SVG.');
        }

        if ($size <= 0 || $size > 3 * 1024 * 1024) {
            throw new RuntimeException('El logo no puede superar 3MB.');
        }

        $safeKey = preg_replace('/[^a-z0-9_-]+/i', '_', $key) ?: 'setting';
        $filename = date('YmdHis') . '_' . $safeKey . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absoluteDir = public_path('uploads/company');

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de logos de empresa.');
        }

        $destination = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!copy($tmpName, $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo de configuración.');
        }

        return '/uploads/company/' . $filename;
    }

    private function requestId(Request $request): string
    {
        $id = trim((string) $request->header('X-Request-Id', ''));
        return $id !== '' ? $id : bin2hex(random_bytes(8));
    }
}
