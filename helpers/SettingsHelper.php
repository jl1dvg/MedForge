<?php

namespace Helpers;

use DateTimeZone;

class SettingsHelper
{
    public static function definitions(): array
    {
        $languages = self::languageOptions();
        $timezones = self::timezoneOptions();

        return [
            'general' => [
                'title' => 'General',
                'icon' => 'fa-solid fa-gear',
                'description' => 'Configura los datos base de la organización.',
                'groups' => [
                    [
                        'id' => 'company_profile',
                        'title' => 'Perfil de la empresa',
                        'description' => 'Información corporativa mostrada en reportes y comunicaciones.',
                        'fields' => [
                            self::textField('companyname', 'Nombre comercial', true),
                            self::textField('company_legal_name', 'Razón social'),
                            self::textareaField('companyaddress', 'Dirección principal'),
                            self::textField('company_city', 'Ciudad'),
                            self::textField('company_country', 'País'),
                            self::textField('company_vat', 'RUC/NIF'),
                            self::textField('companyphone', 'Teléfono de contacto'),
                            self::emailField('companyemail', 'Correo electrónico principal'),
                            self::textField('companywebsite', 'Sitio web'),
                        ],
                    ],
                ],
            ],
            'branding' => [
                'title' => 'Branding',
                'icon' => 'fa-solid fa-palette',
                'description' => 'Personaliza la apariencia de la plataforma y documentos.',
                'groups' => [
                    [
                        'id' => 'logo_assets',
                        'title' => 'Recursos gráficos',
                        'description' => 'Configura las rutas o nombres de archivo de los recursos cargados en Perfex.',
                        'fields' => [
                            self::textField('company_logo', 'Logo principal'),
                            self::textField('company_logo_dark', 'Logo para modo oscuro'),
                            self::textField('company_logo_small', 'Logo compacto'),
                            self::textField('companysignature', 'Firma digital', false, 'Nombre del archivo subido para la firma.'),
                        ],
                    ],
                    [
                        'id' => 'colors',
                        'title' => 'Colores y temas',
                        'description' => 'Define colores base utilizados en correos y PDF generados.',
                        'fields' => [
                            self::colorField('pdf_text_color', 'Color de texto PDF', '#2D2D2D'),
                            self::colorField('pdf_table_heading_color', 'Encabezados de tabla PDF', '#145388'),
                            self::selectField('admin_default_theme', 'Tema de administrador', [
                                'default' => 'Predeterminado',
                                'dark' => 'Oscuro',
                                'light' => 'Claro',
                            ], 'default'),
                        ],
                    ],
                ],
            ],
            'email' => [
                'title' => 'Correo electrónico',
                'icon' => 'fa-solid fa-envelope',
                'description' => 'Configura la salida de correo y parámetros SMTP.',
                'groups' => [
                    [
                        'id' => 'smtp',
                        'title' => 'Servidor SMTP',
                        'description' => 'Credenciales utilizadas para el envío de notificaciones.',
                        'fields' => [
                            self::selectField('mail_engine', 'Motor de envío', [
                                'phpmailer' => 'PHPMailer',
                                'codeigniter' => 'CodeIgniter Mailer',
                                'mailgun' => 'Mailgun API',
                            ], 'phpmailer'),
                            self::textField('smtp_host', 'Servidor SMTP'),
                            self::numberField('smtp_port', 'Puerto SMTP', 465),
                            self::selectField('smtp_encryption', 'Cifrado', [
                                '' => 'Sin cifrado',
                                'ssl' => 'SSL',
                                'tls' => 'TLS',
                            ]),
                            self::textField('smtp_email', 'Email de autenticación'),
                            self::textField('smtp_username', 'Usuario SMTP'),
                            self::passwordField('smtp_password', 'Contraseña SMTP'),
                        ],
                    ],
                    [
                        'id' => 'email_format',
                        'title' => 'Formato de mensajes',
                        'description' => 'Personaliza encabezados, pie y firma enviados a tus clientes.',
                        'fields' => [
                            self::textareaField('email_header', 'Encabezado HTML'),
                            self::textareaField('email_footer', 'Pie de página HTML'),
                            self::textareaField('email_signature', 'Firma de correo'),
                            self::textField('email_from_name', 'Nombre remitente'),
                            self::emailField('email_from_address', 'Correo remitente'),
                        ],
                    ],
                ],
            ],
            'crm' => [
                'title' => 'CRM y Pipeline',
                'icon' => 'fa-solid fa-diagram-project',
                'description' => 'Configura etapas y comportamiento del tablero Kanban inspirado en Perfex.',
                'groups' => [
                    [
                        'id' => 'pipeline',
                        'title' => 'Pipeline de oportunidades',
                        'description' => 'Define las etapas disponibles y preferencias del tablero clínico/CRM.',
                        'fields' => [
                            self::textareaField(
                                'crm_pipeline_stages',
                                'Etapas del pipeline',
                                'Ingresa una etapa por línea en el orden de tu pipeline.',
                                "Recibido\nContacto inicial\nSeguimiento\nDocs completos\nAutorizado\nAgendado\nCerrado\nPerdido"
                            ),
                            self::selectField(
                                'crm_kanban_sort',
                                'Orden predeterminado del Kanban',
                                [
                                    'fecha_desc' => 'Fecha del procedimiento (más recientes primero)',
                                    'fecha_asc' => 'Fecha del procedimiento (más antiguos primero)',
                                    'creado_desc' => 'Fecha de creación (más recientes primero)',
                                    'creado_asc' => 'Fecha de creación (más antiguos primero)',
                                ],
                                'fecha_desc'
                            ),
                            self::numberField(
                                'crm_kanban_column_limit',
                                'Límite de tarjetas por columna',
                                0,
                                '0 desactiva el límite por columna.'
                            ),
                        ],
                    ],
                ],
            ],
            'notifications' => [
                'title' => 'Notificaciones',
                'icon' => 'fa-solid fa-bell',
                'description' => 'Controla los canales y resúmenes automáticos enviados al equipo.',
                'groups' => [
                    [
                        'id' => 'channels',
                        'title' => 'Canales disponibles',
                        'description' => 'Activa o desactiva los canales soportados por la plataforma.',
                        'fields' => [
                            self::checkboxField('notifications_email_enabled', 'Alertas por correo electrónico', true),
                            self::checkboxField('notifications_sms_enabled', 'Alertas por SMS'),
                        ],
                    ],
                    [
                        'id' => 'realtime',
                        'title' => 'Notificaciones en tiempo real (Pusher.com)',
                        'description' => 'Configura las credenciales necesarias para habilitar actualizaciones instantáneas en el tablero Kanban y módulos CRM.',
                        'fields' => [
                            self::textField('pusher_app_id', 'Pusher APP ID', true),
                            self::textField('pusher_app_key', 'Pusher APP Key', true),
                            self::passwordField('pusher_app_secret', 'Pusher APP Secret'),
                            self::textField('pusher_cluster', 'Cluster', false, 'Consulta https://pusher.com/docs/clusters'),
                            self::checkboxField('pusher_realtime_notifications', 'Habilitar notificaciones en tiempo real'),
                            self::checkboxField('desktop_notifications', 'Habilitar notificaciones de escritorio'),
                            self::numberField(
                                'auto_dismiss_desktop_notifications_after',
                                'Cerrar notificaciones de escritorio después de (segundos)',
                                0,
                                'Usa 0 para mantener la notificación visible hasta que el usuario la cierre.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'summaries',
                        'title' => 'Resúmenes automáticos',
                        'description' => 'Define si deseas recibir un resumen diario consolidado.',
                        'fields' => [
                            self::checkboxField('notifications_daily_summary', 'Enviar resumen diario a administradores'),
                        ],
                    ],
                    [
                        'id' => 'realtime',
                        'title' => 'Pusher.com',
                        'description' => 'Configura credenciales y opciones para notificaciones en tiempo real y de escritorio.',
                        'fields' => [
                            self::textField('pusher_app_id', 'Pusher APP ID'),
                            self::textField('pusher_app_key', 'Pusher APP Key'),
                            self::textField('pusher_app_secret', 'Pusher APP Secret'),
                            self::textField(
                                'pusher_cluster',
                                'Pusher Cluster',
                                false,
                                'Consulta los clústeres disponibles en https://pusher.com/docs/clusters'
                            ),
                            self::checkboxField('pusher_realtime_notifications', 'Enable Real Time Notifications'),
                            self::checkboxField(
                                'desktop_notifications',
                                'Enable Desktop Notifications',
                                false,
                                'Requiere que el navegador esté servido sobre HTTPS para funcionar.'
                            ),
                            self::numberField(
                                'auto_dismiss_desktop_notifications_after',
                                'Auto Dismiss Desktop Notifications After X Seconds (0 to disable)',
                                0,
                                'Establece 0 para mantener las alertas visibles hasta cerrarlas manualmente.'
                            ),
                        ],
                    ],
                ],
            ],
            'integrations' => [
                'title' => 'Integraciones',
                'icon' => 'fa-solid fa-plug',
                'description' => 'Conecta servicios externos como Pusher y Google para ampliar las capacidades del sistema.',
                'groups' => [
                    [
                        'id' => 'pusher',
                        'title' => 'Pusher.com',
                        'description' => 'Configura las credenciales para habilitar notificaciones en tiempo real similares a Perfex.',
                        'fields' => [
                            self::checkboxField(
                                'pusher_realtime_notifications',
                                'Habilitar notificaciones en tiempo real',
                                false,
                                'Activa el disparo de eventos en vivo para usuarios conectados.'
                            ),
                            self::textField('pusher_app_id', 'App ID de Pusher'),
                            self::textField('pusher_app_key', 'App Key de Pusher'),
                            self::passwordField('pusher_app_secret', 'App Secret de Pusher'),
                            self::textField(
                                'pusher_cluster',
                                'Cluster de Pusher',
                                false,
                                'Deja en blanco para utilizar el cluster predeterminado proporcionado por Pusher.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'google',
                        'title' => 'Google Workspace',
                        'description' => 'Integra Google Calendar, Drive y servicios relacionados.',
                        'fields' => [
                            self::textField(
                                'google_api_key',
                                'Clave API de Google',
                                false,
                                'Utilizada para Google Maps, Calendar y el selector de archivos.'
                            ),
                            self::textField(
                                'google_client_id',
                                'ID de cliente OAuth',
                                false,
                                'Formato habitual: xxxxx.apps.googleusercontent.com'
                            ),
                            self::textField(
                                'google_calendar_main_calendar',
                                'ID de calendario principal',
                                false,
                                'Define el calendario predeterminado a sincronizar con Google Calendar.'
                            ),
                            self::checkboxField(
                                'enable_google_picker',
                                'Habilitar Google Drive Picker',
                                false,
                                'Permite adjuntar archivos desde Google Drive en el selector de documentos.'
                            ),
                            self::textField(
                                'recaptcha_site_key',
                                'Clave de sitio reCAPTCHA'
                            ),
                            self::passwordField('recaptcha_secret_key', 'Clave secreta reCAPTCHA'),
                            self::checkboxField(
                                'use_recaptcha_customers_area',
                                'Aplicar reCAPTCHA en el portal de pacientes/cliente'
                            ),
                            self::textareaField(
                                'recaptcha_ignore_ips',
                                'IPs excluidas de reCAPTCHA',
                                'Introduce una IP por línea para saltar la verificación.'
                            ),
                        ],
                    ],
                ],
            ],
            'integrations' => [
                'title' => 'Integraciones',
                'icon' => 'fa-solid fa-plug',
                'description' => 'Conecta servicios externos como Pusher y Google para ampliar las capacidades del sistema.',
                'groups' => [
                    [
                        'id' => 'pusher',
                        'title' => 'Pusher.com',
                        'description' => 'Configura las credenciales para habilitar notificaciones en tiempo real similares a Perfex.',
                        'fields' => [
                            self::checkboxField(
                                'pusher_realtime_notifications',
                                'Habilitar notificaciones en tiempo real',
                                false,
                                'Activa el disparo de eventos en vivo para usuarios conectados.'
                            ),
                            self::textField('pusher_app_id', 'App ID de Pusher'),
                            self::textField('pusher_app_key', 'App Key de Pusher'),
                            self::passwordField('pusher_app_secret', 'App Secret de Pusher'),
                            self::textField(
                                'pusher_cluster',
                                'Cluster de Pusher',
                                false,
                                'Deja en blanco para utilizar el cluster predeterminado proporcionado por Pusher.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'google',
                        'title' => 'Google Workspace',
                        'description' => 'Integra Google Calendar, Drive y servicios relacionados.',
                        'fields' => [
                            self::textField(
                                'google_api_key',
                                'Clave API de Google',
                                false,
                                'Utilizada para Google Maps, Calendar y el selector de archivos.'
                            ),
                            self::textField(
                                'google_client_id',
                                'ID de cliente OAuth',
                                false,
                                'Formato habitual: xxxxx.apps.googleusercontent.com'
                            ),
                            self::textField(
                                'google_calendar_main_calendar',
                                'ID de calendario principal',
                                false,
                                'Define el calendario predeterminado a sincronizar con Google Calendar.'
                            ),
                            self::checkboxField(
                                'enable_google_picker',
                                'Habilitar Google Drive Picker',
                                false,
                                'Permite adjuntar archivos desde Google Drive en el selector de documentos.'
                            ),
                            self::textField(
                                'recaptcha_site_key',
                                'Clave de sitio reCAPTCHA'
                            ),
                            self::passwordField('recaptcha_secret_key', 'Clave secreta reCAPTCHA'),
                            self::checkboxField(
                                'use_recaptcha_customers_area',
                                'Aplicar reCAPTCHA en el portal de pacientes/cliente'
                            ),
                            self::textareaField(
                                'recaptcha_ignore_ips',
                                'IPs excluidas de reCAPTCHA',
                                'Introduce una IP por línea para saltar la verificación.'
                            ),
                        ],
                    ],
                ],
            ],
            'localization' => [
                'title' => 'Localización',
                'icon' => 'fa-solid fa-earth-americas',
                'description' => 'Ajusta idioma, zona horaria y formato de fecha/hora.',
                'groups' => [
                    [
                        'id' => 'locale',
                        'title' => 'Preferencias regionales',
                        'description' => 'Estos valores impactan reportes, plantillas y la interfaz.',
                        'fields' => [
                            self::selectField('default_language', 'Idioma predeterminado', $languages, 'spanish'),
                            self::selectField('timezone', 'Zona horaria', $timezones, 'America/Guayaquil'),
                            self::selectField('dateformat', 'Formato de fecha', [
                                'Y-m-d' => '2024-05-21 (ISO)',
                                'd/m/Y' => '21/05/2024',
                                'm/d/Y' => '05/21/2024',
                                'd.m.Y' => '21.05.2024',
                            ], 'd/m/Y'),
                            self::selectField('time_format', 'Formato de hora', [
                                'H:i' => '24 horas (23:15)',
                                'h:i A' => '12 horas (11:15 PM)',
                            ], 'H:i'),
                            self::textField('default_currency', 'Moneda predeterminada', false, 'Ej. USD, EUR, PEN'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function collectOptionKeys(array $sections): array
    {
        $keys = [];
        foreach ($sections as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['fields'] as $field) {
                    $keys[] = $field['key'];
                }
            }
        }

        return array_values(array_unique($keys));
    }

    public static function populateSections(array $sections, array $values): array
    {
        foreach ($sections as $sectionId => &$section) {
            foreach ($section['groups'] as $groupIndex => &$group) {
                foreach ($group['fields'] as $fieldIndex => &$field) {
                    $key = $field['key'];
                    $value = $values[$key] ?? ($field['default'] ?? '');
                    $field['value'] = $value;
                    if (!empty($field['sensitive']) && $value !== '') {
                        $field['display_value'] = str_repeat('•', 8);
                        $field['has_value'] = true;
                    } else {
                        $field['display_value'] = $value;
                        $field['has_value'] = $value !== '' && $value !== null;
                    }
                    $group['fields'][$fieldIndex] = $field;
                }
                $section['groups'][$groupIndex] = $group;
            }
            $sections[$sectionId] = $section;
        }

        return $sections;
    }

    public static function extractSectionPayload(array $section, array $input): array
    {
        $payload = [];
        foreach ($section['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $key = $field['key'];
                $raw = $input[$key] ?? null;

                if (($field['sensitive'] ?? false) && ($raw === null || $raw === '')) {
                    continue;
                }

                if ($field['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } elseif (is_string($raw)) {
                    $value = trim($raw);
                } else {
                    $value = $raw;
                }

                if ($value === null) {
                    $value = '';
                }

                $payload[$key] = (string) $value;
            }
        }

        return $payload;
    }

    private static function textField(string $key, string $label, bool $required = false, ?string $help = null): array
    {
        return [
            'type' => 'text',
            'key' => $key,
            'label' => $label,
            'required' => $required,
            'help' => $help,
        ];
    }

    private static function emailField(string $key, string $label, ?string $help = null): array
    {
        return [
            'type' => 'email',
            'key' => $key,
            'label' => $label,
            'help' => $help,
        ];
    }

    private static function textareaField(string $key, string $label, ?string $help = null, ?string $default = null): array
    {
        $field = [
            'type' => 'textarea',
            'key' => $key,
            'label' => $label,
        ];

        if ($help !== null) {
            $field['help'] = $help;
        }

        if ($default !== null) {
            $field['default'] = $default;
        }

        return $field;
    }

    private static function numberField(string $key, string $label, int $default = 0, ?string $help = null): array
    {
        return [
            'type' => 'number',
            'key' => $key,
            'label' => $label,
            'default' => $default,
            'help' => $help,
        ];
    }

    private static function colorField(string $key, string $label, string $default = '#145388'): array
    {
        return [
            'type' => 'color',
            'key' => $key,
            'label' => $label,
            'default' => $default,
        ];
    }

    private static function selectField(string $key, string $label, array $options, string $default = ''): array
    {
        return [
            'type' => 'select',
            'key' => $key,
            'label' => $label,
            'options' => $options,
            'default' => $default,
        ];
    }

    private static function passwordField(string $key, string $label): array
    {
        return [
            'type' => 'password',
            'key' => $key,
            'label' => $label,
            'sensitive' => true,
        ];
    }

    private static function checkboxField(string $key, string $label, bool $default = false, ?string $help = null): array
    {
        return [
            'type' => 'checkbox',
            'key' => $key,
            'label' => $label,
            'default' => $default ? '1' : '0',
            'help' => $help,
        ];
    }

    private static function languageOptions(): array
    {
        return [
            'spanish' => 'Español',
            'english' => 'Inglés',
            'french' => 'Francés',
            'portuguese' => 'Portugués',
        ];
    }

    private static function timezoneOptions(): array
    {
        $zones = [];
        foreach (DateTimeZone::listIdentifiers() as $zone) {
            $zones[$zone] = $zone;
        }

        return $zones;
    }
}
