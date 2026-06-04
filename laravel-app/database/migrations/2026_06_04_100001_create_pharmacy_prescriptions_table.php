<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_patient_id')->constrained('pharmacy_patients')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('clinica')->nullable();
            $table->string('medico')->nullable();
            $table->enum('estado', ['pendiente', 'procesada', 'parcial', 'entregada', 'cancelada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->date('fecha_prescripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_prescriptions');
    }
};
