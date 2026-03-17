<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;

class MedforgeNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Request $request): array
    {
        $permissions = LegacyPermissionResolver::resolve($request);

        $canAccessDashboard = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'dashboard.view']
        );
        $canAccessAgenda = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'agenda.view', 'pacientes.view', 'solicitudes.view', 'examenes.view']
        );
        $canAccessPacientes = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'pacientes.view', 'pacientes.manage']
        );
        $canAccessPacientesFlujo = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'pacientes.flujo.view', 'pacientes.view', 'pacientes.manage']
        );
        $canAccessDerivaciones = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'derivaciones.view', 'pacientes.view', 'solicitudes.view']
        );
        $canAccessLeads = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'crm.leads.manage', 'crm.view', 'crm.manage']
        );
        $canAccessSolicitudes = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'solicitudes.view', 'solicitudes.update', 'solicitudes.manage']
        );
        $canAccessSolicitudesTurnero = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'solicitudes.turnero', 'solicitudes.update', 'solicitudes.manage', 'solicitudes.view']
        );
        $canAccessCirugias = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'cirugias.view', 'cirugias.manage']
        );
        $canAccessIpl = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'ipl.view', 'cirugias.view', 'cirugias.manage', 'solicitudes.view', 'solicitudes.manage']
        );
        $canAccessInsumos = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'insumos.view', 'insumos.manage']
        );
        $canAccessFarmacia = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'farmacia.view', 'insumos.view', 'insumos.manage']
        );
        $canAccessExamenes = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'examenes.view', 'examenes.manage']
        );
        $canAccessExamenesRealizados = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'examenes.view', 'examenes.manage']
        );
        $canAccessImagenesDashboard = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'examenes.view', 'examenes.manage']
        );
        $canAccessFinanzas = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'reportes.view', 'reportes.export']
        );
        $canAccessUsers = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'admin.usuarios.view', 'admin.usuarios.manage', 'admin.usuarios']
        );
        $canAccessRoles = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'admin.roles.view', 'admin.roles.manage', 'admin.roles']
        );
        $canAccessSettings = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'settings.manage', 'settings.view']
        );
        $canAccessCRM = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'crm.manage', 'crm.view', 'crm.leads.manage', 'crm.projects.manage', 'crm.tasks.manage', 'crm.tickets.manage']
        );
        $canAccessWhatsAppChat = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'whatsapp.manage', 'whatsapp.chat.view', 'settings.manage']
        );
        $canConfigureWhatsApp = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'whatsapp.manage', 'whatsapp.templates.manage', 'whatsapp.autoresponder.manage', 'settings.manage']
        );
        $canAccessCronManager = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'settings.manage']
        );
        $canAccessDoctors = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'doctores.manage', 'doctores.view']
        );
        $canAccessCodes = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'codes.manage', 'codes.view']
        );
        $canAccessPatientVerification = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'pacientes.verification.manage', 'pacientes.verification.view']
        );
        $canAccessProtocolTemplates = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'protocolos.manage', 'protocolos.templates.view', 'protocolos.templates.manage']
        );
        $canAccessMailbox = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'crm.view', 'crm.manage', 'whatsapp.chat.view']
        );
        $canAccessCirugiasDashboard = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'cirugias.dashboard.view']
        );
        $canAccessSolicitudesDashboard = LegacyPermissionCatalog::containsAny(
            $permissions,
            ['administrativo', 'solicitudes.dashboard.view']
        );
        $canAccessQuirurgicoDashboard = $canAccessCirugiasDashboard || $canAccessSolicitudesDashboard;

        $link = static function (
            string $label,
            string $href,
            string $icon,
            array  $active = []
        ): array {
            return [
                'type' => 'item',
                'label' => $label,
                'href' => $href,
                'icon' => $icon,
                'active' => $active,
            ];
        };

        $group = static function (string $label, string $icon, array $children): array {
            return [
                'type' => 'group',
                'label' => $label,
                'icon' => $icon,
                'children' => array_values(array_filter($children)),
            ];
        };

        $label = static fn(string $text): array => [
            'type' => 'label',
            'label' => $text,
        ];

        $commercial = $group('Comercial', 'mdi mdi-briefcase-outline', array_filter([
            $canAccessCRM
                ? $link('CRM', '/crm', 'mdi mdi-ticket-account', [
                'prefix' => ['/crm'],
            ])
                : null,
            $canAccessPacientesFlujo
                ? $link('Flujo de Pacientes', '/v2/pacientes/flujo', 'mdi mdi-transit-connection-variant', [
                'prefix' => ['/v2/pacientes/flujo'],
            ])
                : null,
            $canAccessLeads
                ? $link('Campanas y Leads', '/leads', 'mdi mdi-bullhorn-outline', [
                'prefix' => ['/leads'],
            ])
                : null,
            $canConfigureWhatsApp
                ? $link('Bot de WhatsApp', '/whatsapp/autoresponder', 'mdi mdi-robot-outline', [
                'prefix' => ['/whatsapp/autoresponder'],
            ])
                : null,
            $canConfigureWhatsApp
                ? $link('Plantillas de WhatsApp', '/whatsapp/templates', 'mdi mdi-message-badge-outline', [
                'prefix' => ['/whatsapp/templates'],
            ])
                : null,
        ]));

        $dailyOperations = $group('Operacion diaria', 'mdi mdi-stethoscope', array_filter([
            $canAccessAgenda
                ? $link('Agenda', '/v2/agenda', 'mdi mdi-calendar-clock-outline', [
                'prefix' => ['/v2/agenda'],
            ])
                : null,
            $canAccessPacientes
                ? $link('Lista de Pacientes', '/v2/pacientes', 'mdi mdi-account-multiple-outline', [
                'exact' => ['/v2/pacientes'],
                'prefix' => ['/v2/pacientes/detalles'],
            ])
                : null,
            $canAccessDerivaciones
                ? $link('Derivaciones', '/v2/derivaciones', 'mdi mdi-file-find-outline', [
                'prefix' => ['/v2/derivaciones'],
            ])
                : null,
            $canAccessAgenda
                ? $link('Agendamiento', '/turnoAgenda/agenda-doctor/index', 'mdi mdi-calendar-check-outline', [
                'prefix' => ['/turnoAgenda'],
            ])
                : null,
            $canAccessPatientVerification
                ? $link('Certificacion biometrica', '/pacientes/certificaciones', 'mdi mdi-qrcode-scan', [
                'prefix' => ['/pacientes/certificaciones'],
            ])
                : null,
            $canAccessWhatsAppChat
                ? $link('Chat de WhatsApp', '/whatsapp/chat', 'mdi mdi-message-text-outline', [
                'prefix' => ['/whatsapp/chat'],
            ])
                : null,
            $canAccessWhatsAppChat
                ? $link('Dashboard WhatsApp', '/whatsapp/dashboard', 'mdi mdi-chart-line', [
                'prefix' => ['/whatsapp/dashboard'],
            ])
                : null,
            $canAccessMailbox
                ? $link('Mailbox', '/mailbox', 'mdi mdi-email-open-outline', [
                'prefix' => ['/mailbox', '/mail'],
            ])
                : null,
        ]));

        $clinical = $group('Clinica', 'mdi mdi-hospital-box-outline', array_filter([
            $label('Quirúrgicos'),
            $canAccessSolicitudes
                ? $link('Solicitudes', '/v2/solicitudes', 'mdi mdi-file-document-outline', [
                'prefix' => ['/v2/solicitudes'],
                'exclude_prefix' => ['/v2/solicitudes/dashboard', '/v2/solicitudes/turnero'],
            ])
                : null,
            $canAccessSolicitudesTurnero
                ? $link('Turnero solicitudes', '/v2/solicitudes/turnero', 'mdi mdi-bell-ring-outline', [
                'prefix' => ['/v2/solicitudes/turnero'],
            ])
                : null,
            $canAccessCirugias
                ? $link('Protocolos realizados', '/v2/cirugias', 'mdi mdi-clipboard-check-outline', [
                'prefix' => ['/v2/cirugias'],
                'exclude_prefix' => ['/v2/cirugias/dashboard'],
            ])
                : null,
            $canAccessQuirurgicoDashboard
                ? $link('Dashboard quirurgico', '/v2/cirugias/dashboard', 'mdi mdi-chart-arc', [
                'prefix' => ['/v2/cirugias/dashboard', '/v2/solicitudes/dashboard'],
            ])
                : null,
            $canAccessIpl
                ? $link('Planificador de IPL', '/ipl', 'mdi mdi-calendar-multiselect-outline', [
                'prefix' => ['/ipl', '/views/ipl'],
            ])
                : null,
            $canAccessProtocolTemplates
                ? $link('Plantillas de protocolos', '/protocolos', 'mdi mdi-note-multiple-outline', [
                'prefix' => ['/protocolos'],
            ])
                : null,
            $label('Imágenes'),
            $canAccessExamenes
                ? $link('Examenes', '/v2/examenes', 'mdi mdi-image-filter-center-focus', [
                'prefix' => ['/v2/examenes'],
            ])
                : null,
            $canAccessExamenesRealizados
                ? $link('Examenes realizados', '/v2/imagenes/examenes-realizados', 'mdi mdi-file-image-outline', [
                'prefix' => ['/v2/imagenes/examenes-realizados'],
            ])
                : null,
            $canAccessImagenesDashboard
                ? $link('Dashboard imagenes', '/v2/imagenes/dashboard', 'mdi mdi-monitor-dashboard', [
                'prefix' => ['/v2/imagenes/dashboard'],
            ])
                : null,
        ]));

        $inventory = $group('Inventario', 'mdi mdi-package-variant-closed', array_filter([
            $canAccessInsumos
                ? $link('Lista de insumos', '/insumos', 'mdi mdi-format-list-bulleted', [
                'exact' => ['/insumos'],
            ])
                : null,
            $canAccessInsumos
                ? $link('Lista de medicamentos', '/insumos/medicamentos', 'mdi mdi-pill', [
                'prefix' => ['/insumos/medicamentos'],
            ])
                : null,
            $canAccessInsumos
                ? $link('Catalogo de lentes', '/insumos/lentes', 'mdi mdi-glasses', [
                'prefix' => ['/insumos/lentes'],
            ])
                : null,
            $canAccessFarmacia
                ? $link('Dashboard farmacia', '/farmacia', 'mdi mdi-medical-bag', [
                'prefix' => ['/farmacia'],
            ])
                : null,
        ]));

        $finance = $group('Finanzas', 'mdi mdi-cash-multiple', array_filter([
            $label('Facturacion por afiliacion'),
            $canAccessFinanzas
                ? $link('ISSPOL', '/v2/informes/isspol', 'mdi mdi-shield-outline', [
                'prefix' => ['/v2/informes/isspol'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('ISSFA', '/v2/informes/issfa', 'mdi mdi-star-outline', [
                'prefix' => ['/v2/informes/issfa'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('IESS', '/v2/informes/iess', 'mdi mdi-card-account-details-outline', [
                'prefix' => ['/v2/informes/iess'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('MSP', '/v2/informes/msp', 'mdi mdi-hospital-building', [
                'prefix' => ['/v2/informes/msp'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('No facturado', '/v2/billing/no-facturados', 'mdi mdi-alert-circle-outline', [
                'prefix' => ['/v2/billing/no-facturados'],
            ])
                : null,
            $label('Reportes y estadisticas'),
            $canAccessFinanzas
                ? $link('Particulares', '/v2/informes/particulares', 'mdi mdi-account-outline', [
                'prefix' => ['/v2/informes/particulares'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('Dashboard billing', '/v2/billing/dashboard', 'mdi mdi-chart-box-outline', [
                'prefix' => ['/v2/billing/dashboard'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('Honorarios', '/v2/billing/honorarios', 'mdi mdi-account-cash-outline', [
                'prefix' => ['/v2/billing/honorarios'],
            ])
                : null,
            $canAccessFinanzas
                ? $link('Flujo de pacientes', '/views/reportes/estadistica_flujo.php', 'mdi mdi-chart-timeline-variant', [
                'prefix' => ['/views/reportes/estadistica_flujo.php'],
            ])
                : null,
        ]));

        $administration = $group('Administracion', 'mdi mdi-shield-crown-outline', array_filter([
            $canAccessDoctors
                ? $link('Doctores', '/doctores', 'mdi mdi-stethoscope', [
                'prefix' => ['/doctores'],
            ])
                : null,
            $canAccessUsers
                ? $link('Usuarios', '/v2/usuarios', 'mdi mdi-account-key-outline', [
                'prefix' => ['/v2/usuarios'],
            ])
                : null,
            $canAccessRoles
                ? $link('Roles', '/v2/roles', 'mdi mdi-security', [
                'prefix' => ['/v2/roles'],
            ])
                : null,
            $canAccessSettings
                ? $link('Ajustes', '/settings', 'mdi mdi-cog-outline', [
                'prefix' => ['/settings'],
            ])
                : null,
            $canAccessSettings
                ? $link('Plantillas de correo', '/mail-templates/cobertura', 'mdi mdi-email-outline', [
                'prefix' => ['/mail-templates'],
            ])
                : null,
            $canAccessCronManager
                ? $link('Cron Manager', '/cron-manager', 'mdi mdi-timer-cog-outline', [
                'prefix' => ['/cron-manager'],
            ])
                : null,
            $canAccessCodes
                ? $link('Catalogo de codigos', '/v2/codes', 'mdi mdi-tag-multiple-outline', [
                'prefix' => ['/v2/codes'],
                'exclude_prefix' => ['/v2/codes/packages'],
            ])
                : null,
            $canAccessCodes
                ? $link('Constructor de paquetes', '/v2/codes/packages', 'mdi mdi-package-variant', [
                'prefix' => ['/v2/codes/packages'],
            ])
                : null,
        ]));

        $headerQuickLinks = array_values(array_filter([
            $canAccessPacientes ? [
                'label' => 'Pacientes',
                'href' => '/v2/pacientes',
                'icon' => 'mdi mdi-account-multiple-outline',
            ] : null,
            $canAccessAgenda ? [
                'label' => 'Agenda',
                'href' => '/v2/agenda',
                'icon' => 'mdi mdi-calendar-clock-outline',
            ] : null,
            $canAccessSolicitudes ? [
                'label' => 'Solicitudes',
                'href' => '/v2/solicitudes',
                'icon' => 'mdi mdi-file-document-outline',
            ] : null,
            $canAccessExamenes ? [
                'label' => 'Examenes',
                'href' => '/v2/examenes',
                'icon' => 'mdi mdi-image-filter-center-focus',
            ] : null,
            $canAccessCRM ? [
                'label' => 'CRM',
                'href' => '/crm',
                'icon' => 'mdi mdi-ticket-account',
            ] : null,
            $canAccessSettings ? [
                'label' => 'Ajustes',
                'href' => '/settings',
                'icon' => 'mdi mdi-cog-outline',
            ] : null,
        ]));

        $userMenuLinks = array_values(array_filter([
            $canAccessSettings ? [
                'label' => 'Ajustes',
                'href' => '/settings',
                'icon' => 'ti-settings',
            ] : null,
            $canAccessUsers ? [
                'label' => 'Usuarios',
                'href' => '/v2/usuarios',
                'icon' => 'ti-user',
            ] : null,
        ]));

        $sidebar = array_values(array_filter([
            $canAccessDashboard
                ? $link('Inicio', '/v2/dashboard', 'mdi mdi-view-dashboard-outline', [
                'prefix' => ['/v2/dashboard'],
            ])
                : null,
            $commercial['children'] !== [] ? $commercial : null,
            $dailyOperations['children'] !== [] ? $dailyOperations : null,
            $clinical['children'] !== [] ? $clinical : null,
            $inventory['children'] !== [] ? $inventory : null,
            $finance['children'] !== [] ? $finance : null,
            $administration['children'] !== [] ? $administration : null,
            $link('Cerrar sesion', '/v2/auth/logout', 'mdi mdi-logout'),
        ]));

        return [
            'header_quick_links' => $headerQuickLinks,
            'user_menu_links' => $userMenuLinks,
            'sidebar' => $sidebar,
        ];
    }
}
