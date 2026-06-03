<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->enum('afiliacion_tipo', ['particular', 'privado', 'fundacional', 'publico', 'sin_dato'])
                ->nullable()
                ->after('source_type')
                ->comment('Computed from the linked solicitud afiliacion — avoids live CASE WHEN regex');

            $table->index('afiliacion_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex(['afiliacion_tipo']);
            $table->dropColumn('afiliacion_tipo');
        });
    }
};
