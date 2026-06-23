<?php

/**
 * Global, site-wide notice banner shown above the app shell on every
 * authenticated MedForge screen. Change message/colors/dates here (or via
 * the matching env vars) — no Blade/CSS edits required.
 */
return [

    'enabled' => env('MEDFORGE_BANNER_ENABLED', true),

    // warning | danger | info — maps to a color variant in medforge-global-banner.css
    'variant' => env('MEDFORGE_BANNER_VARIANT', 'warning'),

    'icon' => env('MEDFORGE_BANNER_ICON', 'mdi-alert-outline'),

    'title' => env('MEDFORGE_BANNER_TITLE', 'Aviso Importante'),

    'message' => env(
        'MEDFORGE_BANNER_MESSAGE',
        'MedForge estará operativo hasta el 30 de junio. Desde el 1 de julio hasta el 31 de julio el sistema permanecerá disponible únicamente en modo solo lectura.'
    ),

];
