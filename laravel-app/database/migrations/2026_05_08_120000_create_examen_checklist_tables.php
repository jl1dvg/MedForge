<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('examen_checklist')) {
            Schema::create('examen_checklist', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('examen_id');
                $table->string('etapa_slug', 96);
                $table->boolean('checked')->default(false);
                $table->timestamp('completado_at')->nullable();
                $table->unsignedBigInteger('completado_por')->nullable();
                $table->text('nota')->nullable();
                $table->timestamps();

                $table->unique(['examen_id', 'etapa_slug'], 'examen_checklist_examen_stage_unique');
                $table->index('examen_id', 'examen_checklist_examen_idx');
                $table->index('etapa_slug', 'examen_checklist_stage_idx');
            });
        }

        if (!Schema::hasTable('examen_checklist_log')) {
            Schema::create('examen_checklist_log', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('examen_id');
                $table->string('etapa_slug', 96);
                $table->string('accion', 32);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('nota')->nullable();
                $table->timestamp('old_completado_at')->nullable();
                $table->timestamp('new_completado_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['examen_id', 'etapa_slug'], 'examen_checklist_log_examen_stage_idx');
                $table->index('created_at', 'examen_checklist_log_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_checklist_log');
        Schema::dropIfExists('examen_checklist');
    }
};
