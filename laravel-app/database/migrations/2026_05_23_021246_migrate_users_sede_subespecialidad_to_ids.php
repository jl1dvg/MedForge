<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sede: text → comma-separated IDs ────────────────────────────
        // Normalize by stripping whitespace/dashes/underscores and uppercasing,
        // then map to IDs. Values not matching any known pattern are left
        // untouched (catalog sync will skip them and log a warning).

        // Both sedes
        DB::statement("
            UPDATE users
            SET sede = '1,16'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  IN ('CEIBOSVILLACLUB', 'VILLACLUBYCEIBOS')
        ");

        // Villa Club only
        DB::statement("
            UPDATE users
            SET sede = '1'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  = 'VILLACLUB'
        ");

        // Ceibos only
        DB::statement("
            UPDATE users
            SET sede = '16'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  = 'CEIBOS'
        ");

        // ── Subespecialidad: legacy string → slug ────────────────────────
        DB::statement("
            UPDATE users
            SET subespecialidad = 'segmento_anterior'
            WHERE LOWER(TRIM(subespecialidad)) = 'oftalmologo general'
        ");
    }

    public function down(): void
    {
        // Reverse: slugs/IDs → legacy text

        DB::statement("
            UPDATE users
            SET subespecialidad = 'oftalmologo general'
            WHERE subespecialidad = 'segmento_anterior'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'CEIBOSVILLACLUB'
            WHERE sede = '1,16'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'VILLACLUB'
            WHERE sede = '1'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'CEIBOS'
            WHERE sede = '16'
        ");
    }
};
