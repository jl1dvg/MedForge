<?php

namespace Modules\Billing\Services;

use Controllers\BillingController as LegacyBillingController;
use Modules\Pacientes\Services\PacienteService;

class BillingViewService
{
    public function __construct(
        private readonly LegacyBillingController $billingController,
        private readonly PacienteService $pacienteService
    ) {
    }

    public function obtenerListadoFacturas(?string $mes = null): array
    {
        $facturas = $this->billingController->obtenerFacturasDisponibles($mes);

        $enriquecidas = array_map(function (array $factura): array {
            $paciente = $this->pacienteService->getPatientDetails($factura['hc_number']);
            $nombre = $this->formatearNombrePaciente($paciente);

            return [
                'form_id' => $factura['form_id'],
                'hc_number' => $factura['hc_number'],
                'fecha' => $factura['fecha_ordenada'] ?? null,
                'paciente' => [
                    'nombre' => $nombre,
                    'afiliacion' => $paciente['afiliacion'] ?? null,
                    'ci' => $paciente['ci'] ?? null,
                ],
            ];
        }, $facturas);

        return [
            'facturas' => $enriquecidas,
            'mesSeleccionado' => $mes,
        ];
    }

    public function obtenerDetalleFactura(string $formId): ?array
    {
        $datos = $this->billingController->obtenerDatos($formId);
        if (!$datos) {
            return null;
        }

        $derivacion = $this->billingController->obtenerDerivacionPorFormId($formId) ?: [];

        $grupoClases = [
            'CIRUJANO' => 'table-primary',
            'AYUDANTE' => 'table-secondary',
            'ANESTESIA' => 'table-danger',
            'FARMACIA' => 'table-success',
            'INSUMOS' => 'table-warning',
            'DERECHOS' => 'table-info',
        ];

        $detallePorGrupo = [];
        foreach (array_keys($grupoClases) as $grupo) {
            $detallePorGrupo[$grupo] = [];
        }

        foreach ($datos['procedimientos'] ?? [] as $procedimiento) {
            $grupo = strtoupper($procedimiento['grupo'] ?? '');
            if (isset($detallePorGrupo[$grupo])) {
                $detallePorGrupo[$grupo][] = $procedimiento;
            }
        }

        foreach ($datos['insumos'] ?? [] as $insumo) {
            $detallePorGrupo['INSUMOS'][] = $insumo + [
                'proc_codigo' => $insumo['codigo'] ?? null,
                'proc_detalle' => $insumo['nombre'] ?? null,
                'proc_precio' => $insumo['precio'] ?? 0,
            ];
        }

        foreach ($datos['medicamentos'] ?? [] as $medicamento) {
            $detallePorGrupo['FARMACIA'][] = $medicamento + [
                'proc_codigo' => $medicamento['codigo'] ?? null,
                'proc_detalle' => $medicamento['nombre'] ?? null,
                'proc_precio' => $medicamento['precio'] ?? 0,
            ];
        }

        foreach ($datos['derechos'] ?? [] as $derecho) {
            $detallePorGrupo['DERECHOS'][] = $derecho + [
                'proc_codigo' => $derecho['codigo'] ?? null,
                'proc_detalle' => $derecho['detalle'] ?? null,
                'proc_precio' => $derecho['precio_afiliacion'] ?? 0,
            ];
        }

        foreach ($datos['anestesia'] ?? [] as $anestesia) {
            $detallePorGrupo['ANESTESIA'][] = $anestesia + [
                'proc_codigo' => $anestesia['codigo'] ?? null,
                'proc_detalle' => $anestesia['nombre'] ?? null,
                'proc_precio' => $anestesia['precio'] ?? 0,
            ];
        }

        $subtotales = [];
        foreach ($detallePorGrupo as $grupo => $items) {
            $totalGrupo = 0.0;
            foreach ($items as $item) {
                $cantidad = (float)($item['cantidad'] ?? 1);
                $precio = (float)($item['proc_precio'] ?? 0);
                $totalGrupo += $cantidad * $precio;
            }
            $subtotales[$grupo] = $totalGrupo;
        }

        $totalSinIva = array_sum($subtotales);
        $iva = $totalSinIva * 0.15;
        $totalConIva = $totalSinIva + $iva;

        $paciente = $datos['paciente'] ?? [];

        return [
            'billing' => $datos['billing'] ?? [],
            'paciente' => $paciente,
            'procedimientosPorGrupo' => $detallePorGrupo,
            'subtotales' => $subtotales,
            'totalSinIVA' => $totalSinIva,
            'iva' => $iva,
            'totalConIVA' => $totalConIva,
            'grupoClases' => $grupoClases,
            'metadata' => [
                'codigoDerivacion' => $derivacion['cod_derivacion'] ?? null,
                'doctor' => $derivacion['referido'] ?? null,
                'fecha_registro' => $derivacion['fecha_registro'] ?? null,
                'fecha_vigencia' => $derivacion['fecha_vigencia'] ?? null,
                'diagnostico' => $derivacion['diagnostico'] ?? null,
            ],
        ];
    }

    private function formatearNombrePaciente(array $paciente): string
    {
        $partes = array_filter([
            $paciente['lname'] ?? null,
            $paciente['lname2'] ?? null,
            $paciente['fname'] ?? null,
            $paciente['mname'] ?? null,
        ]);

        return trim(implode(' ', $partes)) ?: 'Desconocido';
    }
}
