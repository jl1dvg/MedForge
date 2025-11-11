<?php

namespace Core;

class Permissions
{
    public const SUPERUSER = 'superuser';
    private const LEGACY_PERMISSION_MAP = [
        'pacientes.manage' => ['pacientes.view', 'pacientes.create', 'pacientes.edit', 'pacientes.delete'],
        'cirugias.manage' => ['cirugias.view', 'cirugias.create', 'cirugias.edit', 'cirugias.delete'],
        'insumos.manage' => ['insumos.view', 'insumos.create', 'insumos.edit', 'insumos.delete'],
        'admin.usuarios' => ['admin.usuarios.view', 'admin.usuarios.manage'],
        'admin.roles' => ['admin.roles.view', 'admin.roles.manage'],
        'settings.manage' => ['settings.view', 'settings.manage'],
        'codes.manage' => ['codes.view', 'codes.manage'],
        'crm.manage' => ['crm.view', 'crm.leads.manage', 'crm.projects.manage', 'crm.tasks.manage', 'crm.tickets.manage'],
        'crm.leads.manage' => ['crm.view'],
        'crm.projects.manage' => ['crm.view'],
        'crm.tasks.manage' => ['crm.view'],
        'crm.tickets.manage' => ['crm.view'],
        'whatsapp.manage' => ['whatsapp.chat.view', 'whatsapp.chat.send', 'whatsapp.templates.manage', 'whatsapp.autoresponder.manage'],
        'whatsapp.chat.send' => ['whatsapp.chat.view'],
        'whatsapp.templates.manage' => ['whatsapp.chat.view'],
        'whatsapp.autoresponder.manage' => ['whatsapp.chat.view'],
        'ai.manage' => ['ai.consultas.enfermedad', 'ai.consultas.plan'],
        'protocolos.manage' => ['protocolos.templates.view', 'protocolos.templates.manage'],
        'protocolos.templates.manage' => ['protocolos.templates.view'],
    ];

    /**
     * Normaliza cualquier representación de permisos a un arreglo de strings.
     */
    public static function normalize(mixed $value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            $normalized = array_values(array_unique(array_filter(array_map(static fn($item) => is_string($item) ? trim($item) : '', $value), static fn($item) => $item !== '')));
        } elseif (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $normalized = self::normalize($decoded);
            } else {
                $normalized = [$value];
            }
        } else {
            $normalized = [];
        }

        $expanded = [];
        foreach ($normalized as $permission) {
            if (!in_array($permission, $expanded, true)) {
                $expanded[] = $permission;
            }

            if (isset(self::LEGACY_PERMISSION_MAP[$permission])) {
                foreach (self::LEGACY_PERMISSION_MAP[$permission] as $alias) {
                    if (!in_array($alias, $expanded, true)) {
                        $expanded[] = $alias;
                    }
                }
            }
        }

        return $expanded;
    }

    public static function contains(mixed $value, string $permission): bool
    {
        $normalized = self::normalize($value);
        if (in_array(self::SUPERUSER, $normalized, true)) {
            return true;
        }

        if (in_array($permission, $normalized, true)) {
            return true;
        }

        if (isset(self::LEGACY_PERMISSION_MAP[$permission])) {
            foreach (self::LEGACY_PERMISSION_MAP[$permission] as $alias) {
                if (in_array($alias, $normalized, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function containsAny(mixed $value, array $permissions): bool
    {
        $normalized = self::normalize($value);
        if (in_array(self::SUPERUSER, $normalized, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (in_array($permission, $normalized, true)) {
                return true;
            }

            if (isset(self::LEGACY_PERMISSION_MAP[$permission])) {
                foreach (self::LEGACY_PERMISSION_MAP[$permission] as $alias) {
                    if (in_array($alias, $normalized, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Fusiona múltiples colecciones de permisos en una lista única normalizada.
     */
    public static function merge(mixed ...$groups): array
    {
        $merged = [];

        foreach ($groups as $group) {
            foreach (self::normalize($group) as $permission) {
                if (!in_array($permission, $merged, true)) {
                    $merged[] = $permission;
                }
            }
        }

        return $merged;
    }
}
