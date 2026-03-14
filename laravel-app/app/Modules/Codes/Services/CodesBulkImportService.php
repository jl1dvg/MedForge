<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use App\Models\Tarifario2014;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use Throwable;

class CodesBulkImportService
{
    private const MAX_ISSUES = 60;

    private CodesCatalogService $catalog;
    private CodePriceService $priceService;
    private CodeHistoryService $history;

    public function __construct()
    {
        $this->catalog = new CodesCatalogService();
        $this->priceService = new CodePriceService();
        $this->history = new CodeHistoryService();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function import(UploadedFile $file, string $user, array $options = []): array
    {
        $storedPath = $this->persistUploadedFile($file);

        return $this->importFromPath(
            $storedPath,
            $user,
            $options,
            $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : basename($storedPath)
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, string $user, array $options = [], ?string $displayName = null): array
    {
        $options = $this->normalizeOptions($options);
        $parsed = $this->parsePath($path);
        $levels = $this->priceService->levels();
        $allowedLevelKeys = $this->priceService->levelKeyMap($levels);
        $groups = $this->groupRowsByCode($parsed['rows']);

        $summary = [
            'filename' => $displayName !== null && $displayName !== '' ? $displayName : basename($path),
            'sheet_name' => $parsed['sheet_name'],
            'dry_run' => $options['dry_run'],
            'rows_total' => count($parsed['rows']),
            'codes_total' => count($groups),
            'created_codes' => 0,
            'updated_codes' => 0,
            'skipped_codes' => 0,
            'prices_synced' => 0,
            'warnings_count' => 0,
            'issues' => [],
        ];

        foreach ($groups as $group) {
            $prices = $this->buildPriceMapForGroup($group, $levels, $summary);
            $resolution = $this->resolveExistingCode(
                (string) ($group['codigo'] ?? ''),
                (string) ($group['nombre'] ?? '')
            );

            $existingCode = $resolution['code'];
            if (($resolution['duplicates'] ?? 0) > 1 && $existingCode instanceof Tarifario2014) {
                $this->pushIssue(
                    $summary,
                    $group['row_numbers'][0] ?? null,
                    'El codigo ' . $group['codigo'] . ' existe ' . (int) $resolution['duplicates'] . ' veces en tarifario_2014. Se usara el ID ' . (int) $existingCode->id . '.'
                );
            }

            $categorySlug = $this->catalog->matchCategorySlug($group['procedure_group']);
            if ($group['procedure_group'] !== '' && $categorySlug === null) {
                $this->pushIssue(
                    $summary,
                    $group['row_numbers'][0] ?? null,
                    'No se pudo mapear la categoria "' . $group['procedure_group'] . '" para el codigo ' . $group['codigo'] . '.'
                );
            }

            if ($existingCode === null) {
                if (!$options['create_missing']) {
                    $summary['skipped_codes']++;
                    $this->pushIssue(
                        $summary,
                        $group['row_numbers'][0] ?? null,
                        'El codigo ' . $group['codigo'] . ' no existe y la opcion de crear faltantes esta desactivada.'
                    );
                    continue;
                }

                if ($group['nombre'] === '') {
                    $this->pushIssue(
                        $summary,
                        $group['row_numbers'][0] ?? null,
                        'El codigo ' . $group['codigo'] . ' no trae nombre. Se usara el mismo codigo como descripcion.'
                    );
                }
            }

            if ($options['dry_run']) {
                if ($existingCode === null) {
                    $summary['created_codes']++;
                } else {
                    $summary['updated_codes']++;
                }
                $summary['prices_synced'] += count($prices);
                continue;
            }

            try {
                DB::transaction(function () use (
                    $existingCode,
                    $group,
                    $prices,
                    $allowedLevelKeys,
                    $categorySlug,
                    $user,
                    &$summary
                ): void {
                    if ($existingCode === null) {
                        $createdCode = $this->catalog->create($this->buildCreatePayload($group, $categorySlug));
                        $codeId = (int) $createdCode->id;
                        $summary['created_codes']++;
                    } else {
                        $updatedCode = $this->catalog->update($existingCode, $this->buildUpdatePayload($group, $categorySlug, $existingCode));
                        $codeId = (int) $updatedCode->id;
                        $summary['updated_codes']++;
                    }

                    if ($prices !== []) {
                        $this->priceService->syncPricesForCode($codeId, $prices, $allowedLevelKeys);
                        $summary['prices_synced'] += count($prices);
                    }

                    $this->history->saveHistory($existingCode === null ? 'new' : 'update', $user, $codeId);
                });
            } catch (Throwable $exception) {
                $summary['skipped_codes']++;
                $this->pushIssue(
                    $summary,
                    $group['row_numbers'][0] ?? null,
                    'Error al guardar el codigo ' . $group['codigo'] . ': ' . $exception->getMessage()
                );
            }
        }

        return $summary;
    }

    /**
     * @return array<int, array{name:string,size:int,modified_at:int}>
     */
    public function availableImportFiles(): array
    {
        $directory = $this->importsDirectory();
        if (!is_dir($directory)) {
            return [];
        }

        $paths = glob($directory . DIRECTORY_SEPARATOR . '*');
        if ($paths === false) {
            return [];
        }

        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls', 'csv', 'txt', 'html', 'htm'], true)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'modified_at' => (int) filemtime($path),
            ];
        }

        usort($files, static function (array $left, array $right): int {
            return $right['modified_at'] <=> $left['modified_at'];
        });

        return $files;
    }

    public function resolveStoredImportPath(string $fileName): ?string
    {
        $safeName = $this->sanitizeFileName($fileName);
        $path = $this->importsDirectory() . DIRECTORY_SEPARATOR . $safeName;

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{sheet_name:string,rows:array<int, array<string, mixed>>}
     */
    private function parsePath(string $path): array
    {
        $spreadsheet = $this->loadSpreadsheet($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray('', true, true, false);

        if ($rows === []) {
            throw new RuntimeException('El archivo no contiene filas para importar.');
        }

        $headerIndex = $this->detectHeaderRow($rows);
        if ($headerIndex === null) {
            throw new RuntimeException('No se encontro una fila de encabezados valida en el archivo.');
        }

        $headerMap = $this->buildHeaderMap($rows[$headerIndex]);
        $this->assertRequiredHeaders($headerMap);

        $dataRows = [];
        $carryForward = [
            'afiliacion_id' => '',
            'afiliacion' => '',
            'procedure_group' => '',
            'activo' => '',
        ];

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $rows[$index];
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $mapped = [
                'row_number' => $index + 1,
                'afiliacion_id' => $this->cellValue($row, $headerMap, 'afiliacion_id'),
                'afiliacion' => $this->cellValue($row, $headerMap, 'afiliacion'),
                'procedure_group' => $this->cellValue($row, $headerMap, 'procedure_group'),
                'codigo_dependencia' => $this->cellValue($row, $headerMap, 'codigo_dependencia'),
                'codigo_particular' => $this->cellValue($row, $headerMap, 'codigo_particular'),
                'codigo_tarifario' => $this->cellValue($row, $headerMap, 'codigo_tarifario'),
                'nombre' => $this->cellValue($row, $headerMap, 'nombre'),
                'precio_raw' => $row[$headerMap['precio']] ?? null,
                'activo' => $this->cellValue($row, $headerMap, 'activo'),
            ];

            foreach (['afiliacion_id', 'afiliacion', 'procedure_group', 'activo'] as $carryKey) {
                if ($mapped[$carryKey] === '') {
                    $mapped[$carryKey] = $carryForward[$carryKey];
                } else {
                    $carryForward[$carryKey] = $mapped[$carryKey];
                }
            }

            $dataRows[] = $mapped;
        }

        if ($dataRows === []) {
            throw new RuntimeException('El archivo no contiene filas de datos debajo del encabezado.');
        }

        return [
            'sheet_name' => (string) $sheet->getTitle(),
            'rows' => $dataRows,
        ];
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        if (!is_file($path)) {
            throw new RuntimeException('El archivo de importacion no existe en la ruta esperada.');
        }

        if ($this->looksLikeHtmlFile($path)) {
            return $this->loadHtmlSpreadsheet($path);
        }

        try {
            return IOFactory::load($path);
        } catch (Throwable $exception) {
            if ($this->looksLikeHtmlFile($path)) {
                return $this->loadHtmlSpreadsheet($path);
            }

            throw new RuntimeException('No se pudo leer el archivo de importacion: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function loadHtmlSpreadsheet(string $path): Spreadsheet
    {
        $normalizedHtmlPath = $this->normalizeHtmlSource($path);
        $previousLibxmlState = libxml_use_internal_errors(true);

        try {
            $reader = new HtmlReader();

            return $reader->load($normalizedHtmlPath);
        } catch (Throwable $exception) {
            throw new RuntimeException('No se pudo procesar el archivo HTML/XLS: ' . $exception->getMessage(), 0, $exception);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);

            if ($normalizedHtmlPath !== $path && is_file($normalizedHtmlPath)) {
                @unlink($normalizedHtmlPath);
            }
        }
    }

    private function normalizeHtmlSource(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el contenido del archivo HTML/XLS.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $normalized = preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $content) ?? $content;

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($normalized === $content && in_array($extension, ['html', 'htm'], true)) {
            return $path;
        }

        $tempPath = sys_get_temp_dir() . '/codes-import-' . uniqid('', true) . '.html';
        if (@file_put_contents($tempPath, $normalized) === false) {
            throw new RuntimeException('No se pudo preparar una copia temporal del archivo HTML/XLS.');
        }

        return $tempPath;
    }

    private function looksLikeHtmlFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 4096);
        fclose($handle);

        if (!is_string($sample) || $sample === '') {
            return false;
        }

        $sample = strtolower($sample);

        return str_contains($sample, '<html')
            || str_contains($sample, '<table')
            || str_contains($sample, '<tr')
            || str_contains($sample, '<td')
            || str_contains($sample, '<meta')
            || str_contains($sample, '<!doctype');
    }

    private function persistUploadedFile(UploadedFile $file): string
    {
        $targetDirectory = $this->importsDirectory();
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('No se pudo crear el directorio de importaciones de codes.');
        }

        $originalName = trim((string) $file->getClientOriginalName());
        if ($originalName === '') {
            $originalName = 'codes-import.' . strtolower($file->getClientOriginalExtension() ?: 'dat');
        }

        $safeName = $this->sanitizeFileName($originalName);
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $safeName;
        $sourcePath = $file->getRealPath();
        if (!is_string($sourcePath) || $sourcePath === '') {
            $sourcePath = $file->path();
        }

        $sourceRealPath = realpath($sourcePath);
        $targetRealPath = realpath($targetPath);
        if ($sourceRealPath !== false && $targetRealPath !== false && $sourceRealPath === $targetRealPath) {
            return $targetPath;
        }

        if (is_file($targetPath) && !@unlink($targetPath)) {
            throw new RuntimeException('No se pudo reemplazar el archivo existente en storage/imports/codes.');
        }

        $movedFile = $file->move($targetDirectory, $safeName);

        return $movedFile->getPathname();
    }

    private function importsDirectory(): string
    {
        $projectRoot = realpath(base_path('..'));
        if (!is_string($projectRoot) || $projectRoot === '') {
            $projectRoot = dirname(base_path());
        }

        return $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'codes';
    }

    private function sanitizeFileName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'codes-import.dat';
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($normalized) && $normalized !== '') {
            $value = $normalized;
        }

        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? 'codes-import.dat';
        $value = trim($value, '._-');

        return $value !== '' ? $value : 'codes-import.dat';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function groupRowsByCode(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $codigoTarifario = trim((string) ($row['codigo_tarifario'] ?? ''));
            $codigoParticular = trim((string) ($row['codigo_particular'] ?? ''));
            $codigo = $codigoTarifario !== '' ? $codigoTarifario : $codigoParticular;

            if ($codigo === '') {
                $codigo = '__missing__row_' . (string) ($row['row_number'] ?? uniqid('', true));
            }

            if (!isset($groups[$codigo])) {
                $groups[$codigo] = [
                    'codigo' => $codigo,
                    'codigo_particular' => $codigoParticular,
                    'nombre' => trim((string) ($row['nombre'] ?? '')),
                    'procedure_group' => trim((string) ($row['procedure_group'] ?? '')),
                    'activo' => $this->parseActiveValue($row['activo'] ?? 1),
                    'first_price' => $this->parsePrice($row['precio_raw'] ?? null),
                    'rows' => [],
                    'row_numbers' => [],
                ];
            }

            if ($groups[$codigo]['nombre'] === '' && trim((string) ($row['nombre'] ?? '')) !== '') {
                $groups[$codigo]['nombre'] = trim((string) ($row['nombre'] ?? ''));
            }

            if ($groups[$codigo]['procedure_group'] === '' && trim((string) ($row['procedure_group'] ?? '')) !== '') {
                $groups[$codigo]['procedure_group'] = trim((string) ($row['procedure_group'] ?? ''));
            }

            if ($groups[$codigo]['first_price'] === null) {
                $groups[$codigo]['first_price'] = $this->parsePrice($row['precio_raw'] ?? null);
            }

            $groups[$codigo]['rows'][] = $row;
            $groups[$codigo]['row_numbers'][] = (int) ($row['row_number'] ?? 0);
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $group
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     * @param array<string, mixed> $summary
     * @return array<string, float>
     */
    private function buildPriceMapForGroup(array $group, array $levels, array &$summary): array
    {
        $prices = [];

        foreach ((array) ($group['rows'] ?? []) as $row) {
            $codigo = trim((string) ($group['codigo'] ?? ''));
            if (str_starts_with($codigo, '__missing__row_')) {
                $this->pushIssue(
                    $summary,
                    (int) ($row['row_number'] ?? 0),
                    'La fila no tiene codigo tarifario ni codigo particular, por lo que se omitio.'
                );
                continue;
            }

            $affiliation = trim((string) ($row['afiliacion'] ?? ''));
            if ($affiliation === '') {
                $this->pushIssue(
                    $summary,
                    (int) ($row['row_number'] ?? 0),
                    'La fila del codigo ' . $codigo . ' no trae afiliacion.'
                );
                continue;
            }

            $price = $this->parsePrice($row['precio_raw'] ?? null);
            if ($price === null) {
                $this->pushIssue(
                    $summary,
                    (int) ($row['row_number'] ?? 0),
                    'La fila del codigo ' . $codigo . ' no trae un precio valido.'
                );
                continue;
            }

            $levelKey = $this->priceService->resolveLevelKey($affiliation, $levels);
            if ($levelKey === null) {
                $this->pushIssue(
                    $summary,
                    (int) ($row['row_number'] ?? 0),
                    'No existe un pricelevel para la afiliacion "' . $affiliation . '" del codigo ' . $codigo . '.'
                );
                continue;
            }

            if (isset($prices[$levelKey]) && (float) $prices[$levelKey] !== $price) {
                $this->pushIssue(
                    $summary,
                    (int) ($row['row_number'] ?? 0),
                    'La afiliacion "' . $affiliation . '" del codigo ' . $codigo . ' viene repetida con precios distintos. Se uso la ultima fila.'
                );
            }

            $prices[$levelKey] = $price;
        }

        return $prices;
    }

    /**
     * @return array{status:string,code:?Tarifario2014,duplicates:int}
     */
    private function resolveExistingCode(string $codigo, string $description = ''): array
    {
        $codigo = trim($codigo);
        if ($codigo === '' || str_starts_with($codigo, '__missing__row_')) {
            return ['status' => 'missing', 'code' => null, 'duplicates' => 0];
        }

        $matches = $this->catalog->findByCodigo($codigo);
        if ($matches === []) {
            return ['status' => 'missing', 'code' => null, 'duplicates' => 0];
        }

        if (count($matches) === 1) {
            return ['status' => 'found', 'code' => $matches[0], 'duplicates' => 1];
        }

        $scoredMatches = [];
        foreach ($matches as $match) {
            $scoredMatches[] = [
                'code' => $match,
                'score' => $this->scoreExistingCodeCandidate($match, $description),
            ];
        }

        usort($scoredMatches, static function (array $left, array $right): int {
            $scoreDiff = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            /** @var Tarifario2014 $leftCode */
            $leftCode = $left['code'];
            /** @var Tarifario2014 $rightCode */
            $rightCode = $right['code'];

            return (int) $leftCode->id <=> (int) $rightCode->id;
        });

        /** @var Tarifario2014 $bestMatch */
        $bestMatch = $scoredMatches[0]['code'];

        return ['status' => 'found', 'code' => $bestMatch, 'duplicates' => count($matches)];
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function buildCreatePayload(array $group, ?string $categorySlug): array
    {
        $description = trim((string) ($group['nombre'] ?? ''));
        $price = $group['first_price'];

        return [
            'codigo' => trim((string) ($group['codigo'] ?? '')),
            'modifier' => null,
            'code_type' => null,
            'superbill' => $categorySlug,
            'revenue_code' => null,
            'descripcion' => $description !== '' ? $description : trim((string) ($group['codigo'] ?? '')),
            'short_description' => $this->shortDescription($description !== '' ? $description : trim((string) ($group['codigo'] ?? ''))),
            'active' => !empty($group['activo']) ? 1 : 0,
            'reportable' => 0,
            'financial_reporting' => 0,
            'precio_nivel1' => $price,
            'precio_nivel2' => $price,
            'precio_nivel3' => $price,
            'anestesia_nivel1' => null,
            'anestesia_nivel2' => null,
            'anestesia_nivel3' => null,
        ];
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function buildUpdatePayload(array $group, ?string $categorySlug, Tarifario2014 $existingCode): array
    {
        $description = trim((string) ($group['nombre'] ?? ''));

        return [
            'codigo' => trim((string) ($existingCode->codigo ?? $group['codigo'] ?? '')),
            'modifier' => $existingCode->modifier,
            'code_type' => $existingCode->code_type,
            'superbill' => $categorySlug ?? $existingCode->superbill,
            'revenue_code' => $existingCode->revenue_code,
            'descripcion' => $description !== '' ? $description : $existingCode->descripcion,
            'short_description' => $description !== '' ? $this->shortDescription($description) : $existingCode->short_description,
            'active' => !empty($group['activo']) ? 1 : 0,
            'reportable' => !empty($existingCode->reportable) ? 1 : 0,
            'financial_reporting' => !empty($existingCode->financial_reporting) ? 1 : 0,
            'precio_nivel1' => $existingCode->valor_facturar_nivel1,
            'precio_nivel2' => $existingCode->valor_facturar_nivel2,
            'precio_nivel3' => $existingCode->valor_facturar_nivel3,
            'anestesia_nivel1' => $existingCode->anestesia_nivel1,
            'anestesia_nivel2' => $existingCode->anestesia_nivel2,
            'anestesia_nivel3' => $existingCode->anestesia_nivel3,
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function detectHeaderRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($value): string => $this->canonicalHeaderToken((string) $value), $row);
            if (in_array('afiliacion', $normalized, true) && (in_array('codigotarifario', $normalized, true) || in_array('codigoparticular', $normalized, true))) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $headerRow
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $resolvedKey = $this->resolveHeaderKey((string) $header);
            if ($resolvedKey === null || isset($map[$resolvedKey])) {
                continue;
            }

            $map[$resolvedKey] = $index;
        }

        return $map;
    }

    /**
     * @param array<string, int> $headerMap
     */
    private function assertRequiredHeaders(array $headerMap): void
    {
        $missing = [];
        foreach (['afiliacion', 'precio'] as $requiredKey) {
            if (!isset($headerMap[$requiredKey])) {
                $missing[] = $requiredKey;
            }
        }

        if (!isset($headerMap['codigo_tarifario']) && !isset($headerMap['codigo_particular'])) {
            $missing[] = 'codigo_tarifario|codigo_particular';
        }

        if ($missing !== []) {
            throw new RuntimeException('Faltan columnas requeridas: ' . implode(', ', $missing) . '.');
        }
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int> $headerMap
     */
    private function cellValue(array $row, array $headerMap, string $key): string
    {
        if (!isset($headerMap[$key])) {
            return '';
        }

        $value = $row[$headerMap[$key]] ?? null;

        return trim((string) $value);
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveHeaderKey(string $header): ?string
    {
        $header = $this->canonicalHeaderToken($header);
        if ($header === '') {
            return null;
        }

        $aliases = [
            'afiliacion_id' => ['afiliacionid', 'idafiliacion', 'iddeafiliacion'],
            'afiliacion' => ['afiliacion'],
            'procedure_group' => ['siglastipoprocedimientoenarchivoplano', 'siglastipoprocedimiento', 'tipoprocedimientoenarchivoplano', 'tipoprocedimiento', 'procedimiento'],
            'codigo_dependencia' => ['codigodependencia'],
            'codigo_particular' => ['codigoparticular'],
            'codigo_tarifario' => ['codigotarifario'],
            'nombre' => ['nombre', 'descripcion'],
            'precio' => ['precio', 'valor'],
            'activo' => ['activo', 'estado'],
        ];

        foreach ($aliases as $key => $values) {
            if (in_array($header, $values, true)) {
                return $key;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        if ($header === '') {
            return '';
        }

        $normalized = mb_strtolower($header, 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function canonicalHeaderToken(string $header): string
    {
        $normalized = $this->normalizeHeader($header);

        return str_replace(' ', '', $normalized);
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 4);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if ((int) strrpos($value, ',') > (int) strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    private function parseActiveValue(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = $this->normalizeHeader((string) $value);
        if ($normalized === '') {
            return 1;
        }

        return in_array($normalized, ['1', 'si', 'yes', 'true', 'activo'], true) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function pushIssue(array &$summary, ?int $rowNumber, string $message): void
    {
        $summary['warnings_count']++;

        if (count($summary['issues']) >= self::MAX_ISSUES) {
            return;
        }

        $summary['issues'][] = [
            'row' => $rowNumber,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{dry_run:bool,create_missing:bool}
     */
    private function normalizeOptions(array $options): array
    {
        return [
            'dry_run' => !empty($options['dry_run']),
            'create_missing' => !array_key_exists('create_missing', $options) || !empty($options['create_missing']),
        ];
    }

    private function shortDescription(string $value): string
    {
        return mb_substr(trim($value), 0, 255);
    }

    private function scoreExistingCodeCandidate(Tarifario2014 $code, string $importDescription): int
    {
        $score = 0;

        $codeType = trim((string) ($code->code_type ?? ''));
        $modifier = trim((string) ($code->modifier ?? ''));
        if ($codeType === '') {
            $score += 30;
        }
        if ($modifier === '') {
            $score += 30;
        }
        if (!empty($code->active)) {
            $score += 10;
        }

        $importDescription = $this->normalizeLookupText($importDescription);
        $existingDescription = $this->normalizeLookupText((string) ($code->descripcion ?? ''));
        $existingShortDescription = $this->normalizeLookupText((string) ($code->short_description ?? ''));

        if ($importDescription !== '') {
            if ($existingDescription !== '' && $existingDescription === $importDescription) {
                $score += 100;
            } elseif ($existingShortDescription !== '' && $existingShortDescription === $importDescription) {
                $score += 80;
            } elseif (
                $existingDescription !== ''
                && (str_contains($existingDescription, $importDescription) || str_contains($importDescription, $existingDescription))
            ) {
                $score += 40;
            }
        }

        return $score;
    }

    private function normalizeLookupText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $normalized = mb_strtolower($value, 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }
}
