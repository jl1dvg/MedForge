<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_prescription_id')->constrained('pharmacy_prescriptions')->cascadeOnDelete();
            $table->enum('estado', ['preparando', 'en_camino', 'entregada', 'cancelada'])->default('preparando');
            $table->text('direccion')->nullable();
            $table->text('observacion')->nullable();
            $table->date('fecha_programada')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->string('responsable')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_deliveries');
    }
};
