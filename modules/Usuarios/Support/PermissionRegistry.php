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
                'pacientes.view' => 'Pacientes - Ver',
                'pacientes.create' => 'Pacientes - Crear',
                'pacientes.edit' => 'Pacientes - Editar',
                'pacientes.delete' => 'Pacientes - Eliminar',
                'pacientes.verification.view' => 'Certificación biométrica - Ver',
                'pacientes.verification.manage' => 'Certificación biométrica - Gestionar',
            ],
            'Cirugías' => [
                'cirugias.view' => 'Cirugías - Ver',
                'cirugias.create' => 'Cirugías - Registrar',
                'cirugias.edit' => 'Cirugías - Editar',
                'cirugias.delete' => 'Cirugías - Anular',
            ],
            'Insumos' => [
                'insumos.view' => 'Insumos - Ver',
                'insumos.create' => 'Insumos - Crear',
                'insumos.edit' => 'Insumos - Editar',
                'insumos.delete' => 'Insumos - Eliminar',
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
                'whatsapp.templates.manage' => 'WhatsApp - Gestionar plantillas',
                'whatsapp.autoresponder.manage' => 'WhatsApp - Gestionar automatizaciones',
                'whatsapp.manage' => 'WhatsApp - Acceso total (atajo)',
            ],
            'Inteligencia Artificial' => [
                'ai.consultas.enfermedad' => 'IA - Generar resumen de enfermedad',
                'ai.consultas.plan' => 'IA - Generar plan de tratamiento',
                'ai.manage' => 'IA - Acceso total (atajo)',
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
                'cirugias.manage' => 'Cirugías (gestión legado)',
                'insumos.manage' => 'Insumos (gestión legado)',
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
