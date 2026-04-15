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
        'automation' => [
            'enabled' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_ENABLED', false),
            'compare_with_legacy' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_COMPARE_WITH_LEGACY', true),
            'fallback_to_legacy' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_FALLBACK_TO_LEGACY', true),
            'dry_run' => (bool) env('WHATSAPP_LARAVEL_AUTOMATION_DRY_RUN', true),
        ],
    ],
    'transport' => [
        'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'timeout' => (int) env('WHATSAPP_GRAPH_TIMEOUT', 15),
        'dry_run' => (bool) env('WHATSAPP_LARAVEL_TRANSPORT_DRY_RUN', false),
    ],
];
