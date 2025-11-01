<?php

namespace Modules\Pacientes\Support;

class ViewHelper
{
    public static function safe(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function formatDateSafe(?string $fecha, string $formato = 'd/m/Y'): string
    {
        if (empty($fecha) || !strtotime($fecha)) {
            return '—';
        }

        return date($formato, strtotime($fecha));
    }

    public static function obtenerNombreProcedimiento(string $texto): string
    {
        $partes = explode(' - ', $texto);

        return implode(' - ', array_slice($partes, 2));
    }
}
