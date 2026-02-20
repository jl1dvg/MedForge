<?php

namespace App\Modules\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingReadController
{
    private function baseSql(): string
    {
        return <<<'SQL'
            SELECT
                base.form_id,
                base.hc_number,
                base.fecha,
                base.afiliacion,
                base.paciente,
                base.procedimiento,
                base.tipo,
                base.estado_revision,
                base.estado_agenda,
                base.valor_estimado
            FROM (
                SELECT
                    pr.form_id,
                    pr.hc_number,
                    pr.fecha AS fecha,
                    pa.afiliacion,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    pr.procedimiento_proyectado AS procedimiento,
                    CASE
                        WHEN pr.procedimiento_proyectado LIKE 'Imagenes%' THEN 'imagen'
                        WHEN pr.procedimiento_proyectado LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'no_quirurgico'
                    END AS tipo,
                    NULL AS estado_revision,
                    pr.estado_agenda AS estado_agenda,
                    0 AS valor_estimado
                FROM procedimiento_proyectado pr
                INNER JOIN patient_data pa ON pa.hc_number = pr.hc_number
                LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
                WHERE pd.form_id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pr.form_id)

                UNION ALL

                SELECT
                    pd.form_id,
                    pd.hc_number,
                    pd.fecha_inicio AS fecha,
                    pa.afiliacion,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) AS procedimiento,
                    CASE
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Imagenes%' THEN 'imagen'
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'quirurgico'
                    END AS tipo,
                    pd.status AS estado_revision,
                    pr.estado_agenda AS estado_agenda,
                    0 AS valor_estimado
                FROM protocolo_data pd
                INNER JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
                INNER JOIN patient_data pa ON pa.hc_number = pd.hc_number
                WHERE NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id)
            ) AS base
        SQL;
    }

    public function noFacturados(Request $request): JsonResponse
    {
        $start = max((int) $request->query('start', 0), 0);
        $length = min(max((int) $request->query('length', 25), 1), 200);

        $baseSql = $this->baseSql();

        $recordsTotal = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM (' . $baseSql . ') AS x')->total ?? 0);
        $rows = DB::select($baseSql . ' ORDER BY base.paciente ASC, base.fecha DESC, base.form_id DESC LIMIT ' . $start . ', ' . $length);

        return response()->json([
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $rows,
            'summary' => [
                'total' => $recordsTotal,
                'monto' => 0,
                'quirurgicos' => ['cantidad' => 0, 'monto' => 0],
                'no_quirurgicos' => ['cantidad' => 0, 'monto' => 0],
            ],
        ]);
    }

    public function afiliaciones(): JsonResponse
    {
        $baseSql = $this->baseSql();
        $rows = DB::select(
            'SELECT DISTINCT TRIM(base.afiliacion) AS afiliacion
             FROM (' . $baseSql . ') AS base
             WHERE base.afiliacion IS NOT NULL AND TRIM(base.afiliacion) <> ""
             ORDER BY afiliacion'
        );

        return response()->json(array_map(static fn ($row) => $row->afiliacion, $rows));
    }
}
