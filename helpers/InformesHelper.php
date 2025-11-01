<?php

namespace Helpers;

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;

class InformesHelper
{
    public static function calcularTotalFactura(array $datosPaciente, BillingController $billingController): float
    {
        $total = 0;

        foreach (($datosPaciente['procedimientos'] ?? []) as $index => $p) {
            $codigo = $p['proc_codigo'] ?? '';
            $precio = (float)($p['proc_precio'] ?? 0);

            $porcentaje = ($index === 0 || stripos($p['proc_detalle'], 'separado') !== false) ? 1 : 0.5;
            if ($codigo === '67036') {
                $porcentaje = 0.625;
            }

            $total += $precio * $porcentaje;
        }

        if (!empty($datosPaciente['protocoloExtendido']['cirujano_2']) || !empty($datosPaciente['protocoloExtendido']['primer_ayudante'])) {
            foreach (($datosPaciente['procedimientos'] ?? []) as $index => $p) {
                $precio = (float)($p['proc_precio'] ?? 0);
                $porcentaje = ($index === 0) ? 0.2 : 0.1;
                $total += $precio * $porcentaje;
            }
        }

        foreach (($datosPaciente['anestesia'] ?? []) as $a) {
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
            ['grupo' => 'FARMACIA', 'items' => array_merge($datosPaciente['medicamentos'] ?? [], $datosPaciente['oxigeno'] ?? [])],
            ['grupo' => 'INSUMOS', 'items' => $datosPaciente['insumos'] ?? []],
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

        foreach (($datosPaciente['derechos'] ?? []) as $servicio) {
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

    public static function renderConsolidadoFila($n, $p, $pacienteInfo, $datosPaciente, $edad, $genero, $url, $codigoDerivacion, $referido, $diagnostico, $grupo = '')
    {
        $prefijo = '';
        if (!empty($grupo)) {
            $grupoNormalizado = strtoupper(trim($grupo));
            if (in_array($grupoNormalizado, ['ISSFA', 'ISSPOL'])) {
                $prefijo = $grupoNormalizado . '-';
            } else {
                // Generar iniciales como SG, CV, etc.
                $iniciales = implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), explode(' ', $grupoNormalizado)));
                $prefijo = $iniciales . '-';
            }
        }
        $apellido = trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? ''));
        $nombre = trim(($pacienteInfo['fname'] ?? '') . ' ' . ($pacienteInfo['mname'] ?? ''));
        $fecha_ingreso = $datosPaciente['formulario']['fecha_ordenada'] ?? ($p['fecha'] ?? '');
        $procedimiento = $datosPaciente['procedimientos'][0]["proc_codigo"] ?? '';
        $detalle = $datosPaciente['procedimientos'][0]["proc_detalle"] ?? '';
        $fecha_egreso = $fecha_ingreso;
        $hc_number = $p['hc_number'];
        $monto_sol = number_format($p['total'], 2);

        $referidoWords = preg_split('/\s+/', trim($referido));
        $referidoFormateado = '';

        if (count($referidoWords) === 2) {
            $primera = $referidoWords[0];
            $segunda = strtoupper(mb_substr($referidoWords[1], 0, 1));
            $referidoFormateado = $primera . ' ' . $segunda;
        } elseif (count($referidoWords) >= 3) {
            // Unir DE/DEL con el siguiente si aplica
            if (in_array(strtoupper($referidoWords[0]), ['DE', 'DEL'])) {
                $primera = $referidoWords[0] . ' ' . $referidoWords[1];
                $tercera = strtoupper(mb_substr($referidoWords[2], 0, 1));
            } else {
                $primera = $referidoWords[0];
                $tercera = strtoupper(mb_substr($referidoWords[2], 0, 1));
            }
            $referidoFormateado = $primera . ' ' . $tercera;
        } else {
            $referidoFormateado = $referido;
        }

        return "<tr style='font-size: 12.5px;'>
        <td>{$prefijo}{$n}</td>
            <td>{$hc_number}</td>
            <td>{$apellido}</td>
            <td>{$nombre}</td>
            <td>" . ($fecha_ingreso ? date('d/m/Y', strtotime($fecha_ingreso)) : '--') . "</td>
            <td>" . ($fecha_egreso ? date('d/m/Y', strtotime($fecha_egreso)) : '--') . "</td>
            <td>" . htmlspecialchars(self::extraerCie10($diagnostico)) . "</td>
            <td>" . htmlspecialchars($referidoFormateado) . "</td>
            <td title='" . htmlspecialchars($detalle) . "'>{$procedimiento}</td>
            <td>{$edad}</td>
            <td>{$genero}</td>
            <td>{$monto_sol}</td>
            <td>" .
            (!empty($codigoDerivacion)
                ? "<span class='badge badge-success'>" . htmlspecialchars($codigoDerivacion) . "</span>"
                : "<form method='post' style='display:inline;'>
                          <input type='hidden' name='form_id_scrape' value='" . htmlspecialchars($p['form_id']) . "'>
                            <input type='hidden' name='hc_number_scrape' value='" . htmlspecialchars($hc_number) . "'>
                          <button type='submit' name='scrape_derivacion' class='btn btn-sm btn-warning'>📌 Obtener Código Derivación</button>
                       </form>"
            ) .
            "</td>
            <td><a href='{$url}' class='btn btn-sm btn-info'>Ver detalle</a></td>
        </tr>";
    }

    public static function formatearListaProcedimientos(array $bloques): string {
        $lista = [];
        foreach ($bloques as $grupo) {
            foreach ($grupo as $p) {
                if (!empty($p['descripcion'])) {
                    $lista[] = trim($p['descripcion']);
                }
            }
        }
        return implode('; ', array_unique($lista));
    }

    public static function obtenerConsolidadoFiltrado(
        array             $facturas,
        array             $filtros,
        BillingController $billingController,
        PacienteService   $pacienteService,
        array             $afiliacionesPermitidas = []
    ): array
    {
        $consolidado = [];

        foreach ($facturas as $factura) {
            $pacienteInfo = $pacienteService->getPatientDetails($factura['hc_number']);
            if (!is_array($pacienteInfo)) {
                continue;
            }
            $afiliacion = self::normalizarAfiliacion($pacienteInfo['afiliacion'] ?? '');
            if ($afiliacionesPermitidas && !in_array($afiliacion, $afiliacionesPermitidas)) continue;

            $datosPaciente = $billingController->obtenerDatos($factura['form_id']);
            if (!$datosPaciente) continue;

            $fechaFactura = $factura['fecha_ordenada'];
            $mes = (!empty($fechaFactura) && strtotime($fechaFactura)) ? date('Y-m', strtotime($fechaFactura)) : 'desconocido';
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

    public static function filtrarPacientes(array $pacientes, array &$pacientesCache, array &$datosCache, PacienteService $pacienteService, $billingController, string $apellidoFiltro): array
    {
        return array_filter($pacientes, function ($p) use (&$pacientesCache, &$datosCache, $pacienteService, $billingController, $apellidoFiltro) {
            $hc = $p['hc_number'];
            $fid = $p['form_id'];

            if (!isset($pacientesCache[$hc])) {
                $pacientesCache[$hc] = $pacienteService->getPatientDetails($hc);
            }

            if (!isset($datosCache[$fid])) {
                $datosCache[$fid] = $billingController->obtenerDatos($fid);
            }

            $pacienteInfo = $pacientesCache[$hc] ?? [];
            $apellidoCompleto = strtolower(trim(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '')));

            return (!$apellidoFiltro || str_contains($apellidoCompleto, $apellidoFiltro));
        });
    }

    public static function extraerCie10(string $diagnostico): string
    {
        $cie10s = [];
        $partes = explode(';', $diagnostico);
        foreach ($partes as $parte) {
            if (preg_match('/^\s*([A-Z]\d{2,3})\s*-/', trim($parte), $matches)) {
                $cie10s[] = $matches[1];
            }
        }
        return implode(', ', $cie10s);
    }
}
