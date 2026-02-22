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
@endphp
<aside class="main-sidebar">
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">
                <ul class="sidebar-menu" data-widget="tree">
                    <li class="{{ $isActive('/dashboard') }}{{ $isPrefix('/v2/dashboard') }}">
                        <a href="/dashboard">
                            <i class="mdi mdi-view-dashboard"><span class="path1"></span><span class="path2"></span></i>
                            <span>Inicio</span>
                        </a>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/crm', '/pacientes/flujo', '/leads', '/whatsapp/autoresponder', '/whatsapp/templates']) }}">
                        <a href="#">
                            <i class="mdi mdi-sale"><span class="path1"></span><span class="path2"></span></i>
                            <span>Marketing y captación</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isActive('/crm') }}"><a href="/crm"><i class="mdi mdi-ticket-account"></i>CRM</a></li>
                            <li class="{{ $isPrefix('/pacientes/flujo') }}"><a href="/pacientes/flujo"><i class="mdi mdi-timetable"></i>Flujo de Pacientes</a></li>
                            <li class="{{ $isActive('/leads') }}"><a href="/leads"><i class="mdi mdi-bullhorn"></i>Campañas y Leads</a></li>
                            <li class="{{ $isActive('/whatsapp/autoresponder') }}"><a href="/whatsapp/autoresponder"><i class="mdi mdi-robot"></i>Automatizaciones de WhatsApp</a></li>
                            <li class="{{ $isActive('/whatsapp/templates') }}"><a href="/whatsapp/templates"><i class="mdi mdi-whatsapp"></i>Plantillas de WhatsApp</a></li>
                        </ul>
                    </li>

                    <li class="{{ $isActive('/agenda') }}">
                        <a href="/agenda">
                            <i class="mdi mdi-calendar-clock"><span class="path1"></span><span class="path2"></span></i>
                            <span>Agenda</span>
                        </a>
                    </li>

                    <li class="{{ $isPrefix('/doctores') }}">
                        <a href="/doctores">
                            <i class="mdi mdi-stethoscope"></i>
                            <span>Doctores</span>
                        </a>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/pacientes', '/whatsapp/chat', '/pacientes/certificaciones', '/turnoAgenda', '/derivaciones', '/mailbox']) }}">
                        <a href="#">
                            <i class="icon-Compiling"><span class="path1"></span><span class="path2"></span></i>
                            <span>Atención al paciente</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isActive('/pacientes') }}"><a href="/pacientes"><i class="mdi mdi-account-multiple-outline"></i>Lista de Pacientes</a></li>
                            <li class="{{ $isActive('/derivaciones') }}"><a href="/derivaciones"><i class="mdi mdi-file-find"></i>Derivaciones</a></li>
                            <li class="{{ $isPrefix('/turnoAgenda') }}"><a href="/turnoAgenda/agenda-doctor/index"><i class="mdi mdi-calendar"></i>Agendamiento</a></li>
                            <li class="{{ $isPrefix('/pacientes/certificaciones') }}"><a href="/pacientes/certificaciones"><i class="mdi mdi-qrcode-scan"></i>Certificación biométrica</a></li>
                            <li class="{{ $isActive('/whatsapp/chat') }}"><a href="/whatsapp/chat"><i class="mdi mdi-message-text-outline"></i>Chat de WhatsApp</a></li>
                            <li class="{{ $isActive('/mailbox') }}{{ $isActive('/mail') }}"><a href="/mailbox"><i class="mdi mdi-email-open-outline"></i>Mailbox</a></li>
                        </ul>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/cirugias', '/solicitudes', '/ipl', '/protocolos']) }}">
                        <a href="#">
                            <i class="icon-Diagnostics"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span>Coordinación quirúrgica</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isActive('/solicitudes') }}"><a href="/solicitudes"><i class="mdi mdi-file-document"></i>Solicitudes (Kanban)</a></li>
                            <li class="{{ $isActive('/solicitudes/dashboard') }}"><a href="/solicitudes/dashboard"><i class="mdi mdi-chart-timeline"></i>Dashboard solicitudes</a></li>
                            <li class="{{ $isActive('/cirugias') }}"><a href="/cirugias"><i class="mdi mdi-clipboard-check"></i>Protocolos Realizados</a></li>
                            <li class="{{ $isActive('/cirugias/dashboard') }}"><a href="/cirugias/dashboard"><i class="mdi mdi-chart-arc"></i>Dashboard quirúrgico</a></li>
                            <li class="{{ $isActive('/ipl') }}{{ $isPrefix('/ipl') }}"><a href="/ipl"><i class="mdi mdi-calendar-clock"></i>Planificador de IPL</a></li>
                            <li class="{{ $isActive('/protocolos') }}"><a href="/protocolos"><i class="mdi mdi-note-multiple"></i>Plantillas de Protocolos</a></li>
                        </ul>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/insumos', '/insumos/medicamentos', '/insumos/lentes']) }}">
                        <a href="#">
                            <i class="mdi mdi-medical-bag"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span>Inventario y logística</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isActive('/insumos') }}"><a href="/insumos"><i class="mdi mdi-format-list-bulleted"></i>Lista de Insumos</a></li>
                            <li class="{{ $isActive('/insumos/medicamentos') }}"><a href="/insumos/medicamentos"><i class="mdi mdi-pill"></i>Lista de Medicamentos</a></li>
                            <li class="{{ $isActive('/insumos/lentes') }}"><a href="/insumos/lentes"><i class="mdi mdi-glasses"></i>Catálogo de Lentes</a></li>
                        </ul>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/examenes', '/imagenes/examenes-realizados']) }}">
                        <a href="#">
                            <i class="mdi mdi-image-multiple"><span class="path1"></span><span class="path2"></span></i>
                            <span>Imágenes</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isActive('/examenes') }}"><a href="/examenes"><i class="mdi mdi-eyedropper"></i>Exámenes (Kanban)</a></li>
                            <li class="{{ $isActive('/imagenes/examenes-realizados') }}"><a href="/imagenes/examenes-realizados"><i class="mdi mdi-file-image"></i>Exámenes realizados</a></li>
                        </ul>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/informes', '/billing', '/views/reportes']) }}">
                        <a href="#">
                            <i class="mdi mdi-chart-areaspline"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span>Finanzas y análisis</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="header">Facturación por afiliación</li>
                            <li class="{{ $isActive('/informes/isspol') }}"><a href="/informes/isspol"><i class="mdi mdi-shield"></i>ISSPOL</a></li>
                            <li class="{{ $isActive('/informes/issfa') }}"><a href="/informes/issfa"><i class="mdi mdi-star"></i>ISSFA</a></li>
                            <li class="{{ $isActive('/informes/iess') }}"><a href="/informes/iess"><i class="mdi mdi-account"></i>IESS</a></li>
                            <li class="{{ $isActive('/informes/particulares') }}"><a href="/informes/particulares"><i class="mdi mdi-account-outline"></i>Particulares</a></li>
                            <li class="{{ $isPrefix('/billing/no-facturados') }}"><a href="/billing/no-facturados"><i class="mdi mdi-account-outline"></i>No Facturado</a></li>
                            <li class="{{ $isActive('/billing/dashboard') }}"><a href="/billing/dashboard"><i class="mdi mdi-chart-line"></i>Dashboard Billing</a></li>
                            <li class="header">Reportes y estadísticas</li>
                            <li class="{{ $isPrefix('/views/reportes/estadistica_flujo.php') }}"><a href="/views/reportes/estadistica_flujo.php"><i class="mdi mdi-chart-line"></i>Flujo de Pacientes</a></li>
                        </ul>
                    </li>

                    <li class="treeview{{ $isTreeOpen(['/usuarios', '/roles', '/codes', '/codes/packages', '/mail-templates', '/reglas']) }}">
                        <a href="#">
                            <i class="mdi mdi-settings"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span>Administración y TI</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="{{ $isPrefix('/usuarios') }}"><a href="/usuarios"><i class="mdi mdi-account-key"></i>Usuarios</a></li>
                            <li class="{{ $isPrefix('/roles') }}"><a href="/roles"><i class="mdi mdi-shield-account"></i>Roles y permisos</a></li>
                            <li class="{{ $isPrefix('/codes/packages') }}"><a href="/codes/packages"><i class="mdi mdi-package-variant"></i>Catálogo de paquetes</a></li>
                            <li class="{{ $isPrefix('/codes') }}"><a href="/codes"><i class="mdi mdi-barcode-scan"></i>Códigos médicos</a></li>
                            <li class="{{ $isPrefix('/reglas') }}"><a href="/reglas"><i class="mdi mdi-function"></i>Reglas y automatizaciones</a></li>
                            <li class="{{ $isPrefix('/mail-templates') }}"><a href="/mail-templates"><i class="mdi mdi-email-edit"></i>Plantillas de correo</a></li>
                        </ul>
                    </li>

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
