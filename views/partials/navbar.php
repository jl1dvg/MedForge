<?php
use Core\Permissions;

// Helpers para resaltar elementos activos y abrir treeviews según la URL actual
if (!function_exists('currentPath')) {
    function currentPath(): string
    {
        return rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    }
}

if (!function_exists('isActive')) {
    function isActive(string $path): string
    {
        $current = currentPath();
        return $current === rtrim($path, '/') ? ' is-active' : '';
    }
}

if (!function_exists('isActivePrefix')) {
    // Activo si la ruta actual comienza con el prefijo dado
    function isActivePrefix(string $prefix): string
    {
        $current = currentPath() . '/';
        $pref = rtrim($prefix, '/') . '/';
        return str_starts_with($current, $pref) ? ' is-active' : '';
    }
}

if (!function_exists('isTreeOpen')) {
    /**
     * Retorna ' menu-open' si la ruta actual empieza con alguno de los prefijos.
     * @param array $prefixes Prefijos de rutas, p.ej. ['/pacientes', '/solicitudes']
     */
    function isTreeOpen(array $prefixes): string
    {
        $current = currentPath() . '/';
        foreach ($prefixes as $p) {
            $pref = rtrim($p, '/') . '/';
            if (str_starts_with($current, $pref)) {
                return ' menu-open';
            }
        }
        return '';
    }
}
?>
<aside class="main-sidebar">
    <!-- sidebar-->
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">

                <!-- sidebar menu-->
                <ul class="sidebar-menu" data-widget="tree">
                    <?php
                    $rawPermissions = $_SESSION['permisos'] ?? [];
                    $normalizedPermissions = Permissions::normalize($rawPermissions);
                    $canAccessUsers = Permissions::containsAny($normalizedPermissions, ['administrativo', 'admin.usuarios.manage', 'admin.usuarios.view', 'admin.usuarios']);
                    $canAccessRoles = Permissions::containsAny($normalizedPermissions, ['administrativo', 'admin.roles.manage', 'admin.roles.view', 'admin.roles']);
                    $canAccessSettings = Permissions::containsAny($normalizedPermissions, ['administrativo', 'settings.manage', 'settings.view']);
                    $canAccessCRM = Permissions::containsAny($normalizedPermissions, ['administrativo', 'crm.manage', 'crm.view', 'crm.leads.manage', 'crm.projects.manage', 'crm.tasks.manage', 'crm.tickets.manage']);
                    $canAccessWhatsAppChat = Permissions::containsAny($normalizedPermissions, ['administrativo', 'whatsapp.manage', 'whatsapp.chat.view', 'settings.manage']);
                    $canConfigureWhatsApp = Permissions::containsAny($normalizedPermissions, ['administrativo', 'whatsapp.manage', 'whatsapp.templates.manage', 'whatsapp.autoresponder.manage', 'settings.manage']);
                    $canAccessCronManager = Permissions::containsAny($normalizedPermissions, ['administrativo', 'settings.manage']);
                    $canAccessDoctors = Permissions::containsAny($normalizedPermissions, ['administrativo', 'doctores.manage', 'doctores.view']);
                    $canAccessCodes = Permissions::containsAny($normalizedPermissions, ['administrativo', 'codes.manage', 'codes.view']);
                    $canAccessPatientVerification = Permissions::containsAny($normalizedPermissions, ['administrativo', 'pacientes.verification.manage', 'pacientes.verification.view']);
                    $canAccessProtocolTemplates = Permissions::containsAny($normalizedPermissions, ['administrativo', 'protocolos.manage', 'protocolos.templates.view', 'protocolos.templates.manage']);
                    $canAccessMailbox = Permissions::containsAny($normalizedPermissions, ['administrativo', 'crm.view', 'crm.manage', 'whatsapp.chat.view']);
                    ?>
                    <li class="<?= isActive('/dashboard') ?>">
                        <a href="/dashboard">
                            <i class="mdi mdi-view-dashboard"><span class="path1"></span><span class="path2"></span></i>
                            <span>Inicio</span>
                        </a>
                    </li>
                    <li class="treeview<?= isTreeOpen(['/crm', '/views/pacientes/flujo', '/leads', '/whatsapp/autoresponder', '/whatsapp/templates']) ?>">
                        <a href="#">
                            <i class="mdi mdi-sale"><span class="path1"></span><span class="path2"></span></i>
                            <span>Marketing y captación</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <?php if ($canAccessCRM): ?>
                                <li class="<?= isActive('/crm') ?>">
                                    <a href="/crm">
                                        <i class="mdi mdi-ticket-account"></i>CRM
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="<?= isActivePrefix('/pacientes/flujo') ?: isActive('/views/pacientes/flujo/flujo.php') ?>">
                                <a href="/views/pacientes/flujo/flujo.php">
                                    <i class="mdi mdi-timetable"></i>Flujo de Pacientes
                                </a>
                            </li>
                            <li class="<?= isActive('/leads') ?>">
                                <a href="/leads">
                                    <i class="mdi mdi-bullhorn"></i>Campañas y Leads
                                </a>
                            </li>
                            <?php if ($canConfigureWhatsApp): ?>
                                <li class="<?= isActive('/whatsapp/autoresponder') ?>">
                                    <a href="/whatsapp/autoresponder">
                                        <i class="mdi mdi-robot"></i>Automatizaciones de WhatsApp
                                    </a>
                                </li>
                                <li class="<?= isActive('/whatsapp/templates') ?>">
                                    <a href="/whatsapp/templates">
                                        <i class="mdi mdi-whatsapp"></i>Plantillas de WhatsApp
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="<?= isActive('/agenda') ?>">
                        <a href="/agenda">
                            <i class="mdi mdi-calendar-clock"><span class="path1"></span><span class="path2"></span></i>
                            <span>Agenda</span>
                        </a>
                    </li>

                    <?php if ($canAccessDoctors): ?>
                        <li class="<?= isActivePrefix('/doctores') ?>">
                            <a href="/doctores">
                                <i class="mdi mdi-stethoscope"></i>
                                <span>Doctores</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="treeview<?= isTreeOpen(['/pacientes', '/whatsapp/chat', '/pacientes/certificaciones', '/turnoAgenda']) ?>">
                        <a href="#">
                            <i class="icon-Compiling"><span class="path1"></span><span class="path2"></span></i>
                            <span>Atención al paciente</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/pacientes') ?>">
                                <a href="/pacientes">
                                    <i class="mdi mdi-account-multiple-outline"></i>Lista de Pacientes
                                </a>
                            </li>
                            <li class="<?= isActivePrefix('/turnoAgenda') ?>">
                                <a href="/turnoAgenda/agenda-doctor/index">
                                    <i class="mdi mdi-calendar"></i>Agendamiento
                                </a>
                            </li>
                            <?php if ($canAccessPatientVerification): ?>
                                <li class="<?= isActive('/pacientes/certificaciones') ?: isActivePrefix('/pacientes/certificaciones') ?>">
                                    <a href="/pacientes/certificaciones">
                                        <i class="mdi mdi-qrcode-scan"></i>Certificación biométrica
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canAccessWhatsAppChat): ?>
                                <li class="<?= isActive('/whatsapp/chat') ?>">
                                    <a href="/whatsapp/chat">
                                        <i class="mdi mdi-message-text-outline"></i>Chat de WhatsApp
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canAccessMailbox): ?>
                                <li class="<?= isActive('/mailbox') ?: isActive('/mail') ?>">
                                    <a href="/mailbox">
                                        <i class="mdi mdi-email-open-outline"></i>Mailbox
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/cirugias', '/solicitudes', '/views/ipl', '/ipl', '/protocolos']) ?>">
                        <a href="#">
                            <i class="icon-Diagnostics"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Operaciones clínicas</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/solicitudes') ?: isActive('/views/solicitudes/examenes.php') ?>">
                                <a href="/solicitudes">
                                    <i class="mdi mdi-file-document"></i>Solicitudes (Kanban)
                                </a>
                            </li>
                            <li class="<?= isActive('/examenes') ?>">
                                <a href="/examenes">
                                    <i class="mdi mdi-eyedropper"></i>Exámenes (Kanban)
                                </a>
                            </li>
                            <li class="<?= isActive('/cirugias') ?>">
                                <a href="/cirugias">
                                    <i class="mdi mdi-clipboard-check"></i>Protocolos Realizados
                                </a>
                            </li>
                            <li class="<?= isActive('/ipl') ?: isActive('/views/ipl/ipl_planificador_lista.php') ?>">
                                <a href="/ipl">
                                    <i class="mdi mdi-calendar-clock"></i>Planificador de IPL
                                </a>
                            </li>
                            <?php if ($canAccessProtocolTemplates): ?>
                                <li class="<?= isActive('/protocolos') ?>">
                                    <a href="/protocolos">
                                        <i class="mdi mdi-note-multiple"></i>Plantillas de Protocolos
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/insumos', '/insumos/medicamentos']) ?>">
                        <a href="#">
                            <i class="mdi mdi-medical-bag"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Inventario y logística</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/insumos') ?>">
                                <a href="/insumos">
                                    <i class="mdi mdi-format-list-bulleted"></i>Lista de Insumos
                                </a>
                            </li>
                            <li class="<?= isActive('/insumos/medicamentos') ?>">
                                <a href="/insumos/medicamentos">
                                    <i class="mdi mdi-pill"></i>Lista de Medicamentos
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/informes', '/billing', '/views/reportes']) ?>">
                        <a href="#">
                            <i class="mdi mdi-chart-areaspline"><span class="path1"></span><span
                                        class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Finanzas y análisis</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="header">Facturación por afiliación</li>
                            <li class="<?= isActive('/informes/isspol') ?>">
                                <a href="/informes/isspol">
                                    <i class="mdi mdi-shield"></i>ISSPOL
                                </a>
                            </li>
                            <li class="<?= isActive('/informes/issfa') ?>">
                                <a href="/informes/issfa">
                                    <i class="mdi mdi-star"></i>ISSFA
                                </a>
                            </li>
                            <li class="<?= isActive('/informes/iess') ?>">
                                <a href="/informes/iess">
                                    <i class="mdi mdi-account"></i>IESS
                                </a>
                            </li>
                            <li class="<?= isActive('/informes/particulares') ?>">
                                <a href="/informes/particulares">
                                    <i class="mdi mdi-account-outline"></i>Particulares
                                </a>
                            </li>
                            <li class="<?= isActive('/billing/no-facturados') ?>">
                                <a href="/billing/no-facturados">
                                    <i class="mdi mdi-account-outline"></i>No Facturado
                                </a>
                            </li>
                            <li class="header">Reportes y estadísticas</li>
                            <li class="<?= isActive('/views/reportes/estadistica_flujo.php') ?>">
                                <a href="/views/reportes/estadistica_flujo.php">
                                    <i class="mdi mdi-chart-line"></i>Flujo de Pacientes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <?php
                    $showAdmin = $canAccessUsers || $canAccessRoles || $canAccessSettings || $canAccessCodes || $canAccessCronManager;
                    ?>
                    <?php if ($showAdmin): ?>
                        <li class="treeview<?= isTreeOpen(['/usuarios', '/roles', '/codes', '/codes/packages']) ?>">
                            <a href="#">
                                <i class="mdi mdi-settings"><span class="path1"></span><span class="path2"></span><span
                                            class="path3"></span></i>
                                <span>Administración y TI</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                <?php if ($canAccessUsers): ?>
                                    <li class="<?= isActive('/usuarios') ?>">
                                        <a href="/usuarios">
                                            <i class="mdi mdi-account-key"></i>Usuarios
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canAccessRoles): ?>
                                    <li class="<?= isActive('/roles') ?>">
                                        <a href="/roles">
                                            <i class="mdi mdi-security"></i>Roles
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canAccessSettings): ?>
                                    <li class="<?= isActive('/settings') ?>">
                                        <a href="/settings">
                                            <i class="mdi mdi-settings"></i>Ajustes
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canAccessCronManager): ?>
                                    <li class="<?= isActive('/cron-manager') ?>">
                                        <a href="/cron-manager">
                                            <i class="mdi mdi-react"></i>Cron Manager
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($canAccessCodes): ?>
                                    <li class="<?= isActive('/codes') ?>">
                                        <a href="/codes">
                                            <i class="mdi mdi-tag-text-outline"></i>Catálogo de códigos
                                        </a>
                                    </li>
                                    <li class="<?= isActive('/codes/packages') ?>">
                                        <a href="/codes/packages">
                                            <i class="mdi mdi-package-variant-closed"></i>Constructor de paquetes
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>
</aside>
