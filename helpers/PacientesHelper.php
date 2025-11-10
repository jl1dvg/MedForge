<?php

namespace Helpers;

class PacientesHelper
{
    public static function safe($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function formatDateSafe($fecha, $formato = 'd/m/Y')
    {
        if (empty($fecha) || !strtotime($fecha)) {
            return '—';
        }
        return date($formato, strtotime($fecha));
    }

    public static function obtenerNombreProcedimiento($texto)
    {
        $partes = explode(' - ', $texto);
        return implode(' - ', array_slice($partes, 2));
    }
}