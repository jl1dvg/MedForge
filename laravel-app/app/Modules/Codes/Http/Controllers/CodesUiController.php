<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Services\CodePriceService;
use App\Modules\Codes\Services\CodesCatalogService;
use App\Modules\Codes\Services\CodesPackageService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class CodesUiController
{
    private CodesCatalogService $catalog;
    private CodePriceService $priceService;
    private CodesPackageService $packages;

    public function __construct()
    {
        $this->catalog = new CodesCatalogService();
        $this->priceService = new CodePriceService();

        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->packages = new CodesPackageService($pdo);
    }

    public function index(Request $request): View
    {
        $filters = $this->catalog->filtersFromRequest($request);

        return view('codes.v2-index', [
            'pageTitle' => 'Códigos',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'types' => $this->catalog->listTypes(),
            'cats' => $this->catalog->listCategories(),
            'f' => $filters,
            'total' => $this->catalog->filteredCount($filters),
            'status' => session('status'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('codes.v2-form', [
            'pageTitle' => 'Nuevo código',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'types' => $this->catalog->listTypes(),
            'cats' => $this->catalog->listCategories(),
            'priceLevels' => $this->priceService->levels(),
            'prices' => [],
            'rels' => [],
            'code' => null,
            'status' => session('status'),
        ]);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $code = $this->catalog->find($id);
        if ($code === null) {
            return redirect('/v2/codes')->with('status', 'not_found');
        }

        $priceLevels = $this->priceService->levels();

        return view('codes.v2-form', [
            'pageTitle' => 'Editar código',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'types' => $this->catalog->listTypes(),
            'cats' => $this->catalog->listCategories(),
            'code' => $code->toArray(),
            'rels' => $this->catalog->relatedList($id),
            'priceLevels' => $priceLevels,
            'prices' => $this->priceService->pricesForCode($id, $priceLevels),
            'status' => session('status'),
        ]);
    }

    public function packages(Request $request): View
    {
        return view('codes.v2-packages', [
            'pageTitle' => 'Constructor de paquetes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'initialPackages' => $this->packages->list(['limit' => 25]),
        ]);
    }
}
