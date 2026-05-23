<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some users already had subespecialidad stored as display names
 * (e.g. "Glaucoma", "Retina y Vítreo") rather than slugs.
 * This migration converts all known display names to canonical slugs.
 * Unknown values (e.g. "Uveitis") are left untouched.
 */
return new class extends Migration
{
    /** @var array<string, string>  display variants → slug */
    private const DISPLAY_TO_SLUG = [
        'glaucoma'                     => 'glaucoma',
        'retina y vitreo'              => 'retina_vitreo',
        'retina y vítreo'              => 'retina_vitreo',
        'oculoplastia'                 => 'oculoplastia',
        'oculoplástia'                 => 'oculoplastia',
        'oftalmopediatria'             => 'oftalmopediatria',
        'oftalmopediatría'             => 'oftalmopediatria',
        'cornea y cirugia refractiva'  => 'cornea_refractiva',
        'córnea y cirugía refractiva'  => 'cornea_refractiva',
        'oncologia ocular'             => 'oncologia_ocular',
        'oncología ocular'             => 'oncologia_ocular',
        'contactologia y baja vision'  => 'contactologia_baja_vision',
        'contactología y baja visión'  => 'contactologia_baja_vision',
        'segmento anterior'            => 'segmento_anterior',
        'oftalmologo general'          => 'segmento_anterior',
    ];

    public function up(): void
    {
        foreach (self::DISPLAY_TO_SLUG as $displayLower => $slug) {
            DB::statement(
                "UPDATE users SET subespecialidad = ? WHERE LOWER(TRIM(subespecialidad)) = ?",
                [$slug, $displayLower]
            );
        }
    }

    public function down(): void
    {
        // Cannot reliably reverse without knowing original case; no-op.
    }
};
