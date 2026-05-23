<?php

/**
 * MedForge — domain constants shared across the application.
 *
 * subspecialties:
 *   Key         = stable slug stored in users.subespecialidad (comma-separated if multiple)
 *   label       = display name shown in the form UI
 *   catalog_key = value stored in whatsapp_sigcenter_doctor_catalog.subespecialidad
 *                 (must match what bot services filter on; backward-compatible with
 *                  the legacy 'oftalmologo general' key used by FlowSigcenterAgendaService)
 *
 * sedes:
 *   Key   = integer sede_id stored in users.sede (comma-separated if multiple)
 *   Value = display name shown in form and stored in catalog.sede_nombre
 */
return [

    'subspecialties' => [
        'segmento_anterior'           => ['label' => 'Segmento Anterior',           'catalog_key' => 'oftalmologo general'],
        'glaucoma'                    => ['label' => 'Glaucoma',                    'catalog_key' => 'glaucoma'],
        'retina_vitreo'               => ['label' => 'Retina y Vítreo',             'catalog_key' => 'retina y vitreo'],
        'oculoplastia'                => ['label' => 'Oculoplástia',               'catalog_key' => 'oculoplastia'],
        'oftalmopediatria'            => ['label' => 'Oftalmopediatría',            'catalog_key' => 'oftalmopediatria'],
        'cornea_refractiva'           => ['label' => 'Córnea y Cirugía Refractiva', 'catalog_key' => 'cornea y cirugia refractiva'],
        'oncologia_ocular'            => ['label' => 'Oncología Ocular',            'catalog_key' => 'oncologia ocular'],
        'contactologia_baja_vision'   => ['label' => 'Contactología y Baja Visión', 'catalog_key' => 'contactologia y baja vision'],
    ],

    'sedes' => [
        1  => 'Villa Club',
        16 => 'Ceibos',
    ],

];
