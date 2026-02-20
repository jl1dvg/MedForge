<?php

namespace App\Modules\Pacientes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PacientesReadController
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $offset = max((int) $request->query('offset', 0), 0);

        $rows = DB::select(
            'SELECT hc_number, fname, lname, lname2, afiliacion, fecha_nacimiento
             FROM patient_data
             ORDER BY hc_number DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        return response()->json([
            'data' => $rows,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($rows),
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = min(max((int) $request->input('length', 10), 1), 200);
        $search = trim((string) data_get($request->all(), 'search.value', ''));

        $recordsTotal = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM patient_data')->total ?? 0);

        $params = [];
        $where = '';
        if ($search !== '') {
            $where = 'WHERE hc_number LIKE ? OR fname LIKE ? OR lname LIKE ? OR lname2 LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like, $like];
        }

        $filteredSql = 'SELECT COUNT(*) AS total FROM patient_data ' . $where;
        $recordsFiltered = (int) (DB::selectOne($filteredSql, $params)->total ?? 0);

        $dataSql = 'SELECT hc_number, fname, lname, lname2, afiliacion, fecha_nacimiento
                    FROM patient_data '
                    . $where
                    . ' ORDER BY hc_number DESC LIMIT ? OFFSET ?';

        $rows = DB::select($dataSql, array_merge($params, [$length, $start]));

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function detalles(Request $request): JsonResponse
    {
        $hcNumber = trim((string) $request->input('hc_number', ''));
        if ($hcNumber === '') {
            return response()->json(['error' => 'hc_number es requerido'], 422);
        }

        $patient = DB::selectOne(
            'SELECT * FROM patient_data WHERE hc_number = ? LIMIT 1',
            [$hcNumber]
        );

        if ($patient === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $consultas = DB::select(
            'SELECT form_id, fecha, diagnosticos, examen_fisico
             FROM consulta_data
             WHERE hc_number = ?
             ORDER BY fecha DESC
             LIMIT 20',
            [$hcNumber]
        );

        $protocolos = DB::select(
            'SELECT form_id, fecha_inicio, membrete, status
             FROM protocolo_data
             WHERE hc_number = ?
             ORDER BY fecha_inicio DESC
             LIMIT 20',
            [$hcNumber]
        );

        return response()->json([
            'data' => [
                'patient' => $patient,
                'consultas' => $consultas,
                'protocolos' => $protocolos,
            ],
        ]);
    }

    public function flujo(Request $request): JsonResponse
    {
        $hcNumber = trim((string) $request->query('hc_number', ''));
        if ($hcNumber === '') {
            return response()->json(['error' => 'hc_number es requerido'], 422);
        }

        $rows = DB::select(
            'SELECT form_id, hc_number, fecha_creacion, fecha_registro, cod_derivacion
             FROM prefactura_paciente
             WHERE hc_number = ?
             ORDER BY fecha_creacion DESC
             LIMIT 50',
            [$hcNumber]
        );

        return response()->json([
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
            ],
        ]);
    }
}
