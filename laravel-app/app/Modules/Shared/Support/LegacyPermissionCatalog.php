<?php

namespace App\Modules\Shared\Support;

class LegacyPermissionCatalog
{
    public const SUPERUSER = 'superuser';

    /**
     * @var array<string, array<int, string>>
     */
    private const ALIAS_MAP = [
        'pacientes.manage' => ['pacientes.view', 'pacientes.create', 'pacientes.edit', 'pacientes.delete'],
        'cirugias.manage' => ['cirugias.view', 'cirugias.create', 'cirugias.edit', 'cirugias.delete'],
        'insumos.manage' => ['insumos.view', 'insumos.create', 'insumos.edit', 'insumos.delete'],
        'admin.usuarios' => ['admin.usuarios.view', 'admin.usuarios.manage'],
        'admin.roles' => ['admin.roles.view', 'admin.roles.manage'],
        'settings.manage' => ['settings.view'],
        'codes.manage' => ['codes.view'],
        'crm.manage' => ['crm.view', 'crm.leads.manage', 'crm.projects.manage', 'crm.tasks.manage', 'crm.tickets.manage'],
        'crm.leads.manage' => ['crm.view'],
        'crm.projects.manage' => ['crm.view'],
        'crm.tasks.manage' => ['crm.view'],
        'crm.tickets.manage' => ['crm.view'],
        'whatsapp.manage' => ['whatsapp.chat.view', 'whatsapp.chat.send', 'whatsapp.chat.assign', 'whatsapp.chat.supervise', 'whatsapp.templates.manage', 'whatsapp.autoresponder.manage'],
        'whatsapp.chat.send' => ['whatsapp.chat.view'],
        'whatsapp.chat.assign' => ['whatsapp.chat.view'],
        'whatsapp.chat.supervise' => ['whatsapp.chat.view', 'whatsapp.chat.assign'],
        'whatsapp.templates.manage' => ['whatsapp.chat.view'],
        'whatsapp.autoresponder.manage' => ['whatsapp.chat.view'],
        'ai.manage' => ['ai.consultas.enfermedad', 'ai.consultas.plan'],
        'protocolos.manage' => ['protocolos.templates.view', 'protocolos.templates.manage'],
        'protocolos.templates.manage' => ['protocolos.templates.view'],
        'doctores.manage' => ['doctores.view'],
        'solicitudes.manage' => ['solicitudes.view', 'solicitudes.update', 'solicitudes.turnero', 'solicitudes.dashboard.view', 'solicitudes.checklist.override'],
        'examenes.manage' => ['examenes.view', 'examenes.checklist.override'],
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'General' => [
                'dashboard.view' => 'Acceder al panel principal',
                'agenda.view' => 'Agenda - Acceder',
            ],
            'Pacientes' => [
                'pacientes.view' => 'Pacientes - Ver',
                'pacientes.create' => 'Pacientes - Crear',
                'pacientes.edit' => 'Pacientes - Editar',
                'pacientes.delete' => 'Pacientes - Eliminar',
                'pacientes.flujo.view' => 'Flujo de pacientes - Ver',
                'pacientes.verification.view' => 'Certificación biométrica - Ver',
                'pacientes.verification.manage' => 'Certificación biométrica - Gestionar',
            ],
            'Derivaciones' => [
                'derivaciones.view' => 'Derivaciones - Ver',
            ],
            'Cirugías' => [
                'cirugias.view' => 'Cirugías - Ver',
                'cirugias.dashboard.view' => 'Cirugías - Dashboard',
                'cirugias.create' => 'Cirugías - Registrar',
                'cirugias.edit' => 'Cirugías - Editar',
                'cirugias.delete' => 'Cirugías - Anular',
                'cirugias.manage' => 'Cirugías - Acceso total (atajo)',
            ],
            'Insumos' => [
                'insumos.view' => 'Insumos - Ver',
                'insumos.create' => 'Insumos - Crear',
                'insumos.edit' => 'Insumos - Editar',
                'insumos.delete' => 'Insumos - Eliminar',
                'insumos.manage' => 'Insumos - Acceso total (atajo)',
            ],
            'CRM' => [
                'crm.view' => 'CRM - Acceder y consultar',
                'crm.leads.manage' => 'CRM - Gestionar leads',
                'crm.projects.manage' => 'CRM - Gestionar proyectos',
                'crm.tasks.manage' => 'CRM - Gestionar tareas',
                'crm.tickets.manage' => 'CRM - Gestionar tickets',
                'crm.manage' => 'CRM - Acceso total (atajo)',
            ],
            'WhatsApp' => [
                'whatsapp.chat.view' => 'WhatsApp - Ver conversaciones',
                'whatsapp.chat.send' => 'WhatsApp - Enviar mensajes',
                'whatsapp.chat.assign' => 'WhatsApp - Asignar o transferir chats',
                'whatsapp.chat.supervise' => 'WhatsApp - Supervisar, reasignar y cerrar chats',
                'whatsapp.templates.manage' => 'WhatsApp - Gestionar plantillas',
                'whatsapp.autoresponder.manage' => 'WhatsApp - Gestionar automatizaciones',
                'whatsapp.manage' => 'WhatsApp - Acceso total (atajo)',
            ],
            'Inteligencia Artificial' => [
                'ai.consultas.enfermedad' => 'IA - Generar resumen de enfermedad',
                'ai.consultas.plan' => 'IA - Generar plan de tratamiento',
                'ai.manage' => 'IA - Acceso total (atajo)',
            ],
            'Solicitudes' => [
                'solicitudes.view' => 'Solicitudes - Ver',
                'solicitudes.update' => 'Solicitudes - Actualizar etapas',
                'solicitudes.turnero' => 'Solicitudes - Usar turnero',
                'solicitudes.dashboard.view' => 'Solicitudes - Ver dashboard',
                'solicitudes.checklist.override' => 'Solicitudes - Forzar checklist',
                'solicitudes.manage' => 'Solicitudes - Acceso total (atajo)',
            ],
            'Exámenes' => [
                'examenes.view' => 'Exámenes - Ver y exportar',
                'examenes.checklist.override' => 'Exámenes - Forzar checklist',
                'examenes.manage' => 'Exámenes - Acceso total (atajo)',
            ],
            'Protocolos' => [
                'protocolos.templates.view' => 'Plantillas de protocolos - Ver',
                'protocolos.templates.manage' => 'Plantillas de protocolos - Crear y editar',
                'protocolos.manage' => 'Plantillas de protocolos - Acceso total (atajo)',
            ],
            'Reportes' => [
                'reportes.view' => 'Visualizar reportes e informes',
                'reportes.export' => 'Exportar reportes',
            ],
            'Doctores' => [
                'doctores.view' => 'Doctores - Ver',
                'doctores.manage' => 'Doctores - Gestionar',
            ],
            'Administración' => [
                'admin.usuarios.view' => 'Usuarios - Ver',
                'admin.usuarios.manage' => 'Usuarios - Crear y editar',
                'admin.roles.view' => 'Roles - Ver',
                'admin.roles.manage' => 'Roles - Crear y editar',
                'settings.view' => 'Configuración - Ver',
                'settings.manage' => 'Configuración - Modificar',
                'codes.view' => 'Codificación - Ver',
                'codes.manage' => 'Codificación - Modificar',
            ],
            'Compatibilidad' => [
                'administrativo' => 'Rol administrativo (compatibilidad)',
                'superuser' => 'Acceso total (superusuario)',
                'admin.usuarios' => 'Usuarios (legado)',
                'admin.roles' => 'Roles (legado)',
                'pacientes.manage' => 'Pacientes (gestión legado)',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
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

    /**
     * @param array<int, mixed> $selected
     * @return array<int, string>
     */
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

    /**
     * @return array<int, string>
     */
    public static function normalize(mixed $value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item === '' || in_array($item, $normalized, true)) {
                    continue;
                }
                $normalized[] = $item;
            }
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $normalized = self::normalize($decoded);
            } else {
                $normalized = [trim($value)];
            }
        } else {
            $normalized = [];
        }

        $expanded = [];
        foreach ($normalized as $permission) {
            if (!in_array($permission, $expanded, true)) {
                $expanded[] = $permission;
            }

            if (!isset(self::ALIAS_MAP[$permission])) {
                continue;
            }

            foreach (self::ALIAS_MAP[$permission] as $alias) {
                if (!in_array($alias, $expanded, true)) {
                    $expanded[] = $alias;
                }
            }
        }

        return $expanded;
    }

    /**
     * @return array<int, string>
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

    public static function contains(mixed $value, string $permission): bool
    {
        $normalized = self::normalize($value);

        if (in_array(self::SUPERUSER, $normalized, true)) {
            return true;
        }

        if (in_array($permission, $normalized, true)) {
            return true;
        }

        if (!isset(self::ALIAS_MAP[$permission])) {
            return false;
        }

        foreach (self::ALIAS_MAP[$permission] as $alias) {
            if (in_array($alias, $normalized, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $permissions
     */
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

            if (!isset(self::ALIAS_MAP[$permission])) {
                continue;
            }

            foreach (self::ALIAS_MAP[$permission] as $alias) {
                if (in_array($alias, $normalized, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
