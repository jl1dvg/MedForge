<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_sedes', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 80);
            $table->string('abrev', 8)->default('');
            $table->time('apertura')->default('08:00:00');
            $table->time('cierre')->default('18:00:00');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_medicos', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('nombre', 120);
            $table->string('especialidad', 120)->default('');
            $table->json('areas');
            $table->string('sede_id', 32);
            $table->string('color', 16)->default('#5156be');
            $table->string('iniciales', 4)->default('');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreign('sede_id')->references('id')->on('agenda_sedes');
        });

        Schema::create('agenda_salas', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('sede_id', 32);
            $table->string('label', 80);
            $table->string('tipo', 32);
            $table->string('area', 32);
            $table->unsignedTinyInteger('cap')->default(1);
            $table->boolean('activo')->default(true);
            $table->foreign('sede_id')->references('id')->on('agenda_sedes');
        });

        Schema::create('agenda_tipos_cita', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 120);
            $table->string('area', 32);
            $table->unsignedSmallInteger('dur_minutos')->default(20);
            $table->json('requiere_tipo_sala');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_horarios', function (Blueprint $table): void {
            $table->id();
            $table->string('medico_id', 32);
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('sede_id', 32);
            $table->boolean('activo')->default(true);
            $table->foreign('medico_id')->references('id')->on('agenda_medicos');
            $table->foreign('sede_id')->references('id')->on('agenda_sedes');
        });

        Schema::create('agenda_bloqueos', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 8);
            $table->string('ref_id', 32);
            $table->date('fecha');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('motivo', 200)->default('');
            $table->string('tipo', 32)->default('otro');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
            $table->index('fecha');
        });

        Schema::create('agenda_citas_v3', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('sede_id', 32);
            $table->string('medico_id', 32);
            $table->string('sala_id', 32);
            $table->string('tipo_id', 32);
            $table->string('paciente', 200);
            $table->string('hc_number', 64)->default('');
            $table->unsignedTinyInteger('edad')->nullable();
            $table->string('afiliacion', 64)->default('');
            $table->string('tel', 32)->default('');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('estado', 32)->default('agendado');
            $table->string('whatsapp_estado', 32)->default('na');
            $table->time('hora_llegada')->nullable();
            $table->time('hora_sala')->nullable();
            $table->time('hora_consulta')->nullable();
            $table->time('hora_fin_atencion')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('sobreturno')->default(false);
            $table->boolean('hc_llena')->default(false);
            $table->json('hc_data')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['fecha', 'sede_id']);
            $table->index(['fecha', 'medico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_citas_v3');
        Schema::dropIfExists('agenda_bloqueos');
        Schema::dropIfExists('agenda_horarios');
        Schema::dropIfExists('agenda_tipos_cita');
        Schema::dropIfExists('agenda_salas');
        Schema::dropIfExists('agenda_medicos');
        Schema::dropIfExists('agenda_sedes');
    }
};
