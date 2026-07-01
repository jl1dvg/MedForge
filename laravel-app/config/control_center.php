<?php

return [
    'instance_slug' => env('CONTROL_CENTER_INSTANCE_SLUG', env('CONTROL_CENTER_CLIENT_SLUG')),
    'state_cache_ttl' => (int) env('CONTROL_CENTER_STATE_CACHE_TTL', 60),
    'fallback_state' => env('CONTROL_CENTER_FALLBACK_STATE', 'production'),
    'allowlist' => [
        'auth/login',
        'auth/logout',
        'v2/auth/logout',
        'api/v2/health',
        'v2/health',
        'health',
        'status',
        'up',
        'logout',
    ],
    'allowlist_prefixes' => [
        'build/',
        'assets/',
        'fonts/',
        'storage/',
        'control-center/assets/',
    ],
    'maintenance_permissions' => [
        'control_center.view',
        'superuser',
    ],
];
