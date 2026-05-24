<?php

declare(strict_types=1);

namespace App\Modules\Procedimientos\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Expone el catálogo de procedimientos quirúrgicos al popup de CiveExtension.
 *
 * Replica la lógica de Controllers\ListarProcedimientosController (PHP legado)
 * con soporte CORS mediante el middleware consultas.cors.
 */
class ProcedimientosReadController
{
    public function listar(Request $request): JsonResponse
    {
        $afiliacion = trim((string) $request->query('afiliacion', ''));

        $rows = DB::table('procedimientos')->get();

        $procedimientos = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $id = $row['id'];

            $tecnicos = DB::table('procedimientos_tecnicos')
                ->where('procedimiento_id', $id)
                ->get()
                ->map(static fn ($r) => (array) $r)
                ->all();

            $codigos = DB::table('procedimientos_codigos')
                ->where('procedimiento_id', $id)
                ->get()
                ->map(static fn ($r) => (array) $r)
                ->all();

            $diagnosticos = DB::table('procedimientos_diagnosticos')
                ->where('procedimiento_id', $id)
                ->get()
                ->map(static fn ($r) => (array) $r)
                ->all();

            $operatorio = $this->procesarOperatorio((string) ($row['operatorio'] ?? ''), $afiliacion);
            $staffCount = $this->countFilledRows($tecnicos, 'funcion');
            $codigoCount = $this->countFilledRows($codigos, 'nombre');

            $procedimientos[] = array_merge($row, [
                'staffCount' => $staffCount,
                'codigoCount' => $codigoCount,
                'tecnicos' => $tecnicos,
                'codigos' => $codigos,
                'diagnosticos' => $diagnosticos,
                'operatorio' => $operatorio,
            ]);
        }

        return response()->json(['procedimientos' => $procedimientos]);
    }

    private function procesarOperatorio(string $texto, string $afiliacion): string
    {
        return (string) preg_replace_callback(
            '/\[\[ID:(\d+)\]\]/',
            static function (array $matches) use ($afiliacion): string {
                $insumo = DB::table('insumos')
                    ->where('id', (int) $matches[1])
                    ->select('nombre', 'producto_issfa')
                    ->first();

                if ($insumo === null) {
                    return $matches[0];
                }

                $insumo = (array) $insumo;
                if ($afiliacion === 'ISSFA' && !empty($insumo['producto_issfa'])) {
                    return (string) $insumo['producto_issfa'];
                }

                return (string) ($insumo['nombre'] ?? $matches[0]);
            },
            $texto
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function countFilledRows(array $rows, string $field): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                $count++;
            }
        }
        return $count;
    }
}
