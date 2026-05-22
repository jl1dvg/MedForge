<?php

declare(strict_types=1);

namespace App\Modules\Insumos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InsumoService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function listarInsumos(): array
    {
        return DB::table('insumos')
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get()
            ->map(static fn($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listarMedicamentos(): array
    {
        return DB::table('medicamentos')
            ->orderBy('medicamento')
            ->get()
            ->map(static fn($row) => (array) $row)
            ->all();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function guardar(array $payload): array
    {
        $campos = [
            'nombre',
            'categoria',
            'codigo_issfa',
            'codigo_isspol',
            'codigo_iess',
            'codigo_msp',
            'producto_issfa',
            'es_medicamento',
            'precio_base',
            'iva_15',
            'gestion_10',
            'precio_total',
            'precio_isspol',
        ];

        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $payload)) {
                return [
                    'success' => false,
                    'message' => "Campo faltante: {$campo}",
                ];
            }
        }

        $numericFields = ['precio_base', 'iva_15', 'gestion_10', 'precio_total', 'precio_isspol'];
        foreach ($numericFields as $campo) {
            $valor = $payload[$campo];
            if ($valor === '' || $valor === null) {
                $payload[$campo] = null;
                continue;
            }

            if (!is_numeric($valor)) {
                return [
                    'success' => false,
                    'message' => "El campo {$campo} debe ser numérico.",
                ];
            }

            $payload[$campo] = (float) $valor;
        }

        $payload['es_medicamento'] = isset($payload['es_medicamento']) && $payload['es_medicamento'] !== ''
            ? (int) $payload['es_medicamento']
            : 0;

        $id = isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : null;

        $data = [
            'nombre'          => $payload['nombre'],
            'categoria'       => $payload['categoria'],
            'codigo_issfa'    => $payload['codigo_issfa'],
            'codigo_isspol'   => $payload['codigo_isspol'],
            'codigo_iess'     => $payload['codigo_iess'],
            'codigo_msp'      => $payload['codigo_msp'],
            'producto_issfa'  => $payload['producto_issfa'],
            'es_medicamento'  => $payload['es_medicamento'],
            'precio_base'     => $payload['precio_base'],
            'iva_15'          => $payload['iva_15'],
            'gestion_10'      => $payload['gestion_10'],
            'precio_total'    => $payload['precio_total'],
            'precio_isspol'   => $payload['precio_isspol'],
        ];

        try {
            if ($id !== null && $id > 0) {
                DB::table('insumos')->where('id', $id)->update($data);
            } else {
                $id = (int) DB::table('insumos')->insertGetId($data);
            }

            return [
                'success' => true,
                'message' => 'Insumo guardado correctamente.',
                'id'      => $id,
            ];
        } catch (Throwable $e) {
            Log::error('InsumoService::guardar failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al guardar el insumo: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function guardarMedicamento(array $payload): array
    {
        foreach (['medicamento', 'via_administracion'] as $campo) {
            if (empty($payload[$campo])) {
                return [
                    'success' => false,
                    'message' => "El campo '{$campo}' es obligatorio.",
                ];
            }
        }

        $nombre = trim((string) $payload['medicamento']);
        $via    = trim((string) $payload['via_administracion']);
        $id     = isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : null;

        try {
            if ($id !== null && $id > 0) {
                DB::table('medicamentos')->where('id', $id)->update([
                    'medicamento'       => $nombre,
                    'via_administracion' => $via,
                ]);
            } else {
                $id = (int) DB::table('medicamentos')->insertGetId([
                    'medicamento'       => $nombre,
                    'via_administracion' => $via,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Medicamento guardado correctamente.',
                'id'      => $id,
            ];
        } catch (Throwable $e) {
            Log::error('InsumoService::guardarMedicamento failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al guardar el medicamento: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function eliminarMedicamento(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'Identificador de medicamento inválido.',
            ];
        }

        try {
            DB::table('medicamentos')->where('id', $id)->delete();

            return [
                'success' => true,
                'message' => 'Medicamento eliminado correctamente.',
            ];
        } catch (Throwable $e) {
            Log::error('InsumoService::eliminarMedicamento failed', ['id' => $id, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al eliminar el medicamento: ' . $e->getMessage(),
            ];
        }
    }
}
