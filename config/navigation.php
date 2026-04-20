<?php

return static function (array $context): array {
    $permissions = $context['permissions'] ?? [];
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
    $canAccessBillingIndex = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.view', 'billing.manage']);
    $canAccessBillingNoFacturados = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.no_facturados.view', 'billing.no_facturados.create', 'billing.manage']);
    $canAccessBillingDashboard = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.dashboard.view', 'billing.manage']);
    $canAccessBillingHonorarios = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.honorarios.view', 'billing.manage']);
    $canAccessBillingIess = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.iess.view', 'billing.manage']);
    $canAccessBillingIsspol = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.isspol.view', 'billing.manage']);
    $canAccessBillingIssfa = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.issfa.view', 'billing.manage']);
    $canAccessBillingMsp = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.msp.view', 'billing.manage']);
    $canAccessBillingParticulares = \Core\Permissions::containsAny($permissions, ['administrativo', 'billing.particulares.view', 'billing.manage']);
    $canAccessFinanceReports = \Core\Permissions::containsAny($permissions, ['administrativo', 'reportes.view', 'reportes.export', 'billing.manage']);

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
            ? $link('Flowmaker WhatsApp', '/v2/whatsapp/flowmaker', 'mdi mdi-robot-outline', [
                'prefix' => ['/v2/whatsapp/flowmaker'],
            ])
            : null,
        $canConfigureWhatsApp
            ? $link('Plantillas de WhatsApp', '/v2/whatsapp/templates', 'mdi mdi-message-badge-outline', [
                'prefix' => ['/v2/whatsapp/templates'],
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
            ? $link('Dashboard WhatsApp', '/v2/whatsapp/dashboard', 'mdi mdi-chart-line', [
                'prefix' => ['/v2/whatsapp/dashboard'],
            ])
            : null,
        $canAccessWhatsAppChat
            ? $link('Campañas WhatsApp', '/v2/whatsapp/campaigns', 'mdi mdi-bullhorn-outline', [
                'prefix' => ['/v2/whatsapp/campaigns'],
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

    $finanzas = $group('Finanzas', 'mdi mdi-cash-multiple', array_filter([
        ($canAccessBillingIess || $canAccessBillingIsspol || $canAccessBillingIssfa || $canAccessBillingMsp)
            ? $label('Facturacion por afiliacion')
            : null,
        $canAccessBillingIsspol
            ? $link('ISSPOL', '/informes/isspol', 'mdi mdi-shield-outline', [
            'prefix' => ['/informes/isspol', '/v2/informes/isspol'],
        ])
            : null,
        $canAccessBillingIssfa
            ? $link('ISSFA', '/informes/issfa', 'mdi mdi-star-outline', [
            'prefix' => ['/informes/issfa', '/v2/informes/issfa'],
        ])
            : null,
        $canAccessBillingIess
            ? $link('IESS', '/informes/iess', 'mdi mdi-card-account-details-outline', [
            'prefix' => ['/informes/iess', '/v2/informes/iess'],
        ])
            : null,
        $canAccessBillingMsp
            ? $link('MSP', '/informes/msp', 'mdi mdi-hospital-building', [
            'prefix' => ['/informes/msp', '/v2/informes/msp'],
        ])
            : null,
        $canAccessBillingParticulares
            ? $link('Particulares', '/informes/particulares', 'mdi mdi-account-outline', [
            'prefix' => ['/informes/particulares', '/v2/informes/particulares'],
        ])
            : null,
        $canAccessBillingNoFacturados
            ? $link('No facturado', '/billing/no-facturados', 'mdi mdi-alert-circle-outline', [
            'prefix' => ['/billing/no-facturados', '/v2/billing/no-facturados'],
        ])
            : null,
        $canAccessBillingDashboard
            ? $link('Dashboard billing', '/billing/dashboard', 'mdi mdi-chart-box-outline', [
            'prefix' => ['/billing/dashboard', '/v2/billing/dashboard'],
        ])
            : null,
        $canAccessBillingHonorarios
            ? $link('Honorarios', '/billing/honorarios', 'mdi mdi-account-cash-outline', [
            'prefix' => ['/billing/honorarios', '/v2/billing/honorarios'],
        ])
            : null,
        $canAccessBillingIndex
            ? $link('Facturas', '/billing', 'mdi mdi-receipt-text-outline', [
            'prefix' => ['/billing', '/v2/billing'],
            'exclude_prefix' => ['/billing/no-facturados', '/billing/dashboard', '/billing/honorarios', '/v2/billing/no-facturados', '/v2/billing/dashboard', '/v2/billing/honorarios'],
        ])
            : null,
        ($canAccessFinanceReports)
            ? $label('Reportes y estadisticas')
            : null,
        $canAccessFinanceReports
            ? $link('Flujo de pacientes', '/views/reportes/estadistica_flujo.php', 'mdi mdi-chart-timeline-variant', [
            'prefix' => ['/views/reportes/estadistica_flujo.php'],
        ])
            : null,
    ]));

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
        $finanzas['children'] !== [] ? $finanzas : null,
        $administracion['children'] !== [] ? $administracion : null,
        $link('Cerrar sesion', '/v2/auth/logout', 'mdi mdi-logout', []),
    ]));

    return [
        'header_quick_links' => $headerQuickLinks,
        'sidebar' => $sidebar,
        'user_menu_links' => $userMenuLinks,
    ];
};
