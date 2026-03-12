<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Services\CodesCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CodesReadController
{
    private CodesCatalogService $catalog;

    public function __construct()
    {
        $this->catalog = new CodesCatalogService();
    }

    public function datatable(Request $request): JsonResponse
    {
        $draw = (int) $request->query('draw', 0);
        $start = max(0, (int) $request->query('start', 0));
        $length = max(1, (int) $request->query('length', 25));

        $orderIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $columns = [
            0 => 'codigo',
            1 => 'modifier',
            2 => 'active',
            3 => 'superbill',
            4 => 'reportable',
            5 => 'financial_reporting',
            6 => 'code_type',
            7 => 'descripcion',
            8 => 'short_description',
            9 => 'id',
            10 => 'valor_facturar_nivel1',
            11 => 'valor_facturar_nivel2',
            12 => 'valor_facturar_nivel3',
            13 => 'id',
        ];
        $orderBy = $columns[$orderIndex] ?? 'codigo';

        $filters = $this->catalog->filtersFromRequest($request);
        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $filters['q'] = trim(($filters['q'] ?? '') . ' ' . $searchValue);
        }

        $total = $this->catalog->totalCount();
        $filtered = $this->catalog->filteredCount($filters);
        $rows = $this->catalog->search($filters, $start, $length, $orderBy, $orderDir);
        $catMap = $this->catalog->categoriesMap($this->catalog->listCategories());

        $data = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $modifier = trim((string) ($row['modifier'] ?? ''));
            $categoryKey = trim((string) ($row['superbill'] ?? ''));
            $category = trim((string) ($catMap[$categoryKey] ?? $categoryKey));
            $codeType = trim((string) ($row['code_type'] ?? ''));
            $description = trim((string) ($row['descripcion'] ?? ''));
            $shortDescription = trim((string) ($row['short_description'] ?? ''));

            $data[] = [
                'codigo' => $codigo !== '' ? $codigo : '#' . $id,
                'modifier' => $modifier !== '' ? $modifier : '—',
                'active_text' => !empty($row['active']) ? 'Sí' : 'No',
                'category' => $category !== '' ? $category : 'Sin categoría',
                'reportable_text' => !empty($row['reportable']) ? 'Sí' : 'No',
                'finrep_text' => !empty($row['financial_reporting']) ? 'Sí' : 'No',
                'code_type' => $codeType !== '' ? $codeType : '—',
                'descripcion' => $description !== '' ? $description : '—',
                'short_description' => $shortDescription !== '' ? $shortDescription : '—',
                'related' => '—',
                'valor1' => number_format((float) ($row['valor_facturar_nivel1'] ?? 0), 2),
                'valor2' => number_format((float) ($row['valor_facturar_nivel2'] ?? 0), 2),
                'valor3' => number_format((float) ($row['valor_facturar_nivel3'] ?? 0), 2),
                'acciones' => '<a href="/v2/codes/' . $id . '/edit" class="btn btn-sm btn-outline-primary">Editar</a>',
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function searchCodes(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            if ($query === '') {
                return response()->json(['ok' => true, 'data' => []]);
            }

            $limit = max(1, min(50, (int) $request->query('limit', 15)));
            $results = $this->catalog->quickSearch($query, $limit);

            return response()->json(['ok' => true, 'data' => $results]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => 'No se pudieron buscar los códigos solicitados',
                'details' => $exception->getMessage(),
            ], 500);
        }
    }
}
