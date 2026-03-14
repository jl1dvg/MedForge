<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use App\Models\CodeCategory;
use App\Models\CodeType;
use App\Models\Tarifario2014;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodesCatalogService
{
    private const ORDERABLE_COLUMNS = [
        'codigo',
        'modifier',
        'active',
        'superbill',
        'reportable',
        'financial_reporting',
        'code_type',
        'descripcion',
        'short_description',
        'id',
        'valor_facturar_nivel1',
        'valor_facturar_nivel2',
        'valor_facturar_nivel3',
    ];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $allCategoriesCache = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTypes(): array
    {
        return CodeType::query()
            ->orderBy('label')
            ->get()
            ->map(static fn (CodeType $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(bool $activeOnly = true): array
    {
        $query = CodeCategory::query();
        if ($activeOnly) {
            $query->where('active', 1);
        }

        return $query
            ->orderBy('seq')
            ->orderBy('title')
            ->get()
            ->map(static fn (CodeCategory $row): array => $row->toArray())
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<string, string>
     */
    public function categoriesMap(array $categories): array
    {
        $map = [];
        foreach ($categories as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $map[$slug] = trim((string) ($category['title'] ?? '')) ?: $slug;
        }

        return $map;
    }

    /**
     * @return array{q:string,code_type:string,superbill:string,active:int,reportable:int,financial_reporting:int}
     */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'code_type' => trim((string) $request->query('code_type', '')),
            'superbill' => trim((string) $request->query('superbill', '')),
            'active' => $request->boolean('active') ? 1 : 0,
            'reportable' => $request->boolean('reportable') ? 1 : 0,
            'financial_reporting' => $request->boolean('financial_reporting') ? 1 : 0,
        ];
    }

    public function totalCount(): int
    {
        return (int) DB::table('tarifario_2014')->count();
    }

    /**
     * @param array{q:string,code_type:string,superbill:string,active:int,reportable:int,financial_reporting:int} $filters
     */
    public function filteredCount(array $filters): int
    {
        return (int) $this->applyFilters(DB::table('tarifario_2014 as t'), $filters)->count();
    }

    /**
     * @param array{q:string,code_type:string,superbill:string,active:int,reportable:int,financial_reporting:int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, int $offset, int $limit, string $orderBy = 'codigo', string $orderDir = 'ASC'): array
    {
        $column = in_array($orderBy, self::ORDERABLE_COLUMNS, true) ? $orderBy : 'codigo';
        $direction = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $rows = $this->applyFilters(DB::table('tarifario_2014 as t'), $filters)
            ->select('t.*')
            ->orderBy("t.{$column}", $direction)
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->get();

        return $rows->map(static fn (object $row): array => (array) $row)->all();
    }

    public function find(int $id): ?Tarifario2014
    {
        return Tarifario2014::query()->find($id);
    }

    /**
     * @return array<int, Tarifario2014>
     */
    public function findByCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        return Tarifario2014::query()
            ->where('codigo', $codigo)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function isDuplicate(string $codigo, ?string $codeType, ?string $modifier, ?int $excludeId = null): bool
    {
        $query = DB::table('tarifario_2014')
            ->where('codigo', trim($codigo))
            ->whereRaw("COALESCE(code_type, '') = COALESCE(?, '')", [$this->trimNullable($codeType)])
            ->whereRaw("COALESCE(modifier, '') = COALESCE(?, '')", [$this->trimNullable($modifier)]);

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): Tarifario2014
    {
        /** @var Tarifario2014 $code */
        $code = Tarifario2014::query()->create($this->normalizePayload($input));

        return $code;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(Tarifario2014 $code, array $input): Tarifario2014
    {
        $code->fill($this->normalizePayload($input));
        $code->save();

        return $code->refresh();
    }

    public function toggleActive(Tarifario2014 $code): Tarifario2014
    {
        $code->active = !$code->active;
        $code->save();

        return $code->refresh();
    }

    public function delete(Tarifario2014 $code): void
    {
        $code->delete();
    }

    /**
     * @return array<int, array{related_code_id:int,relation_type:string,codigo:string,descripcion:string}>
     */
    public function relatedList(int $codeId): array
    {
        return DB::table('related_codes as rc')
            ->join('tarifario_2014 as t', 't.id', '=', 'rc.related_code_id')
            ->where('rc.code_id', $codeId)
            ->orderBy('rc.related_code_id')
            ->get([
                'rc.related_code_id',
                'rc.relation_type',
                't.codigo',
                't.descripcion',
            ])
            ->map(static function (object $row): array {
                return [
                    'related_code_id' => (int) ($row->related_code_id ?? 0),
                    'relation_type' => (string) ($row->relation_type ?? ''),
                    'codigo' => (string) ($row->codigo ?? ''),
                    'descripcion' => (string) ($row->descripcion ?? ''),
                ];
            })
            ->all();
    }

    public function addRelation(int $codeId, int $relatedCodeId, string $relationType = 'maps_to'): void
    {
        DB::table('related_codes')->insertOrIgnore([
            'code_id' => $codeId,
            'related_code_id' => $relatedCodeId,
            'relation_type' => trim($relationType) !== '' ? trim($relationType) : 'maps_to',
        ]);
    }

    public function removeRelation(int $codeId, int $relatedCodeId): void
    {
        DB::table('related_codes')
            ->where('code_id', $codeId)
            ->where('related_code_id', $relatedCodeId)
            ->delete();
    }

    public function removeAllRelations(int $codeId): void
    {
        DB::table('related_codes')
            ->where('code_id', $codeId)
            ->delete();
    }

    public function matchCategorySlug(?string $value): ?string
    {
        $normalizedNeedle = $this->normalizeLookupText($value);
        if ($normalizedNeedle === '') {
            return null;
        }

        foreach ($this->allCategories() as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $title = trim((string) ($category['title'] ?? ''));
            if (
                $this->normalizeLookupText($slug) === $normalizedNeedle
                || $this->normalizeLookupText($title) === $normalizedNeedle
            ) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allCategories(): array
    {
        if ($this->allCategoriesCache === null) {
            $this->allCategoriesCache = $this->listCategories(false);
        }

        return $this->allCategoriesCache;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function quickSearch(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $pattern = '%' . $query . '%';

        return DB::table('tarifario_2014')
            ->select([
                'id',
                'codigo',
                'descripcion',
                'short_description',
                'valor_facturar_nivel1',
                'valor_facturar_nivel2',
                'valor_facturar_nivel3',
                'code_type',
                'superbill',
            ])
            ->where(function ($builder) use ($pattern): void {
                $builder
                    ->where('codigo', 'like', $pattern)
                    ->orWhere('descripcion', 'like', $pattern);
            })
            ->orderBy('codigo')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param array{q:string,code_type:string,superbill:string,active:int,reportable:int,financial_reporting:int} $filters
     */
    private function applyFilters($query, array $filters)
    {
        if ($filters['q'] !== '') {
            $pattern = '%' . $filters['q'] . '%';
            $query->where(function ($builder) use ($pattern): void {
                $builder
                    ->where('t.codigo', 'like', $pattern)
                    ->orWhere('t.descripcion', 'like', $pattern);
            });
        }

        if ($filters['code_type'] !== '') {
            $query->where('t.code_type', $filters['code_type']);
        }

        if ($filters['superbill'] !== '') {
            $query->where('t.superbill', $filters['superbill']);
        }

        if (!empty($filters['active'])) {
            $query->where('t.active', 1);
        }

        if (!empty($filters['reportable'])) {
            $query->where('t.reportable', 1);
        }

        if (!empty($filters['financial_reporting'])) {
            $query->where('t.financial_reporting', 1);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        return [
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'descripcion' => $this->trimNullable($input['descripcion'] ?? null),
            'short_description' => $this->trimNullable($input['short_description'] ?? null),
            'code_type' => $this->trimNullable($input['code_type'] ?? null),
            'modifier' => $this->trimNullable($input['modifier'] ?? null),
            'superbill' => $this->trimNullable($input['superbill'] ?? null),
            'active' => !empty($input['active']) ? 1 : 0,
            'reportable' => !empty($input['reportable']) ? 1 : 0,
            'financial_reporting' => !empty($input['financial_reporting']) ? 1 : 0,
            'revenue_code' => $this->trimNullable($input['revenue_code'] ?? null),
            'valor_facturar_nivel1' => $this->decimalOrNull($input['precio_nivel1'] ?? null),
            'valor_facturar_nivel2' => $this->decimalOrNull($input['precio_nivel2'] ?? null),
            'valor_facturar_nivel3' => $this->decimalOrNull($input['precio_nivel3'] ?? null),
            'anestesia_nivel1' => $this->decimalOrNull($input['anestesia_nivel1'] ?? null),
            'anestesia_nivel2' => $this->decimalOrNull($input['anestesia_nivel2'] ?? null),
            'anestesia_nivel3' => $this->decimalOrNull($input['anestesia_nivel3'] ?? null),
        ];
    }

    private function trimNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
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
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        return $normalized;
    }
}
