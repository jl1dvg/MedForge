<?php

return [
    /*
     * 'legacy' → one opportunity per contact (current behaviour, unchanged).
     * 'intent' → episode-based model (Phase 2B — do not activate in production yet).
     */
    'intent_model_enabled' => env('CRM_OPPORTUNITY_MODEL', 'legacy') === 'intent',


    'urgency_threshold_hours' => [
        'whatsapp' => env('CRM_URGENCY_WA_HOURS', 6),
        'default'  => env('CRM_URGENCY_DEFAULT_HOURS', 48),
    ],

    'escalacion' => [
        // Days stuck in 'contactado' before escalating to commercial
        'dias_contactado'     => (int) env('CRM_ESC_DIAS_CONTACTADO', 7),
        // Days stuck in 'en_evaluacion' before escalating to commercial
        'dias_en_evaluacion'  => (int) env('CRM_ESC_DIAS_EN_EVALUACION', 14),
    ],
];
