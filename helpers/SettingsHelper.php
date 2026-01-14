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
                    [
                        'id' => 'data_retention',
                        'title' => 'Retención y limpieza de archivos',
                        'description' => 'Define cuánto tiempo conservar archivos subidos y si la purga será automática.',
                        'fields' => [
                            self::numberField(
                                'general_file_retention_days',
                                'Días para conservar adjuntos',
                                365,
                                'Usa 0 para conservar indefinidamente. Aplica a documentos clínicos y administrativos.'
                            ),
                            self::checkboxField(
                                'general_file_auto_purge',
                                'Purgar adjuntos automáticamente',
                                false,
                                'Si está activo, se eliminarán archivos vencidos según la política de días definida.'
                            ),
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
                        'id' => 'smtp_advanced',
                        'title' => 'SMTP avanzado',
                        'description' => 'Ajustes de compatibilidad y respuesta para servidores exigentes.',
                        'fields' => [
                            self::numberField('smtp_timeout_seconds', 'Timeout de conexión (segundos)', 15),
                            self::checkboxField('smtp_debug_enabled', 'Registrar salida SMTP para administradores'),
                            self::checkboxField('smtp_allow_self_signed', 'Permitir certificados autofirmados'),
                            self::emailField('email_reply_to_address', 'Dirección Reply-To'),
                            self::textField('email_reply_to_name', 'Nombre Reply-To'),
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
                            self::emailField('email_from_address_notifications', 'Remitente para notificaciones'),
                            self::emailField('email_from_address_billing', 'Remitente para facturación'),
                        ],
                    ],
                    [
                        'id' => 'email_policy',
                        'title' => 'Políticas y auditoría',
                        'description' => 'Define controles de seguridad, retención y copia oculta.',
                        'fields' => [
                            self::emailField('email_bcc_audit_address', 'Copia oculta para auditoría'),
                            self::checkboxField('email_store_sent_log', 'Almacenar log de correos enviados', true),
                            self::numberField('email_sent_log_ttl_days', 'Retención de log (días)', 180),
                            self::numberField('email_rate_limit_per_minute', 'Límite por minuto', 60),
                            self::numberField('email_max_attempts', 'Reintentos máximos', 5),
                            self::numberField('email_retry_backoff_seconds', 'Espera entre reintentos (segundos)', 60),
                            self::checkboxField('email_circuit_breaker_enabled', 'Circuit breaker SMTP habilitado', false),
                            self::numberField('email_circuit_breaker_failures', 'Fallas para abrir circuito', 10),
                            self::numberField('email_circuit_breaker_cooldown_minutes', 'Cooldown del circuito (minutos)', 15),
                            self::checkboxField('email_bcc_audit_enabled', 'Forzar copia oculta a auditoría'),
                            self::textareaField('email_blocklist_domains', 'Dominios bloqueados', 'Uno por línea.'),
                        ],
                    ],
                    [
                        'id' => 'email_templates',
                        'title' => 'Plantillas editables',
                        'description' => 'Personaliza los textos utilizados en el NotificationMailer.',
                        'fields' => [
                            array_merge(
                                self::textField('email_template_patient_update_subject', 'Asunto de actualización al paciente'),
                                ['default' => 'Actualización de {{tipo}} #{{id}} · {{descripcion}}']
                            ),
                            self::textareaField(
                                'email_template_patient_update_body',
                                'Cuerpo HTML de actualización',
                                'Soporta variables: {{tipo}}, {{id}}, {{descripcion}}, {{enlace}}.',
                                '<p>Hemos actualizado {{tipo}} #{{id}}.</p><p>{{descripcion}}</p>'
                            ),
                        ],
                    ],
                    [
                        'id' => 'mailbox_guardrails',
                        'title' => 'Notificaciones desde Mailbox',
                        'description' => 'Evita envíos accidentales y define orígenes permitidos.',
                        'fields' => [
                            self::checkboxField('mailbox_notify_patient_enabled', 'Permitir notificar paciente', true),
                            self::checkboxField('mailbox_notify_patient_require_tag', 'Requerir tag [PACIENTE] u origen explícito', true),
                            self::textareaField('mailbox_notify_patient_allowed_sources', 'Orígenes permitidos', 'Una fuente por línea. Ej: solicitud, examen'),
                            self::checkboxField('mailbox_notify_patient_default', 'Notificar paciente por defecto'),
                        ],
                    ],
                ],
            ],
            'system_observability' => [
                'title' => 'Observabilidad',
                'icon' => 'fa-solid fa-magnifying-glass-chart',
                'description' => 'Controles para logging, trazas HTTP y monitoreo APM.',
                'groups' => [
                    [
                        'id' => 'logging',
                        'title' => 'Logs y rastreo',
                        'description' => 'Define el nivel de detalle y los canales disponibles.',
                        'fields' => [
                            self::selectField('observability_log_level', 'Nivel de log', [
                                'debug' => 'Debug',
                                'info' => 'Info',
                                'warn' => 'Warn',
                                'error' => 'Error',
                            ], 'info'),
                            self::selectField('observability_log_channel', 'Canal de logs', [
                                'file' => 'Archivo',
                                'db' => 'Base de datos',
                                'syslog' => 'Syslog',
                            ], 'file'),
                            self::checkboxField('observability_http_trace_enabled', 'Registrar trazas de HTTP saliente'),
                            self::selectField('observability_apm_provider', 'Proveedor APM', [
                                '' => 'Desactivado',
                                'sentry' => 'Sentry',
                                'newrelic' => 'New Relic / genérico',
                            ]),
                            self::textField('observability_apm_dsn', 'DSN o endpoint APM'),
                            self::checkboxField('observability_notify_on_critical_errors', 'Enviar alerta en errores críticos', true),
                            self::textareaField('observability_critical_recipients', 'Destinatarios críticos', 'Correos separados por coma.'),
                        ],
                    ],
                ],
            ],
            'security_access' => [
                'title' => 'Seguridad y acceso',
                'icon' => 'fa-solid fa-shield-halved',
                'description' => 'Controla sesiones, MFA y límites de acceso.',
                'groups' => [
                    [
                        'id' => 'sessions',
                        'title' => 'Sesiones',
                        'description' => 'Tiempo máximo de inactividad y expiración absoluta.',
                        'fields' => [
                            self::numberField('session_idle_timeout_minutes', 'Timeout por inactividad (minutos)', 30),
                            self::numberField('session_absolute_timeout_hours', 'Timeout absoluto (horas)', 24),
                            self::checkboxField('csrf_strict_mode', 'CSRF en modo estricto'),
                        ],
                    ],
                    [
                        'id' => 'auth_controls',
                        'title' => 'Autenticación y red',
                        'description' => 'Refuerza el acceso con MFA, IPs permitidas y rate limits.',
                        'fields' => [
                            self::textareaField('mfa_enabled_roles', 'Roles con MFA requerido', 'Una lista de roles separados por coma o línea.'),
                            self::textareaField('admin_ip_whitelist', 'Whitelist de IP/CIDR', 'Una por línea, formato CIDR admitido.'),
                            self::numberField('login_max_attempts', 'Intentos máximos de login', 5),
                            self::numberField('login_lockout_minutes', 'Bloqueo tras exceder intentos (minutos)', 15),
                        ],
                    ],
                ],
            ],
            'audit' => [
                'title' => 'Auditoría',
                'icon' => 'fa-solid fa-clipboard-check',
                'description' => 'Registro de cambios y políticas de retención.',
                'groups' => [
                    [
                        'id' => 'audit_controls',
                        'title' => 'Parámetros de auditoría',
                        'description' => 'Activa el tracking y selecciona módulos cubiertos.',
                        'fields' => [
                            self::checkboxField('audit_enabled', 'Habilitar auditoría'),
                            self::numberField('audit_ttl_days', 'Retención de auditoría (días)', 365),
                            self::textareaField('audit_modules', 'Módulos auditados', 'billing, identidad, crm, whatsapp, mailbox'),
                            self::checkboxField('audit_mask_pii', 'Enmascarar PII en logs y exportes', true),
                        ],
                    ],
                ],
            ],
            'scheduler' => [
                'title' => 'Scheduler',
                'icon' => 'fa-solid fa-clock-rotate-left',
                'description' => 'Configura la ejecución programada y sus jobs dependientes.',
                'groups' => [
                    [
                        'id' => 'core',
                        'title' => 'Núcleo del scheduler',
                        'description' => 'Frecuencias base y protección contra ejecuciones dobles.',
                        'fields' => [
                            self::checkboxField('scheduler_enabled', 'Habilitar scheduler interno', true),
                            self::selectField('scheduler_timezone', 'Zona horaria', $timezones, 'America/Guayaquil'),
                            self::numberField('scheduler_tick_minutes', 'Tick del dispatcher (minutos)', 5),
                            self::numberField('scheduler_max_runtime_seconds', 'Tiempo máximo por ciclo (segundos)', 55),
                            self::numberField('scheduler_lock_ttl_seconds', 'TTL del lock (segundos)', 120),
                            self::passwordField('scheduler_endpoint_secret', 'Secreto del endpoint de cron'),
                            self::selectField('scheduler_overlap_policy', 'Política de solapamiento', [
                                'skip' => 'Saltar si hay una ejecución activa',
                                'queue' => 'Encolar hasta finalizar la ejecución previa',
                            ], 'skip'),
                            self::checkboxField('scheduler_run_missed_jobs', 'Reintentar jobs omitidos', true),
                        ],
                    ],
                    [
                        'id' => 'jobs',
                        'title' => 'Jobs programados',
                        'description' => 'Activa cada job y define su frecuencia.',
                        'fields' => [
                            self::checkboxField('job_mail_queue_enabled', 'Procesar cola de correo', true),
                            self::numberField('job_mail_queue_every_minutes', 'Frecuencia cola de correo (minutos)', 1),
                            self::checkboxField('job_whatsapp_queue_enabled', 'Procesar cola de WhatsApp', true),
                            self::numberField('job_whatsapp_queue_every_minutes', 'Frecuencia cola WhatsApp (minutos)', 1),
                            self::checkboxField('job_file_purge_enabled', 'Purgar archivos vencidos', true),
                            self::numberField('job_file_purge_every_hours', 'Frecuencia purga de archivos (horas)', 24),
                            self::checkboxField('job_mailbox_autoarchive_enabled', 'Auto-archivar mailbox', true),
                            self::numberField('job_mailbox_autoarchive_every_hours', 'Frecuencia auto-archivo (horas)', 24),
                            self::checkboxField('job_crm_sla_alerts_enabled', 'Alertas SLA CRM', true),
                            self::numberField('job_crm_sla_alerts_every_minutes', 'Frecuencia SLA (minutos)', 10),
                            self::checkboxField('job_healthchecks_enabled', 'Healthchecks automáticos', true),
                            self::numberField('job_healthchecks_every_minutes', 'Frecuencia healthchecks (minutos)', 30),
                            self::checkboxField('job_backups_enabled', 'Respaldos programados'),
                            self::textField('job_backups_cron', 'Cron de respaldos (formato crontab)'),
                        ],
                    ],
                    [
                        'id' => 'failure_policies',
                        'title' => 'Manejo de fallas',
                        'description' => 'Cómo reaccionar ante errores recurrentes en jobs.',
                        'fields' => [
                            self::checkboxField('scheduler_failure_alert_enabled', 'Alertar fallas de scheduler'),
                            self::textareaField('scheduler_failure_alert_recipients', 'Destinatarios de alertas', 'Correos separados por coma.'),
                            self::numberField('scheduler_failure_backoff_minutes', 'Backoff ante fallas (minutos)', 10),
                            self::numberField('scheduler_max_failures_before_disable', 'Máx. fallas antes de pausar', 20),
                            self::numberField('scheduler_failure_notify_threshold', 'Alertar tras N fallas consecutivas', 3),
                            self::numberField('scheduler_log_retention_days', 'Retención de logs de scheduler (días)', 30),
                        ],
                    ],
                ],
            ],
            'delivery_queue' => [
                'title' => 'Cola de envíos',
                'icon' => 'fa-solid fa-paper-plane',
                'description' => 'Controla el batching y la concurrencia para correos y WhatsApp.',
                'groups' => [
                    [
                        'id' => 'queue_core',
                        'title' => 'Parámetros generales',
                        'description' => 'Ajusta los umbrales para el motor de colas.',
                        'fields' => [
                            self::checkboxField('queue_enabled', 'Habilitar motor de colas', true),
                            self::numberField('queue_batch_size', 'Tamaño de lote', 20),
                            self::numberField('queue_interval_seconds', 'Intervalo entre lotes (segundos)', 30),
                            self::numberField('queue_max_concurrency', 'Máxima concurrencia', 5),
                            self::numberField('queue_fail_after_attempts', 'Fallas antes de marcar DLQ', 5),
                            self::checkboxField('queue_dlq_enabled', 'Habilitar Dead Letter Queue', true),
                            self::numberField('queue_alert_on_backlog', 'Umbral de alerta por backlog', 200),
                        ],
                    ],
                ],
            ],
            'locations' => [
                'title' => 'Sedes y multi-tenant',
                'icon' => 'fa-solid fa-building',
                'description' => 'Administra sedes y parámetros específicos por ubicación.',
                'groups' => [
                    [
                        'id' => 'locations_core',
                        'title' => 'Listado de sedes',
                        'description' => 'Define sedes y el comportamiento por defecto.',
                        'fields' => [
                            self::checkboxField('locations_enabled', 'Habilitar multi-sede'),
                            self::textareaField('locations_list', 'Sedes', 'Formato JSON o id|nombre|color|logo|timezone por línea.'),
                            self::textField('default_location_id', 'Sede predeterminada'),
                            self::checkboxField('location_scoped_settings', 'Aislar settings por sede'),
                        ],
                    ],
                ],
            ],
            'privacy_exports' => [
                'title' => 'Privacidad y exportes',
                'icon' => 'fa-solid fa-user-shield',
                'description' => 'Reducción de exposición de datos en exportaciones y staging.',
                'groups' => [
                    [
                        'id' => 'exports',
                        'title' => 'Políticas de exportación',
                        'description' => 'Controla marcas de agua y anonimización.',
                        'fields' => [
                            self::checkboxField('export_watermark_enabled', 'Agregar watermark en exportes'),
                            self::checkboxField('export_mask_sensitive_fields', 'Enmascarar campos sensibles'),
                            self::checkboxField('anonymization_mode_enabled', 'Modo anonimizado para staging'),
                            self::checkboxField('attachments_public_access_enabled', 'Permitir acceso público a adjuntos'),
                        ],
                    ],
                ],
            ],
            'webhooks' => [
                'title' => 'Webhooks',
                'icon' => 'fa-solid fa-plug',
                'description' => 'Eventos generales para integrarse con sistemas externos.',
                'groups' => [
                    [
                        'id' => 'webhooks_core',
                        'title' => 'Configuración general',
                        'description' => 'Firma compartida y listado de eventos.',
                        'fields' => [
                            self::checkboxField('webhooks_enabled', 'Habilitar webhooks'),
                            self::passwordField('webhooks_secret', 'Secreto para firma'),
                            self::textareaField('webhooks_events', 'Eventos y URLs', 'Formato: event|url|retries por línea.'),
                            self::numberField('webhooks_default_retries', 'Reintentos por defecto', 3),
                        ],
                    ],
                ],
            ],
            'feature_flags' => [
                'title' => 'Feature flags',
                'icon' => 'fa-solid fa-toggle-on',
                'description' => 'Toggles por módulo para despliegues seguros.',
                'groups' => [
                    [
                        'id' => 'toggles',
                        'title' => 'Bandera de funcionalidades',
                        'description' => 'Enciende o apaga módulos sin redeploy.',
                        'fields' => [
                            self::checkboxField('enable_mailbox_notify_patient', 'Habilitar notificación desde mailbox', true),
                            self::checkboxField('enable_crm_sla', 'Habilitar alertas SLA en CRM', true),
                            self::checkboxField('enable_ai_traceability', 'Activar trazabilidad de IA', true),
                            self::textareaField('flags_by_role', 'Flags por rol', 'Formato: rol|flag1,flag2'),
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
                            self::textareaField(
                                'crm_whatsapp_stage_templates',
                                'Plantillas de WhatsApp por etapa',
                                'Una regla por línea: Etapa | nombre_de_plantilla | idioma | componentes (JSON opcional).'
                                . " Ejemplo: Evaluación médica realizada | prequirurgico_confirmado | es | {\"components\":[{\"type\":\"body\",\"parameters\":[{\"type\":\"text\",\"text\":\"{{nombre}}\"}]}]}"
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
                            self::textareaField(
                                'crm_pipeline_sla_rules',
                                'SLA por etapa',
                                'Define una regla por línea con el formato: Etapa | minutos | alerta (email/sms). Ej: Seguimiento | 1440 | email',
                                'Se utiliza para disparar avisos cuando una tarjeta supere el tiempo configurado en la columna.'
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
                    [
                        'id' => 'quiet_hours',
                        'title' => 'Ventanas de silencio y alertas críticas',
                        'description' => 'Establece horarios sin notificaciones y los destinatarios de alertas críticas.',
                        'fields' => [
                            self::checkboxField(
                                'notifications_quiet_hours_enabled',
                                'Activar ventana de silencio global'
                            ),
                            self::textField(
                                'notifications_quiet_hours_start',
                                'Inicio silencio (HH:MM)',
                                false,
                                'Formato 24h. Ej: 22:00'
                            ),
                            self::textField(
                                'notifications_quiet_hours_end',
                                'Fin silencio (HH:MM)',
                                false,
                                'Formato 24h. Ej: 06:00'
                            ),
                            self::textareaField(
                                'notifications_quiet_hours_exceptions',
                                'Fechas excepcionales',
                                'Una fecha por línea en formato YYYY-MM-DD para desactivar el silencio.'
                            ),
                            self::textareaField(
                                'notifications_critical_recipients',
                                'Destinatarios de alertas críticas',
                                'Correos separados por coma que recibirán incidencias graves incluso en silencio.'
                            ),
                        ],
                    ],
                ],
            ],
            'turnero' => [
                'title' => 'Turneros',
                'icon' => 'fa-solid fa-display',
                'description' => 'Preferencias unificadas para el panel de turnos y su experiencia de audio.',
                'groups' => [
                    [
                        'id' => 'pantalla',
                        'title' => 'Pantalla y disposición',
                        'description' => 'Controla cómo se muestra el turnero unificado.',
                        'fields' => [
                            self::checkboxField(
                                'turnero_fullscreen_default',
                                'Intentar iniciar en pantalla completa',
                                false,
                                'El navegador puede impedir la pantalla completa sin interacción previa. Siempre habrá un botón para activarla.'
                            ),
                            self::numberField(
                                'turnero_refresh_interval_seconds',
                                'Frecuencia de refresco (segundos)',
                                30,
                                'Controla cada cuánto se sincroniza la lista de turnos. Valores menores aumentan el tráfico.'
                            ),
                            self::textareaField(
                                'turnero_profiles_by_location',
                                'Perfiles por sede',
                                'Una línea por sede en formato: Sede | logo.png | #color_principal | layout',
                                'Permite personalizar logo, colores y layout según la ubicación.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'audio',
                        'title' => 'Audio y locución',
                        'description' => 'Centraliza los sonidos y la lectura de nombres del turnero.',
                        'fields' => [
                            self::checkboxField(
                                'turnero_sound_enabled',
                                'Habilitar sonidos',
                                true
                            ),
                            self::selectField(
                                'turnero_bell_style',
                                'Tipo de timbre',
                                [
                                    'classic' => 'Clásico (triple tono)',
                                    'soft' => 'Suave / notificación breve',
                                    'bright' => 'Agudo / alerta',
                                ],
                                'classic'
                            ),
                            self::textField(
                                'turnero_sound_volume',
                                'Volumen de alertas (0 a 1)',
                                false,
                                'Ejemplo: 0.7 para un volumen moderado.'
                            ),
                            self::checkboxField(
                                'turnero_quiet_enabled',
                                'Activar horario silencioso'
                            ),
                            self::textField(
                                'turnero_quiet_start',
                                'Inicio silencio (HH:MM)',
                                false,
                                'Formato 24h, ejemplo 22:00'
                            ),
                            self::textField(
                                'turnero_quiet_end',
                                'Fin silencio (HH:MM)',
                                false,
                                'Formato 24h, ejemplo 06:00'
                            ),
                            self::checkboxField(
                                'turnero_tts_enabled',
                                'Leer el nombre al llamar',
                                true
                            ),
                            self::checkboxField(
                                'turnero_tts_repeat',
                                'Repetir la locución'
                            ),
                            self::checkboxField(
                                'turnero_speak_on_new',
                                'Leer al crear un nuevo turno',
                                true
                            ),
                            self::textField(
                                'turnero_voice_preference',
                                'Voz preferida para lectura',
                                false,
                                'Nombre exacto de la voz TTS del navegador. Déjalo vacío para seleccionar automáticamente una voz en español.'
                            ),
                        ],
                    ],
                ],
            ],
            'billing' => [
                'title' => 'Facturación',
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'description' => 'Centraliza reglas de precios y exclusiones aplicadas durante el flujo de facturación.',
                'groups' => [
                    [
                        'id' => 'rules_by_code',
                        'title' => 'Reglas por código',
                        'description' => 'Ajustes específicos para códigos de procedimiento o insumo.',
                        'fields' => [
                            self::billingRulesField(
                                'billing_rules_code',
                                'Listado de reglas por código',
                                'Prioridad más alta. Se aplica a procedimientos, insumos, derechos y anestesia con el mismo código.',
                                'code'
                            ),
                        ],
                    ],
                    [
                        'id' => 'rules_by_affiliation',
                        'title' => 'Reglas por afiliación',
                        'description' => 'Condiciones cuando el paciente pertenece a una afiliación específica.',
                        'fields' => [
                            self::billingRulesField(
                                'billing_rules_affiliation',
                                'Listado de reglas por afiliación',
                                'Se evalúan si no existe coincidencia exacta por código.',
                                'affiliation'
                            ),
                        ],
                    ],
                    [
                        'id' => 'rules_by_age',
                        'title' => 'Reglas por edad o rango etario',
                        'description' => 'Define tarifas, descuentos o exclusiones según la edad del paciente.',
                        'fields' => [
                            self::billingRulesField(
                                'billing_rules_age',
                                'Listado de reglas por edad',
                                'Se aplican cuando no hay regla por código ni por afiliación.',
                                'age'
                            ),
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
                            self::textareaField(
                                'mailbox_default_filters',
                                'Filtros predefinidos',
                                'Una regla por línea en formato: fuente | estado | etiqueta',
                                'Se aplican al cargar el buzón para destacar las fuentes más relevantes.'
                            ),
                            self::textareaField(
                                'mailbox_autoarchive_rules',
                                'Reglas automáticas de archivado',
                                'Una regla por línea en formato: fuente | condición | días_para_archivar',
                                'Ejemplo: whatsapp | sin respuesta | 30. Las reglas se aplican en tareas programadas.'
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
                            self::numberField('billing_informes_iess_table_page_length', 'Pacientes por página', 25, 'Cantidad de filas visibles por defecto en el consolidado.'),
                            self::selectField(
                                'billing_informes_iess_table_order',
                                'Orden predeterminado de la tabla',
                                [
                                    'fecha_ingreso_desc' => 'Fecha de ingreso (más recientes primero)',
                                    'fecha_ingreso_asc' => 'Fecha de ingreso (más antiguos primero)',
                                    'nombre_asc' => 'Nombre (A-Z)',
                                    'nombre_desc' => 'Nombre (Z-A)',
                                    'monto_desc' => 'Monto (mayor a menor)',
                                    'monto_asc' => 'Monto (menor a mayor)',
                                ],
                                'fecha_ingreso_desc'
                            ),
                            self::textareaField(
                                'billing_informes_code_mapping',
                                'Tabla de mapeo códigos internos → externos',
                                'Una línea por regla: codigo_interno | codigo_aseguradora | descripción'
                            ),
                            self::numberField(
                                'billing_informes_rounding_tolerance',
                                'Tolerancia de redondeo en exportes',
                                0,
                                'Cantidad máxima permitida para ajustar decimales en conciliaciones.'
                            ),
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
                            self::numberField('billing_informes_isspol_table_page_length', 'Pacientes por página', 25, 'Cantidad de filas visibles por defecto en el consolidado.'),
                            self::selectField(
                                'billing_informes_isspol_table_order',
                                'Orden predeterminado de la tabla',
                                [
                                    'fecha_ingreso_desc' => 'Fecha de ingreso (más recientes primero)',
                                    'fecha_ingreso_asc' => 'Fecha de ingreso (más antiguos primero)',
                                    'nombre_asc' => 'Nombre (A-Z)',
                                    'nombre_desc' => 'Nombre (Z-A)',
                                    'monto_desc' => 'Monto (mayor a menor)',
                                    'monto_asc' => 'Monto (menor a mayor)',
                                ],
                                'fecha_ingreso_desc'
                            ),
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
                            self::numberField('billing_informes_issfa_table_page_length', 'Pacientes por página', 25, 'Cantidad de filas visibles por defecto en el consolidado.'),
                            self::selectField(
                                'billing_informes_issfa_table_order',
                                'Orden predeterminado de la tabla',
                                [
                                    'fecha_ingreso_desc' => 'Fecha de ingreso (más recientes primero)',
                                    'fecha_ingreso_asc' => 'Fecha de ingreso (más antiguos primero)',
                                    'nombre_asc' => 'Nombre (A-Z)',
                                    'nombre_desc' => 'Nombre (Z-A)',
                                    'monto_desc' => 'Monto (mayor a menor)',
                                    'monto_asc' => 'Monto (menor a mayor)',
                                ],
                                'fecha_ingreso_desc'
                            ),
                        ],
                    ],
                    [
                        'id' => 'msp',
                        'title' => 'MSP',
                        'description' => 'Configura la vista de /informes/msp.',
                        'fields' => [
                            array_merge(self::textField('billing_informes_msp_title', 'Título del informe'), ['default' => 'Informe MSP']),
                            array_merge(self::textField('billing_informes_msp_base_path', 'Ruta base'), ['default' => '/informes/msp']),
                            array_merge(self::textareaField('billing_informes_msp_afiliaciones', 'Afiliaciones permitidas', 'Una afiliación por línea.'), ['default' => 'msp']),
                            array_merge(self::textareaField(
                                'billing_informes_msp_excel_buttons',
                                'Botones de descarga',
                                'Una línea por botón usando el formato GRUPO|Etiqueta|Clase CSS|Icono opcional.'
                            ), [
                                'default' => 'MSP|Descargar Excel|btn btn-success btn-lg me-2|fa fa-file-excel-o',
                            ]),
                            array_merge(self::textField('billing_informes_msp_scrape_label', 'Etiqueta del botón de scraping'), ['default' => '📋 Obtener código de derivación']),
                            array_merge(self::textField('billing_informes_msp_consolidado_title', 'Título del consolidado'), ['default' => 'Consolidado mensual de pacientes MSP']),
                            self::checkboxField('billing_informes_msp_apellido_filter', 'Habilitar filtro por apellido', true),
                            self::numberField('billing_informes_msp_table_page_length', 'Pacientes por página', 25, 'Cantidad de filas visibles por defecto en el consolidado.'),
                            self::selectField(
                                'billing_informes_msp_table_order',
                                'Orden predeterminado de la tabla',
                                [
                                    'fecha_ingreso_desc' => 'Fecha de ingreso (más recientes primero)',
                                    'fecha_ingreso_asc' => 'Fecha de ingreso (más antiguos primero)',
                                    'nombre_asc' => 'Nombre (A-Z)',
                                    'nombre_desc' => 'Nombre (Z-A)',
                                    'monto_desc' => 'Monto (mayor a menor)',
                                    'monto_asc' => 'Monto (menor a mayor)',
                                ],
                                'fecha_ingreso_desc'
                            ),
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
                    [
                        'id' => 'delivery_controls',
                        'title' => 'Control de envío y límites',
                        'description' => 'Configura protección de reputación y rendimiento en el envío de mensajes.',
                        'fields' => [
                            self::numberField(
                                'whatsapp_hourly_limit',
                                'Límite de mensajes por hora',
                                200,
                                '0 desactiva el límite y delega el control al proveedor.'
                            ),
                            self::numberField(
                                'whatsapp_attachment_max_mb',
                                'Tamaño máximo de adjuntos (MB)',
                                15,
                                'Bloquea envíos que superen este tamaño para prevenir rechazos.'
                            ),
                            self::numberField(
                                'whatsapp_retry_attempts',
                                'Reintentos ante fallo',
                                3,
                                'Cantidad de reintentos antes de marcar el mensaje como fallido.'
                            ),
                            self::numberField(
                                'whatsapp_retry_backoff_seconds',
                                'Intervalo entre reintentos (segundos)',
                                30,
                                'Define el tiempo de espera entre cada reintento.'
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
                    [
                        'id' => 'governance',
                        'title' => 'Gobernanza y límites de uso',
                        'description' => 'Controla trazabilidad, retención y cuotas de llamadas a IA por rol o usuario.',
                        'fields' => [
                            self::checkboxField(
                                'ai_traceability_enabled',
                                'Guardar prompts y respuestas para auditoría',
                                false,
                                'Si está activo, se registran las interacciones en una tabla de auditoría.'
                            ),
                            self::numberField(
                                'ai_audit_ttl_days',
                                'Días para conservar trazas de IA',
                                90,
                                'Usa 0 para conservar indefinidamente.'
                            ),
                            self::numberField(
                                'ai_daily_limit_per_user',
                                'Límite diario por usuario',
                                50,
                                '0 desactiva el límite diario individual.'
                            ),
                            self::numberField(
                                'ai_daily_limit_per_role',
                                'Límite diario por rol',
                                0,
                                'Introduce un número mayor a 0 para aplicar cuotas compartidas por rol.'
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
                            self::numberField(
                                'identity_verification_revalidation_days',
                                'Días para revalidación temprana',
                                300,
                                'Genera una nueva verificación antes de que expire la vigencia principal.'
                            ),
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
                    [
                        'id' => 'webhooks',
                        'title' => 'Webhooks de eventos',
                        'description' => 'Notifica a sistemas externos cuando se crea, renueva o expira una certificación.',
                        'fields' => [
                            self::textField(
                                'identity_verification_webhook_url',
                                'URL del webhook',
                                false,
                                'Se enviarán eventos de creación, revalidación y expiración con firma compartida.'
                            ),
                            self::passwordField(
                                'identity_verification_webhook_secret',
                                'Secreto para firma HMAC'
                            ),
                        ],
                    ],
                ],
            ],
            'solicitudes' => [
                'title' => 'Solicitudes',
                'icon' => 'fa-solid fa-notes-medical',
                'description' => 'Define los umbrales SLA, configuración del turnero y formatos de reportes.',
                'groups' => [
                    [
                        'id' => 'sla',
                        'title' => 'SLA y alertas',
                        'description' => 'Controla los umbrales utilizados para advertencias y prioridades automáticas.',
                        'fields' => [
                            self::numberField('solicitudes.sla.warning_hours', 'SLA advertencia (horas)', 72),
                            self::numberField('solicitudes.sla.critical_hours', 'SLA crítico (horas)', 24),
                            self::textareaField(
                                'solicitudes.sla.labels',
                                'Etiquetas SLA (JSON)',
                                'Opcional. Define labels/íconos por estado SLA.',
                                json_encode([
                                    'en_rango' => ['color' => 'success', 'label' => 'SLA en rango', 'icon' => 'mdi-check-circle-outline'],
                                    'advertencia' => ['color' => 'warning', 'label' => 'SLA 72h', 'icon' => 'mdi-timer-sand'],
                                    'critico' => ['color' => 'danger', 'label' => 'SLA crítico', 'icon' => 'mdi-alert-octagon'],
                                    'vencido' => ['color' => 'dark', 'label' => 'SLA vencido', 'icon' => 'mdi-alert'],
                                    'sin_fecha' => ['color' => 'secondary', 'label' => 'SLA sin fecha', 'icon' => 'mdi-calendar-remove'],
                                    'cerrado' => ['color' => 'secondary', 'label' => 'SLA cerrado', 'icon' => 'mdi-lock-outline'],
                                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                            ),
                        ],
                    ],
                    [
                        'id' => 'turnero',
                        'title' => 'Turnero',
                        'description' => 'Ajusta el estado por defecto y el intervalo de refresco.',
                        'fields' => [
                            self::textField(
                                'solicitudes.turnero.default_state',
                                'Estado por defecto',
                                false,
                                'Ej: Llamado.'
                            ),
                            self::numberField(
                                'solicitudes.turnero.refresh_ms',
                                'Refresco automático (ms)',
                                30000,
                                'Intervalo de actualización del turnero.'
                            ),
                        ],
                    ],
                    [
                        'id' => 'reportes',
                        'title' => 'Reportes',
                        'description' => 'Habilita formatos disponibles y quick reports.',
                        'fields' => [
                            self::checkboxGroupField(
                                'solicitudes.report.formats',
                                'Formatos habilitados',
                                [
                                    'pdf' => 'PDF',
                                    'excel' => 'Excel (.xlsx)',
                                ],
                                ['pdf', 'excel']
                            ),
                            self::textareaField(
                                'solicitudes.report.quick_metrics',
                                'Quick reports (JSON)',
                                'Formato JSON con label y estado/sla_status por clave.',
                                json_encode([
                                    'anestesia' => [
                                        'label' => 'Pendientes de apto de anestesia',
                                        'estado' => 'apto-anestesia',
                                    ],
                                    'cobertura' => [
                                        'label' => 'Pendientes de cobertura',
                                        'estado' => 'revision-codigos',
                                    ],
                                    'sla-vencido' => [
                                        'label' => 'SLA vencido',
                                        'sla_status' => 'vencido',
                                    ],
                                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                            ),
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
                        'id' => 'environment',
                        'title' => 'Entorno',
                        'description' => 'Selecciona el entorno activo para las peticiones de la extensión.',
                        'fields' => [
                            self::selectField(
                                'cive_extension_environment',
                                'Entorno activo',
                                [
                                    'production' => 'Producción',
                                    'sandbox' => 'Sandbox / staging',
                                ],
                                'production'
                            ),
                        ],
                    ],
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
                        'id' => 'headers',
                        'title' => 'Encabezados personalizados',
                        'description' => 'Define headers adicionales por tenant o cliente para enriquecer las peticiones.',
                        'fields' => [
                            self::textareaField(
                                'cive_extension_custom_headers',
                                'Headers por tenant',
                                'Formato JSON: {"tenantA":{"X-Tenant":"A"},"tenantB":{"X-Tenant":"B","X-Region":"EU"}}',
                                'Se aplican en cada solicitud saliente desde la extensión.'
                            ),
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
                $altKey = str_replace('.', '_', $key);
                $raw = $input[$key] ?? $input[$altKey] ?? null;

                if (($field['sensitive'] ?? false) && ($raw === null || $raw === '')) {
                    continue;
                }

                if ($field['type'] === 'checkbox') {
                    $value = $raw ? '1' : '0';
                } elseif ($field['type'] === 'checkbox_group') {
                    $values = is_array($raw) ? $raw : [];
                    $values = array_values(array_filter(array_map(static function ($item) {
                        $clean = trim((string) $item);
                        return $clean === '' ? null : $clean;
                    }, $values)));
                    $value = json_encode($values, JSON_UNESCAPED_UNICODE);
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

    private static function checkboxGroupField(
        string $key,
        string $label,
        array $options,
        array $default = [],
        ?string $help = null
    ): array {
        return [
            'type' => 'checkbox_group',
            'key' => $key,
            'label' => $label,
            'options' => $options,
            'default' => json_encode(array_values($default), JSON_UNESCAPED_UNICODE),
            'help' => $help,
        ];
    }

    private static function billingRulesField(string $key, string $label, string $description, string $ruleType): array
    {
        return [
            'type' => 'billing_rules',
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'rule_type' => $ruleType,
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
