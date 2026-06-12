<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            // 'unica' | 'recurrente' | 'diagnostico'
            $table->string('tipo', 20)->default('unica');
            // null unless tipo = 'recurrente'
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            // Future: categoria, subcategoria, especialidad, tipo_servicio
            $table->timestamps();

            $table->index('grupo_codigo');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_procedure_rules');
    }
};
