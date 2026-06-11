<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocolo_huellas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('protocolo_id')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('evento', 50)->default('guardado');
            $table->dateTime('creado_en');
            $table->dateTime('actualizado_en');

            $table->unique(['protocolo_id', 'usuario_id'], 'protocolo_huellas_protocolo_usuario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocolo_huellas');
    }
};
