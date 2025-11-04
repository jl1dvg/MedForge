<?php

namespace Modules\Usuarios\Support;

class PermissionRegistry
{
    public static function groups(): array
    {
        return [
            'General' => [
                'dashboard.view' => 'Acceder al panel principal',
            ],
            'Pacientes' => [
                'pacientes.view' => 'Ver listado de pacientes',
                'pacientes.manage' => 'Crear y editar pacientes',
            ],
            'Cirugías' => [
                'cirugias.view' => 'Ver protocolos registrados',
                'cirugias.manage' => 'Gestionar protocolos y solicitudes',
            ],
            'Insumos' => [
                'insumos.view' => 'Consultar inventario de insumos',
                'insumos.manage' => 'Administrar insumos y medicamentos',
            ],
            'Reportes' => [
                'reportes.view' => 'Visualizar reportes e informes',
            ],
            'Administración' => [
                'admin.usuarios' => 'Gestionar usuarios',
                'admin.roles' => 'Gestionar roles',
                'settings.manage' => 'Administrar configuración del sistema',
                'codes.manage' => 'Administrar codificación',
            ],
            'Compatibilidad' => [
                'administrativo' => 'Rol administrativo (compatibilidad)',
                'superuser' => 'Acceso total (superusuario)',
            ],
        ];
    }

    public static function all(): array
    {
        $flat = [];
        foreach (self::groups() as $permissions) {
            foreach ($permissions as $key => $label) {
                $flat[$key] = $label;
            }
        }

        return $flat;
    }

    public static function sanitizeSelection(array $selected): array
    {
        $valid = array_keys(self::all());
        $normalized = [];

        foreach ($selected as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '' || !in_array($item, $valid, true)) {
                continue;
            }

            if (!in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
