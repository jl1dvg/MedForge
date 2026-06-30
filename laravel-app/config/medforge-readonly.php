<?php

/**
 * Read-only mode lockout for human UI write actions (billing, cirugías, CRM,
 * solicitudes, agenda, etc.). Bots/crons/queue workers/SigCenter integration
 * never authenticate through Laravel's session guard, so they are unaffected
 * regardless of this config — see RequireAppSession.
 *
 * 'mode':
 *   - auto: read-only is active only between start_date and end_date (inclusive)
 *   - on:   force read-only regardless of date (manual override, always locked)
 *   - off:  force normal read-write regardless of date (manual override, always open)
 */
return [

    'mode' => env('MEDFORGE_READONLY_MODE', 'auto'),

    'start_date' => env('MEDFORGE_READONLY_START', '2026-07-01 00:00:00'),
    'end_date' => env('MEDFORGE_READONLY_END', '2026-07-31 23:59:59'),

    'message' => env(
        'MEDFORGE_READONLY_MESSAGE',
        'Sistema en modo solo lectura. No se pueden guardar cambios en este momento.'
    ),

];
