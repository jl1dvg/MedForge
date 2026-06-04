<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ImagenesUiService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ImagenesUiController
{
    private ImagenesUiService $service;

    public function __construct()
    {
        $this->service = new ImagenesUiService();
    }

    public function realizadas(Request $request): View
    {
        $payload = $this->service->imagenesRealizadas($request->query());

        return view('examenes.v2-imagenes-realizadas', [
            'pageTitle' => 'Imágenes · Procedimientos proyectados',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'imagenesRealizadas' => $payload['rows'],
            'filters' => $payload['filters'],
            'afiliacionOptions' => $payload['afiliacionOptions'],
            'afiliacionCategoriaOptions' => $payload['afiliacionCategoriaOptions'],
            'seguroOptions' => $payload['seguroOptions'],
        ]);
    }

    public function dashboard(Request $request): View
    {
        $payload = $this->service->imagenesDashboard($request->query());

        return view('examenes.v2-imagenes-dashboard', [
            'pageTitle' => 'Dashboard de Imágenes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'filters' => $payload['filters'],
            'dashboard' => $payload['dashboard'],
            'rows' => $payload['rows'],
            'afiliacionOptions' => $payload['afiliacionOptions'],
            'afiliacionCategoriaOptions' => $payload['afiliacionCategoriaOptions'],
            'seguroOptions' => $payload['seguroOptions'],
            'sedeOptions' => $payload['sedeOptions'],
        ]);
    }
}
