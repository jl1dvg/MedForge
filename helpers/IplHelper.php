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
}