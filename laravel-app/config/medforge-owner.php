<?php

/**
 * Platform-owner access config.
 * Only the email configured here can access /owner/* routes.
 * Set MEDFORGE_OWNER_EMAIL in .env to your own email.
 */
return [
    'email' => env('MEDFORGE_OWNER_EMAIL'),
];
