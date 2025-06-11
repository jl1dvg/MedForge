<?php

namespace Helpers;

use Controllers\BillingController;
use Controllers\PacienteController;

class InformesHelper
{
    public static function calcularTotalFactura(array $datosPaciente, BillingController $billingController): float
    {
        $total = 0;

        foreach ($datosPaciente['procedimientos'] as $index => $p) {
            $codigo = $p['proc_codigo'] ?? '';
            $precio = (float)($p['proc_precio'] ?? 0);

            $porcentaje = ($index === 0 || stripos($p['proc_detalle'], 'separado') !== false) ? 1 : 0.5;
            if ($codigo === '67036') {
                $porcentaje = 0.625;
            }

            $total += $precio * $porcentaje;
        }

        if (!empty($datosPaciente['protocoloExtendido']['cirujano_2']) || !empty($datosPaciente['protocoloExtendido']['primer_ayudante'])) {
            foreach ($datosPaciente['procedimientos'] as $index => $p) {
                $precio = (float)($p['proc_precio'] ?? 0);
                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                $total += $precio * $porcentaje;
            }
        }

        foreach ($datosPaciente['anestesia'] as $a) {
            $valor2 = (float)($a['valor2'] ?? 0);
            $tiempo = (float)($a['tiempo'] ?? 0);
            $total += $valor2 * $tiempo;
        }

        if (!empty($datosPaciente['procedimientos'][0])) {
            $codigo = $datosPaciente['procedimientos'][0]['proc_codigo'] ?? '';
            $precioReal = $codigo ? $billingController->obtenerValorAnestesia($codigo) : null;
            $valorUnitario = $precioReal ?? (float)($datosPaciente['procedimientos'][0]['proc_precio'] ?? 0);
            $total += $valorUnitario;
        }

        $fuenteDatos = [
            ['grupo' => 'FARMACIA', 'items' => array_merge($datosPaciente['medicamentos'], $datosPaciente['oxigeno'])],
            ['grupo' => 'INSUMOS', 'items' => $datosPaciente['insumos']],
        ];

        foreach ($fuenteDatos as $bloque) {
            foreach ($bloque['items'] as $item) {
                $valorUnitario = 0;
                $cantidad = 1;

                if (isset($item['litros'], $item['tiempo'], $item['valor2'])) {
                    $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                    $valorUnitario = (float)$item['valor2'];
                } else {
                    $cantidad = $item['cantidad'] ?? 1;
                    $valorUnitario = $item['precio'] ?? 0;
                }

                $subtotal = $valorUnitario * $cantidad;
                $iva = ($bloque['grupo'] === 'FARMACIA') ? 0 : 1;
                $total += $subtotal + ($iva ? $subtotal * 0.1 : 0);
            }
        }

        foreach ($datosPaciente['derechos'] as $servicio) {
            $valorUnitario = $servicio['precio_afiliacion'] ?? 0;
            $cantidad = $servicio['cantidad'] ?? 1;
            $total += $valorUnitario * $cantidad;
        }

        return $total;
    }

    public static function renderFilaDetalle($data)
    {
        return "<tr>
            <td>{$data['tipo']}</td>
            <td>{$data['cedulaPaciente']}</td>
            <td>{$data['periodo']}</td>
            <td>{$data['grupo']}</td>
            <td>{$data['tipoProc']}</td>
            <td>{$data['cedulaMedico']}</td>
            <td>{$data['fecha']}</td>
            <td>{$data['codigo']}</td>
            <td>{$data['descripcion']}</td>
            <td>{$data['anestesia']}</td>
            <td>{$data['porcentajePago']}</td>
            <td>{$data['cantidad']}</td>
            <td>" . number_format($data['valorUnitario'], 2) . "</td>
            <td>" . number_format($data['subtotal'], 2) . "</td>
            <td>{$data['bodega']}</td>
            <td>{$data['iva']}</td>
            <td>" . number_format($data['total'], 2) . "</td>
        </tr>";
    }

    public static function renderConsolidadoFila($n, $p, $pacienteInfo, $datosPaciente, $edad, $genero, $url, $grupo = '')
    {
        $prefijo = $grupo ? strtoupper($grupo) . '-' : '';
        $apellido = trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? ''));
        $nombre = trim(($pacienteInfo['fname'] ?? '') . ' ' . ($pacienteInfo['mname'] ?? ''));
        $fecha_ingreso = $datosPaciente['formulario']['fecha_inicio'] ?? ($p['fecha'] ?? '');
        $fecha_egreso = $fecha_ingreso;
        $cie10 = $datosPaciente['formulario']['diagnostico1_codigo'] ?? '--';
        $desc_diag = $datosPaciente['formulario']['diagnostico1_nombre'] ?? '--';
        $hc_number = $p['hc_number'];
        $items = 75;
        $monto_sol = number_format($p['total'], 2);

        return "<tr>
        <td>{$prefijo}{$n}</td>
            <td>{$hc_number}</td>
            <td>{$apellido}</td>
            <td>{$nombre}</td>
            <td>" . ($fecha_ingreso ? date('d/m/Y', strtotime($fecha_ingreso)) : '--') . "</td>
            <td>" . ($fecha_egreso ? date('d/m/Y', strtotime($fecha_egreso)) : '--') . "</td>
            <td>{$cie10}</td>
            <td>{$desc_diag}</td>
            <td>{$hc_number}</td>
            <td>{$edad}</td>
            <td>{$genero}</td>
            <td>{$items}</td>
            <td>{$monto_sol}</td>
                        <td>
                <a href='{$url}' class='btn btn-sm btn-info'>Ver detalle</a>
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='form_id_scrape' value='{$p['form_id']}'>
                    <button type='submit' name='scrape_derivacion' class='btn btn-sm btn-warning'>📌 Obtener Código Derivación</button>
                </form>
            </td>
        </tr>";
    }

    public static function obtenerConsolidadoFiltrado(
        array              $facturas,
        array              $filtros,
        BillingController  $billingController,
        PacienteController $pacienteController,
        array              $afiliacionesPermitidas = []
    ): array
    {
        $consolidado = [];

        foreach ($facturas as $factura) {
            $pacienteInfo = $pacienteController->getPatientDetails($factura['hc_number']);
            $afiliacion = self::normalizarAfiliacion($pacienteInfo['afiliacion'] ?? '');
            if ($afiliacionesPermitidas && !in_array($afiliacion, $afiliacionesPermitidas)) continue;

            $datosPaciente = $billingController->obtenerDatos($factura['form_id']);
            if (!$datosPaciente) continue;

            $fechaFactura = $factura['fecha_inicio'];
            $mes = date('Y-m', strtotime($fechaFactura));
            if (!empty($filtros['mes']) && $mes !== $filtros['mes']) continue;

            $apellidoCompleto = strtolower(trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '')));
            if (!empty($filtros['apellido']) && !str_contains($apellidoCompleto, strtolower($filtros['apellido']))) {
                continue;
            }

            $total = InformesHelper::calcularTotalFactura($datosPaciente, $billingController);

            $consolidado[$mes][] = [
                'nombre' => $pacienteInfo['lname'] . ' ' . $pacienteInfo['fname'],
                'hc_number' => $factura['hc_number'],
                'form_id' => $factura['form_id'],
                'fecha' => $fechaFactura,
                'total' => $total,
                'id' => $factura['id'],
            ];
        }

        return $consolidado;
    }

    public static function normalizarAfiliacion($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/\s+/', ' ', $str);
        $str = strtr($str, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n'
        ]);
        return $str;
    }

    public static function filtrarPacientes(array $pacientes, array &$pacientesCache, array &$datosCache, $pacienteController, $billingController, string $apellidoFiltro): array
    {
        return array_filter($pacientes, function ($p) use (&$pacientesCache, &$datosCache, $pacienteController, $billingController, $apellidoFiltro) {
            $hc = $p['hc_number'];
            $fid = $p['form_id'];

            if (!isset($pacientesCache[$hc])) {
                $pacientesCache[$hc] = $pacienteController->getPatientDetails($hc);
            }

            if (!isset($datosCache[$fid])) {
                $datosCache[$fid] = $billingController->obtenerDatos($fid);
            }

            $pacienteInfo = $pacientesCache[$hc] ?? [];
            $apellidoCompleto = strtolower(trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '')));

            return (!$apellidoFiltro || str_contains($apellidoCompleto, $apellidoFiltro));
        });
    }
}
