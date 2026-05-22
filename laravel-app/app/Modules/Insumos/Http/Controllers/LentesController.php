<?php

declare(strict_types=1);

namespace App\Modules\Insumos\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LentesController
{
    public function index(Request $request): View
    {
        return view('insumos.lentes', [
            'pageTitle'   => 'Catálogo de Lentes',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }

    public function listar(): JsonResponse
    {
        $lentes = DB::table('lentes_catalogo')
            ->select([
                'id', 'marca', 'modelo', 'nombre', 'poder', 'observacion',
                'rango_desde', 'rango_hasta', 'rango_paso', 'rango_inicio_incremento',
                'rango_texto', 'constante_a', 'constante_a_us', 'tipo_optico',
            ])
            ->orderBy('marca')
            ->orderBy('modelo')
            ->orderBy('nombre')
            ->get()
            ->map(static fn($row) => (array) $row)
            ->all();

        return response()->json(['success' => true, 'lentes' => $lentes]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $data = $this->resolvePayload($request);

        $marca  = trim((string) ($data['marca'] ?? ''));
        $modelo = trim((string) ($data['modelo'] ?? ''));
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($marca === '' || $modelo === '' || $nombre === '') {
            return response()->json([
                'success' => false,
                'message' => 'Marca, modelo y nombre son obligatorios',
            ], 422);
        }

        $id         = isset($data['id']) ? (int) $data['id'] : null;
        $tipoOptico = trim((string) ($data['tipo_optico'] ?? ''));
        if ($tipoOptico !== '' && !in_array($tipoOptico, ['una_pieza', 'multipieza'], true)) {
            $tipoOptico = null;
        }

        $row = [
            'marca'                    => $marca,
            'modelo'                   => $modelo,
            'nombre'                   => $nombre,
            'poder'                    => trim((string) ($data['poder'] ?? '')) ?: null,
            'observacion'              => trim((string) ($data['observacion'] ?? '')) ?: null,
            'rango_desde'              => $this->toDecimal($data['rango_desde'] ?? null),
            'rango_hasta'              => $this->toDecimal($data['rango_hasta'] ?? null),
            'rango_paso'               => $this->toDecimal($data['rango_paso'] ?? null),
            'rango_inicio_incremento'  => $this->toDecimal($data['rango_inicio_incremento'] ?? null),
            'rango_texto'              => trim((string) ($data['rango_texto'] ?? '')) ?: null,
            'constante_a'              => $this->toDecimal($data['constante_a'] ?? null),
            'constante_a_us'           => $this->toDecimal($data['constante_a_us'] ?? null),
            'tipo_optico'              => $tipoOptico ?: null,
        ];

        try {
            if ($id !== null && $id > 0) {
                DB::table('lentes_catalogo')->where('id', $id)->update($row);
            } else {
                $id = (int) DB::table('lentes_catalogo')->insertGetId($row);
            }

            return response()->json(['success' => true, 'id' => $id]);
        } catch (Throwable $e) {
            Log::error('LentesController::guardar failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el lente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function eliminar(Request $request): JsonResponse
    {
        $id = $request->integer('id') ?: null;
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'ID requerido'], 400);
        }

        try {
            DB::table('lentes_catalogo')->where('id', $id)->delete();

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            Log::error('LentesController::eliminar failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el lente: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function toDecimal(mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $v = str_replace(',', '.', (string) $value);

        return is_numeric($v) ? (float) $v : null;
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
