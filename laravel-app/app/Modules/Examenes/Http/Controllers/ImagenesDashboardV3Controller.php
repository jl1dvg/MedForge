<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ImagenesDashboardV3Service;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImagenesDashboardV3Controller
{
    private ImagenesDashboardV3Service $service;

    public function __construct()
    {
        $this->service = new ImagenesDashboardV3Service();
    }

    public function index(Request $request): View
    {
        $payload = $this->service->dashboardData($request->query());

        return view('examenes.v3-imagenes-dashboard', [
            'pageTitle' => 'Dashboard V3 de Imágenes',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'initialPayload' => $payload,
            'filters' => $payload['filters'] ?? [],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->service->dashboardData($request->query()));
    }

    public function detail(Request $request): JsonResponse
    {
        return response()->json($this->service->detailRows($request->query()));
    }

    public function export(Request $request): Response
    {
        $payload = $this->service->exportPayload($request->query());
        $rows = [
            ['Seccion', 'Indicador', 'Valor'],
            ['Resumen ejecutivo', 'Facturado real', $payload['executive']['facturado_real'] ?? 0],
            ['Resumen ejecutivo', 'Honorario real', $payload['executive']['honorario_real'] ?? 0],
            ['Resumen ejecutivo', 'Pendiente de facturar', $payload['executive']['pendiente_de_facturar'] ?? 0],
            ['Resumen ejecutivo', 'Pendiente de cobrar', $payload['executive']['pendiente_de_cobrar'] ?? 0],
            ['Resumen ejecutivo', 'Perdida estimada', $payload['executive']['perdida_estimada'] ?? 0],
            ['Resumen ejecutivo', 'Oportunidad de recuperacion', $payload['executive']['oportunidad_recuperacion'] ?? 0],
            ['Solicitudes', 'Solicitudes recibidas', $payload['solicitudes']['solicitudes_recibidas'] ?? 0],
            ['Solicitudes', 'Solicitudes sin agenda', $payload['solicitudes']['solicitudes_sin_agenda'] ?? 0],
            ['Operacion', 'Agendas del periodo', $payload['operacion']['agendas_periodo'] ?? 0],
            ['Operacion', 'Atendidas', $payload['operacion']['atendidas'] ?? 0],
            ['Facturacion', 'Estudios con billing real', $payload['billing']['estudios_con_billing_real'] ?? 0],
            ['Facturacion', 'Realizados sin billing real', $payload['billing']['realizados_sin_billing_real'] ?? 0],
        ];

        $csv = implode("\n", array_map(static function (array $row): string {
            return implode(',', array_map(static function (mixed $value): string {
                $escaped = str_replace('"', '""', (string) $value);

                return '"' . $escaped . '"';
            }, $row));
        }, $rows));

        return response($csv . "\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="imagenes-dashboard-v3.csv"',
        ]);
    }
}
