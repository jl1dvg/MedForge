<?php

declare(strict_types=1);

namespace App\Modules\Insumos\Http\Controllers;

use App\Modules\Insumos\Services\InsumoService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsumosController
{
    public function __construct(private readonly InsumoService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('insumos.index', [
            'pageTitle'   => 'Catálogo de Insumos',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }

    public function listar(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'insumos' => $this->service->listarInsumos(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $payload   = $this->resolvePayload($request);
        $resultado = $this->service->guardar($payload);
        $status    = ($resultado['success'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function medicamentos(Request $request): View
    {
        return view('insumos.medicamentos', [
            'pageTitle'   => 'Catálogo de Medicamentos',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }

    public function listarMedicamentos(): JsonResponse
    {
        return response()->json([
            'success'      => true,
            'medicamentos' => $this->service->listarMedicamentos(),
        ]);
    }

    public function guardarMedicamento(Request $request): JsonResponse
    {
        $payload   = $this->resolvePayload($request);
        $resultado = $this->service->guardarMedicamento($payload);
        $status    = ($resultado['success'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function eliminarMedicamento(Request $request): JsonResponse
    {
        $payload   = $this->resolvePayload($request);
        $id        = isset($payload['id']) ? (int) $payload['id'] : 0;
        $resultado = $this->service->eliminarMedicamento($id);
        $status    = ($resultado['success'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    /**
     * Merge POST fields with JSON body (same precedence as legacy).
     *
     * @return array<string,mixed>
     */
    private function resolvePayload(Request $request): array
    {
        $payload = $request->post();
        $payload = is_array($payload) ? $payload : [];

        $raw = $request->getContent();
        if ($raw !== '' && $raw !== null) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        return $payload;
    }
}
