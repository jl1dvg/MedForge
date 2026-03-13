<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Services\CodesBulkImportService;
use App\Modules\Codes\Services\CodeHistoryService;
use App\Modules\Codes\Services\CodePriceService;
use App\Modules\Codes\Services\CodesCatalogService;
use App\Modules\Codes\Services\CodesDeduplicationService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CodesWriteController
{
    private CodesCatalogService $catalog;
    private CodePriceService $priceService;
    private CodeHistoryService $history;
    private CodesBulkImportService $bulkImport;
    private CodesDeduplicationService $deduplication;

    public function __construct()
    {
        $this->catalog = new CodesCatalogService();
        $this->priceService = new CodePriceService();
        $this->history = new CodeHistoryService();
        $this->bulkImport = new CodesBulkImportService();
        $this->deduplication = new CodesDeduplicationService();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCodePayload($request);
        $codigo = trim((string) ($validated['codigo'] ?? ''));
        $codeType = $validated['code_type'] ?? null;
        $modifier = $validated['modifier'] ?? null;

        if ($this->catalog->isDuplicate($codigo, is_string($codeType) ? $codeType : null, is_string($modifier) ? $modifier : null)) {
            return redirect('/v2/codes/create')
                ->withInput()
                ->withErrors(['codigo' => 'Duplicado: (codigo, code_type, modifier) debe ser único.']);
        }

        $payload = $this->augmentFlags($validated, $request);
        $priceLevels = $this->priceService->levels();
        $allowedLevelKeys = $this->priceService->levelKeyMap($priceLevels);
        $prices = is_array($validated['prices'] ?? null) ? $validated['prices'] : [];
        $user = $this->currentUserName($request);

        try {
            $codeId = DB::transaction(function () use ($payload, $prices, $allowedLevelKeys, $user): int {
                $code = $this->catalog->create($payload);
                $codeId = (int) $code->id;

                $this->priceService->syncPricesForCode($codeId, $prices, $allowedLevelKeys);
                $this->history->saveHistory('new', $user, $codeId);

                return $codeId;
            });

            return redirect('/v2/codes/' . $codeId . '/edit')->with('status', 'created');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/create')
                ->withInput()
                ->withErrors(['general' => 'Error al crear: ' . $exception->getMessage()]);
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $validated = $this->validateCodePayload($request);
        $codigo = trim((string) ($validated['codigo'] ?? ''));
        $codeType = is_string($validated['code_type'] ?? null) ? $validated['code_type'] : null;
        $modifier = is_string($validated['modifier'] ?? null) ? $validated['modifier'] : null;

        $identityChanged = $this->hasIdentityChanged(
            $code,
            $codigo,
            $codeType,
            $modifier
        );

        if (
            $identityChanged
            && $this->catalog->isDuplicate($codigo, $codeType, $modifier, $id)
        ) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withInput()
                ->withErrors(['codigo' => 'Duplicado: (codigo, code_type, modifier) debe ser único.']);
        }

        $payload = $this->augmentFlags($validated, $request);
        $priceLevels = $this->priceService->levels();
        $allowedLevelKeys = $this->priceService->levelKeyMap($priceLevels);
        $prices = is_array($validated['prices'] ?? null) ? $validated['prices'] : [];
        $user = $this->currentUserName($request);

        try {
            DB::transaction(function () use ($code, $payload, $prices, $allowedLevelKeys, $user): void {
                $updated = $this->catalog->update($code, $payload);
                $codeId = (int) $updated->id;

                $this->priceService->syncPricesForCode($codeId, $prices, $allowedLevelKeys);
                $this->history->saveHistory('update', $user, $codeId);
            });

            return redirect('/v2/codes/' . $id . '/edit')->with('status', 'updated');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withInput()
                ->withErrors(['general' => 'Error al actualizar: ' . $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $user = $this->currentUserName($request);

        try {
            DB::transaction(function () use ($code, $id, $user): void {
                $snapshot = $this->history->snapshot($id);

                $this->catalog->removeAllRelations($id);
                DB::table('prices')->where('code_id', $id)->delete();
                $this->catalog->delete($code);

                $this->history->saveHistory('delete', $user, $id, $snapshot);
            });

            return redirect('/v2/codes')->with('status', 'deleted');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withErrors(['general' => 'Error al eliminar: ' . $exception->getMessage()]);
        }
    }

    public function toggleActive(Request $request, int $id): RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $user = $this->currentUserName($request);

        try {
            DB::transaction(function () use ($code, $user): void {
                $updated = $this->catalog->toggleActive($code);
                $this->history->saveHistory('update', $user, (int) $updated->id);
            });

            return redirect('/v2/codes/' . $id . '/edit')->with('status', 'toggled');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withErrors(['general' => 'Error al cambiar estado: ' . $exception->getMessage()]);
        }
    }

    public function addRelation(Request $request, int $id): RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $validated = $request->validate([
            'related_id' => ['required', 'integer', 'min:1'],
            'relation_type' => ['nullable', 'string', 'max:40'],
        ]);

        $relatedId = (int) $validated['related_id'];
        $relationType = trim((string) ($validated['relation_type'] ?? 'maps_to')) ?: 'maps_to';
        $user = $this->currentUserName($request);

        try {
            DB::transaction(function () use ($id, $relatedId, $relationType, $user): void {
                $this->catalog->addRelation($id, $relatedId, $relationType);
                $this->history->saveHistory('update', $user, $id);
            });

            return redirect('/v2/codes/' . $id . '/edit')->with('status', 'relation_added');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withErrors(['general' => 'Error al agregar relación: ' . $exception->getMessage()]);
        }
    }

    public function removeRelation(Request $request, int $id): RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $validated = $request->validate([
            'related_id' => ['required', 'integer', 'min:1'],
        ]);

        $relatedId = (int) $validated['related_id'];
        $user = $this->currentUserName($request);

        try {
            DB::transaction(function () use ($id, $relatedId, $user): void {
                $this->catalog->removeRelation($id, $relatedId);
                $this->history->saveHistory('update', $user, $id);
            });

            return redirect('/v2/codes/' . $id . '/edit')->with('status', 'relation_removed');
        } catch (Throwable $exception) {
            return redirect('/v2/codes/' . $id . '/edit')
                ->withErrors(['general' => 'Error al quitar relación: ' . $exception->getMessage()]);
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['nullable', 'file', 'extensions:xlsx,xls,csv,txt,html,htm', 'max:20480'],
            'stored_file' => ['nullable', 'string', 'max:255'],
            'dry_run' => ['nullable', 'boolean'],
            'create_missing' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $storedFile = trim((string) ($validated['stored_file'] ?? ''));

        if ($file === null && $storedFile === '') {
            return redirect('/v2/codes/import')
                ->withInput()
                ->withErrors(['file' => 'Debes seleccionar un archivo o elegir uno desde storage/imports/codes.']);
        }

        try {
            $options = [
                'dry_run' => $request->boolean('dry_run'),
                'create_missing' => $request->boolean('create_missing', true),
            ];

            if ($file !== null) {
                $summary = $this->bulkImport->import($file, $this->currentUserName($request), $options);
            } else {
                $storedPath = $this->bulkImport->resolveStoredImportPath($storedFile);
                if ($storedPath === null) {
                    return redirect('/v2/codes/import')
                        ->withInput()
                        ->withErrors(['stored_file' => 'El archivo seleccionado no existe en storage/imports/codes.']);
                }

                $summary = $this->bulkImport->importFromPath(
                    $storedPath,
                    $this->currentUserName($request),
                    $options,
                    basename($storedPath)
                );
            }

            return redirect('/v2/codes/import')
                ->with('status', $summary['dry_run'] ? 'validated' : 'imported')
                ->with('import_summary', $summary);
        } catch (Throwable $exception) {
            return redirect('/v2/codes/import')
                ->withInput()
                ->withErrors(['general' => 'Error en la importación: ' . $exception->getMessage()]);
        }
    }

    public function deduplicate(Request $request): RedirectResponse
    {
        $request->validate([
            'dedupe_dry_run' => ['nullable', 'boolean'],
        ]);

        try {
            $summary = $this->deduplication->run($request->boolean('dedupe_dry_run', true));

            return redirect('/v2/codes/import')
                ->with('status', $summary['dry_run'] ? 'dedupe_validated' : 'deduped')
                ->with('dedupe_summary', $summary);
        } catch (Throwable $exception) {
            return redirect('/v2/codes/import')
                ->withErrors(['general' => 'Error en la depuración: ' . $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCodePayload(Request $request): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:64'],
            'modifier' => ['nullable', 'string', 'max:32'],
            'code_type' => ['nullable', 'string', 'max:50'],
            'superbill' => ['nullable', 'string', 'max:100'],
            'revenue_code' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'reportable' => ['nullable', 'boolean'],
            'financial_reporting' => ['nullable', 'boolean'],
            'precio_nivel1' => ['nullable', 'numeric'],
            'precio_nivel2' => ['nullable', 'numeric'],
            'precio_nivel3' => ['nullable', 'numeric'],
            'anestesia_nivel1' => ['nullable', 'numeric'],
            'anestesia_nivel2' => ['nullable', 'numeric'],
            'anestesia_nivel3' => ['nullable', 'numeric'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric'],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function augmentFlags(array $validated, Request $request): array
    {
        $validated['active'] = $request->boolean('active') ? 1 : 0;
        $validated['reportable'] = $request->boolean('reportable') ? 1 : 0;
        $validated['financial_reporting'] = $request->boolean('financial_reporting') ? 1 : 0;

        return $validated;
    }

    private function currentUserName(Request $request): string
    {
        $currentUser = LegacyCurrentUser::resolve($request);
        $displayName = trim((string) ($currentUser['display_name'] ?? ''));

        return $displayName !== '' ? $displayName : 'system';
    }

    private function hasIdentityChanged($code, string $codigo, ?string $codeType, ?string $modifier): bool
    {
        $currentCodigo = trim((string) ($code->codigo ?? ''));
        $currentCodeType = $this->normalizeIdentityValue($code->code_type ?? null);
        $currentModifier = $this->normalizeIdentityValue($code->modifier ?? null);

        return $currentCodigo !== trim($codigo)
            || $currentCodeType !== $this->normalizeIdentityValue($codeType)
            || $currentModifier !== $this->normalizeIdentityValue($modifier);
    }

    private function normalizeIdentityValue(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized;
    }
}
