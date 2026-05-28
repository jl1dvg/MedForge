<?php

return [
    'urgency_threshold_hours' => [
        'whatsapp' => env('CRM_URGENCY_WA_HOURS', 6),
        'default'  => env('CRM_URGENCY_DEFAULT_HOURS', 48),
    ],
];
