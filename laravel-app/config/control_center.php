<?php

return [
    'client_slug' => env('CONTROL_CENTER_CLIENT_SLUG'),
    'state_cache_ttl' => (int) env('CONTROL_CENTER_STATE_CACHE_TTL', 60),
    'fallback_state' => env('CONTROL_CENTER_FALLBACK_STATE', 'production'),
    'allowlist' => [
        'auth/login',
        'auth/logout',
        'v2/auth/logout',
        'logout',
    ],
    'maintenance_permissions' => [
        'control_center.view',
        'superuser',
    ],
];
