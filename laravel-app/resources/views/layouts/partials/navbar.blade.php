@php
    $path = rtrim((string) request()->getPathInfo(), '/');
    if ($path === '') {
        $path = '/';
    }

    $isActive = static function (string $needle) use ($path): string {
        return $path === rtrim($needle, '/') ? ' is-active' : '';
    };

    $isPrefix = static function (string $prefix) use ($path): string {
        $prefix = rtrim($prefix, '/');
        if ($prefix === '') {
            return '';
        }

        return str_starts_with($path . '/', $prefix . '/') ? ' is-active' : '';
    };

    $isTreeOpen = static function (array $prefixes) use ($path): string {
        $current = $path . '/';
        foreach ($prefixes as $prefix) {
            $pref = rtrim((string) $prefix, '/') . '/';
            if (str_starts_with($current, $pref)) {
                return ' menu-open';
            }
        }

        return '';
    };

    $isV2Shell = str_starts_with($path . '/', '/v2/');
    $resolvedPermissions = \App\Modules\Shared\Support\LegacyPermissionResolver::resolve(request());
    $canAccessDashboard = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'dashboard.view']
    );
    $canAccessAgenda = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'agenda.view', 'pacientes.view', 'solicitudes.view', 'examenes.view']
    );
    $canAccessPacientes = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'pacientes.view', 'pacientes.manage']
    );
    $canAccessPacientesFlujo = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'pacientes.flujo.view', 'pacientes.view', 'pacientes.manage']
    );
    $canAccessDerivaciones = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'derivaciones.view', 'pacientes.view', 'solicitudes.view']
    );
    $canAccessLeads = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'crm.leads.manage', 'crm.view', 'crm.manage']
    );
    $canAccessSolicitudes = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'solicitudes.view', 'solicitudes.update', 'solicitudes.manage']
    );
    $canAccessSolicitudesTurnero = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'solicitudes.turnero', 'solicitudes.update', 'solicitudes.manage', 'solicitudes.view']
    );
    $canAccessCirugias = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'cirugias.view', 'cirugias.manage']
    );
    $canAccessIpl = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'ipl.view', 'cirugias.view', 'cirugias.manage', 'solicitudes.view', 'solicitudes.manage']
    );
    $canAccessInsumos = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'insumos.view', 'insumos.manage']
    );
    $canAccessFarmacia = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'farmacia.view', 'insumos.view', 'insumos.manage']
    );
    $canAccessExamenes = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'examenes.view', 'examenes.manage']
    );
    $canAccessExamenesRealizados = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'examenes.view', 'examenes.manage']
    );
    $canAccessImagenesDashboard = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'examenes.view', 'examenes.manage']
    );
    $canAccessFinanzas = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'reportes.view', 'reportes.export']
    );
    $canAccessUsers = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'admin.usuarios.view', 'admin.usuarios.manage', 'admin.usuarios']
    );
    $canAccessRoles = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'admin.roles.view', 'admin.roles.manage', 'admin.roles']
    );
    $canAccessSettings = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'settings.manage', 'settings.view']
    );
    $canAccessCRM = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'crm.manage', 'crm.view', 'crm.leads.manage', 'crm.projects.manage', 'crm.tasks.manage', 'crm.tickets.manage']
    );
    $canAccessWhatsAppChat = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'whatsapp.manage', 'whatsapp.chat.view', 'settings.manage']
    );
    $canConfigureWhatsApp = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'whatsapp.manage', 'whatsapp.templates.manage', 'whatsapp.autoresponder.manage', 'settings.manage']
    );
    $canAccessCronManager = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'settings.manage']
    );
    $canAccessDoctors = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'doctores.manage', 'doctores.view']
    );
    $canAccessCodes = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'codes.manage', 'codes.view']
    );
    $canAccessPatientVerification = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'pacientes.verification.manage', 'pacientes.verification.view']
    );
    $canAccessProtocolTemplates = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'protocolos.manage', 'protocolos.templates.view', 'protocolos.templates.manage']
    );
    $canAccessMailbox = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'crm.view', 'crm.manage', 'whatsapp.chat.view']
    );
    $canAccessCirugiasDashboard = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'cirugias.dashboard.view']
    );
    $canAccessSolicitudesDashboard = \App\Modules\Shared\Support\LegacyPermissionCatalog::containsAny(
        $resolvedPermissions,
        ['administrativo', 'solicitudes.dashboard.view']
    );
    $canAccessQuirurgicoDashboard = $canAccessCirugiasDashboard || $canAccessSolicitudesDashboard;
    $showMarketingTree = $canAccessCRM || $canAccessPacientesFlujo || $canAccessLeads || $canConfigureWhatsApp;
    $showAtencionTree = $canAccessPacientes || $canAccessDerivaciones || $canAccessAgenda || $canAccessPatientVerification || $canAccessWhatsAppChat || $canAccessMailbox;
    $showCoordinacionTree = $canAccessSolicitudes || $canAccessSolicitudesTurnero || $canAccessCirugias || $canAccessQuirurgicoDashboard || $canAccessIpl || $canAccessProtocolTemplates;
    $showInsumosTree = $canAccessInsumos || $canAccessFarmacia;
    $showImagenesTree = $canAccessExamenes || $canAccessExamenesRealizados || $canAccessImagenesDashboard;
    $showFinanzasTree = $canAccessFinanzas;
    $showAdminTree = $canAccessUsers || $canAccessRoles || $canAccessSettings || $canAccessCronManager || $canAccessCodes;
    $dashboardLink = $isV2Shell ? '/v2/dashboard' : '/dashboard';
    $agendaLink = $isV2Shell ? '/v2/agenda' : '/agenda';
    $solicitudesLink = $isV2Shell ? '/v2/solicitudes' : '/solicitudes';
    $solicitudesTurneroLink = $isV2Shell ? '/v2/solicitudes/turnero' : '/solicitudes/turnero';
    $cirugiasLink = $isV2Shell ? '/v2/cirugias' : '/cirugias';
    $cirugiasDashboardLink = $isV2Shell ? '/v2/cirugias/dashboard' : '/cirugias/dashboard';
    $examenesLink = $isV2Shell ? '/v2/examenes' : '/examenes';
    $pacientesLink = $isV2Shell ? '/v2/pacientes' : '/pacientes';
    $pacientesFlujoLink = $isV2Shell ? '/v2/pacientes/flujo' : '/pacientes/flujo';
    $derivacionesLink = $isV2Shell ? '/v2/derivaciones' : '/derivaciones';
    $billingNoFacturadosLink = $isV2Shell ? '/v2/billing/no-facturados' : '/billing/no-facturados';
    $billingDashboardLink = $isV2Shell ? '/v2/billing/dashboard' : '/billing/dashboard';
    $billingHonorariosLink = $isV2Shell ? '/v2/billing/honorarios' : '/billing/honorarios';
    $informesIessLink = $isV2Shell ? '/v2/informes/iess' : '/informes/iess';
    $informesIsspolLink = $isV2Shell ? '/v2/informes/isspol' : '/informes/isspol';
    $informesIssfaLink = $isV2Shell ? '/v2/informes/issfa' : '/informes/issfa';
    $informesMspLink = $isV2Shell ? '/v2/informes/msp' : '/informes/msp';
    $informesParticularesLink = $isV2Shell ? '/v2/informes/particulares' : '/informes/particulares';
    $usersLink = '/usuarios';
    $rolesLink = '/roles';
    $codesLink = $isV2Shell ? '/v2/codes' : '/codes';
    $codesPackagesLink = $isV2Shell ? '/v2/codes/packages' : '/codes/packages';
@endphp
<aside class="main-sidebar">
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">
                <ul class="sidebar-menu" data-widget="tree">
                    @if($canAccessDashboard)
                        <li class="{{ $isActive('/dashboard') }}{{ $isPrefix('/v2/dashboard') }}">
                            <a href="{{ $dashboardLink }}">
                                <i class="mdi mdi-view-dashboard"><span class="path1"></span><span class="path2"></span></i>
                                <span>Inicio</span>
                            </a>
                        </li>
                    @endif

                    @if($showMarketingTree)
                        <li class="treeview{{ $isTreeOpen(['/crm', '/pacientes/flujo', '/v2/pacientes/flujo', '/leads', '/whatsapp/autoresponder', '/whatsapp/templates']) }}">
                            <a href="#">
                                <i class="mdi mdi-sale"><span class="path1"></span><span class="path2"></span></i>
                                <span>Marketing y captación</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessCRM)
                                    <li class="{{ $isActive('/crm') }}"><a href="/crm"><i class="mdi mdi-ticket-account"></i>CRM</a></li>
                                @endif
                                @if($canAccessPacientesFlujo)
                                    <li class="{{ $isPrefix('/pacientes/flujo') }}{{ $isPrefix('/v2/pacientes/flujo') }}"><a href="{{ $pacientesFlujoLink }}"><i class="mdi mdi-timetable"></i>Flujo de Pacientes</a></li>
                                @endif
                                @if($canAccessLeads)
                                    <li class="{{ $isActive('/leads') }}"><a href="/leads"><i class="mdi mdi-bullhorn"></i>Campañas y Leads</a></li>
                                @endif
                                @if($canConfigureWhatsApp)
                                    <li class="{{ $isActive('/whatsapp/autoresponder') }}"><a href="/whatsapp/autoresponder"><i class="mdi mdi-robot"></i>Automatizaciones de WhatsApp</a></li>
                                    <li class="{{ $isActive('/whatsapp/templates') }}"><a href="/whatsapp/templates"><i class="mdi mdi-whatsapp"></i>Plantillas de WhatsApp</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($canAccessAgenda)
                        <li class="{{ $isActive('/agenda') }}{{ $isActive('/v2/agenda') }}">
                            <a href="{{ $agendaLink }}">
                                <i class="mdi mdi-calendar-clock"><span class="path1"></span><span class="path2"></span></i>
                                <span>Agenda</span>
                            </a>
                        </li>
                    @endif

                    @if($canAccessDoctors)
                        <li class="{{ $isPrefix('/doctores') }}">
                            <a href="/doctores">
                                <i class="mdi mdi-stethoscope"></i>
                                <span>Doctores</span>
                            </a>
                        </li>
                    @endif

                    @if($showAtencionTree)
                        <li class="treeview{{ $isTreeOpen(['/pacientes', '/v2/pacientes', '/whatsapp/chat', '/whatsapp/dashboard', '/pacientes/certificaciones', '/turnoAgenda', '/derivaciones', '/v2/derivaciones', '/mailbox']) }}">
                            <a href="#">
                                <i class="icon-Compiling"><span class="path1"></span><span class="path2"></span></i>
                                <span>Atención al paciente</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessPacientes)
                                    <li class="{{ $isPrefix('/pacientes') }}{{ $isPrefix('/v2/pacientes') }}"><a href="{{ $pacientesLink }}"><i class="mdi mdi-account-multiple-outline"></i>Lista de Pacientes</a></li>
                                @endif
                                @if($canAccessDerivaciones)
                                    <li class="{{ $isActive('/derivaciones') }}{{ $isActive('/v2/derivaciones') }}"><a href="{{ $derivacionesLink }}"><i class="mdi mdi-file-find"></i>Derivaciones</a></li>
                                @endif
                                @if($canAccessAgenda)
                                    <li class="{{ $isPrefix('/turnoAgenda') }}"><a href="/turnoAgenda/agenda-doctor/index"><i class="mdi mdi-calendar"></i>Agendamiento</a></li>
                                @endif
                                @if($canAccessPatientVerification)
                                    <li class="{{ $isPrefix('/pacientes/certificaciones') }}"><a href="/pacientes/certificaciones"><i class="mdi mdi-qrcode-scan"></i>Certificación biométrica</a></li>
                                @endif
                                @if($canAccessWhatsAppChat)
                                    <li class="{{ $isActive('/whatsapp/chat') }}"><a href="/whatsapp/chat"><i class="mdi mdi-message-text-outline"></i>Chat de WhatsApp</a></li>
                                    <li class="{{ $isActive('/whatsapp/dashboard') }}"><a href="/whatsapp/dashboard"><i class="mdi mdi-chart-line"></i>Dashboard WhatsApp</a></li>
                                @endif
                                @if($canAccessMailbox)
                                    <li class="{{ $isActive('/mailbox') }}{{ $isActive('/mail') }}"><a href="/mailbox"><i class="mdi mdi-email-open-outline"></i>Mailbox</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($showCoordinacionTree)
                        <li class="treeview{{ $isTreeOpen(['/cirugias', '/v2/cirugias', '/v2/cirugias/dashboard', '/solicitudes', '/v2/solicitudes', '/ipl', '/protocolos']) }}">
                            <a href="#">
                                <i class="icon-Diagnostics"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <span>Coordinación quirúrgica</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessSolicitudes)
                                    <li class="{{ $isActive('/solicitudes') }}{{ $isActive('/v2/solicitudes') }}"><a href="{{ $solicitudesLink }}"><i class="mdi mdi-file-document"></i>Solicitudes (Kanban)</a></li>
                                @endif
                                @if($canAccessSolicitudesTurnero)
                                    <li class="{{ $isActive('/solicitudes/turnero') }}{{ $isActive('/v2/solicitudes/turnero') }}"><a href="{{ $solicitudesTurneroLink }}"><i class="mdi mdi-bell-ring-outline"></i>Turnero solicitudes</a></li>
                                @endif
                                @if($canAccessCirugias)
                                    <li class="{{ $isActive('/cirugias') }}{{ $isActive('/v2/cirugias') }}"><a href="{{ $cirugiasLink }}"><i class="mdi mdi-clipboard-check"></i>Protocolos Realizados</a></li>
                                @endif
                                @if($canAccessQuirurgicoDashboard)
                                    <li class="{{ $isActive('/cirugias/dashboard') }}{{ $isActive('/v2/cirugias/dashboard') }}{{ $isActive('/solicitudes/dashboard') }}{{ $isActive('/v2/solicitudes/dashboard') }}"><a href="{{ $cirugiasDashboardLink }}"><i class="mdi mdi-chart-arc"></i>Dashboard quirúrgico</a></li>
                                @endif
                                @if($canAccessIpl)
                                    <li class="{{ $isActive('/ipl') }}{{ $isPrefix('/ipl') }}"><a href="/ipl"><i class="mdi mdi-calendar-clock"></i>Planificador de IPL</a></li>
                                @endif
                                @if($canAccessProtocolTemplates)
                                    <li class="{{ $isActive('/protocolos') }}"><a href="/protocolos"><i class="mdi mdi-note-multiple"></i>Plantillas de Protocolos</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($showInsumosTree)
                        <li class="treeview{{ $isTreeOpen(['/insumos', '/insumos/medicamentos', '/insumos/lentes', '/farmacia']) }}">
                            <a href="#">
                                <i class="mdi mdi-medical-bag"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <span>Inventario y logística</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessInsumos)
                                    <li class="{{ $isActive('/insumos') }}"><a href="/insumos"><i class="mdi mdi-format-list-bulleted"></i>Lista de Insumos</a></li>
                                    <li class="{{ $isActive('/insumos/medicamentos') }}"><a href="/insumos/medicamentos"><i class="mdi mdi-pill"></i>Lista de Medicamentos</a></li>
                                    <li class="{{ $isActive('/insumos/lentes') }}"><a href="/insumos/lentes"><i class="mdi mdi-glasses"></i>Catálogo de Lentes</a></li>
                                @endif
                                @if($canAccessFarmacia)
                                    <li class="{{ $isActive('/farmacia') }}"><a href="/farmacia"><i class="mdi mdi-pill"></i>Dashboard farmacia</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($showImagenesTree)
                        <li class="treeview{{ $isTreeOpen(['/examenes', '/v2/examenes', '/imagenes/examenes-realizados', '/imagenes/dashboard', '/v2/imagenes/examenes-realizados', '/v2/imagenes/dashboard']) }}">
                            <a href="#">
                                <i class="mdi mdi-image-multiple"><span class="path1"></span><span class="path2"></span></i>
                                <span>Imágenes</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessExamenes)
                                    <li class="{{ $isActive('/examenes') }}{{ $isActive('/v2/examenes') }}"><a href="{{ $examenesLink }}"><i class="mdi mdi-eyedropper"></i>Exámenes (Kanban)</a></li>
                                @endif
                                @if($canAccessExamenesRealizados)
                                    <li class="{{ $isActive('/imagenes/examenes-realizados') || $isActive('/v2/imagenes/examenes-realizados') }}"><a href="/v2/imagenes/examenes-realizados"><i class="mdi mdi-file-image"></i>Exámenes realizados</a></li>
                                @endif
                                @if($canAccessImagenesDashboard)
                                    <li class="{{ $isActive('/imagenes/dashboard') || $isActive('/v2/imagenes/dashboard') }}"><a href="/v2/imagenes/dashboard"><i class="mdi mdi-chart-line"></i>Dashboard imágenes</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if($showFinanzasTree)
                        <li class="treeview{{ $isTreeOpen(['/informes', '/v2/informes', '/billing', '/v2/billing', '/views/reportes']) }}">
                            <a href="#">
                                <i class="mdi mdi-chart-areaspline"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <span>Finanzas y análisis</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                <li class="header">Facturación por afiliación</li>
                                <li class="{{ $isActive('/informes/isspol') }}{{ $isActive('/v2/informes/isspol') }}"><a href="{{ $informesIsspolLink }}"><i class="mdi mdi-shield"></i>ISSPOL</a></li>
                                <li class="{{ $isActive('/informes/issfa') }}{{ $isActive('/v2/informes/issfa') }}"><a href="{{ $informesIssfaLink }}"><i class="mdi mdi-star"></i>ISSFA</a></li>
                                <li class="{{ $isActive('/informes/iess') }}{{ $isActive('/v2/informes/iess') }}"><a href="{{ $informesIessLink }}"><i class="mdi mdi-account"></i>IESS</a></li>
                                <li class="{{ $isActive('/informes/msp') }}{{ $isActive('/v2/informes/msp') }}"><a href="{{ $informesMspLink }}"><i class="mdi mdi-hospital-building"></i>MSP</a></li>
                                <li class="{{ $isActive('/informes/particulares') }}{{ $isActive('/v2/informes/particulares') }}"><a href="{{ $informesParticularesLink }}"><i class="mdi mdi-account-outline"></i>Particulares</a></li>
                                <li class="{{ $isPrefix('/billing/no-facturados') }}{{ $isPrefix('/v2/billing/no-facturados') }}"><a href="{{ $billingNoFacturadosLink }}"><i class="mdi mdi-account-outline"></i>No Facturado</a></li>
                                <li class="{{ $isActive('/billing/dashboard') }}{{ $isActive('/v2/billing/dashboard') }}"><a href="{{ $billingDashboardLink }}"><i class="mdi mdi-chart-line"></i>Dashboard Billing</a></li>
                                <li class="{{ $isActive('/billing/honorarios') }}{{ $isActive('/v2/billing/honorarios') }}"><a href="{{ $billingHonorariosLink }}"><i class="mdi mdi-account-cash"></i>Honorarios</a></li>
                                <li class="header">Reportes y estadísticas</li>
                                <li class="{{ $isPrefix('/views/reportes/estadistica_flujo.php') }}"><a href="/views/reportes/estadistica_flujo.php"><i class="mdi mdi-chart-line"></i>Flujo de Pacientes</a></li>
                            </ul>
                        </li>
                    @endif

                    @if($showAdminTree)
                        <li class="treeview{{ $isTreeOpen(['/usuarios', '/roles', '/settings', '/cron-manager', '/codes', '/v2/codes', '/codes/packages', '/v2/codes/packages', '/mail-templates']) }}">
                            <a href="#">
                                <i class="mdi mdi-settings"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                <span>Administración y TI</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                @if($canAccessUsers)
                                    <li class="{{ $isPrefix('/usuarios') }}{{ $isPrefix('/v2/usuarios') }}"><a href="{{ $usersLink }}"><i class="mdi mdi-account-key"></i>Usuarios</a></li>
                                @endif
                                @if($canAccessRoles)
                                    <li class="{{ $isPrefix('/roles') }}{{ $isPrefix('/v2/roles') }}"><a href="{{ $rolesLink }}"><i class="mdi mdi-security"></i>Roles</a></li>
                                @endif
                                @if($canAccessSettings)
                                    <li class="{{ $isActive('/settings') }}"><a href="/settings"><i class="mdi mdi-settings"></i>Ajustes</a></li>
                                    <li class="{{ $isPrefix('/mail-templates') }}"><a href="/mail-templates/cobertura"><i class="mdi mdi-email-variant"></i>Plantillas de correo</a></li>
                                @endif
                                @if($canAccessCronManager)
                                    <li class="{{ $isActive('/cron-manager') }}"><a href="/cron-manager"><i class="mdi mdi-react"></i>Cron Manager</a></li>
                                @endif
                                @if($canAccessCodes)
                                    <li class="{{ $isPrefix('/codes') }}{{ $isPrefix('/v2/codes') }}"><a href="{{ $codesLink }}"><i class="mdi mdi-tag-text-outline"></i>Catálogo de códigos</a></li>
                                    <li class="{{ $isPrefix('/codes/packages') }}{{ $isPrefix('/v2/codes/packages') }}"><a href="{{ $codesPackagesLink }}"><i class="mdi mdi-package-variant-closed"></i>Constructor de paquetes</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    <li>
                        <a href="/v2/auth/logout">
                            <i class="mdi mdi-logout"></i>
                            <span>Cerrar sesión</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</aside>
