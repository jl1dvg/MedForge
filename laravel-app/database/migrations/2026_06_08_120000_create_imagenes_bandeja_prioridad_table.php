<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('imagenes_bandeja_prioridad')) {
            return;
        }

        Schema::create('imagenes_bandeja_prioridad', function (Blueprint $table): void {
            $table->id();

            // Referencia al procedimiento priorizado
            $table->unsignedBigInteger('procedimiento_id')->unique()
                ->comment('FK a procedimiento_proyectado.id — una entrada activa por examen');
            $table->string('form_id', 64)->nullable()->index()
                ->comment('Copia desnormalizada para búsquedas rápidas sin JOIN');

            // Datos de prioridad
            $table->enum('prioridad', ['urgente', 'pronto'])->index();
            $table->date('fecha_limite')->nullable();
            $table->string('responsable', 255)->nullable()
                ->comment('Nombre del médico responsable del informe');
            $table->text('motivo');

            // Auditoría de quién priorizó
            $table->unsignedBigInteger('solicitado_por')->nullable()->index()
                ->comment('users.id del usuario que creó la entrada');
            $table->string('solicitado_nombre', 255)->nullable()
                ->comment('Nombre desnormalizado para mostrar aunque el usuario se elimine');

            $table->timestamps();

            $table->index(['prioridad', 'fecha_limite'], 'idx_bandeja_prio_limite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_bandeja_prioridad');
    }
};
