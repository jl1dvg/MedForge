<?php

namespace App\Modules\Derivaciones\Http\Controllers;

use App\Modules\Derivaciones\Services\DerivacionesParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DerivacionesReadController
{
    private DerivacionesParityService $service;
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(base_path());
        $this->service = new DerivacionesParityService(
            DB::connection()->getPdo(),
            $this->projectRoot
        );
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión expirada',
            ], 401);
        }

        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = max((int) $request->input('length', 25), 1);
        $search = trim((string) $request->input('search.value', ''));
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = (string) $request->input('order.0.dir', 'desc');

        $columnMap = [
            0 => 'fecha_creacion',
            1 => 'cod_derivacion',
            2 => 'form_id',
            3 => 'hc_number',
            4 => 'paciente_nombre',
            5 => 'referido',
            6 => 'fecha_registro',
            7 => 'fecha_vigencia',
            8 => 'archivo',
            9 => 'diagnostico',
            10 => 'sede',
            11 => 'parentesco',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'fecha_creacion';
        $archivoEndpoint = $this->resolveBaseRoute($request) . '/archivo';

        try {
            $resultado = $this->service->obtenerPaginadas(
                $start,
                $length,
                $search,
                $orderColumn,
                $orderDir,
                $archivoEndpoint
            );

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $resultado['total'],
                'recordsFiltered' => $resultado['filtrados'],
                'data' => $resultado['datos'],
            ]);
        } catch (\Throwable $e) {
            Log::error('derivaciones.datatable.error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudo cargar derivaciones',
            ], 500);
        }
    }

    public function archivo(Request $request, int $id): BinaryFileResponse|RedirectResponse|Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $derivacion = $this->service->buscarPorId($id);
        if (!is_array($derivacion)) {
            return response('Derivación no encontrada', 404);
        }

        $rutaRelativa = trim((string) ($derivacion['archivo_derivacion_path'] ?? ''));
        if ($rutaRelativa === '') {
            return response('La derivación no tiene archivo asociado', 404);
        }

        $rutaReal = $this->resolveDerivacionAbsolutePath($rutaRelativa);
        if ($rutaReal === null) {
            Log::warning('derivaciones.archivo.not_found', [
                'derivacion_id' => $id,
                'archivo_path' => $rutaRelativa,
            ]);

            return response('Archivo de derivación no encontrado en disco', 404);
        }

        $filename = basename($rutaReal);

        return response()->file($rutaReal, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveBaseRoute(Request $request): string
    {
        $path = '/' . ltrim($request->path(), '/');

        return str_starts_with($path, '/v2/') ? '/v2/derivaciones' : '/derivaciones';
    }

    private function resolveDerivacionAbsolutePath(string $rutaRelativa): ?string
    {
        $rutaNormalizada = ltrim(str_replace('\\', '/', $rutaRelativa), '/');
        if ($rutaNormalizada === '') {
            return null;
        }

        $rutaAbsoluta = $this->projectRoot . '/' . $rutaNormalizada;
        $rutaReal = realpath($rutaAbsoluta);
        $baseReal = realpath($this->projectRoot . '/storage/derivaciones');

        if (!is_string($rutaReal) || !is_string($baseReal)) {
            return null;
        }

        $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($rutaReal, $basePrefix)) {
            return null;
        }

        if (!is_file($rutaReal)) {
            return null;
        }

        return $rutaReal;
    }
}
