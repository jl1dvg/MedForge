<?php

use App\Models\CrmOpportunity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stage_mappings', function (Blueprint $table): void {
            $table->id();
            // solicitud_procedimiento | consulta_examenes
            $table->string('source_type', 60);
            // Kanban slug / estado value from the operational module
            $table->string('source_state', 80);
            // CRM pipeline stage this source state maps to
            $table->string('crm_stage', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source_type', 'source_state']);
            $table->index(['source_type', 'crm_stage', 'is_active']);
        });

        // Seed default mappings — mirrors the previous hardcoded constants
        $now = now()->toDateTimeString();

        // Solicitudes kanban slugs → CRM stages
        $solicitudMappings = [
            'recibida'          => CrmOpportunity::STAGE_NUEVO,
            'en-atencion'       => CrmOpportunity::STAGE_EN_EVALUACION,
            'revision-codigos'  => CrmOpportunity::STAGE_EN_EVALUACION,
            'espera-documentos' => CrmOpportunity::STAGE_EN_EVALUACION,
            'apto-oftalmologo'  => CrmOpportunity::STAGE_EN_EVALUACION,
            'apto-anestesia'    => CrmOpportunity::STAGE_EN_EVALUACION,
            'listo-para-agenda' => CrmOpportunity::STAGE_EN_EVALUACION,
            'programada'        => CrmOpportunity::STAGE_COMPROMETIDO,
            'completado'        => CrmOpportunity::STAGE_GANADO,
        ];

        foreach ($solicitudMappings as $sourceState => $crmStage) {
            DB::table('crm_stage_mappings')->insert([
                'source_type'  => 'solicitud_procedimiento',
                'source_state' => $sourceState,
                'crm_stage'    => $crmStage,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Examen estados (normalized, accent-free) → CRM stages
        $examenMappings = [
            'recibido'              => CrmOpportunity::STAGE_NUEVO,
            'revision de cobertura' => CrmOpportunity::STAGE_EN_EVALUACION,
            'revision-cobertura'    => CrmOpportunity::STAGE_EN_EVALUACION,
            'listo para agenda'     => CrmOpportunity::STAGE_COMPROMETIDO,
            'listo-para-agenda'     => CrmOpportunity::STAGE_COMPROMETIDO,
            'completado'            => CrmOpportunity::STAGE_GANADO,
            'archivado'             => CrmOpportunity::STAGE_GANADO,
        ];

        foreach ($examenMappings as $sourceState => $crmStage) {
            DB::table('crm_stage_mappings')->insert([
                'source_type'  => 'consulta_examenes',
                'source_state' => $sourceState,
                'crm_stage'    => $crmStage,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stage_mappings');
    }
};
