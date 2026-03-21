<?php

return static function (array $context): array {
    $canAccessUsers = (bool) ($context['canAccessUsers'] ?? false);
    $canAccessRoles = (bool) ($context['canAccessRoles'] ?? false);
    $canAccessSettings = (bool) ($context['canAccessSettings'] ?? false);
    $canAccessCRM = (bool) ($context['canAccessCRM'] ?? false);
    $canAccessWhatsAppChat = (bool) ($context['canAccessWhatsAppChat'] ?? false);
    $canConfigureWhatsApp = (bool) ($context['canConfigureWhatsApp'] ?? false);
    $canAccessCronManager = (bool) ($context['canAccessCronManager'] ?? false);
    $canAccessDoctors = (bool) ($context['canAccessDoctors'] ?? false);
    $canAccessCodes = (bool) ($context['canAccessCodes'] ?? false);
    $canAccessPatientVerification = (bool) ($context['canAccessPatientVerification'] ?? false);
    $canAccessProtocolTemplates = (bool) ($context['canAccessProtocolTemplates'] ?? false);
    $canAccessMailbox = (bool) ($context['canAccessMailbox'] ?? false);
    $canAccessQuirurgicoDashboard = (bool) ($context['canAccessQuirurgicoDashboard'] ?? false);

    $link = static function (
        string $label,
        string $href,
        string $icon,
        array $active = []
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

    $comercial = $group('Comercial', 'mdi mdi-briefcase-outline', array_filter([
        $canAccessCRM
            ? $link('CRM', '/crm', 'mdi mdi-ticket-account', [
                'prefix' => ['/crm'],
            ])
            : null,
        $link('Flujo de Pacientes', '/pacientes/flujo', 'mdi mdi-transit-connection-variant', [
            'prefix' => ['/pacientes/flujo', '/v2/pacientes/flujo'],
        ]),
        $link('Campanas y Leads', '/leads', 'mdi mdi-bullhorn-outline', [
            'prefix' => ['/leads'],
        ]),
        $canConfigureWhatsApp
            ? $link('Automatizaciones de WhatsApp', '/whatsapp/autoresponder', 'mdi mdi-robot-outline', [
                'prefix' => ['/whatsapp/autoresponder'],
            ])
            : null,
        $canConfigureWhatsApp
            ? $link('Plantillas de WhatsApp', '/whatsapp/templates', 'mdi mdi-message-badge-outline', [
                'prefix' => ['/whatsapp/templates'],
            ])
            : null,
    ]));

    $operacionDiaria = $group('Operacion diaria', 'mdi mdi-stethoscope', array_filter([
        $link('Agenda', '/agenda', 'mdi mdi-calendar-clock-outline', [
            'prefix' => ['/agenda'],
        ]),
        $link('Lista de Pacientes', '/pacientes', 'mdi mdi-account-multiple-outline', [
            'exact' => ['/pacientes', '/v2/pacientes'],
            'prefix' => ['/pacientes/detalles', '/v2/pacientes/detalles'],
        ]),
        $link('Derivaciones', '/derivaciones', 'mdi mdi-file-find-outline', [
            'prefix' => ['/derivaciones', '/v2/derivaciones'],
        ]),
        $link('Agendamiento', '/turnoAgenda/agenda-doctor/index', 'mdi mdi-calendar-check-outline', [
            'prefix' => ['/turnoAgenda'],
        ]),
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

    $clinica = $group('Clinica', 'mdi mdi-hospital-box-outline', array_filter([
        $link('Solicitudes', '/solicitudes', 'mdi mdi-file-document-outline', [
            'prefix' => ['/solicitudes', '/v2/solicitudes'],
            'exclude_prefix' => ['/solicitudes/dashboard', '/v2/solicitudes/dashboard'],
        ]),
        $link('Protocolos realizados', '/cirugias', 'mdi mdi-clipboard-check-outline', [
            'prefix' => ['/cirugias', '/v2/cirugias'],
            'exclude_prefix' => ['/cirugias/dashboard', '/v2/cirugias/dashboard'],
        ]),
        $canAccessQuirurgicoDashboard
            ? $link('Dashboard quirurgico', '/cirugias/dashboard', 'mdi mdi-chart-arc', [
                'prefix' => ['/cirugias/dashboard', '/solicitudes/dashboard', '/v2/cirugias/dashboard', '/v2/solicitudes/dashboard'],
            ])
            : null,
        $link('Planificador de IPL', '/ipl', 'mdi mdi-calendar-multiselect-outline', [
            'prefix' => ['/ipl', '/views/ipl', '/v2/ipl'],
        ]),
        $link('Examenes', '/examenes', 'mdi mdi-image-filter-center-focus', [
            'prefix' => ['/examenes', '/v2/examenes'],
        ]),
        $link('Examenes realizados', '/imagenes/examenes-realizados', 'mdi mdi-file-image-outline', [
            'prefix' => ['/imagenes/examenes-realizados', '/v2/imagenes/examenes-realizados'],
        ]),
        $link('Dashboard imagenes', '/imagenes/dashboard', 'mdi mdi-monitor-dashboard', [
            'prefix' => ['/imagenes/dashboard', '/v2/imagenes/dashboard'],
        ]),
        $canAccessProtocolTemplates
            ? $link('Plantillas de protocolos', '/protocolos', 'mdi mdi-note-multiple-outline', [
                'prefix' => ['/protocolos'],
            ])
            : null,
    ]));

    $inventario = $group('Inventario', 'mdi mdi-package-variant-closed', array_filter([
        $link('Lista de insumos', '/insumos', 'mdi mdi-format-list-bulleted', [
            'exact' => ['/insumos'],
        ]),
        $link('Lista de medicamentos', '/insumos/medicamentos', 'mdi mdi-pill', [
            'prefix' => ['/insumos/medicamentos'],
        ]),
        $link('Catalogo de lentes', '/insumos/lentes', 'mdi mdi-glasses', [
            'prefix' => ['/insumos/lentes'],
        ]),
        $link('Dashboard farmacia', '/v2/farmacia', 'mdi mdi-medical-bag', [
            'prefix' => ['/farmacia', '/v2/farmacia'],
        ]),
    ]));

    $finanzas = $group('Finanzas', 'mdi mdi-cash-multiple', [
        $label('Facturacion por afiliacion'),
        $link('ISSPOL', '/informes/isspol', 'mdi mdi-shield-outline', [
            'prefix' => ['/informes/isspol', '/v2/informes/isspol'],
        ]),
        $link('ISSFA', '/informes/issfa', 'mdi mdi-star-outline', [
            'prefix' => ['/informes/issfa', '/v2/informes/issfa'],
        ]),
        $link('IESS', '/informes/iess', 'mdi mdi-card-account-details-outline', [
            'prefix' => ['/informes/iess', '/v2/informes/iess'],
        ]),
        $link('Particulares', '/informes/particulares', 'mdi mdi-account-outline', [
            'prefix' => ['/informes/particulares', '/v2/informes/particulares'],
        ]),
        $link('No facturado', '/billing/no-facturados', 'mdi mdi-alert-circle-outline', [
            'prefix' => ['/billing/no-facturados', '/v2/billing/no-facturados'],
        ]),
        $link('Dashboard billing', '/billing/dashboard', 'mdi mdi-chart-box-outline', [
            'prefix' => ['/billing/dashboard', '/v2/billing/dashboard'],
        ]),
        $label('Reportes y estadisticas'),
        $link('Flujo de pacientes', '/views/reportes/estadistica_flujo.php', 'mdi mdi-chart-timeline-variant', [
            'prefix' => ['/views/reportes/estadistica_flujo.php'],
        ]),
    ]);

    $administracion = $group('Administracion', 'mdi mdi-shield-crown-outline', array_filter([
        $canAccessDoctors
            ? $link('Doctores', '/doctores', 'mdi mdi-stethoscope', [
                'prefix' => ['/doctores'],
            ])
            : null,
        $canAccessUsers
            ? $link('Usuarios', '/usuarios', 'mdi mdi-account-key-outline', [
                'prefix' => ['/usuarios'],
            ])
            : null,
        $canAccessRoles
            ? $link('Roles', '/roles', 'mdi mdi-security', [
                'prefix' => ['/roles'],
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
            ? $link('Catalogo de codigos', '/codes', 'mdi mdi-tag-multiple-outline', [
                'prefix' => ['/codes', '/v2/codes'],
                'exclude_prefix' => ['/codes/packages', '/v2/codes/packages'],
            ])
            : null,
        $canAccessCodes
            ? $link('Constructor de paquetes', '/codes/packages', 'mdi mdi-package-variant', [
                'prefix' => ['/codes/packages', '/v2/codes/packages'],
            ])
            : null,
    ]));

    $headerQuickLinks = array_values(array_filter([
        [
            'label' => 'Pacientes',
            'href' => '/pacientes',
            'icon' => 'mdi mdi-account-multiple-outline',
        ],
        [
            'label' => 'Agenda',
            'href' => '/agenda',
            'icon' => 'mdi mdi-calendar-clock-outline',
        ],
        [
            'label' => 'Solicitudes',
            'href' => '/solicitudes',
            'icon' => 'mdi mdi-file-document-outline',
        ],
        [
            'label' => 'Examenes',
            'href' => '/examenes',
            'icon' => 'mdi mdi-image-filter-center-focus',
        ],
        $canAccessCRM
            ? [
                'label' => 'CRM',
                'href' => '/crm',
                'icon' => 'mdi mdi-ticket-account',
            ]
            : null,
        $canAccessSettings
            ? [
                'label' => 'Ajustes',
                'href' => '/settings',
                'icon' => 'mdi mdi-cog-outline',
            ]
            : null,
    ]));

    $userMenuLinks = array_values(array_filter([
        $canAccessSettings
            ? [
                'label' => 'Ajustes',
                'href' => '/settings',
                'icon' => 'ti-settings',
            ]
            : null,
        $canAccessUsers
            ? [
                'label' => 'Usuarios',
                'href' => '/usuarios',
                'icon' => 'ti-user',
            ]
            : null,
    ]));

    $sidebar = array_values(array_filter([
        $link('Inicio', '/dashboard', 'mdi mdi-view-dashboard-outline', [
            'prefix' => ['/dashboard', '/v2/dashboard'],
        ]),
        $comercial['children'] !== [] ? $comercial : null,
        $operacionDiaria,
        $clinica,
        $inventario,
        $finanzas,
        $administracion['children'] !== [] ? $administracion : null,
        $link('Cerrar sesion', '/v2/auth/logout', 'mdi mdi-logout', []),
    ]));

    return [
        'header_quick_links' => $headerQuickLinks,
        'sidebar' => $sidebar,
        'user_menu_links' => $userMenuLinks,
    ];
};
