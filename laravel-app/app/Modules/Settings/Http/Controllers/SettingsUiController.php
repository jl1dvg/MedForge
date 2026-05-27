<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Models\AppSetting;
use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Solicitudes\Services\SolicitudesSlaSettingsService;
use App\Modules\Whatsapp\Services\ReminderTemplateVariableCatalog;
use Helpers\SettingsHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SettingsUiController
{
    public function index(Request $request): View
    {
        $sections = $this->definitions();
        $active = (string) $request->query('section', array_key_first($sections));
        if (!isset($sections[$active])) {
            $active = (string) array_key_first($sections);
        }

        $options = $this->resolveOptions($sections);
        $sections = SettingsHelper::populateSections($sections, $options);
        $slaSettings = new SolicitudesSlaSettingsService();
        $templateCatalog = new ReminderTemplateVariableCatalog();

        return view('settings.v2-index', [
            'pageTitle' => 'Configuración',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'sections' => $sections,
            'activeSection' => $active,
            'baseRules' => $slaSettings->baseRules(),
            'stageRules' => $slaSettings->stageRules(),
            'categoryLabels' => $slaSettings->categoryLabels(),
            'stageLabels' => $slaSettings->stageLabels(),
            'whatsappTemplateMetadata' => $templateCatalog->templateMetadata(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $sections = $this->definitions();
        $sectionId = (string) $request->input('section', array_key_first($sections));
        if (!isset($sections[$sectionId])) {
            return redirect('/v2/settings?section=general')->with('error', 'Sección de ajustes no válida.');
        }

        $section = $sections[$sectionId];
        $payload = SettingsHelper::extractSectionPayload($section, $request->all());
        $payload = $this->applyUploadedFiles($section, $request, $payload);

        foreach ($payload as $name => $value) {
            AppSetting::query()->updateOrCreate(
                ['name' => $name],
                [
                    'category' => $sectionId,
                    'value' => (string) $value,
                    'type' => 'text',
                    'autoload' => true,
                ]
            );
        }

        SettingsOptionResolver::flush();

        if ($sectionId === 'crm_pipeline') {
            $slaSettings = new SolicitudesSlaSettingsService();
            $baseRules = $request->input('base_rules', []);
            $stageRules = $request->input('stage_rules', []);
            $slaSettings->saveBaseRules(is_array($baseRules) ? $baseRules : []);
            $slaSettings->saveStageRules(is_array($stageRules) ? $stageRules : []);
        }

        return redirect('/v2/settings?section=' . urlencode($sectionId))->with('status', 'Ajustes actualizados.');
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function definitions(): array
    {
        return SettingsHelper::definitions();
    }

    /**
     * @param array<string,array<string,mixed>> $sections
     * @return array<string,string>
     */
    private function resolveOptions(array $sections): array
    {
        $keys = SettingsHelper::collectOptionKeys($sections);
        if ($keys === []) {
            return [];
        }

        return AppSetting::query()
            ->whereIn('name', $keys)
            ->pluck('value', 'name')
            ->map(static fn($value): string => (string) $value)
            ->all();
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

                $payload[$key] = $this->storeSettingsImage($file->getRealPath(), (string) $file->getClientOriginalName(), $file->getSize(), $key);
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
        $relativeDir = '/uploads/company';
        $absoluteDir = public_path('uploads/company');
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de logos de empresa.');
        }

        $destination = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!copy($tmpName, $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo de configuración.');
        }

        return $relativeDir . '/' . $filename;
    }
}
