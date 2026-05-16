<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Session Fallback
    |--------------------------------------------------------------------------
    |
    | Wave 1 keeps backward compatibility by allowing Laravel auth middleware
    | to bootstrap Auth::user() from PHPSESSID when present.
    |
    */
    'accept_legacy_session' => (bool) env('AUTH_ACCEPT_LEGACY_SESSION', true),

    /*
    |--------------------------------------------------------------------------
    | Legacy Compatibility Session Write
    |--------------------------------------------------------------------------
    |
    | Controls whether Laravel login writes the compatibility PHPSESSID
    | payload for still-legacy modules.
    |
    */
    'write_legacy_compat_session' => (bool) env('AUTH_WRITE_LEGACY_COMPAT_SESSION', true),
];

