<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Services\BillingPreviewService;
use App\Modules\Billing\Services\NoFacturadosQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingReadController
{
    private NoFacturadosQueryService $service;
    private BillingPreviewService $previewService;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->service = new NoFacturadosQueryService($pdo);
        $this->previewService = new BillingPreviewService($pdo);
    }

    public function noFacturados(Request $request): JsonResponse
    {
        $draw = (int) $request->query('draw', 0);
        $start = max((int) $request->query('start', 0), 0);
        $length = min(max((int) $request->query('length', 25), 1), 500);

        $afiliacion = $request->query('afiliacion', []);
        $afiliaciones = is_array($afiliacion) ? $afiliacion : [$afiliacion];

        $estadoAgenda = $request->query('estado_agenda', []);
        $estadosAgenda = is_array($estadoAgenda) ? $estadoAgenda : [$estadoAgenda];

        $filters = [
            'fecha_desde' => $request->query('fecha_desde'),
            'fecha_hasta' => $request->query('fecha_hasta'),
            'form_id' => $request->query('form_id'),
            'hc_number' => $request->query('hc_number'),
            'afiliacion' => $afiliaciones,
            'empresa_seguro' => $request->query('empresa_seguro'),
            'sede' => $request->query('sede'),
            'estado_revision' => $request->query('estado_revision'),
            'informado' => $request->query('informado'),
            'estado_agenda' => $estadosAgenda,
            'tipo' => $request->query('tipo'),
            'busqueda' => $request->query('busqueda'),
            'procedimiento' => $request->query('procedimiento'),
            'valor_min' => $request->query('valor_min'),
            'valor_max' => $request->query('valor_max'),
        ];

        $resultado = $this->service->listar($filters, $start, $length);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $resultado['recordsTotal'],
            'recordsFiltered' => $resultado['recordsFiltered'],
            'data' => $resultado['data'],
            'summary' => $resultado['summary'],
        ]);
    }

    public function afiliaciones(): JsonResponse
    {
        return response()->json(array_values($this->service->listarAfiliaciones()));
    }

    public function sedes(): JsonResponse
    {
        $sedes = $this->service->listarSedes();
        if (!in_array('MATRIZ', $sedes, true)) {
            $sedes[] = 'MATRIZ';
        }
        if (!in_array('CEIBOS', $sedes, true)) {
            $sedes[] = 'CEIBOS';
        }

        usort($sedes, static function (string $a, string $b): int {
            $order = ['MATRIZ' => 1, 'CEIBOS' => 2];
            $oa = $order[$a] ?? 99;
            $ob = $order[$b] ?? 99;
            if ($oa === $ob) {
                return strcasecmp($a, $b);
            }
            return $oa <=> $ob;
        });

        return response()->json([
            'data' => array_values(array_unique(array_filter(array_map('trim', $sedes)))),
        ]);
    }

    public function billingPreview(Request $request): JsonResponse
    {
        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros faltantes',
            ]);
        }

        try {
            $preview = $this->previewService->prepararPreviewFacturacion($formId, $hcNumber);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'procedimientos' => $preview['procedimientos'] ?? [],
            'insumos' => $preview['insumos'] ?? [],
            'derechos' => $preview['derechos'] ?? [],
            'oxigeno' => $preview['oxigeno'] ?? [],
            'anestesia' => $preview['anestesia'] ?? [],
            'reglas' => $preview['reglas'] ?? [],
        ]);
    }
}
