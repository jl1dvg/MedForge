<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_prescription_id')->constrained('pharmacy_prescriptions')->cascadeOnDelete();
            $table->string('nombre_medicamento');
            $table->string('principio_activo')->nullable();
            $table->string('presentacion')->nullable();
            $table->string('dosis')->nullable();
            $table->string('frecuencia')->nullable();
            $table->integer('duracion_dias')->nullable();
            $table->text('indicaciones')->nullable();
            $table->enum('disponibilidad', ['disponible', 'parcial', 'no_disponible'])->default('no_disponible');
            $table->foreignId('inventory_id')->nullable()->constrained('pharmacy_inventory')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_prescription_items');
    }
};
