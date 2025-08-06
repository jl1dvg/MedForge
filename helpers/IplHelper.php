<?php

namespace Helpers;

class IplHelper
{
    public static function formatearFecha(?string $fecha): string
    {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return '-';
        }

        return date('d/m/Y', strtotime($fecha));
    }

    public static function claseFilaEstado(string $estado): string
    {
        return match (true) {
            str_contains($estado, '✅') => 'success',
            str_contains($estado, '⚠️') => 'warning',
            str_contains($estado, '❌') => 'danger',
            default => 'light',
        };
    }

    public static function iconoEstado(string $estado): string
    {
        return match (true) {
            str_contains($estado, '✅') => '🟢',
            str_contains($estado, '⚠️') => '🟠',
            str_contains($estado, '❌') => '🔴',
            default => '⚪',
        };
    }

    public static function estadoTexto(string $estado): string
    {
        return htmlspecialchars($estado);
    }

    public static function nombreCompleto(array $row): string
    {
        return trim("{$row['fname']} {$row['lname']} {$row['lname2']}");
    }

    public static function calcularSesionesFaltantes(array $fechasIdeales, array $fechasRealizadas): int
    {
        $realizadas = 0;

        foreach ($fechasIdeales as $fIdeal) {
            $fechaIdeal = is_array($fIdeal) ? $fIdeal['fecha'] : $fIdeal;

            foreach ($fechasRealizadas as $sesion) {
                if (
                    isset($sesion['fecha_real'], $sesion['estado'], $sesion['fecha_ideal']) &&
                    !empty($sesion['fecha_real']) &&
                    strpos($sesion['estado'], 'Dado de Alta') !== false &&
                    substr($sesion['fecha_ideal'], 0, 10) === $fechaIdeal
                ) {
                    $realizadas++;
                    break;
                }
            }
        }

        return count($fechasIdeales) - $realizadas;
    }

    public static function toDateTime($fecha)
    {
        if ($fecha instanceof \DateTime) {
            return $fecha;
        }

        try {
            return new \DateTime($fecha);
        } catch (\Exception $e) {
            return null;
        }
    }
}