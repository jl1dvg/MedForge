<?php

return [
    'legacy' => [
        'chat_path' => env('WHATSAPP_LEGACY_CHAT_PATH', '/whatsapp/chat'),
        'templates_path' => env('WHATSAPP_LEGACY_TEMPLATES_PATH', '/whatsapp/templates'),
        'dashboard_path' => env('WHATSAPP_LEGACY_DASHBOARD_PATH', '/whatsapp/dashboard'),
        'flowmaker_path' => env('WHATSAPP_LEGACY_FLOWMAKER_PATH', '/whatsapp/flowmaker'),
        'api_conversations_path' => env('WHATSAPP_LEGACY_API_CONVERSATIONS_PATH', '/whatsapp/api/conversations'),
    ],
    'migration' => [
        'enabled' => (bool) env('WHATSAPP_LARAVEL_ENABLED', false),
        'fallback_to_legacy' => (bool) env('WHATSAPP_LARAVEL_FALLBACK_TO_LEGACY', true),
        'compare_with_legacy' => (bool) env('WHATSAPP_LARAVEL_COMPARE_WITH_LEGACY', true),
        'ui' => [
            'enabled' => (bool) env('WHATSAPP_LARAVEL_UI_ENABLED', false),
        ],
        'api' => [
            'read_enabled' => (bool) env('WHATSAPP_LARAVEL_API_READ_ENABLED', false),
            'write_enabled' => (bool) env('WHATSAPP_LARAVEL_API_WRITE_ENABLED', false),
            'webhook_enabled' => (bool) env('WHATSAPP_LARAVEL_WEBHOOK_ENABLED', false),
        ],
        'handoff' => [
            'requeue_schedule_enabled' => (bool) env('WHATSAPP_LARAVEL_HANDOFF_REQUEUE_SCHEDULED', false),
        ],
        'abandonment_monitor' => [
            'enabled' => (bool) env('WHATSAPP_LARAVEL_ABANDONMENT_MONITOR_ENABLED', false),
            'role_id' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_MONITOR_ROLE_ID', 4),
            'max_age_hours' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_MAX_AGE_HOURS', 72),
            'nudge_message' => (string) env(
                'WHATSAPP_LARAVEL_ABANDONMENT_NUDGE_MESSAGE',
                '😔 Parece que se interrumpió tu proceso. Si aún deseas continuar con tu cita, responde este mensaje y con gusto te ayudo.'
            ),
            'thresholds' => [
                'consentimiento_pendiente' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_CONSENT_MINUTES', 15),
                'esperando_cedula' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_IDENTIFIER_MINUTES', 15),
                'agenda' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_AGENDA_MINUTES', 12),
                'confirmacion' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_CONFIRMATION_MINUTES', 10),
            ],
            'followup_minutes' => [
                'low_intent' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_FOLLOWUP_LOW_INTENT_MINUTES', 10),
                'high_intent' => (int) env('WHATSAPP_LARAVEL_ABANDONMENT_FOLLOWUP_HIGH_INTENT_MINUTES', 10),
            ],
        ],
        'automation' => [
            'enabled' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_ENABLED', false),
            'compare_with_legacy' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_COMPARE_WITH_LEGACY', true),
            'fallback_to_legacy' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_FALLBACK_TO_LEGACY', true),
            'dry_run' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_DRY_RUN', true),
        ],
        'reminders' => [
            'enabled' => (bool) env('WHATSAPP_LARAVEL_REMINDERS_ENABLED', false),
            'consultation_template_code' => (string) env('WHATSAPP_LARAVEL_REMINDER_CONSULTATION_TEMPLATE', 'confirmacion_cita_med_v2'),
            'image_template_code' => (string) env('WHATSAPP_LARAVEL_REMINDER_IMAGE_TEMPLATE', 'confirmacion_cita_med_v2'),
            'timezone' => (string) env('WHATSAPP_LARAVEL_REMINDER_TIMEZONE', 'America/Guayaquil'),
            'windows' => [
                '24h' => (int) env('WHATSAPP_LARAVEL_REMINDER_WINDOW_24H_MINUTES', 1440),
                '2h' => (int) env('WHATSAPP_LARAVEL_REMINDER_WINDOW_2H_MINUTES', 120),
            ],
            'window_tolerance_minutes' => (int) env('WHATSAPP_LARAVEL_REMINDER_WINDOW_TOLERANCE_MINUTES', 15),
            'agent_role_id' => (int) env('WHATSAPP_LARAVEL_REMINDER_AGENT_ROLE_ID', 4),
        ],
    ],
    'audit' => [
        'enabled' => (bool) env('WHATSAPP_AUDIT_ENABLED', false),
    ],
    'transport' => [
        'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'timeout' => (int) env('WHATSAPP_GRAPH_TIMEOUT', 15),
        'dry_run' => (bool) env('WHATSAPP_LARAVEL_TRANSPORT_DRY_RUN', false),
    ],
    'media' => [
        'image' => [
            'max_kb' => 5 * 1024,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'video' => [
            'max_kb' => 16 * 1024,
            'mime_types' => ['video/mp4', 'video/3gpp'],
        ],
        'audio' => [
            'max_kb' => 16 * 1024,
            'mime_types' => ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/amr', 'audio/webm', 'application/ogg'],
        ],
        'document' => [
            'max_kb' => 100 * 1024,
            'mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
            ],
        ],
    ],
];
