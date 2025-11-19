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
            'examenes' => [
                'title' => 'Exámenes',
                'icon' => 'fa-solid fa-eye-dropper',
                'description' => 'Ajusta el comportamiento del tablero de exámenes y su distribución por columnas.',
                'groups' => [
                    [
                        'id' => 'kanban',
                        'title' => 'Tablero de exámenes',
                        'description' => 'Controla el orden inicial y los límites de tarjetas visibles por estado.',
                        'fields' => [
                            self::selectField(
                                'examenes_kanban_sort',
                                'Orden predeterminado del Kanban de Exámenes',
                                [
                                    'creado_desc' => 'Fecha de creación (más recientes primero)',
                                    'creado_asc' => 'Fecha de creación (más antiguos primero)',
                                    'fecha_desc' => 'Fecha de consulta (más recientes primero)',
                                    'fecha_asc' => 'Fecha de consulta (más antiguos primero)',
                                ],
                                'creado_desc'
                            ),
                            self::numberField(
                                'examenes_kanban_column_limit',
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
                ],
            ],
            'mailbox' => [
                'title' => 'Mailbox',
                'icon' => 'fa-solid fa-inbox',
                'description' => 'Configura el inbox unificado que combina Solicitudes, Exámenes, Tickets y WhatsApp.',
                'groups' => [
                    [
                        'id' => 'mailbox_preferences',
                        'title' => 'Preferencias generales',
                        'description' => 'Activa el módulo y elige qué fuentes deben aparecer en el panel.',
                        'fields' => [
                            self::checkboxField(
                                'mailbox_enabled',
                                'Habilitar Mailbox unificado',
                                true,
                                'Oculta por completo el módulo si lo desactivas.'
                            ),
                            self::checkboxField(
                                'mailbox_compose_enabled',
                                'Permitir registrar notas desde el Mailbox',
                                true,
                                'Si lo desactivas, solo podrás visualizar conversaciones.'
                            ),
                            self::checkboxField(
                                'mailbox_source_solicitudes',
                                'Mostrar notas de Solicitudes',
                                true
                            ),
                            self::checkboxField(
                                'mailbox_source_examenes',
                                'Mostrar notas de Exámenes',
                                true
                            ),
                            self::checkboxField(
                                'mailbox_source_tickets',
                                'Mostrar mensajes de Tickets',
                                true
                            ),
                            self::checkboxField(
                                'mailbox_source_whatsapp',
                                'Mostrar mensajes de WhatsApp',
                                true
                            ),
                            self::numberField(
                                'mailbox_limit',
                                'Mensajes visibles por carga',
                                50,
                                'Valor recomendado entre 25 y 100 (máximo 200).'
                            ),
                            self::selectField(
                                'mailbox_sort',
                                'Orden predeterminado',
                                [
                                    'recent' => 'Más recientes primero',
                                    'oldest' => 'Más antiguos primero',
                                ],
                                'recent'
                            ),
                        ],
                    ],
                ],
            ],
            'billing_informes' => [
                'title' => 'Informes de facturación',
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'description' => 'Personaliza títulos, rutas, afiliaciones y botones de exportación para cada aseguradora.',
                'groups' => [
                    [
                        'id' => 'iess',
                        'title' => 'IESS',
                        'description' => 'Ajustes mostrados cuando navegas a /informes/iess.',
                        'fields' => [
                            array_merge(self::textField('billing_informes_iess_title', 'Título del informe'), ['default' => 'Informe IESS']),
                            array_merge(self::textField('billing_informes_iess_base_path', 'Ruta base'), ['default' => '/informes/iess']),
                            array_merge(self::textareaField('billing_informes_iess_afiliaciones', 'Afiliaciones permitidas', 'Introduce una afiliación por línea.'), [
                                'default' => "contribuyente voluntario\nconyuge\nconyuge pensionista\nseguro campesino\nseguro campesino jubilado\nseguro general\nseguro general jubilado\nseguro general por montepio\nseguro general tiempo parcial\nhijos dependientes",
                            ]),
                            array_merge(self::textareaField(
                                'billing_informes_iess_excel_buttons',
                                'Botones de descarga',
                                'Una línea por botón usando el formato GRUPO|Etiqueta|Clase CSS|Icono opcional.'
                            ), [
                                'default' => "IESS|Descargar Excel|btn btn-success btn-lg me-2|fa fa-file-excel-o\nIESS_SOAM|Descargar SOAM|btn btn-outline-success btn-lg me-2|fa fa-file-excel-o",
                            ]),
                            array_merge(self::textField('billing_informes_iess_scrape_label', 'Etiqueta del botón de scraping'), ['default' => '📋 Ver todas las atenciones por cobrar']),
                            array_merge(self::textField('billing_informes_iess_consolidado_title', 'Título del consolidado'), ['default' => 'Consolidado mensual de pacientes IESS']),
                            self::checkboxField('billing_informes_iess_apellido_filter', 'Habilitar filtro por apellido'),
                        ],
                    ],
                    [
                        'id' => 'isspol',
                        'title' => 'ISSPOL',
                        'description' => 'Configura la vista de /informes/isspol.',
                        'fields' => [
                            array_merge(self::textField('billing_informes_isspol_title', 'Título del informe'), ['default' => 'Informe ISSPOL']),
                            array_merge(self::textField('billing_informes_isspol_base_path', 'Ruta base'), ['default' => '/informes/isspol']),
                            array_merge(self::textareaField('billing_informes_isspol_afiliaciones', 'Afiliaciones permitidas', 'Una afiliación por línea.'), ['default' => 'isspol']),
                            array_merge(self::textareaField(
                                'billing_informes_isspol_excel_buttons',
                                'Botones de descarga',
                                'Una línea por botón usando el formato GRUPO|Etiqueta|Clase CSS|Icono opcional.'
                            ), [
                                'default' => 'ISSPOL|Descargar Excel|btn btn-success btn-lg me-2|fa fa-file-excel-o',
                            ]),
                            array_merge(self::textField('billing_informes_isspol_scrape_label', 'Etiqueta del botón de scraping'), ['default' => '📋 Obtener código de derivación']),
                            array_merge(self::textField('billing_informes_isspol_consolidado_title', 'Título del consolidado'), ['default' => 'Consolidado mensual de pacientes ISSPOL']),
                            self::checkboxField('billing_informes_isspol_apellido_filter', 'Habilitar filtro por apellido', true),
                        ],
                    ],
                    [
                        'id' => 'issfa',
                        'title' => 'ISSFA',
                        'description' => 'Configura la vista de /informes/issfa.',
                        'fields' => [
                            array_merge(self::textField('billing_informes_issfa_title', 'Título del informe'), ['default' => 'Informe ISSFA']),
                            array_merge(self::textField('billing_informes_issfa_base_path', 'Ruta base'), ['default' => '/informes/issfa']),
                            array_merge(self::textareaField('billing_informes_issfa_afiliaciones', 'Afiliaciones permitidas', 'Una afiliación por línea.'), ['default' => 'issfa']),
                            array_merge(self::textareaField(
                                'billing_informes_issfa_excel_buttons',
                                'Botones de descarga',
                                'Una línea por botón usando el formato GRUPO|Etiqueta|Clase CSS|Icono opcional.'
                            ), [
                                'default' => 'ISSFA|Descargar Excel|btn btn-success btn-lg me-2|fa fa-file-excel-o',
                            ]),
                            array_merge(self::textField('billing_informes_issfa_scrape_label', 'Etiqueta del botón de scraping'), ['default' => '📋 Obtener código de derivación']),
                            array_merge(self::textField('billing_informes_issfa_consolidado_title', 'Título del consolidado'), ['default' => 'Consolidado mensual de pacientes ISSFA']),
                            self::checkboxField('billing_informes_issfa_apellido_filter', 'Habilitar filtro por apellido', true),
                        ],
                    ],
                    [
                        'id' => 'custom',
                        'title' => 'Grupos adicionales',
                        'description' => 'Define reglas extra en formato JSON compatibles con el arreglo $grupoConfigs.',
                        'fields' => [
                            self::textareaField(
                                'billing_informes_custom_groups',
                                'Configuración avanzada (JSON)',
                                'Ejemplo: [{"slug":"seguroxyz","titulo":"Informe Seguro XYZ","basePath":"/informes/seguroxyz","afiliaciones":["seguro xyz"],"excelButtons":[{"grupo":"XYZ","label":"Excel"}]}]'
                            ),
                        ],
                    ],
                ],
            ],
            'whatsapp' => [
                'title' => 'WhatsApp',
                'icon' => 'fa-brands fa-whatsapp',
                'description' => 'Administra la integración con WhatsApp Cloud API y futuros canales conversacionales internos.',
                'groups' => [
                    [
                        'id' => 'cloud_api',
                        'title' => 'WhatsApp Cloud API',
                        'description' => 'Credenciales y preferencias compartidas por el módulo de WhatsApp, listas para reutilizar en un chat interno.',
                        'fields' => [
                            self::checkboxField(
                                'whatsapp_cloud_enabled',
                                'Habilitar WhatsApp Cloud API',
                                false,
                                'Activa el envío de mensajes transaccionales y notificaciones por WhatsApp.'
                            ),
                            self::textField(
                                'whatsapp_cloud_phone_number_id',
                                'Phone Number ID',
                                true,
                                'Identificador del número configurado en Meta Business.'
                            ),
                            self::textField(
                                'whatsapp_cloud_business_account_id',
                                'Business Account ID',
                                false,
                                'Dato informativo útil para auditoría o múltiples líneas.'
                            ),
                            self::passwordField('whatsapp_cloud_access_token', 'Access Token'),
                            array_merge(
                                self::textField(
                                    'whatsapp_cloud_api_version',
                                    'Versión de la API de Graph'
                                ),
                                ['default' => 'v17.0']
                            ),
                            self::textField(
                                'whatsapp_cloud_default_country_code',
                                'Código de país predeterminado',
                                false,
                                'Se antepone si el número de teléfono no incluye prefijo internacional. Ej: 593.'
                            ),
                            array_merge(
                                self::textField(
                                    'whatsapp_webhook_verify_token',
                                    'Token de verificación del webhook',
                                    false,
                                    'Debe coincidir con el token configurado en Meta para validar la suscripción.'
                                ),
                                ['default' => 'medforge-whatsapp']
                            ),
                        ],
                    ],
                    [
                        'id' => 'data_protection',
                        'title' => 'Protección de datos y plantillas',
                        'description' => 'Controla la verificación de identidad, el consentimiento y las plantillas enriquecidas enviadas por el autorespondedor.',
                        'fields' => [
                            self::textField(
                                'whatsapp_registry_lookup_url',
                                'Endpoint del Registro Civil',
                                false,
                                'URL del servicio externo para validar cédulas. Usa {{cedula}} como placeholder.'
                            ),
                            self::passwordField(
                                'whatsapp_registry_token',
                                'Token API Registro Civil'
                            ),
                            array_merge(
                                self::numberField(
                                    'whatsapp_registry_timeout',
                                    'Tiempo de espera del API (segundos)',
                                    10,
                                    'Define el tiempo máximo de espera antes de marcar la consulta como fallida.'
                                ),
                                ['min' => 1, 'max' => 60]
                            ),
                            array_merge(
                                self::textareaField(
                                    'whatsapp_data_consent_message',
                                    'Mensaje de consentimiento predeterminado',
                                    "Confirmamos tu identidad y protegemos tus datos personales. ¿Autorizas el uso de tu información para gestionar tus servicios médicos?"
                                ),
                                ['rows' => 3]
                            ),
                            array_merge(
                                self::textField(
                                    'whatsapp_data_consent_yes_keywords',
                                    'Palabras clave para aceptar',
                                    false,
                                    "si,acepto,confirmo,confirmar"
                                ),
                                ['placeholder' => 'Separadas por comas']
                            ),
                            array_merge(
                                self::textField(
                                    'whatsapp_data_consent_no_keywords',
                                    'Palabras clave para rechazar',
                                    false,
                                    "no,rechazo,no autorizo"
                                ),
                                ['placeholder' => 'Separadas por comas']
                            ),
                            array_merge(
                                self::textField(
                                    'whatsapp_webhook_verify_token',
                                    'Token de verificación del webhook',
                                    false,
                                    'Debe coincidir con el token configurado en Meta para validar la suscripción.'
                                ),
                                ['default' => 'medforge-whatsapp']
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
            'ai' => [
                'title' => 'Inteligencia Artificial',
                'icon' => 'fa-solid fa-robot',
                'description' => 'Administra los proveedores de IA y qué funciones clínicas utilizan asistencia automatizada.',
                'groups' => [
                    [
                        'id' => 'provider',
                        'title' => 'Proveedor activo',
                        'description' => 'Selecciona el motor principal de IA que se usará en la plataforma.',
                        'fields' => [
                            self::selectField(
                                'ai_provider',
                                'Proveedor de IA',
                                [
                                    '' => 'Desactivado',
                                    'openai' => 'OpenAI',
                                ],
                                'openai'
                            ),
                        ],
                    ],
                    [
                        'id' => 'openai_credentials',
                        'title' => 'Credenciales de OpenAI',
                        'description' => 'Configura el acceso a la Responses API o a un gateway compatible.',
                        'fields' => [
                            array_merge(
                                self::passwordField('ai_openai_api_key', 'API Key de OpenAI'),
                                ['required' => true]
                            ),
                            array_merge(
                                self::textField(
                                    'ai_openai_endpoint',
                                    'Endpoint principal',
                                    true,
                                    'URL completa al endpoint compatible con Responses API.'
                                ),
                                ['default' => 'https://api.openai.com/v1/responses']
                            ),
                            array_merge(
                                self::textField(
                                    'ai_openai_model',
                                    'Modelo predeterminado',
                                    true,
                                    'Modelo utilizado por defecto para las solicitudes clínicas.'
                                ),
                                ['default' => 'gpt-4o-mini']
                            ),
                            array_merge(
                                self::numberField(
                                    'ai_openai_max_output_tokens',
                                    'Límite de tokens de salida',
                                    400,
                                    'Define el máximo de tokens que se solicitará al generar respuestas.'
                                ),
                                ['default' => 400]
                            ),
                            self::textField(
                                'ai_openai_organization',
                                'Organización (opcional)',
                                false,
                                'Solo necesario si tu cuenta requiere cabecera OpenAI-Organization.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'features',
                        'title' => 'Funciones asistidas',
                        'description' => 'Activa o desactiva las herramientas clínicas que utilizan IA.',
                        'fields' => [
                            self::checkboxField(
                                'ai_enable_consultas_enfermedad',
                                'Sugerencias para enfermedad actual en consultas',
                                true
                            ),
                            self::checkboxField(
                                'ai_enable_consultas_plan',
                                'Propuestas de plan y procedimientos',
                                true
                            ),
                        ],
                    ],
                ],
            ],
            'ai' => [
                'title' => 'Inteligencia Artificial',
                'icon' => 'fa-solid fa-robot',
                'description' => 'Configura las credenciales y decide en qué pantallas clínicas estará disponible la asistencia de IA (consultas médicas y planes de tratamiento).',
                'groups' => [
                    [
                        'id' => 'provider',
                        'title' => 'Proveedor activo',
                        'description' => 'Selecciona el motor principal de IA que responderá a las solicitudes generadas desde MedForge. Si lo dejas desactivado, los botones de IA desaparecerán de las vistas clínicas.',
                        'fields' => [
                            self::selectField(
                                'ai_provider',
                                'Proveedor de IA',
                                [
                                    '' => 'Desactivado',
                                    'openai' => 'OpenAI',
                                ],
                                'openai'
                            ),
                        ],
                    ],
                    [
                        'id' => 'openai_credentials',
                        'title' => 'Credenciales de OpenAI',
                        'description' => 'Configura el acceso a la Responses API o a un gateway compatible para que la plataforma pueda generar resúmenes y propuestas clínicas.',
                        'fields' => [
                            array_merge(
                                self::passwordField('ai_openai_api_key', 'API Key de OpenAI'),
                                [
                                    'required' => true,
                                    'help' => 'Crea o reutiliza una API Key desde tu cuenta en platform.openai.com y pégala aquí. Se utiliza en cada solicitud de IA clínica.'
                                ]
                            ),
                            array_merge(
                                self::textField(
                                    'ai_openai_endpoint',
                                    'Endpoint principal',
                                    true,
                                    'URL completa al endpoint compatible con Responses API.'
                                ),
                                [
                                    'default' => 'https://api.openai.com/v1/responses',
                                    'help' => 'Modifica este valor solo si utilizas un proxy o gateway propio. El endpoint debe aceptar solicitudes de la Responses API.'
                                ]
                            ),
                            array_merge(
                                self::textField(
                                    'ai_openai_model',
                                    'Modelo predeterminado',
                                    true,
                                    'Modelo utilizado por defecto para las solicitudes clínicas.'
                                ),
                                [
                                    'default' => 'gpt-4o-mini',
                                    'help' => 'Introduce el identificador del modelo (por ejemplo, gpt-4o-mini o gpt-4o). Debe estar habilitado en tu cuenta.'
                                ]
                            ),
                            array_merge(
                                self::numberField(
                                    'ai_openai_max_output_tokens',
                                    'Límite de tokens de salida',
                                    400,
                                    'Define el máximo de tokens que se solicitará al generar respuestas.'
                                ),
                                [
                                    'default' => 400,
                                    'help' => 'Reduce el número si deseas respuestas más cortas o si tu plan tiene límites estrictos de uso.'
                                ]
                            ),
                            self::textField(
                                'ai_openai_organization',
                                'Organización (opcional)',
                                false,
                                'Solo necesario si tu cuenta requiere cabecera OpenAI-Organization.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'features',
                        'title' => 'Funciones asistidas',
                        'description' => 'Activa o desactiva las herramientas clínicas que consumen IA. Cada opción controla un botón dentro de la historia clínica que envía información al endpoint correspondiente.',
                        'fields' => [
                            self::checkboxField(
                                'ai_enable_consultas_enfermedad',
                                'Sugerencias para enfermedad actual en consultas',
                                true,
                                'Cuando está activo, el formulario de consulta mostrará el botón “Generar enfermedad actual con IA” que llama al endpoint /ai/enfermedad usando los datos capturados.'
                            ),
                            self::checkboxField(
                                'ai_enable_consultas_plan',
                                'Propuestas de plan y procedimientos',
                                true,
                                'Habilita el botón “Proponer plan con IA” dentro de la consulta. Envía el resumen clínico al endpoint /ai/plan para obtener recomendaciones.'
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
            'identity_verification' => [
                'title' => 'Verificación de identidad',
                'icon' => 'fa-solid fa-user-check',
                'description' => 'Configura políticas de vigencia, umbrales biométricos y el escalamiento automático del módulo de certificación.',
                'groups' => [
                    [
                        'id' => 'policies',
                        'title' => 'Políticas y umbrales biométricos',
                        'description' => 'Define cuánto tiempo permanece vigente una certificación y los puntajes requeridos para aprobar o rechazar un check-in.',
                        'fields' => [
                            self::numberField('identity_verification_validity_days', 'Días de vigencia de una certificación', 365, 'Usa 0 para desactivar la caducidad automática.'),
                            self::numberField('identity_verification_face_approve_threshold', 'Puntaje mínimo rostro (aprobación)', 80),
                            self::numberField('identity_verification_face_reject_threshold', 'Puntaje mínimo rostro (rechazo)', 40),
                            self::numberField('identity_verification_signature_approve_threshold', 'Puntaje mínimo firma (aprobación)', 80),
                            self::numberField('identity_verification_signature_reject_threshold', 'Puntaje mínimo firma (rechazo)', 40),
                            self::numberField('identity_verification_single_approve_threshold', 'Puntaje mínimo biometría única (aprobación)', 85),
                            self::numberField('identity_verification_single_reject_threshold', 'Puntaje mínimo biometría única (rechazo)', 40),
                        ],
                    ],
                    [
                        'id' => 'escalation',
                        'title' => 'Escalamiento automático',
                        'description' => 'Controla la generación de tickets internos cuando falte evidencia biométrica o venza una certificación.',
                        'fields' => [
                            self::checkboxField('identity_verification_auto_escalate', 'Habilitar escalamiento automático', true, 'Genera avisos internos cuando se detecten incidentes en el check-in.'),
                            self::selectField('identity_verification_escalation_channel', 'Canal de escalamiento', [
                                'crm_ticket' => 'Ticket CRM interno',
                                'none' => 'Sin escalamiento',
                            ], 'crm_ticket'),
                            self::selectField('identity_verification_escalation_priority', 'Prioridad de tickets', [
                                'baja' => 'Baja',
                                'media' => 'Media',
                                'alta' => 'Alta',
                                'critica' => 'Crítica',
                            ], 'alta'),
                            self::numberField('identity_verification_escalation_assignee', 'Asignar tickets al usuario ID', 0, 'Utiliza 0 para dejar el ticket sin asignar.'),
                        ],
                    ],
                    [
                        'id' => 'consents',
                        'title' => 'Consentimientos y comprobantes',
                        'description' => 'Configura la generación de documentos PDF firmados digitalmente para respaldar cada check-in.',
                        'fields' => [
                            self::checkboxField('identity_verification_generate_pdf', 'Generar PDF firmado digitalmente', true),
                            self::textField('identity_verification_pdf_signature_certificate', 'Certificado digital (ruta)'),
                            self::textField('identity_verification_pdf_signature_key', 'Clave privada (ruta)'),
                            self::passwordField('identity_verification_pdf_signature_password', 'Contraseña del certificado'),
                            self::textField('identity_verification_pdf_signature_name', 'Nombre del firmante digital'),
                            self::textField('identity_verification_pdf_signature_location', 'Ubicación de la firma'),
                            self::textField('identity_verification_pdf_signature_reason', 'Motivo registrado en el PDF', false, 'Se mostrará en el panel de firma digital.'),
                            self::textField('identity_verification_pdf_signature_image', 'Imagen de la firma digital (ruta)'),
                        ],
                    ],
                ],
            ],
            'cive_extension' => [
                'title' => 'CIVE Extension',
                'icon' => 'fa-solid fa-puzzle-piece',
                'description' => 'Controla desde MedForge las operaciones de la extensión clínica y sus integraciones.',
                'groups' => [
                    [
                        'id' => 'api_client',
                        'title' => 'Cliente API',
                        'description' => 'Parámetros compartidos por todos los módulos que consumen las APIs de MedForge/CIVE.',
                        'fields' => [
                            self::textField('cive_extension_control_base_url', 'URL base pública', false, 'Se usa para emitir el bootstrap de la extensión. Si se omite se deriva desde BASE_URL.'),
                            self::textField('cive_extension_api_base_url', 'URL base del API', true, 'Ej: https://asistentecive.consulmed.me/api. Puedes sobreescribirlo si tu API está detrás de otro host.'),
                            self::selectField('cive_extension_api_credentials_mode', 'Modo credentials de fetch', [
                                'include' => 'include (enviar cookies a dominios autorizados)',
                                'same-origin' => 'same-origin',
                                'omit' => 'omit',
                            ], 'include'),
                            self::numberField('cive_extension_timeout_ms', 'Timeout de peticiones (ms)', 12000),
                            self::numberField('cive_extension_max_retries', 'Reintentos ante error', 2),
                            self::numberField('cive_extension_retry_delay_ms', 'Tiempo entre reintentos (ms)', 600),
                            self::numberField('cive_extension_procedures_cache_ttl_ms', 'TTL caché de procedimientos (ms)', 300000),
                            self::numberField('cive_extension_refresh_interval_ms', 'Intervalo de sincronización del service worker (ms)', 900000),
                        ],
                    ],
                    [
                        'id' => 'openai',
                        'title' => 'OpenAI',
                        'description' => 'Credenciales utilizadas por los asistentes clínicos dentro de la extensión.',
                        'fields' => [
                            self::passwordField('cive_extension_openai_api_key', 'API Key'),
                            self::textField('cive_extension_openai_model', 'Modelo preferido', false, 'Ej: gpt-4o-mini'),
                        ],
                    ],
                    [
                        'id' => 'health_checks',
                        'title' => 'Health checks automáticos',
                        'description' => 'Define los endpoints críticos que serán monitorizados periódicamente.',
                        'fields' => [
                            self::checkboxField('cive_extension_health_enabled', 'Habilitar supervisión de endpoints'),
                            self::textareaField('cive_extension_health_endpoints', 'Listado de endpoints', 'Un endpoint por línea con el formato: Nombre | METODO | URL. El método es opcional (GET por defecto).'),
                            self::numberField('cive_extension_health_max_age_minutes', 'Considerar resultado como vigente (minutos)', 60),
                        ],
                    ],
                    [
                        'id' => 'runtime_flags',
                        'title' => 'Flags del agente',
                        'description' => 'Controla el comportamiento local/remoto de la extensión.',
                        'fields' => [
                            self::checkboxField('cive_extension_local_mode', 'Forzar modo local (desarrollo)'),
                            self::textField('cive_extension_extension_id_local', 'ID de extensión en modo local', false, 'Se utiliza cuando la bandera anterior está activa.'),
                            self::textField('cive_extension_extension_id_remote', 'ID de extensión en producción', false, 'Valor utilizado cuando local_mode está desactivado.'),
                            self::checkboxField('cive_extension_debug_api_logging', 'Mostrar solicitudes/respuestas de API en consola'),
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

                $payload[$key] = (string)$value;
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
