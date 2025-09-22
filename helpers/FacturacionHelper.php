<?php

namespace Helpers;

use PDO;

class FacturacionHelper
{
    /**
     * Calcula los datos de oxígeno según la duración en formato HH:MM
     */
    public static function calcularOxigeno(string $duracion): array
    {
        [$h, $m] = explode(':', $duracion);
        $tiempo = (float)$h + ((int)$m / 60);

        $litros = 3;
        $valor1 = 60.00;
        $valor2 = 0.01;

        return [
            'codigo' => '911111',
            'nombre' => 'OXIGENO',
            'tiempo' => $tiempo,
            'litros' => $litros,
            'valor1' => $valor1,
            'valor2' => $valor2,
            'precio' => round($tiempo * $litros * $valor1 * $valor2, 2)
        ];
    }

    /**
     * Calcula la anestesia en bloques de 15 min según afiliación y código de cirugía
     */
    public static function calcularAnestesia(string $duracion, string $afiliacion, string $codigoCirugia): array
    {
        [$h, $m] = explode(':', $duracion);
        $cuartos = ceil(((int)$h * 60 + (int)$m) / 15);

        $afiliacion = strtoupper(trim($afiliacion));
        $resultado = [];

        if ($afiliacion === "ISSFA" && $codigoCirugia === "66984") {
            $resultado[] = [
                'codigo' => '999999',
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];
        } elseif ($afiliacion === "ISSFA") {
            $cantidad99149 = ($cuartos >= 2) ? 1 : $cuartos;
            $cantidad99150 = ($cuartos > 2) ? $cuartos - 2 : 0;

            if ($cantidad99149 > 0) {
                $resultado[] = [
                    'codigo' => '99149',
                    'nombre' => 'SEDACIÓN INICIAL 30 MIN',
                    'tiempo' => $cantidad99149,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99149 * 13.34, 2)
                ];
            }

            if ($cantidad99150 > 0) {
                $resultado[] = [
                    'codigo' => '99150',
                    'nombre' => 'SEDACIÓN ADICIONAL 15 MIN',
                    'tiempo' => $cantidad99150,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99150 * 13.34, 2)
                ];
            }
        } else {
            $resultado[] = [
                'codigo' => '999999',
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];
        }

        return $resultado;
    }

    public static function obtenerDerechoPorDuracion(PDO $db, int $duracionMinutos): array
    {
        $stmt = $db->prepare("
        SELECT id, codigo, descripcion, valor_facturar_nivel1
        FROM tarifario_2014
        WHERE descripcion LIKE 'DESDE%' OR descripcion LIKE 'HASTA%'
        ORDER BY id ASC
    ");
        $stmt->execute();
        $derechos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grupoA = []; // ej. ids 3962xx
        $grupoB = []; // ej. ids 3942xx

        foreach ($derechos as $d) {
            if ((int)$d['codigo'] >= 394200 && (int)$d['codigo'] < 394400) {
                $grupoA[] = $d;
            } elseif ((int)$d['codigo'] >= 396200 && (int)$d['codigo'] < 396400) {
                $grupoB[] = $d;
            }
        }

        $resultado = [];

        // 🔹 Buscar en cada grupo
        foreach (['A' => $grupoA, 'B' => $grupoB] as $grupo => $items) {
            foreach ($items as $d) {
                if (preg_match('/DESDE (\d+) MIN.*HASTA ?(\d+) MIN/i', $d['descripcion'], $m)) {
                    $desde = (int)$m[1];
                    $hasta = (int)$m[2];
                    if ($duracionMinutos >= $desde && $duracionMinutos <= $hasta) {
                        $resultado[] = [
                            'grupo' => $grupo,
                            'codigo' => $d['codigo'],
                            'detalle' => $d['descripcion'],
                            'precioAfiliacion' => (float)$d['valor_facturar_nivel1']
                        ];
                        break; // ✅ tomamos solo uno
                    }
                } elseif (preg_match('/HASTA ?(\d+)MIN/i', $d['descripcion'], $m)) {
                    $hasta = (int)$m[1];
                    if ($duracionMinutos <= $hasta) {
                        $resultado[] = [
                            'grupo' => $grupo,
                            'codigo' => $d['codigo'],
                            'detalle' => $d['descripcion'],
                            'precioAfiliacion' => (float)$d['valor_facturar_nivel1']
                        ];
                        break; // ✅ tomamos solo uno
                    }
                }
            }
        }

        // 🔹 Siempre agregar el código fijo 395281
        $stmtFijo = $db->prepare("SELECT codigo, descripcion, valor_facturar_nivel1 FROM tarifario_2014 WHERE codigo = '395281' LIMIT 1");
        $stmtFijo->execute();
        if ($fijo = $stmtFijo->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = [
                'grupo' => 'FIJO',
                'codigo' => $fijo['codigo'],
                'detalle' => $fijo['descripcion'],
                'precioAfiliacion' => (float)$fijo['valor_facturar_nivel1']
            ];
        }

        return $resultado; // ✅ ahora devolverá: uno de grupo A, uno de grupo B y siempre el 395281
    }
}